# Dev stack: app on http://localhost:8095, Vite on :5176, mock accounts on :8096.
# Shared targets live in common.mk and android.mk (synced from ~/Sites/mossyleaf-studio/toolchain).

IMAGE ?= docker.io/injust/muhmeal
DEPLOY_HOST ?= debian@duprat.cloud
DEPLOY_DIR ?= /mnt/muhmeal
APK_ALIAS = muhmeal
APK_CN = Muhmeal

include common.mk
include android.mk
