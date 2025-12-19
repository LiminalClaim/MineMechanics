
<?php
// topup.php - Top Up MINEM Tokens with OxaPay
session_start();

// Supabase configuration
define('SUPABASE_URL', 'https://vrgrmqrhrwkltopjwlrr.supabase.co');
define('SUPABASE_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InZyZ3JtcXJocndrbHRvcGp3bHJyIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjUwODQxODUsImV4cCI6MjA4MDY2MDE4NX0.6cffp0njkFx1zzfp1PT5s29oNlg2WXoNH8ZsBx2qvz0');
define('SUPABASE_SECRET', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InZyZ3JtcXJocndrbHRvcGp3bHJyIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc2NTA4NDE4NSwiZXhwIjoyMDgwNjYwMTg1fQ.8VeeaMPbjUkffiHizrwJBVlLE028R2y2QOAkV9O5gXA');

// OxaPay Configuration
define('OXAPAY_API_KEY', '8RCYTK-LBE2VO-UASLXV-XBOKR8');
define('OXAPAY_INVOICE_URL', 'https://api.oxapay.com/v1/payment/invoice');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['access_token'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$access_token = $_SESSION['access_token'];

// Constants
define('MINEM_PER_DOLLAR', 1000000); // 1,000,000 MINEM = $1
define('MIN_DEPOSIT_AMOUNT', 0.30); // Minimum $0.30 deposit
define('MAX_DEPOSIT_AMOUNT', 10000); // Maximum $10,000 deposit

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

// Function to create OxaPay invoice
function createOxapayInvoice($amountUSD, $userId, $orderId) {
    $data = [
        "amount" => round($amountUSD, 2),
        "currency" => "USD",
        "lifetime" => 60,
        "fee_paid_by_payer" => 1,
        "under_paid_coverage" => 0.5,
        "to_currency" => "USDT",
        "auto_withdrawal" => false,
        "mixed_payment" => true,
        "callback_url" => "https://minemechanics.xo.je/webhook/oxapay.php",
        "return_url" => "https://minemechanics.xo.je/topup.php",
        "order_id" => $orderId,
        "description" => "MineMechanics Deposit",
        "thanks_message" => "Thank you for supporting us. 20% of our profits are donated to charities.",
        "sandbox" => true
    ];
    
    $headers = [
        'Content-Type: application/json',
        'merchant_api_key: ' . OXAPAY_API_KEY
    ];
    
    $ch = curl_init(OXAPAY_INVOICE_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_TIMEOUT => 20,
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

// Function to calculate MINEM amount
function calculateMinemAmount($usdAmount) {
    return $usdAmount * MINEM_PER_DOLLAR;
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

// Get user's topup history
$topupHistory = [];
$historyResponse = supabaseRequest('/rest/v1/topups?user_id=eq.' . $user_id . '&order=created_at.desc&limit=10');
if ($historyResponse['status'] === 200) {
    $topupHistory = $historyResponse['data'];
}

// Handle topup initiation
$topupSuccess = false;
$topupMessage = '';
$paymentUrl = '';
$orderId = '';
$minemAmount = 0;
$usdAmount = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['initiate_topup'])) {
    $usdAmount = floatval($_POST['usd_amount'] ?? 0);
    
    // Validate input
    if ($usdAmount < MIN_DEPOSIT_AMOUNT) {
        $topupMessage = 'Minimum deposit amount is $' . number_format(MIN_DEPOSIT_AMOUNT, 2) . '.';
    } elseif ($usdAmount > MAX_DEPOSIT_AMOUNT) {
        $topupMessage = 'Maximum deposit amount is $' . number_format(MAX_DEPOSIT_AMOUNT, 2) . '.';
    } else {
        // Calculate MINEM amount
        $minemAmount = calculateMinemAmount($usdAmount);
        
        // Generate unique order ID
        $orderId = 'MM_' . $user_id . '_' . time();
        
        // Create topup record in database with 'pending' status
        $topupData = [
            'user_id' => $user_id,
            'amount_usd' => $usdAmount,
            'amount_minem' => $minemAmount,
            'method' => 'crypto',
            'status' => 'pending'
        ];
        
        $topupResponse = supabaseRequest('/rest/v1/topups', 'POST', $topupData, true);
        
        if ($topupResponse['status'] === 201 || $topupResponse['status'] === 200) {
            // Create OxaPay invoice
            $paymentResult = createOxapayInvoice($usdAmount, $user_id, $orderId);
            
            if (isset($paymentResult['data']['payment_url'])) {
                $paymentUrl = $paymentResult['data']['payment_url'];
                $topupSuccess = true;
                
                // Store payment info in session for redirect
                $_SESSION['pending_payment'] = [
                    'order_id' => $orderId,
                    'usd_amount' => $usdAmount,
                    'minem_amount' => $minemAmount,
                    'payment_url' => $paymentUrl
                ];
                
                // Redirect to OxaPay
                header('Location: ' . $paymentUrl);
                exit();
            } else {
                $errorMsg = $paymentResult['msg'] ?? 'Unknown error';
                $topupMessage = 'Failed to create payment invoice: ' . $errorMsg;
                
                // Update topup status to failed
                if (!empty($topupResponse['data'][0]['id'])) {
                    $updateData = ['status' => 'failed'];
                    supabaseRequest('/rest/v1/topups?id=eq.' . $topupResponse['data'][0]['id'], 'PATCH', $updateData, true);
                }
            }
        } else {
            $topupMessage = 'Failed to initiate topup. Please try again.';
        }
    }
}

// Handle successful payment redirect
if (isset($_GET['success']) && $_GET['success'] === 'true' && isset($_GET['order'])) {
    $orderId = $_GET['order'];
    $topupSuccess = true;
    $topupMessage = 'Payment successful! MINEM tokens will be credited to your account shortly.';
    
    // Check if we have pending payment in session
    if (isset($_SESSION['pending_payment']) && $_SESSION['pending_payment']['order_id'] === $orderId) {
        $usdAmount = $_SESSION['pending_payment']['usd_amount'];
        $minemAmount = $_SESSION['pending_payment']['minem_amount'];
        
        // Clear pending payment session
        unset($_SESSION['pending_payment']);
    }
}

// Handle cancelled payment
if (isset($_GET['cancel']) && $_GET['cancel'] === 'true' && isset($_GET['order'])) {
    $orderId = $_GET['order'];
    $topupMessage = 'Payment was cancelled. No funds were deducted.';
    
    // Update latest pending topup status to cancelled
    $updateData = ['status' => 'cancelled'];
    $updateResponse = supabaseRequest('/rest/v1/topups?user_id=eq.' . $user_id . '&order=created_at.desc&limit=1', 'PATCH', $updateData, true);
    
    // Clear pending payment session
    if (isset($_SESSION['pending_payment'])) {
        unset($_SESSION['pending_payment']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Top Up MINEM Tokens - MineMechanics</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
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
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(139, 92, 246, 0.1) 0%, transparent 20%),
                radial-gradient(circle at 90% 80%, rgba(250, 204, 21, 0.1) 0%, transparent 20%);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
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

        .logo {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 28px;
            font-weight: 800;
            background: var(--gradient-gold);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-transform: uppercase;
        }

        .user-nav {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .nav-link {
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            padding: 10px 20px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            background: rgba(139, 92, 246, 0.1);
            color: var(--text-primary);
        }

        .nav-link.active {
            background: rgba(139, 92, 246, 0.15);
            color: var(--violet);
        }

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

        .alert-info {
            background: rgba(6, 182, 212, 0.1);
            border: 1px solid rgba(6, 182, 212, 0.2);
            color: #67E8F9;
        }

        .dashboard {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 30px;
        }

        .sidebar {
            background: var(--bg-sidebar);
            border-radius: 20px;
            padding: 30px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .balance-card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 25px;
            text-align: center;
            margin-bottom: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
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
            background: var(--gradient-gold);
        }

        .balance-label {
            color: var(--text-muted);
            font-size: 14px;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .balance-value {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 32px;
            font-weight: 800;
            background: var(--gradient-gold);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .menu {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 30px;
        }

        .menu-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            color: var(--text-secondary);
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s ease;
        }

        .menu-item:hover {
            background: rgba(139, 92, 246, 0.1);
            color: var(--text-primary);
        }

        .menu-item.active {
            background: rgba(139, 92, 246, 0.15);
            color: var(--violet);
        }

        .menu-item i {
            font-size: 20px;
            width: 24px;
        }

        .main-content {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 40px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .page-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 30px;
        }

        .page-header i {
            font-size: 32px;
            background: var(--gradient-gold);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .page-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 28px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .exchange-banner {
            background: rgba(250, 204, 21, 0.1);
            border: 1px solid rgba(250, 204, 21, 0.2);
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 30px;
            text-align: center;
        }

        .exchange-rate {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--gold);
        }

        .exchange-desc {
            color: var(--text-muted);
            font-size: 14px;
        }

        .topup-form {
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
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(250, 204, 21, 0.1);
        }

        .currency-label {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 14px;
            font-weight: 600;
        }

        .minem-preview {
            margin-top: 10px;
            padding: 12px 16px;
            background: rgba(6, 182, 212, 0.1);
            border: 1px solid rgba(6, 182, 212, 0.2);
            border-radius: 10px;
            display: none;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .minem-preview.active {
            display: block;
        }

        .minem-preview-label {
            color: var(--text-muted);
            font-size: 12px;
            margin-bottom: 4px;
        }

        .minem-preview-value {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 18px;
            font-weight: 700;
            color: var(--cyan);
        }

        .validation-message {
            margin-top: 8px;
            font-size: 12px;
            min-height: 16px;
        }

        .validation-error {
            color: #FCA5A5;
        }

        .validation-success {
            color: #6EE7B7;
        }

        .crypto-info {
            background: rgba(247, 147, 26, 0.1);
            border: 1px solid rgba(247, 147, 26, 0.2);
            border-radius: 16px;
            padding: 25px;
            margin-top: 25px;
            text-align: center;
        }

        .crypto-header {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-bottom: 15px;
        }

        .crypto-icon {
            font-size: 32px;
            color: var(--orange);
        }

        .crypto-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 20px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .crypto-desc {
            color: var(--text-muted);
            font-size: 14px;
            margin-bottom: 15px;
        }

        .supported-coins {
            display: flex;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 15px;
        }

        .coin-badge {
            background: rgba(255, 255, 255, 0.05);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
        }

        .topup-preview {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
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

        .preview-value.usd {
            color: var(--green);
        }

        .preview-value.minem {
            color: var(--gold);
        }

        .preview-value.rate {
            color: var(--cyan);
        }

        .topup-btn {
            width: 100%;
            padding: 18px 30px;
            background: var(--gradient-gold);
            border: none;
            border-radius: 12px;
            color: #000;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 18px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-top: 20px;
        }

        .topup-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(250, 204, 21, 0.3);
        }

        .topup-btn:active {
            transform: translateY(0);
        }

        .history-section {
            margin-top: 40px;
            padding-top: 30px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .history-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--text-primary);
        }

        .history-table {
            width: 100%;
            border-collapse: collapse;
        }

        .history-table th {
            text-align: left;
            padding: 15px;
            color: var(--text-muted);
            font-weight: 500;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .history-table td {
            padding: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-pending {
            background: rgba(250, 204, 21, 0.1);
            color: var(--gold);
        }

        .status-completed {
            background: rgba(16, 185, 129, 0.1);
            color: var(--green);
        }

        .status-cancelled {
            background: rgba(239, 68, 68, 0.1);
            color: #FCA5A5;
        }

        .status-failed {
            background: rgba(156, 163, 175, 0.1);
            color: #9CA3AF;
        }

        .empty-history {
            text-align: center;
            padding: 40px;
            color: var(--text-muted);
        }

        @media (max-width: 1024px) {
            .dashboard {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                order: 2;
            }
            
            .main-content {
                order: 1;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }
            
            .header {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">MineMechanics</div>
            <div class="user-nav">
                <a href="dashboard.php" class="nav-link">Dashboard</a>
                <a href="topup.php" class="nav-link active">Top Up</a>
                <a href="logout.php" class="nav-link">Logout</a>
            </div>
        </div>

        <?php if ($topupMessage): ?>
            <div class="alert <?php echo $topupSuccess ? 'alert-success' : 'alert-error'; ?>">
                <i class="fas <?php echo $topupSuccess ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($topupMessage); ?>
            </div>
        <?php endif; ?>

        <div class="dashboard">
            <div class="sidebar">
                <div class="balance-card">
                    <div class="balance-label">Your MINEM Balance</div>
                    <div class="balance-value">
                        <?php 
                            $minemBalance = $balance['minem'] ?? 0;
                            echo number_format($minemBalance, 0);
                        ?> MINEM
                    </div>
                </div>

                <div class="menu">
                    <a href="dashboard.php" class="menu-item">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="miners.php" class="menu-item">
                        <i class="fas fa-microchip"></i>
                        <span>My Miners</span>
                    </a>
                    <a href="energy.php" class="menu-item">
                        <i class="fas fa-bolt"></i>
                        <span>Energy Plants</span>
                    </a>
                    <a href="topup.php" class="menu-item active">
                        <i class="fas fa-coins"></i>
                        <span>Top Up MINEM</span>
                    </a>
                    <a href="redeem.php" class="menu-item">
                        <i class="fas fa-wallet"></i>
                        <span>Redeem Earnings</span>
                    </a>
                    <a href="faucet.php" class="menu-item">
                        <i class="fas fa-faucet"></i>
                        <span>Free Faucet</span>
                    </a>
                    <a href="swap.php" class="menu-item">
                        <i class="fas fa-exchange-alt"></i>
                        <span>Swap M2/MINEM</span>
                    </a>
                </div>
            </div>

            <div class="main-content">
                <div class="page-header">
                    <i class="fas fa-coins"></i>
                    <h1 class="page-title">Top Up MINEM Tokens</h1>
                </div>

                <div class="exchange-banner">
                    <div class="exchange-rate">1 USD = <?php echo number_format(MINEM_PER_DOLLAR); ?> MINEM</div>
                    <div class="exchange-desc">Purchase MINEM tokens to buy miners, energy plants, and trade on our platform</div>
                </div>

                <form method="POST" action="" class="topup-form">
                    <div class="form-group">
                        <label class="form-label">Enter Deposit Amount (USD)</label>
                        <div class="form-input-group">
                            <input type="number" 
                                   name="usd_amount" 
                                   id="usd_amount"
                                   class="form-input" 
                                   min="<?php echo MIN_DEPOSIT_AMOUNT; ?>" 
                                   max="<?php echo MAX_DEPOSIT_AMOUNT; ?>" 
                                   step="0.01" 
                                   placeholder="10.00"
                                   value="<?php echo isset($_POST['usd_amount']) ? htmlspecialchars($_POST['usd_amount']) : ''; ?>"
                                   required>
                            <span class="currency-label">USD</span>
                        </div>
                        
                        <!-- Real-time MINEM preview -->
                        <div class="minem-preview" id="minemPreview">
                            <div class="minem-preview-label">You will receive:</div>
                            <div class="minem-preview-value" id="minemAmount">0 MINEM</div>
                        </div>
                        
                        <!-- Validation message -->
                        <div class="validation-message" id="validationMessage"></div>
                    </div>

                    <div class="crypto-info">
                        <div class="crypto-header">
                            <i class="fas fa-bitcoin crypto-icon"></i>
                            <div class="crypto-title">Cryptocurrency Payment</div>
                        </div>
                        <div class="crypto-desc">
                            Pay with multiple cryptocurrencies. We accept BTC, ETH, USDT, and many other coins.
                        </div>
                        <div class="supported-coins">
                            <span class="coin-badge">BTC</span>
                            <span class="coin-badge">ETH</span>
                            <span class="coin-badge">USDT</span>
                            <span class="coin-badge">USDC</span>
                            <span class="coin-badge">BNB</span>
                            <span class="coin-badge">SOL</span>
                            <span class="coin-badge">XRP</span>
                            <span class="coin-badge">DOGE</span>
                            <span class="coin-badge">
                              more</span>
                        </div>
                    </div>

                    <?php if (isset($_POST['usd_amount']) && floatval($_POST['usd_amount']) > 0): ?>
                        <div class="topup-preview">
                            <div class="preview-header">
                                <div class="preview-title">Purchase Summary</div>
                            </div>
                            <div class="preview-grid">
                                <div class="preview-item">
                                    <div class="preview-label">USD Amount</div>
                                    <div class="preview-value usd">$<?php echo number_format($usdAmount, 2); ?></div>
                                </div>
                                <div class="preview-item">
                                    <div class="preview-label">MINEM Amount</div>
                                    <div class="preview-value minem"><?php echo number_format($minemAmount); ?></div>
                                </div>
                                <div class="preview-item">
                                    <div class="preview-label">Exchange Rate</div>
                                    <div class="preview-value rate">1 USD = <?php echo number_format(MINEM_PER_DOLLAR); ?> MINEM</div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <button type="submit" name="initiate_topup" class="topup-btn">
                        <i class="fas fa-lock"></i>
                        Proceed to Crypto Payment
                    </button>
                </form>

                <div class="history-section">
                    <h2 class="history-title">Recent Topups</h2>
                    
                    <?php if (empty($topupHistory)): ?>
                        <div class="empty-history">
                            <i class="fas fa-history" style="font-size: 48px; margin-bottom: 20px; opacity: 0.3;"></i>
                            <p>No topup history found</p>
                        </div>
                    <?php else: ?>
                        <table class="history-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>MINEM</th>
                                    <th>Method</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topupHistory as $record): ?>
                                <tr>
                                    <td><?php echo date('M d, Y H:i', strtotime($record['created_at'])); ?></td>
                                    <td>$<?php echo number_format($record['amount_usd'], 2); ?></td>
                                    <td><?php echo number_format($record['amount_minem']); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($record['method'])); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo htmlspecialchars($record['status']); ?>">
                                            <?php echo htmlspecialchars(ucfirst($record['status'])); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Constants from PHP
        const MINEM_PER_DOLLAR = <?php echo MINEM_PER_DOLLAR; ?>;
        const MIN_DEPOSIT_AMOUNT = <?php echo MIN_DEPOSIT_AMOUNT; ?>;
        const MAX_DEPOSIT_AMOUNT = <?php echo MAX_DEPOSIT_AMOUNT; ?>;

        // DOM elements
        const usdInput = document.getElementById('usd_amount');
        const minemPreview = document.getElementById('minemPreview');
        const minemAmount = document.getElementById('minemAmount');
        const validationMessage = document.getElementById('validationMessage');

        // Function to format number with commas
        function formatNumber(number) {
            return number.toLocaleString('en-US', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            });
        }

        // Function to calculate and display MINEM amount
        function updateMinemPreview() {
            const usdValue = parseFloat(usdInput.value) || 0;
            
            // Hide/show preview based on input
            if (usdValue > 0) {
                minemPreview.classList.add('active');
                
                // Calculate MINEM amount
                const calculatedMinem = usdValue * MINEM_PER_DOLLAR;
                minemAmount.textContent = formatNumber(calculatedMinem) + ' MINEM';
                
                // Validate input
                validateAmount(usdValue);
            } else {
                minemPreview.classList.remove('active');
                validationMessage.textContent = '';
                validationMessage.className = 'validation-message';
            }
        }

        // Function to validate amount
        function validateAmount(amount) {
            if (amount < MIN_DEPOSIT_AMOUNT) {
                validationMessage.textContent = `Minimum deposit is $${MIN_DEPOSIT_AMOUNT}`;
                validationMessage.className = 'validation-message validation-error';
                return false;
            } else if (amount > MAX_DEPOSIT_AMOUNT) {
                validationMessage.textContent = `Maximum deposit is $${MAX_DEPOSIT_AMOUNT}`;
                validationMessage.className = 'validation-message validation-error';
                return false;
            } else {
                validationMessage.textContent = `Valid amount. You'll receive ${formatNumber(amount * MINEM_PER_DOLLAR)} MINEM`;
                validationMessage.className = 'validation-message validation-success';
                return true;
            }
        }

        // Event listeners for real-time updates
        usdInput.addEventListener('input', updateMinemPreview);
        usdInput.addEventListener('change', updateMinemPreview);
        
        // Initialize preview if there's already a value
        document.addEventListener('DOMContentLoaded', function() {
            if (usdInput.value) {
                updateMinemPreview();
            }
            
            // Prevent form submission if amount is invalid
            document.querySelector('form').addEventListener('submit', function(e) {
                const usdValue = parseFloat(usdInput.value) || 0;
                if (!validateAmount(usdValue)) {
                    e.preventDefault();
                    usdInput.focus();
                }
            });
        });

        // Auto-format input on blur
        usdInput.addEventListener('blur', function() {
            if (this.value) {
                this.value = parseFloat(this.value).toFixed(2);
                updateMinemPreview();
            }
        });

        // Prevent invalid characters
        usdInput.addEventListener('keypress', function(e) {
            const charCode = e.which ? e.which : e.keyCode;
            const value = this.value + String.fromCharCode(charCode);
            
            // Allow only numbers and one decimal point
            if (charCode !== 46 && charCode > 31 && (charCode < 48 || charCode > 57)) {
                e.preventDefault();
                return;
            }
            
            // Allow only one decimal point
            if (charCode === 46 && this.value.includes('.')) {
                e.preventDefault();
                return;
            }
            
            // Limit to 2 decimal places
            if (this.value.includes('.') && this.value.split('.')[1].length >= 2) {
                e.preventDefault();
                return;
            }
            
            // Check if total value would exceed max
            if (parseFloat(value) > MAX_DEPOSIT_AMOUNT) {
                e.preventDefault();
                this.value = MAX_DEPOSIT_AMOUNT;
                updateMinemPreview();
            }
        });
    </script>
</body>
</html>