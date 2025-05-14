// doctor.js - Doctor dashboard functionality

document.addEventListener('DOMContentLoaded', function() {
    // Patient search functionality
    const patientSearch = document.getElementById('patientSearch');
    if (patientSearch) {
        patientSearch.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('.patient-list tbody tr');
            
            rows.forEach(row => {
                const name = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
                const phone = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
                
                if (name.includes(searchTerm) || phone.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
    
    // View patient vitals
    const viewVitalsButtons = document.querySelectorAll('.view-vitals');
    viewVitalsButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const patientId = this.getAttribute('data-patient-id');
            window.location.href = `vitals.php?patient_id=${patientId}`;
        });
    });
    
    // Generate report form
    const reportForm = document.getElementById('reportForm');
    if (reportForm) {
        reportForm.addEventListener('submit', function(e) {
            const patientId = document.getElementById('patient_id').value;
            const reportType = document.getElementById('report_type').value;
            const timePeriod = document.getElementById('time_period').value;
            
            if (!patientId || !reportType || !timePeriod) {
                e.preventDefault();
                alert('Please fill in all fields');
            }
        });
    }
    
    // Real-time monitoring simulation
    const monitoringSection = document.getElementById('realTimeMonitoring');
    if (monitoringSection) {
        // This would be replaced with actual WebSocket connection in production
        setInterval(() => {
            fetch('../api/check_alerts.php')
                .then(response => response.json())
                .then(data => {
                    if (data.alerts && data.alerts.length > 0) {
                        data.alerts.forEach(alert => {
                            showAlertNotification(alert);
                        });
                    }
                });
        }, 30000); // Check every 30 seconds
        
        function showAlertNotification(alert) {
            const notification = document.createElement('div');
            notification.className = 'alert-notification';
            notification.innerHTML = `
                <strong>Alert for ${alert.patient_name}:</strong>
                <p>${alert.message}</p>
                <small>${new Date(alert.sent_at).toLocaleTimeString()}</small>
            `;
            monitoringSection.prepend(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 10000);
        }
    }
});