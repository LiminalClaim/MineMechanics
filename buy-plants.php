<?php
// buy-plants.php - Purchase Solar Energy Plants
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

// Solar Energy Plants Data
$solar_plants = [
    [
        'name' => 'Small Panel (1×1)',
        'cost_minem' => 10000,
        'output_wh' => 50,
        'img' => 'solar-small.png',
        'description' => 'Perfect for starting your solar energy journey'
    ],
    [
        'name' => 'Mid Panel (1×5)',
        'cost_minem' => 45000,
        'output_wh' => 300,
        'img' => 'solar-mid.png',
        'description' => 'Great balance of cost and power output'
    ],
    [
        'name' => 'Large Panel (1×20)',
        'cost_minem' => 150000,
        'output_wh' => 500,
        'img' => 'solar-large.png',
        'description' => 'High efficiency solar panels'
    ],
    [
        'name' => 'Solar City',
        'cost_minem' => 500000,
        'output_wh' => 3000,
        'img' => 'solar-city.png',
        'description' => 'Massive solar power generation'
    ]
];

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

// Get user's existing solar plants
$userPlants = [];
$plantsResponse = supabaseRequest('/rest/v1/user_energy_plants?user_id=eq.' . $user_id . '&select=*,energy_plant_types(category,name,cost_minem,output_wh)');
if ($plantsResponse['status'] === 200) {
    $userPlants = $plantsResponse['data'];
}

// Calculate totals
$totalEnergyGenerated = 0;
$totalMinemInvested = 0;
$totalPlants = 0;
foreach ($userPlants as $plant) {
    if (isset($plant['energy_plant_types'])) {
        $totalEnergyGenerated += (floatval($plant['energy_plant_types']['output_wh']) * intval($plant['quantity']));
        $totalMinemInvested += (floatval($plant['energy_plant_types']['cost_minem']) * intval($plant['quantity']));
        $totalPlants += intval($plant['quantity']);
    }
}

// Handle plant purchase
$purchaseSuccess = false;
$purchaseMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purchase_plant'])) {
    $plantIndex = intval($_POST['plant_index'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 1);
    
    if ($quantity < 1 || $quantity > 1000) {
        $purchaseMessage = 'Quantity must be between 1 and 1000.';
    } elseif (!isset($solar_plants[$plantIndex])) {
        $purchaseMessage = 'Invalid plant selection.';
    } else {
        $plant = $solar_plants[$plantIndex];
        $totalCost = $plant['cost_minem'] * $quantity;
        
        if ($totalCost > ($balance['minem'] ?? 0)) {
            $purchaseMessage = 'Insufficient MINEM balance. Available: ' . number_format($balance['minem'] ?? 0, 2) . ' MINEM';
        } else {
            // Step 1: Check if plant type already exists
            $plantTypeId = null;
            $typeCheckResponse = supabaseRequest('/rest/v1/energy_plant_types?category=eq.Solar&name=eq.' . urlencode($plant['name']));
            
            if ($typeCheckResponse['status'] === 200 && !empty($typeCheckResponse['data'])) {
                $plantTypeId = $typeCheckResponse['data'][0]['id'];
            } else {
                // Create new plant type
                $plantTypeData = [
                    'category' => 'Solar',
                    'name' => $plant['name'],
                    'cost_minem' => $plant['cost_minem'],
                    'output_wh' => $plant['output_wh']
                ];
                
                $typeResponse = supabaseRequest('/rest/v1/energy_plant_types', 'POST', $plantTypeData, true);
                
                if ($typeResponse['status'] === 201 && !empty($typeResponse['data'])) {
                    $plantTypeId = $typeResponse['data'][0]['id'];
                } else {
                    $purchaseMessage = 'Failed to create plant type. Please try again.';
                }
            }
            
            if ($plantTypeId) {
                // Step 2: Check if user already has this plant type
                $existingPlantResponse = supabaseRequest('/rest/v1/user_energy_plants?user_id=eq.' . $user_id . '&plant_type_id=eq.' . $plantTypeId);
                
                if ($existingPlantResponse['status'] === 200 && !empty($existingPlantResponse['data'])) {
                    // Update existing plant quantity
                    $existingPlant = $existingPlantResponse['data'][0];
                    $newQuantity = intval($existingPlant['quantity']) + $quantity;
                    
                    if ($newQuantity > 1000) {
                        $purchaseMessage = 'Maximum quantity per plant type is 1000.';
                    } else {
                        $updateData = ['quantity' => $newQuantity];
                        $updateResponse = supabaseRequest('/rest/v1/user_energy_plants?id=eq.' . $existingPlant['id'], 'PATCH', $updateData, true);
                        
                        if ($updateResponse['status'] === 204 || $updateResponse['status'] === 200) {
                            // Update balance
                            $newMinemBalance = floatval($balance['minem'] ?? 0) - $totalCost;
                            $balanceUpdate = [
                                'minem' => $newMinemBalance,
                                'updated_at' => date('Y-m-d H:i:s')
                            ];
                            
                            $balanceResponse = supabaseRequest('/rest/v1/balances?user_id=eq.' . $user_id, 'PATCH', $balanceUpdate, true);
                            
                            if ($balanceResponse['status'] === 204 || $balanceResponse['status'] === 200) {
                                $purchaseSuccess = true;
                                $purchaseMessage = 'Successfully added ' . $quantity . ' ' . $plant['name'] . '(s)! Total cost: ' . number_format($totalCost) . ' MINEM';
                                $balance['minem'] = $newMinemBalance;
                                
                                // Refresh user plants
                                $plantsResponse = supabaseRequest('/rest/v1/user_energy_plants?user_id=eq.' . $user_id . '&select=*,energy_plant_types(category,name,cost_minem,output_wh)');
                                if ($plantsResponse['status'] === 200) {
                                    $userPlants = $plantsResponse['data'];
                                    
                                    // Recalculate totals
                                    $totalEnergyGenerated = 0;
                                    $totalMinemInvested = 0;
                                    $totalPlants = 0;
                                    foreach ($userPlants as $plant) {
                                        if (isset($plant['energy_plant_types'])) {
                                            $totalEnergyGenerated += (floatval($plant['energy_plant_types']['output_wh']) * intval($plant['quantity']));
                                            $totalMinemInvested += (floatval($plant['energy_plant_types']['cost_minem']) * intval($plant['quantity']));
                                            $totalPlants += intval($plant['quantity']);
                                        }
                                    }
                                }
                            } else {
                                // Rollback: Reset quantity
                                $rollbackData = ['quantity' => $existingPlant['quantity']];
                                supabaseRequest('/rest/v1/user_energy_plants?id=eq.' . $existingPlant['id'], 'PATCH', $rollbackData, true);
                                $purchaseMessage = 'Failed to update balance. Please contact support.';
                            }
                        }
                    }
                } else {
                    // Create new user plant
                    $plantData = [
                        'user_id' => $user_id,
                        'plant_type_id' => $plantTypeId,
                        'quantity' => $quantity
                    ];
                    
                    $plantResponse = supabaseRequest('/rest/v1/user_energy_plants', 'POST', $plantData, true);
                    
                    if ($plantResponse['status'] === 201 || $plantResponse['status'] === 200) {
                        // Update balance
                        $newMinemBalance = floatval($balance['minem'] ?? 0) - $totalCost;
                        $balanceUpdate = [
                            'minem' => $newMinemBalance,
                            'updated_at' => date('Y-m-d H:i:s')
                        ];
                        
                        $balanceResponse = supabaseRequest('/rest/v1/balances?user_id=eq.' . $user_id, 'PATCH', $balanceUpdate, true);
                        
                        if ($balanceResponse['status'] === 204 || $balanceResponse['status'] === 200) {
                            $purchaseSuccess = true;
                            $purchaseMessage = 'Successfully purchased ' . $quantity . ' ' . $plant['name'] . '(s)! Total cost: ' . number_format($totalCost) . ' MINEM';
                            $balance['minem'] = $newMinemBalance;
                            
                            // Refresh user plants
                            $plantsResponse = supabaseRequest('/rest/v1/user_energy_plants?user_id=eq.' . $user_id . '&select=*,energy_plant_types(category,name,cost_minem,output_wh)');
                            if ($plantsResponse['status'] === 200) {
                                $userPlants = $plantsResponse['data'];
                                
                                // Recalculate totals
                                $totalEnergyGenerated = 0;
                                $totalMinemInvested = 0;
                                $totalPlants = 0;
                                foreach ($userPlants as $plant) {
                                    if (isset($plant['energy_plant_types'])) {
                                        $totalEnergyGenerated += (floatval($plant['energy_plant_types']['output_wh']) * intval($plant['quantity']));
                                        $totalMinemInvested += (floatval($plant['energy_plant_types']['cost_minem']) * intval($plant['quantity']));
                                        $totalPlants += intval($plant['quantity']);
                                    }
                                }
                            }
                        } else {
                            // Rollback: Delete the plant
                            if (!empty($plantResponse['data'][0]['id'])) {
                                supabaseRequest('/rest/v1/user_energy_plants?id=eq.' . $plantResponse['data'][0]['id'], 'DELETE', null, true);
                            }
                            $purchaseMessage = 'Failed to update balance. Please contact support.';
                        }
                    } else {
                        $purchaseMessage = 'Failed to purchase plant. Please try again.';
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Buy Solar Plants - MineMechanics</title>
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
  --gradient-yellow: linear-gradient(135deg, #F59E0B 0%, #FBBF24 100%);
  --gradient-solar: linear-gradient(135deg, #F59E0B 0%, #FDE047 100%);
  --violet: #8B5CF6;
  --orange: #F97316;
  --green: #10B981;
  --blue: #3B82F6;
  --gold: #FACC15;
  --yellow: #F59E0B;
  --solar: #F59E0B;
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
  background: var(--gradient-solar);
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

.balance-card.energy::before {
  background: var(--gradient-green);
}

.balance-card.plants::before {
  background: var(--gradient-solar);
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

.energy-balance {
  background: var(--gradient-green);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.plants-balance {
  background: var(--gradient-solar);
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
  background: var(--gradient-solar);
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

.section-description {
  color: var(--text-secondary);
  font-size: 16px;
  max-width: 800px;
  margin-bottom: 30px;
  line-height: 1.6;
}

/* Solar Plants Grid */
.plants-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 30px;
  margin-bottom: 40px;
}

.plant-item {
  background: rgba(245, 158, 11, 0.05);
  border: 1px solid rgba(245, 158, 11, 0.1);
  border-radius: 20px;
  padding: 30px;
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
}

.plant-item:hover {
  transform: translateY(-5px);
  border-color: rgba(245, 158, 11, 0.3);
  box-shadow: 0 15px 40px rgba(245, 158, 11, 0.15);
}

.plant-item::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 4px;
  background: var(--gradient-solar);
}

.plant-image {
  width: 100%;
  height: 180px;
  background: rgba(255, 255, 255, 0.05);
  border-radius: 12px;
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

.plant-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.plant-name {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 22px;
  font-weight: 700;
  margin-bottom: 10px;
  color: var(--text-primary);
}

.plant-description {
  color: var(--text-muted);
  font-size: 14px;
  margin-bottom: 20px;
  line-height: 1.5;
}

.plant-specs {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 15px;
  margin-bottom: 25px;
  padding: 20px;
  background: rgba(0, 0, 0, 0.3);
  border-radius: 12px;
}

.plant-spec {
  display: flex;
  flex-direction: column;
}

.spec-label {
  color: var(--text-muted);
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 1px;
  margin-bottom: 5px;
}

.spec-value {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 16px;
  font-weight: 700;
}

.spec-value.cost {
  color: var(--gold);
}

.spec-value.power {
  color: var(--green);
}

/* Quantity Input */
.quantity-input {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 20px;
  background: rgba(0, 0, 0, 0.3);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  padding: 5px;
}

.quantity-btn {
  width: 40px;
  height: 40px;
  background: rgba(245, 158, 11, 0.2);
  border: 1px solid rgba(245, 158, 11, 0.3);
  border-radius: 8px;
  color: var(--solar);
  font-size: 20px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s ease;
}

.quantity-btn:hover {
  background: rgba(245, 158, 11, 0.3);
  transform: scale(1.05);
}

.quantity-field {
  flex: 1;
  padding: 10px 15px;
  background: transparent;
  border: none;
  color: var(--text-primary);
  text-align: center;
  font-size: 18px;
  font-weight: 600;
}

/* Plant Purchase Button */
.plant-purchase-btn {
  width: 100%;
  padding: 18px;
  background: var(--gradient-solar);
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

.plant-purchase-btn:hover:not(:disabled) {
  transform: translateY(-3px);
  box-shadow: 0 10px 30px rgba(245, 158, 11, 0.4);
}

.plant-purchase-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Your Plants Section */
.plants-section {
  background: var(--bg-card);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 20px;
  padding: 35px;
}

.plants-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 25px;
}

.plants-title {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 24px;
  font-weight: 600;
  color: var(--text-primary);
}

/* Plants List */
.plants-list {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
  gap: 25px;
}

.plant-card {
  background: rgba(245, 158, 11, 0.05);
  border: 1px solid rgba(245, 158, 11, 0.1);
  border-radius: 20px;
  padding: 25px;
  position: relative;
  overflow: hidden;
  transition: all 0.3s ease;
}

.plant-card:hover {
  transform: translateY(-5px);
  border-color: rgba(245, 158, 11, 0.3);
  box-shadow: 0 10px 30px rgba(245, 158, 11, 0.15);
}

.plant-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 4px;
  background: var(--gradient-solar);
}

.plant-card-name {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 20px;
  font-weight: 600;
  margin-bottom: 15px;
  color: var(--text-primary);
}

.plant-card-specs {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 15px;
  margin-bottom: 20px;
}

.plant-card-spec {
  display: flex;
  flex-direction: column;
}

.plant-card-label {
  color: var(--text-muted);
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 1px;
  margin-bottom: 5px;
}

.plant-card-value {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 15px;
  font-weight: 600;
}

.plant-card-quantity {
  background: rgba(245, 158, 11, 0.1);
  border: 1px solid rgba(245, 158, 11, 0.2);
  border-radius: 12px;
  padding: 15px;
  text-align: center;
  margin-top: 15px;
}

.quantity-label {
  color: var(--solar);
  font-size: 13px;
  margin-bottom: 8px;
}

.quantity-value {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 18px;
  font-weight: 700;
  color: var(--text-primary);
}

.total-power {
  background: rgba(16, 185, 129, 0.1);
  border: 1px solid rgba(16, 185, 129, 0.2);
  border-radius: 12px;
  padding: 15px;
  text-align: center;
  margin-top: 15px;
}

.total-power-label {
  color: var(--green);
  font-size: 13px;
  margin-bottom: 8px;
}

.total-power-value {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 18px;
  font-weight: 700;
  color: var(--text-primary);
}

/* Empty States */
.empty-state {
  grid-column: 1 / -1;
  text-align: center;
  padding: 60px 20px;
  color: var(--text-muted);
}

.empty-state i {
  font-size: 64px;
  margin-bottom: 20px;
  opacity: 0.5;
  background: var(--gradient-solar);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.empty-state h3 {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 28px;
  margin-bottom: 10px;
  color: var(--text-secondary);
}

.empty-state p {
  font-size: 16px;
  max-width: 400px;
  margin: 0 auto;
  line-height: 1.6;
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
  
  .plants-grid {
    grid-template-columns: 1fr;
  }
  
  .purchase-section {
    padding: 25px;
  }
  
  .plants-list {
    grid-template-columns: 1fr;
  }
  
  .plant-specs {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 480px) {
  .balance-display {
    grid-template-columns: 1fr;
  }
  
  .plants-grid {
    gap: 20px;
  }
  
  .plant-item {
    padding: 20px;
  }
  
  .plant-image {
    height: 150px;
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
        <a href="buy-plants.php" class="nav-item active">
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
            <h1>Solar Energy Plants</h1>
            <p class="text-muted">Purchase solar plants to generate clean energy for your miners</p>
        </div>
    </div>
    
    <div class="buy-container">
        <!-- Alert Messages -->
        <?php if (!empty($purchaseMessage)): ?>
        <div class="alert <?php echo $purchaseSuccess ? 'alert-success' : 'alert-error'; ?>">
            <i class="ph <?php echo $purchaseSuccess ? 'ph-check-circle-fill' : 'ph-warning-circle-fill'; ?>"></i>
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
            
            <div class="balance-card energy">
                <div class="balance-label">
                    <i class="ph ph-lightning"></i>
                    <span>Energy Generated</span>
                </div>
                <div class="balance-value energy-balance">
                    <?php echo number_format($totalEnergyGenerated, 2); ?> W/h
                </div>
            </div>
            
            <div class="balance-card plants">
                <div class="balance-label">
                    <i class="ph ph-sun"></i>
                    <span>Total Solar Plants</span>
                </div>
                <div class="balance-value plants-balance">
                    <?php echo number_format($totalPlants); ?>
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
        
        <!-- Solar Plants Purchase Section -->
        <div class="purchase-section">
            <div class="section-header">
                <i class="ph ph-sun section-icon"></i>
                <h2 class="section-title">Purchase Solar Energy Plants</h2>
            </div>
            
            <p class="section-description">
                Invest in solar energy plants to generate clean, renewable power for your mining operations. 
                Each plant produces energy continuously, helping you run more miners and earn more rewards.
            </p>
            
            <div class="plants-grid">
                <?php foreach ($solar_plants as $index => $plant): ?>
                <div class="plant-item">
                    <div class="plant-image">
                        <!-- Add solar plant image here -->
                        <img src="small.jpg<?php echo $plant['img']; ?>" alt="<?php echo $plant['name']; ?>" 
                             onerror="this.src='small.jpg<?php echo urlencode($plant['name']); ?>'">
                    </div>
                    
                    <h3 class="plant-name"><?php echo $plant['name']; ?></h3>
                    <p class="plant-description"><?php echo $plant['description']; ?></p>
                    
                    <div class="plant-specs">
                        <div class="plant-spec">
                            <span class="spec-label">Cost per Unit</span>
                            <span class="spec-value cost"><?php echo number_format($plant['cost_minem']); ?> MINEM</span>
                        </div>
                        <div class="plant-spec">
                            <span class="spec-label">Power Output</span>
                            <span class="spec-value power"><?php echo number_format($plant['output_wh']); ?> W/h</span>
                        </div>
                    </div>
                    
                    <form method="POST" onsubmit="return validatePlantPurchase(<?php echo $plant['cost_minem']; ?>, <?php echo $index; ?>)">
                        <input type="hidden" name="plant_index" value="<?php echo $index; ?>">
                        
                        <div class="quantity-input">
                            <button type="button" class="quantity-btn" onclick="decreaseQuantity(this)">-</button>
                            <input type="number" name="quantity" class="quantity-field" value="1" min="1" max="1000" required>
                            <button type="button" class="quantity-btn" onclick="increaseQuantity(this)">+</button>
                        </div>
                        
                        <button type="submit" name="purchase_plant" class="plant-purchase-btn"
                                <?php echo ($balance['minem'] ?? 0) < $plant['cost_minem'] ? 'disabled' : ''; ?>>
                            <i class="ph ph-shopping-cart"></i>
                            Purchase Solar Plant
                        </button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Your Solar Plants Section -->
        <div class="plants-section">
            <div class="plants-header">
                <h2 class="plants-title">
                    <i class="ph ph-sun"></i>
                    Your Solar Plants (<?php echo number_format($totalPlants); ?>)
                </h2>
            </div>
            
            <?php if (empty($userPlants)): ?>
            <div class="empty-state">
                <i class="ph ph-sun"></i>
                <h3>No Solar Plants Yet</h3>
                <p>Purchase your first solar plant above to start generating clean, renewable energy for your mining operations!</p>
                <p style="margin-top: 15px; font-size: 14px; color: var(--solar);">
                    <i class="ph ph-info"></i>
                    Solar plants generate energy continuously, allowing you to power more miners and earn more rewards.
                </p>
            </div>
            <?php else: ?>
            <div class="plants-list">
                <?php foreach ($userPlants as $plant): ?>
                <?php if (isset($plant['energy_plant_types'])): 
                    $plantType = $plant['energy_plant_types'];
                    $totalPower = floatval($plantType['output_wh']) * intval($plant['quantity']);
                ?>
                <div class="plant-card">
                    <h3 class="plant-card-name"><?php echo htmlspecialchars($plantType['name']); ?></h3>
                    
                    <div class="plant-card-specs">
                        <div class="plant-card-spec">
                            <span class="plant-card-label">Category</span>
                            <span class="plant-card-value"><?php echo htmlspecialchars($plantType['category']); ?></span>
                        </div>
                        <div class="plant-card-spec">
                            <span class="plant-card-label">Cost per Unit</span>
                            <span class="plant-card-value"><?php echo number_format($plantType['cost_minem']); ?> MINEM</span>
                        </div>
                        <div class="plant-card-spec">
                            <span class="plant-card-label">Power per Unit</span>
                            <span class="plant-card-value"><?php echo number_format($plantType['output_wh']); ?> W/h</span>
                        </div>
                        <div class="plant-card-spec">
                            <span class="plant-card-label">Purchased</span>
                            <span class="plant-card-value"><?php echo date('M d, Y', strtotime($plant['created_at'])); ?></span>
                        </div>
                    </div>
                    
                    <div class="plant-card-quantity">
                        <div class="quantity-label">Quantity Owned</div>
                        <div class="quantity-value"><?php echo $plant['quantity']; ?> units</div>
                    </div>
                    
                    <div class="total-power">
                        <div class="total-power-label">Total Power Generation</div>
                        <div class="total-power-value"><?php echo number_format($totalPower, 2); ?> W/h</div>
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
const USER_BALANCE = <?php echo $balance['minem'] ?? 0; ?>;
const SOLAR_PLANTS = <?php echo json_encode($solar_plants); ?>;

// Quantity controls for plants
function increaseQuantity(button) {
    const input = button.parentElement.querySelector('.quantity-field');
    if (parseInt(input.value) < 1000) {
        input.value = parseInt(input.value) + 1;
    }
}

function decreaseQuantity(button) {
    const input = button.parentElement.querySelector('.quantity-field');
    if (parseInt(input.value) > 1) {
        input.value = parseInt(input.value) - 1;
    }
}

// Validate plant purchase
function validatePlantPurchase(plantCost, plantIndex) {
    const form = event.target;
    const quantityInput = form.querySelector('input[name="quantity"]');
    const quantity = parseInt(quantityInput.value);
    const totalCost = plantCost * quantity;
    const balance = USER_BALANCE;
    
    if (quantity < 1 || quantity > 1000) {
        alert('Quantity must be between 1 and 1000.');
        return false;
    }
    
    if (totalCost > balance) {
        alert('Insufficient MINEM balance. Available: ' + balance.toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }) + ' MINEM\n\nYou need ' + totalCost.toLocaleString() + ' MINEM for this purchase.');
        return false;
    }
    
    const plant = SOLAR_PLANTS[plantIndex];
    const totalPower = plant.output_wh * quantity;
    
    return confirm(
        'Are you sure you want to purchase:\n\n' +
        '• ' + quantity + ' x ' + plant.name + '\n' +
        '• Total Cost: ' + totalCost.toLocaleString() + ' MINEM\n' +
        '• Total Power Output: ' + totalPower.toLocaleString() + ' W/h\n\n' +
        'This will generate ' + totalPower.toLocaleString() + ' watts per hour for your mining operations.'
    );
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Add input validation for quantity fields
    document.querySelectorAll('.quantity-field').forEach(input => {
        input.addEventListener('change', function() {
            let value = parseInt(this.value);
            if (isNaN(value) || value < 1) {
                this.value = 1;
            } else if (value > 1000) {
                this.value = 1000;
            }
        });
    });
});
</script>
</body>
</html>