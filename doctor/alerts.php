<?php
require_once '../config/config.php';
require_once '../includes/header.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Ensure only doctors can access
requireRole('Doctor');

$doctor_id = $_SESSION['doctor_id'];

// Get patient_id from the URL
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

// Handle "Noted" button submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_noted'])) {
    // Fetch the Alert_IDs of the displayed alerts (latest unread per Alert_Type)
    $stmt = $conn->prepare("SELECT a1.Alert_ID 
                           FROM Alerts a1
                           WHERE a1.Doctor_ID = ? AND a1.Patient_ID = ? AND a1.Status = 'Unread'
                           AND a1.Timestamp = (
                               SELECT MAX(a2.Timestamp)
                               FROM Alerts a2
                               WHERE a2.Doctor_ID = a1.Doctor_ID 
                               AND a2.Patient_ID = a1.Patient_ID 
                               AND a2.Alert_Type = a1.Alert_Type 
                               AND a2.Status = 'Unread'
                           )");
    $stmt->bind_param("ii", $doctor_id, $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $alert_ids = [];
    while ($row = $result->fetch_assoc()) {
        $alert_ids[] = $row['Alert_ID'];
    }
    $stmt->close();

    // Mark the fetched alerts as "Read"
    if (!empty($alert_ids)) {
        $placeholders = implode(',', array_fill(0, count($alert_ids), '?'));
        $stmt = $conn->prepare("UPDATE Alerts SET Status = 'Read' WHERE Alert_ID IN ($placeholders)");
        $types = str_repeat('i', count($alert_ids));
        $stmt->bind_param($types, ...$alert_ids);
        $stmt->execute();
        $stmt->close();

        setAlert("Alerts marked as noted.", "success");
    }

    // Redirect to refresh the page
    header("Location: alerts.php?patient_id=$patient_id");
    exit();
}

// Validate that the patient is assigned to this doctor
$stmt = $conn->prepare("SELECT p.Patient_ID, u.Full_Name 
                       FROM DoctorPatientMapping dm
                       JOIN Patient p ON dm.Patient_ID = p.Patient_ID
                       JOIN User u ON p.User_ID = u.User_ID
                       WHERE dm.Doctor_ID = ? AND dm.Patient_ID = ?");
$stmt->bind_param("ii", $doctor_id, $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$patient) {
    setAlert("Patient not found or not assigned to you.", "error");
    header("Location: patients.php");
    exit();
}

// Fetch the latest unread alert for each Alert_Type
$stmt = $conn->prepare("SELECT a1.Alert_Type, a1.Message, a1.Severity, a1.Timestamp 
                       FROM Alerts a1
                       WHERE a1.Doctor_ID = ? AND a1.Patient_ID = ? AND a1.Status = 'Unread'
                       AND a1.Timestamp = (
                           SELECT MAX(a2.Timestamp)
                           FROM Alerts a2
                           WHERE a2.Doctor_ID = a1.Doctor_ID 
                           AND a2.Patient_ID = a1.Patient_ID 
                           AND a2.Alert_Type = a1.Alert_Type 
                           AND a2.Status = 'Unread'
                       )
                       ORDER BY a1.Timestamp DESC");
$stmt->bind_param("ii", $doctor_id, $patient_id);
$stmt->execute();
$alerts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Display any alerts (e.g., error messages)
displayAlert();
?>

<div class="alerts-container">
    <h2>Alerts for <?= htmlspecialchars($patient['Full_Name']) ?></h2>
    <div class="back-link">
        <a href="patients.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Patients</a>
    </div>
    
    <?php if (!empty($alerts)): ?>
        <div class="table-responsive card">
            <table class="alerts-table">
                <thead>
                    <tr>
                        <th class="col-alert-type">Alert Type</th>
                        <th class="col-message">Message</th>
                        <th class="col-severity">Severity</th>
                        <th class="col-timestamp">Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($alerts as $alert): ?>
                        <tr>
                            <td class="col-alert-type"><?= htmlspecialchars($alert['Alert_Type']) ?></td>
                            <td class="col-message"><?= htmlspecialchars($alert['Message']) ?></td>
                            <td class="col-severity">
                                <span class="badge severity-<?= strtolower($alert['Severity']) ?>">
                                    <?= htmlspecialchars($alert['Severity']) ?>
                                </span>
                            </td>
                            <td class="col-timestamp"><?= htmlspecialchars($alert['Timestamp']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="noted-button-wrapper">
            <form method="post">
                <button type="submit" name="mark_noted" class="btn-noted">Noted</button>
            </form>
        </div>
    <?php else: ?>
        <div class="no-records card">
            <i class="fas fa-info-circle"></i>
            <p>No unread alerts found for this patient.</p>
        </div>
    <?php endif; ?>
</div>

<style>
.alerts-container {
    padding: 2rem;
    max-width: 1400px;
    margin: 0 auto;
    font-family: 'Arial', sans-serif;
}

.alerts-container h2 {
    color: var(--primary-dark);
    font-size: 2.2rem;
    margin-bottom: 1.5rem;
    font-weight: 700;
    text-align: center;
    position: relative;
    padding-bottom: 1rem;
}

.alerts-container h2::after {
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

.back-link {
    margin-bottom: 1.5rem;
    text-align: left;
}

.btn-back {
    display: inline-flex;
    align-items: center;
    justify-content: center;
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
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
}

.btn-back i {
    margin-right: 0.5rem;
}

.table-responsive {
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    padding: 1.5rem;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    overflow-x: auto;
}

.table-responsive:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12);
}

.alerts-table {
    width: 100%;
    border-collapse: collapse;
    background: #f9fafb;
    border-radius: 8px;
    overflow: hidden;
}

.alerts-table th,
.alerts-table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    vertical-align: middle;
}

.alerts-table th {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    color: white;
    font-weight: 600;
    white-space: nowrap;
}

.alerts-table td {
    background: white;
    color: var(--dark-gray);
}

/* Define column widths */
.col-alert-type {
    width: 20%;
}

.col-message {
    width: 40%;
}

.col-severity {
    width: 15%;
    text-align: center;
}

.col-timestamp {
    width: 25%;
}

/* Style for badges */
.badge {
    display: inline-block;
    padding: 0.3rem 0.6rem;
    border-radius: 12px;
    font-size: 0.85rem;
    line-height: 1;
    vertical-align: middle;
}

.severity-critical {
    background-color: #ffe6e6;
    color: #cc0000;
}

.severity-high {
    background-color: #fff3e6;
    color: #cc6600;
}

.severity-moderate {
    background-color: #fffbe6;
    color: #cc9900;
}

.severity-low,
.severity-unknown {
    background-color: #e6f7ff;
    color: #0066cc;
}

/* Noted Button */
.noted-button-wrapper {
    margin-top: 1.5rem;
    text-align: center;
}

.btn-noted {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.75rem 1.5rem;
    background-color: #4caf50;
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 500;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-noted:hover {
    background-color: #45a049;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
}

.no-records {
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    padding: 2rem;
    text-align: center;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.no-records:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12);
}

.no-records i {
    font-size: 2rem;
    color: var(--primary-dark);
    margin-bottom: 1rem;
    display: block;
}

.no-records p {
    margin: 0;
    font-size: 1.1rem;
    color: var(--dark-gray);
}

/* Responsive Adjustments */
@media (max-width: 1024px) {
    .alerts-container {
        padding: 1.5rem;
    }
    
    .table-responsive,
    .no-records {
        padding: 1.5rem;
    }
    
    .alerts-table th,
    .alerts-table td {
        padding: 0.75rem;
    }

    .col-alert-type, .col-message, .col-severity, .col-timestamp {
        width: auto;
    }

    .btn-noted {
        padding: 0.5rem 1rem;
        font-size: 0.9rem;
    }
}

@media (max-width: 768px) {
    .alerts-table {
        display: block;
        overflow-x: auto;
        white-space: nowrap;
    }

    .alerts-table thead {
        display: table;
        width: 100%;
    }

    .alerts-table tbody {
        display: table;
        width: 100%;
    }

    .alerts-table tr {
        display: table-row;
    }

    .alerts-table th,
    .alerts-table td {
        display: table-cell;
        padding: 0.5rem;
        font-size: 0.9rem;
    }
}

@media (max-width: 576px) {
    .alerts-container {
        padding: 1rem;
    }
    
    .alerts-container h2 {
        font-size: 1.8rem;
    }
    
    .alerts-table th,
    .alerts-table td {
        font-size: 0.8rem;
    }

    .btn-noted {
        padding: 0.4rem 0.8rem;
        font-size: 0.8rem;
    }
}
</style>

<?php require_once '../includes/footer.php'; ?>