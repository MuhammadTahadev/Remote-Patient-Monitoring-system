<?php
require_once '../config/config.php';
require_once '../includes/header.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Ensure only patients can access
requireRole('Patient');

// Get patient data
$patient_id = $_SESSION['patient_id'] ?? getPatientByUserId($_SESSION['user_id'])['Patient_ID'];
$_SESSION['patient_id'] = $patient_id;
$patient_user_id = $_SESSION['user_id'];

// Verify database connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Get recent vitals
$recent_vitals = [];
$query = "SELECT * FROM HealthData WHERE Patient_ID = ? ORDER BY Timestamp DESC LIMIT 1";
$stmt = $conn->prepare($query);
if ($stmt === false) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("i", $patient_id);
if (!$stmt->execute()) {
    die("Execute failed: " . $stmt->error);
}
$recent_vitals = $stmt->get_result()->fetch_assoc();

// Get assigned doctor's User_ID
$stmt = $conn->prepare("SELECT d.User_ID 
                       FROM DoctorPatientMapping dm 
                       JOIN Doctor d ON dm.Doctor_ID = d.Doctor_ID 
                       WHERE dm.Patient_ID = ?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$doctor = $stmt->get_result()->fetch_assoc();
$doctor_user_id = $doctor['User_ID'] ?? null;

// Count unread messages from doctor
$unread_messages = 0;
if ($doctor_user_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) as unread_count 
                           FROM Notifications 
                           WHERE User_ID = ? AND Sender_ID = ? AND Status = 'Sent' 
                           AND Alert_Type IN ('Message', 'Reply')");
    $stmt->bind_param("ii", $patient_user_id, $doctor_user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $unread_messages = $result['unread_count'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-i18n="title">Patient Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* Patient Dashboard Modern Styles */
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

        .dashboard-cards {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2rem;
        }

        /* Vitals Card */
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            padding: 2rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12);
        }

        .card h3 {
            color: var(--primary-dark);
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            font-weight: 600;
        }

        .timestamp {
            color: var(--secondary-dark);
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
            display: block;
            font-style: italic;
        }

        .vitals-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 1.5rem;
        }

        .vitals-table thead th {
            background-color: var(--primary);
            color: white;
            padding: 1rem;
            text-align: left;
            font-weight: 500;
            position: sticky;
            top: 0;
        }

        .vitals-table th:first-child {
            border-top-left-radius: 8px;
        }

        .vitals-table th:last-child {
            border-top-right-radius: 8px;
        }

        .vitals-table td {
            padding: 1rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            vertical-align: middle;
        }

        .vitals-table tr:last-child td {
            border-bottom: none;
        }

        .vitals-table tr:hover td {
            background-color: rgba(96, 108, 56, 0.03);
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
            text-decoration: none;
            margin-top: 1rem;
        }

        .btn:hover {
            background-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(96, 108, 56, 0.3);
        }

        .btn i {
            margin-right: 0.5rem;
        }

        /* Quick Actions Section */
        .quick-actions {
            margin-top: 2rem;
        }

        .quick-actions h2 {
            color: var(--primary-dark);
            font-size: 1.8rem;
            margin-bottom: 1.5rem;
            font-weight: 600;
            text-align: left;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid rgba(96, 108, 56, 0.1);
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .action-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            text-decoration: none;
            color: var(--dark-gray);
            border: 1px solid rgba(0, 0, 0, 0.05);
            position: relative;
            overflow: hidden;
        }

        .action-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 0;
            background: var(--secondary);
            transition: height 0.3s ease;
        }

        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border-color: rgba(221, 161, 94, 0.2);
        }

        .action-card:hover::before {
            height: 100%;
        }

        .action-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
            position: relative;
            transition: transform 0.3s ease;
        }

        .action-card:hover .action-icon {
            transform: rotate(10deg) scale(1.1);
        }

        .action-card h3 {
            color: var(--primary-dark);
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .action-card p {
            color: var(--dark-gray);
            font-size: 0.9rem;
            line-height: 1.5;
            margin: 0;
        }

        /* Badge for unread messages */
        .badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: var(--secondary);
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 700;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        /* Language Dropdown Styling */
        .language-selector {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }

        .language-selector select {
            padding: 8px 12px;
            border-radius: 4px;
            border: 1px solid var(--primary);
            background-color: var(--white);
            color: var(--primary-dark);
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .language-selector select:hover {
            background-color: var(--light);
        }

        .language-selector select:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.2);
        }

        /* Responsive Adjustments */
        @media (max-width: 992px) {
            .patient-dashboard {
                padding: 1.5rem;
            }
            
            .actions-grid {
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .patient-dashboard h2 {
                font-size: 1.8rem;
            }
            
            .card {
                padding: 1.5rem;
            }
            
            .vitals-table {
                font-size: 0.9rem;
            }
            
            .vitals-table th,
            .vitals-table td {
                padding: 0.75rem;
            }

            .language-selector {
                top: 15px;
                right: 15px;
            }

            .language-selector select {
                font-size: 12px;
                padding: 6px 10px;
            }
        }

        @media (max-width: 576px) {
            .patient-dashboard {
                padding: 1rem;
            }
            
            .actions-grid {
                grid-template-columns: 1fr;
            }
            
            .quick-actions h2 {
                font-size: 1.5rem;
            }
            
            .card h3 {
                font-size: 1.3rem;
            }
        }
    </style>
</head>
<body>
<!-- Language Selector -->
<!-- <div class="language-selector">
    <select id="language_select" onchange="changeLanguage()">
        <option value="en">English</option>
        <option value="ur">اردو</option>
    </select>
</div> -->

<div class="patient-dashboard">
    <h2 data-i18n="dashboard_title">Patient Dashboard</h2>
    <div class="dashboard-cards">
        <div class="card">
            <h3 data-i18n="recent_vitals_title">Recent Vitals</h3>
            <?php if ($recent_vitals && !empty($recent_vitals)): ?>
                <p class="timestamp" data-i18n="last_updated">Last updated: <?= date('M j, Y g:i A', strtotime($recent_vitals['Timestamp'])) ?></p>
                <table class="vitals-table">
                    <thead>
                        <tr>
                            <th data-i18n="metric">Metric</th>
                            <th data-i18n="value">Value</th>
                            <th data-i18n="status">Status</th>
                            <th data-i18n="unit">Unit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td data-i18n="heart_rate">Heart Rate</td>
                            <td><?= $recent_vitals['Heart_Rate'] ?? '--' ?></td>
                            <td><?= getVitalBadge('heart_rate', $recent_vitals['Heart_Rate']) ?></td>
                            <td data-i18n="bpm">bpm</td>
                        </tr>
                        <tr>
                            <td data-i18n="blood_pressure">Blood Pressure</td>
                            <td><?= ($recent_vitals['Systolic_BP'] ?? '--') . '/' . ($recent_vitals['Diastolic_BP'] ?? '--') ?></td>
                            <td><?= getVitalBadge('blood_pressure', [$recent_vitals['Systolic_BP'], $recent_vitals['Diastolic_BP']]) ?></td>
                            <td data-i18n="mmhg">mmHg</td>
                        </tr>
                        <tr>
                            <td data-i18n="glucose_level">Glucose Level</td>
                            <td><?= $recent_vitals['Glucose_Level'] ?? '--' ?></td>
                            <td><?= getVitalBadge('glucose', $recent_vitals['Glucose_Level']) ?></td>
                            <td data-i18n="mg_dl">mg/dL</td>
                        </tr>
                        <tr>
                            <td data-i18n="oxygen_saturation">Oxygen Saturation</td>
                            <td><?= $recent_vitals['Oxygen_Saturation'] ?? '--' ?></td>
                            <td><?= getVitalBadge('oxygen', $recent_vitals['Oxygen_Saturation']) ?></td>
                            <td data-i18n="percent">%</td>
                        </tr>
                        <tr>
                            <td data-i18n="temperature">Temperature</td>
                            <td><?= $recent_vitals['Temperature'] ?? '--' ?></td>
                            <td><?= getVitalBadge('temperature', $recent_vitals['Temperature']) ?></td>
                            <td data-i18n="fahrenheit">°F</td>
                        </tr>
                        <tr>
                            <td data-i18n="weight">Weight</td>
                            <td><?= $recent_vitals['Weight'] ?? '--' ?></td>
                            <td><?= getVitalBadge('weight', $recent_vitals['Weight']) ?></td>
                            <td data-i18n="lbs">lbs</td>
                        </tr>
                    </tbody>
                </table>
            <?php else: ?>
                <p data-i18n="no_vitals">No vital data recorded yet.</p>
            <?php endif; ?>
            <a href="vitals.php" class="btn" data-i18n="enter_vitals">Enter New Vitals</a>
        </div>

        <div class="quick-actions">
            <h2 data-i18n="quick_actions_title">Quick Actions</h2>
            <div class="actions-grid">
                <a href="vitals.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-heartbeat"></i>
                    </div>
                    <h3 data-i18n="record_vitals_title">Record Vitals</h3>
                    <p data-i18n="record_vitals_desc">Log your latest health metrics quickly.</p>
                </a>
                <a href="vitals_history.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-history"></i>
                    </div>
                    <h3 data-i18n="vitals_history_title">Vitals History</h3>
                    <p data-i18n="vitals_history_desc">View all your previous vitals.</p>
                </a>
                <a href="chat.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-comments"></i>
                        <?php if ($unread_messages > 0): ?>
                            <span class="badge"><?= $unread_messages ?></span>
                        <?php endif; ?>
                    </div>
                    <h3 data-i18n="chat_doctor_title">Chat with Doctor</h3>
                    <p data-i18n="chat_doctor_desc">Message your doctor directly.</p>
                </a>
                <a href="History.html" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3 data-i18n="view_trends_title">View Trends</h3>
                    <p data-i18n="view_trends_desc">Analyze patterns in your health data.</p>
                </a>
                <a href="reports.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-file-medical"></i>
                    </div>
                    <h3 data-i18n="view_reports_title">View Reports</h3>
                    <p data-i18n="view_reports_desc">Access detailed health reports.</p>
                </a>
            </div>
        </div>
    </div>
</div>

<script>
const translations = {
    en: {
        title: "Patient Dashboard",
        dashboard_title: "Patient Dashboard",
        recent_vitals_title: "Recent Vitals",
        last_updated: "Last updated",
        metric: "Metric",
        value: "Value",
        status: "Status",
        unit: "Unit",
        heart_rate: "Heart Rate",
        blood_pressure: "Blood Pressure",
        glucose_level: "Glucose Level",
        oxygen_saturation: "Oxygen Saturation",
        temperature: "Temperature",
        weight: "Weight",
        bpm: "bpm",
        mmhg: "mmHg",
        mg_dl: "mg/dL",
        percent: "%",
        fahrenheit: "°F",
        lbs: "lbs",
        no_vitals: "No vital data recorded yet.",
        enter_vitals: "Enter New Vitals",
        quick_actions_title: "Quick Actions",
        record_vitals_title: "Record Vitals",
        record_vitals_desc: "Log your latest health metrics quickly.",
        vitals_history_title: "Vitals History",
        vitals_history_desc: "View all your previous vitals.",
        chat_doctor_title: "Chat with Doctor",
        chat_doctor_desc: "Message your doctor directly.",
        view_trends_title: "View Trends",
        view_trends_desc: "Analyze patterns in your health data.",
        view_reports_title: "View Reports",
        view_reports_desc: "Access detailed health reports."
    },
    ur: {
        title: "مریض ڈیش بورڈ",
        dashboard_title: "مریض ڈیش بورڈ",
        recent_vitals_title: "حالیہ وائٹلز",
        last_updated: "آخری اپ ڈیٹ",
        metric: "پیمائش",
        value: "قدر",
        status: "حالت",
        unit: "یونٹ",
        heart_rate: "دل کی دھڑکن",
        blood_pressure: "بلڈ پریشر",
        glucose_level: "گلوکوز لیول",
        oxygen_saturation: "آکسیجن سیچریشن",
        temperature: "درجہ حرارت",
        weight: "وزن",
        bpm: "بیٹس فی منٹ",
        mmhg: "ملی میٹر ایچ جی",
        mg_dl: "ملی گرام/ڈی ایل",
        percent: "%",
        fahrenheit: "°ف",
        lbs: "پاؤنڈ",
        no_vitals: "ابھی تک کوئی وائٹل ڈیٹا ریکارڈ نہیں کیا گیا۔",
        enter_vitals: "نئے وائٹلز درج کریں",
        quick_actions_title: "فوری اقدامات",
        record_vitals_title: "وائٹلز ریکارڈ کریں",
        record_vitals_desc: "اپنے تازہ ترین صحت کے پیمانوں کو تیزی سے لاگ کریں۔",
        vitals_history_title: "وائٹلز کی تاریخ",
        vitals_history_desc: "اپنے تمام پچھلے وائٹلز دیکھیں۔",
        chat_doctor_title: "ڈاکٹر کے ساتھ چیٹ کریں",
        chat_doctor_desc: "اپنے ڈاکٹر سے براہ راست پیغام کریں۔",
        view_trends_title: "رجحانات دیکھیں",
        view_trends_desc: "اپنے صحت کے ڈیٹا میں پیٹرن کا تجزیہ کریں۔",
        view_reports_title: "رپورٹس دیکھیں",
        view_reports_desc: "تفصیلی صحت رپورٹس تک رسائی حاصل کریں۔"
    }
};

// Function to change language
function changeLanguage() {
    const lang = document.getElementById('language_select').value;
    document.querySelectorAll('[data-i18n]').forEach(element => {
        const key = element.getAttribute('data-i18n');
        if (translations[lang][key]) {
            // Handle elements with dynamic content (e.g., last_updated)
            if (key === 'last_updated' && element.textContent.includes(':')) {
                const timestamp = element.textContent.split(': ')[1];
                element.textContent = `${translations[lang][key]}: ${timestamp}`;
            } else {
                element.textContent = translations[lang][key];
            }
        }
    });
}

// Initialize language on page load
document.addEventListener('DOMContentLoaded', () => {
    changeLanguage();
});
</script>

<?php require_once '../includes/footer.php'; ?>
</body>
</html>