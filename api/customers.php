<?php
/**
 * Customers API
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../models/User.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$user = new User();

switch ($action) {
    case 'toggle_status':
        Auth::requirePermission('customers.manage');
        
        $userId = (int)($_POST['user_id'] ?? 0);
        $isActive = (int)($_POST['is_active'] ?? 0);
        
        if (!$userId) {
            Response::error('Invalid user ID');
        }
        
        // Prevent deactivating self
        if ($userId == Auth::user()['id']) {
            Response::error('Cannot deactivate your own account');
        }
        
        $db = Database::getInstance();
        $stmt = $db->prepare("UPDATE users SET is_active = ?, updated_at = NOW() WHERE id = ?");
        $success = $stmt->execute([$isActive, $userId]);
        
        if ($success) {
            Response::success('User status updated successfully');
        } else {
            Response::error('Failed to update user status');
        }
        break;
        
    case 'get_details':
        Auth::requirePermission('customers.view');
        
        $userId = (int)($_GET['user_id'] ?? 0);
        if (!$userId) {
            Response::error('Invalid user ID');
        }
        
        $customer = $user->getWithProfile($userId);
        if (!$customer) {
            Response::error('Customer not found');
        }
        
        // Get addresses
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC");
        $stmt->execute([$userId]);
        $customer['addresses'] = $stmt->fetchAll();
        
        // Get recent orders
        $stmt = $db->prepare("
            SELECT * FROM orders 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        $stmt->execute([$userId]);
        $customer['recent_orders'] = $stmt->fetchAll();
        
        Response::success('Customer details retrieved', $customer);
        break;
        
    case 'create':
        Auth::requirePermission('customers.manage');

        $input     = json_decode(file_get_contents('php://input'), true) ?? [];
        $validator = new Validator();
        $rules = [
            'email'      => 'required|email',
            'password'   => 'required|min:8',
            'first_name' => 'required|min:2|max:100',
            'last_name'  => 'required|min:2|max:100',
            'role'       => 'required|in:customer,delivery_partner,admin'
        ];

        if (!$validator->validate($input, $rules)) {
            echo json_encode(['success' => false, 'message' => $validator->firstError()]);
            exit;
        }

        // Check campus email only for customers
        if ($input['role'] === 'customer' && !Auth::isValidCampusEmail($input['email'])) {
            echo json_encode(['success' => false, 'message' => 'Customer accounts require a @goa.bits-pilani.ac.in email']);
            exit;
        }

        // Check if email already exists
        $db   = Database::getInstance();
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$input['email']]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Email already registered']);
            exit;
        }

        // Get role ID
        $stmt = $db->prepare("SELECT id FROM roles WHERE name = ?");
        $stmt->execute([$input['role']]);
        $role = $stmt->fetch();
        if (!$role) {
            echo json_encode(['success' => false, 'message' => 'Invalid role']);
            exit;
        }

        try {
            $db->beginTransaction();

            // Create user
            $stmt = $db->prepare("
                INSERT INTO users (email, password_hash, role_id, is_active, is_verified, created_at)
                VALUES (?, ?, ?, 1, 1, NOW())
            ");
            $stmt->execute([
                $input['email'],
                password_hash($input['password'], PASSWORD_ARGON2ID),
                $role['id']
            ]);
            $newUserId = $db->lastInsertId();

            // Create profile
            $stmt = $db->prepare("
                INSERT INTO user_profiles (user_id, first_name, last_name, phone, student_id, department)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $newUserId,
                $input['first_name'],
                $input['last_name'],
                $input['phone']      ?? '',
                $input['student_id'] ?? '',
                $input['department'] ?? ''
            ]);

            // If delivery partner: also create delivery_partners record
            if ($input['role'] === 'delivery_partner') {
                $employeeId = 'DP-' . strtoupper(substr(uniqid(), -6));
                $stmt = $db->prepare("
                    INSERT INTO delivery_partners (user_id, employee_id, is_available, total_deliveries)
                    VALUES (?, ?, 1, 0)
                ");
                $stmt->execute([$newUserId, $employeeId]);
            }

            $db->commit();
            echo json_encode(['success' => true, 'message' => 'User created successfully', 'user_id' => $newUserId]);
        } catch (Exception $e) {
            $db->rollBack();
            error_log('[api/customers create] ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to create user. Please try again.']);
        }
        break;
        
    default:
        Response::error('Invalid action');
}
?>
