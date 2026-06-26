package com.possystem.app.ui.inventory

import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import androidx.fragment.app.Fragment
import androidx.fragment.app.viewModels
import androidx.recyclerview.widget.LinearLayoutManager
import com.possystem.app.databinding.FragmentInventoryBinding

class InventoryFragment : Fragment() {
    private var _binding: FragmentInventoryBinding? = null
    private val binding get() = _binding!!
    private val inventoryViewModel: InventoryViewModel by viewModels()
    private lateinit var adapter: InventoryAdapter

    override fun onCreateView(inflater: LayoutInflater, container: ViewGroup?, savedInstanceState: Bundle?): View {
        _binding = FragmentInventoryBinding.inflate(inflater, container, false)
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)

        adapter = InventoryAdapter { product ->
            // Show stock adjustment dialog from the adapter callback
        }

        binding.inventoryRecyclerView.layoutManager = LinearLayoutManager(requireContext())
        binding.inventoryRecyclerView.adapter = adapter

        inventoryViewModel.inventory.observe(viewLifecycleOwner) { inventory ->
            adapter.submitList(inventory)
            binding.emptyText.visibility = if (inventory.isEmpty()) View.VISIBLE else View.GONE
        }

        inventoryViewModel.isLoading.observe(viewLifecycleOwner) { loading ->
            binding.inventoryProgress.visibility = if (loading) View.VISIBLE else View.GONE
        }

        inventoryViewModel.loadInventory()
    }

    override fun onDestroyView() {
        super.onDestroyView()
        _binding = null
    }
}
