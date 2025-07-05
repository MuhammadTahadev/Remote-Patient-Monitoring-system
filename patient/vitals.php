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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-i18n="title">Record and View Vitals</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* Patient Container Styles */
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

        /* Language Dropdown Styling */
        .language-selector {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }

        .language-selector select {
            padding: 8px 12px;
            border-radius: 4px;
            border: 1px solid var(--primary);
            background-color: var(--white);
            color: var(--primary-dark);
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .language-selector select:hover {
            background-color: var(--light);
        }

        .language-selector select:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.2);
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

            .language-selector {
                top: 15px;
                right: 15px;
            }

            .language-selector select {
                font-size: 12px;
                padding: 6px 10px;
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
</head>
<body>
<!-- Language Selector -->
<!-- <div class="language-selector">
    <select id="language_select" onchange="changeLanguage()">
        <option value="en">English</option>
        <option value="ur">اردو</option>
    </select>
</div> -->

<div class="patient-container">
    <h2 data-i18n="vitals_title">Record and View Vitals</h2>
    <div class="back-link">
        <a href="dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> <span data-i18n="back_to_dashboard">Back to Dashboard</span></a>
    </div>
    <?php displayAlert(); ?>
    
    <div class="vitals-container card">
        <div class="vitals-form">
            <h3 data-i18n="enter_vitals_title">Enter New Vitals</h3>
            <form method="post">
                <div class="form-row">
                    <div class="form-group">
                        <label for="heart_rate" data-i18n="heart_rate_label">Heart Rate (bpm):</label>
                        <input type="number" id="heart_rate" name="heart_rate" min="30" max="200">
                    </div>
                    <div class="form-group">
                        <label for="systolic_bp" data-i18n="systolic_bp_label">Systolic BP (mmHg):</label>
                        <input type="number" id="systolic_bp" name="systolic_bp" min="50" max="250">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="diastolic_bp" data-i18n="diastolic_bp_label">Diastolic BP (mmHg):</label>
                        <input type="number" id="diastolic_bp" name="diastolic_bp" min="30" max="150">
                    </div>
                    <div class="form-group">
                        <label for="glucose" data-i18n="glucose_label">Glucose (mg/dL):</label>
                        <input type="number" step="0.1" id="glucose" name="glucose" min="20" max="500">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="oxygen" data-i18n="oxygen_label">Oxygen Saturation (%):</label>
                        <input type="number" step="0.1" id="oxygen" name="oxygen" min="70" max="100">
                    </div>
                    <div class="form-group">
                        <label for="temperature" data-i18n="temperature_label">Temperature (°F):</label>
                        <input type="number" step="0.1" id="temperature" name="temperature" min="90" max="110">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="weight" data-i18n="weight_label">Weight (lbs):</label>
                    <input type="number" step="0.1" id="weight" name="weight" min="50" max="500">
                </div>
                
                <div class="form-group">
                    <label for="notes" data-i18n="notes_label">Notes:</label>
                    <textarea id="notes" name="notes" rows="3"></textarea>
                </div>
                
                <div class="form-buttons">
                    <button type="submit" class="btn" data-i18n="record_vitals_button">Record Vitals</button>
                    <button type="button" class="btn btn-generate" onclick="generateVitals()" data-i18n="generate_vitals_button">Generate Vitals</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const translations = {
    en: {
        title: "Record and View Vitals",
        vitals_title: "Record and View Vitals",
        back_to_dashboard: "Back to Dashboard",
        enter_vitals_title: "Enter New Vitals",
        heart_rate_label: "Heart Rate (bpm):",
        systolic_bp_label: "Systolic BP (mmHg):",
        diastolic_bp_label: "Diastolic BP (mmHg):",
        glucose_label: "Glucose (mg/dL):",
        oxygen_label: "Oxygen Saturation (%):",
        temperature_label: "Temperature (°F):",
        weight_label: "Weight (lbs):",
        notes_label: "Notes:",
        record_vitals_button: "Record Vitals",
        generate_vitals_button: "Generate Vitals",
        generated_notes: "Generated vitals for demo"
    },
    ur: {
        title: "وائٹلز ریکارڈ اور دیکھیں",
        vitals_title: "وائٹلز ریکارڈ اور دیکھیں",
        back_to_dashboard: "ڈیش بورڈ پر واپس جائیں",
        enter_vitals_title: "نئے وائٹلز درج کریں",
        heart_rate_label: "دل کی دھڑکن (بیٹس فی منٹ):",
        systolic_bp_label: "سسٹولک بلڈ پریشر (ملی میٹر ایچ جی):",
        diastolic_bp_label: "ڈائیسٹولک بلڈ پریشر (ملی میٹر ایچ جی):",
        glucose_label: "گلوکوز (ملی گرام/ڈی ایل):",
        oxygen_label: "آکسیجن سیچریشن (%):",
        temperature_label: "درجہ حرارت (°ف):",
        weight_label: "وزن (پاؤنڈ):",
        notes_label: "نوٹس:",
        record_vitals_button: "وائٹلز ریکارڈ کریں",
        generate_vitals_button: "وائٹلز تیار کریں",
        generated_notes: "ڈیمو کے لیے تیار کردہ وائٹلز"
    }
};

// Function to change language
function changeLanguage() {
    const lang = document.getElementById('language_select').value;
    document.querySelectorAll('[data-i18n]').forEach(element => {
        const key = element.getAttribute('data-i18n');
        if (translations[lang][key]) {
            element.textContent = translations[lang][key];
        }
    });
}

// Modified generateVitals to use translated notes
function generateVitals() {
    const lang = document.getElementById('language_select').value;
    // Generate random vitals within realistic ranges
    document.getElementById('heart_rate').value = Math.floor(Math.random() * (100 - 60 + 1)) + 60; // 60-100 bpm
    document.getElementById('systolic_bp').value = Math.floor(Math.random() * (140 - 90 + 1)) + 90; // 90-140 mmHg
    document.getElementById('diastolic_bp').value = Math.floor(Math.random() * (90 - 60 + 1)) + 60; // 60-90 mmHg
    document.getElementById('glucose').value = (Math.random() * (120 - 70) + 70).toFixed(1); // 70-120 mg/dL
    document.getElementById('oxygen').value = (Math.random() * (100 - 95) + 95).toFixed(1); // 95-100%
    document.getElementById('temperature').value = (Math.random() * (99.5 - 97.5) + 97.5).toFixed(1); // 97.5-99.5°F
    document.getElementById('weight').value = (Math.random() * (200 - 120) + 120).toFixed(1); // 120-200 lbs
    document.getElementById('notes').value = translations[lang].generated_notes;
}

// Initialize language on page load
document.addEventListener('DOMContentLoaded', () => {
    changeLanguage();
});
</script>

<?php require_once '../includes/footer.php'; ?>
</body>
</html>