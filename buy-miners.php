<?php
// buy-miners.php - Purchase Miners with MINEM
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
define('MIN_MINEM', 300000); // Minimum MINEM to buy a miner ($0.30)
define('MINEM_PER_DOLLAR', 1000000); // 1,000,000 MINEM = $1
define('WATTS_PER_DOLLAR', 10); // 10 W/h per $1
define('MONTHLY_REWARD_RATE', 0.19); // 19% monthly reward rate
define('THS_PER_DOLLAR', 1.0); // 1 TH/s per $1

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

// Function to calculate miner specs
function calculateMinerSpecs($minemAmount) {
    $usdValue = $minemAmount / MINEM_PER_DOLLAR;
    $energyUsageWh = $usdValue * WATTS_PER_DOLLAR;
    $hashpowerThs = $usdValue * THS_PER_DOLLAR;
    $monthlyRewardM2 = $minemAmount * MONTHLY_REWARD_RATE * (30/365);
    
    return [
        'usd_value' => $usdValue,
        'energy_usage_wh' => $energyUsageWh,
        'hashpower_ths' => $hashpowerThs,
        'monthly_reward_m2' => $monthlyRewardM2
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

// Get user's existing miners
$userMiners = [];
$minersResponse = supabaseRequest('/rest/v1/user_miners?user_id=eq.' . $user_id . '&select=*,miner_types(name)');
if ($minersResponse['status'] === 200) {
    $userMiners = $minersResponse['data'];
}

// Calculate totals
$totalHashpower = 0;
$totalMinemInvested = 0;
foreach ($userMiners as $miner) {
    $totalHashpower += floatval($miner['hashpower_ths']);
    $totalMinemInvested += ($miner['usd_value'] * MINEM_PER_DOLLAR);
}

// Handle miner purchase
$purchaseSuccess = false;
$purchaseMessage = '';
$purchasedMiner = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purchase_miner'])) {
    $minemAmount = floatval($_POST['minem_amount'] ?? 0);
    $minerName = trim($_POST['miner_name'] ?? 'Custom Miner');
    
    // Validate input
    if ($minemAmount < MIN_MINEM) {
        $purchaseMessage = 'Minimum purchase amount is ' . number_format(MIN_MINEM) . ' MINEM ($0.30).';
    } elseif ($minemAmount > ($balance['minem'] ?? 0)) {
        $purchaseMessage = 'Insufficient MINEM balance. Available: ' . number_format($balance['minem'] ?? 0, 2) . ' MINEM';
    } else {
        // Calculate miner specifications
        $specs = calculateMinerSpecs($minemAmount);
        
        // Step 1: Check if miner type already exists
        $minerTypeId = null;
        $typeCheckResponse = supabaseRequest('/rest/v1/miner_types?name=eq.' . urlencode($minerName) . '&min_minem=eq.' . $minemAmount . '&max_minem=eq.' . $minemAmount);
        
        if ($typeCheckResponse['status'] === 200 && !empty($typeCheckResponse['data'])) {
            $minerTypeId = $typeCheckResponse['data'][0]['id'];
        } else {
            // Create new miner type
            $minerTypeData = [
                'name' => $minerName,
                'min_usd' => $specs['usd_value'],
                'max_usd' => $specs['usd_value'],
                'min_minem' => $minemAmount,
                'max_minem' => $minemAmount,
                'energy_per_usd' => intval(WATTS_PER_DOLLAR)
            ];
            
            $typeResponse = supabaseRequest('/rest/v1/miner_types', 'POST', $minerTypeData, true);
            
            if ($typeResponse['status'] === 201 && !empty($typeResponse['data'])) {
                $minerTypeId = $typeResponse['data'][0]['id'];
            } elseif ($typeResponse['status'] === 201) {
                // If no data in response, try to get the created type
                $typeGetResponse = supabaseRequest('/rest/v1/miner_types?name=eq.' . urlencode($minerName) . '&min_minem=eq.' . $minemAmount . '&max_minem=eq.' . $minemAmount);
                if ($typeGetResponse['status'] === 200 && !empty($typeGetResponse['data'])) {
                    $minerTypeId = $typeGetResponse['data'][0]['id'];
                } else {
                    $purchaseMessage = 'Failed to create miner type. Please try again.';
                }
            } else {
                $purchaseMessage = 'Failed to create miner type. HTTP Code: ' . $typeResponse['status'];
            }
        }
        
        if ($minerTypeId) {
            // Step 2: Create user miner
            $minerData = [
                'user_id' => $user_id,
                'miner_type_id' => $minerTypeId,
                'usd_value' => $specs['usd_value'],
                'hashpower_ths' => $specs['hashpower_ths'],
                'energy_usage_wh' => $specs['energy_usage_wh']
            ];
            
            $minerResponse = supabaseRequest('/rest/v1/user_miners', 'POST', $minerData, true);
            
            if ($minerResponse['status'] === 201 || $minerResponse['status'] === 200) {
                // Step 3: Update user balance
                $newMinemBalance = floatval($balance['minem'] ?? 0) - $minemAmount;
                $updateData = [
                    'minem' => $newMinemBalance,
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                $balanceResponse = supabaseRequest('/rest/v1/balances?user_id=eq.' . $user_id, 'PATCH', $updateData, true);
                
                if ($balanceResponse['status'] === 204 || $balanceResponse['status'] === 200) {
                    $purchaseSuccess = true;
                    $purchaseMessage = 'Miner purchased successfully! Monthly reward: ' . number_format($specs['monthly_reward_m2'], 2) . ' m²';
                    
                    // Store purchased miner data for display
                    $purchasedMiner = [
                        'name' => $minerName,
                        'minem_cost' => $minemAmount,
                        'usd_value' => $specs['usd_value'],
                        'hashpower' => $specs['hashpower_ths'],
                        'energy_usage' => $specs['energy_usage_wh'],
                        'monthly_reward' => $specs['monthly_reward_m2']
                    ];
                    
                    // Update balance
                    $balance['minem'] = $newMinemBalance;
                    
                    // Refresh user's miners list
                    $minersResponse = supabaseRequest('/rest/v1/user_miners?user_id=eq.' . $user_id . '&select=*,miner_types(name)');
                    if ($minersResponse['status'] === 200) {
                        $userMiners = $minersResponse['data'];
                        $totalHashpower = 0;
                        $totalMinemInvested = 0;
                        foreach ($userMiners as $miner) {
                            $totalHashpower += floatval($miner['hashpower_ths']);
                            $totalMinemInvested += ($miner['usd_value'] * MINEM_PER_DOLLAR);
                        }
                    }
                    
                    // Redirect to prevent form resubmission
                    header('Location: buy-miners.php?success=true&minem=' . $minemAmount . '&name=' . urlencode($minerName));
                    exit();
                } else {
                    // Rollback: Delete the miner
                    if (!empty($minerResponse['data'][0]['id'])) {
                        supabaseRequest('/rest/v1/user_miners?id=eq.' . $minerResponse['data'][0]['id'], 'DELETE', null, true);
                    }
                    $purchaseMessage = 'Failed to update balance. Please contact support.';
                }
            } else {
                $purchaseMessage = 'Failed to create miner. HTTP Code: ' . $minerResponse['status'];
            }
        }
    }
}

// Check for success redirect
if (isset($_GET['success']) && $_GET['success'] === 'true') {
    $purchaseSuccess = true;
    $purchasedMinem = $_GET['minem'] ?? 0;
    $purchasedName = $_GET['name'] ?? 'Custom Miner';
    $specs = calculateMinerSpecs(floatval($purchasedMinem));
    
    $purchasedMiner = [
        'name' => $purchasedName,
        'minem_cost' => $purchasedMinem,
        'usd_value' => $specs['usd_value'],
        'hashpower' => $specs['hashpower_ths'],
        'energy_usage' => $specs['energy_usage_wh'],
        'monthly_reward' => $specs['monthly_reward_m2']
    ];
    
    $purchaseMessage = 'Miner purchased successfully! Monthly reward: ' . number_format($specs['monthly_reward_m2'], 2) . ' m²';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Buy Miners - MineMechanics</title>
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
  --violet: #8B5CF6;
  --orange: #F97316;
  --green: #10B981;
  --blue: #3B82F6;
  --gold: #FACC15;
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
  background: var(--gradient-green);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

/* Buy Container */
.buy-container {
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

.balance-card.hashpower::before {
  background: var(--gradient-blue);
}

.balance-card.m2::before {
  background: var(--gradient-green);
}

.balance-card.invested::before {
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

.hashpower-balance {
  background: var(--gradient-blue);
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

.invested-balance {
  background: var(--gradient-violet);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

/* Purchase Section */
.purchase-section {
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
  background: var(--gradient-gold);
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

/* Purchase Form */
.purchase-form {
  max-width: 600px;
  margin: 0 auto;
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

.form-input {
  width: 100%;
  padding: 15px 20px;
  background: rgba(0, 0, 0, 0.3);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  color: var(--text-primary);
  font-size: 16px;
  transition: all 0.3s ease;
}

.form-input:focus {
  outline: none;
  border-color: var(--violet);
  box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
}

.form-range {
  width: 100%;
  height: 8px;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 4px;
  outline: none;
  -webkit-appearance: none;
}

.form-range::-webkit-slider-thumb {
  -webkit-appearance: none;
  width: 24px;
  height: 24px;
  background: var(--gradient-gold);
  border-radius: 50%;
  cursor: pointer;
}

.form-range::-moz-range-thumb {
  width: 24px;
  height: 24px;
  background: var(--gradient-gold);
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

/* Miner Preview */
.miner-preview {
  background: rgba(59, 130, 246, 0.1);
  border: 1px solid rgba(59, 130, 246, 0.2);
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

.preview-value.minem {
  color: var(--gold);
}

.preview-value.usd {
  color: var(--green);
}

.preview-value.energy {
  color: var(--orange);
}

.preview-value.reward {
  color: var(--blue);
}

/* Purchase Button */
.purchase-btn {
  width: 100%;
  padding: 20px;
  background: var(--gradient-green);
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

.purchase-btn:hover:not(:disabled) {
  transform: translateY(-3px);
  box-shadow: 0 15px 30px rgba(16, 185, 129, 0.4);
}

.purchase-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Your Miners Section */
.miners-section {
  background: var(--bg-card);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 20px;
  padding: 35px;
}

.miners-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 25px;
}

.miners-title {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 24px;
  font-weight: 600;
  color: var(--text-primary);
}

.miners-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 20px;
}

.miner-card {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  padding: 25px;
  position: relative;
  overflow: hidden;
  transition: all 0.3s ease;
}

.miner-card:hover {
  transform: translateY(-5px);
  border-color: rgba(139, 92, 246, 0.3);
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
}

.miner-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 4px;
  background: var(--gradient-violet);
}

.miner-name {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 18px;
  font-weight: 600;
  margin-bottom: 15px;
  color: var(--text-primary);
}

.miner-specs {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
  margin-bottom: 20px;
}

.miner-spec {
  display: flex;
  flex-direction: column;
}

.spec-label {
  color: var(--text-muted);
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 1px;
  margin-bottom: 4px;
}

.spec-value {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 14px;
  font-weight: 600;
}

.miner-reward {
  background: rgba(16, 185, 129, 0.1);
  border: 1px solid rgba(16, 185, 129, 0.2);
  border-radius: 12px;
  padding: 12px;
  text-align: center;
}

.reward-label {
  color: var(--green);
  font-size: 12px;
  margin-bottom: 5px;
}

.reward-value {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 16px;
  font-weight: 700;
  color: var(--text-primary);
}

.empty-miners {
  grid-column: 1 / -1;
  text-align: center;
  padding: 60px 20px;
  color: var(--text-muted);
}

.empty-miners i {
  font-size: 48px;
  margin-bottom: 20px;
  opacity: 0.5;
}

.empty-miners h3 {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 24px;
  margin-bottom: 10px;
  color: var(--text-secondary);
}

.empty-miners p {
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
  
  .miners-grid {
    grid-template-columns: 1fr;
  }
  
  .purchase-section {
    padding: 25px;
  }
}

@media (max-width: 480px) {
  .balance-display {
    grid-template-columns: 1fr;
  }
  
  .preview-grid {
    grid-template-columns: 1fr;
  }
  
  .miner-specs {
    grid-template-columns: 1fr;
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
        <a href="buy-miners.php" class="nav-item active">
            <i class="ph ph-rocket-launch"></i>
            <span>Buy Miners</span>
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
            <h1>Buy Miners</h1>
            <p class="text-muted">Purchase miners with MINEM tokens to start earning m² rewards</p>
        </div>
    </div>
    
    <div class="buy-container">
        <!-- Alert Messages -->
        <?php if ($purchaseSuccess && !empty($purchaseMessage)): ?>
        <div class="alert alert-success">
            <i class="ph ph-check-circle-fill"></i>
            <span><?php echo htmlspecialchars($purchaseMessage); ?></span>
        </div>
        <?php elseif (!empty($purchaseMessage)): ?>
        <div class="alert alert-error">
            <i class="ph ph-warning-circle-fill"></i>
            <span><?php echo htmlspecialchars($purchaseMessage); ?></span>
        </div>
        <?php endif; ?>
        
        <!-- Balance Display -->
        <div class="balance-display">
            <div class="balance-card minem">
                <div class="balance-label">
                    <i class="ph ph-coins"></i>
                    <span>MINEM Balance</span>
                </div>
                <div class="balance-value minem-balance">
                    <?php echo number_format($balance['minem'] ?? 0, 2); ?>
                </div>
            </div>
            
            <div class="balance-card hashpower">
                <div class="balance-label">
                    <i class="ph ph-cpu"></i>
                    <span>Total Hashpower</span>
                </div>
                <div class="balance-value hashpower-balance">
                    <?php echo number_format($totalHashpower, 2); ?> TH/s
                </div>
            </div>
            
            <div class="balance-card m2">
                <div class="balance-label">
                    <i class="ph ph-gem"></i>
                    <span>m² Generated</span>
                </div>
                <div class="balance-value m2-balance">
                    <?php echo number_format($balance['m2'] ?? 0, 2); ?>
                </div>
            </div>
            
            <div class="balance-card invested">
                <div class="balance-label">
                    <i class="ph ph-trend-up"></i>
                    <span>Total Invested</span>
                </div>
                <div class="balance-value invested-balance">
                    <?php echo number_format($totalMinemInvested, 2); ?> MINEM
                </div>
            </div>
        </div>
        
        <!-- Purchase Section -->
        <div class="purchase-section">
            <div class="section-header">
                <i class="ph ph-rocket-launch section-icon"></i>
                <h2 class="section-title">Purchase New Miner</h2>
            </div>
            
            <form method="POST" class="purchase-form" onsubmit="return validatePurchase()">
                <div class="form-group">
                    <label class="form-label">
                        <i class="ph ph-tag"></i>
                        Miner Name
                    </label>
                    <input type="text" 
                           name="miner_name" 
                           class="form-input" 
                           value="Custom Miner" 
                           required
                           placeholder="Give your miner a name">
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="ph ph-coins"></i>
                        MINEM Amount (Minimum: <?php echo number_format(MIN_MINEM); ?> MINEM)
                    </label>
                    <input type="range" 
                           name="minem_amount_range" 
                           class="form-range" 
                           min="<?php echo MIN_MINEM; ?>" 
                           max="<?php echo max($balance['minem'] ?? MIN_MINEM, MIN_MINEM * 10); ?>" 
                           step="10000" 
                           value="<?php echo MIN_MINEM; ?>"
                           oninput="updateMinemAmount(this.value)">
                    <div class="range-labels">
                        <span><?php echo number_format(MIN_MINEM); ?> MINEM</span>
                        <span id="currentRangeValue"><?php echo number_format(MIN_MINEM); ?> MINEM</span>
                        <span><?php echo number_format(max($balance['minem'] ?? MIN_MINEM, MIN_MINEM * 10)); ?> MINEM</span>
                    </div>
                    <input type="number" 
                           name="minem_amount" 
                           id="minemAmount" 
                           class="form-input" 
                           value="<?php echo MIN_MINEM; ?>" 
                           required
                           min="<?php echo MIN_MINEM; ?>"
                           max="<?php echo $balance['minem'] ?? 0; ?>"
                           oninput="updateMinemSlider(this.value)">
                </div>
                
                <!-- Miner Preview -->
                <div class="miner-preview" id="minerPreview">
                    <div class="preview-header">
                        <h3 class="preview-title">Miner Specifications Preview</h3>
                    </div>
                    <div class="preview-grid">
                        <div class="preview-item">
                            <div class="preview-label">Cost</div>
                            <div class="preview-value minem" id="previewCost"><?php echo number_format(MIN_MINEM); ?> MINEM</div>
                        </div>
                        <div class="preview-item">
                            <div class="preview-label">USD Value</div>
                            <div class="preview-value usd" id="previewUsd">$0.30</div>
                        </div>
                        <div class="preview-item">
                            <div class="preview-label">Hashpower</div>
                            <div class="preview-value" id="previewHash">0.30 TH/s</div>
                        </div>
                        <div class="preview-item">
                            <div class="preview-label">Energy Usage</div>
                            <div class="preview-value energy" id="previewEnergy">3.0 W/h</div>
                        </div>
                        <div class="preview-item">
                            <div class="preview-label">Monthly m² Reward</div>
                            <div class="preview-value reward" id="previewReward">46.85 m²</div>
                        </div>
                    </div>
                </div>
                
                <button type="submit" 
                        name="purchase_miner" 
                        class="purchase-btn"
                        <?php echo ($balance['minem'] ?? 0) < MIN_MINEM ? 'disabled' : ''; ?>>
                    <i class="ph ph-shopping-cart"></i>
                    Purchase Miner
                </button>
            </form>
        </div>
        
        <!-- Your Miners Section -->
        <div class="miners-section">
            <div class="miners-header">
                <h2 class="miners-title">
                    <i class="ph ph-cube"></i>
                    Your Miners (<?php echo count($userMiners); ?>)
                </h2>
            </div>
            
            <?php if (empty($userMiners)): ?>
            <div class="empty-miners">
                <i class="ph ph-miner"></i>
                <h3>No Miners Yet</h3>
                <p>Purchase your first miner above to start earning m² rewards!</p>
            </div>
            <?php else: ?>
            <div class="miners-grid">
                <?php foreach ($userMiners as $miner): ?>
                <?php 
                $minerName = $miner['miner_types']['name'] ?? 'Unnamed Miner';
                $usdValue = floatval($miner['usd_value']);
                $hashpower = floatval($miner['hashpower_ths']);
                $energyUsage = floatval($miner['energy_usage_wh']);
                $monthlyReward = $usdValue * MINEM_PER_DOLLAR * MONTHLY_REWARD_RATE * (30/365);
                ?>
                <div class="miner-card">
                    <h3 class="miner-name"><?php echo htmlspecialchars($minerName); ?></h3>
                    
                    <div class="miner-specs">
                        <div class="miner-spec">
                            <span class="spec-label">USD Value</span>
                            <span class="spec-value">$<?php echo number_format($usdValue, 2); ?></span>
                        </div>
                        <div class="miner-spec">
                            <span class="spec-label">Hashpower</span>
                            <span class="spec-value"><?php echo number_format($hashpower, 2); ?> TH/s</span>
                        </div>
                        <div class="miner-spec">
                            <span class="spec-label">Energy Usage</span>
                            <span class="spec-value"><?php echo number_format($energyUsage, 1); ?> W/h</span>
                        </div>
                        <div class="miner-spec">
                            <span class="spec-label">Created</span>
                            <span class="spec-value"><?php echo date('M d, Y', strtotime($miner['created_at'])); ?></span>
                        </div>
                    </div>
                    
                    <div class="miner-reward">
                        <div class="reward-label">Monthly m² Reward</div>
                        <div class="reward-value"><?php echo number_format($monthlyReward, 2); ?> m²</div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Constants
const MIN_MINEM = <?php echo MIN_MINEM; ?>;
const MINEM_PER_DOLLAR = <?php echo MINEM_PER_DOLLAR; ?>;
const WATTS_PER_DOLLAR = <?php echo WATTS_PER_DOLLAR; ?>;
const MONTHLY_REWARD_RATE = <?php echo MONTHLY_REWARD_RATE; ?>;
const THS_PER_DOLLAR = <?php echo THS_PER_DOLLAR; ?>;
const USER_BALANCE = <?php echo $balance['minem'] ?? 0; ?>;

// Update miner preview based on MINEM amount
function updateMinerPreview(minemAmount) {
    const usdValue = minemAmount / MINEM_PER_DOLLAR;
    const energyUsageWh = usdValue * WATTS_PER_DOLLAR;
    const hashpowerThs = usdValue * THS_PER_DOLLAR;
    const monthlyRewardM2 = minemAmount * MONTHLY_REWARD_RATE * (30/365);
    
    // Update preview elements
    document.getElementById('previewCost').textContent = minemAmount.toLocaleString() + ' MINEM';
    document.getElementById('previewUsd').textContent = '$' + usdValue.toFixed(2);
    document.getElementById('previewHash').textContent = hashpowerThs.toFixed(2) + ' TH/s';
    document.getElementById('previewEnergy').textContent = energyUsageWh.toFixed(1) + ' W/h';
    document.getElementById('previewReward').textContent = monthlyRewardM2.toFixed(2) + ' m²';
}

// Update range slider value display
function updateMinemAmount(value) {
    const minemAmount = parseInt(value);
    document.getElementById('currentRangeValue').textContent = minemAmount.toLocaleString() + ' MINEM';
    document.getElementById('minemAmount').value = minemAmount;
    updateMinerPreview(minemAmount);
}

// Update range slider from input
function updateMinemSlider(value) {
    const minemAmount = Math.max(MIN_MINEM, Math.min(value, USER_BALANCE));
    document.querySelector('input[name="minem_amount_range"]').value = minemAmount;
    document.getElementById('currentRangeValue').textContent = minemAmount.toLocaleString() + ' MINEM';
    updateMinerPreview(minemAmount);
}

// Validate purchase
function validatePurchase() {
    const minemAmount = parseFloat(document.getElementById('minemAmount').value);
    const balance = USER_BALANCE;
    
    if (minemAmount < MIN_MINEM) {
        alert('Minimum purchase amount is ' + MIN_MINEM.toLocaleString() + ' MINEM ($0.30).');
        return false;
    }
    
    if (minemAmount > balance) {
        alert('Insufficient MINEM balance. Available: ' + balance.toLocaleString() + ' MINEM');
        return false;
    }
    
    return confirm('Are you sure you want to purchase this miner for ' + minemAmount.toLocaleString() + ' MINEM?');
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Set max value for range slider
    const rangeSlider = document.querySelector('input[name="minem_amount_range"]');
    const maxValue = Math.max(USER_BALANCE, MIN_MINEM * 10);
    rangeSlider.max = maxValue;
    
    // Update max label
    document.querySelector('.range-labels span:last-child').textContent = maxValue.toLocaleString() + ' MINEM';
    
    // Initial preview update
    updateMinerPreview(MIN_MINEM);
});
</script>
</body>
</html>