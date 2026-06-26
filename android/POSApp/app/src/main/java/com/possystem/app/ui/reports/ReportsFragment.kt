package com.possystem.app.ui.reports

import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import androidx.fragment.app.Fragment
import androidx.fragment.app.viewModels
import androidx.recyclerview.widget.LinearLayoutManager
import com.possystem.app.databinding.FragmentReportsBinding

class ReportsFragment : Fragment() {
    private var _binding: FragmentReportsBinding? = null
    private val binding get() = _binding!!
    private val reportsViewModel: ReportsViewModel by viewModels()
    private lateinit var adapter: ReportAdapter
    private var currentPeriod = "today"

    override fun onCreateView(inflater: LayoutInflater, container: ViewGroup?, savedInstanceState: Bundle?): View {
        _binding = FragmentReportsBinding.inflate(inflater, container, false)
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)

        adapter = ReportAdapter()
        binding.reportRecyclerView.layoutManager = LinearLayoutManager(requireContext())
        binding.reportRecyclerView.adapter = adapter

        binding.chipSales.setOnClickListener { loadReport("sales") }
        binding.chipProducts.setOnClickListener { loadReport("products") }
        binding.chipProfit.setOnClickListener { loadReport("profit") }

        binding.periodButton.setOnClickListener {
            val periods = arrayOf("Today", "This Week", "This Month", "This Year")
            val periodValues = arrayOf("today", "week", "month", "year")
            android.app.AlertDialog.Builder(requireContext())
                .setTitle("Select Period")
                .setItems(periods) { _, which ->
                    currentPeriod = periodValues[which]
                    binding.periodButton.text = periods[which]
                    loadReport(reportsViewModel.selectedReport.value ?: "sales")
                }
                .show()
        }

        reportsViewModel.reportData.observe(viewLifecycleOwner) { data ->
            if (data?.labels != null && data.values != null) {
                val items = data.labels.zip(data.values) { label, value ->
                    Pair(label, value)
                }
                adapter.submitList(items)
            }
            binding.emptyText.visibility = if (data == null) View.VISIBLE else View.GONE
        }

        reportsViewModel.isLoading.observe(viewLifecycleOwner) { loading ->
            binding.reportProgress.visibility = if (loading) View.VISIBLE else View.GONE
        }

        loadReport("sales")
    }

    private fun loadReport(type: String) {
        reportsViewModel.loadReport(type, currentPeriod)
    }

    override fun onDestroyView() {
        super.onDestroyView()
        _binding = null
    }
}
