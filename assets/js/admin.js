// admin.js - Admin dashboard functionality

document.addEventListener('DOMContentLoaded', function() {
    // User management table
    const userTable = document.getElementById('userTable');
    if (userTable) {
        // Enable row selection
        const rows = userTable.querySelectorAll('tbody tr');
        rows.forEach(row => {
            row.addEventListener('click', function() {
                this.classList.toggle('selected');
            });
        });
        
        // Bulk actions
        const bulkActions = document.getElementById('bulkActions');
        if (bulkActions) {
            const actionSelect = bulkActions.querySelector('select');
            const applyButton = bulkActions.querySelector('button');
            
            applyButton.addEventListener('click', function() {
                const selectedRows = userTable.querySelectorAll('tbody tr.selected');
                const action = actionSelect.value;
                
                if (selectedRows.length === 0) {
                    alert('Please select at least one user');
                    return;
                }
                
                if (action === '') {
                    alert('Please select an action');
                    return;
                }
                
                const userIds = Array.from(selectedRows).map(row => row.getAttribute('data-user-id'));
                
                // Confirm dangerous actions
                if (action === 'delete' && !confirm('Are you sure you want to delete the selected users?')) {
                    return;
                }
                
                // Send request to server
                fetch('../api/bulk_user_actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: action,
                        user_ids: userIds
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(`Successfully ${action}ed ${data.count} users`);
                        location.reload();
                    } else {
                        alert('Error performing action: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error performing action');
                });
            });
        }
    }
    
    // System settings form
    const settingsForm = document.getElementById('settingsForm');
    if (settingsForm) {
        settingsForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const data = {};
            formData.forEach((value, key) => {
                data[key] = value;
            });
            
            fetch('../api/update_settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Settings updated successfully');
                } else {
                    alert('Error updating settings: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating settings');
            });
        });
    }
    
    // Backup system
    const backupButton = document.getElementById('startBackup');
    if (backupButton) {
        backupButton.addEventListener('click', function() {
            if (!confirm('Start a system backup? This may take several minutes.')) {
                return;
            }
            
            this.disabled = true;
            this.textContent = 'Backup in progress...';
            
            fetch('../api/start_backup.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Backup started successfully. You will be notified when complete.');
                        setTimeout(() => {
                            location.reload();
                        }, 3000);
                    } else {
                        alert('Error starting backup: ' + (data.message || 'Unknown error'));
                        this.disabled = false;
                        this.textContent = 'Start Backup';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error starting backup');
                    this.disabled = false;
                    this.textContent = 'Start Backup';
                });
        });
    }
});