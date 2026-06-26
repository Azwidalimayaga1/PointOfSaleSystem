package com.possystem.app.ui.dashboard

import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import androidx.fragment.app.Fragment
import androidx.fragment.app.viewModels
import com.possystem.app.databinding.FragmentDashboardBinding
import com.possystem.app.util.CurrencyFormatter
import com.possystem.app.util.SessionManager
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch

class DashboardFragment : Fragment() {
    private var _binding: FragmentDashboardBinding? = null
    private val binding get() = _binding!!
    private val dashboardViewModel: DashboardViewModel by viewModels()

    override fun onCreateView(inflater: LayoutInflater, container: ViewGroup?, savedInstanceState: Bundle?): View {
        _binding = FragmentDashboardBinding.inflate(inflater, container, false)
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)

        CoroutineScope(Dispatchers.Main).launch {
            val name = SessionManager.getFullName()
            binding.greetingText.text = "Welcome, ${name ?: "User"}"
        }

        binding.swipeRefresh.setOnRefreshListener {
            dashboardViewModel.loadDashboard()
        }

        dashboardViewModel.dashboardData.observe(viewLifecycleOwner) { data ->
            if (data == null) return@observe

            binding.todaySalesText.text = CurrencyFormatter.format(data.todaySales ?: 0.0)
            binding.transactionsText.text = "${data.todayTransactions ?: 0}"

            val progress = (data.targetProgress ?: 0.0).toInt()
            binding.targetProgress.progress = progress
            val target = CurrencyFormatter.format(data.dailyTarget ?: 5000.0)
            val current = CurrencyFormatter.format(data.todaySales ?: 0.0)
            binding.targetText.text = "$current / $target ($progress%)"

            val lowStockCount = data.lowStockCount ?: 0
            binding.lowStockText.text = "$lowStockCount products low on stock"

            val recentSales = data.recentSales ?: emptyList()
            binding.recentSalesText.text = if (recentSales.isEmpty()) {
                "No recent sales"
            } else {
                recentSales.joinToString("\n") { s ->
                    "${s.receiptNumber} - ${CurrencyFormatter.format(s.total)}"
                }
            }
        }

        dashboardViewModel.isLoading.observe(viewLifecycleOwner) { loading ->
            binding.dashboardProgress.visibility = if (loading) View.VISIBLE else View.GONE
            if (!loading) binding.swipeRefresh.isRefreshing = false
        }

        dashboardViewModel.loadDashboard()
    }

    override fun onDestroyView() {
        super.onDestroyView()
        _binding = null
    }
}
