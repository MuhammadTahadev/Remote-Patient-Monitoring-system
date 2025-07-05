<?php
header('Content-Type: application/json');

require_once '../config/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Verify authentication and role (Patient or Doctor)
if (!isLoggedIn() || (!hasRole('Doctor') && !hasRole('Admin'))) {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized']));
}

// Determine patient ID based on role
$user_id = $_SESSION['user_id'];
$patient_id = null;

if (hasRole('Patient')) {
    // For patients, use their own patient ID from session
    $patient_id = $_SESSION['patient_id'];
} elseif (hasRole('Doctor')) {
    // For doctors, get patient ID from query parameter and verify organization
    if (!isset($_GET['patient_id']) || !is_numeric($_GET['patient_id'])) {
        http_response_code(400);
        die(json_encode(['error' => 'Invalid or missing patient ID']));
    }
    $patient_id = (int)$_GET['patient_id'];

    // Get doctor's organization ID
    $stmt = $conn->prepare("SELECT Organization_ID FROM Doctor WHERE User_ID = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows !== 1) {
        http_response_code(403);
        die(json_encode(['error' => 'Doctor organization not found']));
    }
    $doctor_org = $result->fetch_assoc()['Organization_ID'];

    // Verify patient belongs to the same organization
    $stmt = $conn->prepare("SELECT Patient_ID FROM Patient WHERE Patient_ID = ? AND Organization_ID = ?");
    $stmt->bind_param("ii", $patient_id, $doctor_org);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows !== 1) {
        http_response_code(403);
        die(json_encode(['error' => 'Unauthorized access to patient data']));
    }
}

// Ensure patient ID is valid
if (!$patient_id) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid patient ID']));
}

// Get time period from request (default: 24 hours)
$time_period = isset($_GET['period']) ? (int)$_GET['period'] : 24;
$time_period = min(max($time_period, 1), 168); // Limit between 1 and 168 hours (7 days)

// Get vitals data grouped by hour
$stmt = $conn->prepare("SELECT 
                        DATE_FORMAT(Timestamp, '%Y-%m-%d %H:00:00') as hour,
                        AVG(Heart_Rate) as avg_heart_rate,
                        AVG(Systolic_BP) as avg_systolic,
                        AVG(Diastolic_BP) as avg_diastolic,
                        AVG(Glucose_Level) as avg_glucose,
                        AVG(Oxygen_Saturation) as avg_oxygen
                        FROM HealthData 
                        WHERE Patient_ID = ? 
                        AND Timestamp >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                        GROUP BY DATE_FORMAT(Timestamp, '%Y-%m-%d %H:00:00')
                        ORDER BY hour ASC");
$stmt->bind_param("ii", $patient_id, $time_period);
$stmt->execute();
$vitals_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Prepare data for charts
$chart_data = [
    'labels' => [],
    'heart_rate' => [],
    'systolic' => [],
    'diastolic' => [],
    'glucose' => [],
    'oxygen' => []
];

foreach ($vitals_data as $row) {
    $chart_data['labels'][] = date('M j, H:i', strtotime($row['hour']));
    $chart_data['heart_rate'][] = $row['avg_heart_rate'];
    $chart_data['systolic'][] = $row['avg_systolic'];
    $chart_data['diastolic'][] = $row['avg_diastolic'];
    $chart_data['glucose'][] = $row['avg_glucose'];
    $chart_data['oxygen'][] = $row['avg_oxygen'];
}

echo json_encode([
    'success' => true,
    'data' => $chart_data
]);
?>