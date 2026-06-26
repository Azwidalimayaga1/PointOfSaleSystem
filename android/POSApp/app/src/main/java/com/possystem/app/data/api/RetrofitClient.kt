package com.possystem.app.data.api

import com.possystem.app.BuildConfig
import okhttp3.Interceptor
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import java.util.concurrent.TimeUnit

object RetrofitClient {
    private var authToken: String? = null
    private var refreshToken: String? = null

    fun setTokens(access: String, refresh: String) {
        authToken = access
        refreshToken = refresh
    }

    fun clearTokens() {
        authToken = null
        refreshToken = null
    }

    fun getAuthToken(): String? = authToken
    fun getRefreshToken(): String? = refreshToken

    private val authInterceptor = Interceptor { chain ->
        val request = chain.request()
        val builder = request.newBuilder()
        authToken?.let {
            builder.addHeader("Authorization", "Bearer $it")
        }
        chain.proceed(builder.build())
    }

    private val loggingInterceptor = HttpLoggingInterceptor().apply {
        level = if (BuildConfig.DEBUG)
            HttpLoggingInterceptor.Level.BODY
        else
            HttpLoggingInterceptor.Level.NONE
    }

    private val okHttpClient = OkHttpClient.Builder()
        .addInterceptor(authInterceptor)
        .addInterceptor(loggingInterceptor)
        .connectTimeout(30, TimeUnit.SECONDS)
        .readTimeout(30, TimeUnit.SECONDS)
        .writeTimeout(30, TimeUnit.SECONDS)
        .build()

    private val retrofit = Retrofit.Builder()
        .baseUrl(BuildConfig.BASE_URL)
        .client(okHttpClient)
        .addConverterFactory(GsonConverterFactory.create())
        .build()

    val apiService: ApiService = retrofit.create(ApiService::class.java)
}
