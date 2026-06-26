package com.possystem.app.data.api

import com.possystem.app.data.model.*
import retrofit2.Response
import retrofit2.http.*

interface ApiService {
    @POST("auth/login")
    suspend fun login(@Body request: LoginRequest): Response<LoginResponse>

    @POST("auth/refresh")
    suspend fun refreshToken(@Body request: RefreshTokenRequest): Response<LoginResponse>

    @POST("auth/logout")
    suspend fun logout(): Response<ApiResponse<Any>>

    @GET("auth/me")
    suspend fun getMe(): Response<ApiResponse<User>>

    @GET("dashboard")
    suspend fun getDashboard(): Response<ApiResponse<DashboardData>>

    @GET("products")
    suspend fun getProducts(
        @Query("search") search: String? = null,
        @Query("category") category: String? = null,
        @Query("stock") stock: String? = null,
        @Query("page") page: Int? = null
    ): Response<ApiResponse<List<Product>>>

    @GET("products/{id}")
    suspend fun getProduct(@Path("id") id: Int): Response<ApiResponse<Product>>

    @GET("products/{barcode}/barcode")
    suspend fun getProductByBarcode(@Path("barcode") barcode: String): Response<ApiResponse<Product>>

    @POST("products")
    suspend fun createProduct(@Body product: ProductRequest): Response<ApiResponse<Product>>

    @PUT("products/{id}")
    suspend fun updateProduct(@Path("id") id: Int, @Body product: ProductRequest): Response<ApiResponse<Product>>

    @DELETE("products/{id}")
    suspend fun deleteProduct(@Path("id") id: Int): Response<ApiResponse<Any>>

    @GET("sales")
    suspend fun getSales(
        @Query("search") search: String? = null,
        @Query("period") period: String? = null,
        @Query("page") page: Int? = null
    ): Response<ApiResponse<List<Sale>>>

    @GET("sales/{id}")
    suspend fun getSale(@Path("id") id: Int): Response<ApiResponse<Sale>>

    @GET("sales/held")
    suspend fun getHeldSales(): Response<ApiResponse<List<Any>>>

    @POST("sales/complete")
    suspend fun completeSale(@Body request: CompleteSaleRequest): Response<ApiResponse<Sale>>

    @POST("sales/hold")
    suspend fun holdSale(@Body request: HoldSaleRequest): Response<ApiResponse<Any>>

    @DELETE("sales/held/{id}")
    suspend fun deleteHeldSale(@Path("id") id: Int): Response<ApiResponse<Any>>

    @GET("customers")
    suspend fun getCustomers(
        @Query("search") search: String? = null,
        @Query("page") page: Int? = null
    ): Response<ApiResponse<List<Customer>>>

    @GET("customers/{id}")
    suspend fun getCustomer(@Path("id") id: Int): Response<ApiResponse<Customer>>

    @POST("customers")
    suspend fun createCustomer(@Body customer: CustomerRequest): Response<ApiResponse<Customer>>

    @PUT("customers/{id}")
    suspend fun updateCustomer(
        @Path("id") id: Int,
        @Body customer: CustomerRequest
    ): Response<ApiResponse<Customer>>

    @GET("inventory")
    suspend fun getInventory(): Response<ApiResponse<List<InventoryProduct>>>

    @GET("inventory/{id}")
    suspend fun getInventoryProduct(@Path("id") id: Int): Response<ApiResponse<InventoryProduct>>

    @POST("inventory/adjust")
    suspend fun adjustStock(@Body request: StockAdjustmentRequest): Response<ApiResponse<Any>>

    @GET("returns")
    suspend fun getReturns(
        @Query("period") period: String? = null,
        @Query("status") status: String? = null
    ): Response<ApiResponse<List<ReturnRequest>>>

    @GET("returns/{id}")
    suspend fun getReturn(@Path("id") id: Int): Response<ApiResponse<ReturnRequest>>

    @GET("returns/pending")
    suspend fun getPendingReturns(): Response<ApiResponse<List<ReturnRequest>>>

    @POST("returns")
    suspend fun submitReturn(@Body request: ReturnSubmitRequest): Response<ApiResponse<ReturnRequest>>

    @PUT("returns/{id}/status")
    suspend fun updateReturnStatus(
        @Path("id") id: Int,
        @Body status: Map<String, String>
    ): Response<ApiResponse<Any>>

    @GET("reports/sales")
    suspend fun getSalesReport(@Query("period") period: String? = null): Response<ApiResponse<ReportData>>

    @GET("reports/products")
    suspend fun getProductsReport(@Query("period") period: String? = null): Response<ApiResponse<ReportData>>

    @GET("reports/cashiers")
    suspend fun getCashiersReport(@Query("period") period: String? = null): Response<ApiResponse<ReportData>>

    @GET("reports/profit")
    suspend fun getProfitReport(@Query("period") period: String? = null): Response<ApiResponse<ReportData>>

    @GET("reports/inventory")
    suspend fun getInventoryReport(): Response<ApiResponse<ReportData>>

    @GET("messages")
    suspend fun getMessages(): Response<ApiResponse<List<Message>>>

    @GET("messages/unread")
    suspend fun getUnreadMessages(): Response<ApiResponse<List<Message>>>

    @POST("messages")
    suspend fun sendMessage(@Body request: MessageRequest): Response<ApiResponse<Message>>

    @PUT("messages/{id}/read")
    suspend fun markMessageRead(@Path("id") id: Int): Response<ApiResponse<Any>>

    @GET("settings")
    suspend fun getSettings(): Response<ApiResponse<Settings>>

    @PUT("settings")
    suspend fun updateSettings(@Body settings: SettingUpdate): Response<ApiResponse<Settings>>

    @GET("users")
    suspend fun getUsers(): Response<ApiResponse<List<User>>>

    @GET("users/{id}")
    suspend fun getUser(@Path("id") id: Int): Response<ApiResponse<User>>
}
