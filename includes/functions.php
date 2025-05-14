<?php
// includes/functions.php

// Include database connection
require_once 'db.php';

// Display alert message
function displayAlert() {
    if (isset($_SESSION['alert'])) {
        $alert = $_SESSION['alert'];
        echo "<div class='alert alert-{$alert['type']}'>{$alert['message']}</div>";
        unset($_SESSION['alert']);
    }
}

// functions.php

// Check if email exists
function emailExists($email) {
    global $conn;
    $stmt = $conn->prepare("SELECT User_ID FROM user WHERE EMAIL = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}
// Set alert message
function setAlert($message, $type = 'success') {
    $_SESSION['alert'] = [
        'message' => $message,
        'type' => $type
    ];
}

// Get user by ID
function getUserById($user_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM User WHERE User_ID = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Get patient by user ID
function getPatientByUserId($user_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT p.* FROM Patient p JOIN User u ON p.User_ID = u.User_ID WHERE u.User_ID = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Get doctor by user ID
function getDoctorByUserId($user_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT d.* FROM Doctor d JOIN User u ON d.User_ID = u.User_ID WHERE u.User_ID = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Check if phone number exists
function phoneNumberExists($phone) {
    global $conn;
    $stmt = $conn->prepare("SELECT User_ID FROM User WHERE Phone_Number = ?");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

// Get role name by ID
function getRoleName($role_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT Role_Name FROM Role WHERE Role_ID = ?");
    $stmt->bind_param("i", $role_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc()['Role_Name'];
}


// Get vital status badge
function getVitalBadge($vital_type, $value, $thresholds = null) {
    global $conn;
    
    // Fetch thresholds if not provided
    if ($thresholds === null) {
        $thresholds = [];
        $result = $conn->query("SELECT * FROM AlertThreshold");
        while ($row = $result->fetch_assoc()) {
            $thresholds[$row['Metric_Type']] = $row;
        }
    }
    
    // Handle null values
    if ($value === null) {
        return 'Normal';
    }
    
    switch ($vital_type) {
        case 'heart_rate':
            $hr_thresh = $thresholds['heart_rate'] ?? ['Min_Value' => 60, 'Max_Value' => 100, 'Severity' => 'Critical'];
            if ($value < $hr_thresh['Min_Value'] || $value > $hr_thresh['Max_Value']) {
                return $hr_thresh['Severity'] === 'Critical' ? 'Critical' : 'Warning';
            }
            break;
            
        case 'blood_pressure':
            if (!is_array($value) || count($value) < 2) {
                return 'Normal';
            }
            
            $systolic = $value[0];
            $diastolic = $value[1];
            
            if ($systolic === null || $diastolic === null) {
                return 'Normal';
            }
            
            $sys_thresh = $thresholds['systolic_bp'] ?? ['Min_Value' => 90, 'Max_Value' => 140, 'Severity' => 'Critical'];
            $dia_thresh = $thresholds['diastolic_bp'] ?? ['Min_Value' => 60, 'Max_Value' => 90, 'Severity' => 'Critical'];
            
            $sys_status = 'Normal';
            if ($systolic < $sys_thresh['Min_Value'] || $systolic > $sys_thresh['Max_Value']) {
                $sys_status = $sys_thresh['Severity'] === 'Critical' ? 'Critical' : 'Warning';
            }
            
            $dia_status = 'Normal';
            if ($diastolic < $dia_thresh['Min_Value'] || $diastolic > $dia_thresh['Max_Value']) {
                $dia_status = $dia_thresh['Severity'] === 'Critical' ? 'Critical' : 'Warning';
            }
            
            // Return the "worst" status
            if ($sys_status === 'Critical' || $dia_status === 'Critical') {
                return 'Critical';
            }
            if ($sys_status === 'Warning' || $dia_status === 'Warning') {
                return 'Warning';
            }
            break;
            
        case 'glucose':
            $glucose_thresh = $thresholds['glucose'] ?? ['Min_Value' => 70, 'Max_Value' => 140, 'Severity' => 'Warning'];
            if ($value < $glucose_thresh['Min_Value'] || $value > $glucose_thresh['Max_Value']) {
                return $glucose_thresh['Severity'] === 'Critical' ? 'Critical' : 'Warning';
            }
            break;
            
        case 'oxygen':
            $oxygen_thresh = $thresholds['oxygen'] ?? ['Min_Value' => 95, 'Max_Value' => 100, 'Severity' => 'Critical'];
            if ($value < $oxygen_thresh['Min_Value']) {
                return $oxygen_thresh['Severity'] === 'Critical' ? 'Critical' : 'Warning';
            }
            break;
            
        case 'temperature':
            // Convert temperature from °C to °F if stored in °C
            $temp_in_f = ($value * 9/5) + 32;
            $temp_thresh = $thresholds['temperature'] ?? ['Min_Value' => 97, 'Max_Value' => 99, 'Severity' => 'Warning'];
            if ($temp_in_f < $temp_thresh['Min_Value'] || $temp_in_f > $temp_thresh['Max_Value']) {
                return $temp_thresh['Severity'] === 'Critical' ? 'Critical' : 'Warning';
            }
            break;
            
        case 'weight':
            // Convert weight from kg to lbs if stored in kg
            $weight_in_lbs = $value * 2.20462;
            $weight_thresh = $thresholds['weight'] ?? ['Min_Value' => 100, 'Max_Value' => 200, 'Severity' => 'Warning'];
            if ($weight_in_lbs < $weight_thresh['Min_Value'] || $weight_in_lbs > $weight_thresh['Max_Value']) {
                return $weight_thresh['Severity'] === 'Critical' ? 'Critical' : 'Warning';
            }
            break;
    }
    
    return 'Normal';
}

// Check for abnormal vitals and create alerts
function checkForAlerts($patient_id, $heart_rate, $systolic, $diastolic, $glucose, $oxygen, $temp, $weight) {
    global $conn;

    // Fetch the patient's assigned doctor
    $stmt = $conn->prepare("SELECT Doctor_ID FROM DoctorPatientMapping WHERE Patient_ID = ?");
    if ($stmt === false) {
        die("Prepare failed (DoctorPatientMapping query): " . $conn->error);
    }
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $doctor = $result->fetch_assoc();
    $doctor_id = $doctor['Doctor_ID'] ?? null;
    $stmt->close();

    if (!$doctor_id) {
        return; // No doctor assigned, skip alert generation
    }

    // Fetch thresholds from AlertThreshold table
    $thresholds = [];
    $result = $conn->query("SELECT * FROM AlertThreshold");
    if ($result === false) {
        die("Query failed (AlertThreshold query): " . $conn->error);
    }
    while ($row = $result->fetch_assoc()) {
        $thresholds[$row['Metric_Type']] = $row;
    }

    // Map submitted vitals to metric types (match with AlertThreshold Metric_Type)
    $vitals = [
        'Heart Rate' => $heart_rate,
        'Systolic BP' => $systolic,
        'Diastolic BP' => $diastolic,
        'Glucose' => $glucose,
        'Oxygen Saturation' => $oxygen,
        'Temperature' => $temp,
        'Weight' => $weight
    ];

    // Check each vital against thresholds and generate alerts
    $alerts = [];

    foreach ($vitals as $metric_type => $value) {
        if ($value === null) continue; // Skip if no value provided

        $thresh = $thresholds[$metric_type] ?? null;
        if ($thresh) {
            $min_value = $thresh['Min_Value'];
            $max_value = $thresh['Max_Value'];
            $severity = $thresh['Severity'] ?? 'Unknown';
            $description = $thresh['Description'] ?? "Abnormal $metric_type detected.";

            if ($min_value !== null && $value < $min_value) {
                $alerts[] = [
                    'type' => "Low" . str_replace(' ', '', $metric_type),
                    'message' => "$description: $value (Normal: $min_value - $max_value)",
                    'severity' => $severity
                ];
            } elseif ($max_value !== null && $value > $max_value) {
                $alerts[] = [
                    'type' => "High" . str_replace(' ', '', $metric_type),
                    'message' => "$description: $value (Normal: $min_value - $max_value)",
                    'severity' => $severity
                ];
            }
        }
    }

    // Insert alerts into Alerts table
    foreach ($alerts as $alert) {
        $stmt = $conn->prepare("INSERT INTO Alerts (Doctor_ID, Patient_ID, Alert_Type, Message, Severity) VALUES (?, ?, ?, ?, ?)");
        if ($stmt === false) {
            die("Prepare failed (Alerts insert): " . $conn->error);
        }
        $stmt->bind_param("iisss", $doctor_id, $patient_id, $alert['type'], $alert['message'], $alert['severity']);
        $stmt->execute();
        $stmt->close();
    }
}

// Doctor-specific functions

// Get doctor's report count
function getReportCount($doctor_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT COUNT(*) FROM Reports WHERE Doctor_ID = ?");
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_row()[0];
}

// Check if vitals are abnormal
function isVitalAbnormal($vital, $thresholds) {
    // Check heart rate
    if (isset($vital['Heart_Rate']) && isset($thresholds['HeartRate'])) {
        $hr = $vital['Heart_Rate'];
        if ($hr < $thresholds['HeartRate']['Min_Value'] || $hr > $thresholds['HeartRate']['Max_Value']) {
            return true;
        }
    }
    
    // Check blood pressure
    if ((isset($vital['Systolic_BP']) && isset($vital['Diastolic_BP'])) && isset($thresholds['BloodPressure'])) {
        $sys = $vital['Systolic_BP'];
        $dia = $vital['Diastolic_BP'];
        if ($sys < $thresholds['BloodPressure']['Min_Value'] || 
            $dia < $thresholds['BloodPressure']['Min_Value'] || 
            $sys > $thresholds['BloodPressure']['Max_Value'] || 
            $dia > $thresholds['BloodPressure']['Max_Value']) {
            return true;
        }
    }
    
    // Check glucose
    if (isset($vital['Glucose_Level']) && isset($thresholds['Glucose'])) {
        $gluc = $vital['Glucose_Level'];
        if ($gluc < $thresholds['Glucose']['Min_Value'] || $gluc > $thresholds['Glucose']['Max_Value']) {
            return true;
        }
    }
    
    return false;
}

// Get assigned patients for a doctor
function getAssignedPatients($doctor_id, $search = '') {
    global $conn;
    
    $where_clause = "WHERE dm.Doctor_ID = ?";
    $params = [$doctor_id];
    $param_types = "i";

    if (!empty($search)) {
        $where_clause .= " AND (u.Full_Name LIKE ? OR u.Phone_Number LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $param_types .= "ss";
    }

    $stmt = $conn->prepare("SELECT p.Patient_ID, u.Full_Name, u.Phone_Number, 
                           p.Emergency_Contact_Number, COUNT(n.Notification_ID) as alert_count
                           FROM DoctorPatientMapping dm
                           JOIN Patient p ON dm.Patient_ID = p.Patient_ID
                           JOIN User u ON p.User_ID = u.User_ID
                           LEFT JOIN Notifications n ON n.User_ID = u.User_ID AND n.Status = 'Sent'
                           $where_clause
                           GROUP BY p.Patient_ID, u.Full_Name, u.Phone_Number, p.Emergency_Contact_Number
                           ORDER BY u.Full_Name");
    
    call_user_func_array([$stmt, 'bind_param'], array_merge([$param_types], $params));
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get patients needing attention (with alerts)
function getAttentionPatients($doctor_id, $limit = 5) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT p.Patient_ID, u.Full_Name, COUNT(n.Notification_ID) as alert_count
                           FROM DoctorPatientMapping dm
                           JOIN Patient p ON dm.Patient_ID = p.Patient_ID
                           JOIN User u ON p.User_ID = u.User_ID
                           LEFT JOIN Notifications n ON n.User_ID = u.User_ID AND n.Status = 'Sent'
                           WHERE dm.Doctor_ID = ?
                           GROUP BY p.Patient_ID, u.Full_Name
                           HAVING alert_count > 0
                           ORDER BY alert_count DESC
                           LIMIT ?");
    $stmt->bind_param("ii", $doctor_id, $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
function getPatientIdFromUserId($user_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT Patient_ID FROM Patient WHERE User_ID = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0 ? $result->fetch_assoc()['Patient_ID'] : false;
}
// Get recent patient activity for a doctor
function getRecentPatientActivity($doctor_id, $limit = 5) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT p.Patient_ID, u.Full_Name, MAX(h.Timestamp) as last_reading
                           FROM DoctorPatientMapping dm
                           JOIN Patient p ON dm.Patient_ID = p.Patient_ID
                           JOIN User u ON p.User_ID = u.User_ID
                           JOIN HealthData h ON p.Patient_ID = h.Patient_ID
                           WHERE dm.Doctor_ID = ?
                           GROUP BY p.Patient_ID, u.Full_Name
                           ORDER BY last_reading DESC
                           LIMIT ?");
    $stmt->bind_param("ii", $doctor_id, $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>
