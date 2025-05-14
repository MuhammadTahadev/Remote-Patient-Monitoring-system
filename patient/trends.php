<?php
require_once '../config/config.php';
require_once '../includes/header.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Ensure only patients can access
requireRole('Patient');

$patient_id = $_SESSION['patient_id'];

// Get vitals data for charts
$vitals_data = [];
$stmt = $conn->prepare("SELECT 
                        DATE(Timestamp) as date,
                        AVG(Heart_Rate) as avg_heart_rate,
                        AVG(Systolic_BP) as avg_systolic,
                        AVG(Diastolic_BP) as avg_diastolic,
                        AVG(Glucose_Level) as avg_glucose,
                        AVG(Oxygen_Saturation) as avg_oxygen
                        FROM HealthData 
                        WHERE Patient_ID = ? 
                        AND Timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                        GROUP BY DATE(Timestamp)
                        ORDER BY date ASC");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$vitals_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Prepare data for JavaScript
$chart_data = [
    'labels' => [],
    'heart_rate' => [],
    'systolic' => [],
    'diastolic' => [],
    'glucose' => [],
    'oxygen' => []
];

foreach ($vitals_data as $row) {
    $chart_data['labels'][] = date('M j', strtotime($row['date']));
    $chart_data['heart_rate'][] = $row['avg_heart_rate'];
    $chart_data['systolic'][] = $row['avg_systolic'];
    $chart_data['diastolic'][] = $row['avg_diastolic'];
    $chart_data['glucose'][] = $row['avg_glucose'];
    $chart_data['oxygen'][] = $row['avg_oxygen'];
}
?>

<div class="patient-trends">
    <h2>Health Trends</h2>
    
    <div class="trends-filters">
        <form id="trendsFilterForm">
            <div class="form-group">
                <label for="time_period">Time Period:</label>
                <select id="time_period" name="time_period">
                    <option value="7">Last 7 Days</option>
                    <option value="30" selected>Last 30 Days</option>
                    <option value="90">Last 90 Days</option>
                    <option value="365">Last Year</option>
                </select>
            </div>
            <div class="form-group">
                <label for="metric">Metric:</label>
                <select id="metric" name="metric">
                    <option value="all">All Metrics</option>
                    <option value="heart_rate">Heart Rate</option>
                    <option value="blood_pressure">Blood Pressure</option>
                    <option value="glucose">Glucose</option>
                    <option value="oxygen">Oxygen</option>
                </select>
            </div>
            <button type="button" id="applyFilters" class="btn">Apply</button>
        </form>
    </div>
    
    <div class="trends-charts">
        <div class="chart-container">
            <h3>Heart Rate Trend</h3>
            <canvas id="heartRateChart"></canvas>
        </div>
        
        <div class="chart-container">
            <h3>Blood Pressure Trend</h3>
            <canvas id="bloodPressureChart"></canvas>
        </div>
        
        <div class="chart-container">
            <h3>Glucose Level Trend</h3>
            <canvas id="glucoseChart"></canvas>
        </div>
        
        <div class="chart-container">
            <h3>Oxygen Saturation Trend</h3>
            <canvas id="oxygenChart"></canvas>
        </div>
    </div>
</div>

<script>
    // Pass PHP data to JavaScript
    var chartData = <?= json_encode($chart_data) ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="../assets/js/trends.js"></script>
<?php require_once '../includes/footer.php'; ?>