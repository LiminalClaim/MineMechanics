<?php
// index.php - Landing Page
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>MineMechanics — Global Crypto Mining Simulation</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<!-- FONTS -->
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">

<!-- ICONS -->
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
  --gradient-cyan: linear-gradient(135deg, #06B6D4 0%, #22D3EE 100%);
  
  --violet: #8B5CF6;
  --orange: #F97316;
  --green: #10B981;
  --cyan: #06B6D4;
  --yellow: #FACC15;
  
  --glow-violet: 0 0 40px rgba(139, 92, 246, 0.3);
  --glow-orange: 0 0 40px rgba(249, 115, 22, 0.3);
  --glow-green: 0 0 40px rgba(16, 185, 129, 0.3);
  --glow-cyan: 0 0 40px rgba(6, 182, 212, 0.3);
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
  overflow-x: hidden;
  position: relative;
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
    radial-gradient(circle at 80% 70%, rgba(249, 115, 22, 0.1) 0%, transparent 50%),
    radial-gradient(circle at 40% 80%, rgba(16, 185, 129, 0.1) 0%, transparent 50%);
  z-index: -1;
}

.container {
  max-width: 1280px;
  margin: 0 auto;
  padding: 0 24px;
}

/* NAVIGATION */
.navbar {
  padding: 32px 0;
  display: flex;
  justify-content: space-between;
  align-items: center;
  position: relative;
  z-index: 100;
}

.logo {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 28px;
  font-weight: 800;
  background: var(--gradient-violet);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  text-transform: uppercase;
  letter-spacing: -0.5px;
}

.logo-icon {
  font-size: 32px;
  background: var(--gradient-violet);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.nav-buttons {
  display: flex;
  gap: 16px;
}

.btn-nav {
  padding: 14px 28px;
  border-radius: 12px;
  font-weight: 600;
  font-size: 16px;
  cursor: pointer;
  transition: all 0.3s ease;
  border: none;
  font-family: 'Space Grotesk', sans-serif;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.btn-login {
  background: transparent;
  color: var(--text-primary);
  border: 2px solid rgba(255, 255, 255, 0.1);
}

.btn-login:hover {
  border-color: var(--violet);
  box-shadow: var(--glow-violet);
}

.btn-signup {
  background: var(--gradient-orange);
  color: white;
}

.btn-signup:hover {
  transform: translateY(-2px);
  box-shadow: var(--glow-orange);
}

/* HERO SECTION */
.hero {
  text-align: center;
  padding: 80px 0 120px;
  position: relative;
}

.hero-title {
  font-size: 84px;
  font-weight: 800;
  line-height: 1;
  margin-bottom: 24px;
  background: linear-gradient(90deg, #ffffff 0%, rgba(255, 255, 255, 0.8) 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  letter-spacing: -2px;
}

.hero-subtitle {
  font-size: 24px;
  color: var(--text-secondary);
  max-width: 800px;
  margin: 0 auto 48px;
  line-height: 1.6;
  font-family: 'Inter', sans-serif;
}

.subtitle-highlight {
  background: var(--gradient-violet);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  font-weight: 600;
}

.hero-buttons {
  display: flex;
  gap: 20px;
  justify-content: center;
  margin-top: 48px;
}

.btn-primary {
  padding: 20px 40px;
  border-radius: 16px;
  font-size: 18px;
  font-weight: 600;
  border: none;
  cursor: pointer;
  transition: all 0.3s ease;
  font-family: 'Space Grotesk', sans-serif;
  display: flex;
  align-items: center;
  gap: 12px;
  text-decoration: none;
}

.btn-start {
  background: var(--gradient-green);
  color: white;
}

.btn-start:hover {
  transform: translateY(-4px);
  box-shadow: var(--glow-green);
}

.btn-explore {
  background: transparent;
  color: var(--text-primary);
  border: 2px solid rgba(255, 255, 255, 0.15);
}

.btn-explore:hover {
  border-color: var(--cyan);
  box-shadow: var(--glow-cyan);
}

/* LOCATIONS SECTION */
.locations-section {
  padding: 100px 0;
}

.section-title {
  font-size: 48px;
  font-weight: 700;
  margin-bottom: 48px;
  text-align: center;
  background: var(--gradient-orange);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.locations-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 24px;
  margin-top: 48px;
}

.location-card {
  background: var(--bg-card);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 20px;
  padding: 32px;
  text-align: center;
  transition: all 0.4s ease;
  backdrop-filter: blur(10px);
  position: relative;
  overflow: hidden;
}

.location-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 4px;
  background: var(--gradient-violet);
  opacity: 0;
  transition: opacity 0.3s ease;
}

.location-card:hover::before {
  opacity: 1;
}

.location-card:hover {
  transform: translateY(-8px);
  border-color: rgba(255, 255, 255, 0.2);
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
}

.location-icon {
  font-size: 48px;
  margin-bottom: 20px;
  background: var(--gradient-cyan);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.location-name {
  font-size: 24px;
  font-weight: 600;
  color: var(--text-primary);
}

/* HARDWARE SECTION */
.hardware-section {
  padding: 100px 0;
}

.hardware-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 32px;
  margin-top: 48px;
}

.hardware-card {
  background: var(--bg-card);
  border-radius: 24px;
  padding: 40px 32px;
  border: 1px solid rgba(255, 255, 255, 0.1);
  transition: all 0.4s ease;
  position: relative;
  overflow: hidden;
}

.hardware-card::after {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: linear-gradient(135deg, transparent 0%, rgba(255, 255, 255, 0.02) 100%);
  pointer-events: none;
}

.hardware-card:hover {
  transform: translateY(-8px);
  border-color: rgba(255, 255, 255, 0.2);
  box-shadow: 0 25px 50px rgba(0, 0, 0, 0.4);
}

.hardware-icon {
  font-size: 56px;
  margin-bottom: 24px;
  display: inline-block;
}

.hardware-title {
  font-size: 28px;
  font-weight: 600;
  margin-bottom: 12px;
  color: var(--text-primary);
}

.hardware-desc {
  color: var(--text-muted);
  font-family: 'Inter', sans-serif;
  line-height: 1.6;
  font-size: 16px;
}

/* ENERGY SECTION */
.energy-section {
  padding: 100px 0;
}

.energy-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  gap: 32px;
  margin-top: 48px;
}

.energy-card {
  background: var(--bg-card);
  border-radius: 24px;
  padding: 40px;
  border: 1px solid rgba(255, 255, 255, 0.1);
  transition: all 0.4s ease;
}

.energy-card:hover {
  transform: translateY(-6px);
  border-color: rgba(255, 255, 255, 0.2);
}

.energy-icon {
  font-size: 56px;
  margin-bottom: 24px;
  display: inline-block;
}

.energy-title {
  font-size: 28px;
  font-weight: 600;
  margin-bottom: 16px;
  color: var(--text-primary);
}

/* ECONOMY SECTION */
.economy-section {
  padding: 100px 0;
  text-align: center;
}

.economy-display {
  background: var(--bg-card);
  border-radius: 32px;
  padding: 80px 60px;
  border: 1px solid rgba(255, 255, 255, 0.1);
  margin-top: 48px;
  position: relative;
  overflow: hidden;
}

.economy-display::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 1px;
  background: var(--gradient-violet);
}

.economy-value {
  font-size: 72px;
  font-weight: 800;
  margin: 24px 0;
  background: var(--gradient-orange);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  line-height: 1;
}

.economy-desc {
  color: var(--text-muted);
  font-size: 18px;
  max-width: 600px;
  margin: 0 auto;
  font-family: 'Inter', sans-serif;
}

/* MECHANICS SECTION */
.mechanics-section {
  padding: 100px 0;
}

.mechanics-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 24px;
  margin-top: 48px;
}

.mechanic-card {
  background: var(--bg-card);
  border-radius: 20px;
  padding: 32px;
  border: 1px solid rgba(255, 255, 255, 0.1);
  transition: all 0.4s ease;
}

.mechanic-card:hover {
  transform: translateY(-6px);
  border-color: rgba(255, 255, 255, 0.2);
}

.mechanic-icon {
  font-size: 40px;
  margin-bottom: 20px;
  display: inline-block;
}

.mechanic-title {
  font-size: 22px;
  font-weight: 600;
  margin-bottom: 12px;
  color: var(--text-primary);
}

/* FOOTER */
.footer {
  padding: 80px 0 40px;
  text-align: center;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
  margin-top: 100px;
}

.footer-logo {
  font-size: 32px;
  font-weight: 800;
  background: var(--gradient-violet);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  margin-bottom: 24px;
  text-transform: uppercase;
}

.footer-tagline {
  font-size: 20px;
  color: var(--text-secondary);
  margin-bottom: 48px;
  font-family: 'Inter', sans-serif;
}

.footer-links {
  display: flex;
  justify-content: center;
  gap: 40px;
  margin-bottom: 60px;
  flex-wrap: wrap;
}

.footer-link {
  color: var(--text-muted);
  text-decoration: none;
  font-size: 16px;
  transition: color 0.3s ease;
  font-family: 'Inter', sans-serif;
}

.footer-link:hover {
  color: var(--text-primary);
}

.copyright {
  color: var(--text-muted);
  font-size: 14px;
  font-family: 'Inter', sans-serif;
  opacity: 0.6;
}

/* RESPONSIVE */
@media (max-width: 768px) {
  .hero-title {
    font-size: 52px;
  }
  
  .hero-subtitle {
    font-size: 20px;
    padding: 0 16px;
  }
  
  .hero-buttons {
    flex-direction: column;
    align-items: center;
    padding: 0 16px;
  }
  
  .btn-primary {
    width: 100%;
    max-width: 300px;
    justify-content: center;
  }
  
  .section-title {
    font-size: 36px;
  }
  
  .economy-value {
    font-size: 48px;
  }
  
  .locations-grid,
  .hardware-grid,
  .energy-grid,
  .mechanics-grid {
    grid-template-columns: 1fr;
    padding: 0 16px;
  }
  
  .navbar {
    flex-direction: column;
    gap: 24px;
    padding: 24px 0;
  }
  
  .nav-buttons {
    width: 100%;
    justify-content: center;
  }
}

/* Additional Features */
.conversion-banner {
  background: var(--bg-card);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 20px;
  padding: 30px;
  margin: 40px auto;
  max-width: 800px;
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 20px;
  flex-wrap: wrap;
  backdrop-filter: blur(10px);
}

.conversion-item {
  text-align: center;
  padding: 0 20px;
}

.conversion-value {
  font-size: 36px;
  font-weight: 800;
  background: var(--gradient-green);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  margin-bottom: 5px;
}

.conversion-label {
  color: var(--text-muted);
  font-size: 14px;
}

.equals {
  font-size: 24px;
  color: var(--orange);
  font-weight: 800;
}

/* Feature Cards */
.feature-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 30px;
  margin-top: 50px;
}

.feature-card {
  background: var(--bg-card);
  border-radius: 20px;
  padding: 35px;
  border: 1px solid rgba(255, 255, 255, 0.1);
  transition: all 0.4s ease;
  text-align: center;
}

.feature-card:hover {
  transform: translateY(-8px);
  border-color: var(--violet);
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
}

.feature-icon {
  font-size: 48px;
  margin-bottom: 20px;
  color: var(--green);
}

.feature-title {
  font-size: 24px;
  font-weight: 600;
  margin-bottom: 15px;
  color: var(--text-primary);
}

.feature-desc {
  color: var(--text-muted);
  font-family: 'Inter', sans-serif;
  line-height: 1.6;
  font-size: 16px;
}

/* Donation Banner */
.donation-banner {
  background: linear-gradient(135deg, var(--violet), var(--cyan));
  border-radius: 20px;
  padding: 40px;
  text-align: center;
  margin: 60px 0;
  position: relative;
  overflow: hidden;
}

.donation-banner::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: linear-gradient(45deg, transparent 30%, rgba(255, 255, 255, 0.1) 50%, transparent 70%);
  animation: shine 3s infinite linear;
}

@keyframes shine {
  0% { transform: translateX(-100%); }
  100% { transform: translateX(100%); }
}

.donation-percentage {
  font-size: 72px;
  font-weight: 800;
  color: white;
  margin: 10px 0;
  text-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
}

.donation-text {
  font-size: 24px;
  color: rgba(255, 255, 255, 0.9);
  margin-top: 10px;
}
</style>
</head>

<body>
<div class="container">
  
  <!-- NAVIGATION -->
  <nav class="navbar">
    <div class="logo">
      <i class="ph-lightning-fill logo-icon"></i>
      MineMechanics
    </div>
    <div class="nav-buttons">
      <?php if (isset($_SESSION['user_id'])): ?>
        <a href="dashboard.php" class="btn-nav btn-signup">
          <i class="ph-gauge"></i> Dashboard
        </a>
        <a href="logout.php" class="btn-nav btn-login">
          <i class="ph-sign-out"></i> Logout
        </a>
      <?php else: ?>
        <a href="login.php" class="btn-nav btn-login">
          <i class="ph-sign-in"></i> Login
        </a>
        <a href="signup.php" class="btn-nav btn-signup">
          <i class="ph-user-plus"></i> Sign Up
        </a>
      <?php endif; ?>
    </div>
  </nav>
  
  <!-- HERO SECTION -->
  <section class="hero">
    <h1 class="hero-title">GLOBAL CRYPTO MINING<br>SIMULATION</h1>
    <p class="hero-subtitle">
      A <span class="subtitle-highlight">vibrant crypto mining simulation</span> powered by energy, location & strategy. 
      Build your mining empire with precision and style.
    </p>
    
    <!-- Conversion Banner -->
    <div class="conversion-banner">
      <div class="conversion-item">
        <div class="conversion-value">$1</div>
        <div class="conversion-label">USD Deposit</div>
      </div>
      <div class="equals">=</div>
      <div class="conversion-item">
        <div class="conversion-value">1M</div>
        <div class="conversion-label">MINEM Tokens</div>
      </div>
      <div class="equals">=</div>
      <div class="conversion-item">
        <div class="conversion-value">1M</div>
        <div class="conversion-label">m² tokens</div>
      </div>
    </div>
    
    <div class="hero-buttons">
      <?php if (isset($_SESSION['user_id'])): ?>
        <a href="dashboard.php" class="btn-primary btn-start">
          <i class="ph-play-fill"></i> Go to Dashboard
        </a>
      <?php else: ?>
        <a href="signup.php" class="btn-primary btn-start">
          <i class="ph-play-fill"></i> Start Mining Now
        </a>
      <?php endif; ?>
      <a href="#features" class="btn-primary btn-explore">
        <i class="ph-compass"></i> Explore.
      </a>
    </div>
  </section>
  
  <!-- FEATURES SECTION -->
  <section id="features" class="locations-section">
    <h2 class="section-title">Why Choose MineMechanics?</h2>
    <div class="feature-grid">
      <div class="feature-card">
        <div class="feature-icon">
          <i class="ph-coins"></i>
        </div>
        <h3 class="feature-title">Real Payouts</h3>
        <p class="feature-desc">Earn real cryptocurrency through simulated mining operations</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon">
          <i class="ph-globe"></i>
        </div>
        <h3 class="feature-title">Global Strategy</h3>
        <p class="feature-desc">Choose locations worldwide with different energy costs and conditions</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon">
          <i class="ph-lightning"></i>
        </div>
        <h3 class="feature-title">Energy Management</h3>
        <p class="feature-desc">Balance power generation and consumption for optimal profits</p>
      </div>
    </div>
  </section>
  
  <!-- LOCATIONS -->
  <section class="locations-section">
    <h2 class="section-title">Strategic Locations</h2>
    <p class="hero-subtitle" style="margin-top: -30px; margin-bottom: 40px;">Each location offers unique advantages and challenges for mining operations</p>
    <div class="locations-grid">
      <div class="location-card">
        <i class="ph-sun location-icon"></i>
        <h3 class="location-name">California</h3>
        <p class="hardware-desc" style="margin-top: 10px;">Solar-rich, high-tech hub with variable energy costs</p>
      </div>
      <div class="location-card">
        <i class="ph-snowflake location-icon"></i>
        <h3 class="location-name">Iceland</h3>
        <p class="hardware-desc" style="margin-top: 10px;">Geothermal paradise with free cooling and cheap power</p>
      </div>
      <div class="location-card">
        <i class="ph-buildings location-icon"></i>
        <h3 class="location-name">Berlin</h3>
        <p class="hardware-desc" style="margin-top: 10px;">European tech center with renewable energy focus</p>
      </div>
      <div class="location-card">
        <i class="ph-mountains location-icon"></i>
        <h3 class="location-name">Sahara</h3>
        <p class="hardware-desc" style="margin-top: 10px;">World's cheapest solar but extreme cooling needs</p>
      </div>
      <div class="location-card">
        <i class="ph-cloud-rain location-icon"></i>
        <h3 class="location-name">Mumbai</h3>
        <p class="hardware-desc" style="margin-top: 10px;">Seasonal monsoon effects, developing infrastructure</p>
      </div>
      <div class="location-card">
        <i class="ph-tree location-icon"></i>
        <h3 class="location-name">Rio</h3>
        <p class="hardware-desc" style="margin-top: 10px;">Hydroelectric dominance with coastal cooling</p>
      </div>
      <div class="location-card">
        <i class="ph-snowflake location-icon"></i>
        <h3 class="location-name">Antarctica</h3>
        <p class="hardware-desc" style="margin-top: 10px;">Free cooling but extremely high energy costs</p>
      </div>
    </div>
  </section>
  
  <!-- HARDWARE -->
  <section class="hardware-section">
    <h2 class="section-title">Mining Hardware</h2>
    <p class="hero-subtitle" style="margin-top: -30px; margin-bottom: 40px;">Scale from beginner to industrial mining operations</p>
    <div class="hardware-grid">
      <div class="hardware-card" style="border-top-color: var(--violet);">
        <i class="ph-cu hardware-icon" style="background: var(--gradient-violet); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;"></i>
        <h3 class="hardware-title">CPU Miner</h3>
        <p class="hardware-desc">$0.3 - $49 | 300k - 49M MINEM</p>
        <p class="hardware-desc" style="margin-top: 10px;">Entry level mining setup for beginners</p>
      </div>
      <div class="hardware-card" style="border-top-color: var(--orange);">
        <i class="ph-lapop hardware-icon" style="background: var(--gradient-orange); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;"></i>
        <h3 class="hardware-title">Laptop GPU</h3>
        <p class="hardware-desc">$50 - $99 | 50-99M MINEM</p>
        <p class="hardware-desc" style="margin-top: 10px;">Portable mining with graphics power</p>
      </div>
      <div class="hardware-card" style="border-top-color: var(--green);">
        <i class="ph-mmory hardware-icon" style="background: var(--gradient-green); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;"></i>
        <h3 class="hardware-title">Raspberry Pi</h3>
        <p class="hardware-desc">$100 - $299 | 100-299M MINEM</p>
        <p class="hardware-desc" style="margin-top: 10px;">Ultra efficient low-power mining</p>
      </div>
      <div class="hardware-card" style="border-top-color: var(--cyan);">
        <i class="ph-graphics-card hardware-icon" style="background: var(--gradient-cyan); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;"></i>
        <h3 class="hardware-title">STX 4090</h3>
        <p class="hardware-desc">$300 - $599 | 300-599M MINEM</p>
        <p class="hardware-desc" style="margin-top: 10px;">High-performance mining beast</p>
      </div>
      <div class="hardware-card" style="border-top-color: var(--violet);">
        <i class="ph-server hardware-icon" style="background: var(--gradient-violet); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;"></i>
        <h3 class="hardware-title">XSIC Miner</h3>
        <p class="hardware-desc">$2,000 - $4,999 | 2-4.99B MINEM</p>
        <p class="hardware-desc" style="margin-top: 10px;">Professional ASIC mining rig</p>
      </div>
      <div class="hardware-card" style="border-top-color: var(--orange);">
        <i class="ph-container hardware-icon" style="background: var(--gradient-orange); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;"></i>
        <h3 class="hardware-title">Mining Container</h3>
        <p class="hardware-desc">$20,000 - $99,999 | 20-99.99B MINEM</p>
        <p class="hardware-desc" style="margin-top: 10px;">Industrial mining data center</p>
      </div>
    </div>
  </section>
  
  <!-- ENERGY -->
  <section class="energy-section">
    <h2 class="section-title">Energy & Power Plants</h2>
    <p class="hero-subtitle" style="margin-top: -30px; margin-bottom: 40px;">Generate sustainable energy to power your mining operations</p>
    <div class="energy-grid">
      <div class="energy-card">
        <i class="ph-sun energy-icon" style="background: var(--gradient-orange); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;"></i>
        <h3 class="energy-title">Solar Energy</h3>
        <p class="hardware-desc">• SunRay Panel: 10k MINEM (50w/h)</p>
        <p class="hardware-desc">• SolarFlux Array: 45k MINEM (300w/h)</p>
        <p class="hardware-desc">• Helios Farm: 150k MINEM (500w/h)</p>
        <p class="hardware-desc">• Solar City: 500k MINEM (3Kw/h)</p>
      </div>
      <div class="energy-card">
        <i class="ph-water energy-icon" style="background: var(--gradient-cyan); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;"></i>
        <h3 class="energy-title">Hydro Energy</h3>
        <p class="hardware-desc">• StreamFlow Dam: 100k MINEM (75w/h)</p>
        <p class="hardware-desc">• RiverForce Station: 350k MINEM (200w/h)</p>
        <p class="hardware-desc">• Cascade Mega-Dam: 500k MINEM (375w/h)</p>
        <p class="hardware-desc">• Titan Reservoir: 900k MINEM (1Kw/h)</p>
      </div>
      <div class="energy-card">
        <i class="ph-wind energy-icon" style="background: var(--gradient-green); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;"></i>
        <h3 class="energy-title">Wind Energy</h3>
        <p class="hardware-desc">• BreezeSpinner: 20k MINEM (30w/h)</p>
        <p class="hardware-desc">• GaleForce Turbine: 75k MINEM (75w/h)</p>
        <p class="hardware-desc">• Tempest Generator: 200k MINEM (235w/h)</p>
        <p class="hardware-desc">• Cyclone Array: 350k MINEM (400w/h)</p>
      </div>
    </div>
  </section>
  
  <!-- DONATION BANNER -->
  <div class="donation-banner">
    <h2 style="font-size: 32px; color: white; margin-bottom: 10px;">Mining For Good</h2>
    <p style="font-size: 18px; color: rgba(255, 255, 255, 0.9);">We believe in giving back to the community</p>
    <div class="donation-percentage">20%</div>
    <p class="donation-text">of our profits go to environmental and social non-profits</p>
  </div>
  
  <!-- ECONOMY -->
  <section class="economy-section">
    <h2 class="section-title">Mining Economy</h2>
    <div class="economy-display">
      <div class="economy-value">$1 = 1,000,000 minem</div>
      <p class="economy-desc">Universal mining currency with dynamic valuation. Top up with USD, mine with strategy, redeem real crypto.</p>
    </div>
  </section>
  
  <!-- MECHANICS -->
  <section class="mechanics-section">
    <h2 class="section-title">Game Mechanics</h2>
    <div class="mechanics-grid">
      <div class="mechanic-card">
        <i class="ph-map-pin mechanic-icon" style="color: var(--violet);"></i>
        <h3 class="mechanic-title">Location-based Strategy</h3>
        <p class="hardware-desc">Strategic placement matters for energy costs and efficiency</p>
      </div>
      <div class="mechanic-card">
        <i class="ph-lightning mechanic-icon" style="color: var(--orange);"></i>
        <h3 class="mechanic-title">Energy Management</h3>
        <p class="hardware-desc">Balance power consumption with generation for optimal profits</p>
      </div>
      <div class="mechanic-card">
        <i class="ph-chart-line mechanic-icon" style="color: var(--green);"></i>
        <h3 class="mechanic-title">Progressive Growth</h3>
        <p class="hardware-desc">Scale from CPU miner to industrial mining city</p>
      </div>
      <div class="mechanic-card">
        <i class="ph-gift mechanic-icon" style="color: var(--cyan);"></i>
        <h3 class="mechanic-title">Miner Gifting</h3>
        <p class="hardware-desc">Share miners with friends and grow the community</p>
      </div>
      <div class="mechanic-card">
        <i class="ph-gift mechanic-icon" style="color: var(--cyan);"></i>
        <h3 class="mechanic-title">Free Faucet</h3>
        <p class="hardware-desc">Admin can drop free minem tokens anytime to all users.</p>
      </div>
      <div class="mechanic-card">
        <i class="ph-wallet mechanic-icon" style="color: var(--orange);"></i>
        <h3 class="mechanic-title">Real Payouts</h3>
        <p class="hardware-desc">Redeem earned m² for real cryptocurrency</p>
      </div>
    </div>
  </section>
  
  <!-- FOOTER -->
  <footer class="footer">
    <div class="footer-logo">MineMechanics</div>
    <p class="footer-tagline">Strategy. Energy. Economics.</p>
    <div class="footer-links">
      <a href="privacy.php" class="footer-link">Privacy</a>
      <a href="terms.php" class="footer-link">Terms</a>
      <a href="faq.php" class="footer-link">FAQ</a>
      <a href="contact.php" class="footer-link">Contact</a>
      <a href="https://t.me/mine_mechanics" class="footer-link">Telegram Group</a>
    </div>
    <p class="copyright">© <?php echo date('Y'); ?> MineMechanics. All rights reserved.</p>
  </footer>
</div>

<script>
// Add hover effects and animations
document.querySelectorAll('.location-card, .hardware-card, .energy-card, .mechanic-card, .feature-card').forEach(card => {
  card.addEventListener('mouseenter', function() {
    this.style.transform = 'translateY(-8px)';
  });
  
  card.addEventListener('mouseleave', function() {
    this.style.transform = 'translateY(0)';
  });
});

// Button click animations
document.querySelectorAll('button, .btn-primary, .btn-nav').forEach(button => {
  button.addEventListener('click', function() {
    this.style.transform = 'scale(0.98)';
    setTimeout(() => {
      this.style.transform = '';
    }, 150);
  });
});

// Dynamic gradient on hero title
const heroTitle = document.querySelector('.hero-title');
const gradients = [
  'linear-gradient(90deg, #ffffff 0%, rgba(255, 255, 255, 0.8) 100%)',
  'linear-gradient(90deg, #8B5CF6 0%, #F97316 100%)',
  'linear-gradient(90deg, #10B981 0%, #06B6D4 100%)'
];

let currentGradient = 0;
setInterval(() => {
  currentGradient = (currentGradient + 1) % gradients.length;
  heroTitle.style.background = gradients[currentGradient];
  heroTitle.style.webkitBackgroundClip = 'text';
  heroTitle.style.webkitTextFillColor = 'transparent';
  heroTitle.style.backgroundClip = 'text';
}, 5000);

// Smooth scrolling for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
  anchor.addEventListener('click', function(e) {
    e.preventDefault();
    const targetId = this.getAttribute('href');
    if (targetId === '#') return;
    
    const targetElement = document.querySelector(targetId);
    if (targetElement) {
      window.scrollTo({
        top: targetElement.offsetTop - 80,
        behavior: 'smooth'
      });
    }
  });
});

// Animate elements on scroll
const observerOptions = {
  threshold: 0.1,
  rootMargin: '0px 0px -50px 0px'
};

const observer = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      entry.target.style.opacity = '1';
      entry.target.style.transform = 'translateY(0)';
    }
  });
}, observerOptions);

// Observe all cards for animation
document.querySelectorAll('.location-card, .hardware-card, .energy-card, .mechanic-card, .feature-card').forEach(el => {
  el.style.opacity = '0';
  el.style.transform = 'translateY(20px)';
  el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
  observer.observe(el);
});

// Check if user is logged in and show appropriate buttons
document.addEventListener('DOMContentLoaded', function() {
  const startButton = document.querySelector('.btn-start');
  if (startButton) {
    // This is handled by PHP now, but keeping for reference
  }
});
</script>
</body>
</html>