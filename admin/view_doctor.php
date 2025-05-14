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

// Get doctor ID from query parameter
$doctor_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($doctor_id <= 0) {
    die("Invalid doctor ID.");
}

// Verify doctor belongs to admin's organization
$stmt = $conn->prepare("SELECT d.Doctor_ID, u.Full_Name, d.Specialization, d.License_Number, u.EMAIL
                       FROM Doctor d
                       JOIN User u ON d.User_ID = u.User_ID
                       WHERE d.Doctor_ID = ? AND d.Organization_ID = ?");
$stmt->bind_param("ii", $doctor_id, $organization_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows !== 1) {
    die("Doctor not found or unauthorized access.");
}
$doctor = $result->fetch_assoc();
?>

<div class="doctor-dashboard">
    <h2>Doctor Profile: <?php echo htmlspecialchars($doctor['Full_Name']); ?></h2>
    <p><strong>Specialization:</strong> <?php echo htmlspecialchars($doctor['Specialization']); ?></p>
    <p><strong>License Number:</strong> <?php echo htmlspecialchars($doctor['License_Number']); ?></p>
    <p><strong>Email:</strong> <?php echo htmlspecialchars($doctor['EMAIL']); ?></p>

    <div class="dashboard-cards">
        <a href="chat.php?doctor_id=<?php echo $doctor_id; ?>" class="dashboard-card btn-primary" style="text-align:center;">
            <i class="fas fa-comments"></i> Chat with Doctor
        </a>
        <a href="doctor/progress_report.php?doctor_id=<?php echo $doctor_id; ?>" class="dashboard-card btn-primary" style="text-align:center;">
            <i class="fas fa-file-medical"></i> Create Report
        </a>
        <a href="doctor/patients.php?doctor_id=<?php echo $doctor_id; ?>" class="dashboard-card btn-primary" style="text-align:center;">
            <i class="fas fa-user-injured"></i> View Patients
        </a>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
