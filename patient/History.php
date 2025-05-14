<?php
header('Content-Type: application/json');

require_once '../config/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Verify authentication and patient role
if (!isLoggedIn() || !hasRole('Patient') && hasRole('Doctor')) {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized']));
}

$patient_id = $_SESSION['patient_id'];

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