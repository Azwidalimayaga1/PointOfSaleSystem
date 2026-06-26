package com.possystem.app.ui.users

import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import androidx.fragment.app.Fragment
import androidx.fragment.app.viewModels
import androidx.recyclerview.widget.LinearLayoutManager
import com.possystem.app.databinding.FragmentUsersBinding

class UsersFragment : Fragment() {
    private var _binding: FragmentUsersBinding? = null
    private val binding get() = _binding!!
    private val usersViewModel: UsersViewModel by viewModels()
    private lateinit var adapter: UserAdapter

    override fun onCreateView(inflater: LayoutInflater, container: ViewGroup?, savedInstanceState: Bundle?): View {
        _binding = FragmentUsersBinding.inflate(inflater, container, false)
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)

        adapter = UserAdapter()
        binding.usersRecyclerView.layoutManager = LinearLayoutManager(requireContext())
        binding.usersRecyclerView.adapter = adapter

        usersViewModel.users.observe(viewLifecycleOwner) { users ->
            adapter.submitList(users)
            binding.emptyText.visibility = if (users.isEmpty()) View.VISIBLE else View.GONE
        }

        usersViewModel.isLoading.observe(viewLifecycleOwner) { loading ->
            binding.usersProgress.visibility = if (loading) View.VISIBLE else View.GONE
        }

        usersViewModel.loadUsers()
    }

    override fun onDestroyView() {
        super.onDestroyView()
        _binding = null
    }
}
