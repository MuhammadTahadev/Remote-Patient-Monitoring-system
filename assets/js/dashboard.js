document.addEventListener('DOMContentLoaded', function() {
    // Handle sidebar navigation
    const navLinks = document.querySelectorAll('.sidebar-nav a[data-page]');
    const mainContent = document.getElementById('main-content');
    
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const page = this.getAttribute('data-page');
            
            // Update active state
            navLinks.forEach(l => l.parentElement.classList.remove('active'));
            this.parentElement.classList.add('active');
            
            // Load content via AJAX
            loadPageContent(page);
        });
    });
    
    // Function to load page content
    function loadPageContent(page) {
        const xhr = new XMLHttpRequest();
        xhr.open('GET', `ajax/${page}.php`, true);
        
        xhr.onload = function() {
            if (this.status === 200) {
                mainContent.innerHTML = this.responseText;
                
                // Initialize any page-specific scripts
                initializePageScripts(page);
                
                // Update browser history
                history.pushState({ page: page }, '', `?page=${page}`);
            } else {
                mainContent.innerHTML = '<div class="error">Error loading content. Please try again.</div>';
            }
        };
        
        xhr.onerror = function() {
            mainContent.innerHTML = '<div class="error">Network error. Please check your connection.</div>';
        };
        
        xhr.send();
    }
    
    // Initialize page-specific scripts
    function initializePageScripts(page) {
        switch(page) {
            case 'dashboard':
                // Initialize dashboard charts
                initDashboardCharts();
                break;
            case 'vitals':
                // Initialize vitals page scripts
                initVitalsPage();
                break;
            case 'trends':
                // Initialize trends charts
                initTrendsCharts();
                break;
            // Add other pages as needed
        }
    }
    
    // Handle browser back/forward buttons
    window.addEventListener('popstate', function(e) {
        if (e.state && e.state.page) {
            const page = e.state.page;
            const activeLink = document.querySelector(`.sidebar-nav a[data-page="${page}"]`);
            
            if (activeLink) {
                navLinks.forEach(l => l.parentElement.classList.remove('active'));
                activeLink.parentElement.classList.add('active');
                loadPageContent(page);
            }
        }
    });
    
    // Check for page parameter in URL on initial load
    const urlParams = new URLSearchParams(window.location.search);
    const pageParam = urlParams.get('page');
    
    if (pageParam && document.querySelector(`.sidebar-nav a[data-page="${pageParam}"]`)) {
        document.querySelector(`.sidebar-nav a[data-page="${pageParam}"]`).click();
    }
});

// Example page initialization functions
function initDashboardCharts() {
    // Same chart initialization code as in dashboard_content.php
    const vitalCtx = document.getElementById('vitalChart')?.getContext('2d');
    if (vitalCtx) {
        new Chart(vitalCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [
                    {
                        label: 'Heart Rate (bpm)',
                        data: [72, 75, 78, 76, 80, 82],
                        borderColor: '#780000',
                        backgroundColor: 'rgba(120, 0, 0, 0.1)',
                        borderWidth: 2,
                        tension: 0.3
                    },
                    {
                        label: 'Systolic BP',
                        data: [120, 122, 125, 130, 128, 132],
                        borderColor: '#C1121F',
                        backgroundColor: 'rgba(193, 18, 31, 0.1)',
                        borderWidth: 2,
                        tension: 0.3
                    },
                    {
                        label: 'Glucose (mg/dL)',
                        data: [110, 115, 120, 118, 125, 130],
                        borderColor: '#003049',
                        backgroundColor: 'rgba(0, 48, 73, 0.1)',
                        borderWidth: 2,
                        tension: 0.3
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: false
                    }
                }
            }
        });
    }
    
    const healthCtx = document.getElementById('healthChart')?.getContext('2d');
    if (healthCtx) {
        new Chart(healthCtx, {
            type: 'doughnut',
            data: {
                labels: ['Normal', 'Warning', 'Critical'],
                datasets: [{
                    data: [65, 25, 10],
                    backgroundColor: [
                        '#669BBC',
                        '#C1121F',
                        '#780000'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${percentage}%`;
                            }
                        }
                    }
                }
            }
        });
    }
}

function initVitalsPage() {
    // Initialize any vitals page specific scripts
    // For example, form submission handling
    const vitalsForm = document.getElementById('vitalsForm');
    if (vitalsForm) {
        vitalsForm.addEventListener('submit', function(e) {
            e.preventDefault();
            // Handle form submission via AJAX
        });
    }
}

function initTrendsCharts() {
    // Initialize trends charts if they exist
    // Similar to the dashboard charts initialization
}