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

// Check database connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Fetch recent patient activities (more detailed than the dashboard)
$sql = "SELECT p.Patient_ID, u.Full_Name, h.Timestamp, h.Heart_Rate, h.Systolic_BP, h.Diastolic_BP, h.Temperature
        FROM DoctorPatientMapping dm
        JOIN Patient p ON dm.Patient_ID = p.Patient_ID
        JOIN User u ON p.User_ID = u.User_ID
        JOIN HealthData h ON p.Patient_ID = h.Patient_ID
        WHERE dm.Doctor_ID = ?
        ORDER BY h.Timestamp DESC
        LIMIT 20";

$stmt = $conn->prepare($sql);

if ($stmt === false) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("i", $doctor_id);

if (!$stmt->execute()) {
    die("Execute failed: " . $stmt->error);
}

$recent_activities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="patient-container">
    <div class="back-link">
        <a href="dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>
    <h2>Recent Patient Activity</h2>
    
    <?php if (!empty($recent_activities)): ?>
        <div class="table-responsive card">
            <table class="vitals-history-table">
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Timestamp</th>
                        <th>Heart Rate</th>
                        <th>Systolic BP</th>
                        <th>Diastolic BP</th>
                        <th>Temperature</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_activities as $activity): ?>
                        <tr>
                            <td><?= htmlspecialchars($activity['Full_Name']) ?></td>
                            <td><?= date('M j, Y g:i A', strtotime($activity['Timestamp'])) ?></td>
                            <td><?= htmlspecialchars($activity['Heart_Rate']) ?> bpm</td>
                            <td><?= htmlspecialchars($activity['Systolic_BP']) ?> mmHg</td>
                            <td><?= htmlspecialchars($activity['Diastolic_BP']) ?> mmHg</td>
                            <td><?= htmlspecialchars($activity['Temperature']) ?> °C</td>
                            <td>
                                <a href="vitals.php?patient_id=<?= $activity['Patient_ID'] ?>" class="btn-sm">View Details</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="no-records card">
            <i class="fas fa-info-circle"></i>
            <p>No recent patient activity found.</p>
        </div>
    <?php endif; ?>
</div>

<style>
.patient-container {
    padding: 2rem;
    max-width: 1400px;
    margin: 0 auto;
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
    box-shadow: 0 5px 15px rgba(96, 108, 56, 0.3);
}

.btn-back i {
    margin-right: 0.5rem;
}

.table-responsive {
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    padding: 2rem;
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
}

.vitals-history-table td {
    background: white;
    color: var(--dark-gray);
}

.btn-sm {
    padding: 0.5rem 1rem;
    background-color: var(--primary-dark);
    color: white;
    border-radius: 6px;
    text-decoration: none;
    display: inline-block;
    transition: all 0.3s ease;
}

.btn-sm:hover {
    background-color: var(--primary);
    transform: translateY(-2px);
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
@media (max-width: 768px) {
    .patient-container {
        padding: 1.5rem;
    }
    
    .table-responsive,
    .no-records {
        padding: 1.5rem;
    }
    
    .vitals-history-table th,
    .vitals-history-table td {
        padding: 0.75rem;
    }
}

@media (max-width: 576px) {
    .patient-container {
        padding: 1rem;
    }
    
    .patient-container h2 {
        font-size: 1.8rem;
    }
    
    .vitals-history-table {
        font-size: 0.9rem;
    }
}
</style>

<?php require_once '../includes/footer.php'; ?>