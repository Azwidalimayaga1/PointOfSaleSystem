package com.possystem.app.ui.receipt

import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import androidx.fragment.app.Fragment
import androidx.fragment.app.viewModels
import com.possystem.app.data.model.Sale
import com.possystem.app.data.model.SaleItem
import com.possystem.app.data.repository.POSRepository
import com.possystem.app.databinding.FragmentReceiptBinding
import com.possystem.app.util.CurrencyFormatter
import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import kotlinx.coroutines.launch

class ReceiptViewModel : ViewModel() {
    private val repository = POSRepository()
    private val _sale = MutableLiveData<Sale?>()
    val sale: LiveData<Sale?> = _sale
    private val _error = MutableLiveData<String?>()
    val error: LiveData<String?> = _error

    fun loadSale(id: Int) {
        viewModelScope.launch {
            repository.getSale(id).fold(
                onSuccess = { _sale.value = it },
                onFailure = { _error.value = it.message }
            )
        }
    }
}

class ReceiptFragment : Fragment() {
    private var _binding: FragmentReceiptBinding? = null
    private val binding get() = _binding!!
    private val receiptViewModel: ReceiptViewModel by viewModels()

    override fun onCreateView(inflater: LayoutInflater, container: ViewGroup?, savedInstanceState: Bundle?): View {
        _binding = FragmentReceiptBinding.inflate(inflater, container, false)
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)

        val saleId = arguments?.getInt("sale_id") ?: return
        receiptViewModel.loadSale(saleId)

        receiptViewModel.sale.observe(viewLifecycleOwner) { sale ->
            sale?.let { displayReceipt(it) }
        }
    }

    private fun displayReceipt(sale: Sale) {
        binding.receiptTitle.text = "SALE RECEIPT"
        binding.receiptNumber.text = "Receipt: ${sale.receiptNumber}"

        val itemsText = sale.items?.joinToString("\n") { item ->
            "${item.productName} x${item.quantity} @ ${CurrencyFormatter.format(item.price)} = ${CurrencyFormatter.format(item.total)}"
        } ?: "No items"

        binding.receiptItems.text = itemsText

        binding.receiptTotals.removeAllViews()
        val addTotalLine = { label: String, value: String ->
            val row = layoutInflater.inflate(android.R.layout.simple_list_item_2, null) as android.widget.LinearLayout
            val text1 = row.findViewById<android.widget.TextView>(android.R.id.text1)
            val text2 = row.findViewById<android.widget.TextView>(android.R.id.text2)
            text1.text = label
            text2.text = value
            binding.receiptTotals.addView(row)
        }

        addTotalLine("Subtotal:", CurrencyFormatter.format(sale.subtotal))
        if ((sale.discount ?: 0.0) > 0) {
            addTotalLine("Discount:", "-${CurrencyFormatter.format(sale.discount ?: 0.0)}")
        }
        addTotalLine("Tax:", CurrencyFormatter.format(sale.tax))
        addTotalLine("Total:", CurrencyFormatter.format(sale.total))
        addTotalLine("Payment:", sale.paymentMethod ?: "")
        addTotalLine("Amount Tendered:", CurrencyFormatter.format((sale.cashAmount ?: 0.0) + (sale.cardAmount ?: 0.0)))
        addTotalLine("Change:", CurrencyFormatter.format(sale.changeAmount ?: 0.0))

        binding.receiptFooter.text = "Thank you for your business!\n${sale.createdAt ?: ""}"

        binding.printButton.setOnClickListener {
            // Print would use Android Print API or Bluetooth printer
        }
    }

    override fun onDestroyView() {
        super.onDestroyView()
        _binding = null
    }
}
