package com.possystem.app.ui.sales

import android.view.LayoutInflater
import android.view.ViewGroup
import androidx.recyclerview.widget.DiffUtil
import androidx.recyclerview.widget.ListAdapter
import androidx.recyclerview.widget.RecyclerView
import com.possystem.app.data.model.Product
import com.possystem.app.databinding.ViewProductItemBinding
import com.possystem.app.util.CurrencyFormatter

class SalesProductAdapter(private val onClick: (Product) -> Unit) :
    ListAdapter<Product, SalesProductAdapter.ViewHolder>(DiffCallback) {

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): ViewHolder {
        val binding = ViewProductItemBinding.inflate(LayoutInflater.from(parent.context), parent, false)
        return ViewHolder(binding)
    }

    override fun onBindViewHolder(holder: ViewHolder, position: Int) {
        holder.bind(getItem(position))
    }

    inner class ViewHolder(private val binding: ViewProductItemBinding) : RecyclerView.ViewHolder(binding.root) {
        fun bind(product: Product) {
            binding.productName.text = product.name
            binding.productBarcode.text = product.barcode
            binding.productCategory.text = product.category ?: ""
            binding.productPrice.text = CurrencyFormatter.format(product.price)
            binding.productStock.text = "Stock: ${product.stockQuantity}"
            binding.root.setOnClickListener { onClick(product) }
        }
    }

    object DiffCallback : DiffUtil.ItemCallback<Product>() {
        override fun areItemsTheSame(old: Product, new: Product) = old.id == new.id
        override fun areContentsTheSame(old: Product, new: Product) = old == new
    }
}
