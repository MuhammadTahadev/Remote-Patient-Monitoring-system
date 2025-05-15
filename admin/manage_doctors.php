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

// Handle delete action (soft delete)
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $doctor_id = (int)$_GET['delete'];

    // Verify doctor belongs to admin's organization before soft deleting
    $stmt = $conn->prepare("SELECT d.Doctor_ID, d.User_ID FROM Doctor d WHERE d.Doctor_ID = ? AND d.Organization_ID = ?");
    $stmt->bind_param("ii", $doctor_id, $organization_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $doctor = $result->fetch_assoc();
        $user_id = $doctor['User_ID'];

        // Soft delete by updating User.status to 'archived'
        $stmt = $conn->prepare("UPDATE User SET status = 'archived' WHERE User_ID = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();

        header("Location: manage_doctors.php?msg=Doctor+archived+successfully");
        exit();
    } else {
        $error = "Invalid doctor or unauthorized action.";
    }
}

// Get list of doctors in the organization excluding archived users
$doctors = [];
if ($organization_id !== null) {
    $stmt = $conn->prepare("
        SELECT d.Doctor_ID, u.Full_Name, d.Specialization, d.License_Number
        FROM Doctor d
        JOIN User u ON d.User_ID = u.User_ID
        WHERE d.Organization_ID = ? AND u.status = 'active'
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
    <style>
        /* Define CSS Variables for consistent theming */
        :root {
            --primary: #2C7A7B; /* Teal primary color */
            --primary-dark: #234E52; /* Darker teal for depth */
            --secondary: #4FD1C5; /* Light teal for accents */
            --secondary-dark: #38A169; /* Slightly darker secondary for contrast */
            --dark-gray: #4A5568; /* Neutral gray for text */
        }

        /* Doctor Dashboard Modern Styles */
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

        /* Back Link */
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

        /* Activity Table */
        .activity-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 1rem;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }

        .activity-table thead th {
            background-color: var(--primary);
            color: white;
            padding: 1rem;
            text-align: left;
            font-weight: 500;
            position: sticky;
            top: 0;
        }

        .activity-table th:first-child {
            border-top-left-radius: 8px;
        }

        .activity-table th:last-child {
            border-top-right-radius: 8px;
        }

        .activity-table td {
            padding: 1rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .activity-table tr:last-child td {
            border-bottom: none;
        }

        .activity-table tr:hover td {
            background-color: rgba(44, 122, 123, 0.03); /* Matches primary teal */
        }

        /* Buttons */
        .btn-link {
            color: var(--primary);
            background: none;
            border: none;
            padding: 0.5rem 0;
            margin-right: 1rem;
            font: inherit;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
        }

        .btn-link:hover {
            color: var(--secondary-dark);
            transform: translateX(3px);
        }

        .btn-link i {
            margin-right: 0.5rem;
            font-size: 0.9rem;
        }

        /* Messages */
        .error-message {
            color: #E53E3E; /* Keep red for errors for clarity */
            margin-bottom: 1rem;
        }

        .success-message {
            color: var(--secondary-dark);
            margin-bottom: 1rem;
        }

        /* Responsive Adjustments */
        @media (max-width: 1200px) {
            .activity-table {
                font-size: 0.95rem;
            }
        }

        @media (max-width: 992px) {
            .doctor-dashboard {
                padding: 1.5rem;
            }

            .activity-table {
                font-size: 0.9rem;
            }

            .activity-table th,
            .activity-table td {
                padding: 0.8rem;
            }
        }

        @media (max-width: 768px) {
            .doctor-dashboard h2 {
                font-size: 1.9rem;
            }

            .activity-table {
                font-size: 0.85rem;
            }
        }

        @media (max-width: 576px) {
            .doctor-dashboard {
                padding: 1rem;
            }

            .back-link {
                margin: 1rem;
            }

            .activity-table {
                min-width: 600px;
                overflow-x: auto;
                display: block;
            }
        }
    </style>
</head>
<body>
    <div class="back-link">
        <a href="dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>
    <div class="doctor-dashboard">
        <h2>Manage Doctors</h2>
        <?php if (isset($error)): ?>
            <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
        <?php elseif (isset($_GET['msg'])): ?>
            <p class="success-message"><?php echo htmlspecialchars($_GET['msg']); ?></p>
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
                        <a href="manage_doctors.php?delete=<?php echo $doctor['Doctor_ID']; ?>" class="btn-link" onclick="return confirm('Are you sure you want to delete this doctor?');">Disable</a>
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
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</body>
</html>
<?php require_once '../includes/footer.php'; ?>