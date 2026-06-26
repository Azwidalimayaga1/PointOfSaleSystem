package com.possystem.app.ui.auth

import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import androidx.fragment.app.Fragment
import androidx.fragment.app.activityViewModels
import com.possystem.app.R
import com.possystem.app.databinding.FragmentLoginBinding

class LoginFragment : Fragment() {
    private var _binding: FragmentLoginBinding? = null
    private val binding get() = _binding!!
    private val authViewModel: AuthViewModel by activityViewModels()

    override fun onCreateView(inflater: LayoutInflater, container: ViewGroup?, savedInstanceState: Bundle?): View {
        _binding = FragmentLoginBinding.inflate(inflater, container, false)
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)

        binding.loginButton.setOnClickListener {
            val username = binding.usernameInput.text.toString().trim()
            val password = binding.passwordInput.text.toString().trim()

            if (username.isEmpty() || password.isEmpty()) {
                binding.errorText.text = "Please enter username and password"
                binding.errorText.visibility = View.VISIBLE
                return@setOnClickListener
            }

            authViewModel.login(username, password)
        }

        authViewModel.isLoading.observe(viewLifecycleOwner) { loading ->
            binding.loginButton.isEnabled = !loading
            binding.loginProgress.visibility = if (loading) View.VISIBLE else View.GONE
        }

        authViewModel.error.observe(viewLifecycleOwner) { error ->
            binding.errorText.text = error ?: getString(R.string.error)
            binding.errorText.visibility = if (error != null) View.VISIBLE else View.GONE
        }
    }

    override fun onDestroyView() {
        super.onDestroyView()
        _binding = null
    }
}
