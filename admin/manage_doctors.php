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
    $doctor_id = (int)$_GET['delete'];

    // Verify doctor belongs to admin's organization before deleting
    $stmt = $conn->prepare("SELECT Doctor_ID FROM Doctor WHERE Doctor_ID = ? AND Organization_ID = ?");
    $stmt->bind_param("ii", $doctor_id, $organization_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $stmt = $conn->prepare("DELETE FROM Doctor WHERE Doctor_ID = ?");
        $stmt->bind_param("i", $doctor_id);
        $stmt->execute();
        header("Location: manage_doctors.php?msg=Doctor+deleted+successfully");
        exit();
    } else {
        $error = "Invalid doctor or unauthorized action.";
    }
}

// Get list of doctors in the organization
$doctors = [];
if ($organization_id !== null) {
    $stmt = $conn->prepare("
        SELECT d.Doctor_ID, u.Full_Name, d.Specialization, d.License_Number
        FROM Doctor d
        JOIN User u ON d.User_ID = u.User_ID
        WHERE d.Organization_ID = ?
    ");
    $stmt->bind_param("i", $organization_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $doctors[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Manage Doctors</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css" />
</head>
<body>
    <div class="doctor-dashboard">
        <h2>Manage Doctors</h2>
        <?php if (isset($error)): ?>
            <p style="color:red;"><?php echo htmlspecialchars($error); ?></p>
        <?php elseif (isset($_GET['msg'])): ?>
            <p style="color:green;"><?php echo htmlspecialchars($_GET['msg']); ?></p>
        <?php endif; ?>
        <?php if (count($doctors) > 0): ?>
        <table class="activity-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Specialization</th>
                    <th>License Number</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($doctors as $doctor): ?>
                <tr>
                    <td><?php echo htmlspecialchars($doctor['Full_Name']); ?></td>
                    <td><?php echo htmlspecialchars($doctor['Specialization']); ?></td>
                    <td><?php echo htmlspecialchars($doctor['License_Number']); ?></td>
                    <td>
                        <a href="send_message.php?to=doctor&id=<?php echo $doctor['Doctor_ID']; ?>" class="btn-link">Send Message</a>
                        <a href="manage_doctors.php?delete=<?php echo $doctor['Doctor_ID']; ?>" class="btn-link" onclick="return confirm('Are you sure you want to delete this doctor?');">Delete</a>
                        <a href="view_doctor.php?id=<?php echo $doctor['Doctor_ID']; ?>" class="btn-link">View</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p>No doctors found in your organization.</p>
        <?php endif; ?>
    </div>
</body>
</html>
<?php require_once '../includes/footer.php'; ?>
