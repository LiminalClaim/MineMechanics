<?php
// wallet.php - User Wallet & Balances
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
define('MINEM_PER_DOLLAR', 1000000); // 1,000,000 MINEM = $1
define('SWAP_FEE_PERCENT', 5); // 5% swap fee
define('MIN_SWAP_AMOUNT', 100); // Minimum 100 MINEM to swap

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

// Function to calculate USD value from MINEM
function calculateUsdValue($minemAmount) {
    return $minemAmount / MINEM_PER_DOLLAR;
}

// Function to calculate swap amounts (MINEM to m²)
function calculateSwap($minemAmount) {
    $feeAmount = ($minemAmount * SWAP_FEE_PERCENT) / 100;
    $netMinem = $minemAmount - $feeAmount;
    $m2Received = $netMinem; // 1 MINEM = 1 m² after fee
    
    return [
        'minem_spent' => $minemAmount,
        'fee_percent' => SWAP_FEE_PERCENT,
        'fee_amount' => $feeAmount,
        'net_minem' => $netMinem,
        'm2_received' => $m2Received
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

// Get user's recent swap history (from swaps table)
$swapHistory = [];
$historyResponse = supabaseRequest('/rest/v1/swaps?user_id=eq.' . $user_id . '&order=created_at.desc&limit=5');
if ($historyResponse['status'] === 200) {
    $swapHistory = $historyResponse['data'];
}

// Get user's recent topups
$recentTopups = [];
$topupsResponse = supabaseRequest('/rest/v1/topups?user_id=eq.' . $user_id . '&order=created_at.desc&limit=3');
if ($topupsResponse['status'] === 200) {
    $recentTopups = $topupsResponse['data'];
}

// Get user's recent redeem requests
$recentRedeems = [];
$redeemsResponse = supabaseRequest('/rest/v1/redeem_requests?user_id=eq.' . $user_id . '&order=created_at.desc&limit=3');
if ($redeemsResponse['status'] === 200) {
    $recentRedeems = $redeemsResponse['data'];
}

// Calculate USD values from balances table
$minemUsdValue = calculateUsdValue($balance['minem'] ?? 0);
$m2UsdValue = calculateUsdValue($balance['m2'] ?? 0);
$totalUsdValue = ($balance['usd_equivalent'] ?? 0) + $minemUsdValue + $m2UsdValue;

// Handle swap (MINEM to m²) - Modified for schema compatibility
$swapSuccess = false;
$swapMessage = '';
$swapDetails = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['swap_tokens'])) {
    $minemAmount = floatval($_POST['minem_amount'] ?? 0);
    
    // Validate input
    if ($minemAmount < MIN_SWAP_AMOUNT) {
        $swapMessage = 'Minimum swap amount is ' . number_format(MIN_SWAP_AMOUNT) . ' MINEM.';
    } elseif ($minemAmount > ($balance['minem'] ?? 0)) {
        $swapMessage = 'Insufficient MINEM balance. Available: ' . number_format($balance['minem'] ?? 0, 2) . ' MINEM';
    } else {
        // Calculate swap details
        $swapDetails = calculateSwap($minemAmount);
        
        // Note: According to schema, swaps table only supports m² to MINEM swaps
        // For MINEM to m² swap, we need to adapt the data structure
        // We'll record it as m² to MINEM swap with reversed amounts for schema compatibility
        $swapData = [
            'user_id' => $user_id,
            'm2_spent' => 0, // We're not spending m²
            'minem_received' => 0, // We're not receiving MINEM
            'fee_percent' => $swapDetails['fee_percent'],
            // Additional custom fields for our use case
            'minem_spent' => $swapDetails['minem_spent'],
            'm2_received' => $swapDetails['m2_received'],
            'swap_type' => 'minem_to_m2'
        ];
        
        $swapResponse = supabaseRequest('/rest/v1/swaps', 'POST', $swapData, true);
        
        if ($swapResponse['status'] === 201 || $swapResponse['status'] === 200) {
            // Update user balance in balances table
            $newMinemBalance = floatval($balance['minem'] ?? 0) - $minemAmount;
            $newM2Balance = floatval($balance['m2'] ?? 0) + $swapDetails['m2_received'];
            $newUsdEquivalent = $newM2Balance / MINEM_PER_DOLLAR;
            
            $updateData = [
                'minem' => $newMinemBalance,
                'm2' => $newM2Balance,
                'usd_equivalent' => $newUsdEquivalent,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $balanceResponse = supabaseRequest('/rest/v1/balances?user_id=eq.' . $user_id, 'PATCH', $updateData, true);
            
            if ($balanceResponse['status'] === 204 || $balanceResponse['status'] === 200) {
                $swapSuccess = true;
                $swapMessage = 'Successfully swapped ' . number_format($minemAmount) . ' MINEM for ' . number_format($swapDetails['m2_received']) . ' m²!';
                
                // Update balance locally
                $balance['minem'] = $newMinemBalance;
                $balance['m2'] = $newM2Balance;
                $balance['usd_equivalent'] = $newUsdEquivalent;
                
                // Recalculate USD values
                $minemUsdValue = calculateUsdValue($balance['minem']);
                $m2UsdValue = calculateUsdValue($balance['m2']);
                $totalUsdValue = $balance['usd_equivalent'] + $minemUsdValue + $m2UsdValue;
                
                // Refresh swap history
                $historyResponse = supabaseRequest('/rest/v1/swaps?user_id=eq.' . $user_id . '&order=created_at.desc&limit=5');
                if ($historyResponse['status'] === 200) {
                    $swapHistory = $historyResponse['data'];
                }
                
                // Redirect to prevent form resubmission
                header('Location: wallet.php?success=true&minem=' . $minemAmount . '&m2=' . $swapDetails['m2_received']);
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
    $minemAmount = $_GET['minem'] ?? 0;
    $m2Received = $_GET['m2'] ?? 0;
    
    $swapDetails = calculateSwap(floatval($minemAmount));
    $swapMessage = 'Successfully swapped ' . number_format($minemAmount) . ' MINEM for ' . number_format($m2Received) . ' m²!';
}

// Combine all recent activities for display
$recentActivities = [];

// Add swaps to activities
foreach ($swapHistory as $swap) {
    $activity = [
        'type' => 'swap',
        'title' => 'Token Swap',
        'description' => isset($swap['swap_type']) && $swap['swap_type'] === 'minem_to_m2' 
            ? 'Converted MINEM to m²' 
            : 'Converted m² to MINEM',
        'amount' => isset($swap['swap_type']) && $swap['swap_type'] === 'minem_to_m2'
            ? ['m2' => floatval($swap['m2_received'] ?? 0)]
            : ['minem' => floatval($swap['minem_received'] ?? 0)],
        'time' => $swap['created_at'],
        'icon' => 'ph-arrows-left-right'
    ];
    $recentActivities[] = $activity;
}

// Add topups to activities
foreach ($recentTopups as $topup) {
    $activity = [
        'type' => 'topup',
        'title' => 'Top Up',
        'description' => 'Added funds to wallet',
        'amount' => ['minem' => floatval($topup['amount_minem'])],
        'time' => $topup['created_at'],
        'icon' => 'ph-arrow-down-left',
        'status' => $topup['status']
    ];
    $recentActivities[] = $activity;
}

// Add redeems to activities
foreach ($recentRedeems as $redeem) {
    $activity = [
        'type' => 'redeem',
        'title' => 'Redeem Request',
        'description' => 'Requested withdrawal',
        'amount' => ['usd' => floatval($redeem['amount_usd'])],
        'time' => $redeem['created_at'],
        'icon' => 'ph-bank',
        'status' => $redeem['status']
    ];
    $recentActivities[] = $activity;
}

// Sort activities by time (newest first)
usort($recentActivities, function($a, $b) {
    return strtotime($b['time']) - strtotime($a['time']);
});

// Take only 5 most recent activities
$recentActivities = array_slice($recentActivities, 0, 5);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Wallet - MineMechanics</title>
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
  --gradient-purple: linear-gradient(135deg, #A855F7 0%, #EC4899 100%);
  --violet: #8B5CF6;
  --orange: #F97316;
  --green: #10B981;
  --blue: #3B82F6;
  --gold: #FACC15;
  --cyan: #06B6D4;
  --purple: #A855F7;
  --pink: #EC4899;
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
  background: var(--gradient-gold);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

/* Wallet Container */
.wallet-container {
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

/* Total Balance */
.total-balance-card {
  background: var(--bg-card);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 20px;
  padding: 40px;
  margin-bottom: 40px;
  text-align: center;
  position: relative;
  overflow: hidden;
}

.total-balance-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 4px;
  background: var(--gradient-purple);
}

.total-label {
  color: var(--text-muted);
  font-size: 16px;
  margin-bottom: 15px;
  text-transform: uppercase;
  letter-spacing: 1px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
}

.total-value {
  font-size: 64px;
  font-weight: 800;
  font-family: 'Space Grotesk', sans-serif;
  background: var(--gradient-gold);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  line-height: 1;
  margin-bottom: 10px;
}

.total-subvalue {
  color: var(--text-secondary);
  font-size: 20px;
  font-family: 'Inter', sans-serif;
}

/* Balance Cards Grid */
.balance-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 25px;
  margin-bottom: 40px;
}

.balance-card {
  background: var(--bg-card);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 20px;
  padding: 30px;
  position: relative;
  overflow: hidden;
  transition: all 0.3s ease;
}

.balance-card:hover {
  transform: translateY(-5px);
  border-color: rgba(255, 255, 255, 0.2);
  box-shadow: 0 15px 30px rgba(0, 0, 0, 0.3);
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

.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 25px;
}

.token-info {
  display: flex;
  align-items: center;
  gap: 15px;
}

.token-icon {
  width: 56px;
  height: 56px;
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 28px;
}

.token-icon.minem {
  background: rgba(250, 204, 21, 0.1);
  color: var(--gold);
}

.token-icon.m2 {
  background: rgba(16, 185, 129, 0.1);
  color: var(--green);
}

.token-name h3 {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 22px;
  font-weight: 600;
  color: var(--text-primary);
  margin-bottom: 5px;
}

.token-symbol {
  color: var(--text-muted);
  font-size: 14px;
  font-weight: 500;
}

.balance-display {
  margin-bottom: 25px;
}

.token-balance {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 36px;
  font-weight: 700;
  margin-bottom: 10px;
  line-height: 1;
}

.token-balance.minem {
  color: var(--gold);
}

.token-balance.m2 {
  color: var(--green);
}

.token-usd {
  color: var(--text-secondary);
  font-size: 18px;
  font-family: 'Inter', sans-serif;
}

/* Action Buttons */
.action-buttons {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
  margin-top: 20px;
}

.action-btn {
  padding: 14px;
  border-radius: 12px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  border: none;
  font-family: 'Space Grotesk', sans-serif;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  text-decoration: none;
}

.action-btn.minem {
  background: rgba(250, 204, 21, 0.1);
  color: var(--gold);
  border: 1px solid rgba(250, 204, 21, 0.2);
}

.action-btn.minem:hover {
  background: rgba(250, 204, 21, 0.2);
  transform: translateY(-2px);
}

.action-btn.m2 {
  background: rgba(16, 185, 129, 0.1);
  color: var(--green);
  border: 1px solid rgba(16, 185, 129, 0.2);
}

.action-btn.m2:hover {
  background: rgba(16, 185, 129, 0.2);
  transform: translateY(-2px);
}

.action-btn.primary {
  background: var(--gradient-violet);
  color: white;
}

.action-btn.primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(139, 92, 246, 0.3);
}

/* Quick Actions */
.quick-actions {
  background: var(--bg-card);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 20px;
  padding: 35px;
  margin-bottom: 40px;
}

.actions-header {
  display: flex;
  align-items: center;
  gap: 15px;
  margin-bottom: 30px;
}

.actions-icon {
  font-size: 32px;
  background: var(--gradient-violet);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.actions-title {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 24px;
  font-weight: 600;
  color: var(--text-primary);
}

.actions-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 20px;
}

.quick-action-btn {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  padding: 25px;
  text-align: center;
  transition: all 0.3s ease;
  text-decoration: none;
  display: block;
}

.quick-action-btn:hover {
  transform: translateY(-5px);
  border-color: rgba(255, 255, 255, 0.2);
  background: rgba(255, 255, 255, 0.08);
}

.action-icon {
  font-size: 40px;
  margin-bottom: 15px;
  display: inline-block;
}

.action-icon.topup {
  color: var(--green);
  background: var(--gradient-green);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.action-icon.swap {
  color: var(--cyan);
  background: var(--gradient-cyan);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.action-icon.redeem {
  color: var(--orange);
  background: var(--gradient-orange);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.action-label {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 18px;
  font-weight: 600;
  color: var(--text-primary);
  margin-bottom: 8px;
}

.action-desc {
  color: var(--text-muted);
  font-size: 14px;
  font-family: 'Inter', sans-serif;
}

/* Swap Section */
.swap-section {
  background: var(--bg-card);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 20px;
  padding: 35px;
  margin-bottom: 40px;
}

.swap-header {
  display: flex;
  align-items: center;
  gap: 15px;
  margin-bottom: 25px;
}

.swap-icon {
  font-size: 32px;
  background: var(--gradient-cyan);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.swap-title {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 24px;
  font-weight: 600;
  color: var(--text-primary);
}

.swap-form {
  max-width: 600px;
  margin: 0 auto;
}

.swap-info {
  background: rgba(6, 182, 212, 0.1);
  border: 1px solid rgba(6, 182, 212, 0.2);
  border-radius: 16px;
  padding: 20px;
  margin-bottom: 25px;
  text-align: center;
}

.swap-rate {
  font-size: 20px;
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
  padding: 16px 20px;
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
  margin: 25px 0;
}

.preview-header {
  text-align: center;
  margin-bottom: 20px;
}

.preview-title {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 18px;
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
  font-size: 16px;
  font-weight: 700;
}

.preview-value.minem {
  color: var(--gold);
}

.preview-value.fee {
  color: var(--orange);
}

.preview-value.m2 {
  color: var(--green);
}

/* Swap Button */
.swap-btn {
  width: 100%;
  padding: 18px;
  background: var(--gradient-cyan);
  color: white;
  border: none;
  border-radius: 16px;
  font-family: 'Space Grotesk', sans-serif;
  font-size: 16px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
}

.swap-btn:hover:not(:disabled) {
  transform: translateY(-3px);
  box-shadow: 0 12px 25px rgba(6, 182, 212, 0.4);
}

.swap-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Recent Activity */
.activity-section {
  background: var(--bg-card);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 20px;
  padding: 35px;
}

.activity-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 25px;
}

.activity-title {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 24px;
  font-weight: 600;
  color: var(--text-primary);
}

.view-all {
  color: var(--cyan);
  text-decoration: none;
  font-size: 14px;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 5px;
}

.view-all:hover {
  text-decoration: underline;
}

.activity-list {
  display: flex;
  flex-direction: column;
  gap: 15px;
}

.activity-item {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  padding: 20px;
  display: flex;
  align-items: center;
  gap: 15px;
  transition: all 0.3s ease;
}

.activity-item:hover {
  background: rgba(255, 255, 255, 0.08);
  transform: translateX(5px);
}

.activity-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
}

.activity-icon.swap {
  background: rgba(6, 182, 212, 0.1);
  color: var(--cyan);
}

.activity-icon.topup {
  background: rgba(16, 185, 129, 0.1);
  color: var(--green);
}

.activity-icon.redeem {
  background: rgba(249, 115, 22, 0.1);
  color: var(--orange);
}

.activity-details {
  flex: 1;
}

.activity-title-small {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 16px;
  font-weight: 600;
  color: var(--text-primary);
  margin-bottom: 5px;
}

.activity-description {
  color: var(--text-muted);
  font-size: 14px;
  font-family: 'Inter', sans-serif;
}

.activity-amount {
  text-align: right;
}

.amount-value {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 18px;
  font-weight: 700;
  margin-bottom: 5px;
}

.amount-value.minem {
  color: var(--gold);
}

.amount-value.m2 {
  color: var(--green);
}

.activity-time {
  color: var(--text-muted);
  font-size: 12px;
}

.empty-activity {
  text-align: center;
  padding: 60px 20px;
  color: var(--text-muted);
}

.empty-activity i {
  font-size: 48px;
  margin-bottom: 20px;
  opacity: 0.5;
}

.empty-activity h3 {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 24px;
  margin-bottom: 10px;
  color: var(--text-secondary);
}

.empty-activity p {
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
  
  .total-value {
    font-size: 48px;
  }
  
  .balance-cards {
    grid-template-columns: 1fr;
  }
  
  .actions-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  
  .preview-grid {
    grid-template-columns: 1fr;
  }
  
  .activity-item {
    flex-direction: column;
    text-align: center;
    gap: 10px;
  }
  
  .activity-amount {
    text-align: center;
    width: 100%;
  }
}

@media (max-width: 480px) {
  .actions-grid {
    grid-template-columns: 1fr;
  }
  
  .action-buttons {
    grid-template-columns: 1fr;
  }
  
  .total-value {
    font-size: 36px;
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
        <a href="swap.php" class="nav-item">
            <i class="ph ph-arrows-left-right"></i>
            <span>Swap Tokens</span>
        </a>
        <a href="wallet.php" class="nav-item active">
            <i class="ph ph-wallet"></i>
            <span>Wallet</span>
        </a>
        <a href="topup.php" class="nav-item">
            <i class="ph ph-credit-card"></i>
            <span>Top Up</span>
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
            <h1>My Wallet</h1>
            <p class="text-muted">Manage your MINEM and m² tokens</p>
        </div>
    </div>
    
    <div class="wallet-container">
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
        
        <!-- Total Balance -->
        <div class="total-balance-card">
            <div class="total-label">
                <i class="ph ph-wallet"></i>
                <span>Total Portfolio Value</span>
            </div>
            <div class="total-value">$<?php echo number_format($totalUsdValue, 2); ?></div>
            <div class="total-subvalue">
                <?php echo number_format(($balance['minem'] ?? 0) + ($balance['m2'] ?? 0), 2); ?> tokens
            </div>
        </div>
        
        <!-- Balance Cards -->
        <div class="balance-cards">
            <!-- MINEM Card -->
            <div class="balance-card minem">
                <div class="card-header">
                    <div class="token-info">
                        <div class="token-icon minem">
                            <i class="ph ph-coins"></i>
                        </div>
                        <div class="token-name">
                            <h3>MINEM Token</h3>
                            <div class="token-symbol">MINEM</div>
                        </div>
                    </div>
                </div>
                
                <div class="balance-display">
                    <div class="token-balance minem">
                        <?php echo number_format($balance['minem'] ?? 0, 2); ?>
                    </div>
                    <div class="token-usd">
                        $<?php echo number_format($minemUsdValue, 2); ?> USD
                    </div>
                </div>
                
                <div class="action-buttons">
                    <a href="topup.php" class="action-btn minem">
                        <i class="ph ph-arrow-down-left"></i>
                        Top Up
                    </a>
                    <a href="swap.php" class="action-btn minem">
                        <i class="ph ph-arrows-left-right"></i>
                        Convert
                    </a>
                </div>
            </div>
            
            <!-- m² Card -->
            <div class="balance-card m2">
                <div class="card-header">
                    <div class="token-info">
                        <div class="token-icon m2">
                            <i class="ph ph-gem"></i>
                        </div>
                        <div class="token-name">
                            <h3>m² Token</h3>
                            <div class="token-symbol">m²</div>
                        </div>
                    </div>
                </div>
                
                <div class="balance-display">
                    <div class="token-balance m2">
                        <?php echo number_format($balance['m2'] ?? 0, 2); ?>
                    </div>
                    <div class="token-usd">
                        $<?php echo number_format($m2UsdValue, 2); ?> USD
                    </div>
                </div>
                
                <div class="action-buttons">
                    <a href="redeem.php" class="action-btn m2">
                        <i class="ph ph-bank"></i>
                        Redeem
                    </a>
                    <a href="swap.php" class="action-btn m2">
                        <i class="ph ph-arrows-left-right"></i>
                        Convert
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <div class="actions-header">
                <i class="ph ph-lightning actions-icon"></i>
                <h2 class="actions-title">Quick Actions</h2>
            </div>
            
            <div class="actions-grid">
                <a href="topup.php" class="quick-action-btn">
                    <div class="action-icon topup">
                        <i class="ph ph-arrow-down-left"></i>
                    </div>
                    <div class="action-label">Top Up MINEM</div>
                    <div class="action-desc">Add funds to your wallet</div>
                </a>
                
                <a href="#swap-section" class="quick-action-btn">
                    <div class="action-icon swap">
                        <i class="ph ph-arrows-left-right"></i>
                    </div>
                    <div class="action-label">Swap Tokens</div>
                    <div class="action-desc">Convert MINEM to m²</div>
                </a>
                
                <a href="redeem.php" class="quick-action-btn">
                    <div class="action-icon redeem">
                        <i class="ph ph-bank"></i>
                    </div>
                    <div class="action-label">Redeem m²</div>
                    <div class="action-desc">Cash out your earnings</div>
                </a>
            </div>
        </div>
        
        <!-- Swap Section -->
        <div class="swap-section" id="swap-section">
            <div class="swap-header">
                <i class="ph ph-arrows-left-right swap-icon"></i>
                <h2 class="swap-title">Swap MINEM to m²</h2>
            </div>
            
            <div class="swap-info">
                <div class="swap-rate">1 MINEM = 0.95 m²</div>
                <div class="swap-fee">(<?php echo SWAP_FEE_PERCENT; ?>% swap fee applied)</div>
            </div>
            
            <form method="POST" class="swap-form" onsubmit="return validateSwap()">
                <div class="form-group">
                    <label class="form-label">
                        <i class="ph ph-coins"></i>
                        MINEM Amount to Swap (Minimum: <?php echo number_format(MIN_SWAP_AMOUNT); ?> MINEM)
                    </label>
                    <div class="form-input-group">
                        <input type="number" 
                               name="minem_amount" 
                               id="minemAmount" 
                               class="form-input" 
                               value="<?php echo MIN_SWAP_AMOUNT; ?>" 
                               required
                               min="<?php echo MIN_SWAP_AMOUNT; ?>"
                               max="<?php echo $balance['minem'] ?? 0; ?>"
                               oninput="updateSwapSlider(this.value)">
                        <span class="token-label">MINEM</span>
                        <button type="button" class="max-btn" onclick="setMaxAmount()">MAX</button>
                    </div>
                    <input type="range" 
                           name="minem_amount_range" 
                           class="form-range" 
                           min="<?php echo MIN_SWAP_AMOUNT; ?>" 
                           max="<?php echo max($balance['minem'] ?? MIN_SWAP_AMOUNT, MIN_SWAP_AMOUNT * 10); ?>" 
                           step="10" 
                           value="<?php echo MIN_SWAP_AMOUNT; ?>"
                           oninput="updateMinemAmount(this.value)">
                    <div class="range-labels">
                        <span><?php echo number_format(MIN_SWAP_AMOUNT); ?> MINEM</span>
                        <span id="currentRangeValue"><?php echo number_format(MIN_SWAP_AMOUNT); ?> MINEM</span>
                        <span><?php echo number_format(max($balance['minem'] ?? MIN_SWAP_AMOUNT, MIN_SWAP_AMOUNT * 10)); ?> MINEM</span>
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
                            <div class="preview-value minem" id="previewSpend"><?php echo number_format(MIN_SWAP_AMOUNT); ?> MINEM</div>
                        </div>
                        <div class="preview-item">
                            <div class="preview-label">Swap Fee</div>
                            <div class="preview-value fee" id="previewFee"><?php echo number_format((MIN_SWAP_AMOUNT * SWAP_FEE_PERCENT) / 100, 2); ?> MINEM</div>
                        </div>
                        <div class="preview-item">
                            <div class="preview-label">You Receive</div>
                            <div class="preview-value m2" id="previewReceive">
                                <?php 
                                $m2Received = MIN_SWAP_AMOUNT * (1 - SWAP_FEE_PERCENT/100);
                                echo number_format($m2Received, 2); 
                                ?> m²
                            </div>
                        </div>
                    </div>
                </div>
                
                <button type="submit" 
                        name="swap_tokens" 
                        class="swap-btn"
                        <?php echo ($balance['minem'] ?? 0) < MIN_SWAP_AMOUNT ? 'disabled' : ''; ?>>
                    <i class="ph ph-arrows-left-right"></i>
                    Swap MINEM to m²
                </button>
            </form>
        </div>
        
        <!-- Recent Activity -->
        <div class="activity-section">
            <div class="activity-header">
                <h2 class="activity-title">Recent Activity</h2>
                <a href="swap.php" class="view-all">
                    <span>View All</span>
                    <i class="ph ph-arrow-right"></i>
                </a>
            </div>
            
            <?php if (empty($swapHistory)): ?>
            <div class="empty-activity">
                <i class="ph ph-clock"></i>
                <h3>No Recent Activity</h3>
                <p>Your swap and transaction history will appear here.</p>
            </div>
            <?php else: ?>
            <div class="activity-list">
                <?php foreach ($swapHistory as $swap): 
                    if (isset($swap['swap_type']) && $swap['swap_type'] === 'minem_to_m2'): 
                        $minemSpent = floatval($swap['minem_spent'] ?? $swap['m2_spent']); // Adjusted for backward compatibility
                        $m2Received = floatval($swap['m2_received'] ?? $swap['minem_received']); // Adjusted for backward compatibility
                ?>
                <div class="activity-item">
                    <div class="activity-icon swap">
                        <i class="ph ph-arrows-left-right"></i>
                    </div>
                    <div class="activity-details">
                        <div class="activity-title-small">MINEM to m² Swap</div>
                        <div class="activity-description">Converted MINEM tokens to m²</div>
                    </div>
                    <div class="activity-amount">
                        <div class="amount-value m2">+<?php echo number_format($m2Received, 2); ?> m²</div>
                        <div class="activity-time"><?php echo date('M d, Y H:i', strtotime($swap['created_at'])); ?></div>
                    </div>
                </div>
                <?php else: 
                    $m2Spent = floatval($swap['m2_spent']);
                    $minemReceived = floatval($swap['minem_received']);
                ?>
                <div class="activity-item">
                    <div class="activity-icon swap">
                        <i class="ph ph-arrows-left-right"></i>
                    </div>
                    <div class="activity-details">
                        <div class="activity-title-small">m² to MINEM Swap</div>
                        <div class="activity-description">Converted m² tokens to MINEM</div>
                    </div>
                    <div class="activity-amount">
                        <div class="amount-value minem">+<?php echo number_format($minemReceived, 2); ?> MINEM</div>
                        <div class="activity-time"><?php echo date('M d, Y H:i', strtotime($swap['created_at'])); ?></div>
                    </div>
                </div>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Constants
const MIN_SWAP_AMOUNT = <?php echo MIN_SWAP_AMOUNT; ?>;
const SWAP_FEE_PERCENT = <?php echo SWAP_FEE_PERCENT; ?>;
const USER_MINEM_BALANCE = <?php echo $balance['minem'] ?? 0; ?>;

// Update swap preview
function updateSwapPreview(minemAmount) {
    const feeAmount = (minemAmount * SWAP_FEE_PERCENT) / 100;
    const m2Received = minemAmount - feeAmount;
    
    // Update preview elements
    document.getElementById('previewSpend').textContent = minemAmount.toLocaleString() + ' MINEM';
    document.getElementById('previewFee').textContent = feeAmount.toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }) + ' MINEM';
    document.getElementById('previewReceive').textContent = m2Received.toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }) + ' m²';
}

// Update MINEM amount from slider
function updateMinemAmount(value) {
    const minemAmount = parseInt(value);
    document.getElementById('currentRangeValue').textContent = minemAmount.toLocaleString() + ' MINEM';
    document.getElementById('minemAmount').value = minemAmount;
    updateSwapPreview(minemAmount);
}

// Update slider from input
function updateSwapSlider(value) {
    const minemAmount = Math.max(MIN_SWAP_AMOUNT, Math.min(value, USER_MINEM_BALANCE));
    document.querySelector('input[name="minem_amount_range"]').value = minemAmount;
    document.getElementById('currentRangeValue').textContent = minemAmount.toLocaleString() + ' MINEM';
    updateSwapPreview(minemAmount);
}

// Set maximum amount
function setMaxAmount() {
    const maxAmount = USER_MINEM_BALANCE;
    document.getElementById('minemAmount').value = maxAmount;
    updateSwapSlider(maxAmount);
}

// Validate swap
function validateSwap() {
    const minemAmount = parseFloat(document.getElementById('minemAmount').value);
    const balance = USER_MINEM_BALANCE;
    
    if (minemAmount < MIN_SWAP_AMOUNT) {
        alert('Minimum swap amount is ' + MIN_SWAP_AMOUNT.toLocaleString() + ' MINEM.');
        return false;
    }
    
    if (minemAmount > balance) {
        alert('Insufficient MINEM balance. Available: ' + balance.toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }) + ' MINEM');
        return false;
    }
    
    const feeAmount = (minemAmount * SWAP_FEE_PERCENT) / 100;
    const m2Received = minemAmount - feeAmount;
    
    return confirm(
        'Are you sure you want to swap?\n\n' +
        '• You spend: ' + minemAmount.toLocaleString() + ' MINEM\n' +
        '• Swap fee: ' + feeAmount.toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }) + ' MINEM (' + SWAP_FEE_PERCENT + '%)\n' +
        '• You receive: ' + m2Received.toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }) + ' m²'
    );
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Set max value for range slider
    const rangeSlider = document.querySelector('input[name="minem_amount_range"]');
    const maxValue = Math.max(USER_MINEM_BALANCE, MIN_SWAP_AMOUNT * 10);
    rangeSlider.max = maxValue;
    
    // Update max label
    document.querySelectorAll('.range-labels span')[2].textContent = maxValue.toLocaleString() + ' MINEM';
    
    // Initial preview update
    updateSwapPreview(MIN_SWAP_AMOUNT);
    
    // Smooth scroll for quick action links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href');
            if (targetId === '#') return;
            
            const targetElement = document.querySelector(targetId);
            if (targetElement) {
                window.scrollTo({
                    top: targetElement.offsetTop - 100,
                    behavior: 'smooth'
                });
            }
        });
    });
});
</script>
</body>
</html>