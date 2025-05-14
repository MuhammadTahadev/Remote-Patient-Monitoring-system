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

// Validate that the patient is assigned to this doctor
$stmt = $conn->prepare("SELECT p.Patient_ID, u.Full_Name 
                       FROM DoctorPatientMapping dm
                       JOIN Patient p ON dm.Patient_ID = p.Patient_ID
                       JOIN User u ON p.User_ID = u.User_ID
                       WHERE dm.Doctor_ID = ? AND dm.Patient_ID = ?");
if ($stmt === false) {
    die("Prepare failed (validate patient): " . $conn->error);
}
$stmt->bind_param("ii", $doctor_id, $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$patient) {
    setAlert("Patient not found or not assigned to you.", "error");
    header("Location: patients.php");
    exit();
}

// Fetch all alerts for this patient and doctor
$stmt = $conn->prepare("SELECT Alert_Type, Message, Severity, Timestamp, Status 
                       FROM Alerts 
                       WHERE Doctor_ID = ? AND Patient_ID = ? 
                       ORDER BY Timestamp DESC");
if ($stmt === false) {
    die("Prepare failed (fetch alerts): " . $conn->error);
}
$stmt->bind_param("ii", $doctor_id, $patient_id);
$stmt->execute();
$alerts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Display any alerts (e.g., error messages)
displayAlert();
?>

<div class="alert-history-container">
    <h2>Alert History for <?= htmlspecialchars($patient['Full_Name']) ?></h2>
    <div class="back-link">
        <a href="patients.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Patients</a>
    </div>
    
    <?php if (!empty($alerts)): ?>
        <div class="table-responsive card">
            <table class="alert-history-table">
                <thead>
                    <tr>
                        <th class="col-alert-type">Alert Type</th>
                        <th class="col-message">Message</th>
                        <th class="col-severity">Severity</th>
                        <th class="col-timestamp">Timestamp</th>
                        <th class="col-status">Status</th>
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
                            <td class="col-status">
                                <span class="badge status-<?= strtolower($alert['Status']) ?>">
                                    <?= htmlspecialchars($alert['Status']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="no-records card">
            <i class="fas fa-info-circle"></i>
            <p>No alerts found for this patient.</p>
        </div>
    <?php endif; ?>
</div>

<style>
.alert-history-container {
    padding: 2rem;
    max-width: 1400px;
    margin: 0 auto;
    font-family: 'Arial', sans-serif;
}

.alert-history-container h2 {
    color: var(--primary-dark);
    font-size: 2.2rem;
    margin-bottom: 1.5rem;
    font-weight: 700;
    text-align: center;
    position: relative;
    padding-bottom: 1rem;
}

.alert-history-container h2::after {
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

.alert-history-table {
    width: 100%;
    border-collapse: collapse;
    background: #f9fafb;
    border-radius: 8px;
    overflow: hidden;
}

.alert-history-table th,
.alert-history-table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    vertical-align: middle;
}

.alert-history-table th {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    color: white;
    font-weight: 600;
    white-space: nowrap;
}

.alert-history-table td {
    background: white;
    color: var(--dark-gray);
}

/* Define column widths */
.col-alert-type {
    width: 20%;
}

.col-message {
    width: 35%;
}

.col-severity {
    width: 15%;
    text-align: center;
}

.col-timestamp {
    width: 20%;
}

.col-status {
    width: 10%;
    text-align: center;
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

.status-unread {
    background-color: #fff3e6;
    color: #cc6600;
}

.status-read {
    background-color: #e6ffe6;
    color: #2d862d;
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
    .alert-history-container {
        padding: 1.5rem;
    }
    
    .table-responsive,
    .no-records {
        padding: 1.5rem;
    }
    
    .alert-history-table th,
    .alert-history-table td {
        padding: 0.75rem;
    }

    .col-alert-type, .col-message, .col-severity, .col-timestamp, .col-status {
        width: auto;
    }
}

@media (max-width: 768px) {
    .alert-history-table {
        display: block;
        overflow-x: auto;
        white-space: nowrap;
    }

    .alert-history-table thead {
        display: table;
        width: 100%;
    }

    .alert-history-table tbody {
        display: table;
        width: 100%;
    }

    .alert-history-table tr {
        display: table-row;
    }

    .alert-history-table th,
    .alert-history-table td {
        display: table-cell;
        padding: 0.5rem;
        font-size: 0.9rem;
    }
}

@media (max-width: 576px) {
    .alert-history-container {
        padding: 1rem;
    }
    
    .alert-history-container h2 {
        font-size: 1.8rem;
    }
    
    .alert-history-table th,
    .alert-history-table td {
        font-size: 0.8rem;
    }
}
</style>

<?php require_once '../includes/footer.php'; ?>