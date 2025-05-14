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

// Get time period from GET parameter or default to '30days'
$time_period = $_GET['time_period'] ?? '30days';
$time_filter = '';
$interval_label = '';
switch ($time_period) {
    case '7days':
        $time_filter = "AND Timestamp >= NOW() - INTERVAL 7 DAY";
        $interval_label = 'Daily';
        break;
    case '30days':
        $time_filter = "AND Timestamp >= NOW() - INTERVAL 30 DAY";
        $interval_label = 'Weekly';
        break;
    case '90days':
        $time_filter = "AND Timestamp >= NOW() - INTERVAL 90 DAY";
        $interval_label = 'Weekly';
        break;
    case 'year':
        $time_filter = "AND Timestamp >= NOW() - INTERVAL 1 YEAR";
        $interval_label = 'Monthly';
        break;
    case 'all':
        $time_filter = '';
        $interval_label = 'Monthly';
        break;
    default:
        $time_filter = "AND Timestamp >= NOW() - INTERVAL 30 DAY";
        $interval_label = 'Weekly';
}

// Fetch progress data (grouped by intervals)
$progress_data = [];
if ($time_period === '7days') {
    $stmt = $conn->prepare("SELECT DATE(Timestamp) as period,
        AVG(Heart_Rate) as avg_hr, AVG(Systolic_BP) as avg_sys, AVG(Diastolic_BP) as avg_dia,
        AVG(Glucose_Level) as avg_gluc, AVG(Oxygen_Saturation) as avg_oxy, AVG(Temperature) as avg_temp,
        COUNT(*) as record_count
        FROM HealthData
        WHERE Patient_ID = ? $time_filter
        GROUP BY DATE(Timestamp)
        ORDER BY period ASC");
} elseif ($time_period === 'year' || $time_period === 'all') {
    $stmt = $conn->prepare("SELECT DATE_FORMAT(Timestamp, '%Y-%m') as period,
        AVG(Heart_Rate) as avg_hr, AVG(Systolic_BP) as avg_sys, AVG(Diastolic_BP) as avg_dia,
        AVG(Glucose_Level) as avg_gluc, AVG(Oxygen_Saturation) as avg_oxy, AVG(Temperature) as avg_temp,
        COUNT(*) as record_count
        FROM HealthData
        WHERE Patient_ID = ? $time_filter
        GROUP BY DATE_FORMAT(Timestamp, '%Y-%m')
        ORDER BY period ASC");
} else {
    $stmt = $conn->prepare("SELECT DATE_FORMAT(Timestamp, '%Y-W%U') as period,
        AVG(Heart_Rate) as avg_hr, AVG(Systolic_BP) as avg_sys, AVG(Diastolic_BP) as avg_dia,
        AVG(Glucose_Level) as avg_gluc, AVG(Oxygen_Saturation) as avg_oxy, AVG(Temperature) as avg_temp,
        COUNT(*) as record_count
        FROM HealthData
        WHERE Patient_ID = ? $time_filter
        GROUP BY DATE_FORMAT(Timestamp, '%Y-W%U')
        ORDER BY period ASC");
}
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$progress_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get latest report notes (if any) for context
// Remove this entire section since there's no notes column
/*
$stmt = $conn->prepare("SELECT Notes FROM Reports 
                       WHERE Patient_ID = ? AND Report_Type = 'progress' 
                       ORDER BY Generated_On DESC LIMIT 1");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
*/

displayAlert();
?>

<div class="patient-container">
    <div class="back-link">
        <a href="dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>
    <h2>Progress Report: <?= htmlspecialchars($patient['Full_Name']) ?></h2>
    
    <div class="patient-info card">
        <p><strong>Email Address:</strong> <?= htmlspecialchars($patient['EMAIL']) ?></p>
        <p><strong>Emergency Contact:</strong> <?= htmlspecialchars($patient['Emergency_Contact_Number']) ?></p>
    </div>
    
    <div class="progress-controls card">
        <form method="get" action="progress_report.php">
            <input type="hidden" name="patient_id" value="<?= $patient_id ?>">
            <div class="form-group">
                <label for="time_period">Time Period:</label>
                <select id="time_period" name="time_period" onchange="this.form.submit()">
                    <option value="7days" <?= $time_period === '7days' ? 'selected' : '' ?>>Last 7 Days</option>
                    <option value="30days" <?= $time_period === '30days' ? 'selected' : '' ?>>Last 30 Days</option>
                    <option value="90days" <?= $time_period === '90days' ? 'selected' : '' ?>>Last 90 Days</option>
                    <option value="year" <?= $time_period === 'year' ? 'selected' : '' ?>>Last Year</option>
                    <option value="all" <?= $time_period === 'all' ? 'selected' : '' ?>>All Time</option>
                </select>
            </div>
        </form>
    </div>

    <div class="progress-container">
        <?php if (!empty($progress_data)): ?>
            <div class="progress-report card">
                <h3>Progress Report (<?= $time_period === 'all' ? 'All Time' : "Last " . str_replace('days', ' Days', $time_period) ?>)</h3>
                <p><strong>Trend Interval:</strong> <?= $interval_label ?></p>
                <div class="table-responsive">
                    <table class="progress-table">
                        <thead>
                            <tr>
                                <th>Period</th>
                                <th>Heart Rate (bpm)</th>
                                <th>Blood Pressure (mmHg)</th>
                                <th>Glucose (mg/dL)</th>
                                <th>Oxygen (%)</th>
                                <th>Temperature (°F)</th>
                                <th>Records</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($progress_data as $data): ?>
                                <tr>
                                    <td><?= htmlspecialchars($data['period']) ?></td>
                                    <td><?= number_format($data['avg_hr'], 1) ?></td>
                                    <td><?= number_format($data['avg_sys'], 1) ?>/<?= number_format($data['avg_dia'], 1) ?></td>
                                    <td><?= number_format($data['avg_gluc'], 1) ?></td>
                                    <td><?= number_format($data['avg_oxy'], 1) ?></td>
                                    <td><?= number_format($data['avg_temp'], 1) ?></td>
                                    <td><?= $data['record_count'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php /* Remove the notes section
                if ($latest_notes): ?>
                    <div class="notes-section">
                        <h4>Latest Doctor Notes</h4>
                        <p><?= htmlspecialchars($latest_notes) ?></p>
                    </div>
                <?php endif; */ ?>
            </div>
        <?php else: ?>
            <div class="no-records card">
                <i class="fas fa-info-circle"></i>
                <p>No vital records found for this patient in the selected time period.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="export-options">
        <a href="patients.php" class="btn">Back to Patients</a>
        <a href="vitals.php?patient_id=<?= $patient_id ?>" class="btn">View Vitals History</a>
        <a href="reports.php?patient_id=<?= $patient_id ?>" class="btn">Generate Report</a>
    </div>
</div>

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

.patient-info,
.progress-controls,
.progress-report,
.no-records {
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    padding: 1.5rem;
    margin-bottom: 2rem;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.patient-info:hover,
.progress-controls:hover,
.progress-report:hover,
.no-records:hover {
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

.progress-controls .form-group {
    display: flex;
    flex-direction: column;
}

.progress-controls label {
    color: var(--dark-gray);
    font-weight: 500;
    margin-bottom: 0.5rem;
}

.progress-controls select {
    padding: 0.75rem;
    border: 1px solid rgba(0, 0, 0, 0.1);
    border-radius: 8px;
    font-size: 1rem;
    transition: border-color 0.3s ease;
}

.progress-controls select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 5px rgba(96, 108, 56, 0.2);
}

.progress-report h3 {
    color: var(--primary-dark);
    font-size: 1.5rem;
    margin-bottom: 1rem;
    font-weight: 600;
}

.progress-report p {
    color: var(--dark-gray);
    margin-bottom: 1.5rem;
}

.table-responsive {
    overflow-x: auto;
}

.progress-table {
    width: 100%;
    border-collapse: collapse;
    background: #f9fafb;
    border-radius: 8px;
    overflow: hidden;
}

.progress-table th,
.progress-table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.progress-table th {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    color: white;
    font-weight: 600;
}

.progress-table td {
    background: white;
    color: var(--dark-gray);
}

.notes-section {
    margin-top: 2rem;
}

.notes-section h4 {
    color: var(--primary-dark);
    font-size: 1.2rem;
    margin-bottom: 0.75rem;
    font-weight: 500;
}

.notes-section p {
    background: #f9fafb;
    padding: 1rem;
    border-radius: 8px;
    color: var(--dark-gray);
    line-height: 1.6;
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

/* Responsive Adjustments */
@media (max-width: 768px) {
    .patient-container {
        padding: 1.5rem;
    }
    
    .patient-info,
    .progress-controls,
    .progress-report,
    .no-records {
        padding: 1.5rem;
    }
    
    .progress-table th,
    .progress-table td {
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
    
    .progress-table {
        font-size: 0.9rem;
    }
}
</style>

<?php require_once '../includes/footer.php'; ?>
