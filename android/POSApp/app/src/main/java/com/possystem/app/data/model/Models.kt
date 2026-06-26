package com.possystem.app.data.model

import com.google.gson.annotations.SerializedName

data class LoginRequest(val username: String, val password: String)
data class LoginResponse(
    @SerializedName("access_token") val accessToken: String,
    @SerializedName("refresh_token") val refreshToken: String,
    val user: User?
)
data class RefreshTokenRequest(@SerializedName("refresh_token") val refreshToken: String)
data class AuthResponse(val message: String?, val user: User?)

data class User(
    val id: Int,
    val username: String,
    @SerializedName("full_name") val fullName: String,
    val role: String,
    val status: String?,
    @SerializedName("store_id") val storeId: Int?,
    val email: String?
)

data class Product(
    val id: Int,
    val name: String,
    val barcode: String,
    val category: String?,
    val price: Double,
    @SerializedName("cost_price") val costPrice: Double?,
    @SerializedName("stock_quantity") val stockQuantity: Int,
    @SerializedName("low_stock_threshold") val lowStockThreshold: Int?,
    val supplier: String?,
    val image: String?,
    val status: String?,
    @SerializedName("store_id") val storeId: Int?,
    @SerializedName("expiry_date") val expiryDate: String?
)

data class ProductRequest(
    val name: String,
    val barcode: String,
    val category: String?,
    val price: Double,
    @SerializedName("cost_price") val costPrice: Double?,
    @SerializedName("stock_quantity") val stockQuantity: Int?,
    @SerializedName("low_stock_threshold") val lowStockThreshold: Int?,
    val supplier: String?,
    val status: String?
)

data class Sale(
    val id: Int,
    @SerializedName("receipt_number") val receiptNumber: String,
    @SerializedName("cashier_id") val cashierId: Int,
    @SerializedName("cashier_name") val cashierName: String?,
    @SerializedName("customer_name") val customerName: String?,
    val subtotal: Double,
    val tax: Double,
    @SerializedName("tax_rate") val taxRate: Double?,
    val discount: Double?,
    @SerializedName("discount_type") val discountType: String?,
    val total: Double,
    @SerializedName("payment_method") val paymentMethod: String?,
    @SerializedName("cash_amount") val cashAmount: Double?,
    @SerializedName("card_amount") val cardAmount: Double?,
    @SerializedName("change_amount") val changeAmount: Double?,
    val status: String?,
    @SerializedName("created_at") val createdAt: String?,
    val items: List<SaleItem>?
)

data class SaleItem(
    val id: Int?,
    @SerializedName("sale_id") val saleId: Int?,
    @SerializedName("product_id") val productId: Int,
    @SerializedName("product_name") val productName: String,
    val quantity: Int,
    val price: Double,
    @SerializedName("cost_price") val costPrice: Double?,
    val total: Double
)

data class CompleteSaleRequest(
    val items: List<SaleItemRequest>,
    @SerializedName("payment_method") val paymentMethod: String,
    @SerializedName("cash_amount") val cashAmount: Double?,
    @SerializedName("card_amount") val cardAmount: Double?,
    val discount: Double?,
    @SerializedName("discount_type") val discountType: String?,
    @SerializedName("customer_id") val customerId: Int?,
    @SerializedName("customer_name") val customerName: String?
)

data class SaleItemRequest(
    @SerializedName("product_id") val productId: Int,
    val quantity: Int,
    val price: Double
)

data class Customer(
    val id: Int,
    val name: String,
    val phone: String?,
    val email: String?,
    val address: String?,
    val notes: String?,
    @SerializedName("visit_count") val visitCount: Int?,
    @SerializedName("total_spent") val totalSpent: Double?,
    @SerializedName("created_at") val createdAt: String?
)

data class CustomerRequest(
    val name: String,
    val phone: String?,
    val email: String?,
    val address: String?,
    val notes: String?
)

data class DashboardData(
    @SerializedName("today_sales") val todaySales: Double?,
    @SerializedName("today_transactions") val todayTransactions: Int?,
    @SerializedName("daily_target") val dailyTarget: Double?,
    @SerializedName("target_progress") val targetProgress: Double?,
    @SerializedName("low_stock_count") val lowStockCount: Int?,
    @SerializedName("low_stock_products") val lowStockProducts: List<Product>?,
    @SerializedName("recent_sales") val recentSales: List<Sale>?,
    @SerializedName("best_sellers") val bestSellers: List<Product>?,
    @SerializedName("expiring_products") val expiringProducts: List<Product>?
)

data class StockAdjustmentRequest(
    @SerializedName("product_id") val productId: Int,
    val type: String,
    val quantity: Int,
    val reason: String?
)

data class StockAdjustment(
    val id: Int,
    @SerializedName("product_id") val productId: Int,
    @SerializedName("user_name") val userName: String?,
    val type: String,
    val quantity: Int,
    @SerializedName("previous_stock") val previousStock: Int?,
    @SerializedName("new_stock") val newStock: Int?,
    val reason: String?,
    @SerializedName("created_at") val createdAt: String?
)

data class ReturnRequest(
    val id: Int?,
    @SerializedName("sale_id") val saleId: Int?,
    @SerializedName("receipt_number") val receiptNumber: String?,
    @SerializedName("product_id") val productId: Int?,
    @SerializedName("product_name") val productName: String?,
    val quantity: Int?,
    val reason: String?,
    val resolution: String?,
    val status: String?,
    @SerializedName("created_at") val createdAt: String?
)

data class ReturnSubmitRequest(
    @SerializedName("sale_id") val saleId: Int,
    val items: List<ReturnItemRequest>,
    val reason: String,
    val resolution: String
)

data class ReturnItemRequest(
    @SerializedName("product_id") val productId: Int,
    val quantity: Int
)

data class Message(
    val id: Int,
    @SerializedName("sender_id") val senderId: Int?,
    @SerializedName("sender_name") val senderName: String?,
    val message: String?,
    @SerializedName("is_read") val isRead: Boolean?,
    @SerializedName("created_at") val createdAt: String?
)

data class MessageRequest(val message: String)

data class Settings(
    @SerializedName("store_name") val storeName: String?,
    @SerializedName("store_address") val storeAddress: String?,
    @SerializedName("store_contact") val storeContact: String?,
    @SerializedName("tax_rate") val taxRate: Double?,
    val currency: String?,
    @SerializedName("receipt_footer") val receiptFooter: String?,
    @SerializedName("daily_target") val dailyTarget: Double?,
    @SerializedName("self_checkout_enabled") val selfCheckoutEnabled: Boolean?
)

data class SettingUpdate(
    @SerializedName("store_name") val storeName: String?,
    @SerializedName("store_address") val storeAddress: String?,
    @SerializedName("store_contact") val storeContact: String?,
    @SerializedName("tax_rate") val taxRate: Double?,
    val currency: String?,
    @SerializedName("receipt_footer") val receiptFooter: String?,
    @SerializedName("daily_target") val dailyTarget: Double?
)

data class ReportData(
    val period: String?,
    val labels: List<String>?,
    val values: List<Double>?,
    val data: List<Map<String, Any>>?
)

data class ApiResponse<T>(
    val success: Boolean?,
    val message: String?,
    val data: T?
)

data class PaginatedResponse<T>(
    val success: Boolean?,
    val message: String?,
    val data: List<T>?,
    val total: Int?,
    val page: Int?,
    @SerializedName("per_page") val perPage: Int?
)

data class HoldSaleRequest(
    val items: List<SaleItemRequest>,
    @SerializedName("customer_name") val customerName: String?
)

data class InventoryProduct(
    val id: Int,
    val name: String,
    val barcode: String?,
    @SerializedName("stock_quantity") val stockQuantity: Int,
    @SerializedName("cost_price") val costPrice: Double?,
    val price: Double,
    @SerializedName("low_stock_threshold") val lowStockThreshold: Int?,
    val status: String?,
    val adjustments: List<StockAdjustment>?
)
