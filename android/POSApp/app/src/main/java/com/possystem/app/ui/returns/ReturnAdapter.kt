package com.possystem.app.ui.returns

import android.view.LayoutInflater
import android.view.ViewGroup
import android.widget.TextView
import androidx.recyclerview.widget.DiffUtil
import androidx.recyclerview.widget.ListAdapter
import androidx.recyclerview.widget.RecyclerView
import com.possystem.app.data.model.ReturnRequest

class ReturnAdapter : ListAdapter<ReturnRequest, ReturnAdapter.ViewHolder>(DiffCallback) {

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): ViewHolder {
        val view = LayoutInflater.from(parent.context)
            .inflate(android.R.layout.simple_list_item_2, parent, false)
        return ViewHolder(view)
    }

    override fun onBindViewHolder(holder: ViewHolder, position: Int) {
        val item = getItem(position)
        holder.text1.text = "Return #${item.id} - ${item.receiptNumber ?: "N/A"}"
        holder.text2.text = "Status: ${item.status ?: "pending"} | ${item.productName ?: ""}"
    }

    class ViewHolder(view: android.view.View) : RecyclerView.ViewHolder(view) {
        val text1: TextView = view.findViewById(android.R.id.text1)
        val text2: TextView = view.findViewById(android.R.id.text2)
    }

    object DiffCallback : DiffUtil.ItemCallback<ReturnRequest>() {
        override fun areItemsTheSame(old: ReturnRequest, new: ReturnRequest) = old.id == new.id
        override fun areContentsTheSame(old: ReturnRequest, new: ReturnRequest) = old == new
    }
}
