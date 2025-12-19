<?php
// swap.php - Swap m² tokens to MINEM
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

// Constants
define('SWAP_FEE_PERCENT', 5); // 5% fee on swap
define('MIN_SWAP_AMOUNT', 100); // Minimum m² to swap
define('MINEM_PER_M2', 1); // 1 m² = 1 MINEM (before fee)

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
    } elseif ($method === 'PATCH') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
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

// Function to calculate swap amounts
function calculateSwap($m2Amount) {
    $feeAmount = ($m2Amount * SWAP_FEE_PERCENT) / 100;
    $netM2 = $m2Amount - $feeAmount;
    $minemReceived = $netM2 * MINEM_PER_M2;
    
    return [
        'm2_spent' => $m2Amount,
        'fee_percent' => SWAP_FEE_PERCENT,
        'fee_amount' => $feeAmount,
        'net_m2' => $netM2,
        'minem_received' => $minemReceived
    ];
}

// Get user profile
$profile = null;
$profileResponse = supabaseRequest('/rest/v1/profiles?id=eq.' . $user_id);
if ($profileResponse['status'] === 200 && !empty($profileResponse['data'])) {
    $profile = $profileResponse['data'][0];
}

// Get user balance
$balance = null;
$balanceResponse = supabaseRequest('/rest/v1/balances?user_id=eq.' . $user_id);
if ($balanceResponse['status'] === 200 && !empty($balanceResponse['data'])) {
    $balance = $balanceResponse['data'][0];
}

// Get user's swap history
$swapHistory = [];
$historyResponse = supabaseRequest('/rest/v1/swaps?user_id=eq.' . $user_id . '&order=created_at.desc&limit=10');
if ($historyResponse['status'] === 200) {
    $swapHistory = $historyResponse['data'];
}

// Handle swap
$swapSuccess = false;
$swapMessage = '';
$swapDetails = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['swap_tokens'])) {
    $m2Amount = floatval($_POST['m2_amount'] ?? 0);
    
    // Validate input
    if ($m2Amount < MIN_SWAP_AMOUNT) {
        $swapMessage = 'Minimum swap amount is ' . number_format(MIN_SWAP_AMOUNT) . ' m².';
    } elseif ($m2Amount > ($balance['m2'] ?? 0)) {
        $swapMessage = 'Insufficient m² balance. Available: ' . number_format($balance['m2'] ?? 0, 2) . ' m²';
    } else {
        // Calculate swap details
        $swapDetails = calculateSwap($m2Amount);
        
        // Start transaction: Record swap
        $swapData = [
            'user_id' => $user_id,
            'm2_spent' => $swapDetails['m2_spent'],
            'minem_received' => $swapDetails['minem_received'],
            'fee_percent' => $swapDetails['fee_percent']
        ];
        
        $swapResponse = supabaseRequest('/rest/v1/swaps', 'POST', $swapData, true);
        
        if ($swapResponse['status'] === 201 || $swapResponse['status'] === 200) {
            // Update user balance
            $newM2Balance = floatval($balance['m2'] ?? 0) - $m2Amount;
            $newMinemBalance = floatval($balance['minem'] ?? 0) + $swapDetails['minem_received'];
            
            $updateData = [
                'm2' => $newM2Balance,
                'minem' => $newMinemBalance,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $balanceResponse = supabaseRequest('/rest/v1/balances?user_id=eq.' . $user_id, 'PATCH', $updateData, true);
            
            if ($balanceResponse['status'] === 204 || $balanceResponse['status'] === 200) {
                $swapSuccess = true;
                $swapMessage = 'Successfully swapped ' . number_format($m2Amount) . ' m² for ' . number_format($swapDetails['minem_received']) . ' MINEM!';
                
                // Update balance locally
                $balance['m2'] = $newM2Balance;
                $balance['minem'] = $newMinemBalance;
                
                // Refresh swap history
                $historyResponse = supabaseRequest('/rest/v1/swaps?user_id=eq.' . $user_id . '&order=created_at.desc&limit=10');
                if ($historyResponse['status'] === 200) {
                    $swapHistory = $historyResponse['data'];
                }
                
                // Redirect to prevent form resubmission
                header('Location: swap.php?success=true&m2=' . $m2Amount . '&minem=' . $swapDetails['minem_received']);
                exit();
            } else {
                // Rollback: Delete the swap record
                if (!empty($swapResponse['data'][0]['id'])) {
                    supabaseRequest('/rest/v1/swaps?id=eq.' . $swapResponse['data'][0]['id'], 'DELETE', null, true);
                }
                $swapMessage = 'Failed to update balance. Please contact support.';
            }
        } else {
            $swapMessage = 'Failed to process swap. Please try again. HTTP Code: ' . $swapResponse['status'];
        }
    }
}

// Check for success redirect
if (isset($_GET['success']) && $_GET['success'] === 'true') {
    $swapSuccess = true;
    $m2Amount = $_GET['m2'] ?? 0;
    $minemReceived = $_GET['minem'] ?? 0;
    
    $swapDetails = calculateSwap(floatval($m2Amount));
    $swapMessage = 'Successfully swapped ' . number_format($m2Amount) . ' m² for ' . number_format($minemReceived) . ' MINEM!';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Swap Tokens - MineMechanics</title>
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
  --gradient-gold: linear-gradient(135deg, #FACC15 0%, #FDE047 100%);
  --gradient-cyan: linear-gradient(135deg, #06B6D4 0%, #22D3EE 100%);
  --violet: #8B5CF6;
  --orange: #F97316;
  --green: #10B981;
  --blue: #3B82F6;
  --gold: #FACC15;
  --cyan: #06B6D4;
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
  min-height: 100vh;
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
  background: var(--gradient-cyan);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

/* Swap Container */
.swap-container {
  max-width: 1200px;
  margin: 0 auto;
}

/* Alert Messages */
.alert {
  padding: 20px;
  border-radius: 12px;
  margin-bottom: 25px;
  font-size: 16px;
  display: flex;
  align-items: center;
  gap: 15px;
  animation: slideIn 0.5s ease;
}

@keyframes slideIn {
  from { opacity: 0; transform: translateY(-10px); }
  to { opacity: 1; transform: translateY(0); }
}

.alert-success {
  background: rgba(16, 185, 129, 0.1);
  border: 1px solid rgba(16, 185, 129, 0.2);
  color: #6EE7B7;
}

.alert-error {
  background: rgba(239, 68, 68, 0.1);
  border: 1px solid rgba(239, 68, 68, 0.2);
  color: #FCA5A5;
}

/* Balance Display */
.balance-display {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 20px;
  margin-bottom: 40px;
}

.balance-card {
  background: var(--bg-card);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 20px;
  padding: 25px;
  text-align: center;
  position: relative;
  overflow: hidden;
}

.balance-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 4px;
}

.balance-card.minem::before {
  background: var(--gradient-gold);
}

.balance-card.m2::before {
  background: var(--gradient-green);
}

.balance-card.swapped::before {
  background: var(--gradient-cyan);
}

.balance-card.fee::before {
  background: var(--gradient-violet);
}

.balance-label {
  color: var(--text-muted);
  font-size: 14px;
  margin-bottom: 15px;
  text-transform: uppercase;
  letter-spacing: 1px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
}

.balance-value {
  font-size: 32px;
  font-weight: 800;
  font-family: 'Space Grotesk', sans-serif;
}

.minem-balance {
  background: var(--gradient-gold);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.m2-balance {
  background: var(--gradient-green);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.swapped-balance {
  background: var(--gradient-cyan);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.fee-balance {
  background: var(--gradient-violet);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

/* Swap Section */
.swap-section {
  background: var(--bg-card);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 20px;
  padding: 40px;
  margin-bottom: 40px;
}

.section-header {
  display: flex;
  align-items: center;
  gap: 15px;
  margin-bottom: 30px;
}

.section-icon {
  font-size: 32px;
  background: var(--gradient-cyan);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.section-title {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 28px;
  font-weight: 700;
  color: var(--text-primary);
}

/* Swap Form */
.swap-form {
  max-width: 600px;
  margin: 0 auto;
}

.swap-info {
  background: rgba(6, 182, 212, 0.1);
  border: 1px solid rgba(6, 182, 212, 0.2);
  border-radius: 16px;
  padding: 25px;
  margin-bottom: 30px;
  text-align: center;
}

.swap-rate {
  font-size: 24px;
  font-weight: 700;
  margin-bottom: 10px;
  color: var(--cyan);
}

.swap-fee {
  color: var(--text-muted);
  font-size: 14px;
}

.form-group {
  margin-bottom: 25px;
}

.form-label {
  display: block;
  color: var(--text-secondary);
  font-size: 14px;
  margin-bottom: 10px;
  font-weight: 500;
}

.form-input-group {
  position: relative;
}

.form-input {
  width: 100%;
  padding: 15px 20px;
  background: rgba(0, 0, 0, 0.3);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  color: var(--text-primary);
  font-size: 16px;
  transition: all 0.3s ease;
  padding-right: 120px;
}

.form-input:focus {
  outline: none;
  border-color: var(--cyan);
  box-shadow: 0 0 0 3px rgba(6, 182, 212, 0.1);
}

.token-label {
  position: absolute;
  right: 15px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--text-muted);
  font-size: 14px;
  font-weight: 600;
}

.max-btn {
  position: absolute;
  right: 15px;
  top: 50%;
  transform: translateY(-50%);
  background: rgba(6, 182, 212, 0.2);
  border: 1px solid rgba(6, 182, 212, 0.3);
  color: var(--cyan);
  padding: 6px 12px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
}

.max-btn:hover {
  background: rgba(6, 182, 212, 0.3);
}

.form-range {
  width: 100%;
  height: 8px;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 4px;
  outline: none;
  -webkit-appearance: none;
  margin-top: 10px;
}

.form-range::-webkit-slider-thumb {
  -webkit-appearance: none;
  width: 24px;
  height: 24px;
  background: var(--gradient-cyan);
  border-radius: 50%;
  cursor: pointer;
}

.form-range::-moz-range-thumb {
  width: 24px;
  height: 24px;
  background: var(--gradient-cyan);
  border-radius: 50%;
  cursor: pointer;
  border: none;
}

.range-labels {
  display: flex;
  justify-content: space-between;
  margin-top: 10px;
  color: var(--text-muted);
  font-size: 12px;
}

/* Swap Preview */
.swap-preview {
  background: rgba(245, 158, 11, 0.1);
  border: 1px solid rgba(245, 158, 11, 0.2);
  border-radius: 16px;
  padding: 25px;
  margin: 30px 0;
}

.preview-header {
  text-align: center;
  margin-bottom: 20px;
}

.preview-title {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 20px;
  font-weight: 600;
  color: var(--text-primary);
}

.preview-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 15px;
}

.preview-item {
  text-align: center;
  padding: 15px;
  background: rgba(0, 0, 0, 0.2);
  border-radius: 12px;
}

.preview-label {
  color: var(--text-muted);
  font-size: 11px;
  margin-bottom: 5px;
  text-transform: uppercase;
  letter-spacing: 1px;
}

.preview-value {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 18px;
  font-weight: 700;
}

.preview-value.m2 {
  color: var(--green);
}

.preview-value.fee {
  color: var(--orange);
}

.preview-value.minem {
  color: var(--gold);
}

/* Swap Button */
.swap-btn {
  width: 100%;
  padding: 20px;
  background: var(--gradient-cyan);
  color: white;
  border: none;
  border-radius: 16px;
  font-family: 'Space Grotesk', sans-serif;
  font-size: 18px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 15px;
}

.swap-btn:hover:not(:disabled) {
  transform: translateY(-3px);
  box-shadow: 0 15px 30px rgba(6, 182, 212, 0.4);
}

.swap-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* History Section */
.history-section {
  background: var(--bg-card);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 20px;
  padding: 35px;
}

.history-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 25px;
}

.history-title {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 24px;
  font-weight: 600;
  color: var(--text-primary);
}

.history-table {
  width: 100%;
  border-collapse: collapse;
}

.history-table th {
  color: var(--text-muted);
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 1px;
  padding: 15px;
  text-align: left;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  font-weight: 600;
}

.history-table td {
  padding: 15px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.05);
  color: var(--text-secondary);
}

.history-table tr:last-child td {
  border-bottom: none;
}

.history-table tr:hover {
  background: rgba(255, 255, 255, 0.03);
}

.m2-amount {
  color: var(--green);
  font-weight: 600;
}

.minem-amount {
  color: var(--gold);
  font-weight: 600;
}

.fee-amount {
  color: var(--orange);
  font-weight: 600;
}

.status-success {
  color: var(--green);
  font-weight: 600;
}

.empty-history {
  text-align: center;
  padding: 60px 20px;
  color: var(--text-muted);
}

.empty-history i {
  font-size: 48px;
  margin-bottom: 20px;
  opacity: 0.5;
}

.empty-history h3 {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 24px;
  margin-bottom: 10px;
  color: var(--text-secondary);
}

.empty-history p {
  font-size: 16px;
  max-width: 400px;
  margin: 0 auto;
}

/* Responsive Design */
@media (max-width: 1024px) {
  .sidebar {
    width: 80px;
  }
  
  .logo span {
    display: none;
  }
  
  .nav-item span {
    display: none;
  }
  
  .nav-item {
    justify-content: center;
    padding: 15px;
  }
  
  .main-content {
    margin-left: 80px;
  }
}

@media (max-width: 768px) {
  .main-content {
    padding: 20px;
  }
  
  .header {
    flex-direction: column;
    align-items: flex-start;
    gap: 15px;
  }
  
  .balance-display {
    grid-template-columns: repeat(2, 1fr);
  }
  
  .swap-section {
    padding: 25px;
  }
  
  .history-table {
    display: block;
    overflow-x: auto;
  }
  
  .preview-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 480px) {
  .balance-display {
    grid-template-columns: 1fr;
  }
  
  .preview-grid {
    grid-template-columns: 1fr;
  }
  
  .history-table th,
  .history-table td {
    padding: 10px 5px;
    font-size: 14px;
  }
}
</style>
</head>
<body>
<!-- Sidebar -->
<div class="sidebar">
    <div class="logo">
        <i class="ph ph-pickaxe"></i>
        <span>MineMechanics</span>
    </div>
    
    <nav class="nav-menu">
        <a href="dashboard.php" class="nav-item">
            <i class="ph ph-gauge"></i>
            <span>Dashboard</span>
        </a>
        <a href="buy-miners.php" class="nav-item">
            <i class="ph ph-rocket-launch"></i>
            <span>Buy Miners</span>
        </a>
        <a href="buy-plants.php" class="nav-item">
            <i class="ph ph-sun"></i>
            <span>Solar Plants</span>
        </a>
        <a href="my-miners.php" class="nav-item">
            <i class="ph ph-cube"></i>
            <span>My Miners</span>
        </a>
        <a href="energy-market.php" class="nav-item">
            <i class="ph ph-lightning"></i>
            <span>Energy Market</span>
        </a>
        <a href="swap.php" class="nav-item active">
            <i class="ph ph-arrows-left-right"></i>
            <span>Swap Tokens</span>
        </a>
        <a href="topup.php" class="nav-item">
            <i class="ph ph-credit-card"></i>
            <span>Top Up MINEM</span>
        </a>
        <a href="redeem.php" class="nav-item">
            <i class="ph ph-bank"></i>
            <span>Redeem</span>
        </a>
        <a href="profile.php" class="nav-item">
            <i class="ph ph-user"></i>
            <span>Profile</span>
        </a>
        <a href="logout.php" class="nav-item">
            <i class="ph ph-sign-out"></i>
            <span>Logout</span>
        </a>
    </nav>
</div>

<!-- Main Content -->
<div class="main-content">
    <div class="header">
        <div class="welcome-message">
            <h1>Swap Tokens</h1>
            <p class="text-muted">Convert your earned m² tokens to MINEM tokens</p>
        </div>
    </div>
    
    <div class="swap-container">
        <!-- Alert Messages -->
        <?php if ($swapSuccess && !empty($swapMessage)): ?>
        <div class="alert alert-success">
            <i class="ph ph-check-circle-fill"></i>
            <span><?php echo htmlspecialchars($swapMessage); ?></span>
        </div>
        <?php elseif (!empty($swapMessage)): ?>
        <div class="alert alert-error">
            <i class="ph ph-warning-circle-fill"></i>
            <span><?php echo htmlspecialchars($swapMessage); ?></span>
        </div>
        <?php endif; ?>
        
        <!-- Balance Display -->
        <div class="balance-display">
            <div class="balance-card m2">
                <div class="balance-label">
                    <i class="ph ph-gem"></i>
                    <span>m² Balance</span>
                </div>
                <div class="balance-value m2-balance">
                    <?php echo number_format($balance['m2'] ?? 0, 2); ?>
                </div>
            </div>
            
            <div class="balance-card minem">
                <div class="balance-label">
                    <i class="ph ph-coins"></i>
                    <span>MINEM Balance</span>
                </div>
                <div class="balance-value minem-balance">
                    <?php echo number_format($balance['minem'] ?? 0, 2); ?>
                </div>
            </div>
            
            <?php 
            // Calculate total swapped
            $totalM2Swapped = 0;
            $totalMinemReceived = 0;
            foreach ($swapHistory as $swap) {
                $totalM2Swapped += floatval($swap['m2_spent']);
                $totalMinemReceived += floatval($swap['minem_received']);
            }
            ?>
            <div class="balance-card swapped">
                <div class="balance-label">
                    <i class="ph ph-arrows-left-right"></i>
                    <span>Total Swapped</span>
                </div>
                <div class="balance-value swapped-balance">
                    <?php echo number_format($totalM2Swapped, 2); ?> m²
                </div>
            </div>
            
            <div class="balance-card fee">
                <div class="balance-label">
                    <i class="ph ph-percent"></i>
                    <span>Swap Fee</span>
                </div>
                <div class="balance-value fee-balance">
                    <?php echo SWAP_FEE_PERCENT; ?>%
                </div>
            </div>
        </div>
        
        <!-- Swap Section -->
        <div class="swap-section">
            <div class="section-header">
                <i class="ph ph-arrows-left-right section-icon"></i>
                <h2 class="section-title">Swap m² to MINEM</h2>
            </div>
            
            <div class="swap-info">
                <div class="swap-rate">1 m² = <?php echo (1 - SWAP_FEE_PERCENT/100); ?> MINEM</div>
                <div class="swap-fee">(<?php echo SWAP_FEE_PERCENT; ?>% swap fee applied)</div>
            </div>
            
            <form method="POST" class="swap-form" onsubmit="return validateSwap()">
                <div class="form-group">
                    <label class="form-label">
                        <i class="ph ph-gem"></i>
                        m² Amount to Swap (Minimum: <?php echo number_format(MIN_SWAP_AMOUNT); ?> m²)
                    </label>
                    <div class="form-input-group">
                        <input type="number" 
                               name="m2_amount" 
                               id="m2Amount" 
                               class="form-input" 
                               value="<?php echo MIN_SWAP_AMOUNT; ?>" 
                               required
                               min="<?php echo MIN_SWAP_AMOUNT; ?>"
                               max="<?php echo $balance['m2'] ?? 0; ?>"
                               oninput="updateSwapSlider(this.value)">
                        <span class="token-label">m²</span>
                        <button type="button" class="max-btn" onclick="setMaxAmount()">MAX</button>
                    </div>
                    <input type="range" 
                           name="m2_amount_range" 
                           class="form-range" 
                           min="<?php echo MIN_SWAP_AMOUNT; ?>" 
                           max="<?php echo max($balance['m2'] ?? MIN_SWAP_AMOUNT, MIN_SWAP_AMOUNT * 10); ?>" 
                           step="10" 
                           value="<?php echo MIN_SWAP_AMOUNT; ?>"
                           oninput="updateM2Amount(this.value)">
                    <div class="range-labels">
                        <span><?php echo number_format(MIN_SWAP_AMOUNT); ?> m²</span>
                        <span id="currentRangeValue"><?php echo number_format(MIN_SWAP_AMOUNT); ?> m²</span>
                        <span><?php echo number_format(max($balance['m2'] ?? MIN_SWAP_AMOUNT, MIN_SWAP_AMOUNT * 10)); ?> m²</span>
                    </div>
                </div>
                
                <!-- Swap Preview -->
                <div class="swap-preview" id="swapPreview">
                    <div class="preview-header">
                        <h3 class="preview-title">Swap Preview</h3>
                    </div>
                    <div class="preview-grid">
                        <div class="preview-item">
                            <div class="preview-label">You Spend</div>
                            <div class="preview-value m2" id="previewSpend"><?php echo number_format(MIN_SWAP_AMOUNT); ?> m²</div>
                        </div>
                        <div class="preview-item">
                            <div class="preview-label">Swap Fee</div>
                            <div class="preview-value fee" id="previewFee"><?php echo number_format((MIN_SWAP_AMOUNT * SWAP_FEE_PERCENT) / 100, 2); ?> m²</div>
                        </div>
                        <div class="preview-item">
                            <div class="preview-label">You Receive</div>
                            <div class="preview-value minem" id="previewReceive">
                                <?php 
                                $minemReceived = MIN_SWAP_AMOUNT * (1 - SWAP_FEE_PERCENT/100);
                                echo number_format($minemReceived, 2); 
                                ?> MINEM
                            </div>
                        </div>
                    </div>
                </div>
                
                <button type="submit" 
                        name="swap_tokens" 
                        class="swap-btn"
                        <?php echo ($balance['m2'] ?? 0) < MIN_SWAP_AMOUNT ? 'disabled' : ''; ?>>
                    <i class="ph ph-arrows-left-right"></i>
                    Swap Now
                </button>
            </form>
        </div>
        
        <!-- History Section -->
        <div class="history-section">
            <div class="history-header">
                <h2 class="history-title">
                    <i class="ph ph-clock-counter-clockwise"></i>
                    Swap History
                </h2>
            </div>
            
            <?php if (empty($swapHistory)): ?>
            <div class="empty-history">
                <i class="ph ph-clock"></i>
                <h3>No Swaps Yet</h3>
                <p>Your swap history will appear here once you start converting m² to MINEM tokens.</p>
            </div>
            <?php else: ?>
            <table class="history-table">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>m² Spent</th>
                        <th>MINEM Received</th>
                        <th>Fee (<?php echo SWAP_FEE_PERCENT; ?>%)</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($swapHistory as $swap): ?>
                    <tr>
                        <td><?php echo date('M d, Y H:i', strtotime($swap['created_at'])); ?></td>
                        <td class="m2-amount"><?php echo number_format($swap['m2_spent'], 2); ?> m²</td>
                        <td class="minem-amount"><?php echo number_format($swap['minem_received'], 2); ?> MINEM</td>
                        <td class="fee-amount"><?php echo number_format(($swap['m2_spent'] * $swap['fee_percent']) / 100, 2); ?> m²</td>
                        <td class="status-success">Completed</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Constants
const MIN_SWAP_AMOUNT = <?php echo MIN_SWAP_AMOUNT; ?>;
const SWAP_FEE_PERCENT = <?php echo SWAP_FEE_PERCENT; ?>;
const USER_M2_BALANCE = <?php echo $balance['m2'] ?? 0; ?>;

// Update swap preview
function updateSwapPreview(m2Amount) {
    const feeAmount = (m2Amount * SWAP_FEE_PERCENT) / 100;
    const minemReceived = m2Amount - feeAmount;
    
    // Update preview elements
    document.getElementById('previewSpend').textContent = m2Amount.toLocaleString() + ' m²';
    document.getElementById('previewFee').textContent = feeAmount.toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }) + ' m²';
    document.getElementById('previewReceive').textContent = minemReceived.toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }) + ' MINEM';
}

// Update m² amount from slider
function updateM2Amount(value) {
    const m2Amount = parseInt(value);
    document.getElementById('currentRangeValue').textContent = m2Amount.toLocaleString() + ' m²';
    document.getElementById('m2Amount').value = m2Amount;
    updateSwapPreview(m2Amount);
}

// Update slider from input
function updateSwapSlider(value) {
    const m2Amount = Math.max(MIN_SWAP_AMOUNT, Math.min(value, USER_M2_BALANCE));
    document.querySelector('input[name="m2_amount_range"]').value = m2Amount;
    document.getElementById('currentRangeValue').textContent = m2Amount.toLocaleString() + ' m²';
    updateSwapPreview(m2Amount);
}

// Set maximum amount
function setMaxAmount() {
    const maxAmount = USER_M2_BALANCE;
    document.getElementById('m2Amount').value = maxAmount;
    updateSwapSlider(maxAmount);
}

// Validate swap
function validateSwap() {
    const m2Amount = parseFloat(document.getElementById('m2Amount').value);
    const balance = USER_M2_BALANCE;
    
    if (m2Amount < MIN_SWAP_AMOUNT) {
        alert('Minimum swap amount is ' + MIN_SWAP_AMOUNT.toLocaleString() + ' m².');
        return false;
    }
    
    if (m2Amount > balance) {
        alert('Insufficient m² balance. Available: ' + balance.toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }) + ' m²');
        return false;
    }
    
    const feeAmount = (m2Amount * SWAP_FEE_PERCENT) / 100;
    const minemReceived = m2Amount - feeAmount;
    
    return confirm(
        'Are you sure you want to swap?\n\n' +
        '• You spend: ' + m2Amount.toLocaleString() + ' m²\n' +
        '• Swap fee: ' + feeAmount.toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }) + ' m² (' + SWAP_FEE_PERCENT + '%)\n' +
        '• You receive: ' + minemReceived.toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }) + ' MINEM'
    );
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Set max value for range slider
    const rangeSlider = document.querySelector('input[name="m2_amount_range"]');
    const maxValue = Math.max(USER_M2_BALANCE, MIN_SWAP_AMOUNT * 10);
    rangeSlider.max = maxValue;
    
    // Update max label
    document.querySelectorAll('.range-labels span')[2].textContent = maxValue.toLocaleString() + ' m²';
    
    // Initial preview update
    updateSwapPreview(MIN_SWAP_AMOUNT);
});
</script>
</body>
</html>