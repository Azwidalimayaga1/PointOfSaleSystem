package com.possystem.app.ui.inventory

import android.view.LayoutInflater
import android.view.ViewGroup
import androidx.recyclerview.widget.DiffUtil
import androidx.recyclerview.widget.ListAdapter
import androidx.recyclerview.widget.RecyclerView
import com.possystem.app.data.model.InventoryProduct
import com.possystem.app.databinding.ViewProductItemBinding
import com.possystem.app.util.CurrencyFormatter

class InventoryAdapter(private val onClick: (InventoryProduct) -> Unit) :
    ListAdapter<InventoryProduct, InventoryAdapter.ViewHolder>(DiffCallback) {

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): ViewHolder {
        val binding = ViewProductItemBinding.inflate(LayoutInflater.from(parent.context), parent, false)
        return ViewHolder(binding)
    }

    override fun onBindViewHolder(holder: ViewHolder, position: Int) {
        holder.bind(getItem(position))
    }

    inner class ViewHolder(private val binding: ViewProductItemBinding) : RecyclerView.ViewHolder(binding.root) {
        fun bind(product: InventoryProduct) {
            binding.productName.text = product.name
            binding.productBarcode.text = "Stock: ${product.stockQuantity}"
            binding.productCategory.text = "Cost: ${CurrencyFormatter.format(product.costPrice ?: 0.0)}"
            binding.productPrice.text = CurrencyFormatter.format(product.price)
            binding.productStock.text = "Qty: ${product.stockQuantity}"

            val threshold = product.lowStockThreshold ?: 10
            if (product.stockQuantity <= threshold) {
                binding.productStock.setTextColor(
                    binding.root.context.getColor(android.R.color.holo_red_dark)
                )
            }

            binding.root.setOnClickListener { onClick(product) }
        }
    }

    object DiffCallback : DiffUtil.ItemCallback<InventoryProduct>() {
        override fun areItemsTheSame(old: InventoryProduct, new: InventoryProduct) = old.id == new.id
        override fun areContentsTheSame(old: InventoryProduct, new: InventoryProduct) = old == new
    }
}
