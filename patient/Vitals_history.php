<?php
require_once '../config/config.php';
require_once '../includes/header.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Ensure only patients can access
requireRole('Patient');

// Get patient data
$patient_id = $_SESSION['patient_id'] ?? getPatientByUserId($_SESSION['user_id'])['Patient_ID'];
$_SESSION['patient_id'] = $patient_id;

// Get all vitals records for the patient, ordered by most recent first
$vitals_history = [];
$stmt = $conn->prepare("SELECT * FROM HealthData WHERE Patient_ID = ? ORDER BY Timestamp DESC");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$result = $stmt->get_result();
$vitals_history = $result->fetch_all(MYSQLI_ASSOC);

// Get unread notifications count
$notification_count = 0;
$stmt = $conn->prepare("SELECT COUNT(*) FROM Notifications WHERE User_ID = ? AND Status = 'Sent'");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$notification_count = $stmt->get_result()->fetch_row()[0];
?>

<div class="patient-container">
    <h2>Vitals History</h2>
    
    <div class="back-link">
        <a href="dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>
    
    <?php if (!empty($vitals_history)): ?>
        <div class="table-responsive card">
            <table class="vitals-history-table">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Heart Rate</th>
                        <th>Blood Pressure</th>
                        <th>Glucose</th>
                        <th>Oxygen</th>
                        <th>Temperature</th>
                        <th>Weight</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vitals_history as $vital): ?>
                        <tr>
                            <td><?= date('M j, Y g:i A', strtotime($vital['Timestamp'])) ?></td>
                            <td>
                                <span class="vital-value"><?= $vital['Heart_Rate'] ?? '--' ?></span>
                                <span class="vital-status"><?= getVitalBadge('heart_rate', $vital['Heart_Rate']) ?></span>
                            </td>
                            <td>
                                <span class="vital-value"><?= ($vital['Systolic_BP'] ?? '--') . '/' . ($vital['Diastolic_BP'] ?? '--') ?></span>
                                <span class="vital-status"><?= getVitalBadge('blood_pressure', [$vital['Systolic_BP'], $vital['Diastolic_BP']]) ?></span>
                            </td>
                            <td>
                                <span class="vital-value"><?= $vital['Glucose_Level'] ?? '--' ?></span>
                                <span class="vital-status"><?= getVitalBadge('glucose', $vital['Glucose_Level']) ?></span>
                            </td>
                            <td>
                                <span class="vital-value"><?= $vital['Oxygen_Saturation'] ?? '--' ?></span>
                                <span class="vital-status"><?= getVitalBadge('oxygen', $vital['Oxygen_Saturation']) ?></span>
                            </td>
                            <td>
                                <span class="vital-value"><?= $vital['Temperature'] ?? '--' ?></span>
                                <span class="vital-status"><?= getVitalBadge('temperature', $vital['Temperature']) ?></span>
                            </td>
                            <td>
                                <span class="vital-value"><?= $vital['Weight'] ?? '--' ?></span>
                                <span class="vital-status"><?= getVitalBadge('weight', $vital['Weight']) ?></span>
                            </td>
                            <td><?= htmlspecialchars($vital['Notes'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="export-options">
            <button class="btn" onclick="printVitals()">
                <i class="fas fa-print"></i> Print Records
            </button>
        </div>
    <?php else: ?>
        <div class="no-records card">
            <i class="fas fa-info-circle"></i>
            <p>No vital records found. You can add your first vitals reading <a href="vitals.php">here</a>.</p>
        </div>
    <?php endif; ?>
</div>

<script>
function exportToCSV() {
    // This would be enhanced with actual CSV export functionality
    alert("Export to CSV functionality will be implemented here");
}

function printVitals() {
    window.print();
}
</script>

<style>
/* Vitals History Styles matching Patient Chat */
.patient-container {
    padding: 2rem;
    max-width: 1400px;
    margin: 0 auto;
}

.patient-container h2 {
    color: var(--primary-dark);
    font-size: 2.2rem;
    margin-bottom: 1.5rem;
    font-weight: 700;
    text-align: center;
    position: relative;
    padding-bottom: 1rem;
}

.patient-container h2::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 80px;
    height: 4px;
    background: var(--secondary);
    border-radius: 2px;
}

.back-link {
    margin-bottom: 1.5rem;
    text-align: left;
}

.btn-back {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.75rem 1.5rem;
    background-color: var(--primary-dark);
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
}

.btn-back:hover {
    background-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(96, 108, 56, 0.3);
}

.btn-back i {
    margin-right: 0.5rem;
}

.table-responsive {
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    padding: 2rem;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    overflow-x: auto;
}

.table-responsive:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12);
}

.vitals-history-table {
    width: 100%;
    border-collapse: collapse;
    background: #f9fafb;
    border-radius: 8px;
    overflow: hidden;
}

.vitals-history-table th,
.vitals-history-table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.vitals-history-table th {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    color: white;
    font-weight: 600;
}

.vitals-history-table td {
    background: white;
    color: var(--dark-gray);
}

.vital-value {
    display: block;
    font-weight: 500;
}

.vital-status {
    display: inline-block;
    margin-top: 0.25rem;
    font-size: 0.85rem;
    color: var(--secondary-dark);
}

.export-options {
    margin-top: 1.5rem;
    text-align: right;
}

.btn {
    padding: 0.75rem 1.5rem;
    background-color: var(--primary-dark);
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
}

.btn:hover {
    background-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(96, 108, 56, 0.3);
}

.btn i {
    margin-right: 0.5rem;
}

.no-records {
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    padding: 2rem;
    text-align: center;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.no-records:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12);
}

.no-records i {
    font-size: 2rem;
    color: var(--primary-dark);
    margin-bottom: 1rem;
    display: block;
}

.no-records p {
    margin: 0;
    font-size: 1.1rem;
    color: var(--dark-gray);
}

.no-records a {
    color: var(--primary);
    text-decoration: none;
    font-weight: 500;
}

.no-records a:hover {
    text-decoration: underline;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .patient-container {
        padding: 1.5rem;
    }
    
    .table-responsive,
    .no-records {
        padding: 1.5rem;
    }
    
    .vitals-history-table th,
    .vitals-history-table td {
        padding: 0.75rem;
    }
    
    .export-options {
        text-align: center;
    }
}

@media (max-width: 576px) {
    .patient-container {
        padding: 1rem;
    }
    
    .patient-container h2 {
        font-size: 1.8rem;
    }
    
    .vitals-history-table {
        font-size: 0.9rem;
    }
}
</style>

<?php require_once '../includes/footer.php'; ?>