package com.possystem.app.ui.settings

import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import androidx.fragment.app.Fragment
import androidx.fragment.app.viewModels
import com.google.android.material.snackbar.Snackbar
import com.possystem.app.data.model.SettingUpdate
import com.possystem.app.databinding.FragmentSettingsBinding

class SettingsFragment : Fragment() {
    private var _binding: FragmentSettingsBinding? = null
    private val binding get() = _binding!!
    private val settingsViewModel: SettingsViewModel by viewModels()

    override fun onCreateView(inflater: LayoutInflater, container: ViewGroup?, savedInstanceState: Bundle?): View {
        _binding = FragmentSettingsBinding.inflate(inflater, container, false)
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)

        settingsViewModel.settings.observe(viewLifecycleOwner) { settings ->
            settings?.let {
                binding.storeNameInput.setText(it.storeName ?: "")
                binding.addressInput.setText(it.storeAddress ?: "")
                binding.contactInput.setText(it.storeContact ?: "")
                binding.taxRateInput.setText(it.taxRate?.toString() ?: "")
                binding.currencyInput.setText(it.currency ?: "R")
                binding.receiptFooterInput.setText(it.receiptFooter ?: "")
                binding.dailyTargetInput.setText(it.dailyTarget?.toString() ?: "")
            }
        }

        binding.saveButton.setOnClickListener {
            val update = SettingUpdate(
                storeName = binding.storeNameInput.text.toString().trim().ifBlank { null },
                storeAddress = binding.addressInput.text.toString().trim().ifBlank { null },
                storeContact = binding.contactInput.text.toString().trim().ifBlank { null },
                taxRate = binding.taxRateInput.text.toString().toDoubleOrNull(),
                currency = binding.currencyInput.text.toString().trim().ifBlank { null },
                receiptFooter = binding.receiptFooterInput.text.toString().trim().ifBlank { null },
                dailyTarget = binding.dailyTargetInput.text.toString().toDoubleOrNull()
            )
            settingsViewModel.updateSettings(update)
        }

        settingsViewModel.success.observe(viewLifecycleOwner) { msg ->
            msg?.let { Snackbar.make(binding.root, it, Snackbar.LENGTH_SHORT).show() }
        }

        settingsViewModel.isLoading.observe(viewLifecycleOwner) { loading ->
            binding.settingsProgress.visibility = if (loading) View.VISIBLE else View.GONE
        }

        settingsViewModel.loadSettings()
    }

    override fun onDestroyView() {
        super.onDestroyView()
        _binding = null
    }
}
