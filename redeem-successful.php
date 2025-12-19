<?php
// redeem-successful.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['access_token'])) {
    header('Location: login.php');
    exit();
}

// Check if success data exists
if (!isset($_SESSION['redeem_success_data'])) {
    header('Location: redeem.php');
    exit();
}

// Get success data
$successData = $_SESSION['redeem_success_data'];

// Clear the success data to prevent showing again on refresh
unset($_SESSION['redeem_success_data']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Redemption Successful - MineMechanics</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://unpkg.com/phosphor-icons@1.4.2/src/css/icons.css">
<style>
:root {
  --bg-dark: #000000;
  --bg-darker: #0a0a0a;
  --bg-card: rgba(30, 30, 30, 0.7);
  --text-primary: #ffffff;
  --text-secondary: rgba(255, 255, 255, 0.8);
  --text-muted: rgba(255, 255, 255, 0.6);
  --gradient-green: linear-gradient(135deg, #10B981 0%, #34D399 100%);
  --gradient-blue: linear-gradient(135deg, #3B82F6 0%, #60A5FA 100%);
  --green: #10B981;
  --blue: #3B82F6;
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
  justify-content: center;
  align-items: center;
  padding: 20px;
}

.success-container {
  background: var(--bg-card);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 24px;
  padding: 50px;
  max-width: 600px;
  width: 100%;
  text-align: center;
  position: relative;
  overflow: hidden;
}

.success-container::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 4px;
  background: var(--gradient-green);
}

.success-icon {
  font-size: 80px;
  margin-bottom: 30px;
  background: var(--gradient-green);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  animation: bounce 1s infinite alternate;
}

@keyframes bounce {
  from { transform: translateY(0); }
  to { transform: translateY(-10px); }
}

.success-title {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 32px;
  font-weight: 700;
  margin-bottom: 15px;
  color: var(--text-primary);
}

.success-subtitle {
  color: var(--text-muted);
  font-size: 18px;
  margin-bottom: 40px;
  line-height: 1.6;
}

.details-table {
  width: 100%;
  border-collapse: collapse;
  margin: 30px 0;
  background: rgba(0, 0, 0, 0.3);
  border-radius: 16px;
  overflow: hidden;
}

.details-table tr {
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.details-table tr:last-child {
  border-bottom: none;
}

.details-table td {
  padding: 20px;
  text-align: left;
}

.details-table td:first-child {
  color: var(--text-muted);
  font-weight: 500;
  width: 40%;
}

.details-table td:last-child {
  color: var(--text-primary);
  font-weight: 600;
  text-align: right;
}

.highlight {
  color: var(--green) !important;
  font-weight: 700 !important;
}

.back-button {
  display: inline-block;
  padding: 18px 40px;
  background: var(--gradient-blue);
  color: white;
  border: none;
  border-radius: 16px;
  font-family: 'Space Grotesk', sans-serif;
  font-size: 18px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.3s ease;
  text-decoration: none;
  margin-top: 30px;
}

.back-button:hover {
  transform: translateY(-3px);
  box-shadow: 0 15px 30px rgba(59, 130, 246, 0.4);
}

.note {
  color: var(--text-muted);
  font-size: 14px;
  margin-top: 30px;
  padding: 15px;
  background: rgba(59, 130, 246, 0.1);
  border-radius: 12px;
  border: 1px solid rgba(59, 130, 246, 0.2);
}

@media (max-width: 768px) {
  .success-container {
    padding: 30px 20px;
  }
  
  .success-icon {
    font-size: 60px;
  }
  
  .success-title {
    font-size: 26px;
  }
  
  .success-subtitle {
    font-size: 16px;
  }
  
  .details-table td {
    padding: 15px 10px;
    font-size: 14px;
  }
  
  .back-button {
    padding: 15px 30px;
    font-size: 16px;
  }
}
</style>
</head>
<body>
<div class="success-container">
  <div class="success-icon">
    <i class="ph-check-circle"></i>
  </div>
  
  <h2 class="success-title">Redemption Successful!</h2>
  <p class="success-subtitle">
    Your redemption request has been submitted successfully and is being processed.
  </p>
  
  <table class="details-table">
    <tr>
      <td>Redemption Method:</td>
      <td><?php echo htmlspecialchars($successData['method']); ?></td>
    </tr>
    <tr>
      <td>Coin/Type:</td>
      <td><?php echo htmlspecialchars($successData['coin']); ?></td>
    </tr>
    <tr>
      <td>Amount Redeemed:</td>
      <td class="highlight">$<?php echo number_format($successData['amount'], 2); ?></td>
    </tr>
    <tr>
      <td>Processing Fee:</td>
      <td>$<?php echo number_format($successData['fee'], 2); ?></td>
    </tr>
    <tr>
      <td>Net Amount:</td>
      <td class="highlight">$<?php echo number_format($successData['net_amount'], 2); ?></td>
    </tr>
    <tr>
      <td>Date & Time:</td>
      <td><?php echo htmlspecialchars($successData['timestamp']); ?></td>
    </tr>
    <?php if (!empty($successData['address'])): ?>
    <tr>
      <td>Recipient:</td>
      <td><?php echo htmlspecialchars($successData['address']); ?></td>
    </tr>
    <?php endif; ?>
  </table>
  
  <a href="dashboard.php" class="back-button">
    <i class="ph-arrow-left"></i> Back to Dashboard
  </a>
  
  <div class="note">
    <i class="ph-info"></i> Your redemption request status is now "Pending". It will be processed within 24-48 hours.
  </div>
</div>

<script>
// Prevent back button from resubmitting form
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}

// Auto-redirect after 30 seconds (optional)
setTimeout(function() {
    window.location.href = 'dashboard.php';
}, 30000);
</script>
</body>
</html>