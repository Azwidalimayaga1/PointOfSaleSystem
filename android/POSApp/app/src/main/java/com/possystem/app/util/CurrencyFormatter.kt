package com.possystem.app.util

import java.text.NumberFormat
import java.util.Locale

object CurrencyFormatter {
    private val format = NumberFormat.getCurrencyInstance(Locale("en", "ZA"))

    fun format(amount: Double): String {
        return format.format(amount)
    }
}
