<?php
// login.php
session_start();

// Supabase configuration
define('SUPABASE_URL', 'https://vrgrmqrhrwkltopjwlrr.supabase.co');
define('SUPABASE_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InZyZ3JtcXJocndrbHRvcGp3bHJyIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjUwODQxODUsImV4cCI6MjA4MDY2MDE4NX0.6cffp0njkFx1zzfp1PT5s29oNlg2WXoNH8ZsBx2qvz0');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters";
    } else {
        // Sign in with Supabase Auth
        $ch = curl_init();
        $url = SUPABASE_URL . '/auth/v1/token?grant_type=password';

$data = [

    'email' => $email,

    'password' => $password

];

$headers = [

    'apikey: ' . SUPABASE_KEY,

    'Content-Type: application/json'

];

curl_setopt($ch, CURLOPT_URL, $url);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

curl_setopt($ch, CURLOPT_POST, true);

curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$response = curl_exec($ch);

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

$responseData = json_decode($response, true);
        
        if ($httpCode === 200) {
            // Store user data in session
            $_SESSION['user_id'] = $responseData['user']['id'];
            $_SESSION['email'] = $responseData['user']['email'];
            $_SESSION['access_token'] = $responseData['access_token'];
            $_SESSION['user_data'] = $responseData['user'];
            
            // Redirect to dashboard
            header('Location: dashboard.php');
            exit();
        } else {
            $error = "Invalid email or password";
        }
        
        curl_close($ch);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login - MineMechanics</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://unpkg.com/phosphor-icons@1.4.2/src/css/icons.css">
<style>
/* Same CSS as login.html */
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
  background: var(--gradient-violet);
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

.form-group {
  margin-bottom: 24px;
}

.form-label {
  display: block;
  margin-bottom: 8px;
  color: var(--text-secondary);
  font-size: 14px;
  font-weight: 500;
}

.form-input {
  width: 100%;
  padding: 16px 20px;
  background: rgba(255, 255, 255, 0.05);
  border: 2px solid rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  color: var(--text-primary);
  font-family: 'Inter', sans-serif;
  font-size: 16px;
  transition: all 0.3s ease;
}

.form-input:focus {
  outline: none;
  border-color: var(--violet);
  box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
}

.btn-submit {
  width: 100%;
  padding: 18px;
  background: var(--gradient-violet);
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
  box-shadow: 0 10px 25px rgba(139, 92, 246, 0.3);
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
      <i class="ph-lightning-fill logo-icon"></i>
      MineMechanics
    </div>
    
    <h1 class="auth-title">Welcome Back</h1>
    <p class="auth-subtitle">Sign in to continue to your mining empire</p>
    
    <?php if ($error): ?>
      <div class="alert alert-error">
        <i class="ph-warning-circle"></i> <?php echo htmlspecialchars($error); ?>
      </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['demo']) && $_GET['demo'] === 'true'): ?>
      <div class="alert alert-success">
        <i class="ph-info"></i> This is a demo. For real login, please use the form below.
      </div>
    <?php endif; ?>
    
    <form method="POST" action="">
      <div class="form-group">
        <label class="form-label">Email Address</label>
        <input type="email" name="email" class="form-input" placeholder="miner@example.com" required 
               value="<?php echo isset($_GET['email']) ? htmlspecialchars($_GET['email']) : ''; ?>">
      </div>
      
      <div class="form-group">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-input" placeholder="••••••••" required>
      </div>
      
      <button type="submit" class="btn-submit">
        <i class="ph-sign-in"></i> Sign In
      </button>
    </form>
    
    <div class="divider">
      <span>New to MineMechanics?</span>
    </div>
    
    <a href="signup.html" class="btn-switch">
      <i class="ph-user-plus"></i> Create Account
    </a>
  </div>
</div>

<script>
// Add interactive effects
document.querySelectorAll('.form-input').forEach(input => {
  input.addEventListener('focus', function() {
    this.parentElement.style.transform = 'translateY(-2px)';
  });
  
  input.addEventListener('blur', function() {
    this.parentElement.style.transform = 'translateY(0)';
  });
});
</script>
</body>
</html>