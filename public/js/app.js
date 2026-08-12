/**
 * Main Application JavaScript
 * Mobile-friendly sidebar with swipe & touch support
 */

document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarClose = document.getElementById('sidebarClose');

    // Create overlay
    const sidebarOverlay = document.createElement('div');
    sidebarOverlay.className = 'sidebar-overlay';
    document.body.appendChild(sidebarOverlay);

    function openSidebar() {
        sidebar.classList.add('show');
        sidebarOverlay.classList.add('show');
        document.body.style.overflow = 'hidden'; // Lock scroll
    }

    function closeSidebar() {
        sidebar.classList.remove('show');
        sidebarOverlay.classList.remove('show');
        document.body.style.overflow = ''; // Unlock scroll
    }

    // Toggle button
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function (e) {
            e.stopPropagation();
            if (sidebar.classList.contains('show')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
    }

    // Close button inside sidebar
    if (sidebarClose) {
        sidebarClose.addEventListener('click', function (e) {
            e.stopPropagation();
            closeSidebar();
        });
    }

    // Close sidebar when clicking overlay
    sidebarOverlay.addEventListener('click', closeSidebar);

    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function (event) {
        if (window.innerWidth <= 767.98) {
            if (!sidebar.contains(event.target) &&
                sidebarToggle && !sidebarToggle.contains(event.target) &&
                sidebar.classList.contains('show')) {
                closeSidebar();
            }
        }
    });

    // Close sidebar on nav link click (mobile)
    const navLinks = sidebar.querySelectorAll('.nav-link');
    navLinks.forEach(function (link) {
        link.addEventListener('click', function () {
            if (window.innerWidth <= 767.98) {
                closeSidebar();
            }
        });
    });

    // Swipe-to-close gesture on sidebar
    let touchStartX = 0;
    let touchStartY = 0;
    let isSwiping = false;

    sidebar.addEventListener('touchstart', function (e) {
        touchStartX = e.touches[0].clientX;
        touchStartY = e.touches[0].clientY;
        isSwiping = true;
    }, { passive: true });

    sidebar.addEventListener('touchmove', function (e) {
        if (!isSwiping) return;
        const deltaX = e.touches[0].clientX - touchStartX;
        const deltaY = Math.abs(e.touches[0].clientY - touchStartY);

        // Only if horizontal swipe and going left
        if (deltaX < -50 && deltaY < 40) {
            closeSidebar();
            isSwiping = false;
        }
    }, { passive: true });

    sidebar.addEventListener('touchend', function () {
        isSwiping = false;
    }, { passive: true });

    // Swipe from left edge to open sidebar
    let edgeTouchStartX = 0;
    document.addEventListener('touchstart', function (e) {
        edgeTouchStartX = e.touches[0].clientX;
    }, { passive: true });

    document.addEventListener('touchend', function (e) {
        if (window.innerWidth <= 767.98 && !sidebar.classList.contains('show')) {
            const endX = e.changedTouches[0].clientX;
            if (edgeTouchStartX < 25 && (endX - edgeTouchStartX) > 60) {
                openSidebar();
            }
        }
    }, { passive: true });

    // Close sidebar on window resize to desktop
    window.addEventListener('resize', function () {
        if (window.innerWidth > 767.98 && sidebar.classList.contains('show')) {
            closeSidebar();
        }
    });

    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert[data-auto-dismiss]');
    alerts.forEach(function (alert) {
        setTimeout(function () {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });

    // Confirm delete actions
    const deleteButtons = document.querySelectorAll('[data-confirm-delete]');
    deleteButtons.forEach(function (button) {
        button.addEventListener('click', function (e) {
            if (!confirm('Apakah Anda yakin ingin menghapus data ini?')) {
                e.preventDefault();
            }
        });
    });
});
