<?php
// dashboard.php - User Dashboard
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

// Get user data
$profile = null;
$balance = null;
$dailyReport = null;
$userMiners = [];
$userPlants = [];
$recentActivity = [];

try {
    // Get user profile
    $profileResponse = supabaseRequest('/rest/v1/profiles?id=eq.' . $user_id);
    if ($profileResponse['status'] === 200 && !empty($profileResponse['data'])) {
        $profile = $profileResponse['data'][0];
    }
    
    // Get user balance
    $balanceResponse = supabaseRequest('/rest/v1/balances?user_id=eq.' . $user_id);
    if ($balanceResponse['status'] === 200 && !empty($balanceResponse['data'])) {
        $balance = $balanceResponse['data'][0];
    } else {
        // Create balance if it doesn't exist
        $balance = ['minem' => 0, 'm2' => 0, 'usd_equivalent' => 0];
    }
    
    // Get today's daily report
    $today = date('Y-m-d');
    $reportResponse = supabaseRequest('/rest/v1/daily_reports?user_id=eq.' . $user_id . '&report_date=eq.' . $today);
    if ($reportResponse['status'] === 200 && !empty($reportResponse['data'])) {
        $dailyReport = $reportResponse['data'][0];
    } else {
        // Create daily report if it doesn't exist
        $dailyReport = [
            'total_ths' => 0,
            'total_miners' => 0,
            'energy_generated_wh' => 0,
            'energy_used_wh' => 0,
            'energy_balance_wh' => 0
        ];
    }
    
    // Get user miners
    $minersResponse = supabaseRequest('/rest/v1/user_miners?user_id=eq.' . $user_id . '&select=*,miner_types(name)');
    if ($minersResponse['status'] === 200) {
        $userMiners = $minersResponse['data'];
    }
    
    // Get user energy plants
    $plantsResponse = supabaseRequest('/rest/v1/user_energy_plants?user_id=eq.' . $user_id . '&select=*,energy_plant_types(name,category,output_wh)');
    if ($plantsResponse['status'] === 200) {
        $userPlants = $plantsResponse['data'];
    }
    
    // Calculate totals
    $totalMiners = count($userMiners);
    $totalPlants = count($userPlants);
    $totalHashrate = array_sum(array_column($userMiners, 'hashpower_ths'));
    $totalEnergyGenerated = 0;
    foreach ($userPlants as $plant) {
        if (isset($plant['energy_plant_types'])) {
            $totalEnergyGenerated += $plant['quantity'] * $plant['energy_plant_types']['output_wh'];
        }
    }
    $totalEnergyUsed = array_sum(array_column($userMiners, 'energy_usage_wh'));
    $energyBalance = $totalEnergyGenerated - $totalEnergyUsed;
    
} catch (Exception $e) {
    $error = "Error loading dashboard: " . $e->getMessage();
}

// Logout functionality
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Dashboard - MineMechanics</title>
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
  --error: #EF4444;
  --success: #10B981;
  --info: #3B82F6;
  --warning: #F59E0B;
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
  display: flex;
}

/* Sidebar */
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

.logo-icon {
  font-size: 28px;
}

.user-info {
  padding: 20px 25px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  margin-bottom: 30px;
}

.user-avatar {
  width: 60px;
  height: 60px;
  background: var(--gradient-violet);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  font-weight: 700;
  margin-bottom: 15px;
}

.user-name {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 18px;
  font-weight: 600;
  margin-bottom: 5px;
}

.user-email {
  color: var(--text-muted);
  font-size: 14px;
  margin-bottom: 10px;
}

.user-location {
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--violet);
  font-size: 14px;
  font-weight: 500;
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

.nav-item i {
  font-size: 20px;
  width: 24px;
  text-align: center;
}

.nav-divider {
  height: 1px;
  background: rgba(255, 255, 255, 0.1);
  margin: 25px 20px;
}

.logout-btn {
  display: flex;
  align-items: center;
  gap: 15px;
  padding: 15px 20px;
  color: var(--red);
  text-decoration: none;
  border-radius: 12px;
  margin: 20px;
  transition: all 0.3s ease;
  font-weight: 500;
  border: 1px solid rgba(239, 68, 68, 0.2);
  background: rgba(239, 68, 68, 0.05);
}

.logout-btn:hover {
  background: rgba(239, 68, 68, 0.1);
  border-color: var(--red);
}

/* Main Content */
.main-content {
  flex: 1;
  margin-left: 260px;
  padding: 30px;
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
  font-size: 28px;
  font-weight: 700;
  margin-bottom: 8px;
  background: var(--gradient-green);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.welcome-message p {
  color: var(--text-muted);
  font-size: 16px;
}

.user-id {
  background: rgba(59, 130, 246, 0.1);
  color: #93C5FD;
  padding: 8px 16px;
  border-radius: 20px;
  font-size: 14px;
  font-family: monospace;
  border: 1px solid rgba(59, 130, 246, 0.2);
}

/* Dashboard Grid */
.dashboard-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 25px;
  margin-bottom: 40px;
}

.card {
  background: var(--bg-card);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 20px;
  padding: 25px;
  backdrop-filter: blur(10px);
  transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.card:hover {
  transform: translateY(-5px);
  box-shadow: 0 15px 30px rgba(0, 0, 0, 0.3);
}

.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
}

.card-icon {
  width: 50px;
  height: 50px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
}

.wallet-icon { background: rgba(16, 185, 129, 0.1); color: var(--green); }
.miners-icon { background: rgba(139, 92, 246, 0.1); color: var(--violet); }
.energy-icon { background: rgba(59, 130, 246, 0.1); color: var(--blue); }
.report-icon { background: rgba(249, 115, 22, 0.1); color: var(--orange); }

.card-title {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 18px;
  font-weight: 600;
  margin-bottom: 5px;
}

.card-subtitle {
  color: var(--text-muted);
  font-size: 14px;
}

/* Balance Card */
.balance-amount {
  font-size: 36px;
  font-weight: 800;
  margin: 15px 0;
  font-family: 'Space Grotesk', sans-serif;
}

.minem-balance { background: var(--gradient-green); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
.m2-balance { background: var(--gradient-blue); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
.usd-balance { background: var(--gradient-orange); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }

.balance-buttons {
  display: flex;
  gap: 10px;
  margin-top: 20px;
}

.btn {
  padding: 12px 20px;
  border-radius: 12px;
  font-family: 'Space Grotesk', sans-serif;
  font-weight: 600;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  transition: all 0.3s ease;
  border: none;
  cursor: pointer;
  font-size: 14px;
  flex: 1;
}

.btn-primary {
  background: var(--gradient-green);
  color: white;
}

.btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 20px rgba(16, 185, 129, 0.3);
}

.btn-secondary {
  background: rgba(255, 255, 255, 0.05);
  color: var(--text-primary);
  border: 1px solid rgba(255, 255, 255, 0.1);
}

.btn-secondary:hover {
  background: rgba(255, 255, 255, 0.1);
  border-color: var(--violet);
}

.btn-swap {
  background: var(--gradient-violet);
  color: white;
  margin-top: 10px;
  width: 100%;
}

.btn-swap:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 20px rgba(139, 92, 246, 0.3);
}

/* Stats Cards */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 20px;
  margin-top: 15px;
}

.stat-item {
  text-align: center;
  padding: 15px;
  background: rgba(255, 255, 255, 0.03);
  border-radius: 12px;
  border: 1px solid rgba(255, 255, 255, 0.05);
}

.stat-value {
  font-size: 24px;
  font-weight: 800;
  font-family: 'Space Grotesk', sans-serif;
  margin-bottom: 5px;
}

.stat-label {
  color: var(--text-muted);
  font-size: 13px;
}

/* Report Section */
.report-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 15px;
  margin-top: 20px;
}

.report-stat {
  background: rgba(255, 255, 255, 0.03);
  border-radius: 12px;
  padding: 15px;
  border-left: 4px solid var(--orange);
}

.report-stat:nth-child(2) { border-left-color: var(--violet); }
.report-stat:nth-child(3) { border-left-color: var(--green); }
.report-stat:nth-child(4) { border-left-color: var(--blue); }
.report-stat:nth-child(5) { border-left-color: var(--red); }

.report-stat-value {
  font-size: 20px;
  font-weight: 700;
  margin-bottom: 5px;
}

.report-stat-label {
  color: var(--text-muted);
  font-size: 12px;
}

/* Recent Activity */
.activity-list {
  margin-top: 20px;
}

.activity-item {
  display: flex;
  align-items: center;
  gap: 15px;
  padding: 15px;
  background: rgba(255, 255, 255, 0.03);
  border-radius: 12px;
  margin-bottom: 10px;
  border-left: 4px solid var(--green);
}

.activity-icon {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  background: rgba(16, 185, 129, 0.1);
  color: var(--green);
}

.activity-details h4 {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 14px;
  font-weight: 600;
  margin-bottom: 4px;
}

.activity-details p {
  color: var(--text-muted);
  font-size: 12px;
}

/* Empty States */
.empty-state {
  text-align: center;
  padding: 40px 20px;
  color: var(--text-muted);
}

.empty-state i {
  font-size: 48px;
  margin-bottom: 20px;
  opacity: 0.5;
}

.empty-state h3 {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 18px;
  margin-bottom: 10px;
  color: var(--text-secondary);
}

.empty-state p {
  margin-bottom: 20px;
}

/* Responsive */
@media (max-width: 1024px) {
  .sidebar {
    width: 220px;
  }
  
  .main-content {
    margin-left: 220px;
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
  
  .dashboard-grid {
    grid-template-columns: 1fr;
  }
}
</style>
</head>
<body>
<!-- Sidebar -->
<div class="sidebar">
  <div class="logo">
    <i class="ph-lightning-fill logo-icon"></i>
    MineMechanics 
  </div>
  
  <div class="user-info">
    <div class="user-avatar">
      <?php echo strtoupper(substr($profile['username'] ?? 'U', 0, 1)); ?>
    </div>
    <div class="user-name"><?php echo htmlspecialchars($profile['username'] ?? 'User'); ?></div>
    <div class="user-email"><?php echo htmlspecialchars($profile['email'] ?? 'user@example.com'); ?></div>
    <div class="user-location">
      <i class="ph-map-pin"></i>
      <?php echo htmlspecialchars($profile['location'] ?? 'Unknown Location'); ?>
    </div>
  </div>
  
  <div class="nav-menu">
    <a href="#" class="nav-item active">
      <i class="ph-gauge"></i>
      Dashboard
    </a>
    <a href="topup.php" class="nav-item">
      <i class="ph-credit-card"></i>
      Top Up
    </a>
    <a href="wallet.php" class="nav-item">
      <i class="ph-wallet"></i>
      Wallet
    </a>
    <a href="redeem.php" class="nav-item">
      <i class="ph-money"></i>
      Redeem
    </a>
    <a href="buy-miners.php" class="nav-item">
      <i class="ph-cpu"></i>
      Buy Miners
    </a>
    <a href="buy-plants.php" class="nav-item">
      <i class="ph-leaf"></i>
      Buy Plants
    </a>
    <a href="reports.php" class="nav-item">
      <i class="ph-chart-bar"></i>
      Report
    </a>
    <a href="gift.php" class="nav-item">
      <i class="ph-gift"></i>
      Gift
    </a>
   
    
    <div class="nav-divider"></div>
    
    <a href="settings.php" class="nav-item">
      <i class="ph-gear"></i>
      Settings
    </a>
    <a href="help.php" class="nav-item">
      <i class="ph-question"></i>
      Help & Support
    </a>
  </div>
  
  <a href="?logout=1" class="logout-btn">
    <i class="ph-sign-out"></i>
    Logout
  </a>
</div>

<!-- Main Content -->
<div class="main-content">
  <div class="header">
    <div class="welcome-message">
      <h1>Welcome Back, <?php echo htmlspecialchars($profile['username'] ?? 'Miner'); ?>!</h1>
      <p>Your mining dashboard - Monitor your operations and profits</p>
    </div>
    <div class="user-id">
      UID: <?php echo substr($user_id, 0, 8) . '...'; ?>
    </div>
  </div>
  
  <!-- Dashboard Grid -->
  <div class="dashboard-grid">
    <!-- Wallet Card -->
    <div class="card">
      <div class="card-header">
        <div>
          <div class="card-title">Wallet Balance</div>
          <div class="card-subtitle">Total assets value</div>
        </div>
        <div class="card-icon wallet-icon">
          <i class="ph-wallet"></i>
        </div>
      </div>
      
      <div class="balance-amount minem-balance">
        <?php echo number_format($balance['minem'] ?? 0, 2); ?> MINEM
      </div>
      <div class="balance-amount m2-balance">
        <?php echo number_format($balance['m2'] ?? 0, 2); ?> m²
      </div>
      <div class="balance-amount usd-balance">
        $<?php echo number_format($balance['usd_equivalent'] ?? 0, 2); ?>
      </div>
      
      <div class="balance-buttons">
        <a href="topup.php" class="btn btn-primary">
          <i class="ph-plus"></i> Top Up
        </a>
        <a href="redeem.php" class="btn btn-secondary">
          <i class="ph-arrow-down"></i> Redeem
        </a>
      </div>
      
      <button class="btn btn-swap">
        <i class="ph-arrows-left-right"></i> Swap m² to MINEM (5% fee)
      </button>
    </div>
    
    <!-- Miners Card -->
    <div class="card">
      <div class="card-header">
        <div>
          <div class="card-title">Mining Operation</div>
          <div class="card-subtitle">Active miners & hashpower</div>
        </div>
        <div class="card-icon miners-icon">
          <i class="ph-cpu"></i>
        </div>
      </div>
      
      <div class="stats-grid">
        <div class="stat-item">
          <div class="stat-value"><?php echo $totalMiners; ?></div>
          <div class="stat-label">Miners</div>
        </div>
        <div class="stat-item">
          <div class="stat-value"><?php echo number_format($totalHashrate, 2); ?></div>
          <div class="stat-label">TH/s</div>
        </div>
        <div class="stat-item">
          <div class="stat-value"><?php echo number_format($totalEnergyUsed, 0); ?></div>
          <div class="stat-label">W/h Used</div>
        </div>
        <div class="stat-item">
          <div class="stat-value"><?php echo $dailyReport['total_miners'] ?? 0; ?></div>
          <div class="stat-label">Today</div>
        </div>
      </div>
      
      <?php if ($totalMiners > 0): ?>
        <div class="activity-list">
          <?php foreach (array_slice($userMiners, 0, 3) as $miner): ?>
            <div class="activity-item">
              <div class="activity-icon">
                <i class="ph-cpu"></i>
              </div>
              <div class="activity-details">
               <h4><?php echo htmlspecialchars($miner['miner_types']['name'] ?? 'Miner'); ?></h4>
                <p><?php echo number_format($miner['hashpower_ths'], 2); ?> TH/s</p>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="empty-state">
          <i class="ph-cpu"></i>
          <h3>No Miners Yet</h3>
          <p>Start your mining journey by purchasing miners</p>
          <a href="buy-miners.php" class="btn btn-primary" style="margin-top: 15px;">
            <i class="ph-shopping-cart"></i> Buy Miners
          </a>
        </div>
      <?php endif; ?>
    </div>
    
    <!-- Energy Plants Card -->
    <div class="card">
      <div class="card-header">
        <div>
          <div class="card-title">Energy Production</div>
          <div class="card-subtitle">Power plants & generation</div>
        </div>
        <div class="card-icon energy-icon">
          <i class="ph-leaf"></i>
        </div>
      </div>
      
      <div class="stats-grid">
        <div class="stat-item">
          <div class="stat-value"><?php echo $totalPlants; ?></div>
          <div class="stat-label">Plants</div>
        </div>
        <div class="stat-item">
          <div class="stat-value"><?php echo number_format($totalEnergyGenerated, 0); ?></div>
          <div class="stat-label">W/h Generated</div>
        </div>
        <div class="stat-item">
          <div class="stat-value"><?php echo number_format($energyBalance, 0); ?></div>
          <div class="stat-label">W/h Balance</div>
        </div>
        <div class="stat-item">
          <div class="stat-value"><?php echo $dailyReport['energy_generated_wh'] ?? 0; ?></div>
          <div class="stat-label">Today</div>
        </div>
      </div>
      
      <?php if ($totalPlants > 0): ?>
        <div class="activity-list">
          <?php foreach (array_slice($userPlants, 0, 3) as $plant): ?>
            <div class="activity-item">
              <div class="activity-icon">
                <i class="ph-leaf"></i>
              </div>
              <div class="activity-details">
                <h4><?php echo htmlspecialchars($plant['energy_plant_types']['name'] ?? 'Plant'); ?></h4>
                <p><?php echo $plant['quantity']; ?>x units</p>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="empty-state">
          <i class="ph-leaf"></i>
          <h3>No Power Plants</h3>
          <p>Power your miners with renewable energy</p>
          <a href="buy-plants.php" class="btn btn-primary" style="margin-top: 15px;">
            <i class="ph-shopping-cart"></i> Buy Plants
          </a>
        </div>
      <?php endif; ?>
    </div>
    
    <!-- Daily Report Card -->
    <div class="card">
      <div class="card-header">
        <div>
          <div class="card-title">Daily Report</div>
          <div class="card-subtitle">Today's performance summary</div>
        </div>
        <div class="card-icon report-icon">
          <i class="ph-chart-bar"></i>
        </div>
      </div>
      
      <div class="report-stats">
        <div class="report-stat">
          <div class="report-stat-value"><?php echo number_format($dailyReport['total_ths'] ?? 0, 2); ?></div>
          <div class="report-stat-label">Total TH/s</div>
        </div>
        <div class="report-stat">
          <div class="report-stat-value"><?php echo $dailyReport['total_miners'] ?? 0; ?></div>
          <div class="report-stat-label">Miners</div>
        </div>
        <div class="report-stat">
          <div class="report-stat-value"><?php echo number_format($dailyReport['energy_generated_wh'] ?? 0, 0); ?></div>
          <div class="report-stat-label">Generated</div>
        </div>
        <div class="report-stat">
          <div class="report-stat-value"><?php echo number_format($dailyReport['energy_used_wh'] ?? 0, 0); ?></div>
          <div class="report-stat-label">Used</div>
        </div>
        <div class="report-stat">
          <div class="report-stat-value"><?php echo number_format($dailyReport['energy_balance_wh'] ?? 0, 0); ?></div>
          <div class="report-stat-label">Balance</div>
        </div>
      </div>
      
      <div style="margin-top: 20px;">
        <a href="report.php" class="btn btn-secondary" style="width: 100%;">
          <i class="ph-file-text"></i> View Full Report
        </a>
      </div>
    </div>
  </div>
  
  <!-- Recent Activity -->
  <div class="card" style="margin-top: 30px;">
    <div class="card-header">
      <div>
        <div class="card-title">Recent Activity</div>
        <div class="card-subtitle">Latest mining operations</div>
      </div>
    </div>
    
    <div class="activity-list">
      <div class="activity-item">
        <div class="activity-icon">
          <i class="ph-user-plus"></i>
        </div>
        <div class="activity-details">
          <h4>Account Created</h4>
          <p>Welcome to MineMechanics! Start mining now.</p>
        </div>
        <div style="margin-left: auto; color: var(--text-muted); font-size: 12px;">
          Just now
        </div>
      </div>
      
      <?php if ($totalMiners === 0): ?>
      <div class="activity-item">
        <div class="activity-icon" style="background: rgba(59, 130, 246, 0.1); color: var(--blue);">
          <i class="ph-info"></i>
        </div>
        <div class="activity-details">
          <h4>Get Started</h4>
          <p>Purchase your first miner to start earning MINEM</p>
        </div>
        <div style="margin-left: auto;">
          <a href="buy-miners.php" class="btn btn-primary btn-sm" style="padding: 8px 15px; font-size: 12px;">
            Buy Miner
          </a>
        </div>
      </div>
      <?php endif; ?>
      
      <?php if ($totalPlants === 0 && $totalMiners > 0): ?>
      <div class="activity-item">
        <div class="activity-icon" style="background: rgba(59, 130, 246, 0.1); color: var(--blue);">
          <i class="ph-info"></i>
        </div>
        <div class="activity-details">
          <h4>Power Needed</h4>
          <p>Your miners need energy! Purchase power plants</p>
        </div>
        <div style="margin-left: auto;">
          <a href="buy-plants.php" class="btn btn-primary btn-sm" style="padding: 8px 15px; font-size: 12px;">
            Buy Plants
          </a>
        </div>
      </div>
      <?php endif; ?>
      
      <div class="activity-item">
        <div class="activity-icon" style="background: rgba(249, 115, 22, 0.1); color: var(--orange);">
          <i class="ph-gift"></i>
        </div>
        <div class="activity-details">
          <h4>Daily Faucet Available</h4>
          <p>Claim free MINEM tokens every hour</p>
        </div>
        <div style="margin-left: auto;">
          <a href="faucet.php" class="btn btn-secondary btn-sm" style="padding: 8px 15px; font-size: 12px;">
            Claim Now
          </a>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
// Auto-refresh data every 60 seconds
setTimeout(() => {
  window.location.reload();
}, 60000);

// Toggle sidebar on mobile
const sidebar = document.querySelector('.sidebar');
const mainContent = document.querySelector('.main-content');

function toggleSidebar() {
  sidebar.classList.toggle('mobile-visible');
}

// Add responsive menu button for mobile
if (window.innerWidth <= 768) {
  const header = document.querySelector('.header');
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
  menuBtn.onclick = toggleSidebar;
  document.body.appendChild(menuBtn);
  
  // Add mobile styles
  const style = document.createElement('style');
  style.textContent = `
    @media (max-width: 768px) {
      .sidebar {
        display: none;
        width: 280px;
        left: -280px;
        transition: left 0.3s ease;
      }
      
      .sidebar.mobile-visible {
        display: block;
        left: 0;
      }
      
      .main-content {
        margin-left: 0;
      }
    }
  `;
  document.head.appendChild(style);
}

// Add animations
document.addEventListener('DOMContentLoaded', function() {
  const cards = document.querySelectorAll('.card');
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
</script>
</body>
</html>