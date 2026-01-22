/**
 * Anomaly Visibility Management
 * Manages hide/show functionality for non-serious anomalies in member and junior member pages
 */

(function() {
    'use strict';
    
    const STORAGE_KEY = 'easyvol_hidden_anomalies';
    
    /**
     * Load hidden anomalies from localStorage
     * @returns {Object} Object with hidden anomaly types as keys
     */
    function loadHiddenAnomalies() {
        try {
            const stored = localStorage.getItem(STORAGE_KEY);
            return stored ? JSON.parse(stored) : {};
        } catch (e) {
            console.error('Error loading hidden anomalies from localStorage:', e);
            return {};
        }
    }
    
    /**
     * Save hidden anomalies to localStorage
     * @param {Object} hiddenAnomalies Object with hidden anomaly types as keys
     */
    function saveHiddenAnomalies(hiddenAnomalies) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(hiddenAnomalies));
        } catch (e) {
            console.error('Error saving hidden anomalies to localStorage:', e);
        }
    }
    
    /**
     * Toggle anomaly visibility
     * @param {HTMLElement} card The anomaly card element
     * @param {HTMLElement} button The toggle button element
     */
    function toggleAnomaly(card, button) {
        const anomalyType = card.dataset.anomalyType;
        const content = card.querySelector('.anomaly-content');
        const hiddenAnomalies = loadHiddenAnomalies();
        
        if (content.style.display === 'none') {
            // Show anomaly
            content.style.display = 'block';
            button.innerHTML = '<i class="bi bi-eye-slash"></i> Nascondi';
            delete hiddenAnomalies[anomalyType];
        } else {
            // Hide anomaly
            content.style.display = 'none';
            button.innerHTML = '<i class="bi bi-eye"></i> Mostra';
            hiddenAnomalies[anomalyType] = true;
        }
        
        saveHiddenAnomalies(hiddenAnomalies);
    }
    
    /**
     * Initialize anomaly visibility management
     */
    function initAnomalyVisibility() {
        const anomalyCards = document.querySelectorAll('.anomaly-card');
        const hiddenAnomalies = loadHiddenAnomalies();
        
        anomalyCards.forEach(card => {
            const anomalyType = card.dataset.anomalyType;
            const toggleBtn = card.querySelector('.anomaly-toggle-btn');
            const content = card.querySelector('.anomaly-content');
            
            // Apply saved state
            if (hiddenAnomalies[anomalyType]) {
                content.style.display = 'none';
                toggleBtn.innerHTML = '<i class="bi bi-eye"></i> Mostra';
            }
            
            // Add click event
            toggleBtn.addEventListener('click', function() {
                toggleAnomaly(card, toggleBtn);
            });
        });
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAnomalyVisibility);
    } else {
        // DOM is already loaded
        initAnomalyVisibility();
    }
})();
