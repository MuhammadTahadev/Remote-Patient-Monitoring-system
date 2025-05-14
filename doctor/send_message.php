<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Ensure only doctors can access
requireRole('Doctor');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_user_id = (int)$_POST['patient_user_id'];
    $message = sanitize($conn, $_POST['message']);
    $doctor_id = $_SESSION['doctor_id'];

    // Validate input
    if (empty($message)) {
        setAlert('Message cannot be empty', 'error');
        header("Location: vitals.php?patient_id=" . $_POST['patient_id']);
        exit();
    }

    // Verify the doctor is assigned to this patient
    $stmt = $conn->prepare("SELECT 1 FROM DoctorPatientMapping dpm
                           JOIN Patient p ON dpm.Patient_ID = p.Patient_ID
                           WHERE dpm.Doctor_ID = ? AND p.User_ID = ?");
    $stmt->bind_param("ii", $doctor_id, $patient_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        setAlert('You are not authorized to message this patient', 'error');
        header("Location: vitals.php?patient_id=" . $_POST['patient_id']);
        exit();
    }
    $stmt->close();

    // Get the doctor's User_ID
    $stmt = $conn->prepare("SELECT User_ID FROM Doctor WHERE Doctor_ID = ?");
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    $doctor_user_id = $stmt->get_result()->fetch_assoc()['User_ID'];
    $stmt->close();

    // Insert the message into the Notifications table
    $stmt = $conn->prepare("INSERT INTO Notifications (User_ID, Sender_ID, Alert_Type, Message, Sent_At, Status)
                           VALUES (?, ?, 'Message', ?, NOW(), 'Sent')");
    $stmt->bind_param("iis", $patient_user_id, $doctor_user_id, $message);
    if ($stmt->execute()) {
        setAlert('Message sent successfully', 'success');
    } else {
        setAlert('Failed to send message: ' . $stmt->error, 'error');
    }
    $stmt->close();

    header("Location: vitals.php?patient_id=" . $_POST['patient_id']);
} else {
    setAlert('Invalid request', 'error');
    header("Location: patients.php");
}
exit();
?>