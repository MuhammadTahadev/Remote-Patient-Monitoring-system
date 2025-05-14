<?php
require_once '../config/config.php';
require_once '../includes/header.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
// Ensure only patients can access
requireRole('Patient');

$patient_id = $_SESSION['patient_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $heart_rate = isset($_POST['heart_rate']) ? (int)$_POST['heart_rate'] : null;
    $systolic_bp = isset($_POST['systolic_bp']) ? (int)$_POST['systolic_bp'] : null;
    $diastolic_bp = isset($_POST['diastolic_bp']) ? (int)$_POST['diastolic_bp'] : null;
    $glucose = isset($_POST['glucose']) ? (float)$_POST['glucose'] : null;
    $oxygen = isset($_POST['oxygen']) ? (float)$_POST['oxygen'] : null;
    $temp = isset($_POST['temperature']) ? (float)$_POST['temperature'] : null;
    $weight = isset($_POST['weight']) ? (float)$_POST['weight'] : null;
    $notes = sanitize($conn, $_POST['notes'] ?? '');

    // Insert into HealthData
    $stmt = $conn->prepare("INSERT INTO HealthData 
                           (Patient_ID, Heart_Rate, Systolic_BP, Diastolic_BP, Glucose_Level, Oxygen_Saturation, Temperature, Weight, Notes)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iiiiiddds", $patient_id, $heart_rate, $systolic_bp, $diastolic_bp, $glucose, $oxygen, $temp, $weight, $notes);
    
    if ($stmt->execute()) {
        setAlert("Vitals recorded successfully!", "success");
        
        // Check for alerts (simplified example)
        checkForAlerts($patient_id, $heart_rate, $systolic_bp, $diastolic_bp, $glucose, $oxygen, $temp, $weight);
        
        header("Location: vitals.php");
        exit();
    } else {
        setAlert("Error recording vitals: " . $conn->error, "error");
    }
}

// Get all vitals for this patient
$vitals_history = [];
$stmt = $conn->prepare("SELECT * FROM HealthData WHERE Patient_ID = ? ORDER BY Timestamp DESC");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$vitals_history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get threshold values
$thresholds = [];
$result = $conn->query("SELECT * FROM AlertThreshold");
while ($row = $result->fetch_assoc()) {
    $thresholds[$row['Metric_Type']] = $row;
}
?>

<div class="patient-container">
    <h2>Record and View Vitals</h2>
    <div class="back-link">
        <a href="dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>
    <?php displayAlert(); ?>
    
    <div class="vitals-container card">
        <div class="vitals-form">
            <h3>Enter New Vitals</h3>
            <form method="post">
                <div class="form-row">
                    <div class="form-group">
                        <label for="heart_rate">Heart Rate (bpm):</label>
                        <input type="number" id="heart_rate" name="heart_rate" min="30" max="200">
                    </div>
                    <div class="form-group">
                        <label for="systolic_bp">Systolic BP (mmHg):</label>
                        <input type="number" id="systolic_bp" name="systolic_bp" min="50" max="250">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="diastolic_bp">Diastolic BP (mmHg):</label>
                        <input type="number" id="diastolic_bp" name="diastolic_bp" min="30" max="150">
                    </div>
                    <div class="form-group">
                        <label for="glucose">Glucose (mg/dL):</label>
                        <input type="number" step="0.1" id="glucose" name="glucose" min="20" max="500">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="oxygen">Oxygen Saturation (%):</label>
                        <input type="number" step="0.1" id="oxygen" name="oxygen" min="70" max="100">
                    </div>
                    <div class="form-group">
                        <label for="temperature">Temperature (°F):</label>
                        <input type="number" step="0.1" id="temperature" name="temperature" min="90" max="110">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="weight">Weight (lbs):</label>
                    <input type="number" step="0.1" id="weight" name="weight" min="50" max="500">
                </div>
                
                <div class="form-group">
                    <label for="notes">Notes:</label>
                    <textarea id="notes" name="notes" rows="3"></textarea>
                </div>
                
                <div class="form-buttons">
                    <button type="submit" class="btn">Record Vitals</button>
                    <button type="button" class="btn btn-generate" onclick="generateVitals()">Generate Vitals</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function generateVitals() {
    // Generate random vitals within realistic ranges
    document.getElementById('heart_rate').value = Math.floor(Math.random() * (100 - 60 + 1)) + 60; // 60-100 bpm
    document.getElementById('systolic_bp').value = Math.floor(Math.random() * (140 - 90 + 1)) + 90; // 90-140 mmHg
    document.getElementById('diastolic_bp').value = Math.floor(Math.random() * (90 - 60 + 1)) + 60; // 60-90 mmHg
    document.getElementById('glucose').value = (Math.random() * (120 - 70) + 70).toFixed(1); // 70-120 mg/dL
    document.getElementById('oxygen').value = (Math.random() * (100 - 95) + 95).toFixed(1); // 95-100%
    document.getElementById('temperature').value = (Math.random() * (99.5 - 97.5) + 97.5).toFixed(1); // 97.5-99.5°F
    document.getElementById('weight').value = (Math.random() * (200 - 120) + 120).toFixed(1); // 120-200 lbs
    document.getElementById('notes').value = 'Generated vitals for demo';
}
</script>

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

.vitals-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    padding: 2rem;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.vitals-container:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12);
}

.vitals-form h3 {
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

.form-group input,
.form-group textarea {
    padding: 0.75rem;
    border: 1px solid rgba(0, 0, 0, 0.1);
    border-radius: 8px;
    font-size: 1rem;
    transition: border-color 0.3s ease;
}

.form-group input:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 5px rgba(96, 108, 56, 0.2);
}

.form-group textarea {
    resize: vertical;
    min-height: 100px;
}

.form-buttons {
    display: flex;
    gap: 1rem;
    margin-top: 1rem;
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
}

.btn:hover {
    background-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(96, 108, 56, 0.3);
}

.btn-generate {
    background-color: var(--secondary);
}

.btn-generate:hover {
    background-color: var(--secondary-dark);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .patient-container {
        padding: 1.5rem;
    }
    
    .vitals-container {
        padding: 1.5rem;
    }
    
    .form-row {
        flex-direction: column;
        gap: 1rem;
    }
    
    .form-buttons {
        flex-direction: column;
        gap: 0.75rem;
    }
}

@media (max-width: 576px) {
    .patient-container {
        padding: 1rem;
    }
    
    .patient-container h2 {
        font-size: 1.8rem;
    }
    
    .vitals-form h3 {
        font-size: 1.3rem;
    }
}
</style>

<?php require_once '../includes/footer.php'; ?>