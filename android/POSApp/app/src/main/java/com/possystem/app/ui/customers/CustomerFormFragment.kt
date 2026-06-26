package com.possystem.app.ui.customers

import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import androidx.fragment.app.Fragment
import androidx.fragment.app.viewModels
import com.possystem.app.data.model.CustomerRequest
import com.possystem.app.databinding.FragmentCustomerFormBinding

class CustomerFormFragment : Fragment() {
    private var _binding: FragmentCustomerFormBinding? = null
    private val binding get() = _binding!!
    private val customersViewModel: CustomersViewModel by viewModels()
    private var editingCustomerId: Int? = null

    override fun onCreateView(inflater: LayoutInflater, container: ViewGroup?, savedInstanceState: Bundle?): View {
        _binding = FragmentCustomerFormBinding.inflate(inflater, container, false)
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)

        editingCustomerId = arguments?.getInt("customer_id")

        if (editingCustomerId != null) {
            customersViewModel.loadCustomer(editingCustomerId!!)
            customersViewModel.selectedCustomer.observe(viewLifecycleOwner) { customer ->
                customer?.let {
                    binding.nameInput.setText(it.name)
                    binding.phoneInput.setText(it.phone ?: "")
                    binding.emailInput.setText(it.email ?: "")
                    binding.addressInput.setText(it.address ?: "")
                    binding.notesInput.setText(it.notes ?: "")
                }
            }
        }

        binding.saveButton.setOnClickListener {
            val request = CustomerRequest(
                name = binding.nameInput.text.toString().trim(),
                phone = binding.phoneInput.text.toString().trim().ifBlank { null },
                email = binding.emailInput.text.toString().trim().ifBlank { null },
                address = binding.addressInput.text.toString().trim().ifBlank { null },
                notes = binding.notesInput.text.toString().trim().ifBlank { null }
            )

            val id = editingCustomerId
            if (id != null) {
                customersViewModel.updateCustomer(id, request) { goBack() }
            } else {
                customersViewModel.createCustomer(request) { goBack() }
            }
        }

        customersViewModel.isLoading.observe(viewLifecycleOwner) { loading ->
            binding.formProgress.visibility = if (loading) View.VISIBLE else View.GONE
        }
    }

    private fun goBack() = parentFragmentManager.popBackStack()

    override fun onDestroyView() {
        super.onDestroyView()
        _binding = null
    }
}
