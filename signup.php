<?php
// signup.php - Combined HTML/PHP signup page with email confirmation
session_start();

// Supabase configuration
define('SUPABASE_URL', 'https://vrgrmqrhrwkltopjwlrr.supabase.co');
define('SUPABASE_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InZyZ3JtcXJocndrbHRvcGp3bHJyIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjUwODQxODUsImV4cCI6MjA4MDY2MDE4NX0.6cffp0njkFx1zzfp1PT5s29oNlg2WXoNH8ZsBx2qvz0');
define('SUPABASE_SECRET', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InZyZ3JtcXJocndrbHRvcGp3bHJyIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc2NTA4NDE4NSwiZXhwIjoyMDgwNjYwMTg1fQ.8VeeaMPbjUkffiHizrwJBVlLE028R2y2QOAkV9O5gXA');

$error = '';
$success = '';
$email_confirmation_message = '';
$show_form = true;
$form_values = [
    'username' => '',
    'email' => '',
    'location' => ''
];

// Function to create all required records for a new user
function createUserRecords($userId, $username, $email, $location) {
    $headers = [
        'apikey: ' . SUPABASE_KEY,
        'Authorization: Bearer ' . SUPABASE_SECRET,
        'Content-Type: application/json',
        'Prefer: return=representation'
    ];

    // 1️⃣ CREATE PROFILE
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => SUPABASE_URL . '/rest/v1/profiles',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode([
            'id' => $userId,
            'username' => $username,
            'email' => $email,
            'location' => $location
        ])
    ]);
    $response1 = curl_exec($ch);
    $code1 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code1 !== 201) {
        return "Profile creation failed: $response1";
    }

    // 2️⃣ CREATE BALANCE RECORD
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => SUPABASE_URL . '/rest/v1/balances',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode([
            'user_id' => $userId,
            'minem' => 0,
            'm2' => 0,
            'usd_equivalent' => 0
        ])
    ]);
    curl_exec($ch);
    curl_close($ch);

    // 3️⃣ CREATE DAILY REPORT
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => SUPABASE_URL . '/rest/v1/daily_reports',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode([
            'user_id' => $userId,
            'total_ths' => 0,
            'total_miners' => 0,
            'energy_generated_wh' => 0,
            'energy_used_wh' => 0,
            'energy_balance_wh' => 0,
            'report_date' => date('Y-m-d')
        ])
    ]);
    curl_exec($ch);
    curl_close($ch);

    return true;
}
    
// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = filter_var($_POST['username'], FILTER_SANITIZE_STRING);
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'] ?? '';
    $location = filter_var($_POST['location'], FILTER_SANITIZE_STRING);
    $terms = isset($_POST['terms']) ? true : false;
    
    // Store form values for repopulation
    $form_values = [
        'username' => $username,
        'email' => $email,
        'location' => $location
    ];
    
    // Validation
    if (strlen($username) < 3 || strlen($username) > 30) {
        $error = "Username must be between 3 and 30 characters";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters";
    } elseif (empty($location)) {
        $error = "Please select a location";
    } elseif (!$terms) {
        $error = "Please agree to the Terms of Service and Privacy Policy";
    } else {
        try {
            // Sign up with Supabase Auth
            $ch = curl_init();
            
            $url = SUPABASE_URL . '/auth/v1/signup';
            $data = [
                'email' => $email,
                'password' => $password,
                'data' => [
                    'username' => $username,
                    'location' => $location
                ]
            ];
            
            $headers = [
                'apikey: ' . SUPABASE_KEY,
                'Content-Type: application/json',
                'Prefer: return=representation'
            ];
            
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $responseData = json_decode($response, true);
            
            if ($httpCode === 200 && isset($responseData['user']['id'])) {
                $userId = $responseData['user']['id'];
                
                // Create all user records with ZERO values
                $recordsResult = createUserRecords($userId, $username, $email, $location);
                
                if ($recordsResult === true) {
                    // Check if email confirmation was sent
                    if (isset($responseData['confirmation_sent_at']) && $responseData['confirmation_sent_at']) {
                        // Email confirmation was sent successfully
                        $email_confirmation_message = "A confirmation email has been sent to $email. Please check your inbox and click the verification link to activate your account.";
                        $show_form = false; // Hide the form
                        
                        // Clear form values
                        $form_values = [
                            'username' => '',
                            'email' => '',
                            'location' => ''
                        ];
                    } elseif (isset($responseData['user']['email_confirmed_at']) && $responseData['user']['email_confirmed_at']) {
                        // User already confirmed email (shouldn't happen with new signup)
                        $success = "Account created successfully! You can now log in.";
                        $show_form = false;
                        header('refresh:3;url=login.php');
                    } else {
                        // No confirmation sent but user created
                        $email_confirmation_message = "Account created! Please check your email $email for confirmation instructions.";
                        $show_form = false;
                    }
                } else {
                    $error = $recordsResult;
                }
            } elseif (isset($responseData['msg'])) {
                $error = $responseData['msg'];
            } elseif (isset($responseData['message'])) {
                $error = $responseData['message'];
            } elseif (isset($responseData['error'])) {
                $error = $responseData['error'];
            } else {
                $error = "Confirm your email.";
            }
            
            curl_close($ch);
        } catch (Exception $e) {
            $error = "An error occurred: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Sign Up - MineMechanics</title>
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
  --gradient-violet: linear-gradient(135deg, #8B5CF6 0%, #A78BFA 100%);
  --gradient-orange: linear-gradient(135deg, #F97316 0%, #FB923C 100%);
  --gradient-green: linear-gradient(135deg, #10B981 0%, #34D399 100%);
  --violet: #8B5CF6;
  --orange: #F97316;
  --green: #10B981;
  --error: #EF4444;
  --success: #10B981;
  --info: #3B82F6;
}

* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: 'Space Grotesk', sans-serif;
  background-color: var(--bg-dark);
  color: var(--text-primary);
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  overflow: hidden;
}

body::before {
  content: '';
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: 
    radial-gradient(circle at 20% 30%, rgba(139, 92, 246, 0.15) 0%, transparent 50%),
    radial-gradient(circle at 80% 70%, rgba(249, 115, 22, 0.1) 0%, transparent 50%);
  z-index: -1;
}

.container {
  width: 100%;
  max-width: 480px;
  padding: 40px;
}

.back-home {
  position: absolute;
  top: 30px;
  left: 30px;
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--text-muted);
  text-decoration: none;
  font-size: 14px;
  transition: color 0.3s ease;
  z-index: 100;
}

.back-home:hover {
  color: var(--text-primary);
}

.auth-card {
  background: var(--bg-card);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 24px;
  padding: 48px;
  backdrop-filter: blur(10px);
  position: relative;
  overflow: hidden;
  box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
}

.auth-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 4px;
  background: var(--gradient-green);
}

.logo {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  font-size: 32px;
  font-weight: 800;
  margin-bottom: 40px;
  background: var(--gradient-violet);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  text-transform: uppercase;
}

.logo-icon {
  font-size: 36px;
}

.auth-title {
  font-size: 32px;
  font-weight: 700;
  margin-bottom: 8px;
  text-align: center;
}

.auth-subtitle {
  color: var(--text-muted);
  text-align: center;
  margin-bottom: 40px;
  font-family: 'Inter', sans-serif;
  font-size: 16px;
}

.bonus-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: rgba(16, 185, 129, 0.1);
  border: 1px solid rgba(16, 185, 129, 0.2);
  color: #6EE7B7;
  padding: 8px 16px;
  border-radius: 20px;
  font-size: 14px;
  margin-left: 10px;
}

.form-group {
  margin-bottom: 24px;
}

.form-label {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 8px;
  color: var(--text-secondary);
  font-size: 14px;
  font-weight: 500;
}

.form-input, .form-select {
  width: 100%;
  padding: 16px 20px;
  background: rgba(255, 255, 255, 0.05);
  border: 2px solid rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  color: var(--text-primary);
  font-family: 'Inter', sans-serif;
  font-size: 16px;
  transition: all 0.3s ease;
  appearance: none;
}

.form-select {
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%238B5CF6' viewBox='0 0 256 256'%3E%3Cpath d='M213.66,101.66l-80,80a8,8,0,0,1-11.32,0l-80-80A8,8,0,0,1,53.66,90.34L128,164.69l74.34-74.35a8,8,0,0,1,11.32,11.32Z'%3E%3C/path%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 20px center;
  background-size: 16px;
  padding-right: 50px;
}

.form-input:focus, .form-select:focus {
  outline: none;
  border-color: var(--violet);
  box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
}

.password-strength {
  height: 4px;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 2px;
  margin-top: 8px;
  overflow: hidden;
}

.strength-meter {
  height: 100%;
  width: 0%;
  transition: all 0.3s ease;
  border-radius: 2px;
}

.strength-weak { background: #EF4444; }
.strength-medium { background: #F59E0B; }
.strength-strong { background: #10B981; }

.btn-submit {
  width: 100%;
  padding: 18px;
  background: var(--gradient-green);
  color: white;
  border: none;
  border-radius: 12px;
  font-family: 'Space Grotesk', sans-serif;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  margin-top: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
}

.btn-submit:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3);
}

.divider {
  display: flex;
  align-items: center;
  margin: 32px 0;
  color: var(--text-muted);
  font-size: 14px;
}

.divider::before,
.divider::after {
  content: '';
  flex: 1;
  height: 1px;
  background: rgba(255, 255, 255, 0.1);
}

.divider span {
  padding: 0 16px;
}

.btn-switch {
  width: 100%;
  padding: 16px;
  background: transparent;
  color: var(--text-primary);
  border: 2px solid rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  font-family: 'Space Grotesk', sans-serif;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  text-decoration: none;
  display: block;
  text-align: center;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
}

.btn-switch:hover {
  border-color: var(--violet);
  background: rgba(139, 92, 246, 0.05);
}

.alert {
  padding: 16px;
  border-radius: 12px;
  margin-bottom: 24px;
  font-size: 14px;
  font-family: 'Inter', sans-serif;
  display: flex;
  align-items: flex-start;
  gap: 10px;
}

.alert i {
  margin-top: 2px;
}

.alert-error {
  background: rgba(239, 68, 68, 0.1);
  border: 1px solid rgba(239, 68, 68, 0.2);
  color: #FCA5A5;
}

.alert-success {
  background: rgba(16, 185, 129, 0.1);
  border: 1px solid rgba(16, 185, 129, 0.2);
  color: #6EE7B7;
}

.alert-info {
  background: rgba(59, 130, 246, 0.1);
  border: 1px solid rgba(59, 130, 246, 0.2);
  color: #93C5FD;
}

/* Terms checkbox */
.terms-group {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  margin: 24px 0;
}

.terms-checkbox {
  margin-top: 4px;
  accent-color: var(--violet);
  min-width: 18px;
  min-height: 18px;
}

.terms-label {
  font-size: 14px;
  color: var(--text-muted);
  line-height: 1.5;
}

.terms-link {
  color: var(--violet);
  text-decoration: none;
}

.terms-link:hover {
  text-decoration: underline;
}

.success-card {
  text-align: center;
  padding: 40px 20px;
}

.success-icon {
  font-size: 64px;
  color: var(--success);
  margin-bottom: 20px;
}

.success-title {
  font-size: 24px;
  font-weight: 700;
  margin-bottom: 16px;
  color: var(--success);
}

.success-message {
  color: var(--text-muted);
  margin-bottom: 30px;
  line-height: 1.6;
}

.success-actions {
  display: flex;
  flex-direction: column;
  gap: 15px;
  margin-top: 30px;
}

.resend-btn {
  background: transparent;
  color: var(--violet);
  border: 2px solid var(--violet);
  padding: 12px 24px;
  border-radius: 12px;
  font-family: 'Space Grotesk', sans-serif;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.resend-btn:hover {
  background: rgba(139, 92, 246, 0.1);
}

/* Animations */
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

.auth-card {
  animation: fadeIn 0.6s ease-out;
}

/* Responsive */
@media (max-width: 600px) {
  .container {
    padding: 20px;
  }
  
  .auth-card {
    padding: 32px 24px;
  }
  
  .back-home {
    top: 20px;
    left: 20px;
  }
}
</style>
</head>
<body>
<a href="index.php" class="back-home">
  <i class="ph-arrow-left"></i> Back to Home
</a>

<div class="container">
  <div class="auth-card">
    <div class="logo">
      <i class="ph-lightning-fill logo-icon"></i> SIGNUP
    </div>
    
    <h1 class="auth-title">Start Mining</h1>
    <p class="auth-subtitle">
      Create your account. <span class="bonus-badge"><i class="ph-gift"></i>0% fee*</span>
    </p>
    
    <?php if ($error): ?>
      <div class="alert alert-error" id="errorAlert">
        <i class="ph-warning-circle"></i> <span><?php echo htmlspecialchars($error); ?></span>
      </div>
    <?php endif; ?>
    
    <?php if ($email_confirmation_message): ?>
      <div class="success-card">
        <div class="success-icon">
          <i class="ph-envelope-simple-open"></i>
        </div>
        <h2 class="success-title">Confirm Your Email</h2>
        <p class="success-message">
          <?php echo htmlspecialchars($email_confirmation_message); ?>
        </p>
        <p style="color: var(--text-muted); font-size: 14px; margin-top: 20px;">
          <i class="ph-info"></i> Check your spam folder if you don't see the email within a few minutes.
        </p>
        
        <div class="success-actions">
          <a href="login.php" class="btn-submit">
            <i class="ph-sign-in"></i> Go to Login
          </a>
          <a href="index.php" class="resend-btn">
            <i class="ph-house"></i> Back to Home
          </a>
        </div>
      </div>
    <?php elseif ($success): ?>
      <div class="alert alert-success" id="successAlert">
        <i class="ph-check-circle"></i> <span><?php echo htmlspecialchars($success); ?></span>
        <p style="margin-top: 8px; font-size: 12px; width: 100%;">Redirecting to login...</p>
      </div>
    <?php elseif ($show_form): ?>
    <form method="POST" action="" id="signupForm">
      <div class="form-group">
        <label class="form-label">Username</label>
        <input type="text" name="username" id="username" class="form-input" placeholder="miner42" required minlength="3" maxlength="30"
               value="<?php echo htmlspecialchars($form_values['username']); ?>">
      </div>
      
      <div class="form-group">
        <label class="form-label">Email Address</label>
        <input type="email" name="email" id="email" class="form-input" placeholder="miner@example.com" required
               value="<?php echo htmlspecialchars($form_values['email']); ?>">
      </div>
      
      <div class="form-group">
        <label class="form-label">Password</label>
        <input type="password" name="password" id="password" class="form-input" placeholder="••••••••" required minlength="6">
        <div class="password-strength">
          <div class="strength-meter" id="strengthMeter"></div>
        </div>
      </div>
      
      <div class="form-group">
        <label class="form-label">Confirm Password</label>
        <input type="password" name="confirm_password" id="confirm_password" class="form-input" placeholder="••••••••" required>
      </div>
      
      <div class="form-group">
        <label class="form-label">Starting Location</label>
        <select name="location" id="location" class="form-select" required>
          <option value="">Select your mining location</option>
          <option value="California" <?php echo $form_values['location'] === 'California' ? 'selected' : ''; ?>>California</option>
          <option value="Iceland" <?php echo $form_values['location'] === 'Iceland' ? 'selected' : ''; ?>>Iceland</option>
          <option value="Berlin" <?php echo $form_values['location'] === 'Berlin' ? 'selected' : ''; ?>>Berlin</option>
          <option value="Sahara" <?php echo $form_values['location'] === 'Sahara' ? 'selected' : ''; ?>>Sahara</option>
          <option value="Mumbai" <?php echo $form_values['location'] === 'Mumbai' ? 'selected' : ''; ?>>Mumbai</option>
          <option value="Rio" <?php echo $form_values['location'] === 'Rio' ? 'selected' : ''; ?>>Rio</option>
          <option value="Antarctica" <?php echo $form_values['location'] === 'Antarctica' ? 'selected' : ''; ?>>Antarctica</option>
        </select>
      </div>
      
      <div class="terms-group">
        <input type="checkbox" name="terms" id="terms" class="terms-checkbox" required>
        <label for="terms" class="terms-label">
          I agree to the <a href="#" class="terms-link">Terms of Service</a> and 
          <a href="#" class="terms-link">Privacy Policy</a>. I understand that this is a simulation.
        </label>
      </div>
      
      <button type="submit" class="btn-submit">
        <i class="ph-user-plus"></i> Create Account
      </button>
    </form>
    
    <div class="divider">
      <span>Already have an account?</span>
    </div>
    
    <a href="login.php" class="btn-switch">
      <i class="ph-sign-in"></i> Sign In Instead
    </a>
    <?php endif; ?>
  </div>
</div>

<script>
// Password strength meter
const passwordInput = document.getElementById('password');
const strengthMeter = document.getElementById('strengthMeter');

if (passwordInput) {
  passwordInput.addEventListener('input', function() {
    const password = this.value;
    let strength = 0;
    
    // Length check
    if (password.length >= 8) strength += 25;
    if (password.length >= 12) strength += 25;
    
    // Complexity checks
    if (/[A-Z]/.test(password)) strength += 25;
    if (/[0-9]/.test(password)) strength += 25;
    if (/[^A-Za-z0-9]/.test(password)) strength += 25;
    
    // Cap at 100%
    strength = Math.min(strength, 100);
    
    // Update meter
    strengthMeter.style.width = strength + '%';
    
    // Update color
    if (strength < 50) {
      strengthMeter.className = 'strength-meter strength-weak';
    } else if (strength < 75) {
      strengthMeter.className = 'strength-meter strength-medium';
    } else {
      strengthMeter.className = 'strength-meter strength-strong';
    }
  });
}

// Form validation before submission
const signupForm = document.getElementById('signupForm');
if (signupForm) {
  signupForm.addEventListener('submit', function(e) {
    const username = document.getElementById('username');
    const email = document.getElementById('email');
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');
    const location = document.getElementById('location');
    const terms = document.getElementById('terms');
    
    let isValid = true;
    
    // Reset previous error states
    document.querySelectorAll('.form-input, .form-select').forEach(el => {
      el.style.borderColor = '';
    });
    
    // Username validation
    if (username.value.length < 3 || username.value.length > 30) {
      username.style.borderColor = 'var(--error)';
      isValid = false;
    }
    
    // Email validation
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email.value)) {
      email.style.borderColor = 'var(--error)';
      isValid = false;
    }
    
    // Password validation
    if (password.value.length < 6) {
      password.style.borderColor = 'var(--error)';
      isValid = false;
    }
    
    // Confirm password
    if (password.value !== confirmPassword.value) {
      confirmPassword.style.borderColor = 'var(--error)';
      isValid = false;
    }
    
    // Location validation
    if (!location.value) {
      location.style.borderColor = 'var(--error)';
      isValid = false;
    }
    
    // Terms validation
    if (!terms.checked) {
      isValid = false;
    }
    
    if (!isValid) {
      e.preventDefault();
      
      // Scroll to first error
      const firstError = document.querySelector('[style*="border-color: var(--error)"]');
      if (firstError) {
        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    }
  });
}

// Add interactive effects to form inputs
const formInputs = document.querySelectorAll('.form-input, .form-select');
if (formInputs.length > 0) {
  formInputs.forEach(input => {
    input.addEventListener('focus', function() {
      this.parentElement.style.transform = 'translateY(-2px)';
    });
    
    input.addEventListener('blur', function() {
      this.parentElement.style.transform = 'translateY(0)';
    });
  });
}

// Real-time password match validation
const confirmPasswordInput = document.getElementById('confirm_password');
if (confirmPasswordInput && passwordInput) {
  confirmPasswordInput.addEventListener('input', function() {
    if (this.value !== passwordInput.value) {
      this.style.borderColor = 'var(--error)';
    } else {
      this.style.borderColor = '';
    }
  });
  
  passwordInput.addEventListener('input', function() {
    if (confirmPasswordInput.value && this.value !== confirmPasswordInput.value) {
      confirmPasswordInput.style.borderColor = 'var(--error)';
    } else if (confirmPasswordInput.value) {
      confirmPasswordInput.style.borderColor = '';
    }
  });
}

// Auto-hide error alerts after 5 seconds
setTimeout(() => {
  const errorAlerts = document.querySelectorAll('.alert-error');
  errorAlerts.forEach(alert => {
    alert.style.opacity = '0';
    alert.style.transition = 'opacity 0.5s ease';
    setTimeout(() => {
      alert.style.display = 'none';
    }, 500);
  });
}, 5000);
</script>
</body>
</html>