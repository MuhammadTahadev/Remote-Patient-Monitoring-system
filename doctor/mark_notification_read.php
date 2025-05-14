<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Ensure only doctors can access
requireRole('Doctor');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $doctor_id = $_SESSION['doctor_id'];
    
    // Get doctor's User_ID
    $stmt = $conn->prepare("SELECT User_ID FROM Doctor WHERE Doctor_ID = ?");
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    $user_id = $stmt->get_result()->fetch_assoc()['User_ID'];
    $stmt->close();

    try {
        if (isset($_POST['notification_id'])) {
            $notification_id = (int)$_POST['notification_id'];
            
            $stmt = $conn->prepare("UPDATE Notifications SET Status = 'Read' 
                                   WHERE Notification_ID = ? AND User_ID = ? AND Status = 'Sent'");
            $stmt->bind_param("ii", $notification_id, $user_id);
            
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    setAlert('Message marked as read', 'success');
                } else {
                    setAlert('Message not found or already read', 'warning');
                }
            } else {
                throw new Exception("Database error: " . $stmt->error);
            }
            $stmt->close();
        } else {
            setAlert('Invalid request parameters', 'error');
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        setAlert('An error occurred while processing your request', 'error');
    }
} else {
    setAlert('Invalid request method', 'error');
}

header("Location: alert_history.php");
exit();
?>