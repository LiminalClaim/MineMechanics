<?php
// report.php - Daily Mining Report
session_start();

// Supabase configuration
define('SUPABASE_URL', 'https://vrgrmqrhrwkltopjwlrr.supabase.co');
define('SUPABASE_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InZyZ3JtcXJocndrbHRvcGp3bHJyIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjUwODQxODUsImV4cCI6MjA4MDY2MDE4NX0.6cffp0njkFx1zzfp1PT5s29oNlg2WXoNH8ZsBx2qvz0');
define('SUPABASE_SECRET', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InZyZ3JtcXJocndrbHRvcGp3bHJyIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc2NTA4NDE4NSwiZXhwIjoyMDgwNjYwMTg1fQ.8VeeaMPbjUkffiHizrwJBVlLE028R2y2QOAkV9O5gXA');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['access_token'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$access_token = $_SESSION['access_token'];

// Function to make API calls to Supabase
function supabaseRequest($endpoint, $method = 'GET', $data = null, $useServiceRole = false) {
    $url = SUPABASE_URL . $endpoint;
    
    $headers = [
        'apikey: ' . SUPABASE_KEY,
        'Content-Type: application/json',
    ];
    
    if ($useServiceRole) {
        $headers[] = 'Authorization: Bearer ' . SUPABASE_SECRET;
    } elseif (isset($_SESSION['access_token'])) {
        $headers[] = 'Authorization: Bearer ' . $_SESSION['access_token'];
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'status' => $httpCode,
        'data' => json_decode($response, true)
    ];
}

// Get date range for filtering
$today = date('Y-m-d');
$defaultDays = 7; // Default to show last 7 days
$selectedDays = isset($_GET['days']) ? intval($_GET['days']) : $defaultDays;
$startDate = date('Y-m-d', strtotime("-$selectedDays days"));

// Get user profile
$profile = null;
$profileResponse = supabaseRequest('/rest/v1/profiles?id=eq.' . $user_id);
if ($profileResponse['status'] === 200 && !empty($profileResponse['data'])) {
    $profile = $profileResponse['data'][0];
}

// Get daily reports
$reports = [];
$reportsResponse = supabaseRequest('/rest/v1/daily_reports?user_id=eq.' . $user_id . '&report_date=gte.' . $startDate . '&order=report_date.desc');
if ($reportsResponse['status'] === 200) {
    $reports = $reportsResponse['data'];
}

// Get current totals from user miners and plants
$userMiners = [];
$userPlants = [];
$currentTotals = [
    'total_ths' => 0,
    'total_miners' => 0,
    'energy_used_wh' => 0,
    'energy_generated_wh' => 0,
    'energy_balance_wh' => 0
];

// Get user miners
$minersResponse = supabaseRequest('/rest/v1/user_miners?user_id=eq.' . $user_id);
if ($minersResponse['status'] === 200) {
    $userMiners = $minersResponse['data'];
    $currentTotals['total_miners'] = count($userMiners);
    $currentTotals['total_ths'] = array_sum(array_column($userMiners, 'hashpower_ths'));
    $currentTotals['energy_used_wh'] = array_sum(array_column($userMiners, 'energy_usage_wh'));
}

// Get user energy plants
$plantsResponse = supabaseRequest('/rest/v1/user_energy_plants?user_id=eq.' . $user_id . '&select=*,energy_plant_types(output_wh)');
if ($plantsResponse['status'] === 200) {
    $userPlants = $plantsResponse['data'];
    foreach ($userPlants as $plant) {
        if (isset($plant['energy_plant_types']['output_wh'])) {
            $currentTotals['energy_generated_wh'] += $plant['quantity'] * $plant['energy_plant_types']['output_wh'];
        }
    }
}

$currentTotals['energy_balance_wh'] = $currentTotals['energy_generated_wh'] - $currentTotals['energy_used_wh'];

// Check if today's report exists, if not create it
$todayReportExists = false;
foreach ($reports as $report) {
    if ($report['report_date'] === $today) {
        $todayReportExists = true;
        break;
    }
}

if (!$todayReportExists) {
    // Create today's report
    $todayReportData = [
        'user_id' => $user_id,
        'total_ths' => $currentTotals['total_ths'],
        'total_miners' => $currentTotals['total_miners'],
        'energy_generated_wh' => $currentTotals['energy_generated_wh'],
        'energy_used_wh' => $currentTotals['energy_used_wh'],
        'energy_balance_wh' => $currentTotals['energy_balance_wh'],
        'report_date' => $today
    ];
    
    $createResponse = supabaseRequest('/rest/v1/daily_reports', 'POST', $todayReportData);
    if ($createResponse['status'] === 201) {
        // Refresh reports
        $reportsResponse = supabaseRequest('/rest/v1/daily_reports?user_id=eq.' . $user_id . '&report_date=gte.' . $startDate . '&order=report_date.desc');
        if ($reportsResponse['status'] === 200) {
            $reports = $reportsResponse['data'];
        }
    }
}

// Calculate statistics
$stats = [
    'total_days' => count($reports),
    'avg_th_per_day' => 0,
    'total_m2_generated' => 0, // Placeholder - you'll need to add m² calculation logic
    'peak_hashrate' => 0,
    'most_efficient_day' => null,
    'total_energy_generated' => 0,
    'total_energy_used' => 0
];

if (!empty($reports)) {
    $hashrates = array_column($reports, 'total_ths');
    $stats['avg_th_per_day'] = array_sum($hashrates) / count($hashrates);
    $stats['peak_hashrate'] = max($hashrates);
    
    // Find most efficient day (highest energy balance)
    $maxEfficiency = -999999;
    foreach ($reports as $report) {
        if ($report['energy_balance_wh'] > $maxEfficiency) {
            $maxEfficiency = $report['energy_balance_wh'];
            $stats['most_efficient_day'] = $report['report_date'];
        }
        $stats['total_energy_generated'] += $report['energy_generated_wh'];
        $stats['total_energy_used'] += $report['energy_used_wh'];
    }
    
    // Calculate estimated m² generated (simplified formula)
    // In reality, you'd need to calculate based on actual mining algorithm
    // This is a placeholder - replace with your actual m² calculation
    foreach ($reports as $report) {
        $stats['total_m2_generated'] += $report['total_ths'] * 0.001; // Example: 0.001 m² per TH/s per day
    }
}

// Handle report download
if (isset($_GET['download']) && $_GET['download'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="mining_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // CSV header
    fputcsv($output, [
        'Date', 
        'Total Hashrate (TH/s)', 
        'Total Miners', 
        'Energy Generated (W/h)', 
        'Energy Used (W/h)', 
        'Energy Balance (W/h)',
        'Estimated m² Generated'
    ]);
    
    // CSV data
    foreach ($reports as $report) {
        $estimated_m2 = $report['total_ths'] * 0.001; // Example calculation
        fputcsv($output, [
            $report['report_date'],
            number_format($report['total_ths'], 2),
            $report['total_miners'],
            number_format($report['energy_generated_wh'], 0),
            number_format($report['energy_used_wh'], 0),
            number_format($report['energy_balance_wh'], 0),
            number_format($estimated_m2, 4)
        ]);
    }
    
    fclose($output);
    exit();
}

// Get next report reset time (12:00 UTC daily)
$now = new DateTime('now', new DateTimeZone('UTC'));
$resetTime = new DateTime('today 12:00', new DateTimeZone('UTC'));

if ($now > $resetTime) {
    $resetTime->modify('+1 day');
}

$timeUntilReset = $now->diff($resetTime);
$hoursUntilReset = $timeUntilReset->h;
$minutesUntilReset = $timeUntilReset->i;
$secondsUntilReset = $timeUntilReset->s;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Mining Report - MineMechanics</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://unpkg.com/phosphor-icons@1.4.2/src/css/icons.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
  --bg-dark: #000000;
  --bg-darker: #0a0a0a;
  --bg-card: rgba(30, 30, 30, 0.7);
  --bg-sidebar: rgba(20, 20, 20, 0.95);
  --text-primary: #ffffff;
  --text-secondary: rgba(255, 255, 255, 0.8);
  --text-muted: rgba(255, 255, 255, 0.6);
  --gradient-violet: linear-gradient(135deg, #8B5CF6 0%, #A78BFA 100%);
  --gradient-orange: linear-gradient(135deg, #F97316 0%, #FB923C 100%);
  --gradient-green: linear-gradient(135deg, #10B981 0%, #34D399 100%);
  --gradient-blue: linear-gradient(135deg, #3B82F6 0%, #60A5FA 100%);
  --gradient-red: linear-gradient(135deg, #EF4444 0%, #F87171 100%);
  --violet: #8B5CF6;
  --orange: #F97316;
  --green: #10B981;
  --blue: #3B82F6;
  --red: #EF4444;
}

* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: 'Inter', sans-serif;
  background-color: var(--bg-dark);
  color: var(--text-primary);
  min-height: 100vh;
}

/* Sidebar (same as dashboard) */
.sidebar {
  width: 260px;
  background: var(--bg-sidebar);
  backdrop-filter: blur(10px);
  border-right: 1px solid rgba(255, 255, 255, 0.1);
  padding: 30px 0;
  position: fixed;
  height: 100vh;
  overflow-y: auto;
  z-index: 100;
}

.logo {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  font-size: 24px;
  font-weight: 800;
  margin-bottom: 40px;
  background: var(--gradient-violet);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  text-transform: uppercase;
  padding: 0 25px;
}

.nav-menu {
  padding: 0 15px;
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 15px;
  padding: 15px 20px;
  color: var(--text-muted);
  text-decoration: none;
  border-radius: 12px;
  margin-bottom: 8px;
  transition: all 0.3s ease;
  font-weight: 500;
}

.nav-item:hover {
  background: rgba(139, 92, 246, 0.1);
  color: var(--text-primary);
}

.nav-item.active {
  background: rgba(139, 92, 246, 0.15);
  color: var(--violet);
  border-left: 3px solid var(--violet);
}

/* Main Content */
.main-content {
  margin-left: 260px;
  padding: 40px;
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 40px;
  padding-bottom: 20px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.welcome-message h1 {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 36px;
  font-weight: 700;
  margin-bottom: 8px;
  background: var(--gradient-green);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

/* Report Controls */
.report-controls {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
  gap: 20px;
  flex-wrap: wrap;
}

.date-filter {
  display: flex;
  gap: 10px;
  align-items: center;
}

.date-btn {
  padding: 10px 20px;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 10px;
  color: var(--text-primary);
  text-decoration: none;
  font-size: 14px;
  transition: all 0.3s ease;
}

.date-btn:hover, .date-btn.active {
  background: var(--gradient-violet);
  border-color: var(--violet);
}

.download-btn {
  padding: 12px 24px;
  background: var(--gradient-green);
  color: white;
  border: none;
  border-radius: 10px;
  font-family: 'Space Grotesk', sans-serif;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.download-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 20px rgba(16, 185, 129, 0.3);
}

/* Reset Timer */
.reset-timer {
  background: rgba(59, 130, 246, 0.1);
  border: 1px solid rgba(59, 130, 246, 0.2);
  border-radius: 12px;
  padding: 15px 20px;
  margin-bottom: 30px;
  display: flex;
  align-items: center;
  gap: 15px;
}

.timer-icon {
  font-size: 24px;
  color: var(--blue);
}

.timer-content h4 {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 16px;
  font-weight: 600;
  margin-bottom: 5px;
}

.timer-content p {
  color: var(--text-muted);
  font-size: 14px;
}

.timer-display {
  font-size: 24px;
  font-weight: 700;
  color: var(--blue);
  font-family: monospace;
}

/* Stats Grid */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 20px;
  margin-bottom: 40px;
}

.stat-card {
  background: var(--bg-card);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  padding: 25px;
  text-align: center;
}

.stat-icon {
  font-size: 32px;
  margin-bottom: 15px;
  color: var(--green);
}

.stat-value {
  font-size: 32px;
  font-weight: 800;
  font-family: 'Space Grotesk', sans-serif;
  margin-bottom: 5px;
  background: var(--gradient-green);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.stat-label {
  color: var(--text-muted);
  font-size: 14px;
}

/* Report Table */
.report-table-container {
  background: var(--bg-card);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  overflow: hidden;
  margin-bottom: 40px;
}

.report-table {
  width: 100%;
  border-collapse: collapse;
}

.report-table thead {
  background: rgba(0, 0, 0, 0.3);
}

.report-table th {
  padding: 18px 20px;
  text-align: left;
  font-family: 'Space Grotesk', sans-serif;
  font-weight: 600;
  color: var(--text-secondary);
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.report-table td {
  padding: 16px 20px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.report-table tbody tr:hover {
  background: rgba(255, 255, 255, 0.03);
}

.report-table tbody tr:last-child td {
  border-bottom: none;
}

.date-cell {
  font-family: 'Space Grotesk', sans-serif;
  font-weight: 600;
}

.value-cell {
  text-align: right;
  font-family: monospace;
}

.positive {
  color: var(--green);
}

.negative {
  color: var(--red);
}

/* Charts Section */
.charts-section {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
  gap: 25px;
  margin-bottom: 40px;
}

.chart-container {
  background: var(--bg-card);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  padding: 25px;
}

.chart-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.chart-title {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 18px;
  font-weight: 600;
}

.chart-placeholder {
  height: 200px;
  background: rgba(255, 255, 255, 0.02);
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-muted);
  font-size: 14px;
}

/* Empty State */
.empty-state {
  text-align: center;
  padding: 60px 20px;
  color: var(--text-muted);
}

.empty-state i {
  font-size: 64px;
  margin-bottom: 20px;
  opacity: 0.5;
}

.empty-state h3 {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 20px;
  margin-bottom: 10px;
  color: var(--text-secondary);
}

/* Responsive */
@media (max-width: 1024px) {
  .sidebar {
    width: 220px;
  }
  
  .main-content {
    margin-left: 220px;
  }
  
  .charts-section {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .sidebar {
    display: none;
  }
  
  .main-content {
    margin-left: 0;
    padding: 20px;
  }
  
  .header {
    flex-direction: column;
    align-items: flex-start;
    gap: 15px;
  }
  
  .report-controls {
    flex-direction: column;
    align-items: stretch;
  }
  
  .report-table {
    display: block;
    overflow-x: auto;
  }
  
  .stats-grid {
    grid-template-columns: 1fr;
  }
}
</style>
</head>
<body>
<!-- Sidebar -->
<div class="sidebar">
  <div class="logo">
    <i class="ph-lightning-fill"></i>
    MineMechanics
  </div>
  
  <div class="nav-menu">
    <a href="dashboard.php" class="nav-item">
      <i class="ph-gauge"></i> Dashboard
    </a>
    <a href="topup.php" class="nav-item">
      <i class="ph-credit-card"></i> Top Up
    </a>
    <a href="wallet.php" class="nav-item">
      <i class="ph-wallet"></i> Wallet
    </a>
    <a href="redeem.php" class="nav-item">
      <i class="ph-money"></i> Redeem
    </a>
    <a href="buy-miners.php" class="nav-item">
      <i class="ph-cpu"></i> Buy Miners
    </a>
    <a href="buy-plants.php" class="nav-item">
      <i class="ph-leaf"></i> Buy Plants
    </a>
    <a href="report.php" class="nav-item active">
      <i class="ph-chart-bar"></i> Report
    </a>
    <a href="gift.php" class="nav-item">
      <i class="ph-gift"></i> Gift
    </a>
    
  </div>
</div>

<!-- Main Content -->
<div class="main-content">
  <div class="header">
    <div class="welcome-message">
      <h1>Mining Report</h1>
      <p>Detailed analysis of your mining operations</p>
    </div>
    <div>
      <span style="color: var(--text-muted); font-size: 14px;">User: <?php echo htmlspecialchars($profile['username'] ?? 'User'); ?></span>
    </div>
  </div>
  
  <!-- Report Controls -->
  <div class="report-controls">
    <div class="date-filter">
      <a href="?days=1" class="date-btn <?php echo $selectedDays == 1 ? 'active' : ''; ?>">Today</a>
      <a href="?days=7" class="date-btn <?php echo $selectedDays == 7 ? 'active' : ''; ?>">7 Days</a>
      <a href="?days=30" class="date-btn <?php echo $selectedDays == 30 ? 'active' : ''; ?>">30 Days</a>
      <a href="?days=90" class="date-btn <?php echo $selectedDays == 90 ? 'active' : ''; ?>">90 Days</a>
    </div>
    
    <a href="?download=csv&days=<?php echo $selectedDays; ?>" class="download-btn">
      <i class="ph-download"></i> Download CSV Report
    </a>
  </div>
  
  <!-- Reset Timer -->
  <div class="reset-timer">
    <div class="timer-icon">
      <i class="ph-clock-countdown"></i>
    </div>
    <div class="timer-content">
      <h4>Next Report Reset</h4>
      <p>Daily reports are generated at 12:00 UTC</p>
    </div>
    <div class="timer-display" id="countdownTimer">
      <?php printf("%02d:%02d:%02d", $hoursUntilReset, $minutesUntilReset, $secondsUntilReset); ?>
    </div>
  </div>
  
  <!-- Statistics -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-icon">
        <i class="ph-chart-line-up"></i>
      </div>
      <div class="stat-value"><?php echo number_format($stats['total_days']); ?></div>
      <div class="stat-label">Days Tracked</div>
    </div>
    
    <div class="stat-card">
      <div class="stat-icon">
        <i class="ph-cpu"></i>
      </div>
      <div class="stat-value"><?php echo number_format($stats['avg_th_per_day'], 2); ?></div>
      <div class="stat-label">Avg TH/s per Day</div>
    </div>
    
    <div class="stat-card">
      <div class="stat-icon">
        <i class="ph-lightning"></i>
      </div>
      <div class="stat-value"><?php echo number_format($stats['total_energy_generated'] / 1000, 1); ?>k</div>
      <div class="stat-label">Total Energy Generated (kW/h)</div>
    </div>
    
    <div class="stat-card">
      <div class="stat-icon">
        <i class="ph-coins"></i>
      </div>
      <div class="stat-value"><?php echo number_format($stats['total_m2_generated'], 2); ?></div>
      <div class="stat-label">Total m² Generated</div>
    </div>
  </div>
  
  <!-- Report Table -->
  <div class="report-table-container">
    <?php if (!empty($reports)): ?>
      <table class="report-table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Total Hashrate</th>
            <th>Miners</th>
            <th>Energy Generated</th>
            <th>Energy Used</th>
            <th>Energy Balance</th>
            <th>Estimated m²</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($reports as $report): 
            $estimated_m2 = $report['total_ths'] * 0.001; // Example calculation
          ?>
            <tr>
              <td class="date-cell">
                <?php 
                  $date = new DateTime($report['report_date']);
                  echo $date->format('M d, Y');
                  if ($report['report_date'] === $today) {
                    echo ' <span style="color: var(--green); font-size: 12px;">(Today)</span>';
                  }
                ?>
              </td>
              <td class="value-cell"><?php echo number_format($report['total_ths'], 2); ?> TH/s</td>
              <td class="value-cell"><?php echo $report['total_miners']; ?></td>
              <td class="value-cell"><?php echo number_format($report['energy_generated_wh'], 0); ?> W/h</td>
              <td class="value-cell"><?php echo number_format($report['energy_used_wh'], 0); ?> W/h</td>
              <td class="value-cell <?php echo $report['energy_balance_wh'] >= 0 ? 'positive' : 'negative'; ?>">
                <?php echo number_format($report['energy_balance_wh'], 0); ?> W/h
              </td>
              <td class="value-cell"><?php echo number_format($estimated_m2, 4); ?> m²</td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="empty-state">
        <i class="ph-chart-line"></i>
        <h3>No Reports Yet</h3>
        <p>Start mining to generate your first daily report</p>
        <a href="buy-miners.php" class="download-btn" style="margin-top: 20px;">
          <i class="ph-cpu"></i> Buy Your First Miner
        </a>
      </div>
    <?php endif; ?>
  </div>
  
  <!-- Charts Section -->
  <?php if (!empty($reports)): ?>
  <div class="charts-section">
    <div class="chart-container">
      <div class="chart-header">
        <div class="chart-title">Hashrate Trend</div>
        <span style="color: var(--text-muted); font-size: 14px;">Last <?php echo $selectedDays; ?> days</span>
      </div>
      <div class="chart-placeholder">
        <i class="ph-chart-line" style="font-size: 48px;"></i>
        <div style="margin-left: 15px; text-align: left;">
          <div>Peak: <?php echo number_format($stats['peak_hashrate'], 2); ?> TH/s</div>
          <div>Average: <?php echo number_format($stats['avg_th_per_day'], 2); ?> TH/s</div>
        </div>
      </div>
    </div>
    
    <div class="chart-container">
      <div class="chart-header">
        <div class="chart-title">Energy Balance</div>
        <span style="color: var(--text-muted); font-size: 14px;">Most efficient: <?php echo $stats['most_efficient_day'] ? date('M d', strtotime($stats['most_efficient_day'])) : 'N/A'; ?></span>
      </div>
      <div class="chart-placeholder">
        <i class="ph-lightning" style="font-size: 48px;"></i>
        <div style="margin-left: 15px; text-align: left;">
          <div>Generated: <?php echo number_format($stats['total_energy_generated'] / 1000, 1); ?> kW/h</div>
          <div>Used: <?php echo number_format($stats['total_energy_used'] / 1000, 1); ?> kW/h</div>
          <div>Net: <?php echo number_format(($stats['total_energy_generated'] - $stats['total_energy_used']) / 1000, 1); ?> kW/h</div>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>
  
  <!-- Current Status -->
  <div class="report-table-container">
    <div style="padding: 25px;">
      <h3 style="font-family: 'Space Grotesk', sans-serif; margin-bottom: 20px; color: var(--text-primary);">Current Mining Status</h3>
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
        <div>
          <div style="color: var(--text-muted); font-size: 14px; margin-bottom: 5px;">Active Hashrate</div>
          <div style="font-size: 24px; font-weight: 700; color: var(--green);"><?php echo number_format($currentTotals['total_ths'], 2); ?> TH/s</div>
        </div>
        <div>
          <div style="color: var(--text-muted); font-size: 14px; margin-bottom: 5px;">Active Miners</div>
          <div style="font-size: 24px; font-weight: 700; color: var(--violet);"><?php echo $currentTotals['total_miners']; ?></div>
        </div>
        <div>
          <div style="color: var(--text-muted); font-size: 14px; margin-bottom: 5px;">Energy Generated</div>
          <div style="font-size: 24px; font-weight: 700; color: var(--blue);"><?php echo number_format($currentTotals['energy_generated_wh'], 0); ?> W/h</div>
        </div>
        <div>
          <div style="color: var(--text-muted); font-size: 14px; margin-bottom: 5px;">Energy Balance</div>
          <div style="font-size: 24px; font-weight: 700; color: <?php echo $currentTotals['energy_balance_wh'] >= 0 ? 'var(--green)' : 'var(--red)'; ?>">
            <?php echo number_format($currentTotals['energy_balance_wh'], 0); ?> W/h
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Countdown timer
function updateCountdown() {
  const now = new Date();
  const utcHours = now.getUTCHours();
  const utcMinutes = now.getUTCMinutes();
  const utcSeconds = now.getUTCSeconds();
  
  // Calculate seconds until 12:00 UTC
  let hoursUntil = 11 - utcHours;
  let minutesUntil = 59 - utcMinutes;
  let secondsUntil = 59 - utcSeconds;
  
  // Adjust if we're past 12:00 UTC
  if (utcHours >= 12) {
    hoursUntil = 35 - utcHours; // 24 + 11
  }
  
  // Handle negative values
  if (secondsUntil < 0) {
    secondsUntil += 60;
    minutesUntil--;
  }
  
  if (minutesUntil < 0) {
    minutesUntil += 60;
    hoursUntil--;
  }
  
  if (hoursUntil < 0) {
    hoursUntil += 24;
  }
  
  // Format and display
  const hoursStr = hoursUntil.toString().padStart(2, '0');
  const minutesStr = minutesUntil.toString().padStart(2, '0');
  const secondsStr = secondsUntil.toString().padStart(2, '0');
  
  document.getElementById('countdownTimer').textContent = `${hoursStr}:${minutesStr}:${secondsStr}`;
}

// Update countdown every second
setInterval(updateCountdown, 1000);
updateCountdown(); // Initial call

// Auto-refresh page at 12:00 UTC
function scheduleRefresh() {
  const now = new Date();
  const utcHours = now.getUTCHours();
  const utcMinutes = now.getUTCMinutes();
  
  let msUntilRefresh;
  
  if (utcHours < 12 || (utcHours === 12 && utcMinutes === 0)) {
    // Refresh at 12:00 UTC today
    const refreshTime = new Date(now);
    refreshTime.setUTCHours(12, 0, 0, 0);
    msUntilRefresh = refreshTime - now;
  } else {
    // Refresh at 12:00 UTC tomorrow
    const refreshTime = new Date(now);
    refreshTime.setUTCDate(refreshTime.getUTCDate() + 1);
    refreshTime.setUTCHours(12, 0, 0, 0);
    msUntilRefresh = refreshTime - now;
  }
  
  setTimeout(() => {
    window.location.reload();
  }, msUntilRefresh);
}

// Schedule refresh
scheduleRefresh();

// Add table sorting functionality
document.querySelectorAll('.report-table th').forEach((th, index) => {
  th.style.cursor = 'pointer';
  th.addEventListener('click', () => {
    sortTable(index);
  });
});

let sortDirection = true; // true = ascending, false = descending

function sortTable(columnIndex) {
  const table = document.querySelector('.report-table');
  const tbody = table.querySelector('tbody');
  const rows = Array.from(tbody.querySelectorAll('tr'));
  
  rows.sort((a, b) => {
    const aText = a.cells[columnIndex].textContent;
    const bText = b.cells[columnIndex].textContent;
    
    // Try to parse as number first
    const aNum = parseFloat(aText.replace(/[^\d.-]/g, ''));
    const bNum = parseFloat(bText.replace(/[^\d.-]/g, ''));
    
    if (!isNaN(aNum) && !isNaN(bNum)) {
      return sortDirection ? aNum - bNum : bNum - aNum;
    }
    
    // Fall back to string comparison
    return sortDirection 
      ? aText.localeCompare(bText)
      : bText.localeCompare(aText);
  });
  
  // Clear and re-add rows
  rows.forEach(row => tbody.appendChild(row));
  
  // Toggle sort direction
  sortDirection = !sortDirection;
}

// Add animations
document.addEventListener('DOMContentLoaded', function() {
  const cards = document.querySelectorAll('.stat-card, .chart-container, .report-table-container');
  cards.forEach((card, index) => {
    card.style.opacity = '0';
    card.style.transform = 'translateY(20px)';
    card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
    
    setTimeout(() => {
      card.style.opacity = '1';
      card.style.transform = 'translateY(0)';
    }, index * 100);
  });
});

// Responsive sidebar toggle for mobile
if (window.innerWidth <= 768) {
  const sidebar = document.querySelector('.sidebar');
  const mainContent = document.querySelector('.main-content');
  
  // Create mobile menu button
  const menuBtn = document.createElement('button');
  menuBtn.innerHTML = '<i class="ph-list"></i>';
  menuBtn.style.cssText = `
    position: fixed;
    top: 20px;
    left: 20px;
    z-index: 1000;
    background: var(--violet);
    color: white;
    border: none;
    width: 50px;
    height: 50px;
    border-radius: 12px;
    font-size: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
  `;
  
  menuBtn.onclick = function() {
    sidebar.style.display = sidebar.style.display === 'block' ? 'none' : 'block';
  };
  
  document.body.appendChild(menuBtn);
  
  // Close sidebar when clicking outside on mobile
  document.addEventListener('click', function(e) {
    if (window.innerWidth <= 768 && 
        !sidebar.contains(e.target) && 
        e.target !== menuBtn && 
        !menuBtn.contains(e.target)) {
      sidebar.style.display = 'none';
    }
  });
}
</script>
</body>
</html>
      