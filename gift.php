<?php
// gift.php - Gift Miners to Other Users
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

// Get user's miners (exclude already gifted miners)
$userMiners = [];
$minersResponse = supabaseRequest('/rest/v1/user_miners?user_id=eq.' . $user_id . '&select=id,user_id,miner_type_id,usd_value,hashpower_ths,energy_usage_wh,created_at,miner_types(name)');
if ($minersResponse['status'] === 200) {
    $userMiners = $minersResponse['data'];
    
    // Check if miners have already been gifted
    foreach ($userMiners as $key => $miner) {
        $giftCheck = supabaseRequest('/rest/v1/miner_gifts?miner_id=eq.' . $miner['id']);
        if ($giftCheck['status'] === 200 && !empty($giftCheck['data'])) {
            // This miner has already been gifted, remove from list
            unset($userMiners[$key]);
        }
    }
    $userMiners = array_values($userMiners); // Reset array keys
}

// Get miner types for display
$minerTypes = [];
$typesResponse = supabaseRequest('/rest/v1/miner_types');
if ($typesResponse['status'] === 200) {
    $minerTypes = $typesResponse['data'];
}

// Handle search for users
$searchResults = [];
$searchQuery = '';
$selectedUser = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_user'])) {
    $searchQuery = trim($_POST['search_query'] ?? '');
    
    if (!empty($searchQuery)) {
        $encodedQuery = urlencode($searchQuery);
        
        // Search by email or username
        $searchUrl = '/rest/v1/profiles?or=(email.ilike.%25' . $encodedQuery . '%25,username.ilike.%25' . $encodedQuery . '%25)&limit=10';
        
        $searchResponse = supabaseRequest($searchUrl);
        
        if ($searchResponse['status'] === 200 && isset($searchResponse['data'])) {
            $searchResults = $searchResponse['data'];
            
            // Remove current user from results
            $searchResults = array_filter($searchResults, function($user) use ($user_id) {
                return $user['id'] !== $user_id;
            });
            
            // Reset array keys
            $searchResults = array_values($searchResults);
        }
    }
}

// Handle user selection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['select_user'])) {
    $selectedUserId = $_POST['selected_user_id'] ?? '';
    
    if (!empty($selectedUserId)) {
        // Get selected user details
        $userResponse = supabaseRequest('/rest/v1/profiles?id=eq.' . $selectedUserId);
        if ($userResponse['status'] === 200 && !empty($userResponse['data'])) {
            $selectedUser = $userResponse['data'][0];
        }
    }
}

// Handle gift submission
$giftSuccess = false;
$giftMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gift_miner'])) {
    $receiverId = $_POST['receiver_id'] ?? '';
    $minerId = $_POST['miner_id'] ?? '';
    
    if (empty($receiverId)) {
        $giftMessage = 'Please select a recipient first.';
    } elseif (empty($minerId)) {
        $giftMessage = 'Please select a miner to gift.';
    } else {
        try {
            // Check if miner exists and belongs to current user
            $minerCheckResponse = supabaseRequest('/rest/v1/user_miners?id=eq.' . $minerId . '&user_id=eq.' . $user_id);
            
            if ($minerCheckResponse['status'] === 200 && !empty($minerCheckResponse['data'])) {
                $minerToGift = $minerCheckResponse['data'][0];
                
                // Check if receiver exists and is not the same as sender
                if ($receiverId === $user_id) {
                    $giftMessage = 'You cannot gift a miner to yourself.';
                } else {
                    $receiverCheckResponse = supabaseRequest('/rest/v1/profiles?id=eq.' . $receiverId);
                    
                    if ($receiverCheckResponse['status'] === 200 && !empty($receiverCheckResponse['data'])) {
                        // Check if miner has already been gifted (UNIQUE constraint)
                        $giftCheckResponse = supabaseRequest('/rest/v1/miner_gifts?miner_id=eq.' . $minerId);
                        
                        if ($giftCheckResponse['status'] === 200 && !empty($giftCheckResponse['data'])) {
                            $giftMessage = 'This miner has already been gifted before and cannot be gifted again.';
                        } else {
                            // Step 1: Create a copy of the miner for the receiver
                            $newMinerData = [
                                'user_id' => $receiverId,
                                'miner_type_id' => $minerToGift['miner_type_id'],
                                'usd_value' => $minerToGift['usd_value'],
                                'hashpower_ths' => $minerToGift['hashpower_ths'],
                                'energy_usage_wh' => $minerToGift['energy_usage_wh']
                            ];
                            
                            $createMinerResponse = supabaseRequest('/rest/v1/user_miners', 'POST', $newMinerData, true);
                            
                            if ($createMinerResponse['status'] === 201 && !empty($createMinerResponse['data'])) {
                                $newMinerId = $createMinerResponse['data'][0]['id'];
                                
                                // Step 2: Create gift record with the NEW miner ID
                                $giftData = [
                                    'sender_id' => $user_id,
                                    'receiver_id' => $receiverId,
                                    'miner_id' => $newMinerId
                                ];
                                
                                $giftResponse = supabaseRequest('/rest/v1/miner_gifts', 'POST', $giftData, true);
                                
                                if ($giftResponse['status'] === 201 || $giftResponse['status'] === 200) {
                                    // Step 3: Delete the original miner from sender
                                    $deleteResponse = supabaseRequest('/rest/v1/user_miners?id=eq.' . $minerId, 'DELETE', null, true);
                                    
                                    if ($deleteResponse['status'] === 204 || $deleteResponse['status'] === 200) {
                                        $giftSuccess = true;
                                        $giftMessage = 'Miner gifted successfully!';
                                        
                                        // Refresh user's miners list
                                        $minersResponse = supabaseRequest('/rest/v1/user_miners?user_id=eq.' . $user_id . '&select=id,user_id,miner_type_id,usd_value,hashpower_ths,energy_usage_wh,created_at,miner_types(name)');
                                        if ($minersResponse['status'] === 200) {
                                            $userMiners = $minersResponse['data'];
                                            
                                            // Re-check for gifted miners
                                            foreach ($userMiners as $key => $miner) {
                                                $giftCheck = supabaseRequest('/rest/v1/miner_gifts?miner_id=eq.' . $miner['id']);
                                                if ($giftCheck['status'] === 200 && !empty($giftCheck['data'])) {
                                                    unset($userMiners[$key]);
                                                }
                                            }
                                            $userMiners = array_values($userMiners);
                                        }
                                        
                                        // Reset selected user
                                        $selectedUser = null;
                                    } else {
                                        // Rollback: Delete the new miner
                                        supabaseRequest('/rest/v1/user_miners?id=eq.' . $newMinerId, 'DELETE', null, true);
                                        $giftMessage = 'Failed to remove miner from your inventory.';
                                    }
                                } else {
                                    // Rollback: Delete the new miner
                                    supabaseRequest('/rest/v1/user_miners?id=eq.' . $newMinerId, 'DELETE', null, true);
                                    $giftMessage = 'Failed to create gift record.';
                                }
                            } else {
                                $giftMessage = 'Failed to create miner copy for recipient.';
                            }
                        }
                    } else {
                        $giftMessage = 'Recipient not found.';
                    }
                }
            } else {
                $giftMessage = 'Miner not found or does not belong to you.';
            }
            
        } catch (Exception $e) {
            $giftMessage = 'An error occurred: ' . $e->getMessage();
        }
    }
}

// Get gift history (sent gifts)
$giftHistory = [];
$historyResponse = supabaseRequest('/rest/v1/miner_gifts?sender_id=eq.' . $user_id . '&select=*,profiles!miner_gifts_receiver_id_fkey(username),user_miners(*,miner_types(*))&order=gifted_at.desc&limit=10');
if ($historyResponse['status'] === 200) {
    $giftHistory = $historyResponse['data'];
}

// Get received gifts
$receivedGifts = [];
$receivedResponse = supabaseRequest('/rest/v1/miner_gifts?receiver_id=eq.' . $user_id . '&select=*,profiles!miner_gifts_sender_id_fkey(username),user_miners(*,miner_types(*))&order=gifted_at.desc&limit=10');
if ($receivedResponse['status'] === 200) {
    $receivedGifts = $receivedResponse['data'];
}
?>

The key changes I made:

1. **Filter out already gifted miners** when fetching user's miners list
2. **Create a copy of the miner** for the receiver (instead of transferring ownership)
3. **Delete the original miner** from the sender
4. **Check UNIQUE constraint** before gifting to ensure a miner isn't gifted twice
5. **Proper rollback** if any step fails

This approach works around the UNIQUE constraint in your schema while properly transferring ownership from sender to receiver.
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Gift Miners - MineMechanics</title>
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

/* Gift Container */
.gift-container {
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

/* Gift Process Steps */
.gift-steps {
  display: flex;
  justify-content: space-between;
  margin-bottom: 40px;
  position: relative;
}

.gift-steps::before {
  content: '';
  position: absolute;
  top: 24px;
  left: 25%;
  right: 25%;
  height: 2px;
  background: rgba(255, 255, 255, 0.1);
  z-index: 1;
}

.step {
  text-align: center;
  position: relative;
  z-index: 2;
  flex: 1;
}

.step-icon {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.05);
  border: 2px solid rgba(255, 255, 255, 0.1);
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 15px;
  font-size: 20px;
  color: var(--text-muted);
  transition: all 0.3s ease;
}

.step.active .step-icon {
  background: var(--gradient-violet);
  border-color: var(--violet);
  color: white;
}

.step.completed .step-icon {
  background: var(--gradient-green);
  border-color: var(--green);
  color: white;
}

.step-label {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 14px;
  font-weight: 600;
  color: var(--text-muted);
}

.step.active .step-label {
  color: var(--text-primary);
}

.step.completed .step-label {
  color: var(--green);
}

/* Search Section */
.search-section {
  background: var(--bg-card);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 20px;
  padding: 30px;
  margin-bottom: 30px;
}

.section-header {
  display: flex;
  align-items: center;
  gap: 15px;
  margin-bottom: 25px;
}

.section-icon {
  font-size: 28px;
  background: var(--gradient-blue);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.section-title {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 24px;
  font-weight: 600;
  color: var(--text-primary);
}

.search-form {
  display: flex;
  gap: 15px;
  margin-bottom: 25px;
}

.search-input {
  flex: 1;
  padding: 15px 20px;
  background: rgba(0, 0, 0, 0.3);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  color: var(--text-primary);
  font-size: 16px;
}

.search-input:focus {
  outline: none;
  border-color: var(--violet);
}

.search-btn {
  padding: 15px 30px;
  background: var(--gradient-blue);
  color: white;
  border: none;
  border-radius: 12px;
  font-family: 'Space Grotesk', sans-serif;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
}

.search-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 20px rgba(59, 130, 246, 0.3);
}

/* Search Results */
.search-results {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
  gap: 15px;
}

.user-card {
  background: rgba(0, 0, 0, 0.2);
  border: 1px solid rgba(255, 255, 255, 0.05);
  border-radius: 12px;
  padding: 20px;
  cursor: pointer;
  transition: all 0.3s ease;
}

.user-card:hover {
  background: rgba(255, 255, 255, 0.05);
  border-color: var(--violet);
}

.user-card.selected {
  background: rgba(139, 92, 246, 0.1);
  border-color: var(--violet);
}

.user-avatar {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  background: var(--gradient-violet);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  color: white;
  margin-bottom: 15px;
}

.user-name {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 18px;
  font-weight: 600;
  margin-bottom: 5px;
}

.user-email {
  color: var(--text-muted);
  font-size: 14px;
  margin-bottom: 15px;
}

.select-btn {
  width: 100%;
  padding: 10px;
  background: var(--gradient-violet);
  color: white;
  border: none;
  border-radius: 8px;
  font-family: 'Space Grotesk', sans-serif;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
}

.select-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 20px rgba(139, 92, 246, 0.3);
}

/* Selected User Display */
.selected-user-display {
  background: rgba(139, 92, 246, 0.05);
  border: 2px solid var(--violet);
  border-radius: 16px;
  padding: 25px;
  margin-top: 20px;
  display: flex;
  align-items: center;
  gap: 20px;
}

.selected-user-avatar {
  width: 70px;
  height: 70px;
  border-radius: 50%;
  background: var(--gradient-violet);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 28px;
  color: white;
}

.selected-user-info h4 {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 20px;
  font-weight: 600;
  margin-bottom: 5px;
}

.selected-user-info p {
  color: var(--text-muted);
  font-size: 14px;
}

/* Miners Grid */
.miners-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 20px;
  margin-top: 20px;
}

.miner-card {
  background: var(--bg-card);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  padding: 25px;
  cursor: pointer;
  transition: all 0.3s ease;
  position: relative;
}

.miner-card:hover {
  transform: translateY(-5px);
  border-color: var(--violet);
}

.miner-card.selected {
  border-color: var(--green);
  background: rgba(16, 185, 129, 0.05);
}

.miner-icon {
  font-size: 40px;
  margin-bottom: 20px;
  background: var(--gradient-gold);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.miner-name {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 20px;
  font-weight: 600;
  margin-bottom: 10px;
  color: var(--text-primary);
}

.miner-specs {
  list-style: none;
  margin-bottom: 20px;
}

.miner-specs li {
  color: var(--text-secondary);
  font-size: 14px;
  margin-bottom: 8px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.miner-specs li i {
  color: var(--green);
  font-size: 12px;
}

.miner-value {
  background: rgba(250, 204, 21, 0.1);
  border: 1px solid rgba(250, 204, 21, 0.2);
  border-radius: 12px;
  padding: 15px;
  text-align: center;
}

.value-label {
  color: var(--text-muted);
  font-size: 12px;
  margin-bottom: 5px;
}

.value-amount {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 20px;
  font-weight: 700;
  color: var(--gold);
}

/* Gift Button */
.gift-btn-container {
  text-align: center;
  margin-top: 40px;
}

.gift-btn {
  padding: 20px 50px;
  background: var(--gradient-green);
  color: white;
  border: none;
  border-radius: 16px;
  font-family: 'Space Grotesk', sans-serif;
  font-size: 18px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 15px;
}

.gift-btn:hover:not(:disabled) {
  transform: translateY(-3px);
  box-shadow: 0 15px 30px rgba(16, 185, 129, 0.4);
}

.gift-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* History Sections */
.history-section {
  background: var(--bg-card);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 20px;
  padding: 35px;
  margin-top: 40px;
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

.user-cell {
  display: flex;
  align-items: center;
  gap: 10px;
}

.user-avatar-small {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: var(--gradient-violet);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  color: white;
}

.miner-cell {
  font-weight: 600;
  color: var(--gold);
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
  
  .gift-steps {
    flex-direction: column;
    gap: 30px;
  }
  
  .gift-steps::before {
    display: none;
  }
  
  .search-form {
    flex-direction: column;
  }
  
  .search-input, .search-btn {
    width: 100%;
  }
  
  .miners-grid {
    grid-template-columns: 1fr;
  }
  
  .history-table {
    display: block;
    overflow-x: auto;
  }
  
  .selected-user-display {
    flex-direction: column;
    text-align: center;
  }
}
</style>
</head>
<body>
<!-- Sidebar -->
<div class="sidebar">
  <div class="logo">
    <i class="ph-lightning-fill"></i>
    MineMechanics
  </div>
  
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
    <a href="redeem.php" class="nav-item">
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
    <a href="gift.php" class="nav-item active">
      <i class="ph-gift"></i> Gift
    </a>
  </div>
</div>

<!-- Main Content -->
<div class="main-content">
  <div class="header">
    <div class="welcome-message">
      <h1>Gift Miners</h1>
      <p>Share your mining power with friends and team members</p>
    </div>
    <div>
      <span style="color: var(--text-muted); font-size: 14px;">User: <?php echo htmlspecialchars($profile['username'] ?? 'User'); ?></span>
    </div>
  </div>
  
  <div class="gift-container">
    <!-- Success/Error Messages -->
    <?php if ($giftMessage): ?>
      <div class="alert <?php echo $giftSuccess ? 'alert-success' : 'alert-error'; ?>">
        <i class="ph-<?php echo $giftSuccess ? 'check-circle' : 'warning-circle'; ?>"></i>
        <span><?php echo htmlspecialchars($giftMessage); ?></span>
      </div>
    <?php endif; ?>
    
    <!-- Gift Process Steps -->
    <div class="gift-steps">
      <div class="step <?php echo !$selectedUser ? 'active' : 'completed'; ?>">
        <div class="step-icon">
          <i class="ph-user"></i>
        </div>
        <div class="step-label">Select Recipient</div>
      </div>
      <div class="step <?php echo $selectedUser && empty($_POST['gift_miner']) ? 'active' : ($selectedUser ? 'completed' : ''); ?>">
        <div class="step-icon">
          <i class="ph-cpu"></i>
        </div>
        <div class="step-label">Choose Miner</div>
      </div>
      <div class="step <?php echo $giftSuccess ? 'active' : ''; ?>">
        <div class="step-icon">
          <i class="ph-gift"></i>
        </div>
        <div class="step-label">Confirm Gift</div>
      </div>
    </div>
    
    <!-- Step 1: Search for Recipient -->
    <div class="search-section">
      <div class="section-header">
        <div class="section-icon">
          <i class="ph-user-plus"></i>
        </div>
        <div class="section-title">
          <?php echo $selectedUser ? 'Selected Recipient' : 'Search for Recipient'; ?>
        </div>
      </div>
      
      <?php if (!$selectedUser): ?>
        <!-- Search Form -->
        <form method="POST" action="">
          <div class="search-form">
            <input type="text" name="search_query" class="search-input" 
                   placeholder="Search by username or email..." 
                   value="<?php echo htmlspecialchars($searchQuery); ?>">
            <button type="submit" name="search_user" class="search-btn">
              <i class="ph-magnifying-glass"></i> Search
            </button>
          </div>
        </form>
        
        <!-- Search Results -->
        <?php if (!empty($searchResults)): ?>
          <div class="search-results">
            <?php foreach ($searchResults as $user): ?>
              <form method="POST" action="" class="user-card">
                <div class="user-avatar">
                  <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                </div>
                <div class="user-name"><?php echo htmlspecialchars($user['username']); ?></div>
                <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
                <input type="hidden" name="selected_user_id" value="<?php echo $user['id']; ?>">
                <button type="submit" name="select_user" class="select-btn">
                  Select User
                </button>
              </form>
            <?php endforeach; ?>
          </div>
        <?php elseif (!empty($searchQuery)): ?>
          <div class="empty-state">
            <i class="ph-users"></i>
            <h3>No Users Found</h3>
            <p>Try searching with a different username or email</p>
          </div>
        <?php endif; ?>
      <?php else: ?>
        <!-- Selected User Display -->
        <div class="selected-user-display">
          <div class="selected-user-avatar">
            <?php echo strtoupper(substr($selectedUser['username'], 0, 1)); ?>
          </div>
          <div class="selected-user-info">
            <h4><?php echo htmlspecialchars($selectedUser['username']); ?></h4>
            <p><?php echo htmlspecialchars($selectedUser['email']); ?></p>
            <small style="color: var(--text-muted);">Location: <?php echo htmlspecialchars($selectedUser['location']); ?></small>
          </div>
        </div>
        
        <div style="text-align: center; margin-top: 20px;">
          <form method="POST" action="" style="display: inline-block;">
            <button type="submit" name="clear_selection" style="
              padding: 10px 20px;
              background: transparent;
              border: 1px solid var(--text-muted);
              color: var(--text-muted);
              border-radius: 8px;
              cursor: pointer;
              transition: all 0.3s ease;
            ">
              <i class="ph-arrow-left"></i> Select Different User
            </button>
          </form>
        </div>
      <?php endif; ?>
    </div>
    
    <!-- Step 2: Select Miner to Gift -->
    <?php if ($selectedUser): ?>
      <div class="search-section">
        <div class="section-header">
          <div class="section-icon">
            <i class="ph-cpu"></i>
          </div>
          <div class="section-title">
            Select Miner to Gift
          </div>
        </div>
        
        <?php if (!empty($userMiners)): ?>
          <form method="POST" action="" id="giftForm">
            <input type="hidden" name="receiver_id" value="<?php echo $selectedUser['id']; ?>">
            
            <div class="miners-grid">
              <?php foreach ($userMiners as $miner): ?>
                <div class="miner-card" onclick="selectMiner('<?php echo $miner['id']; ?>')" id="miner-<?php echo $miner['id']; ?>">
                  <div class="miner-icon">
                    <i class="ph-cpu"></i>
                  </div>
                  <h3 class="miner-name">
                    <?php echo htmlspecialchars($miner['miner_types']['name'] ?? 'Unknown Miner'); ?>
                  </h3>
                  <ul class="miner-specs">
                    <li>
                      <i class="ph-lightning"></i>
                      Hashpower: <?php echo number_format($miner['hashpower_ths'], 2); ?> TH/s
                    </li>
                    <li>
                      <i class="ph-bolt"></i>
                      Energy Usage: <?php echo number_format($miner['energy_usage_wh']); ?> W
                    </li>
                    <li>
                      <i class="ph-calendar"></i>
                      Acquired: <?php echo date('Y-m-d', strtotime($miner['created_at'])); ?>
                    </li>
                  </ul>
                  <div class="miner-value">
                    <div class="value-label">Value</div>
                    <div class="value-amount">$<?php echo number_format($miner['usd_value'], 2); ?></div>
                  </div>
                  <input type="radio" name="miner_id" value="<?php echo $miner['id']; ?>" 
                         style="display: none;" id="radio-<?php echo $miner['id']; ?>">
                </div>
              <?php endforeach; ?>
            </div>
            
            <div class="gift-btn-container">
              <button type="submit" name="gift_miner" class="gift-btn" id="giftBtn" disabled>
                <i class="ph-gift"></i>
                Gift Selected Miner
              </button>
            </div>
          </form>
        <?php else: ?>
          <div class="empty-state">
            <i class="ph-cpu"></i>
            <h3>No Miners Available</h3>
            <p>You don't have any miners to gift. Purchase miners first.</p>
            <a href="buy-miners.php" style="
              display: inline-block;
              margin-top: 20px;
              padding: 12px 24px;
              background: var(--gradient-violet);
              color: white;
              text-decoration: none;
              border-radius: 12px;
              font-family: 'Space Grotesk', sans-serif;
              font-weight: 600;
            ">
              <i class="ph-shopping-cart"></i> Buy Miners
            </a>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    
    <!-- Gift History -->
    <div class="history-section">
      <div class="history-header">
        <div class="history-title">Gifts Sent</div>
        <div style="color: var(--text-muted); font-size: 14px;">
          Showing last 10 gifts
        </div>
      </div>
      
      <?php if (!empty($giftHistory)): ?>
        <table class="history-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Recipient</th>
              <th>Miner</th>
              <th>Value</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($giftHistory as $gift): ?>
              <tr>
                <td class="date-cell">
                  <?php 
                    $date = new DateTime($gift['gifted_at']);
                    echo $date->format('Y-m-d H:i');
                  ?>
                </td>
                <td>
                  <div class="user-cell">
                    <div class="user-avatar-small">
                      <?php echo strtoupper(substr($gift['profiles']['username'] ?? 'U', 0, 1)); ?>
                    </div>
                    <?php echo htmlspecialchars($gift['profiles']['username'] ?? 'Unknown'); ?>
                  </div>
                </td>
                <td class="miner-cell">
                  <?php echo htmlspecialchars($gift['user_miners']['miner_types']['name'] ?? 'Unknown Miner'); ?>
                </td>
                <td style="color: var(--gold); font-weight: 600;">
                  $<?php echo number_format($gift['user_miners']['usd_value'] ?? 0, 2); ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="empty-state">
          <i class="ph-gift"></i>
          <h3>No Gifts Sent Yet</h3>
          <p>Your gift history will appear here</p>
        </div>
      <?php endif; ?>
    </div>
    
    <!-- Received Gifts -->
    <div class="history-section" style="margin-top: 30px;">
      <div class="history-header">
        <div class="history-title">Gifts Received</div>
        <div style="color: var(--text-muted); font-size: 14px;">
          Showing last 10 gifts
        </div>
      </div>
      
      <?php if (!empty($receivedGifts)): ?>
        <table class="history-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Sender</th>
              <th>Miner</th>
              <th>Value</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($receivedGifts as $gift): ?>
              <tr>
                <td class="date-cell">
                  <?php 
                    $date = new DateTime($gift['gifted_at']);
                    echo $date->format('Y-m-d H:i');
                  ?>
                </td>
                <td>
                  <div class="user-cell">
                    <div class="user-avatar-small">
                      <?php echo strtoupper(substr($gift['profiles']['username'] ?? 'U', 0, 1)); ?>
                    </div>
                    <?php echo htmlspecialchars($gift['profiles']['username'] ?? 'Unknown'); ?>
                  </div>
                </td>
                <td class="miner-cell">
                  <?php echo htmlspecialchars($gift['user_miners']['miner_types']['name'] ?? 'Unknown Miner'); ?>
                </td>
                <td style="color: var(--gold); font-weight: 600;">
                  $<?php echo number_format($gift['user_miners']['usd_value'] ?? 0, 2); ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="empty-state">
          <i class="ph-gift"></i>
          <h3>No Gifts Received Yet</h3>
          <p>Gifts from other users will appear here</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
// Miner selection
let selectedMinerId = null;

function selectMiner(minerId) {
    // Remove selected class from all cards
    document.querySelectorAll('.miner-card').forEach(card => {
        card.classList.remove('selected');
    });
    
    // Add selected class to clicked card
    document.getElementById('miner-' + minerId).classList.add('selected');
    
    // Update radio button
    document.getElementById('radio-' + minerId).checked = true;
    
    // Enable gift button
    document.getElementById('giftBtn').disabled = false;
    
    // Store selected miner
    selectedMinerId = minerId;
}

// Form validation
document.getElementById('giftForm')?.addEventListener('submit', function(e) {
    const giftBtn = this.querySelector('[name="gift_miner"]');
    if (giftBtn && !giftBtn.disabled) {
        // Add loading state
        giftBtn.innerHTML = '<i class="ph-circle-notch ph-spin"></i> Processing Gift...';
        giftBtn.disabled = true;
    }
});

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
</script>
</body>
</html>