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
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
if ($patient_id <= 0) {
    die("Invalid patient ID.");
}

// Verify patient belongs to admin's organization
$stmt = $conn->prepare("SELECT p.Patient_ID, u.Full_Name FROM Patient p JOIN User u ON p.User_ID = u.User_ID WHERE p.Patient_ID = ? AND p.Organization_ID = ?");
$stmt->bind_param("ii", $patient_id, $organization_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows !== 1) {
    die("Patient not found or unauthorized access.");
}
$patient = $result->fetch_assoc();

// Get all vitals for this patient
$vitals_history = [];
$stmt = $conn->prepare("SELECT * FROM HealthData WHERE Patient_ID = ? ORDER BY Timestamp DESC");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$vitals_history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Patient Vitals - <?php echo htmlspecialchars($patient['Full_Name']); ?></title>
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
        .patient-vitals {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
            font-family: Arial, sans-serif;
            background-color: #f9f9f9;
        }

        .patient-vitals h2 {
            color: var(--primary-dark);
            font-size: 2.2rem;
            margin-bottom: 1.5rem;
            font-weight: 700;
            text-align: center;
            position: relative;
            padding-bottom: 1rem;
        }

        .patient-vitals h2::after {
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

        /* Table Styles */
        .vitals-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 1rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .vitals-table thead th {
            background-color: var(--primary);
            color: white;
            padding: 0.75rem;
            text-align: center;
            font-weight: 500;
            position: sticky;
            top: 0;
        }

        .vitals-table th:first-child {
            border-top-left-radius: 10px;
        }

        .vitals-table th:last-child {
            border-top-right-radius: 10px;
        }

        .vitals-table td {
            padding: 0.75rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            text-align: center;
            color: var(--dark-gray);
            font-size: 0.9rem;
        }

        .vitals-table tr:last-child td {
            border-bottom: none;
        }

        .vitals-table tr:hover td {
            background-color: rgba(44, 122, 123, 0.03); /* Matches primary teal */
        }

        /* No Data Message */
        .no-data {
            text-align: center;
            padding: 2rem;
            color: var(--dark-gray);
            font-size: 1rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
            margin-top: 1rem;
        }

        /* Responsive Adjustments */
        @media (max-width: 1200px) {
            .vitals-table {
                font-size: 0.85rem;
            }
        }

        @media (max-width: 992px) {
            .patient-vitals {
                padding: 1.5rem;
            }

            .vitals-table th,
            .vitals-table td {
                padding: 0.6rem;
            }
        }

        @media (max-width: 768px) {
            .patient-vitals h2 {
                font-size: 1.9rem;
            }

            .vitals-table {
                font-size: 0.8rem;
            }
        }

        @media (max-width: 576px) {
            .patient-vitals {
                padding: 1rem;
            }

            .back-link {
                margin: 1rem 0;
            }

            .vitals-table {
                min-width: 800px; /* Ensure table is scrollable on small screens */
                overflow-x: auto;
                display: block;
            }

            .no-data {
                padding: 1.5rem;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="patient-vitals">
        <div class="back-link">
            <a href="view_patient.php?id=<?php echo $patient_id; ?>" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Patient Profile</a>
        </div>
        <h2>Vitals History for <?php echo htmlspecialchars($patient['Full_Name']); ?></h2>
        <?php if (count($vitals_history) > 0): ?>
        <table class="vitals-table">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>Heart Rate (bpm)</th>
                    <th>Systolic BP (mmHg)</th>
                    <th>Diastolic BP (mmHg)</th>
                    <th>Glucose (mg/dL)</th>
                    <th>Oxygen Saturation (%)</th>
                    <th>Temperature (°F)</th>
                    <th>Weight (lbs)</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vitals_history as $vital): ?>
                <tr>
                    <td><?php echo htmlspecialchars($vital['Timestamp']); ?></td>
                    <td><?php echo htmlspecialchars($vital['Heart_Rate']); ?></td>
                    <td><?php echo htmlspecialchars($vital['Systolic_BP']); ?></td>
                    <td><?php echo htmlspecialchars($vital['Diastolic_BP']); ?></td>
                    <td><?php echo htmlspecialchars($vital['Glucose_Level']); ?></td>
                    <td><?php echo htmlspecialchars($vital['Oxygen_Saturation']); ?></td>
                    <td><?php echo htmlspecialchars($vital['Temperature']); ?></td>
                    <td><?php echo htmlspecialchars($vital['Weight']); ?></td>
                    <td><?php echo htmlspecialchars($vital['Notes']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p class="no-data">No vitals data available for this patient.</p>
        <?php endif; ?>
    </div>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</body>
</html>

<?php require_once '../includes/footer.php'; ?>