<?php
require_once '../config/config.php';
require_once '../includes/header.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Ensure only admins can access
requireRole('Admin');

// Get logged-in user ID
$user_id = $_SESSION['user_id'];

// Get organization_id for the admin
$stmt = $conn->prepare("SELECT Organization_ID FROM Admin WHERE User_ID = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 1) {
    $org = $result->fetch_assoc();
    $organization_id = $org['Organization_ID'];
} else {
    die("Organization not found.");
}

// Get patient ID from query parameter
$patient_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($patient_id <= 0) {
    die("Invalid patient ID.");
}

// Verify patient belongs to admin's organization
$stmt = $conn->prepare("SELECT p.Patient_ID, u.Full_Name, u.EMAIL
                       FROM Patient p
                       JOIN User u ON p.User_ID = u.User_ID
                       WHERE p.Patient_ID = ? AND p.Organization_ID = ?");
$stmt->bind_param("ii", $patient_id, $organization_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows !== 1) {
    die("Patient not found or unauthorized access.");
}
$patient = $result->fetch_assoc();
?>

<div class="patient-dashboard">
    <h2>Patient Profile: <?php echo htmlspecialchars($patient['Full_Name']); ?></h2>
    <p><strong>Email:</strong> <?php echo htmlspecialchars($patient['EMAIL']); ?></p>

    <div class="dashboard-cards">
        <a href="patient/vitals.php?patient_id=<?php echo $patient_id; ?>" class="dashboard-card btn-primary" style="text-align:center;">
            <i class="fas fa-heartbeat"></i> View Vitals
        </a>
        <a href="chat.php?patient_id=<?php echo $patient_id; ?>" class="dashboard-card btn-primary" style="text-align:center;">
            <i class="fas fa-comments"></i> Chat with Patient
        </a>
        <a href="patient/reports.php?patient_id=<?php echo $patient_id; ?>" class="dashboard-card btn-primary" style="text-align:center;">
            <i class="fas fa-file-medical"></i> View Reports
        </a>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
