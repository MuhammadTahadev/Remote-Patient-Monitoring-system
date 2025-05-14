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
    $organization_id = null;
}

// Handle delete action
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $patient_id = (int)$_GET['delete'];

    // Verify patient belongs to admin's organization before deleting
    $stmt = $conn->prepare("SELECT Patient_ID FROM Patient WHERE Patient_ID = ? AND Organization_ID = ?");
    $stmt->bind_param("ii", $patient_id, $organization_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $stmt = $conn->prepare("DELETE FROM Patient WHERE Patient_ID = ?");
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        header("Location: manage_patients.php?msg=Patient+deleted+successfully");
        exit();
    } else {
        $error = "Invalid patient or unauthorized action.";
    }
}

// Get list of patients in the organization
$patients = [];
if ($organization_id !== null) {
    $stmt = $conn->prepare("
        SELECT p.Patient_ID, u.Full_Name, u.EMAIL
        FROM Patient p
        JOIN User u ON p.User_ID = u.User_ID
        WHERE p.Organization_ID = ?
    ");
    $stmt->bind_param("i", $organization_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $patients[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Manage Patients</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css" />
</head>
<body>
    <div class="doctor-dashboard">
        <h2>Manage Patients</h2>
        <?php if (isset($error)): ?>
            <p style="color:red;"><?php echo htmlspecialchars($error); ?></p>
        <?php elseif (isset($_GET['msg'])): ?>
            <p style="color:green;"><?php echo htmlspecialchars($_GET['msg']); ?></p>
        <?php endif; ?>
        <?php if (count($patients) > 0): ?>
        <table class="activity-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($patients as $patient): ?>
                <tr>
                    <td><?php echo htmlspecialchars($patient['Full_Name']); ?></td>
                    <td><?php echo htmlspecialchars($patient['EMAIL']); ?></td>
                    <td>
                        <a href="send_message.php?to=patient&id=<?php echo $patient['Patient_ID']; ?>" class="btn-link">Send Message</a> |
                        <a href="manage_patients.php?delete=<?php echo $patient['Patient_ID']; ?>" class="btn-link" onclick="return confirm('Are you sure you want to delete this patient?');">Delete</a> |
                        <a href="view_patient.php?id=<?php echo $patient['Patient_ID']; ?>" class="btn-link">View</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p>No patients found in your organization.</p>
        <?php endif; ?>
    </div>
</body>
</html>
<?php require_once '../includes/footer.php'; ?>
