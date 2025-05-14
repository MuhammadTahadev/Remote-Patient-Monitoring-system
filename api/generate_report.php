<?php
require_once '../vendor/autoload.php';
require_once '../config/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Verify authentication and role (Patient or Doctor)
if (!isLoggedIn() || (!hasRole('Patient') && !hasRole('Doctor'))) {
    http_response_code(401);
    die('Unauthorized');
}

// Determine patient_id based on role
$patient_id = null;
$user_id = $_SESSION['user_id'];
$doctor_id = hasRole('Doctor') ? $_SESSION['doctor_id'] : null;

if (hasRole('Patient')) {
    $patient_id = $_SESSION['patient_id'];
} elseif (hasRole('Doctor')) {
    $patient_id = isset($_POST['patient_id']) ? (int)$_POST['patient_id'] : (isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : null);
    
    if (!$patient_id) {
        setAlert('No patient specified for report generation', 'error');
        header("Location: ../doctor/patients.php");
        exit();
    }

    // Verify that the patient is assigned to this doctor
    $stmt = $conn->prepare("SELECT 1 FROM DoctorPatientMapping WHERE Doctor_ID = ? AND Patient_ID = ?");
    $stmt->bind_param("ii", $doctor_id, $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        setAlert('You are not authorized to generate a report for this patient', 'error');
        header("Location: ../doctor/patients.php");
        exit();
    }
}

// Try to load FPDF with error handling
try {
    if (file_exists('../vendor/autoload.php')) {
        require_once '../vendor/autoload.php';
    } elseif (file_exists('../lib/fpdf/fpdf.php')) {
        require_once '../lib/fpdf/fpdf.php';
    } else {
        @include 'FPDF/fpdf.php';
        if (!class_exists('FPDF')) {
            throw new Exception('FPDF library not found');
        }
    }
} catch (Exception $e) {
    error_log("FPDF Error: " . $e->getMessage());
    setAlert('Report generation failed: PDF library not available', 'error');
    header("Location: ../" . (hasRole('Doctor') ? 'doctor/patients.php' : 'patient/reports.php'));
    exit();
}

// Process report generation request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $report_type = $_POST['report_type'] ?? 'vitals';
    $time_period = $_POST['time_period'] ?? '30days';
    
    // Validate inputs
    if (!in_array($report_type, ['vitals', 'summary', 'progress']) || 
        !in_array($time_period, ['7days', '30days', '90days', 'year', 'all'])) {
        setAlert('Invalid report parameters', 'error');
        header("Location: ../" . (hasRole('Doctor') ? 'doctor/patients.php' : 'patient/reports.php'));
        exit();
    }
    
    // Determine date range and interval
    $date_condition = "";
    $interval_label = '';
    switch ($time_period) {
        case '7days':
            $date_condition = "AND Timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            $period_label = "Last 7 Days";
            $interval_label = "Daily";
            break;
        case '30days':
            $date_condition = "AND Timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            $period_label = "Last 30 Days";
            $interval_label = "Weekly";
            break;
        case '90days':
            $date_condition = "AND Timestamp >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
            $period_label = "Last 90 Days";
            $interval_label = "Weekly";
            break;
        case 'year':
            $date_condition = "AND Timestamp >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
            $period_label = "Last Year";
            $interval_label = "Monthly";
            break;
        case 'all':
            $date_condition = "";
            $period_label = "All Time";
            $interval_label = "Monthly";
            break;
    }
    
    // Get patient info
    $stmt = $conn->prepare("SELECT u.Full_Name, p.Emergency_Contact_Number 
                           FROM Patient p
                           JOIN User u ON p.User_ID = u.User_ID
                           WHERE p.Patient_ID = ?");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $patient = $stmt->get_result()->fetch_assoc();
    
    if (!$patient) {
        setAlert('Patient information not found', 'error');
        header("Location: ../" . (hasRole('Doctor') ? 'doctor/patients.php' : 'patient/reports.php'));
        exit();
    }
    
    // Get vitals data for vitals and summary, progress data will be fetched separately
    $vitals = [];
    if ($report_type !== 'progress') {
        $stmt = $conn->prepare("SELECT * FROM HealthData 
                               WHERE Patient_ID = ? $date_condition
                               ORDER BY Timestamp DESC");
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $vitals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    try {
        $pdf = new FPDF();
        $pdf->AddPage();
        
        // Report header
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, 'Patient Health Report', 0, 1, 'C');
        $pdf->Ln(10);
        
        // Patient info
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 10, 'Patient Name: ' . $patient['Full_Name'], 0, 1);
        $pdf->Cell(0, 10, 'Emergency Contact: ' . $patient['Emergency_Contact_Number'], 0, 1);
        $pdf->Cell(0, 10, 'Report Period: ' . $period_label, 0, 1);
        $pdf->Cell(0, 10, 'Generated On: ' . date('F j, Y'), 0, 1);
        if (hasRole('Doctor')) {
            $pdf->Cell(0, 10, 'Generated By: Dr. ' . $_SESSION['full_name'], 0, 1);
        }
        $pdf->Ln(10);
        
        // Report content based on report_type
        if ($report_type === 'summary') {
            $pdf->SetFont('Arial', 'B', 14);
            $pdf->Cell(0, 10, 'Health Summary', 0, 1);
            $pdf->SetFont('Arial', '', 12);
            
            if (!empty($vitals)) {
                $count = count($vitals);
                $avg_hr = array_sum(array_column($vitals, 'Heart_Rate')) / $count;
                $min_hr = min(array_filter(array_column($vitals, 'Heart_Rate'), 'is_numeric'));
                $max_hr = max(array_filter(array_column($vitals, 'Heart_Rate'), 'is_numeric'));
                $avg_sys = array_sum(array_column($vitals, 'Systolic_BP')) / $count;
                $min_sys = min(array_filter(array_column($vitals, 'Systolic_BP'), 'is_numeric'));
                $max_sys = max(array_filter(array_column($vitals, 'Systolic_BP'), 'is_numeric'));
                $avg_dia = array_sum(array_column($vitals, 'Diastolic_BP')) / $count;
                $min_dia = min(array_filter(array_column($vitals, 'Diastolic_BP'), 'is_numeric'));
                $max_dia = max(array_filter(array_column($vitals, 'Diastolic_BP'), 'is_numeric'));
                $avg_gluc = array_sum(array_column($vitals, 'Glucose_Level')) / $count;
                $min_gluc = min(array_filter(array_column($vitals, 'Glucose_Level'), 'is_numeric'));
                $max_gluc = max(array_filter(array_column($vitals, 'Glucose_Level'), 'is_numeric'));
                $avg_oxy = array_sum(array_column($vitals, 'Oxygen_Saturation')) / $count;
                $min_oxy = min(array_filter(array_column($vitals, 'Oxygen_Saturation'), 'is_numeric'));
                $max_oxy = max(array_filter(array_column($vitals, 'Oxygen_Saturation'), 'is_numeric'));
                $avg_temp = array_sum(array_column($vitals, 'Temperature')) / $count;
                $min_temp = min(array_filter(array_column($vitals, 'Temperature'), 'is_numeric'));
                $max_temp = max(array_filter(array_column($vitals, 'Temperature'), 'is_numeric'));

                $pdf->Cell(0, 10, sprintf('Number of Records: %d', $count), 0, 1);
                $pdf->Ln(5);
                $pdf->Cell(0, 10, sprintf('Heart Rate - Avg: %.1f bpm, Range: %d - %d bpm', $avg_hr, $min_hr, $max_hr), 0, 1);
                $pdf->Cell(0, 10, sprintf('Blood Pressure - Avg: %.1f/%.1f mmHg, Range: %d/%d - %d/%d mmHg', $avg_sys, $avg_dia, $min_sys, $min_dia, $max_sys, $max_dia), 0, 1);
                $pdf->Cell(0, 10, sprintf('Glucose - Avg: %.1f mg/dL, Range: %d - %d mg/dL', $avg_gluc, $min_gluc, $max_gluc), 0, 1);
                $pdf->Cell(0, 10, sprintf('Oxygen Saturation - Avg: %.1f%%, Range: %d - %d%%', $avg_oxy, $min_oxy, $max_oxy), 0, 1);
                $pdf->Cell(0, 10, sprintf('Temperature - Avg: %.1f °F, Range: %.1f - %.1f °F', $avg_temp, $min_temp, $max_temp), 0, 1);
            } else {
                $pdf->Cell(0, 10, 'No vital data available for the selected period', 0, 1);
            }
        } elseif ($report_type === 'progress') {
            $pdf->SetFont('Arial', 'B', 14);
            $pdf->Cell(0, 10, 'Progress Report', 0, 1);
            $pdf->SetFont('Arial', '', 12);
            $pdf->Cell(0, 10, 'Trend Interval: ' . $interval_label, 0, 1);
            $pdf->Ln(5);

            // Fetch progress data
            if ($time_period === '7days') {
                $stmt = $conn->prepare("SELECT DATE(Timestamp) as period,
                    AVG(Heart_Rate) as avg_hr, AVG(Systolic_BP) as avg_sys, AVG(Diastolic_BP) as avg_dia,
                    AVG(Glucose_Level) as avg_gluc, AVG(Oxygen_Saturation) as avg_oxy, AVG(Temperature) as avg_temp,
                    COUNT(*) as record_count
                    FROM HealthData
                    WHERE Patient_ID = ? $date_condition
                    GROUP BY DATE(Timestamp)
                    ORDER BY period ASC");
            } elseif ($time_period === 'year' || $time_period === 'all') {
                $stmt = $conn->prepare("SELECT DATE_FORMAT(Timestamp, '%Y-%m') as period,
                    AVG(Heart_Rate) as avg_hr, AVG(Systolic_BP) as avg_sys, AVG(Diastolic_BP) as avg_dia,
                    AVG(Glucose_Level) as avg_gluc, AVG(Oxygen_Saturation) as avg_oxy, AVG(Temperature) as avg_temp,
                    COUNT(*) as record_count
                    FROM HealthData
                    WHERE Patient_ID = ? $date_condition
                    GROUP BY DATE_FORMAT(Timestamp, '%Y-%m')
                    ORDER BY period ASC");
            } else {
                $stmt = $conn->prepare("SELECT DATE_FORMAT(Timestamp, '%Y-W%U') as period,
                    AVG(Heart_Rate) as avg_hr, AVG(Systolic_BP) as avg_sys, AVG(Diastolic_BP) as avg_dia,
                    AVG(Glucose_Level) as avg_gluc, AVG(Oxygen_Saturation) as avg_oxy, AVG(Temperature) as avg_temp,
                    COUNT(*) as record_count
                    FROM HealthData
                    WHERE Patient_ID = ? $date_condition
                    GROUP BY DATE_FORMAT(Timestamp, '%Y-W%U')
                    ORDER BY period ASC");
            }
            $stmt->bind_param("i", $patient_id);
            $stmt->execute();
            $progress_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            if (!empty($progress_data)) {
                // Table header
                $pdf->SetFont('Arial', 'B', 12);
                $pdf->Cell(30, 10, 'Period', 1);
                $pdf->Cell(25, 10, 'HR (bpm)', 1);
                $pdf->Cell(30, 10, 'BP (mmHg)', 1);
                $pdf->Cell(25, 10, 'Glucose', 1);
                $pdf->Cell(25, 10, 'O2 (%)', 1);
                $pdf->Cell(25, 10, 'Temp (°F)', 1);
                $pdf->Cell(20, 10, 'Records', 1);
                $pdf->Ln();

                // Table data
                $pdf->SetFont('Arial', '', 10);
                foreach ($progress_data as $data) {
                    $pdf->Cell(30, 10, $data['period'], 1);
                    $pdf->Cell(25, 10, number_format($data['avg_hr'], 1), 1);
                    $pdf->Cell(30, 10, number_format($data['avg_sys'], 1) . '/' . number_format($data['avg_dia'], 1), 1);
                    $pdf->Cell(25, 10, number_format($data['avg_gluc'], 1), 1);
                    $pdf->Cell(25, 10, number_format($data['avg_oxy'], 1), 1);
                    $pdf->Cell(25, 10, number_format($data['avg_temp'], 1), 1);
                    $pdf->Cell(20, 10, $data['record_count'], 1);
                    $pdf->Ln();
                }
            } else {
                $pdf->Cell(0, 10, 'No vital data available for the selected period', 0, 1);
            }
        } else { // 'vitals'
            $pdf->SetFont('Arial', 'B', 14);
            $pdf->Cell(0, 10, 'Vitals History', 0, 1);
            $pdf->SetFont('Arial', '', 12);
            
            if (!empty($vitals)) {
                $count = count($vitals);
                $avg_hr = array_sum(array_column($vitals, 'Heart_Rate')) / $count;
                $avg_sys = array_sum(array_column($vitals, 'Systolic_BP')) / $count;
                $avg_dia = array_sum(array_column($vitals, 'Diastolic_BP')) / $count;
                $avg_gluc = array_sum(array_column($vitals, 'Glucose_Level')) / $count;
                $avg_oxy = array_sum(array_column($vitals, 'Oxygen_Saturation')) / $count;
                
                $pdf->Cell(0, 10, sprintf('Average Heart Rate: %.1f bpm', $avg_hr), 0, 1);
                $pdf->Cell(0, 10, sprintf('Average Blood Pressure: %.1f/%.1f mmHg', $avg_sys, $avg_dia), 0, 1);
                $pdf->Cell(0, 10, sprintf('Average Glucose: %.1f mg/dL', $avg_gluc), 0, 1);
                $pdf->Cell(0, 10, sprintf('Average Oxygen Saturation: %.1f%%', $avg_oxy), 0, 1);
                $pdf->Ln(10);
                
                $pdf->SetFont('Arial', 'B', 12);
                $pdf->Cell(40, 10, 'Date', 1);
                $pdf->Cell(25, 10, 'HR', 1);
                $pdf->Cell(25, 10, 'BP', 1);
                $pdf->Cell(25, 10, 'Glucose', 1);
                $pdf->Cell(25, 10, 'O2', 1);
                $pdf->Cell(25, 10, 'Temp', 1);
                $pdf->Cell(25, 10, 'Weight', 1);
                $pdf->Ln();
                
                $pdf->SetFont('Arial', '', 10);
                foreach ($vitals as $vital) {
                    $pdf->Cell(40, 10, date('m/d/y H:i', strtotime($vital['Timestamp'])), 1);
                    $pdf->Cell(25, 10, $vital['Heart_Rate'] ?? '--', 1);
                    $pdf->Cell(25, 10, ($vital['Systolic_BP'] ?? '--') . '/' . ($vital['Diastolic_BP'] ?? '--'), 1);
                    $pdf->Cell(25, 10, $vital['Glucose_Level'] ?? '--', 1);
                    $pdf->Cell(25, 10, $vital['Oxygen_Saturation'] ?? '--', 1);
                    $pdf->Cell(25, 10, $vital['Temperature'] ?? '--', 1);
                    $pdf->Cell(25, 10, $vital['Weight'] ?? '--', 1);
                    $pdf->Ln();
                }
            } else {
                $pdf->Cell(0, 10, 'No vitals data available for the selected period', 0, 1);
            }
        }
        
        // Save the report
        $report_dir = '../reports/';
        if (!file_exists($report_dir)) {
            if (!mkdir($report_dir, 0777, true)) {
                throw new Exception('Failed to create reports directory');
            }
        }
        
        $filename = 'report_' . $patient_id . '_' . time() . '.pdf';
        $filepath = $report_dir . $filename;
        $pdf->Output($filepath, 'F');
        
        // Save report record in database
        $stmt = $conn->prepare("INSERT INTO Reports 
                               (Patient_ID, Doctor_ID, Generated_On, Report_Period, Report_Type, File_Path, Generated_By_User_ID)
                               VALUES (?, ?, NOW(), ?, ?, ?, ?)");
        $stmt->bind_param("iisssi", $patient_id, $doctor_id, $period_label, $report_type, $filepath, $user_id);
        $stmt->execute();
        
        // Return the file for download
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit();
        
    } catch (Exception $e) {
        error_log("PDF Generation Error: " . $e->getMessage());
        setAlert('Failed to generate report: ' . $e->getMessage(), 'error');
        header("Location: ../" . (hasRole('Doctor') ? 'doctor/patients.php' : 'patient/reports.php'));
        exit();
    }
} else {
    http_response_code(400);
    header("Location: ../" . (hasRole('Doctor') ? 'doctor/patients.php' : 'patient/reports.php'));
    exit();
}
?>
