<?php
/**
 * User API Endpoints
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Validator.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../models/User.php';

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

$user = new User();

switch ($action) {
    case 'profile':
        Auth::requireAuth();
        handleProfile($user);
        break;
        
    case 'update_profile':
        Auth::requireAuth();
        if ($method !== 'POST') {
            Response::error('Method not allowed', 405);
        }
        handleUpdateProfile($user);
        break;
        
    case 'change_password':
        Auth::requireAuth();
        if ($method !== 'POST') {
            Response::error('Method not allowed', 405);
        }
        handleChangePassword();
        break;
        
    case 'addresses':
        Auth::requireAuth();
        handleAddresses($user);
        break;
        
    case 'add_address':
        Auth::requireAuth();
        if ($method !== 'POST') {
            Response::error('Method not allowed', 405);
        }
        handleAddAddress();
        break;
        
    case 'update_address':
        Auth::requireAuth();
        if ($method !== 'POST') {
            Response::error('Method not allowed', 405);
        }
        handleUpdateAddress();
        break;
        
    case 'delete_address':
        Auth::requireAuth();
        if ($method !== 'POST') {
            Response::error('Method not allowed', 405);
        }
        handleDeleteAddress();
        break;
        
    case 'hostels':
        handleHostels();
        break;
        
    case 'store_status':
        handleStoreStatus();
        break;
        
    case 'pickup_slots':
        handlePickupSlots();
        break;
        
    default:
        Response::error('Invalid action', 404);
}

function handleProfile(User $user): void {
    $currentUser = Auth::user();
    $profile = $user->getWithProfile($currentUser['id']);
    Response::success($profile);
}

function handleUpdateProfile(User $user): void {
    $input = json_decode(file_get_contents('php://input'), true);
    $currentUser = Auth::user();
    
    $validator = new Validator();
    $rules = [
        'first_name' => 'min:2|max:50',
        'last_name' => 'min:2|max:50',
        'phone' => 'phone'
    ];
    
    if (!$validator->validate($input, $rules)) {
        Response::error('Validation failed', 422, $validator->errors());
    }
    
    $profileData = array_intersect_key($input, array_flip(['first_name', 'last_name', 'phone', 'date_of_birth', 'gender', 'department']));
    
    try {
        $user->updateWithProfile($currentUser['id'], [], $profileData);
        Response::success([], 'Profile updated successfully');
    } catch (Exception $e) {
        Response::error('Failed to update profile', 500);
    }
}

function handleChangePassword(): void {
    $input = json_decode(file_get_contents('php://input'), true);
    $currentUser = Auth::user();
    
    $validator = new Validator();
    $rules = [
        'current_password' => 'required',
        'new_password' => 'required|password',
        'confirm_password' => 'required|match:new_password'
    ];
    
    if (!$validator->validate($input, $rules)) {
        Response::error('Validation failed', 422, $validator->errors());
    }
    
    // Verify current password
    $db = Database::getInstance();
    $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$currentUser['id']]);
    $hash = $stmt->fetchColumn();
    
    if (!password_verify($input['current_password'], $hash)) {
        Response::error('Current password is incorrect', 400);
    }
    
    // Update password
    $newHash = password_hash($input['new_password'], PASSWORD_ARGON2ID);
    $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $stmt->execute([$newHash, $currentUser['id']]);
    
    Response::success([], 'Password changed successfully');
}

function handleAddresses(User $user): void {
    $currentUser = Auth::user();
    $addresses = $user->getAddresses($currentUser['id']);
    Response::success(['addresses' => $addresses]);
}

function handleAddAddress(): void {
    $input = json_decode(file_get_contents('php://input'), true);
    $currentUser = Auth::user();
    
    $validator = new Validator();
    $rules = [
        'hostel_id' => 'required|integer',
        'room_number' => 'required|max:20',
        'address_line1' => 'required|max:255',
        'is_default' => 'boolean'
    ];
    
    if (!$validator->validate($input, $rules)) {
        Response::error('Validation failed', 422, $validator->errors());
    }
    
    $db = Database::getInstance();
    
    try {
        $db->beginTransaction();
        
        // If setting as default, unset other defaults
        if (!empty($input['is_default'])) {
            $stmt = $db->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = ?");
            $stmt->execute([$currentUser['id']]);
        }
        
        $stmt = $db->prepare("
            INSERT INTO addresses (user_id, hostel_id, room_number, address_line1, address_line2, is_default, instructions)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $currentUser['id'],
            $input['hostel_id'],
            $input['room_number'],
            $input['address_line1'],
            $input['address_line2'] ?? null,
            !empty($input['is_default']) ? 1 : 0,
            $input['instructions'] ?? null
        ]);
        
        $db->commit();
        Response::success([], 'Address added successfully');
    } catch (Exception $e) {
        $db->rollBack();
        Response::error('Failed to add address', 500);
    }
}

function handleUpdateAddress(): void {
    $input = json_decode(file_get_contents('php://input'), true);
    $currentUser = Auth::user();
    
    $validator = new Validator();
    $rules = [
        'address_id' => 'required|integer',
        'hostel_id' => 'integer',
        'room_number' => 'max:20',
        'address_line1' => 'max:255'
    ];
    
    if (!$validator->validate($input, $rules)) {
        Response::error('Validation failed', 422, $validator->errors());
    }
    
    $db = Database::getInstance();
    
    // Verify ownership
    $stmt = $db->prepare("SELECT user_id FROM addresses WHERE id = ?");
    $stmt->execute([$input['address_id']]);
    $owner = $stmt->fetchColumn();
    
    if ($owner != $currentUser['id']) {
        Response::forbidden();
    }
    
    try {
        $db->beginTransaction();
        
        // If setting as default, unset other defaults
        if (!empty($input['is_default'])) {
            $stmt = $db->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = ?");
            $stmt->execute([$currentUser['id']]);
        }
        
        $fields = [];
        $values = [];
        $allowedFields = ['hostel_id', 'room_number', 'address_line1', 'address_line2', 'is_default', 'instructions'];
        
        foreach ($input as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $fields[] = "{$key} = ?";
                $values[] = $value;
            }
        }
        
        if (empty($fields)) {
            Response::error('No valid fields to update', 400);
        }
        
        $values[] = $input['address_id'];
        $sql = "UPDATE addresses SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($values);
        
        $db->commit();
        Response::success([], 'Address updated successfully');
    } catch (Exception $e) {
        $db->rollBack();
        Response::error('Failed to update address', 500);
    }
}

function handleDeleteAddress(): void {
    $input = json_decode(file_get_contents('php://input'), true);
    $currentUser = Auth::user();
    
    $addressId = $input['address_id'] ?? 0;
    
    $db = Database::getInstance();
    
    // Verify ownership
    $stmt = $db->prepare("SELECT user_id FROM addresses WHERE id = ?");
    $stmt->execute([$addressId]);
    $owner = $stmt->fetchColumn();
    
    if ($owner != $currentUser['id']) {
        Response::forbidden();
    }
    
    $stmt = $db->prepare("UPDATE addresses SET is_active = 0 WHERE id = ?");
    $stmt->execute([$addressId]);
    
    Response::success([], 'Address deleted successfully');
}

function handleHostels(): void {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT * FROM hostel_list WHERE is_active = 1 ORDER BY type, name");
    $hostels = $stmt->fetchAll();
    Response::success(['hostels' => $hostels]);
}

function handleStoreStatus(): void {
    $status = StoreHours::getStatus();
    $hours = StoreHours::getFormattedHours();
    $nextOpen = StoreHours::getNextOpening();
    
    Response::success([
        'status' => $status,
        'hours' => $hours,
        'next_opening' => $nextOpen
    ]);
}

function handlePickupSlots(): void {
    $db = Database::getInstance();
    
    // Get slots for next 3 days
    $stmt = $db->prepare("
        SELECT * FROM pickup_slots 
        WHERE slot_date >= CURDATE() 
        AND slot_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)
        AND is_active = 1
        AND booked_orders < max_orders
        ORDER BY slot_date, start_time
    ");
    $stmt->execute();
    $slots = $stmt->fetchAll();
    
    // Group by date
    $grouped = [];
    foreach ($slots as $slot) {
        $date = $slot['slot_date'];
        if (!isset($grouped[$date])) {
            $grouped[$date] = [];
        }
        $grouped[$date][] = $slot;
    }
    
    Response::success(['slots' => $grouped]);
}
