import java.util.Properties

plugins {
    id("com.android.application")
}

val keystore: File = rootProject.file("keystore.properties")
val code = providers.gradleProperty("versionCode").orNull?.toInt() ?: 1
val appUrl = Properties().apply {
    rootProject.file("local.properties").takeIf(File::exists)?.inputStream()?.use(::load)
}.getProperty("appUrl")?.trim()?.trimEnd('/')?.takeIf { it.startsWith("https://") }
    ?: throw GradleException("Set appUrl=https://<app host> in android/local.properties (git-ignored): the app has no built-in address.")

android {
    namespace = "cloud.duprat.muhmeal"
    compileSdk = 36

    defaultConfig {
        applicationId = "cloud.duprat.muhmeal"
        minSdk = 26
        targetSdk = 36
        versionCode = code
        versionName = code.toString()
        buildConfigField("String", "BASE_URL", "\"$appUrl\"")
    }

    signingConfigs {
        if (keystore.exists()) {
            create("release") {
                val properties = Properties().apply { keystore.inputStream().use(::load) }
                storeFile = rootProject.file(properties.getProperty("storeFile"))
                storePassword = properties.getProperty("storePassword")
                keyAlias = properties.getProperty("keyAlias")
                keyPassword = properties.getProperty("storePassword")
            }
        }
    }

    buildTypes {
        release {
            signingConfig = signingConfigs.findByName("release")
        }
    }

    buildFeatures {
        buildConfig = true
    }

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }
}

dependencies {
    implementation("androidx.activity:activity:1.13.0")
    testImplementation("junit:junit:4.13.2")
}
