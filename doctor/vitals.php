<?php
require_once '../config/config.php';
require_once '../includes/header.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Ensure only doctors can access
requireRole('Doctor');

// Check if patient_id is provided and valid
if (!isset($_GET['patient_id'])) {
    setAlert('No patient selected', 'error');
    header("Location: patients.php");
    exit();
}

$patient_id = (int)$_GET['patient_id'];
$doctor_id = $_SESSION['doctor_id'];

// Verify the doctor is assigned to this patient
$stmt = $conn->prepare("SELECT 1 FROM DoctorPatientMapping 
                       WHERE Doctor_ID = ? AND Patient_ID = ?");
$stmt->bind_param("ii", $doctor_id, $patient_id);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
    setAlert('You are not authorized to view this patient', 'error');
    header("Location: patients.php");
    exit();
}

// Get patient info
$stmt = $conn->prepare("SELECT u.User_ID, u.Full_Name, u.EMAIL, p.Emergency_Contact_Number
                       FROM Patient p
                       JOIN User u ON p.User_ID = u.User_ID
                       WHERE p.Patient_ID = ?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();

// Get vitals history
$stmt = $conn->prepare("SELECT * FROM HealthData 
                       WHERE Patient_ID = ? 
                       ORDER BY Timestamp DESC");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$vitals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get threshold values
$thresholds = [];
$result = $conn->query("SELECT * FROM AlertThreshold");
while ($row = $result->fetch_assoc()) {
    $thresholds[$row['Metric_Type']] = $row;
}

// Function to determine the overall status for a vital record
function getOverallStatus($vital, $thresholds) {
    $statuses = [];
    $statuses[] = getVitalBadge('heart_rate', $vital['Heart_Rate'], $thresholds);
    $statuses[] = getVitalBadge('blood_pressure', [$vital['Systolic_BP'], $vital['Diastolic_BP']], $thresholds);
    $statuses[] = getVitalBadge('glucose', $vital['Glucose_Level'], $thresholds);
    $statuses[] = getVitalBadge('oxygen', $vital['Oxygen_Saturation'], $thresholds);
    $statuses[] = getVitalBadge('temperature', $vital['Temperature'], $thresholds);
    $priority = ['Critical' => 3, 'Warning' => 2, 'Normal' => 1];
    $highest_priority = 1;
    $overall_status = 'Normal';
    foreach ($statuses as $status) {
        if (isset($priority[$status]) && $priority[$status] > $highest_priority) {
            $highest_priority = $priority[$status];
            $overall_status = $status;
        }
    }
    return $overall_status;
}
displayAlert();
?>

<div class="patient-container">
    <div class="back-link">
        <a href="dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>
    <h2>Patient Vitals: <?= htmlspecialchars($patient['Full_Name']) ?></h2>
    
    <div class="patient-info card">
        <p><strong>Email Address:</strong> <?= htmlspecialchars($patient['EMAIL']) ?></p>
        <p><strong>Emergency Contact:</strong> <?= htmlspecialchars($patient['Emergency_Contact_Number']) ?></p>
    </div>
    
    <div class="vitals-container">
        <?php if (!empty($vitals)): ?>
            <div class="vitals-history card">
                <h3>Vitals History</h3>
                <div class="table-responsive">
                    <table class="vitals-history-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Heart Rate</th>
                                <th>Blood Pressure</th>
                                <th>Glucose</th>
                                <th>Oxygen</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vitals as $vital): ?>
                                <tr>
                                    <td><?= date('m/d/y H:i', strtotime($vital['Timestamp'])) ?></td>
                                    <td><?= $vital['Heart_Rate'] ?? '--' ?></td>
                                    <td><?= ($vital['Systolic_BP'] ?? '--') . '/' . ($vital['Diastolic_BP'] ?? '--') ?></td>
                                    <td><?= $vital['Glucose_Level'] ?? '--' ?></td>
                                    <td><?= $vital['Oxygen_Saturation'] ?? '--' ?></td>
                                    <td>
                                        <span class="badge <?= strtolower(getOverallStatus($vital, $thresholds)) ?>-badge">
                                            <?= getOverallStatus($vital, $thresholds) ?>
                                        </span>
                                    </td>
                                    <td class="actions">
                                        <a href="chat.php?patient_id=<?= $patient_id ?>" class="btn-sm" title="Chat with Patient">
                                            <i class="fas fa-comments"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="no-records card">
                <i class="fas fa-info-circle"></i>
                <p>No vitals recorded for this patient yet.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="export-options">
        <a href="patients.php" class="btn">Back to Patients</a>
        <a href="reports.php?patient_id=<?= $patient_id ?>" class="btn">Generate Report</a>
    </div>
</div>

<!-- Modal for vitals details -->
<div id="vitalDetailsModal" class="modal">
    <div class="modal-content">
        <span class="close">×</span>
        <h3>Vital Details</h3>
        <div id="vitalDetailsContent"></div>
    </div>
</div>

<!-- Modal for adding notes -->
<div id="addNoteModal" class="modal">
    <div class="modal-content">
        <span class="close">×</span>
        <h3>Add Medical Note</h3>
        <form id="noteForm">
            <input type="hidden" id="vitalId" name="vital_id">
            <div class="form-group">
                <label for="doctorNote">Your Note:</label>
                <textarea id="doctorNote" name="note" rows="5" required></textarea>
            </div>
            <button type="submit" class="btn">Save Note</button>
        </form>
    </div>
</div>

<script src="../assets/js/doctor.js"></script>

<style>
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

.patient-info {
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    padding: 1.5rem;
    margin-bottom: 2rem;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.patient-info:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12);
}

.patient-info p {
    margin: 0.5rem 0;
    color: var(--dark-gray);
}

.patient-info strong {
    color: var(--primary-dark);
}

.vitals-container {
    margin-bottom: 2rem;
}

.vitals-history {
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    padding: 2rem;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.vitals-history:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12);
}

.vitals-history h3 {
    color: var(--primary-dark);
    font-size: 1.5rem;
    margin-bottom: 1.5rem;
    font-weight: 600;
}

.table-responsive {
    overflow-x: auto;
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

.badge {
    padding: 0.3rem 0.6rem;
    border-radius: 4px;
    font-size: 0.85rem;
}

.normal-badge {
    background-color: #e6ffe6;
    color: #2d862d;
}

.warning-badge {
    background-color: #fff3e6;
    color: #cc6600;
}

.critical-badge {
    background-color: #ffe6e6;
    color: #cc0000;
}

.btn-sm {
    padding: 0.5rem;
    background-color: var(--primary-dark);
    color: white;
    border-radius: 6px;
    text-decoration: none;
    display: inline-block;
    transition: all 0.3s ease;
}

.btn-sm:hover {
    background-color: var(--primary);
    transform: translateY(-2px);
}

.export-options {
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
    margin-left: 1rem;
    display: inline-block;
}

.btn:hover {
    background-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(96, 108, 56, 0.3);
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

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
}

.modal-content {
    background: white;
    margin: 15% auto;
    padding: 2rem;
    border-radius: 12px;
    width: 90%;
    max-width: 500px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
}

.close {
    float: right;
    font-size: 1.5rem;
    cursor: pointer;
    color: var(--dark-gray);
}

.modal-content h3 {
    color: var(--primary-dark);
    margin-bottom: 1.5rem;
}

.form-group label {
    color: var(--dark-gray);
    font-weight: 500;
    margin-bottom: 0.5rem;
    display: block;
}

.form-group textarea {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid rgba(0, 0, 0, 0.1);
    border-radius: 8px;
    resize: vertical;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .patient-container {
        padding: 1.5rem;
    }
    
    .vitals-history,
    .patient-info,
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
    
    .btn {
        margin: 0.5rem 0;
        width: 100%;
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