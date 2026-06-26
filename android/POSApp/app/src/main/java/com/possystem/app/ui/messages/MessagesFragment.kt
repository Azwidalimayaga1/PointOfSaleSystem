package com.possystem.app.ui.messages

import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import androidx.fragment.app.Fragment
import androidx.fragment.app.viewModels
import androidx.recyclerview.widget.LinearLayoutManager
import com.possystem.app.databinding.FragmentMessagesBinding

class MessagesFragment : Fragment() {
    private var _binding: FragmentMessagesBinding? = null
    private val binding get() = _binding!!
    private val messagesViewModel: MessagesViewModel by viewModels()
    private lateinit var adapter: MessageAdapter

    override fun onCreateView(inflater: LayoutInflater, container: ViewGroup?, savedInstanceState: Bundle?): View {
        _binding = FragmentMessagesBinding.inflate(inflater, container, false)
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)

        adapter = MessageAdapter()
        binding.messagesRecyclerView.layoutManager = LinearLayoutManager(requireContext())
        binding.messagesRecyclerView.adapter = adapter

        binding.sendButton.setOnClickListener {
            val text = binding.messageInput.text.toString().trim()
            if (text.isNotEmpty()) {
                messagesViewModel.sendMessage(text) {
                    binding.messageInput.setText("")
                }
            }
        }

        messagesViewModel.messages.observe(viewLifecycleOwner) { messages ->
            adapter.submitList(messages)
            binding.emptyText.visibility = if (messages.isEmpty()) View.VISIBLE else View.GONE
            if (messages.isNotEmpty()) {
                binding.messagesRecyclerView.smoothScrollToPosition(messages.size - 1)
            }
        }

        messagesViewModel.isLoading.observe(viewLifecycleOwner) { loading ->
            binding.messagesProgress.visibility = if (loading) View.VISIBLE else View.GONE
        }

        messagesViewModel.loadMessages()
    }

    override fun onDestroyView() {
        super.onDestroyView()
        _binding = null
    }
}
