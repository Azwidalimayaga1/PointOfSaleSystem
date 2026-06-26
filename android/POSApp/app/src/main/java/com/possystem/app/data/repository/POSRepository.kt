package com.possystem.app.data.repository

import com.possystem.app.data.api.RetrofitClient
import com.possystem.app.data.model.*

class POSRepository {
    private val api = RetrofitClient.apiService

    suspend fun login(username: String, password: String): Result<LoginResponse> = runCatching {
        val response = api.login(LoginRequest(username, password))
        if (response.isSuccessful) {
            response.body() ?: throw Exception("Empty response")
        } else {
            throw Exception(response.errorBody()?.string() ?: "Login failed")
        }
    }

    suspend fun refreshToken(): Result<LoginResponse> = runCatching {
        val rt = RetrofitClient.getRefreshToken() ?: throw Exception("No refresh token")
        val response = api.refreshToken(RefreshTokenRequest(rt))
        if (response.isSuccessful) {
            response.body() ?: throw Exception("Empty response")
        } else {
            throw Exception("Token refresh failed")
        }
    }

    suspend fun getDashboard(): Result<DashboardData> = runCatching {
        val response = api.getDashboard()
        if (response.isSuccessful) {
            response.body()?.data ?: throw Exception("Empty dashboard data")
        } else {
            throw Exception(response.errorBody()?.string() ?: "Failed to load dashboard")
        }
    }

    suspend fun getProducts(search: String? = null, category: String? = null, stock: String? = null): Result<List<Product>> = runCatching {
        val response = api.getProducts(search, category, stock)
        if (response.isSuccessful) {
            response.body()?.data ?: emptyList()
        } else {
            throw Exception(response.errorBody()?.string() ?: "Failed to load products")
        }
    }

    suspend fun getProduct(id: Int): Result<Product> = runCatching {
        val response = api.getProduct(id)
        if (response.isSuccessful) {
            response.body()?.data ?: throw Exception("Product not found")
        } else {
            throw Exception(response.errorBody()?.string() ?: "Failed to load product")
        }
    }

    suspend fun getProductByBarcode(barcode: String): Result<Product> = runCatching {
        val response = api.getProductByBarcode(barcode)
        if (response.isSuccessful) {
            response.body()?.data ?: throw Exception("Product not found")
        } else {
            throw Exception(response.errorBody()?.string() ?: "Product not found")
        }
    }

    suspend fun createProduct(request: ProductRequest): Result<Product> = runCatching {
        val response = api.createProduct(request)
        if (response.isSuccessful) {
            response.body()?.data ?: throw Exception("Failed to create product")
        } else {
            throw Exception(response.errorBody()?.string() ?: "Failed to create product")
        }
    }

    suspend fun updateProduct(id: Int, request: ProductRequest): Result<Product> = runCatching {
        val response = api.updateProduct(id, request)
        if (response.isSuccessful) {
            response.body()?.data ?: throw Exception("Failed to update product")
        } else {
            throw Exception(response.errorBody()?.string() ?: "Failed to update product")
        }
    }

    suspend fun deleteProduct(id: Int): Result<Unit> = runCatching {
        val response = api.deleteProduct(id)
        if (!response.isSuccessful) {
            throw Exception(response.errorBody()?.string() ?: "Failed to delete product")
        }
    }

    suspend fun completeSale(request: CompleteSaleRequest): Result<Sale> = runCatching {
        val response = api.completeSale(request)
        if (response.isSuccessful) {
            response.body()?.data ?: throw Exception("Failed to complete sale")
        } else {
            throw Exception(response.errorBody()?.string() ?: "Failed to complete sale")
        }
    }

    suspend fun getSales(search: String? = null, period: String? = null): Result<List<Sale>> = runCatching {
        val response = api.getSales(search, period)
        if (response.isSuccessful) {
            response.body()?.data ?: emptyList()
        } else {
            throw Exception(response.errorBody()?.string() ?: "Failed to load sales")
        }
    }

    suspend fun getSale(id: Int): Result<Sale> = runCatching {
        val response = api.getSale(id)
        if (response.isSuccessful) {
            response.body()?.data ?: throw Exception("Sale not found")
        } else {
            throw Exception(response.errorBody()?.string() ?: "Failed to load sale")
        }
    }

    suspend fun getCustomers(search: String? = null): Result<List<Customer>> = runCatching {
        val response = api.getCustomers(search)
        if (response.isSuccessful) {
            response.body()?.data ?: emptyList()
        } else {
            throw Exception(response.errorBody()?.string() ?: "Failed to load customers")
        }
    }

    suspend fun getCustomer(id: Int): Result<Customer> = runCatching {
        val response = api.getCustomer(id)
        if (response.isSuccessful) {
            response.body()?.data ?: throw Exception("Customer not found")
        } else {
            throw Exception(response.errorBody()?.string() ?: "Failed to load customer")
        }
    }

    suspend fun createCustomer(request: CustomerRequest): Result<Customer> = runCatching {
        val response = api.createCustomer(request)
        if (response.isSuccessful) {
            response.body()?.data ?: throw Exception("Failed to create customer")
        } else {
            throw Exception(response.errorBody()?.string() ?: "Failed to create customer")
        }
    }

    suspend fun updateCustomer(id: Int, request: CustomerRequest): Result<Customer> = runCatching {
        val response = api.updateCustomer(id, request)
        if (response.isSuccessful) {
            response.body()?.data ?: throw Exception("Failed to update customer")
        } else {
            throw Exception(response.errorBody()?.string() ?: "Failed to update customer")
        }
    }

    suspend fun getInventory(): Result<List<InventoryProduct>> = runCatching {
        val response = api.getInventory()
        if (response.isSuccessful) {
            response.body()?.data ?: emptyList()
        } else {
            throw Exception(response.errorBody()?.string() ?: "Failed to load inventory")
        }
    }

    suspend fun getInventoryProduct(id: Int): Result<InventoryProduct> = runCatching {
        val response = api.getInventoryProduct(id)
        if (response.isSuccessful) {
            response.body()?.data ?: throw Exception("Product not found")
        } else {
            throw Exception(response.errorBody()?.string() ?: "Failed to load product inventory")
        }
    }

    suspend fun adjustStock(request: StockAdjustmentRequest): Result<Unit> = runCatching {
        val response = api.adjustStock(request)
        if (!response.isSuccessful) {
            throw Exception(response.errorBody()?.string() ?: "Failed to adjust stock")
        }
    }

    suspend fun getReturns(period: String? = null, status: String? = null): Result<List<ReturnRequest>> = runCatching {
        val response = api.getReturns(period, status)
        if (response.isSuccessful) {
            response.body()?.data ?: emptyList()
        } else {
            throw Exception(response.errorBody()?.string() ?: "Failed to load returns")
        }
    }

    suspend fun submitReturn(request: ReturnSubmitRequest): Result<ReturnRequest> = runCatching {
        val response = api.submitReturn(request)
        if (response.isSuccessful) {
            response.body()?.data ?: throw Exception("Failed to submit return")
        } else {
            throw Exception(response.errorBody()?.string() ?: "Failed to submit return")
        }
    }

    suspend fun getMessages(): Result<List<Message>> = runCatching {
        val response = api.getMessages()
        if (response.isSuccessful) {
            response.body()?.data ?: emptyList()
        } else {
            throw Exception(response.errorBody()?.string() ?: "Failed to load messages")
        }
    }

    suspend fun sendMessage(text: String): Result<Message> = runCatching {
        val response = api.sendMessage(MessageRequest(text))
        if (response.isSuccessful) {
            response.body()?.data ?: throw Exception("Failed to send message")
        } else {
            throw Exception(response.errorBody()?.string() ?: "Failed to send message")
        }
    }

    suspend fun getSettings(): Result<Settings> = runCatching {
        val response = api.getSettings()
        if (response.isSuccessful) {
            response.body()?.data ?: throw Exception("Failed to load settings")
        } else {
            throw Exception(response.errorBody()?.string() ?: "Failed to load settings")
        }
    }

    suspend fun updateSettings(settings: SettingUpdate): Result<Settings> = runCatching {
        val response = api.updateSettings(settings)
        if (response.isSuccessful) {
            response.body()?.data ?: throw Exception("Failed to update settings")
        } else {
            throw Exception(response.errorBody()?.string() ?: "Failed to update settings")
        }
    }

    suspend fun getUsers(): Result<List<User>> = runCatching {
        val response = api.getUsers()
        if (response.isSuccessful) {
            response.body()?.data ?: emptyList()
        } else {
            throw Exception(response.errorBody()?.string() ?: "Failed to load users")
        }
    }

    suspend fun getReport(endpoint: String, period: String?): Result<ReportData> = runCatching {
        val response = when (endpoint) {
            "sales" -> api.getSalesReport(period)
            "products" -> api.getProductsReport(period)
            "cashiers" -> api.getCashiersReport(period)
            "profit" -> api.getProfitReport(period)
            "inventory" -> api.getInventoryReport()
            else -> throw Exception("Unknown report")
        }
        if (response.isSuccessful) {
            response.body()?.data ?: throw Exception("No report data")
        } else {
            throw Exception(response.errorBody()?.string() ?: "Failed to load report")
        }
    }
}
