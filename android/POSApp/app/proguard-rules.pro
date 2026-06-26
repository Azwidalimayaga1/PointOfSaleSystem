# Retrofit
-keepattributes Signature
-keepattributes *Annotation*
-keep class com.possystem.app.data.model.** { *; }
-keep class com.possystem.app.data.api.** { *; }
-dontwarn okhttp3.**
-dontwarn retrofit2.**
