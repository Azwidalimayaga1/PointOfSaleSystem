package com.possystem.app.ui.sales

import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import androidx.fragment.app.Fragment
import androidx.fragment.app.viewModels
import androidx.recyclerview.widget.GridLayoutManager
import androidx.recyclerview.widget.LinearLayoutManager
import com.google.android.material.dialog.MaterialAlertDialogBuilder
import com.possystem.app.databinding.FragmentSalesBinding
import com.possystem.app.util.CurrencyFormatter

class SalesFragment : Fragment() {
    private var _binding: FragmentSalesBinding? = null
    private val binding get() = _binding!!
    private val salesViewModel: SalesViewModel by viewModels()
    private lateinit var productAdapter: SalesProductAdapter
    private lateinit var cartAdapter: CartAdapter

    override fun onCreateView(inflater: LayoutInflater, container: ViewGroup?, savedInstanceState: Bundle?): View {
        _binding = FragmentSalesBinding.inflate(inflater, container, false)
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)

        productAdapter = SalesProductAdapter { product ->
            salesViewModel.addToCart(product)
        }
        cartAdapter = CartAdapter(
            onQuantityChange = { productId, qty -> salesViewModel.updateQuantity(productId, qty) },
            onRemove = { productId -> salesViewModel.removeFromCart(productId) }
        )

        binding.productGrid.layoutManager = GridLayoutManager(requireContext(), 2)
        binding.productGrid.adapter = productAdapter

        binding.cartRecyclerView.layoutManager = LinearLayoutManager(requireContext())
        binding.cartRecyclerView.adapter = cartAdapter

        binding.saleSearchInput.setOnEditorActionListener { _, _, _ ->
            salesViewModel.loadProducts(binding.saleSearchInput.text.toString().trim())
            true
        }

        binding.scanBarcodeButton.setOnClickListener {
            // Barcode scanning would use CameraX + ZXing
        }

        binding.checkoutButton.setOnClickListener { showCheckoutDialog() }
        binding.holdButton.setOnClickListener { /* hold sale logic */ }

        salesViewModel.products.observe(viewLifecycleOwner) { products ->
            productAdapter.submitList(products)
        }

        salesViewModel.cartItems.observe(viewLifecycleOwner) { items ->
            cartAdapter.submitList(items)
        }

        salesViewModel.total.observe(viewLifecycleOwner) { total ->
            binding.cartTotalText.text = CurrencyFormatter.format(total)
        }

        salesViewModel.saleComplete.observe(viewLifecycleOwner) { sale ->
            if (sale != null) {
                val bundle = Bundle().apply {
                    putInt("sale_id", sale.id)
                }
                parentFragmentManager.beginTransaction()
                    .replace(R.id.navHostFragment, com.possystem.app.ui.receipt.ReceiptFragment::class.java, bundle)
                    .addToBackStack("receipt")
                    .commit()
            }
        }

        salesViewModel.loadProducts()
    }

    private fun showCheckoutDialog() {
        val items = salesViewModel.cartItems.value ?: return
        if (items.isEmpty()) return

        val total = salesViewModel.total.value ?: 0.0
        val paymentOptions = arrayOf("Cash", "Card", "Mixed")

        MaterialAlertDialogBuilder(requireContext())
            .setTitle("Payment - ${CurrencyFormatter.format(total)}")
            .setItems(paymentOptions) { _, which ->
                val method = when (which) {
                    0 -> "cash"
                    1 -> "card"
                    2 -> "mixed"
                    else -> "cash"
                }
                salesViewModel.completeSale(method, total, null, null)
            }
            .setNegativeButton("Cancel", null)
            .show()
    }

    override fun onDestroyView() {
        super.onDestroyView()
        _binding = null
    }
}
