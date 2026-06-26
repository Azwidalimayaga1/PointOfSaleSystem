package com.possystem.app.ui.reports

import android.view.LayoutInflater
import android.view.ViewGroup
import android.widget.TextView
import androidx.recyclerview.widget.DiffUtil
import androidx.recyclerview.widget.ListAdapter
import androidx.recyclerview.widget.RecyclerView
import com.possystem.app.R
import com.possystem.app.util.CurrencyFormatter

class ReportAdapter : ListAdapter<Pair<String, Double>, ReportAdapter.ViewHolder>(DiffCallback) {

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): ViewHolder {
        val view = LayoutInflater.from(parent.context)
            .inflate(android.R.layout.simple_list_item_2, parent, false)
        return ViewHolder(view)
    }

    override fun onBindViewHolder(holder: ViewHolder, position: Int) {
        val item = getItem(position)
        holder.text1.text = item.first
        holder.text2.text = CurrencyFormatter.format(item.second)
    }

    class ViewHolder(view: android.view.View) : RecyclerView.ViewHolder(view) {
        val text1: TextView = view.findViewById(android.R.id.text1)
        val text2: TextView = view.findViewById(android.R.id.text2)
    }

    object DiffCallback : DiffUtil.ItemCallback<Pair<String, Double>>() {
        override fun areItemsTheSame(old: Pair<String, Double>, new: Pair<String, Double>) = old.first == new.first
        override fun areContentsTheSame(old: Pair<String, Double>, new: Pair<String, Double>) = old == new
    }
}
