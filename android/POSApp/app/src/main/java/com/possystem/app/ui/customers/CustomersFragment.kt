package com.possystem.app.ui.customers

import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import androidx.fragment.app.Fragment
import androidx.fragment.app.viewModels
import androidx.recyclerview.widget.LinearLayoutManager
import com.possystem.app.databinding.FragmentCustomersBinding

class CustomersFragment : Fragment() {
    private var _binding: FragmentCustomersBinding? = null
    private val binding get() = _binding!!
    private val customersViewModel: CustomersViewModel by viewModels()
    private lateinit var adapter: CustomerAdapter

    override fun onCreateView(inflater: LayoutInflater, container: ViewGroup?, savedInstanceState: Bundle?): View {
        _binding = FragmentCustomersBinding.inflate(inflater, container, false)
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)

        adapter = CustomerAdapter { customer ->
            val bundle = Bundle().apply { putInt("customer_id", customer.id) }
            parentFragmentManager.beginTransaction()
                .replace(R.id.navHostFragment, CustomerFormFragment::class.java, bundle)
                .addToBackStack("customer_form")
                .commit()
        }

        binding.customersRecyclerView.layoutManager = LinearLayoutManager(requireContext())
        binding.customersRecyclerView.adapter = adapter

        binding.searchInput.setOnEditorActionListener { _, _, _ ->
            customersViewModel.loadCustomers(binding.searchInput.text.toString().trim())
            true
        }

        binding.addButton.setOnClickListener {
            parentFragmentManager.beginTransaction()
                .replace(R.id.navHostFragment, CustomerFormFragment())
                .addToBackStack("customer_form")
                .commit()
        }

        customersViewModel.customers.observe(viewLifecycleOwner) { customers ->
            adapter.submitList(customers)
            binding.emptyText.visibility = if (customers.isEmpty()) View.VISIBLE else View.GONE
        }

        customersViewModel.isLoading.observe(viewLifecycleOwner) { loading ->
            binding.customersProgress.visibility = if (loading) View.VISIBLE else View.GONE
        }

        customersViewModel.loadCustomers()
    }

    override fun onDestroyView() {
        super.onDestroyView()
        _binding = null
    }
}
