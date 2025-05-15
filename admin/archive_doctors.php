<?php
require_once '../config/config.php';
require_once '../includes/header.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Ensure only admins can access
requireRole('Admin');

// Get doctor ID from query parameter
$doctor_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($doctor_id <= 0) {
    die("Invalid doctor ID.");
}

require_once '../config/config.php';
require_once '../includes/header.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Ensure only admins can access
requireRole('Admin');

// Get doctor ID from query parameter
$doctor_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($doctor_id <= 0) {
    die("Invalid doctor ID.");
}

// Fetch archived doctor data without organization restriction
$stmt = $conn->prepare("SELECT d.Doctor_ID, u.Full_Name, d.Specialization, d.License_Number, u.EMAIL
                       FROM Doctor d
                       JOIN User u ON d.User_ID = u.User_ID
                       WHERE d.Doctor_ID = ? AND u.status = 'archived'");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows !== 1) {
    die("Archived doctor not found.");
}
$doctor = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Archived Doctor Profile</title>
    <style>
        /* Define CSS Variables for consistent theming */
        :root {
            --primary: #2C7A7B;
            --primary-dark: #234E52;
            --secondary: #4FD1C5;
            --secondary-dark: #38A169;
            --dark-gray: #4A5568;
        }
        .doctor-dashboard {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }
        .doctor-dashboard h2 {
            color: var(--primary-dark);
            font-size: 2.2rem;
            margin-bottom: 1.5rem;
            font-weight: 700;
            text-align: center;
            position: relative;
            padding-bottom: 1rem;
        }
        .doctor-dashboard h2::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: var(--secondary);
            border-radius: 2px;
        }
        .doctor-dashboard p {
            color: var(--dark-gray);
            font-size: 1rem;
            line-height: 1.6;
            margin-bottom: 1rem;
        }
        .back-link {
            margin: 1rem 2rem;
        }
        .btn-back {
            display: inline-flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            background-color: var(--primary-dark);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        .btn-back:hover {
            background-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(44, 122, 123, 0.3);
        }
        .btn-back i {
            margin-right: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="back-link">
        <a href="dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>
    <div class="doctor-dashboard">
        <h2>Archived Doctor Profile: <?php echo htmlspecialchars($doctor['Full_Name']); ?></h2>
        <p><strong>Specialization:</strong> <?php echo htmlspecialchars($doctor['Specialization']); ?></p>
        <p><strong>License Number:</strong> <?php echo htmlspecialchars($doctor['License_Number']); ?></p>
        <p><strong>Email:</strong> <?php echo htmlspecialchars($doctor['EMAIL']); ?></p>
        <div class="dashboard-cards">
            <a href="doctor/progress_report.php?doctor_id=<?php echo $doctor_id; ?>" class="dashboard-card btn-primary">
                <i class="fas fa-file-medical"></i> View Generated Reports
            </a>
            <a href="view_doctor_patients.php?doctor_id=<?php echo $doctor_id; ?>" class="dashboard-card btn-primary">
                <i class="fas fa-user-injured"></i> View Patients
            </a>
        </div>
    </div>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</body>
</html>
<?php require_once '../includes/footer.php'; ?>
