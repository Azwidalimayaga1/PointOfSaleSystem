package com.possystem.app.ui.products

import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import androidx.fragment.app.Fragment
import androidx.fragment.app.viewModels
import androidx.recyclerview.widget.LinearLayoutManager
import com.possystem.app.databinding.FragmentProductsBinding
import com.possystem.app.util.CurrencyFormatter

class ProductsFragment : Fragment() {
    private var _binding: FragmentProductsBinding? = null
    private val binding get() = _binding!!
    private val productsViewModel: ProductsViewModel by viewModels()
    private lateinit var adapter: ProductAdapter

    override fun onCreateView(inflater: LayoutInflater, container: ViewGroup?, savedInstanceState: Bundle?): View {
        _binding = FragmentProductsBinding.inflate(inflater, container, false)
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)

        adapter = ProductAdapter { product ->
            val bundle = Bundle().apply {
                putInt("product_id", product.id)
            }
            parentFragmentManager.let { fm ->
                fm.beginTransaction()
                    .replace(R.id.navHostFragment, ProductFormFragment::class.java, bundle)
                    .addToBackStack("product_form")
                    .commit()
            }
        }

        binding.productsRecyclerView.layoutManager = LinearLayoutManager(requireContext())
        binding.productsRecyclerView.adapter = adapter

        binding.searchInput.setOnEditorActionListener { _, _, _ ->
            productsViewModel.search(binding.searchInput.text.toString().trim())
            true
        }

        binding.addButton.setOnClickListener {
            parentFragmentManager.beginTransaction()
                .replace(R.id.navHostFragment, ProductFormFragment())
                .addToBackStack("product_form")
                .commit()
        }

        productsViewModel.products.observe(viewLifecycleOwner) { products ->
            adapter.submitList(products)
            binding.emptyText.visibility = if (products.isEmpty()) View.VISIBLE else View.GONE
        }

        productsViewModel.isLoading.observe(viewLifecycleOwner) { loading ->
            binding.productsProgress.visibility = if (loading) View.VISIBLE else View.GONE
        }

        productsViewModel.loadProducts()
    }

    override fun onDestroyView() {
        super.onDestroyView()
        _binding = null
    }
}
