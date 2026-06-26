package com.possystem.app.ui.users

import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.possystem.app.data.model.User
import com.possystem.app.data.repository.POSRepository
import kotlinx.coroutines.launch

class UsersViewModel : ViewModel() {
    private val repository = POSRepository()

    private val _isLoading = MutableLiveData(false)
    val isLoading: LiveData<Boolean> = _isLoading

    private val _users = MutableLiveData<List<User>>()
    val users: LiveData<List<User>> = _users

    private val _error = MutableLiveData<String?>()
    val error: LiveData<String?> = _error

    fun loadUsers() {
        viewModelScope.launch {
            _isLoading.value = true
            repository.getUsers().fold(
                onSuccess = { _users.value = it },
                onFailure = { _error.value = it.message }
            )
            _isLoading.value = false
        }
    }
}
