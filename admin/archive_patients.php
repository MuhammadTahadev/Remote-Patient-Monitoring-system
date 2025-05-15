<?php
require_once '../config/config.php';
require_once '../includes/header.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Ensure only admins can access
requireRole('Admin');

// Get patient ID from query parameter
$patient_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($patient_id <= 0) {
    die("Invalid patient ID.");
}

// Fetch archived patient data without organization restriction
$stmt = $conn->prepare("SELECT p.Patient_ID, u.Full_Name, u.EMAIL, p.Emergency_Contact_Number, p.Address
                       FROM Patient p
                       JOIN User u ON p.User_ID = u.User_ID
                       WHERE p.Patient_ID = ? AND u.status = 'archived'");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows !== 1) {
    die("Archived patient not found.");
}
$patient = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Archived Patient Profile</title>
    <style>
        /* Define CSS Variables for consistent theming */
        :root {
            --primary: #2C7A7B;
            --primary-dark: #234E52;
            --secondary: #4FD1C5;
            --secondary-dark: #38A169;
            --dark-gray: #4A5568;
        }
        .patient-dashboard {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }
        .patient-dashboard h2 {
            color: var(--primary-dark);
            font-size: 2.2rem;
            margin-bottom: 1.5rem;
            font-weight: 700;
            text-align: center;
            position: relative;
            padding-bottom: 1rem;
        }
        .patient-dashboard h2::after {
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
        .patient-dashboard p {
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
    <div class="patient-dashboard">
        <h2>Archived Patient Profile: <?php echo htmlspecialchars($patient['Full_Name']); ?></h2>
        <p><strong>Email:</strong> <?php echo htmlspecialchars($patient['EMAIL']); ?></p>
        <p><strong>Emergency Contact Number:</strong> <?php echo htmlspecialchars($patient['Emergency_Contact_Number']); ?></p>
        <p><strong>Address:</strong> <?php echo htmlspecialchars($patient['Address']); ?></p>
        <div class="dashboard-cards">
            <a href="patient_vitals.php?patient_id=<?php echo $patient_id; ?>" class="dashboard-card btn-primary">
                <i class="fas fa-heartbeat"></i> View Vitals
            </a>
            <a href="patient/reports.php?patient_id=<?php echo $patient_id; ?>" class="dashboard-card btn-primary">
                <i class="fas fa-file-medical"></i> View Reports
            </a>
            <a href="History.html?patient_id=<?php echo $patient_id; ?>" class="dashboard-card btn-primary">
                <i class="fas fa-chart-line"></i> View Trends
            </a>
        </div>
    </div>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</body>
</html>
<?php require_once '../includes/footer.php'; ?>
