package com.possystem.app

import android.os.Bundle
import android.view.View
import androidx.appcompat.app.AppCompatActivity
import androidx.drawerlayout.widget.DrawerLayout
import androidx.navigation.NavController
import androidx.navigation.fragment.NavHostFragment
import androidx.navigation.ui.setupWithNavController
import com.google.android.material.navigation.NavigationView
import com.google.android.material.snackbar.Snackbar
import com.possystem.app.databinding.ActivityMainBinding
import com.possystem.app.ui.auth.AuthViewModel
import com.possystem.app.util.SessionManager
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch

class MainActivity : AppCompatActivity() {
    private lateinit var binding: ActivityMainBinding
    private lateinit var navController: NavController
    private val authViewModel = AuthViewModel()

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityMainBinding.inflate(layoutInflater)
        setContentView(binding.root)

        val navHostFragment = supportFragmentManager
            .findFragmentById(R.id.navHostFragment) as NavHostFragment
        navController = navHostFragment.navController

        setSupportActionBar(binding.toolbar)

        setupNavigation()
        observeAuthState()

        CoroutineScope(Dispatchers.IO).launch {
            SessionManager.restoreSession()
        }
    }

    private fun setupNavigation() {
        binding.navigationView.setupWithNavController(navController)

        binding.navigationView.setNavigationItemSelectedListener { menuItem ->
            when (menuItem.itemId) {
                R.id.nav_logout -> {
                    authViewModel.logout()
                    binding.drawerLayout.closeDrawers()
                    true
                }
                else -> {
                    val id = when (menuItem.itemId) {
                        R.id.nav_dashboard -> R.id.dashboardFragment
                        R.id.nav_sales -> R.id.salesFragment
                        R.id.nav_products -> R.id.productsFragment
                        R.id.nav_inventory -> R.id.inventoryFragment
                        R.id.nav_customers -> R.id.customersFragment
                        R.id.nav_reports -> R.id.reportsFragment
                        R.id.nav_returns -> R.id.returnsFragment
                        R.id.nav_messages -> R.id.messagesFragment
                        R.id.nav_users -> R.id.usersFragment
                        R.id.nav_settings -> R.id.settingsFragment
                        else -> null
                    }
                    if (id != null) {
                        navController.navigate(id)
                    }
                    binding.drawerLayout.closeDrawers()
                    true
                }
            }
        }

        navController.addOnDestinationChangedListener { _, destination, _ ->
            val showDrawer = destination.id != R.id.loginFragment
            binding.drawerLayout.setDrawerLockMode(
                if (showDrawer) DrawerLayout.LOCK_MODE_UNLOCKED
                else DrawerLayout.LOCK_MODE_LOCKED_CLOSED
            )
            supportActionBar?.setDisplayHomeAsUpEnabled(showDrawer)
            binding.toolbar.title = destination.label ?: ""
        }
    }

    private fun observeAuthState() {
        authViewModel.isLoggedIn.observe(this) { loggedIn ->
            if (loggedIn) {
                navController.navigate(R.id.dashboardFragment)
            } else {
                navController.navigate(R.id.loginFragment) {
                    popUpTo(0) { inclusive = true }
                }
            }
        }
    }

    fun showError(message: String) {
        Snackbar.make(binding.root, message, Snackbar.LENGTH_LONG).show()
    }
}
