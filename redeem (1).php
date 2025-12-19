<?php
// redeem.php - Redeem/Withdraw Funds
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
    } elseif ($method === 'PATCH') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'status' => $httpCode,
        'data' => json_decode($response, true)
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

// Convert m2 to USD (assuming 1 m² = $1 USD)
$usd_balance = $balance['m2'] ?? 0;

// Available coins for different methods
$faucetPay_coins = ['BTC', 'ETH', 'DOGE', 'POL', 'XMR', 'XLM', 'ADA', 'TRX', 'BNB', 'SOL', 'XRP', 'PEPE'];
$directWallet_coins = ['BTC', 'ETH', 'DOGE', 'TRX', 'USDT(BEP20)', 'USDT(TRC20)', 'USDT(ERC20)', 'USDT(SOL)', 'XLM', 'ADA', 'DOT', 'ZEC', 'FLOKI', 'SHIBA', 'PEPE', 'SOL', 'BNB', 'NOT'];
$giftCards = [
    'DOORDASH' => ['min' => 15, 'max' => 150],
    'INSTACART' => ['min' => 25, 'max' => 250],
    'UBER' => ['min' => 15, 'max' => 300],
    'UBER_EATS' => ['min' => 15, 'max' => 200],
    'ADIDAS' => ['min' => 5, 'max' => 300],
    'STEAM' => ['min' => 5, 'max' => 100],
    'AIRBNB' => ['min' => 25, 'max' => 60],
    'GOOGLE_PLAY' => ['min' => 5, 'max' => 200],
    'GROUPON' => ['min' => 10, 'max' => 200],
    'STARBUCKS' => ['min' => 5, 'max' => 100],
    'ROBLOX' => ['min' => 10, 'max' => 100],
    'IKEA' => ['min' => 25, 'max' => 1000]
];

// Handle redeem request
$redeemSuccess = false;
$redeemMessage = '';
$selectedMethod = '';
$fee = 0;
$netAmount = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['redeem_submit'])) {
    $method = $_POST['method'] ?? '';
    $amount = floatval($_POST['amount'] ?? 0);
    $address = trim($_POST['address'] ?? '');
    $coin = $_POST['coin'] ?? '';
    $giftCardType = $_POST['gift_card_type'] ?? '';
    
    // Validate amount
    if ($amount <= 0) {
        $redeemMessage = 'Please enter a valid amount.';
    } elseif ($amount > $usd_balance) {
        $redeemMessage = 'Insufficient balance. Available: $' . number_format($usd_balance, 2);
    } else {
        try {
            // Calculate fees based on method
            switch ($method) {
                case 'faucetpay':
                    if ($amount < 0.02) {
                        $redeemMessage = 'Minimum amount for FaucetPay is $0.02';
                        break;
                    }
                    $fee = 0;
                    $netAmount = $amount;
                    $selectedMethod = 'FaucetPay';
                    break;
                    
                case 'direct_wallet':
                    if ($amount < 5) {
                        $redeemMessage = 'Minimum amount for Direct Wallet is $5';
                        break;
                    }
                    $fee = 0.05 + ($amount * 0.006); // $0.05 + 0.6%
                    $netAmount = $amount - $fee;
                    $selectedMethod = 'Direct Wallet';
                    break;
                    
                case 'tonkeeper':
                    if ($amount < 2) {
                        $redeemMessage = 'Minimum amount for Tonkeeper is $2';
                        break;
                    }
                    if ($coin !== 'TON') {
                        $redeemMessage = 'Tonkeeper only supports TON coin';
                        break;
                    }
                    $fee = 0.05;
                    $netAmount = $amount - $fee;
                    $selectedMethod = 'Tonkeeper';
                    break;
                    
                case 'bitcoin_email':
                    if ($amount < 15) {
                        $redeemMessage = 'Minimum amount for Bitcoin by Email is $15';
                        break;
                    }
                    if (!filter_var($address, FILTER_VALIDATE_EMAIL) || !str_contains($address, '@protonmail.com') && !str_contains($address, '@proton.me')) {
                        $redeemMessage = 'Only Proton Mail addresses are accepted for Bitcoin by Email';
                        break;
                    }
                    $fee = 0.05 + ($amount * 0.001); // $0.05 + 0.1%
                    $netAmount = $amount - $fee;
                    $selectedMethod = 'Bitcoin by Email';
                    break;
                    
                case 'gift_card':
                    if (!isset($giftCards[$giftCardType])) {
                        $redeemMessage = 'Invalid gift card type';
                        break;
                    }
                    
                    $cardInfo = $giftCards[$giftCardType];
                    if ($amount < $cardInfo['min'] || $amount > $cardInfo['max']) {
                        $redeemMessage = "Amount must be between $" . $cardInfo['min'] . " and $" . $cardInfo['max'] . " for " . ucfirst(strtolower($giftCardType));
                        break;
                    }
                    
                    // Check if user is from US (simplified check - you might want to add country field to profiles)
                    $isUSUser = true; // Assuming all users can access for now
                    
                    if ($giftCardType === 'STEAM') {
                        // Steam special fee: $0.05 per $1
                        $fee = $amount * 0.05;
                    } else {
                        $fee = $isUSUser ? 0.10 : 0; // Only US users get $0.10 fee
                    }
                    
                    $netAmount = $amount - $fee;
                    $selectedMethod = ucfirst(strtolower($giftCardType)) . ' Gift Card';
                    break;
                    
                default:
                    $redeemMessage = 'Invalid redemption method';
                    break;
            }
            
            // If no error message, proceed with redemption
            if (empty($redeemMessage) && $netAmount > 0) {
                // Prepare redemption data
                $redeemData = [
                    'user_id' => $user_id,
                    'method' => $selectedMethod,
                    'coin' => $coin,
                    'address_or_email' => $address,
                    'amount_usd' => $amount,
                    'fee' => $fee,
                     
                ];
                
                if ($method === 'gift_card') {
                    $redeemData['gift_card_type'] = $giftCardType;
                }
                
                // Save redemption request to database
                $redeemResponse = supabaseRequest('/rest/v1/redeem_requests', 'POST', $redeemData, true);
                
                if ($redeemResponse['status'] === 201 || $redeemResponse['status'] === 200) {
                    // Update user balance (subtract redeemed amount)
                    $newM2Balance = $usd_balance - $amount;
                    $updateData = [
                        'm2' => $newM2Balance,
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    
                    $balanceResponse = supabaseRequest('/rest/v1/balances?user_id=eq.' . $user_id, 'PATCH', $updateData, true);
                    
                    if ($balanceResponse['status'] === 204 || $balanceResponse['status'] === 200) {
                        // Store success data in session for success page
                        $_SESSION['redeem_success_data'] = [
                            'method' => $selectedMethod,
                            'amount' => $amount,
                            'fee' => $fee,
                            'net_amount' => $netAmount,
                            'coin' => $coin,
                            'address' => $address,
                            'timestamp' => date('Y-m-d H:i:s')
                        ];
                        
                        // Redirect to success page
                        header('Location: redeem-successful.php');
                        exit();
                    } else {
                        $redeemMessage = 'Failed to update balance. Please contact support.';
                    }
                } else {
                    $redeemMessage = 'Failed to submit redemption request. Please try again.';
                }
            }
            
        } catch (Exception $e) {
            $redeemMessage = 'An error occurred: ' . $e->getMessage();
        }
    }
}

// Get redemption history
$redeemHistory = [];
$historyResponse = supabaseRequest('/rest/v1/redeem_requests?user_id=eq.' . $user_id . '&order=created_at.desc&limit=10');
if ($historyResponse['status'] === 200) {
    $redeemHistory = $historyResponse['data'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Redeem Funds - MineMechanics</title>
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
  --gradient-red: linear-gradient(135deg, #EF4444 0%, #F87171 100%);
  --violet: #8B5CF6;
  --orange: #F97316;
  --green: #10B981;
  --blue: #3B82F6;
  --gold: #FACC15;
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

/* Redeem Container */
.redeem-container {
  max-width: 1200px;
  margin: 0 auto;
}

/* Balance Display */
.balance-display {
  display: flex;
  justify-content: center;
  gap: 30px;
  margin-bottom: 40px;
  flex-wrap: wrap;
}

.balance-card {
  background: var(--bg-card);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 20px;
  padding: 30px;
  min-width: 280px;
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

.balance-card.usd::before {
  background: var(--gradient-green);
}

.balance-card.minem::before {
  background: var(--gradient-gold);
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
  font-size: 42px;
  font-weight: 800;
  font-family: 'Space Grotesk', sans-serif;
}

.usd-balance {
  background: var(--gradient-green);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.minem-balance {
  background: var(--gradient-gold);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

/* Redeem Methods Grid */
.redeem-methods {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
  gap: 25px;
  margin-bottom: 40px;
}

.method-card {
  background: var(--bg-card);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 20px;
  padding: 30px;
  transition: all 0.3s ease;
  cursor: pointer;
}

.method-card:hover {
  transform: translateY(-5px);
  border-color: var(--violet);
}

.method-card.selected {
  border-color: var(--green);
  background: rgba(16, 185, 129, 0.05);
}

.method-icon {
  font-size: 40px;
  margin-bottom: 20px;
  background: var(--gradient-blue);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.method-title {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 22px;
  font-weight: 700;
  margin-bottom: 10px;
  color: var(--text-primary);
}

.method-description {
  color: var(--text-muted);
  font-size: 14px;
  margin-bottom: 20px;
  line-height: 1.6;
}

.method-features {
  list-style: none;
  margin-bottom: 20px;
}

.method-features li {
  color: var(--text-secondary);
  font-size: 13px;
  margin-bottom: 8px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.method-features li i {
  color: var(--green);
}

.method-min {
  background: rgba(59, 130, 246, 0.1);
  border: 1px solid rgba(59, 130, 246, 0.2);
  border-radius: 12px;
  padding: 15px;
  text-align: center;
}

.method-min-label {
  color: var(--text-muted);
  font-size: 12px;
  margin-bottom: 5px;
}

.method-min-value {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 20px;
  font-weight: 700;
  color: var(--blue);
}

/* Redeem Form */
.redeem-form-section {
  background: var(--bg-card);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 20px;
  padding: 40px;
  margin-bottom: 40px;
}

.form-header {
  display: flex;
  align-items: center;
  gap: 15px;
  margin-bottom: 30px;
}

.form-icon {
  font-size: 32px;
  background: var(--gradient-green);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.form-title {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 28px;
  font-weight: 700;
  color: var(--text-primary);
}

.form-subtitle {
  color: var(--text-muted);
  font-size: 16px;
}

.redeem-form {
  display: none;
}

.redeem-form.active {
  display: block;
  animation: fadeIn 0.5s ease;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
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

.form-select {
  width: 100%;
  padding: 15px 20px;
  background: rgba(0, 0, 0, 0.3);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  color: var(--text-primary);
  font-size: 16px;
  transition: all 0.3s ease;
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%238B5CF6' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 20px center;
  background-size: 16px;
}

.form-select:focus {
  outline: none;
  border-color: var(--violet);
  box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}

/* Fee Calculator */
.fee-calculator {
  background: rgba(59, 130, 246, 0.1);
  border: 1px solid rgba(59, 130, 246, 0.2);
  border-radius: 16px;
  padding: 25px;
  margin: 30px 0;
}

.fee-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 15px 0;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.fee-row:last-child {
  border-bottom: none;
  font-weight: 700;
  font-size: 18px;
}

.fee-label {
  color: var(--text-secondary);
}

.fee-value {
  font-family: 'Space Grotesk', sans-serif;
  font-weight: 600;
}

/* Submit Button */
.submit-btn {
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

.submit-btn:hover:not(:disabled) {
  transform: translateY(-3px);
  box-shadow: 0 15px 30px rgba(16, 185, 129, 0.4);
}

.submit-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
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
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  
  to {
    opacity: 1;
    transform: translateY(0);
  }
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

.history-table thead {
  background: rgba(0, 0, 0, 0.3);
}

.history-table th {
  padding: 15px 20px;
  text-align: left;
  font-family: 'Space Grotesk', sans-serif;
  font-weight: 600;
  color: var(--text-secondary);
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.history-table td {
  padding: 15px 20px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.history-table tbody tr:hover {
  background: rgba(255, 255, 255, 0.03);
}

.history-table tbody tr:last-child td {
  border-bottom: none;
}

.date-cell {
  font-family: monospace;
  color: var(--text-muted);
  font-size: 14px;
}

.amount-cell {
  text-align: right;
  font-weight: 600;
}

.status-cell {
  text-align: center;
}

.status-pending {
  color: var(--orange);
  background: rgba(249, 115, 22, 0.1);
  padding: 5px 15px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
}

.status-completed {
  color: var(--green);
  background: rgba(16, 185, 129, 0.1);
  padding: 5px 15px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
}

/* Empty State */
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
  
  .balance-display {
    flex-direction: column;
    align-items: center;
  }
  
  .balance-card {
    width: 100%;
    max-width: 300px;
  }
  
  .redeem-methods {
    grid-template-columns: 1fr;
  }
  
  .form-row {
    grid-template-columns: 1fr;
  }
  
  .history-table {
    display: block;
    overflow-x: auto;
  }
  
  .history-table th,
  .history-table td {
    padding: 10px 15px;
    font-size: 14px;
  }
}

</style></head><body><div class="sidebar"><div class="logo"><i class="ph-lightning-fill"></i>MineMechanics </div>
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
    <a href="redeem.php" class="nav-item active">
      <i class="ph-money"></i> Redeem
    </a>
    <a href="buy-miners.php" class="nav-item">
      <i class="ph-cpu"></i> Buy Miners
    </a>
    <a href="buy-plants.php" class="nav-item">
      <i class="ph-leaf"></i> Buy Plants
    </a>
    <a href="report.php" class="nav-item">
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
      <h1>Redeem Funds</h1>
      <p>Withdraw your earnings using various payment methods</p>
    </div>
    <div>
      <span style="color: var(--text-muted); font-size: 14px;">User: <?php echo htmlspecialchars($profile['username'] ?? 'User'); ?></span>
    </div>
  </div>
  
  <div class="redeem-container">
    <!-- Success/Error Messages -->
    <?php if ($redeemMessage): ?>
      <div class="alert <?php echo $redeemSuccess ? 'alert-success' : 'alert-error'; ?>">
        <i class="ph-<?php echo $redeemSuccess ? 'check-circle' : 'warning-circle'; ?>"></i>
        <span><?php echo htmlspecialchars($redeemMessage); ?></span>
      </div>
    <?php endif; ?>
    
    <!-- Balance Display -->
    <div class="balance-display">
      <div class="balance-card usd">
        <div class="balance-label">
          <i class="ph-currency-dollar"></i>
          USD Balance
        </div>
        <div class="balance-value usd-balance">$<?php echo number_format($usd_balance, 2); ?></div>
      </div>
      
      <div class="balance-card minem">
        <div class="balance-label">
          <i class="ph-coins"></i>
          MINEM Balance
        </div>
        <div class="balance-value minem-balance"><?php echo number_format($balance['minem'] ?? 0, 2); ?></div>
      </div>
    </div>
    
    <!-- Redeem Methods -->
    <div class="redeem-methods">
      <!-- FaucetPay -->
      <div class="method-card" onclick="selectMethod('faucetpay')" id="faucetpay-card">
        <div class="method-icon">
          <i class="ph-credit-card"></i>
        </div>
        <h3 class="method-title">FaucetPay</h3>
        <p class="method-description">
          Instant withdrawals to FaucetPay wallet. Lowest minimum amount.
        </p>
        <ul class="method-features">
          <li><i class="ph-check-circle"></i> No fees</li>
          <li><i class="ph-check-circle"></i> Email ID required</li>
          <li><i class="ph-check-circle"></i> Multiple coins supported</li>
        </ul>
        <div class="method-min">
          <div class="method-min-label">Minimum Amount</div>
          <div class="method-min-value">$0.02</div>
        </div>
      </div>
      
      <!-- Direct Wallet -->
      <div class="method-card" onclick="selectMethod('direct_wallet')" id="direct_wallet-card">
        <div class="method-icon">
          <i class="ph-wallet"></i>
        </div>
        <h3 class="method-title">Direct Wallet</h3>
        <p class="method-description">
          Send directly to your cryptocurrency wallet address.
        </p>
        <ul class="method-features">
          <li><i class="ph-check-circle"></i> $0.05 + 0.6% fee</li>
          <li><i class="ph-check-circle"></i> Wallet address required</li>
          <li><i class="ph-check-circle"></i> Wide coin selection</li>
        </ul>
        <div class="method-min">
          <div class="method-min-label">Minimum Amount</div>
          <div class="method-min-value">$5</div>
        </div>
      </div>
      
      <!-- Tonkeeper -->
      <div class="method-card" onclick="selectMethod('tonkeeper')" id="tonkeeper-card">
        <div class="method-icon">
          <i class="ph-lightning"></i>
        </div>
        <h3 class="method-title">Tonkeeper</h3>
        <p class="method-description">
          Special TON blockchain wallet for TON coin withdrawals.
        </p>
        <ul class="method-features">
          <li><i class="ph-check-circle"></i> $0.05 flat fee</li>
          <li><i class="ph-check-circle"></i> TON address required</li>
          <li><i class="ph-check-circle"></i> No memo needed</li>
        </ul>
        <div class="method-min">
          <div class="method-min-label">Minimum Amount</div>
          <div class="method-min-value">$2</div>
        </div>
      </div>
      
      <!-- Bitcoin by Email -->
      <div class="method-card" onclick="selectMethod('bitcoin_email')" id="bitcoin_email-card">
        <div class="method-icon">
          <i class="ph-envelope"></i>
        </div>
        <h3 class="method-title">Bitcoin by Email</h3>
        <p class="method-description">
          Send Bitcoin directly to Proton Mail email addresses.
        </p>
        <ul class="method-features">
          <li><i class="ph-check-circle"></i> $0.05 + 0.1% fee</li>
          <li><i class="ph-check-circle"></i> Proton Mail only</li>
          <li><i class="ph-check-circle"></i> Email must have receive feature enabled</li>
        </ul>
        <div class="method-min">
          <div class="method-min-label">Minimum Amount</div>
          <div class="method-min-value">$15</div>
        </div>
      </div>
      
      <!-- Gift Cards -->
      <div class="method-card" onclick="selectMethod('gift_card')" id="gift_card-card">
        <div class="method-icon">
          <i class="ph-gift"></i>
        </div>
        <h3 class="method-title">Gift Cards</h3>
        <p class="method-description">
          Redeem for popular gift cards. US users only (except Steam).
        </p>
        <ul class="method-features">
          <li><i class="ph-check-circle"></i> $0.10 fee (except Steam)</li>
          <li><i class="ph-check-circle"></i> Email delivery</li>
          <li><i class="ph-check-circle"></i> 12+ brands available</li>
        </ul>
        <div class="method-min">
          <div class="method-min-label">Minimum Amount</div>
          <div class="method-min-value">$5</div>
        </div>
      </div>
    </div>
    
    <!-- Redeem Form Section -->
    <div class="redeem-form-section">
      <div class="form-header">
        <div class="form-icon">
          <i class="ph-arrow-circle-down"></i>
        </div>
        <div>
          <h2 class="form-title">Redeem Funds</h2>
          <p class="form-subtitle">Select a method above and fill the form below</p>
        </div>
      </div>
      
      <!-- FaucetPay Form -->
      <form method="POST" action="" class="redeem-form" id="faucetpay-form">
        <input type="hidden" name="method" value="faucetpay">
        
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Amount to Redeem (USD)</label>
            <input type="number" name="amount" class="form-input" 
                   step="0.01" min="0.02" max="<?php echo $usd_balance; ?>"
                   placeholder="Enter amount in USD" required>
            <small style="color: var(--text-muted); display: block; margin-top: 5px;">
              Minimum: $0.02 | Available: $<?php echo number_format($usd_balance, 2); ?>
            </small>
          </div>
          
          <div class="form-group">
            <label class="form-label">Email Address</label>
            <input type="email" name="address" class="form-input" 
                   placeholder="Your FaucetPay email" required>
          </div>
        </div>
        
        <div class="form-group">
          <label class="form-label">Select Coin</label>
          <select name="coin" class="form-select" required>
            <option value="">Choose a coin</option>
            <?php foreach ($faucetPay_coins as $coin): ?>
              <option value="<?php echo $coin; ?>"><?php echo $coin; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        
        <div class="fee-calculator">
          <div class="fee-row">
            <span class="fee-label">Amount:</span>
            <span class="fee-value" id="faucetpay-amount">$0.00</span>
          </div>
          <div class="fee-row">
            <span class="fee-label">Fee:</span>
            <span class="fee-value" id="faucetpay-fee">$0.00</span>
          </div>
          <div class="fee-row">
            <span class="fee-label">You Receive:</span>
            <span class="fee-value" id="faucetpay-net">$0.00</span>
          </div>
        </div>
        
        <button type="submit" name="redeem_submit" class="submit-btn">
          <i class="ph-check-circle"></i>
          Redeem via FaucetPay
        </button>
      </form>
      
      <!-- Direct Wallet Form -->
      <form method="POST" action="" class="redeem-form" id="direct_wallet-form">
        <input type="hidden" name="method" value="direct_wallet">
        
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Amount to Redeem (USD)</label>
            <input type="number" name="amount" class="form-input" 
                   step="0.01" min="5" max="<?php echo $usd_balance; ?>"
                   placeholder="Enter amount in USD" required>
            <small style="color: var(--text-muted); display: block; margin-top: 5px;">
              Minimum: $5.00 | Available: $<?php echo number_format($usd_balance, 2); ?>
            </small>
          </div>
          
          <div class="form-group">
            <label class="form-label">Wallet Address</label>
            <input type="text" name="address" class="form-input" 
                   placeholder="Your cryptocurrency wallet address" required>
          </div>
        </div>
        
        <div class="form-group">
          <label class="form-label">Select Coin</label>
          <select name="coin" class="form-select" required>
            <option value="">Choose a coin</option>
            <?php foreach ($directWallet_coins as $coin): ?>
              <option value="<?php echo $coin; ?>"><?php echo $coin; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        
        <div class="fee-calculator">
          <div class="fee-row">
            <span class="fee-label">Amount:</span>
            <span class="fee-value" id="direct_wallet-amount">$0.00</span>
          </div>
          <div class="fee-row">
            <span class="fee-label">Fee ($0.05 + 0.6%):</span>
            <span class="fee-value" id="direct_wallet-fee">$0.00</span>
          </div>
          <div class="fee-row">
            <span class="fee-label">You Receive:</span>
            <span class="fee-value" id="direct_wallet-net">$0.00</span>
          </div>
        </div>
        
        <button type="submit" name="redeem_submit" class="submit-btn">
          <i class="ph-check-circle"></i>
          Redeem to Wallet
        </button>
      </form>
      
      <!-- Tonkeeper Form -->
      <form method="POST" action="" class="redeem-form" id="tonkeeper-form">
        <input type="hidden" name="method" value="tonkeeper">
        <input type="hidden" name="coin" value="TON">
        
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Amount to Redeem (USD)</label>
            <input type="number" name="amount" class="form-input" 
                   step="0.01" min="2" max="<?php echo $usd_balance; ?>"
                   placeholder="Enter amount in USD" required>
            <small style="color: var(--text-muted); display: block; margin-top: 5px;">
              Minimum: $2.00 | Available: $<?php echo number_format($usd_balance, 2); ?>
            </small>
          </div>
          
          <div class="form-group">
            <label class="form-label">TON Wallet Address</label>
            <input type="text" name="address" class="form-input" 
                   placeholder="Your Tonkeeper TON address" required>
          </div>
        </div>
        
        <div class="fee-calculator">
          <div class="fee-row">
            <span class="fee-label">Amount:</span>
            <span class="fee-value" id="tonkeeper-amount">$0.00</span>
          </div>
          <div class="fee-row">
            <span class="fee-label">Fee:</span>
            <span class="fee-value" id="tonkeeper-fee">$0.05</span>
          </div>
          <div class="fee-row">
            <span class="fee-label">You Receive:</span>
            <span class="fee-value" id="tonkeeper-net">$0.00</span>
          </div>
        </div>
        
        <button type="submit" name="redeem_submit" class="submit-btn">
          <i class="ph-check-circle"></i>
          Redeem to Tonkeeper
        </button>
      </form>
      
      <!-- Bitcoin by Email Form -->
      <form method="POST" action="" class="redeem-form" id="bitcoin_email-form">
        <input type="hidden" name="method" value="bitcoin_email">
        <input type="hidden" name="coin" value="BTC">
        
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Amount to Redeem (USD)</label>
            <input type="number" name="amount" class="form-input" 
                   step="0.01" min="15" max="<?php echo $usd_balance; ?>"
                   placeholder="Enter amount in USD" required>
            <small style="color: var(--text-muted); display: block; margin-top: 5px;">
              Minimum: $15.00 | Available: $<?php echo number_format($usd_balance, 2); ?>
            </small>
          </div>
          
          <div class="form-group">
            <label class="form-label">Proton Mail Address</label>
            <input type="email" name="address" class="form-input" 
                   placeholder="yourname@protonmail.com or yourname@proton.me" required>
          </div>
        </div>
        
        <div class="fee-calculator">
          <div class="fee-row">
            <span class="fee-label">Amount:</span>
            <span class="fee-value" id="bitcoin_email-amount">$0.00</span>
          </div>
          <div class="fee-row">
            <span class="fee-label">Fee ($0.05 + 0.1%):</span>
            <span class="fee-value" id="bitcoin_email-fee">$0.00</span>
          </div>
          <div class="fee-row">
            <span class="fee-label">You Receive:</span>
            <span class="fee-value" id="bitcoin_email-net">$0.00</span>
          </div>
        </div>
        
        <button type="submit" name="redeem_submit" class="submit-btn">
          <i class="ph-check-circle"></i>
          Redeem Bitcoin by Email
        </button>
      </form>
      
      <!-- Gift Card Form -->
      <form method="POST" action="" class="redeem-form" id="gift_card-form">
        <input type="hidden" name="method" value="gift_card">
        <input type="hidden" name="coin" value="GIFT_CARD">
        
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Amount to Redeem (USD)</label>
            <input type="number" name="amount" class="form-input" 
                   id="gift_amount" step="0.01" min="5" max="<?php echo $usd_balance; ?>"
                   placeholder="Enter amount in USD" required>
            <small style="color: var(--text-muted); display: block; margin-top: 5px;">
              Minimum: $5.00 | Available: $<?php echo number_format($usd_balance, 2); ?>
            </small>
          </div>
          <div class="form-group">
            <label class="form-label">Gift Card Type</label>
            <select name="gift_card_type" class="form-select" id="gift_card_type" required>
              <option value="">Select Gift Card</option>
              <?php foreach ($giftCards as $type => $info): ?>
                <option value="<?php echo $type; ?>" 
                        data-min="<?php echo $info['min']; ?>" 
                        data-max="<?php echo $info['max']; ?>">
                  <?php echo ucwords(strtolower(str_replace('_', ' ', $type))); ?> ($<?php echo $info['min']; ?> - $<?php echo $info['max']; ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        
        <div class="form-group">
          <label class="form-label">Email Address for Delivery</label>
          <input type="email" name="address" class="form-input" 
                 placeholder="Email to receive gift card" required>
        </div>
        
        <div class="fee-calculator">
          <div class="fee-row">
            <span class="fee-label">Amount:</span>
            <span class="fee-value" id="gift_card-amount">$0.00</span>
          </div>
          <div class="fee-row">
            <span class="fee-label">Fee:</span>
            <span class="fee-value" id="gift_card-fee">$0.00</span>
          </div>
          <div class="fee-row">
            <span class="fee-label">You Receive:</span>
            <span class="fee-value" id="gift_card-net">$0.00</span>
          </div>
        </div>
        
        <button type="submit" name="redeem_submit" class="submit-btn">
          <i class="ph-check-circle"></i>
          Redeem Gift Card
        </button>
      </form>
    </div>
    
    <!-- Redemption History -->
    <div class="history-section">
      <div class="history-header">
        <div class="history-title">Pending Redemption History(Once paid, it will be removed.)</div>
        <div style="color: var(--text-muted); font-size: 14px;">
          Showing last 10 pending redemption requests
        </div>
      </div>
      
      <?php if (!empty($redeemHistory)): ?>
        <table class="history-table">
          <thead>
            <tr>
              <th>Date & Time</th>
              <th>Method</th>
              <th>Amount</th>
              <th>Fee</th>
              <th>Net Amount</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($redeemHistory as $redeem): ?>
              <tr>
                <td class="date-cell">
                  <?php 
                    $date = new DateTime($redeem['created_at']);
                    echo $date->format('Y-m-d H:i:s');
                  ?>
                </td>
                <td><?php echo htmlspecialchars($redeem['method']); ?></td>
                <td class="amount-cell">$<?php echo number_format($redeem['amount_usd'], 2); ?></td>
                <td class="amount-cell">$<?php echo number_format($redeem['fee'], 2); ?></td>
                <td class="amount-cell">$<?php echo number_format($redeem['net_amount'] ?? ($redeem['amount_usd'] - $redeem['fee']), 2); ?></td>
                <td class="status-cell">
                  <span class="status-<?php echo strtolower($redeem['status']); ?>">
                    <?php echo ucfirst($redeem['status']); ?>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="empty-state">
          <i class="ph-clock"></i>
          <h3>No Redemption History</h3>
          <p>Your redemption requests will appear here</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
// Method selection
let selectedMethod = null;

function selectMethod(method) {
    // Remove selected class from all cards
    document.querySelectorAll('.method-card').forEach(card => {
        card.classList.remove('selected');
    });
    
    // Add selected class to clicked card
    document.getElementById(method + '-card').classList.add('selected');
    
    // Hide all forms
    document.querySelectorAll('.redeem-form').forEach(form => {
        form.classList.remove('active');
    });
    
    // Show selected form
    document.getElementById(method + '-form').classList.add('active');
    
    // Store selected method
    selectedMethod = method;
    
    // Reset and update fee calculator
    updateFeeCalculator(method);
}

// Fee calculation functions
function calculateFaucetPayFee(amount) {
    return 0; // No fee
}

function calculateDirectWalletFee(amount) {
    return 0.05 + (amount * 0.006); // $0.05 + 0.6%
}

function calculateTonkeeperFee(amount) {
    return 0.05; // Flat $0.05
}

function calculateBitcoinEmailFee(amount) {
    return 0.05 + (amount * 0.001); // $0.05 + 0.1%
}

function calculateGiftCardFee(amount, cardType) {
    if (cardType === 'STEAM') {
        return amount * 0.05; // $0.05 per $1 for Steam
    }
    return 0.10; // $0.10 for other gift cards (US users only)
}

// Update fee calculator
function updateFeeCalculator(method) {
    const amountInput = document.querySelector('#' + method + '-form input[name="amount"]');
    const amount = parseFloat(amountInput?.value) || 0;
    
    let fee = 0;
    let netAmount = 0;
    
    switch (method) {
        case 'faucetpay':
            fee = calculateFaucetPayFee(amount);
            netAmount = amount - fee;
            break;
            
        case 'direct_wallet':
            fee = calculateDirectWalletFee(amount);
            netAmount = amount - fee;
            break;
            
        case 'tonkeeper':
            fee = calculateTonkeeperFee(amount);
            netAmount = amount - fee;
            break;
            
        case 'bitcoin_email':
            fee = calculateBitcoinEmailFee(amount);
            netAmount = amount - fee;
            break;
            
        case 'gift_card':
            const cardType = document.getElementById('gift_card_type').value;
            fee = cardType ? calculateGiftCardFee(amount, cardType) : 0;
            netAmount = amount - fee;
            break;
    }
    
    // Update display
    document.getElementById(method + '-amount').textContent = '$' + amount.toFixed(2);
    document.getElementById(method + '-fee').textContent = '$' + fee.toFixed(2);
    document.getElementById(method + '-net').textContent = '$' + netAmount.toFixed(2);
}

// Gift card amount validation
document.getElementById('gift_card_type')?.addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const min = parseFloat(selectedOption.getAttribute('data-min')) || 5;
    const max = parseFloat(selectedOption.getAttribute('data-max')) || 100;
    
    const amountInput = document.getElementById('gift_amount');
    amountInput.min = min;
    amountInput.max = Math.min(max, <?php echo $usd_balance; ?>);
    
    // Update placeholder and validation message
    amountInput.placeholder = `Enter amount ($${min} - $${Math.min(max, <?php echo $usd_balance; ?>)})`;
    
    const small = amountInput.nextElementSibling;
    if (small) {
        small.textContent = `Minimum: $${min} | Maximum: $${Math.min(max, <?php echo $usd_balance; ?>)} | Available: $<?php echo number_format($usd_balance, 2); ?>`;
    }
    
    // Update fee calculator
    updateFeeCalculator('gift_card');
});

// Add event listeners for amount inputs
document.querySelectorAll('input[name="amount"]').forEach(input => {
    input.addEventListener('input', function() {
        const form = this.closest('.redeem-form');
        if (form && form.classList.contains('active')) {
            const method = form.id.replace('-form', '');
            updateFeeCalculator(method);
        }
    });
});

// Add event listener for coin selection in direct wallet
document.querySelector('select[name="coin"]')?.addEventListener('change', function() {
    const form = this.closest('.redeem-form');
    if (form && form.classList.contains('active')) {
        const method = form.id.replace('-form', '');
        updateFeeCalculator(method);
    }
});

// Initialize with first method selected by default
document.addEventListener('DOMContentLoaded', function() {
    selectMethod('faucetpay');
    
    // Auto-hide alerts after 5 seconds
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.5s ease';
            setTimeout(() => {
                alert.style.display = 'none';
            }, 500);
        });
    }, 5000);
    
    // Responsive sidebar toggle for mobile
    if (window.innerWidth <= 768) {
        const sidebar = document.querySelector('.sidebar');
        
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
});
</script>
</body>
</html>