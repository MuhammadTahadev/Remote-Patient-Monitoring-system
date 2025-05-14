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
    // If no organization found, set to null or handle error
    $organization_id = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reinstate_user_id'])) {
    $reinstate_user_id = (int)$_POST['reinstate_user_id'];

    // Verify user belongs to admin's organization before reinstating
    $stmt = $conn->prepare("
        SELECT u.User_ID
        FROM User u
        LEFT JOIN Doctor d ON u.User_ID = d.User_ID
        LEFT JOIN Patient p ON u.User_ID = p.User_ID
        WHERE u.User_ID = ? AND (d.Organization_ID = ? OR p.Organization_ID = ?)
    ");
    $stmt->bind_param("iii", $reinstate_user_id, $organization_id, $organization_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        // Update user status to active
        $stmt = $conn->prepare("UPDATE User SET status = 'active' WHERE User_ID = ?");
        $stmt->bind_param("i", $reinstate_user_id);
        $stmt->execute();

        header("Location: dashboard.php?msg=User+reinstated+successfully");
        exit();
    } else {
        $error = "Invalid user or unauthorized action.";
    }
}

$doctor_count = 0;
$patient_count = 0;
$archived_users = [];

if ($organization_id !== null) {
    // Get active doctors count
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM Doctor d JOIN User u ON d.User_ID = u.User_ID WHERE d.Organization_ID = ? AND u.status = 'active'");
    $stmt->bind_param("i", $organization_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $doctor_count = $result->fetch_assoc()['count'];

    // Get active patients count
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM Patient p JOIN User u ON p.User_ID = u.User_ID WHERE p.Organization_ID = ? AND u.status = 'active'");
    $stmt->bind_param("i", $organization_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $patient_count = $result->fetch_assoc()['count'];

    // Get archived users (doctors and patients) for this organization
    $stmt = $conn->prepare("
        SELECT u.User_ID, u.Full_Name, u.EMAIL, r.Role_Name
        FROM User u
        JOIN Role r ON u.Role_ID = r.Role_ID
        LEFT JOIN Doctor d ON u.User_ID = d.User_ID AND d.Organization_ID = ?
        LEFT JOIN Patient p ON u.User_ID = p.User_ID AND p.Organization_ID = ?
        WHERE u.status = 'archived' AND (d.Doctor_ID IS NOT NULL OR p.Patient_ID IS NOT NULL)
    ");
    $stmt->bind_param("ii", $organization_id, $organization_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $archived_users[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Admin Dashboard</title>
    <style>
        /* Define CSS Variables for consistent theming */
        :root {
            --primary: #2C7A7B; /* Teal primary color */
            --primary-dark: #234E52; /* Darker teal for depth */
            --secondary: #4FD1C5; /* Light teal for accents */
            --secondary-dark: #38A169; /* Slightly darker secondary for contrast */
            --dark-gray: #4A5568; /* Neutral gray for text */
        }

        /* Admin Dashboard Modern Styles */
        .admin-dashboard {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        .admin-dashboard h2 {
            color: var(--primary-dark);
            font-size: 2.2rem;
            margin-bottom: 1.5rem;
            font-weight: 700;
            text-align: center;
            position: relative;
            padding-bottom: 1rem;
        }

        .admin-dashboard h2::after {
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

        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); /* Reduced card width */
            gap: 1rem; /* Reduced gap */
            margin-bottom: 2rem;
        }

        /* Dashboard Cards */
        .dashboard-card {
            background: white;
            border-radius: 10px; /* Slightly smaller border radius */
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08); /* Reduced shadow */
            padding: 1.25rem; /* Reduced padding */
            transition: all 0.3s ease;
            border: 1px solid rgba(0, 0, 0, 0.05);
            position: relative;
            overflow: hidden;
        }

        .dashboard-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 0;
            background: var(--secondary);
            transition: height 0.3s ease;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.12);
            border-color: rgba(79, 209, 197, 0.2); /* Matches secondary teal */
        }

        .dashboard-card:hover::before {
            height: 100%;
        }

        .card-icon {
            width: 48px; /* Reduced icon size */
            height: 48px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem; /* Reduced font size */
            margin-bottom: 1rem;
            position: relative;
            transition: transform 0.3s ease;
        }

        .dashboard-card:hover .card-icon {
            transform: rotate(10deg) scale(1.1);
        }

        .card-content h3 {
            color: var(--primary-dark);
            font-size: 1.1rem; /* Reduced font size */
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .card-value {
            font-size: 1.8rem; /* Reduced font size */
            font-weight: 700;
            color: var(--secondary-dark);
            margin: 0.5rem 0 1rem;
            letter-spacing: -0.5px;
        }

        .card-content p {
            color: var(--dark-gray);
            font-size: 0.9rem; /* Reduced font size */
            line-height: 1.4;
            margin-bottom: 1rem;
        }

        /* Responsive Adjustments */
        @media (max-width: 1200px) {
            .dashboard-cards {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
        }

        @media (max-width: 992px) {
            .admin-dashboard {
                padding: 1.5rem;
            }
        }

        @media (max-width: 768px) {
            .admin-dashboard h2 {
                font-size: 1.9rem;
            }
        }

        @media (max-width: 576px) {
            .admin-dashboard {
                padding: 1rem;
            }

            .dashboard-cards {
                grid-template-columns: 1fr;
            }

            .dashboard-card {
                padding: 1rem;
            }

            .card-content h3 {
                font-size: 1rem;
            }

            .card-value {
                font-size: 1.6rem;
            }
        }
    </style>
</head>
<body>
    <div class="admin-dashboard">
        <h2>Admin Dashboard</h2>
        <div class="dashboard-cards">
            <a href="manage_doctors.php" class="dashboard-card" style="text-decoration:none;">
                <div class="card-icon"><i class="fas fa-user-md"></i></div>
                <div class="card-content">
                    <h3>Manage Doctors</h3>
                    <div class="card-value"><?php echo $doctor_count; ?></div>
                    <p>Doctors in your organization</p>
                </div>
            </a>
            <a href="manage_patients.php" class="dashboard-card" style="text-decoration:none;">
                <div class="card-icon"><i class="fas fa-procedures"></i></div>
                <div class="card-content">
                    <h3>Manage Patients</h3>
                    <div class="card-value"><?php echo $patient_count; ?></div>
                    <p>Patients in your organization</p>
                </div>
            </a>
        </div>

        <!-- Archived Users Section -->
        <div class="archived-users-section" style="margin-top: 2rem;">
            <h2>Archived Users</h2>
            <?php if (count($archived_users) > 0): ?>
            <table class="activity-table" style="width: 100%; border-collapse: collapse; margin-top: 1rem;">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($archived_users as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['Full_Name']); ?></td>
                        <td><?php echo htmlspecialchars($user['EMAIL']); ?></td>
                        <td><?php echo htmlspecialchars($user['Role_Name']); ?></td>
                        <td>
                            <form method="post" action="dashboard.php" style="margin:0;">
                                <input type="hidden" name="reinstate_user_id" value="<?php echo $user['User_ID']; ?>" />
                                <button type="submit" class="btn-link" style="color: var(--primary); background:none; border:none; cursor:pointer;">Reinstate</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p>No archived users found.</p>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</body>
</html>
<?php require_once '../includes/footer.php'; ?>
