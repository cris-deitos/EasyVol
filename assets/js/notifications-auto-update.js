/**
 * Auto-update notifications, sidebar badges, and dashboard counters
 * Polls the API every 5 seconds to update the UI without page refresh
 */

(function() {
    'use strict';
    
    // Configuration
    const UPDATE_INTERVAL = 5000; // 5 seconds
    let updateTimer = null;
    let isDashboardPage = false;
    let isOperationsCenterPage = false;
    
    /**
     * Initialize auto-update system
     */
    function init() {
        // Check which page we're on
        const currentPage = window.location.pathname.split('/').pop();
        isDashboardPage = currentPage === 'dashboard.php';
        isOperationsCenterPage = currentPage === 'operations_center.php';
        
        // Start polling
        startPolling();
        
        // Stop polling when user leaves the page or tab
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                stopPolling();
            } else {
                startPolling();
            }
        });
        
        // Stop polling when page is about to unload
        window.addEventListener('beforeunload', function() {
            stopPolling();
        });
    }
    
    /**
     * Start polling for updates
     */
    function startPolling() {
        if (updateTimer) return; // Already polling
        
        // Initial update
        fetchUpdates();
        
        // Schedule periodic updates
        updateTimer = setInterval(fetchUpdates, UPDATE_INTERVAL);
    }
    
    /**
     * Stop polling for updates
     */
    function stopPolling() {
        if (updateTimer) {
            clearInterval(updateTimer);
            updateTimer = null;
        }
    }
    
    /**
     * Fetch updates from API
     */
    function fetchUpdates() {
        // Use relative path - works since all pages that include this script are in public/
        let url = 'api/notifications_update.php';
        const params = [];
        
        if (isDashboardPage) {
            params.push('include_dashboard=1');
        }
        
        if (isOperationsCenterPage) {
            params.push('include_operations_center=1');
        }
        
        if (params.length > 0) {
            url += '?' + params.join('&');
        }
        
        fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) {
                if (response.status === 401) {
                    // User logged out, stop polling
                    stopPolling();
                    return null;
                }
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data) {
                updateUI(data);
            }
        })
        .catch(error => {
            console.error('Error fetching notification updates:', error);
        });
    }
    
    /**
     * Update UI with new data
     */
    function updateUI(data) {
        // Update navbar notification badge
        updateNavbarNotifications(data.notifications);
        
        // Update sidebar badges
        updateSidebarBadges(data.counts);
        
        // Update dashboard stats if on dashboard page
        if (isDashboardPage && data.dashboard_stats) {
            updateDashboardStats(data.dashboard_stats);
        }
        
        // Update operations center stats if on operations center page
        if (isOperationsCenterPage && data.operations_center_stats) {
            updateOperationsCenterStats(data.operations_center_stats);
        }
    }
    
    /**
     * Update navbar notification badge and dropdown
     */
    function updateNavbarNotifications(notifications) {
        const badge = document.querySelector('#notificationsDropdown .badge');
        const dropdown = document.querySelector('#notificationsDropdown + .dropdown-menu');
        
        if (!badge || !dropdown) return;
        
        const totalCount = notifications.total;
        
        // Update badge
        if (totalCount > 0) {
            badge.textContent = totalCount;
            badge.style.display = '';
        } else {
            badge.style.display = 'none';
        }
        
        // Update dropdown content
        if (notifications.items && notifications.items.length > 0) {
            let dropdownHTML = '<li><h6 class="dropdown-header">Notifiche</h6></li>';
            
            notifications.items.forEach(function(item) {
                // Validate icon - must be a valid Bootstrap icon class
                const icon = item.icon && /^bi-[a-z0-9]+(?:-[a-z0-9]+)*$/.test(item.icon) ? item.icon : 'bi-bell';
                
                // Validate link - must be a relative PHP path with safe query params
                const link = item.link && /^[a-zA-Z0-9_\-\/]+\.php(\?[a-zA-Z0-9_=&\-]+)?$/.test(item.link) ? item.link : '#';
                
                const escapedText = escapeHtml(item.text);
                
                dropdownHTML += `
                    <li>
                        <a class="dropdown-item" href="${link}">
                            <i class="bi ${icon}"></i>
                            ${escapedText}
                        </a>
                    </li>
                `;
            });
            
            dropdownHTML += '<li><hr class="dropdown-divider"></li>';
            dropdownHTML += '<li><a class="dropdown-item text-center text-primary" href="dashboard.php">Vedi tutte</a></li>';
            
            dropdown.innerHTML = dropdownHTML;
        } else {
            dropdown.innerHTML = `
                <li><h6 class="dropdown-header">Notifiche</h6></li>
                <li><a class="dropdown-item text-center text-muted">Nessuna notifica</a></li>
            `;
        }
    }
    
    /**
     * Update sidebar badges
     */
    function updateSidebarBadges(counts) {
        // Update applications badge
        const applicationsBadge = document.querySelector('a[href="applications.php"] .badge');
        if (applicationsBadge) {
            if (counts.applications > 0) {
                applicationsBadge.textContent = counts.applications;
                applicationsBadge.style.display = '';
            } else {
                applicationsBadge.style.display = 'none';
            }
        }
        
        // Update fee payments badge
        const feePaymentsBadge = document.querySelector('a[href="fee_payments.php"] .badge');
        if (feePaymentsBadge) {
            if (counts.fee_payments > 0) {
                feePaymentsBadge.textContent = counts.fee_payments;
                feePaymentsBadge.style.display = '';
            } else {
                feePaymentsBadge.style.display = 'none';
            }
        }
    }
    
    /**
     * Update dashboard statistics cards
     */
    function updateDashboardStats(stats) {
        // Update each stat with smooth transition
        updateStatValue('active_members', stats.active_members);
        updateStatValue('junior_members', stats.junior_members);
        updateStatValue('pending_applications', stats.pending_applications);
        updateStatValue('pending_fee_requests', stats.pending_fee_requests);
    }
    
    /**
     * Update operations center statistics
     */
    function updateOperationsCenterStats(stats) {
        updateStatValue('active_events', stats.active_events);
        updateStatValue('available_members', stats.available_members);
        updateStatValue('available_vehicles', stats.available_vehicles);
        updateStatValue('available_radios', stats.available_radios);
    }
    
    /**
     * Update a single stat value with smooth animation
     */
    function updateStatValue(statKey, newValue) {
        // Find the element by data attribute
        const element = document.querySelector(`[data-stat="${statKey}"]`);
        
        if (element) {
            const currentValue = parseInt(element.textContent) || 0;
            
            if (currentValue !== newValue) {
                // Add animation class
                element.classList.add('stat-updating');
                
                // Update value
                element.textContent = newValue;
                
                // Remove animation class after animation completes
                setTimeout(function() {
                    element.classList.remove('stat-updating');
                }, 300);
            }
        }
    }
    
    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
