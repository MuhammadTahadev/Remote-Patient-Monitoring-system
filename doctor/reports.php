<?php
require_once '../config/config.php';
require_once '../includes/header.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Ensure only doctors can access
requireRole('Doctor');

$doctor_id = $_SESSION['doctor_id'];

// Check if patient_id is provided (for direct access)
$patient_id = $_GET['patient_id'] ?? null;
$has_vitals = false;
if ($patient_id) {
    // Verify the doctor is assigned to this patient
    $stmt = $conn->prepare("SELECT 1 FROM DoctorPatientMapping 
                           WHERE Doctor_ID = ? AND Patient_ID = ?");
    $stmt->bind_param("ii", $doctor_id, $patient_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        $patient_id = null; // Reset if not authorized
    } else {
        // Check if the selected patient has any vitals
        $vitals_check = $conn->prepare("SELECT COUNT(*) as vital_count FROM HealthData WHERE Patient_ID = ?");
        $vitals_check->bind_param("i", $patient_id);
        $vitals_check->execute();
        $vital_result = $vitals_check->get_result()->fetch_assoc();
        $has_vitals = ($vital_result['vital_count'] > 0);
    }
}

// Get assigned patients for dropdown
$stmt = $conn->prepare("SELECT p.Patient_ID, u.Full_Name 
                       FROM DoctorPatientMapping dm
                       JOIN Patient p ON dm.Patient_ID = p.Patient_ID
                       JOIN User u ON p.User_ID = u.User_ID
                       WHERE dm.Doctor_ID = ?
                       ORDER BY u.Full_Name");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$patients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get previously generated reports
$reports = [];
if ($patient_id) {
    $stmt = $conn->prepare("SELECT * FROM Reports 
                           WHERE Patient_ID = ? AND (Doctor_ID = ? OR Doctor_ID IS NULL)
                           ORDER BY Generated_On DESC");
    $stmt->bind_param("ii", $patient_id, $doctor_id);
    $stmt->execute();
    $reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>

<div class="patient-container">
    <h2>Generate Patient Reports</h2>
    <div class="back-link">
        <a href="dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>
    <div class="report-controls card">
        <form method="get" action="reports.php">
            <div class="form-group">
                <label for="patient">Select Patient:</label>
                <select id="patient" name="patient_id" required>
                    <option value="">-- Select Patient --</option>
                    <?php foreach ($patients as $patient): ?>
                        <option value="<?= $patient['Patient_ID'] ?>" 
                            <?= ($patient_id == $patient['Patient_ID']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($patient['Full_Name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn">Load Patient</button>
        </form>
    </div>
    
    <?php if ($patient_id): ?>
        <div class="report-generation card">
            <h3>Generate New Report</h3>
            <form method="post" action="../api/generate_report.php">
                <input type="hidden" name="patient_id" value="<?= $patient_id ?>">
                <input type="hidden" name="doctor_id" value="<?= $doctor_id ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="report_type">Report Type:</label>
                        <select id="report_type" name="report_type" required>
                            <option value="vitals">Vitals History</option>
                            <option value="summary">Health Summary</option>
                            <option value="progress">Progress Report</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="time_period">Time Period:</label>
                        <select id="time_period" name="time_period" required>
                            <option value="7days">Last 7 Days</option>
                            <option value="30days" selected>Last 30 Days</option>
                            <option value="90days">Last 90 Days</option>
                            <option value="year">Last Year</option>
                            <option value="all">All Time</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="notes">Additional Notes:</label>
                    <textarea id="notes" name="notes" rows="3"></textarea>
                </div>
                
                <button type="submit" class="btn">Generate Report</button>
            </form>
        </div>
        
        <div class="previous-reports card">
            <h3>Previous Reports</h3>
            <?php if (!empty($reports)): ?>
                <div class="table-responsive">
                    <table class="vitals-history-table">
                        <thead>
                            <tr>
                                <th>Generated On</th>
                                <th>Type</th>
                                <th>Period</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reports as $report): ?>
                                <tr>
                                    <td><?= date('M j, Y g:i A', strtotime($report['Generated_On'])) ?></td>
                                    <td><?= htmlspecialchars($report['Report_Type']) ?></td>
                                    <td><?= htmlspecialchars($report['Report_Period']) ?></td>
                                    <td>
                                        <?php if ($report['File_Path'] && file_exists($report['File_Path'])): ?>
                                            <a href="<?= htmlspecialchars($report['File_Path']) ?>" class="btn-sm" download>Download</a>
                                        <?php else: ?>
                                            <span class="text-muted">Not available</span>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-records">
                    <i class="fas fa-info-circle"></i>
                    <p>No reports generated yet for this patient.</p>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal for no vitals -->
<?php if ($patient_id && !$has_vitals): ?>
<div id="noVitalsModal" class="modal">
    <div class="modal-content">
        <h3>No Vitals Recorded</h3>
        <p>This patient has not yet recorded any vitals so the report cannot be generated.</p>
        <div class="modal-buttons">
            <a href="chat.php?patient_id=<?= $patient_id ?>" class="btn"><i class="fas fa-comments"></i> Chat with Patient</a>
            <a href="dashboard.php" class="btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('noVitalsModal');
    
    // Show the modal if it exists
    if (modal) {
        modal.style.display = 'block';
    }
});
</script>
<?php endif; ?>

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

.report-controls {
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    padding: 1.5rem;
    margin-bottom: 2rem;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.report-controls:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12);
}

.report-generation,
.previous-reports {
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    padding: 2rem;
    margin-bottom: 2rem;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.report-generation:hover,
.previous-reports:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12);
}

.report-generation h3,
.previous-reports h3 {
    color: var(--primary-dark);
    font-size: 1.5rem;
    margin-bottom: 1.5rem;
    font-weight: 600;
}

.form-row {
    display: flex;
    gap: 1.5rem;
    margin-bottom: 1rem;
}

.form-group {
    flex: 1;
    display: flex;
    flex-direction: column;
}

.form-group label {
    color: var(--dark-gray);
    font-weight: 500;
    margin-bottom: 0.5rem;
}

.form-group select,
.form-group textarea {
    padding: 0.75rem;
    border: 1px solid rgba(0, 0, 0, 0.1);
    border-radius: 8px;
    font-size: 1rem;
    transition: border-color 0.3s ease;
}

.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 5px rgba(96, 108, 56, 0.2);
}

.form-group textarea {
    resize: vertical;
    min-height: 100px;
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

.btn-sm {
    padding: 0.5rem 1rem;
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

.btn {
    padding: 0.75rem 1.5rem;
    background-color: var(--primary-dark);
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-top: 1rem;
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
    text-align: center;
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

.text-muted {
    color: #6c757d;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    overflow: auto;
}

.modal-content {
    background-color: #fff;
    margin: 15% auto;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    width: 90%;
    max-width: 500px;
    text-align: center;
}

.modal-content h3 {
    color: var(--primary-dark);
    font-size: 1.5rem;
    margin-bottom: 1rem;
}

.modal-content p {
    color: var(--dark-gray);
    font-size: 1rem;
    line-height: 1.5;
    margin-bottom: 1.5rem;
}

.modal-buttons {
    display: flex;
    justify-content: center;
    gap: 1rem;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .patient-container {
        padding: 1.5rem;
    }
    
    .report-controls,
    .report-generation,
    .previous-reports {
        padding: 1.5rem;
    }
    
    .form-row {
        flex-direction: column;
        gap: 1rem;
    }
    
    .vitals-history-table th,
    .vitals-history-table td {
        padding: 0.75rem;
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
    
    .modal-buttons {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
    }
}
</style>

<?php require_once '../includes/footer.php'; ?>