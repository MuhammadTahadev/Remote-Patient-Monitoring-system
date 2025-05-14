// patient.js - Patient dashboard functionality

document.addEventListener('DOMContentLoaded', function() {
    // Vitals modal functionality
    const modal = document.getElementById('vitalDetailsModal');
    const modalContent = document.getElementById('vitalDetailsContent');
    const closeBtn = document.querySelector('.close');
    const viewDetailsLinks = document.querySelectorAll('.view-details');
    
    if (modal) {
        // Close modal when clicking X
        closeBtn.addEventListener('click', function() {
            modal.style.display = 'none';
        });
        
        // Close modal when clicking outside
        window.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        });
        
        // View details button click
        viewDetailsLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const dataId = this.getAttribute('data-id');
                
                // Fetch vital details via AJAX
                fetch(`../api/get_vital.php?id=${dataId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const vital = data.vital;
                            const html = `
                                <h4>Vital Details</h4>
                                <p><strong>Recorded:</strong> ${new Date(vital.Timestamp).toLocaleString()}</p>
                                <ul class="vital-details-list">
                                    <li><strong>Heart Rate:</strong> ${vital.Heart_Rate || '--'} bpm</li>
                                    <li><strong>Blood Pressure:</strong> ${vital.Systolic_BP || '--'}/${vital.Diastolic_BP || '--'} mmHg</li>
                                    <li><strong>Glucose Level:</strong> ${vital.Glucose_Level || '--'} mg/dL</li>
                                    <li><strong>Oxygen Saturation:</strong> ${vital.Oxygen_Saturation || '--'}%</li>
                                    <li><strong>Temperature:</strong> ${vital.Temperature || '--'}°F</li>
                                    <li><strong>Weight:</strong> ${vital.Weight || '--'} lbs</li>
                                </ul>
                                ${vital.Notes ? `<div class="notes"><strong>Notes:</strong><p>${vital.Notes}</p></div>` : ''}
                            `;
                            modalContent.innerHTML = html;
                            modal.style.display = 'block';
                        } else {
                            alert('Error loading vital details');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error loading vital details');
                    });
            });
        });
    }
    
    // Vitals form validation
    const vitalsForm = document.querySelector('.vitals-form form');
    if (vitalsForm) {
        vitalsForm.addEventListener('submit', function(e) {
            let hasValue = false;
            
            // Check if at least one field has a value
            const inputs = this.querySelectorAll('input[type="number"], textarea');
            inputs.forEach(input => {
                if (input.value.trim() !== '') {
                    hasValue = true;
                }
            });
            
            if (!hasValue) {
                e.preventDefault();
                alert('Please enter at least one vital sign');
            }
        });
    }
    
    // Notification bell animation
    const notificationBell = document.querySelector('.view-notifications');
    if (notificationBell && document.querySelector('.badge')) {
        notificationBell.addEventListener('click', function(e) {
            e.preventDefault();
            // In a real app, this would navigate to notifications page
            alert('Notifications page would open here');
        });
        
        // Pulse animation if there are notifications
        setInterval(() => {
            if (document.querySelector('.badge')) {
                notificationBell.classList.toggle('pulse');
            }
        }, 2000);
    }
});