package com.possystem.app.ui.returns

import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import androidx.fragment.app.Fragment
import androidx.fragment.app.viewModels
import androidx.recyclerview.widget.LinearLayoutManager
import com.google.android.material.dialog.MaterialAlertDialogBuilder
import com.google.android.material.textfield.TextInputEditText
import com.possystem.app.databinding.FragmentReturnsBinding

class ReturnsFragment : Fragment() {
    private var _binding: FragmentReturnsBinding? = null
    private val binding get() = _binding!!
    private val returnsViewModel: ReturnsViewModel by viewModels()
    private lateinit var adapter: ReturnAdapter

    override fun onCreateView(inflater: LayoutInflater, container: ViewGroup?, savedInstanceState: Bundle?): View {
        _binding = FragmentReturnsBinding.inflate(inflater, container, false)
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)

        adapter = ReturnAdapter()
        binding.returnsRecyclerView.layoutManager = LinearLayoutManager(requireContext())
        binding.returnsRecyclerView.adapter = adapter

        binding.newReturnButton.setOnClickListener { showNewReturnDialog() }
        binding.refreshButton.setOnClickListener { returnsViewModel.loadReturns() }

        returnsViewModel.returns.observe(viewLifecycleOwner) { returns ->
            adapter.submitList(returns)
            binding.emptyText.visibility = if (returns.isEmpty()) View.VISIBLE else View.GONE
        }

        returnsViewModel.isLoading.observe(viewLifecycleOwner) { loading ->
            binding.returnsProgress.visibility = if (loading) View.VISIBLE else View.GONE
        }

        returnsViewModel.loadReturns()
    }

    private fun showNewReturnDialog() {
        val input = TextInputEditText(requireContext())
        input.hint = "Receipt Number"

        MaterialAlertDialogBuilder(requireContext())
            .setTitle("New Return")
            .setView(input)
            .setPositiveButton("Next") { _, _ ->
                val receipt = input.text.toString().trim()
                if (receipt.isNotEmpty()) {
                    // Look up sale by receipt and show items to return
                }
            }
            .setNegativeButton("Cancel", null)
            .show()
    }

    override fun onDestroyView() {
        super.onDestroyView()
        _binding = null
    }
}
