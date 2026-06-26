package com.possystem.app.util

object Constants {
    const val ROLE_ADMIN = "admin"
    const val ROLE_MANAGER = "manager"
    const val ROLE_CASHIER = "cashier"
    const val ROLE_STORE_ADMIN = "store_admin"

    val ADMIN_ROLES = setOf(ROLE_ADMIN, ROLE_STORE_ADMIN, ROLE_MANAGER)

    const val PERIOD_TODAY = "today"
    const val PERIOD_WEEK = "week"
    const val PERIOD_MONTH = "month"
    const val PERIOD_YEAR = "year"

    const val PAYMENT_CASH = "cash"
    const val PAYMENT_CARD = "card"
    const val PAYMENT_MIXED = "mixed"

    const val STOCK_SALE = "sale"
    const val STOCK_PURCHASE = "purchase"
    const val STOCK_RETURN = "return"
    const val STOCK_ADJUSTMENT = "adjustment"
    const val STOCK_DAMAGE = "damage"

    const val RETURN_REASON_RETURN = "return"
    const val RETURN_REASON_DAMAGE = "damage"
    const val RETURN_RESOLUTION_REFUND = "refund"
    const val RETURN_RESOLUTION_EXCHANGE = "exchange"
}
