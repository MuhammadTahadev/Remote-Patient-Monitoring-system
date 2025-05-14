<?php
require_once '../config/config.php';
require_once '../includes/header.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Ensure only doctors can access
requireRole('Doctor');

$doctor_id = $_SESSION['doctor_id'];

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
$stmt = $conn->prepare("SELECT p.Patient_ID, u.User_ID, u.Full_Name, u.EMAIL, 
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
                       ORDER BY u.Full_Name");

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

<div class="patient-container">
    <h2>My Patients</h2>
    <div class="back-link">
        <a href="dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>
    <div class="search-bar card">
        <form method="get" action="patients.php">
            <div class="2 form-group">
                <input type="text" name="search" placeholder="Search patients..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn"><i class="fas fa-search"></i></button>
            </div>
        </form>
    </div>
    
    <?php if (!empty($patients)): ?>
        <div class="table-responsive card">
            <table class="vitals-history-table">
                <thead>
                    <tr>
                        <th class="col-name">Patient Name</th>
                        <th class="col-email">Email</th>
                        <th class="col-emergency">Emergency Contact</th>
                        <th class="col-messages">Unread Messages</th>
                        <th class="col-alerts">Alerts</th>
                        <th class="col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($patients as $patient): ?>
                        <tr>
                            <td class="col-name"><?= htmlspecialchars($patient['Full_Name']) ?></td>
                            <td class="col-email"><?= htmlspecialchars($patient['EMAIL']) ?></td>
                            <td class="col-emergency"><?= htmlspecialchars($patient['Emergency_Contact_Number']) ?></td>
                            <td class="col-messages">
                                <?php if ($patient['alert_count'] > 0): ?>
                                    <span class="badge warning-badge"><?= $patient['alert_count'] ?></span>
                                <?php else: ?>
                                    <span class="badge normal-badge">0</span>
                                <?php endif; ?>
                            </td>
                            <td class="col-alerts">
                                <div class="alerts-wrapper">
                                    <?php if ($patient['notification_count'] > 0): ?>
                                        <span class="badge warning-badge"><?= $patient['notification_count'] ?></span>
                                        <a href="alerts.php?patient_id=<?= $patient['Patient_ID'] ?>" class="btn-sm alert-btn" title="Check Alerts">
                                            <i class="fas fa-exclamation-triangle"></i> Check Alerts
                                        </a>
                                    <?php else: ?>
                                        <span class="badge normal-badge">0</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="col-actions actions">
                                <div class="actions-wrapper">
                                    <a href="vitals.php?patient_id=<?= $patient['Patient_ID'] ?>" class="btn-sm action-btn" title="View Vitals">
                                        <i class="fas fa-heartbeat"></i>
                                    </a>
                                    <a href="chat.php?patient_id=<?= $patient['Patient_ID'] ?>" class="btn-sm action-btn" title="Chat with Patient">
                                        <i class="fas fa-comments"></i>
                                    </a>
                                    <a href="reports.php?patient_id=<?= $patient['Patient_ID'] ?>" class="btn-sm action-btn" title="Generate Report">
                                        <i class="fas fa-file-medical"></i>
                                    </a>
                                    <a href="health_summary.php?patient_id=<?= $patient['Patient_ID'] ?>" class="btn-sm action-btn" title="Health Summary">
                                        <i class="fas fa-chart-bar"></i>
                                    </a>
                                    <a href="progress_report.php?patient_id=<?= $patient['Patient_ID'] ?>" class="btn-sm action-btn" title="Progress Report">
                                        <i class="fas fa-chart-line"></i>
                                    </a>
                                    <a href="alert_history.php?patient_id=<?= $patient['Patient_ID'] ?>" class="btn-sm action-btn" title="Alert History">
                                        <i class="fas fa-history"></i>
                                    </a>
                                    <a href="History.html?patient_id=<?= $patient['Patient_ID'] ?>" class="btn-sm action-btn" title="View Trends">
                                        <i class="fas fa-chart-bar"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="no-records card">
            <i class="fas fa-info-circle"></i>
            <p>No patients assigned to you from your organization yet.</p>
        </div>
    <?php endif; ?>
</div>

<style>
.patient-container {
    padding: 2rem;
    max-width: 1400px;
    margin: 0 auto;
    font-family: 'Arial', sans-serif;
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

.search-bar {
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    padding: 1.5rem;
    margin-bottom: 2rem;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.search-bar:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12);
}

.search-bar .form-group {
    display: flex;
    align-items: center;
    margin: 0;
}

.search-bar input {
    flex: 1;
    padding: 0.75rem;
    border: 1px solid rgba(0, 0, 0, 0.1);
    border-radius: 8px 0 0 8px;
    font-size: 1rem;
    transition: border-color 0.3s ease;
}

.search-bar input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 5px rgba(0, 0, 0, 0.2);
}

.search-bar .btn {
    padding: 0.75rem 1rem;
    border-radius: 0 8px 8px 0;
    background-color: var(--primary-dark);
    color: white;
    border: none;
    transition: all 0.3s ease;
}

.search-bar .btn:hover {
    background-color: var(--primary);
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

.vitals-history-table {
    width: 100%;
    border-collapse: collapse;
    background: #f9fafb;
    border-radius: 8px;
    overflow: hidden;
}

.vitals-history-table th,
.vitals-history-table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.vitals-history-table th {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    color: white;
    font-weight: 600;
    white-space: nowrap;
}

.vitals-history-table td {
    background: white;
    color: var(--dark-gray);
    vertical-align: middle;
}

/* Define column widths */
.col-name {
    width: 20%;
}

.col-email {
    width: 25%;
}

.col-emergency {
    width: 15%;
}

.col-messages {
    width: 15%;
    text-align: center;
}

.col-alerts {
    width: 15%;
    text-align: center;
}

.col-actions {
    width: 20%;
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

.normal-badge {
    background-color: #e6ffe6;
    color: #2d862d;
}

.warning-badge {
    background-color: #fff3e6;
    color: #cc6600;
}

/* Alerts wrapper for badge and button */
.alerts-wrapper {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.alert-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
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

/* Actions wrapper for action buttons */
.actions-wrapper {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.5rem;
    background-color: var(--primary-dark);
    color: white;
    border-radius: 6px;
    text-decoration: none;
    transition: all 0.3s ease;
    width: 40px;
    height: 40px;
}

.action-btn:hover {
    background-color: var(--primary);
    transform: translateY(-2px);
}

.action-btn i {
    font-size: 1.2rem;
}

.btn {
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
}

.btn:hover {
    background-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
}

.btn i {
    margin-right: 0.5rem;
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
    .patient-container {
        padding: 1.5rem;
    }
    
    .table-responsive,
    .search-bar,
    .no-records {
        padding: 1.5rem;
    }
    
    .vitals-history-table th,
    .vitals-history-table td {
        padding: 0.75rem;
    }

    .col-name, .col-email, .col-emergency, .col-messages, .col-alerts, .col-actions {
        width: auto;
    }

    .alert-btn {
        padding: 0.2rem 0.4rem;
        font-size: 0.75rem;
    }

    .action-btn {
        width: 35px;
        height: 35px;
    }

    .action-btn i {
        font-size: 1rem;
    }
}

@media (max-width: 768px) {
    .search-bar .form-group {
        flex-direction: column;
    }
    
    .search-bar input {
        border-radius: 8px;
        margin-bottom: 1rem;
    }
    
    .search-bar .btn {
        border-radius: 8px;
        width: 100%;
    }

    .vitals-history-table {
        display: block;
        overflow-x: auto;
        white-space: nowrap;
    }

    .vitals-history-table thead {
        display: table;
        width: 100%;
    }

    .vitals-history-table tbody {
        display: table;
        width: 100%;
    }

    .vitals-history-table tr {
        display: table-row;
    }

    .vitals-history-table th,
    .vitals-history-table td {
        display: table-cell;
        padding: 0.5rem;
        font-size: 0.9rem;
    }

    .alerts-wrapper {
        flex-direction: row;
        gap: 0.3rem;
    }

    .actions-wrapper {
        gap: 0.3rem;
    }
}

@media (max-width: 576px) {
    .patient-container {
        padding: 1rem;
    }
    
    .patient-container h2 {
        font-size: 1.8rem;
    }
    
    .vitals-history-table th,
    .vitals-history-table td {
        font-size: 0.8rem;
    }

    .alert-btn {
        padding: 0.2rem 0.4rem;
        font-size: 0.7rem;
    }

    .action-btn {
        width: 30px;
        height: 30px;
    }

    .action-btn i {
        font-size: 0.9rem;
    }
}
</style>

<?php require_once '../includes/footer.php'; ?>