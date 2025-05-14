<?php
require_once '../config/config.php';
require_once '../includes/header.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Ensure only doctors can access
requireRole('Doctor');

// Get doctor data
$doctor_id = $_SESSION['doctor_id'] ?? getDoctorByUserId($_SESSION['user_id'])['Doctor_ID'];
$_SESSION['doctor_id'] = $doctor_id;

// Get doctor's User_ID
$stmt = $conn->prepare("SELECT User_ID FROM Doctor WHERE Doctor_ID = ?");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$doctor_user_id = $stmt->get_result()->fetch_assoc()['User_ID'];

// Get assigned patients count
$patient_count = 0;
$stmt = $conn->prepare("SELECT COUNT(*) FROM DoctorPatientMapping WHERE Doctor_ID = ?");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$patient_count = $stmt->get_result()->fetch_row()[0];

// Get pending alerts count (unread messages from patients) and latest unread message patient
$pending_alert_count = 0;
$latest_unread_patient_id = null;
$stmt = $conn->prepare("SELECT COUNT(*) as unread_count, MAX(n.Sender_ID) as sender_id
                       FROM Notifications n
                       JOIN Patient p ON n.Sender_ID = p.User_ID
                       JOIN DoctorPatientMapping dm ON p.Patient_ID = dm.Patient_ID
                       WHERE n.User_ID = ? AND n.Status = 'Sent' AND n.Alert_Type IN ('Message', 'Reply') AND dm.Doctor_ID = ?");
$stmt->bind_param("ii", $doctor_user_id, $doctor_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$pending_alert_count = $result['unread_count'];
$latest_unread_sender_id = $result['sender_id'];

// Convert Sender_ID (User_ID) to Patient_ID if there’s an unread message
if ($latest_unread_sender_id) {
    $stmt = $conn->prepare("SELECT Patient_ID FROM Patient WHERE User_ID = ?");
    $stmt->bind_param("i", $latest_unread_sender_id);
    $stmt->execute();
    $latest_unread_patient_id = $stmt->get_result()->fetch_assoc()['Patient_ID'];
}

// Get alert history count (all messages received by doctor from patients)
$alert_history_count = 0;
$stmt = $conn->prepare("SELECT COUNT(*) 
                       FROM Notifications 
                       WHERE User_ID = ? AND Alert_Type IN ('Message', 'Reply')");
$stmt->bind_param("i", $doctor_user_id);
$stmt->execute();
$alert_history_count = $stmt->get_result()->fetch_row()[0];
?>

<div class="doctor-dashboard">
    <h2>Doctor Dashboard</h2>
    
    <div class="dashboard-cards">
        <div class="dashboard-card">
            <div class="card-icon">
                <i class="fas fa-user-injured"></i>
            </div>
            <div class="card-content">
                <h3>Assigned Patients</h3>
                <p class="card-value"><?= $patient_count ?></p>
                <a href="patients.php" class="btn btn-primary">View All</a>
            </div>
        </div>
        
        <div class="dashboard-card">
            <div class="card-icon">
                <i class="fas fa-file-medical"></i>
            </div>
            <div class="card-content">
                <h3>Reports Generated</h3>
                <p class="card-value"><?= getReportCount($doctor_id) ?></p>
                <a href="reports.php" class="btn btn-primary">Generate Reports</a>
            </div>
        </div>
        
        <div class="dashboard-card">
            <div class="card-icon">
                <i class="fas fa-history"></i>
            </div>
            <div class="card-content">
                <h3>Recent Activity</h3>
                <p class="card-value">
                    <?php
                    $stmt = $conn->prepare("SELECT COUNT(*) 
                                          FROM DoctorPatientMapping dm
                                          JOIN Patient p ON dm.Patient_ID = p.Patient_ID
                                          JOIN HealthData h ON p.Patient_ID = h.Patient_ID
                                          WHERE dm.Doctor_ID = ?");
                    $stmt->bind_param("i", $doctor_id);
                    $stmt->execute();
                    $activity_count = $stmt->get_result()->fetch_row()[0];
                    echo $activity_count;
                    ?>
                </p>
                <a href="recent_activity.php" class="btn btn-primary">View Activity</a>
            </div>
        </div>

        <!-- Chat with Patient Card -->
        <div class="dashboard-card">
    <div class="card-icon position-relative">
        <i class="fas fa-comments"></i>
        <?php if ($pending_alert_count > 0): ?>
            <span class="badge"><?= $pending_alert_count ?></span>
        <?php endif; ?>
    </div>
    <div class="card-content">
        <h3>Chat with Patient</h3>
        <p>Message your patients directly</p>
        <?php if ($latest_unread_patient_id): ?>
            <a href="chat.php?patient_id=<?= $latest_unread_patient_id ?>" class="btn btn-primary">Start Chat</a>
        <?php else: ?>
            <p>No unread messages. Select a patient to chat.</p>
            <a href="patients.php" class="btn btn-primary">Select Patient</a>
        <?php endif; ?>
    </div>
</div>


        <!-- Alert History Card -->

    </div>
    
    <div class="recent-activity">
        <h3>Recent Patient Activity</h3>
        <?php
        $stmt = $conn->prepare("SELECT p.Patient_ID, u.Full_Name, MAX(h.Timestamp) as last_reading
                              FROM DoctorPatientMapping dm
                              JOIN Patient p ON dm.Patient_ID = p.Patient_ID
                              JOIN User u ON p.User_ID = u.User_ID
                              JOIN HealthData h ON p.Patient_ID = h.Patient_ID
                              WHERE dm.Doctor_ID = ?
                              GROUP BY p.Patient_ID, u.Full_Name
                              ORDER BY last_reading DESC
                              LIMIT 5");
        $stmt->bind_param("i", $doctor_id);
        $stmt->execute();
        $recent_patients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        ?>
        
        <?php if (!empty($recent_patients)): ?>
            <table class="activity-table">
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Last Reading</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_patients as $patient): ?>
                        <tr>
                            <td><?= htmlspecialchars($patient['Full_Name']) ?></td>
                            <td><?= date('M j, Y g:i A', strtotime($patient['last_reading'])) ?></td>
                            <td>
                                <a href="vitals.php?patient_id=<?= $patient['Patient_ID'] ?>" class="btn-link">View</a>
                                <a href="chat.php?patient_id=<?= $patient['Patient_ID'] ?>" class="btn-link">Chat with Patient</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No recent patient activity found.</p>
        <?php endif; ?>
    </div>
</div>

<style>
    /* Doctor Dashboard Specific Styles */
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

.dashboard-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2.5rem;
}

/* Dashboard Cards */
.dashboard-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    padding: 1.75rem;
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
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12);
    border-color: rgba(221, 161, 94, 0.2);
}

.dashboard-card:hover::before {
    height: 100%;
}

.card-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    color: white;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    margin-bottom: 1.25rem;
    position: relative;
    transition: transform 0.3s ease;
}

.dashboard-card:hover .card-icon {
    transform: rotate(10deg) scale(1.1);
}

.card-content h3 {
    color: var(--primary-dark);
    font-size: 1.3rem;
    margin-bottom: 0.75rem;
    font-weight: 600;
}

.card-value {
    font-size: 2rem;
    font-weight: 700;
    color: var(--secondary-dark);
    margin: 0.5rem 0 1.5rem;
    letter-spacing: -0.5px;
}

.card-content p {
    color: var(--dark-gray);
    font-size: 0.95rem;
    line-height: 1.5;
    margin-bottom: 1.5rem;
}

/* Buttons */
.btn-primary {
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
    margin-top: auto;
    align-self: flex-start;
}

.btn-primary:hover {
    background-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(96, 108, 56, 0.3);
}

.btn-primary i {
    margin-right: 0.5rem;
}

/* Chat Card Specific */
.dashboard-card .card-icon .badge {
    position: absolute;
    top: -8px;
    right: -8px;
    background-color: var(--secondary);
    color: white;
    border-radius: 50%;
    width: 26px;
    height: 26px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    font-weight: 700;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
    animation: pulse 1.5s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

/* Recent Activity Section */
.recent-activity {
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    padding: 2rem;
    margin-top: 2rem;
}

.recent-activity h3 {
    color: var(--primary-dark);
    font-size: 1.5rem;
    margin-bottom: 1.5rem;
    font-weight: 600;
    position: relative;
    padding-bottom: 0.75rem;
}

.recent-activity h3::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 60px;
    height: 3px;
    background: var(--secondary);
    border-radius: 2px;
}

.activity-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    margin-top: 1rem;
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
    background-color: rgba(96, 108, 56, 0.03);
}

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

/* Responsive Adjustments */
@media (max-width: 1200px) {
    .dashboard-cards {
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    }
}

@media (max-width: 992px) {
    .doctor-dashboard {
        padding: 1.5rem;
    }
    
    .recent-activity {
        padding: 1.5rem;
    }
}

@media (max-width: 768px) {
    .doctor-dashboard h2 {
        font-size: 1.9rem;
    }
    
    .recent-activity h3 {
        font-size: 1.3rem;
    }
    
    .activity-table {
        font-size: 0.9rem;
    }
    
    .activity-table th,
    .activity-table td {
        padding: 0.8rem;
    }
}

@media (max-width: 576px) {
    .doctor-dashboard {
        padding: 1rem;
    }
    
    .dashboard-cards {
        grid-template-columns: 1fr;
    }
    
    .dashboard-card {
        padding: 1.5rem;
    }
    
    .card-content h3 {
        font-size: 1.2rem;
    }
    
    .card-value {
        font-size: 1.8rem;
    }
    
    .recent-activity {
        padding: 1.25rem;
        overflow-x: auto;
    }
    
    .activity-table {
        min-width: 600px;
    }
}
</style>
<?php require_once '../includes/footer.php'; ?>