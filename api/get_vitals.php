<?php
header('Content-Type: application/json');

require_once '../config/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Verify authentication
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    http_response_code(403);
    die(json_encode(['error' => 'Direct access not allowed']));
}

session_start();
if (!isLoggedIn()) {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized']));
}

// Get vital details
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $vital_id = (int)$_GET['id'];
    $patient_id = $_SESSION['patient_id'];
    
    // Verify the vital belongs to the current patient
    $stmt = $conn->prepare("SELECT h.* FROM HealthData h
                           JOIN Patient p ON h.Patient_ID = p.Patient_ID
                           JOIN User u ON p.User_ID = u.User_ID
                           WHERE h.Data_ID = ? AND u.User_ID = ?");
    $stmt->bind_param("ii", $vital_id, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $vital = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'vital' => $vital
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Vital record not found'
        ]);
    }
} else {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid request'
    ]);
}
?>