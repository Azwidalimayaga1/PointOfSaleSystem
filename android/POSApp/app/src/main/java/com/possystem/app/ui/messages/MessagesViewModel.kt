package com.possystem.app.ui.messages

import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.possystem.app.data.model.Message
import com.possystem.app.data.repository.POSRepository
import kotlinx.coroutines.launch

class MessagesViewModel : ViewModel() {
    private val repository = POSRepository()

    private val _isLoading = MutableLiveData(false)
    val isLoading: LiveData<Boolean> = _isLoading

    private val _messages = MutableLiveData<List<Message>>()
    val messages: LiveData<List<Message>> = _messages

    private val _error = MutableLiveData<String?>()
    val error: LiveData<String?> = _error

    fun loadMessages() {
        viewModelScope.launch {
            _isLoading.value = true
            repository.getMessages().fold(
                onSuccess = { _messages.value = it },
                onFailure = { _error.value = it.message }
            )
            _isLoading.value = false
        }
    }

    fun sendMessage(text: String, onSuccess: () -> Unit) {
        viewModelScope.launch {
            _isLoading.value = true
            repository.sendMessage(text).fold(
                onSuccess = { loadMessages(); onSuccess() },
                onFailure = { _error.value = it.message }
            )
            _isLoading.value = false
        }
    }
}
