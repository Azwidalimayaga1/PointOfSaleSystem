package com.possystem.app.ui.sales

import android.view.LayoutInflater
import android.view.ViewGroup
import androidx.recyclerview.widget.DiffUtil
import androidx.recyclerview.widget.ListAdapter
import androidx.recyclerview.widget.RecyclerView
import com.possystem.app.databinding.ViewCartItemBinding
import com.possystem.app.util.CurrencyFormatter

class CartAdapter(
    private val onQuantityChange: (productId: Int, quantity: Int) -> Unit,
    private val onRemove: (productId: Int) -> Unit
) : ListAdapter<CartItem, CartAdapter.ViewHolder>(DiffCallback) {

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): ViewHolder {
        val binding = ViewCartItemBinding.inflate(LayoutInflater.from(parent.context), parent, false)
        return ViewHolder(binding)
    }

    override fun onBindViewHolder(holder: ViewHolder, position: Int) {
        holder.bind(getItem(position))
    }

    inner class ViewHolder(private val binding: ViewCartItemBinding) : RecyclerView.ViewHolder(binding.root) {
        fun bind(cartItem: CartItem) {
            val product = cartItem.product
            binding.itemName.text = product.name
            binding.itemQuantity.text = "${cartItem.quantity}"
            binding.itemTotal.text = CurrencyFormatter.format(product.price * cartItem.quantity)

            binding.decreaseButton.setOnClickListener {
                onQuantityChange(product.id, cartItem.quantity - 1)
            }
            binding.increaseButton.setOnClickListener {
                onQuantityChange(product.id, cartItem.quantity + 1)
            }
            binding.removeButton.setOnClickListener {
                onRemove(product.id)
            }
        }
    }

    object DiffCallback : DiffUtil.ItemCallback<CartItem>() {
        override fun areItemsTheSame(old: CartItem, new: CartItem) = old.product.id == new.product.id
        override fun areContentsTheSame(old: CartItem, new: CartItem) = old.quantity == new.quantity
    }
}
