package com.possystem.app.ui.customers

import android.view.LayoutInflater
import android.view.ViewGroup
import androidx.recyclerview.widget.DiffUtil
import androidx.recyclerview.widget.ListAdapter
import androidx.recyclerview.widget.RecyclerView
import com.possystem.app.data.model.Customer
import com.possystem.app.databinding.ViewProductItemBinding
import com.possystem.app.util.CurrencyFormatter

class CustomerAdapter(private val onClick: (Customer) -> Unit) :
    ListAdapter<Customer, CustomerAdapter.ViewHolder>(DiffCallback) {

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): ViewHolder {
        val binding = ViewProductItemBinding.inflate(LayoutInflater.from(parent.context), parent, false)
        return ViewHolder(binding)
    }

    override fun onBindViewHolder(holder: ViewHolder, position: Int) {
        holder.bind(getItem(position))
    }

    inner class ViewHolder(private val binding: ViewProductItemBinding) : RecyclerView.ViewHolder(binding.root) {
        fun bind(customer: Customer) {
            binding.productName.text = customer.name
            binding.productBarcode.text = customer.phone ?: "No phone"
            binding.productCategory.text = customer.email ?: ""
            binding.productPrice.text = CurrencyFormatter.format(customer.totalSpent ?: 0.0)
            binding.productStock.text = "Visits: ${customer.visitCount ?: 0}"
            binding.root.setOnClickListener { onClick(customer) }
        }
    }

    object DiffCallback : DiffUtil.ItemCallback<Customer>() {
        override fun areItemsTheSame(old: Customer, new: Customer) = old.id == new.id
        override fun areContentsTheSame(old: Customer, new: Customer) = old == new
    }
}
