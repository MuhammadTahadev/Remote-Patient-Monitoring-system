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
$doctor_id = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
if ($doctor_id <= 0) {
    die("Invalid doctor ID.");
}

// Verify doctor belongs to admin's organization
$stmt = $conn->prepare("SELECT d.Doctor_ID, u.Full_Name FROM Doctor d JOIN User u ON d.User_ID = u.User_ID WHERE d.Doctor_ID = ? AND d.Organization_ID = ?");
$stmt->bind_param("ii", $doctor_id, $organization_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows !== 1) {
    die("Doctor not found or unauthorized access.");
}
$doctor = $result->fetch_assoc();

// Search functionality
$search = $_GET['search'] ?? '';
$where_clause = "WHERE dm.Doctor_ID = ?";
$params = [$doctor_id];
$param_types = "i";

if (!empty($search)) {
    $where_clause .= " AND (u.Full_Name LIKE ? OR u.EMAIL LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $param_types .= "ss";
}

// Get assigned patients with the same organization, including unread messages and alerts
$query = "SELECT p.Patient_ID, u.User_ID, u.Full_Name, u.EMAIL, 
          p.Emergency_Contact_Number, 
          COUNT(n.Notification_ID) as alert_count,
          COUNT(a.Alert_ID) as notification_count
          FROM DoctorPatientMapping dm
          JOIN Patient p ON dm.Patient_ID = p.Patient_ID
          JOIN User u ON p.User_ID = u.User_ID
          JOIN Doctor d ON dm.Doctor_ID = d.Doctor_ID
          LEFT JOIN Notifications n ON n.User_ID = u.User_ID AND n.Status = 'Sent'
          LEFT JOIN Alerts a ON a.Doctor_ID = d.Doctor_ID AND a.Patient_ID = p.Patient_ID AND a.Status = 'Unread'
          $where_clause
          GROUP BY p.Patient_ID, u.User_ID, u.Full_Name, u.EMAIL, p.Emergency_Contact_Number
          ORDER BY u.Full_Name";

$stmt = $conn->prepare($query);
if ($stmt === false) {
    die("Prepare failed: " . $conn->error);
}

// Prepare the parameters for binding
$references = [];
$references[] = &$param_types; // Reference to the type string
foreach ($params as $key => $value) {
    $references[] = &$params[$key]; // References to each parameter
}

// Bind parameters using call_user_func_array
call_user_func_array([$stmt, 'bind_param'], $references);

$stmt->execute();
$patients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Display any alerts (e.g., message sent confirmation)
displayAlert();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Patients Assigned to Dr. <?php echo htmlspecialchars($doctor['Full_Name']); ?></title>
    <style>
        /* Define CSS Variables for consistent theming */
        :root {
            --primary: #2C7A7B; /* Teal primary color */
            --primary-dark: #234E52; /* Darker teal for depth */
            --secondary: #4FD1C5; /* Light teal for accents */
            --secondary-dark: #38A169; /* Slightly darker secondary for contrast */
            --dark-gray: #4A5568; /* Neutral gray for text */
        }

        /* Main Container Styles */
        .patient-container {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
            font-family: Arial, sans-serif;
            background-color: #fffae0;
        }

        .patient-container h2 {
            color: var(--primary-dark);
            font-size: 2.2rem;
            margin-bottom: 1.5rem;
            font-weight: 700;
            text-align: center;
            position: relative;
            padding-bottom: 1rem;
        }

        .patient-container h2::after {
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

        /* Back Link */
        .back-link {
            margin: 1rem 0;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            padding: 0.6rem 1.2rem;
            background-color: var(--primary-dark);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.9rem;
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

        /* Search Bar */
        .search-bar {
            background: white;
            border-radius: 10px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
            padding: 1rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }

        .search-bar:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.12);
        }

        .search-bar .form-group {
            display: flex;
            align-items: center;
            margin: 0;
        }

        .search-bar input {
            flex: 1;
            padding: 0.6rem;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 8px 0 0 8px;
            font-size: 0.9rem;
            transition: border-color 0.3s ease;
        }

        .search-bar input:focus {
            outline: none;
            border-color: var(--secondary);
        }

        .search-bar .btn {
            padding: 0.6rem 1rem;
            border-radius: 0 8px 8px 0;
            background-color: var(--primary-dark);
            color: white;
            border: none;
            transition: all 0.3s ease;
        }

        .search-bar .btn:hover {
            background-color: var(--primary);
        }

        /* Table Styles */
        .table-responsive {
            background: white;
            border-radius: 10px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
            padding: 1rem;
            overflow-x: auto;
            transition: all 0.3s ease;
        }

        .table-responsive:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.12);
        }

        .vitals-history-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 1rem;
        }

        .vitals-history-table thead th {
            background-color: var(--primary);
            color: white;
            padding: 0.75rem;
            text-align: center;
            font-weight: 500;
            position: sticky;
            top: 0;
        }

        .vitals-history-table th:first-child {
            border-top-left-radius: 10px;
        }

        .vitals-history-table th:last-child {
            border-top-right-radius: 10px;
        }

        .vitals-history-table td {
            padding: 0.75rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            text-align: center;
            color: var(--dark-gray);
            font-size: 0.9rem;
            vertical-align: middle;
        }

        .vitals-history-table tr:last-child td {
            border-bottom: none;
        }

        .vitals-history-table tr:hover td {
            background-color: rgba(44, 122, 123, 0.03);
        }

        /* Column Widths */
        .col-name {
            width: 20%;
        }

        .col-email {
            width: 20%;
        }

        .col-emergency {
            width: 15%;
        }

        .col-messages {
            width: 15%;
        }

        .col-alerts {
            width: 15%;
        }

        .col-actions {
            width: 15%;
        }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 0.3rem 0.6rem;
            border-radius: 12px;
            font-size: 0.85rem;
            line-height: 1;
        }

        .normal-badge {
            background-color: #e6ffe6;
            color: #2d862d;
        }

        .warning-badge {
            background-color: #fff3e6;
            color: #cc6600;
        }

        /* Alerts Wrapper */
        .alerts-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .alert-btn {
            display: inline-flex;
            align-items: center;
            padding: 0.3rem 0.6rem;
            background-color: #ff9800;
            color: white;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.85rem;
            transition: all 0.3s ease;
        }

        .alert-btn:hover {
            background-color: #f57c00;
            transform: translateY(-2px);
        }

        .alert-btn i {
            margin-right: 0.3rem;
        }

        /* Actions Wrapper */
        .actions-wrapper {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn-link {
            display: inline-flex;
            align-items: center;
            padding: 0.3rem 0.6rem;
            background-color: var(--primary-dark);
            color: white;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.85rem;
            transition: all 0.3s ease;
        }

        .btn-link:hover {
            background-color: var(--primary);
            transform: translateY(-2px);
        }

        /* No Records */
        .no-records {
            background: white;
            border-radius: 10px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .no-records:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.12);
        }

        .no-records i {
            font-size: 2rem;
            color: var(--primary-dark);
            margin-bottom: 1rem;
            display: block;
        }

        .no-records p {
            margin: 0;
            font-size: 1rem;
            color: var(--dark-gray);
        }

        /* Responsive Adjustments */
        @media (max-width: 1200px) {
            .vitals-history-table {
                font-size: 0.85rem;
            }

            .col-name, .col-email, .col-emergency, .col-messages, .col-alerts, .col-actions {
                width: auto;
            }
        }

        @media (max-width: 992px) {
            .patient-container {
                padding: 1.5rem;
            }

            .vitals-history-table th,
            .vitals-history-table td {
                padding: 0.6rem;
            }

            .search-bar, .table-responsive, .no-records {
                padding: 1rem;
            }
        }

        @media (max-width: 768px) {
            .patient-container h2 {
                font-size: 1.9rem;
            }

            .search-bar .form-group {
                flex-direction: column;
            }

            .search-bar input {
                border-radius: 8px;
                margin-bottom: 0.5rem;
            }

            .search-bar .btn {
                border-radius: 8px;
                width: 100%;
            }

            .vitals-history-table {
                min-width: 800px; /* Ensure table is scrollable */
                display: block;
                overflow-x: auto;
            }
        }

        @media (max-width: 576px) {
            .patient-container {
                padding: 1rem;
            }

            .vitals-history-table th,
            .vitals-history-table td {
                font-size: 0.8rem;
                padding: 0.5rem;
            }

            .alert-btn, .btn-link {
                font-size: 0.75rem;
                padding: 0.2rem 0.4rem;
            }
        }
    </style>
</head>
<body>
    <div class="patient-container">
        <h2>Patients Assigned to Dr. <?php echo htmlspecialchars($doctor['Full_Name']); ?></h2>
        <div class="back-link">
            <a href="view_doctor.php?id=<?php echo $doctor_id; ?>" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Doctor Profile</a>
        </div>
        <div class="search-bar">
            <form method="get" action="view_doctor_patients.php">
                <input type="hidden" name="doctor_id" value="<?php echo $doctor_id; ?>" />
                <div class="form-group">
                    <input type="text" name="search" placeholder="Search patients..." value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn"><i class="fas fa-search"></i></button>
                </div>
            </form>
        </div>
        
        <?php if (!empty($patients)): ?>
            <div class="table-responsive">
                <table class="vitals-history-table">
                    <thead>
                        <tr>
                            <th class="col-name">Patient Name</th>
                            <th class="col-email">Email</th>
                            <th class="col-emergency">Emergency Contact</th>
                            <th class="col-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($patients as $patient): ?>
                            <tr>
                                <td class="col-name"><?= htmlspecialchars($patient['Full_Name']) ?></td>
                                <td class="col-email"><?= htmlspecialchars($patient['EMAIL']) ?></td>
                                <td class="col-emergency"><?= htmlspecialchars($patient['Emergency_Contact_Number']) ?></td>
                                <td class="col-actions">
                                    <div class="actions-wrapper">
                                        <a href="manage_patients.php?delete=<?php echo $patient['Patient_ID']; ?>" class="btn-link" onclick="return confirm('Are you sure you want to delete this patient?');">Disable</a>
                                        <a href="view_patient.php?id=<?php echo $patient['Patient_ID']; ?>" class="btn-link">View</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="no-records">
                <i class="fas fa-info-circle"></i>
                <p>No patients assigned to this doctor from your organization yet.</p>
            </div>
        <?php endif; ?>
    </div>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</body>
</html>

<?php require_once '../includes/footer.php'; ?>