(function () {
    'use strict';

    try {
        var theme = localStorage.getItem('pos-theme') || 'dark';
        if (theme === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
        else document.documentElement.removeAttribute('data-theme');

        var sidebarState = localStorage.getItem('pos-sidebar');
        if (sidebarState === 'collapsed' && window.innerWidth > 768 && document.body) {
            document.body.classList.add('sidebar-collapsed');
        }
    } catch (e) {}

    function init() {
        try {
            if (localStorage.getItem('pos-sidebar') === 'collapsed' && window.innerWidth > 768) {
                document.body.classList.add('sidebar-collapsed');
            }
        } catch (e) {}

        var themeBtn = document.getElementById('theme-toggle');
        if (themeBtn) {
            themeBtn.addEventListener('click', function () {
                var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
                if (isDark) {
                    document.documentElement.removeAttribute('data-theme');
                    localStorage.setItem('pos-theme', 'light');
                } else {
                    document.documentElement.setAttribute('data-theme', 'dark');
                    localStorage.setItem('pos-theme', 'dark');
                }
                updateThemeIcon();
            });
        }

        function updateThemeIcon() {
            var btn = document.getElementById('theme-toggle');
            if (!btn) return;
            var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            btn.innerHTML = isDark ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
        }
        updateThemeIcon();

        var navToggle = document.getElementById('navToggle');
        var sidebar = document.getElementById('sidebar');
        var overlay = document.getElementById('sidebarOverlay');
        if (navToggle && sidebar) {
            navToggle.addEventListener('click', function () {
                sidebar.classList.toggle('open');
                if (overlay) overlay.classList.toggle('show');
            });
            if (overlay) {
                overlay.addEventListener('click', function () {
                    sidebar.classList.remove('open');
                    overlay.classList.remove('show');
                });
            }
        }

        var collapseBtn = document.getElementById('sidebarCollapseBtn');
        if (collapseBtn) {
            collapseBtn.addEventListener('click', function () {
                var isCollapsed = document.body.classList.contains('sidebar-collapsed');
                if (isCollapsed) {
                    document.body.classList.remove('sidebar-collapsed');
                    localStorage.setItem('pos-sidebar', 'expanded');
                    collapseBtn.setAttribute('aria-label', 'Collapse sidebar');
                    collapseBtn.setAttribute('title', 'Collapse sidebar');
                    removeSidebarTitles();
                } else {
                    document.body.classList.add('sidebar-collapsed');
                    localStorage.setItem('pos-sidebar', 'collapsed');
                    collapseBtn.setAttribute('aria-label', 'Expand sidebar');
                    collapseBtn.setAttribute('title', 'Expand sidebar');
                    addSidebarTitles();
                }
            });
            var initCollapsed = document.body.classList.contains('sidebar-collapsed');
            collapseBtn.setAttribute('aria-label', initCollapsed ? 'Expand sidebar' : 'Collapse sidebar');
            collapseBtn.setAttribute('title', initCollapsed ? 'Expand sidebar' : 'Collapse sidebar');
            if (initCollapsed) addSidebarTitles();
        }

        function addSidebarTitles() {
            document.querySelectorAll('.sidebar-nav .nav-item > a, .sidebar-nav .nav-item > .dropdown-toggle').forEach(function (el) {
                var span = el.querySelector('span');
                if (span && !el.getAttribute('title')) {
                    el.setAttribute('data-original-title', span.textContent.trim());
                    el.setAttribute('title', span.textContent.trim());
                }
            });
        }

        function removeSidebarTitles() {
            document.querySelectorAll('.sidebar-nav .nav-item > a, .sidebar-nav .nav-item > .dropdown-toggle').forEach(function (el) {
                el.removeAttribute('title');
            });
        }

        var resizeTimer;
        window.addEventListener('resize', function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function () {
                if (window.innerWidth <= 768) {
                    document.body.classList.remove('sidebar-collapsed');
                    removeSidebarTitles();
                } else {
                    var stored = localStorage.getItem('pos-sidebar');
                    if (stored === 'collapsed') {
                        document.body.classList.add('sidebar-collapsed');
                        addSidebarTitles();
                    }
                }
                var btn = document.getElementById('sidebarCollapseBtn');
                if (btn) {
                    var isCollapsed = document.body.classList.contains('sidebar-collapsed');
                    btn.setAttribute('aria-label', isCollapsed ? 'Expand sidebar' : 'Collapse sidebar');
                    btn.setAttribute('title', isCollapsed ? 'Expand sidebar' : 'Collapse sidebar');
                }
            }, 200);
        });

        var dropdownToggles = document.querySelectorAll('.nav-item > .dropdown-toggle');
        dropdownToggles.forEach(function (toggle) {
            toggle.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                if (document.body.classList.contains('sidebar-collapsed')) return;
                var parent = this.parentElement;
                var menu = parent.querySelector('.dropdown-menu');
                var isOpen = parent.classList.contains('open');
                closeAllDropdowns();
                if (!isOpen) {
                    parent.classList.add('open');
                    if (menu) menu.style.display = 'block';
                }
            });
        });

        document.addEventListener('click', function () {
            closeAllDropdowns();
        });

        function closeAllDropdowns() {
            document.querySelectorAll('.nav-item.open').forEach(function (item) {
                item.classList.remove('open');
                var menu = item.querySelector('.dropdown-menu');
                if (menu) menu.style.display = '';
            });
        }

        document.querySelectorAll('.table-container table').forEach(function (t) {
            var wrapper = document.createElement('div');
            wrapper.className = 'table-responsive';
            t.parentNode.insertBefore(wrapper, t);
            wrapper.appendChild(t);
        });

        var toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container';
        document.body.appendChild(toastContainer);

        window.showToast = function (message, type) {
            type = type || 'success';
            var toast = document.createElement('div');
            toast.className = 'toast ' + type;
            toast.textContent = message;
            toastContainer.appendChild(toast);
            setTimeout(function () {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(100%)';
                setTimeout(function () { toast.remove(); }, 300);
            }, 3500);
        };

        var flashMsg = document.getElementById('flash-message');
        if (flashMsg) {
            var data = flashMsg.getAttribute('data-flash');
            if (data) {
                try {
                    var parsed = JSON.parse(data);
                    showToast(parsed.message, parsed.type);
                } catch (e) {}
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
