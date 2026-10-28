DC = docker compose
NO_TTY = $(if $(CI),-T)
EXEC = $(DC) exec $(NO_TTY)
RUN = $(DC) run $(NO_TTY) --rm
PHP = $(EXEC) php
CONSOLE = $(PHP) php bin/console
PLAYWRIGHT_ARGS ?=

IMAGE ?= docker.io/injust/mossy-meals
TAG ?= $(shell git rev-parse --short=7 HEAD)
PLATFORM ?= linux/amd64
DEPLOY_HOST ?= debian@duprat.cloud
DEPLOY_DIR ?= /mnt/mossy-meals
REMOTE_DOCKER ?= sudo -n docker
E2E_ASSETS_DIR ?= build-e2e
CI_BUNDLE ?= ci-build.tgz
export E2E_ASSETS_DIR
OUTPUT_SYNC = $(if $(filter output-sync,$(.FEATURES)),--output-sync=target)
BUILD = docker buildx build --platform $(PLATFORM) --target prod -t $(IMAGE):$(TAG) -t $(IMAGE):latest
ANDROID_HOME ?= $(HOME)/Library/Android/sdk
ANDROID_TOOLS_ZIP = commandlinetools-mac_arm64-15859902_latest.zip
ANDROID_TOOLS_SHA256 = 835b62a26162b229b441d1f6d4680383815a270809eb33522c0d480fa5002c4e
SDKMANAGER = $(ANDROID_HOME)/cmdline-tools/latest/bin/sdkmanager
ANDROID_ENV = JAVA_HOME=$(shell java -XshowSettings:properties -version 2>&1 | sed -n 's/^ *java.home = //p') ANDROID_HOME=$(ANDROID_HOME)
GRADLEW = cd android && $(ANDROID_ENV) ./gradlew
APK = android/app/build/outputs/apk/release/app-release.apk

.PHONY: up down build install assets assets-e2e db db-test fixtures migration test test-unit test-functional test-js deptrac phpstan cs cs-fix audit audit-php audit-js e2e e2e-run qa ci ci-up ci-build ci-bundle ci-unbundle ci-warmup ci-checks ci-e2e image push deploy deploy-files android-sdk android-test apk apk-install

up: ## Start the stack (app on http://localhost:8095, Vite on :5176, mock mossyleaf accounts on :8096)
	$(DC) up -d --wait php database node oidc

down:
	$(DC) down

build:
	$(DC) build

install:
	$(PHP) composer install
	$(RUN) --no-deps node npm install

assets:
	$(RUN) --no-deps node npm run build

assets-e2e:
	$(RUN) --no-deps -e ASSETS_DIR=$(E2E_ASSETS_DIR) node npm run build

db: ## Create and migrate the dev database
	$(CONSOLE) doctrine:database:create --if-not-exists
	$(CONSOLE) doctrine:migrations:migrate --no-interaction --allow-no-migration

fixtures: db ## Reset the dev database with demo data
	$(CONSOLE) doctrine:fixtures:load --no-interaction --purge-with-truncate

db-test: ## Create and migrate the test database
	$(CONSOLE) doctrine:database:create --if-not-exists --env=test
	$(CONSOLE) doctrine:migrations:migrate --no-interaction --allow-no-migration --env=test

migration: ## Generate a migration from mapping changes
	$(CONSOLE) doctrine:migrations:diff --no-interaction

test: db-test ## PHPUnit (unit + functional)
	$(PHP) php bin/phpunit

test-unit:
	$(PHP) php bin/phpunit --testsuite unit

test-functional: db-test
	$(PHP) php bin/phpunit --testsuite functional

test-js: ## Vitest (pure JS modules)
	$(RUN) --no-deps node npx vitest run

deptrac: ## Check onion layer dependencies
	$(PHP) vendor/bin/deptrac analyse --no-progress

cs: ## Coding standard check
	$(EXEC) -e PHP_CS_FIXER_IGNORE_ENV=1 php vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix:
	$(EXEC) -e PHP_CS_FIXER_IGNORE_ENV=1 php vendor/bin/php-cs-fixer fix

phpstan: ## Static analysis (level 10)
	$(PHP) vendor/bin/phpstan analyse --no-progress --memory-limit=1G

audit: audit-php audit-js ## Known vulnerabilities in the locked dependencies

audit-php:
	$(PHP) composer audit

audit-js:
	$(RUN) --no-deps node npm audit --omit=dev --audit-level=high

e2e: assets-e2e e2e-run ## Playwright against a dedicated APP_ENV=test container

e2e-run: ## Playwright against already built assets (E2E_ASSETS_DIR, default build-e2e)
	$(DC) --profile e2e up -d --wait php-e2e
	$(EXEC) php-e2e php bin/console cache:clear --env=test
	$(EXEC) php-e2e php bin/console doctrine:database:drop --force --if-exists --env=test
	$(EXEC) php-e2e php bin/console doctrine:database:create --env=test
	$(EXEC) php-e2e php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration --env=test
	$(EXEC) php-e2e php bin/console doctrine:fixtures:load --no-interaction --env=test
	$(DC) --profile e2e run $(NO_TTY) --rm playwright sh -c "npm ci --no-audit --no-fund && ./node_modules/.bin/playwright test $(PLAYWRIGHT_ARGS)"

qa: cs phpstan deptrac audit test test-js e2e

ci: ci-build ## Full suite from a fresh checkout (GitHub Actions runs ci-build once, then ci-checks and ci-e2e shards in parallel jobs)
	$(MAKE) -j4 $(OUTPUT_SYNC) ci-checks
	$(MAKE) ci-e2e

ci-up:
	$(DC) up -d --wait php database oidc

ci-build: ci-up ## Install PHP and JS dependencies and build the production assets
	$(PHP) composer install --no-interaction --no-progress
	$(RUN) --no-deps node npm ci --no-audit --no-fund
	$(MAKE) assets

ci-bundle: ## Pack what ci-build produced for the CI test jobs (CI_BUNDLE)
	tar -czf $(CI_BUNDLE) vendor node_modules public/build $(wildcard public/bundles)

ci-unbundle: ## Unpack the ci-build output (CI_BUNDLE)
	tar -xzf $(CI_BUNDLE)

ci-warmup: ci-up ## Start the dev stack and warm its cache (PHPStan reads the dev container)
	$(CONSOLE) cache:warmup

ci-e2e: ## Playwright on the production build (PLAYWRIGHT_ARGS to pick a shard)
	$(MAKE) e2e-run E2E_ASSETS_DIR=build

ci-checks: cs phpstan deptrac audit test test-js

image: ## Build the production image locally (IMAGE, TAG, PLATFORM)
	$(BUILD) --load .

push: qa ## Run the full suite, then build and push the production image by hand (CI does it on every push to main; run docker login first)
	$(BUILD) --push .

deploy-files: ## Copy deploy/ (compose, env template, README) to DEPLOY_HOST:DEPLOY_DIR
	@test -n "$(DEPLOY_HOST)" || { echo "Set DEPLOY_HOST=user@server"; exit 1; }
	ssh $(DEPLOY_HOST) 'mkdir -p $(DEPLOY_DIR)'
	scp deploy/compose.yaml deploy/.env.dist deploy/README.md $(DEPLOY_HOST):$(DEPLOY_DIR)/

deploy: ## Run IMAGE:TAG (published by CI) on DEPLOY_HOST: set TAG in its .env, pull, restart
	@curl -sf -o /dev/null https://hub.docker.com/v2/repositories/$(patsubst docker.io/%,%,$(IMAGE))/tags/$(TAG) || { echo "$(IMAGE):$(TAG) is not published: CI only publishes it once the full test suite passes (still running, or failed?)"; exit 1; }
	scp deploy/compose.yaml $(DEPLOY_HOST):$(DEPLOY_DIR)/
	ssh $(DEPLOY_HOST) 'set -e; cd $(DEPLOY_DIR); \
		sed -i "s|^IMAGE=.*|IMAGE=$(IMAGE)|; s|^TAG=.*|TAG=$(TAG)|" .env; \
		$(REMOTE_DOCKER) compose pull app; \
		$(REMOTE_DOCKER) compose up -d --remove-orphans'

android-sdk: ## Install the Android SDK on the Mac (command-line tools 22.0 for arm64, then platform and build tools, in ANDROID_HOME)
	@test -x $(SDKMANAGER) || ( tmp=$$(mktemp -d) && cd $$tmp && \
		curl -fsSLO -C - --retry 5 --retry-all-errors --speed-limit 10000 --speed-time 30 https://dl.google.com/android/repository/$(ANDROID_TOOLS_ZIP) && \
		echo "$(ANDROID_TOOLS_SHA256)  $(ANDROID_TOOLS_ZIP)" | shasum -a 256 -c - && \
		unzip -q $(ANDROID_TOOLS_ZIP) && mkdir -p $(ANDROID_HOME)/cmdline-tools && \
		mv cmdline-tools $(ANDROID_HOME)/cmdline-tools/latest && rm -rf $$tmp )
	yes | $(ANDROID_ENV) $(SDKMANAGER) --licenses
	$(ANDROID_ENV) $(SDKMANAGER) "platforms;android-36" "build-tools;36.0.0" platform-tools

android-test: ## Android app JVM unit tests and lint
	$(GRADLEW) test lintRelease

apk: ## Build the signed Android release APK (creates android/keystore.jks and keystore.properties once: back them up, updates need the same key)
	@[ -f android/keystore.jks ] || [ -f android/keystore.properties ] || ( password=$$(openssl rand -hex 24) && umask 077 && \
		KEYSTORE_PASSWORD=$$password keytool -genkeypair -keystore android/keystore.jks -storetype PKCS12 \
			-alias mossy-meals -keyalg RSA -keysize 4096 -validity 10000 -dname CN=MossyMeals \
			-storepass:env KEYSTORE_PASSWORD -keypass:env KEYSTORE_PASSWORD && \
		printf 'storeFile=keystore.jks\nstorePassword=%s\nkeyAlias=mossy-meals\n' $$password > android/keystore.properties )
	@[ -f android/keystore.jks ] && [ -f android/keystore.properties ] || { echo 'android/keystore.jks and android/keystore.properties go together: restore the missing one from your backup' >&2; exit 1; }
	$(GRADLEW) assembleRelease -PversionCode=$$(git rev-list --count HEAD)

apk-install: ## Install the release APK on the phone adb sees (USB or Wi-Fi pairing), over the installed version
	$(ANDROID_HOME)/platform-tools/adb install -r $(APK)
