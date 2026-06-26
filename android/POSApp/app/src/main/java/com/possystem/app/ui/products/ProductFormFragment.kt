package com.possystem.app.ui.products

import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import androidx.fragment.app.Fragment
import androidx.fragment.app.viewModels
import com.possystem.app.data.model.ProductRequest
import com.possystem.app.databinding.FragmentProductFormBinding

class ProductFormFragment : Fragment() {
    private var _binding: FragmentProductFormBinding? = null
    private val binding get() = _binding!!
    private val productsViewModel: ProductsViewModel by viewModels()
    private var editingProductId: Int? = null

    override fun onCreateView(inflater: LayoutInflater, container: ViewGroup?, savedInstanceState: Bundle?): View {
        _binding = FragmentProductFormBinding.inflate(inflater, container, false)
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)

        editingProductId = arguments?.getInt("product_id")

        if (editingProductId != null) {
            binding.deleteButton.visibility = View.VISIBLE
            loadProduct(editingProductId!!)
        }

        binding.saveButton.setOnClickListener {
            val request = ProductRequest(
                name = binding.nameInput.text.toString().trim(),
                barcode = binding.barcodeInput.text.toString().trim(),
                category = binding.categoryInput.text.toString().trim().ifBlank { null },
                price = binding.priceInput.text.toString().toDoubleOrNull() ?: 0.0,
                costPrice = binding.costPriceInput.text.toString().toDoubleOrNull(),
                stockQuantity = binding.stockInput.text.toString().toIntOrNull(),
                lowStockThreshold = binding.lowStockInput.text.toString().toIntOrNull(),
                supplier = binding.supplierInput.text.toString().trim().ifBlank { null },
                status = "active"
            )

            val id = editingProductId
            if (id != null) {
                productsViewModel.updateProduct(id, request) { goBack() }
            } else {
                productsViewModel.createProduct(request) { goBack() }
            }
        }

        binding.deleteButton.setOnClickListener {
            val id = editingProductId
            if (id != null) {
                productsViewModel.deleteProduct(id)
                goBack()
            }
        }

        productsViewModel.isLoading.observe(viewLifecycleOwner) { loading ->
            binding.formProgress.visibility = if (loading) View.VISIBLE else View.GONE
        }
    }

    private fun loadProduct(id: Int) {
        productsViewModel.loadProducts() // refresh from product detail if needed
    }

    private fun goBack() {
        parentFragmentManager.popBackStack()
    }

    override fun onDestroyView() {
        super.onDestroyView()
        _binding = null
    }
}
