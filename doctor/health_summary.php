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
switch ($time_period) {
    case '7days':
        $time_filter = "AND Timestamp >= NOW() - INTERVAL 7 DAY";
        break;
    case '30days':
        $time_filter = "AND Timestamp >= NOW() - INTERVAL 30 DAY";
        break;
    case '90days':
        $time_filter = "AND Timestamp >= NOW() - INTERVAL 90 DAY";
        break;
    case 'year':
        $time_filter = "AND Timestamp >= NOW() - INTERVAL 1 YEAR";
        break;
    case 'all':
        $time_filter = '';
        break;
    default:
        $time_filter = "AND Timestamp >= NOW() - INTERVAL 30 DAY"; // Default to 30 days
}

// Get aggregated vital statistics
$stmt = $conn->prepare("SELECT 
    AVG(Heart_Rate) as avg_heart_rate, 
    MIN(Heart_Rate) as min_heart_rate, 
    MAX(Heart_Rate) as max_heart_rate,
    AVG(Systolic_BP) as avg_systolic_bp,
    MIN(Systolic_BP) as min_systolic_bp,
    MAX(Systolic_BP) as max_systolic_bp,
    AVG(Diastolic_BP) as avg_diastolic_bp,
    MIN(Diastolic_BP) as min_diastolic_bp,
    MAX(Diastolic_BP) as max_diastolic_bp,
    AVG(Glucose_Level) as avg_glucose,
    MIN(Glucose_Level) as min_glucose,
    MAX(Glucose_Level) as max_glucose,
    AVG(Oxygen_Saturation) as avg_oxygen,
    MIN(Oxygen_Saturation) as min_oxygen,
    MAX(Oxygen_Saturation) as max_oxygen,
    AVG(Temperature) as avg_temperature,
    MIN(Temperature) as min_temperature,
    MAX(Temperature) as max_temperature,
    COUNT(*) as record_count
    FROM HealthData 
    WHERE Patient_ID = ? $time_filter");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();

// Get threshold values (for reference, though not used directly here)
$thresholds = [];
$result = $conn->query("SELECT * FROM AlertThreshold");
while ($row = $result->fetch_assoc()) {
    $thresholds[$row['Metric_Type']] = $row;
}

displayAlert();
?>

<div class="patient-container">
    <div class="back-link">
        <a href="dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>
    <h2>Health Summary: <?= htmlspecialchars($patient['Full_Name']) ?></h2>
    
    <div class="patient-info card">
        <p><strong>Email Address:</strong> <?= htmlspecialchars($patient['EMAIL']) ?></p>
        <p><strong>Emergency Contact:</strong> <?= htmlspecialchars($patient['Emergency_Contact_Number']) ?></p>
    </div>
    
    <div class="summary-controls card">
        <form method="get" action="health_summary.php">
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

    <div class="summary-container">
        <?php if ($summary['record_count'] > 0): ?>
            <div class="health-summary card">
                <h3>Health Summary (<?= $time_period === 'all' ? 'All Time' : "Last " . str_replace('days', ' Days', $time_period) ?>)</h3>
                <div class="summary-stats">
                    <div class="stat-group">
                        <h4>Heart Rate</h4>
                        <p>Average: <?= number_format($summary['avg_heart_rate'], 1) ?> bpm</p>
                        <p>Range: <?= $summary['min_heart_rate'] ?> - <?= $summary['max_heart_rate'] ?> bpm</p>
                    </div>
                    <div class="stat-group">
                        <h4>Blood Pressure</h4>
                        <p>Average: <?= number_format($summary['avg_systolic_bp'], 1) ?>/<?= number_format($summary['avg_diastolic_bp'], 1) ?> mmHg</p>
                        <p>Range: <?= $summary['min_systolic_bp'] ?>/<?= $summary['min_diastolic_bp'] ?> - <?= $summary['max_systolic_bp'] ?>/<?= $summary['max_diastolic_bp'] ?> mmHg</p>
                    </div>
                    <div class="stat-group">
                        <h4>Glucose Level</h4>
                        <p>Average: <?= number_format($summary['avg_glucose'], 1) ?> mg/dL</p>
                        <p>Range: <?= $summary['min_glucose'] ?> - <?= $summary['max_glucose'] ?> mg/dL</p>
                    </div>
                    <div class="stat-group">
                        <h4>Oxygen Saturation</h4>
                        <p>Average: <?= number_format($summary['avg_oxygen'], 1) ?>%</p>
                        <p>Range: <?= $summary['min_oxygen'] ?> - <?= $summary['max_oxygen'] ?>%</p>
                    </div>
                    <div class="stat-group">
                        <h4>Temperature</h4>
                        <p>Average: <?= number_format($summary['avg_temperature'], 1) ?> °F</p>
                        <p>Range: <?= $summary['min_temperature'] ?> - <?= $summary['max_temperature'] ?> °F</p>
                    </div>
                </div>
                <p class="record-count">Based on <?= $summary['record_count'] ?> recorded entries.</p>
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
.summary-controls,
.health-summary,
.no-records {
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    padding: 1.5rem;
    margin-bottom: 2rem;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.patient-info:hover,
.summary-controls:hover,
.health-summary:hover,
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

.summary-controls .form-group {
    display: flex;
    flex-direction: column;
}

.summary-controls label {
    color: var(--dark-gray);
    font-weight: 500;
    margin-bottom: 0.5rem;
}

.summary-controls select {
    padding: 0.75rem;
    border: 1px solid rgba(0, 0, 0, 0.1);
    border-radius: 8px;
    font-size: 1rem;
    transition: border-color 0.3s ease;
}

.summary-controls select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 5px rgba(96, 108, 56, 0.2);
}

.health-summary h3 {
    color: var(--primary-dark);
    font-size: 1.5rem;
    margin-bottom: 1.5rem;
    font-weight: 600;
}

.summary-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
}

.stat-group {
    background: #f9fafb;
    padding: 1rem;
    border-radius: 8px;
}

.stat-group h4 {
    color: var(--primary-dark);
    font-size: 1.2rem;
    margin-bottom: 0.5rem;
    font-weight: 500;
}

.stat-group p {
    margin: 0.25rem 0;
    color: var(--dark-gray);
    font-size: 1rem;
}

.record-count {
    margin-top: 1.5rem;
    color: var(--dark-gray);
    font-style: italic;
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
    .summary-controls,
    .health-summary,
    .no-records {
        padding: 1.5rem;
    }
    
    .summary-stats {
        grid-template-columns: 1fr;
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
    
    .stat-group {
        font-size: 0.9rem;
    }
}
</style>

<?php require_once '../includes/footer.php'; ?>