<?php
require_once '../config/config.php';
require_once '../vendor/autoload.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Verify authentication and patient role

if (!isLoggedIn() || !hasRole('Patient')) {
    http_response_code(401);
    die('Unauthorized');
}

$patient_id = $_SESSION['patient_id'];
$user_id = $_SESSION['user_id'];

// Handle report deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $report_id = $_POST['report_id'] ?? null;
    
    if (!$report_id) {
        setAlert('Invalid report ID', 'error');
        header("Location: ../patient/reports.php");
        exit();
    }
    
    // Verify the report belongs to the current patient
    $stmt = $conn->prepare("SELECT File_Path FROM Reports WHERE Report_ID = ? AND Patient_ID = ?");
    $stmt->bind_param("ii", $report_id, $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        setAlert('Report not found or you do not have permission to delete it', 'error');
        header("Location: ../patient/reports.php");
        exit();
    }
    
    $report = $result->fetch_assoc();
    
    // Delete the file if it exists
    if (!empty($report['File_Path']) && file_exists($report['File_Path'])) {
        unlink($report['File_Path']);
    }
    
    // Delete the record from database
    $stmt = $conn->prepare("DELETE FROM Reports WHERE Report_ID = ?");
    $stmt->bind_param("i", $report_id);
    
    if ($stmt->execute()) {
        setAlert('Report deleted successfully', 'success');
    } else {
        setAlert('Failed to delete report', 'error');
    }
    
    header("Location: ../patient/reports.php");
    exit();
}

// Process report generation request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['action']) || $_POST['action'] !== 'delete')) {
    // ... [keep all the existing report generation code] ...
} else {
    http_response_code(400);
    header("Location: ../patient/reports.php");
    exit();
}