<?php
/**
 * Orders API Endpoints
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Validator.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/StoreHours.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/Cart.php';

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

$order = new Order();

switch ($action) {
    case 'create':
        Auth::requireAuth();
        if ($method !== 'POST') {
            Response::error('Method not allowed', 405);
        }
        handleCreate($order);
        break;
        
    case 'list':
        Auth::requireAuth();
        handleList($order);
        break;
        
    case 'detail':
        Auth::requireAuth();
        handleDetail($order);
        break;
        
    case 'cancel':
        Auth::requireAuth();
        if ($method !== 'POST') {
            Response::error('Method not allowed', 405);
        }
        handleCancel($order);
        break;
        
    case 'update_status':
        Auth::requirePermission('orders.manage');
        if ($method !== 'POST') {
            Response::error('Method not allowed', 405);
        }
        handleUpdateStatus($order);
        break;
        
    case 'admin_list':
        Auth::requirePermission('orders.manage');
        handleAdminList($order);
        break;
        
    case 'delivery_list':
        Auth::requirePermission('delivery.view');
        handleDeliveryList($order);
        break;
        
    case 'assign_delivery':
        Auth::requirePermission('delivery.manage');
        if ($method !== 'POST') {
            Response::error('Method not allowed', 405);
        }
        handleAssignDelivery($order);
        break;
        
    default:
        Response::error('Invalid action', 404);
}

function handleCreate(Order $order): void {
    // Validate store hours
    $storeStatus = StoreHours::validateCheckout();
    if (!$storeStatus['allowed']) {
        Response::error($storeStatus['message'], 403);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $validator = new Validator();
    $rules = [
        'fulfillment_type' => 'required|in:delivery,pickup',
        'address_id' => 'integer',
        'pickup_slot_id' => 'integer'
    ];
    
    if (!$validator->validate($input, $rules)) {
        Response::error('Validation failed', 422, $validator->errors());
    }
    
    if ($input['fulfillment_type'] === 'delivery' && empty($input['address_id'])) {
        Response::error('Delivery address required', 422);
    }
    
    if ($input['fulfillment_type'] === 'pickup' && empty($input['pickup_slot_id'])) {
        Response::error('Pickup slot required', 422);
    }
    
    $user = Auth::user();
    $cart = new Cart();
    $userCart = $cart->getOrCreate($user['id']);
    
    $result = $order->createFromCart($user['id'], $userCart['id'], [
        'fulfillment_type' => $input['fulfillment_type'],
        'address_id' => $input['address_id'] ?? null,
        'pickup_slot_id' => $input['pickup_slot_id'] ?? null,
        'notes' => $input['notes'] ?? null
    ]);
    
    if (!$result['success']) {
        Response::error($result['message'], 400, $result['stock_errors'] ?? []);
    }
    
    Response::success([
        'order_id' => $result['order_id'],
        'order_number' => $result['order_number']
    ], $result['message']);
}

function handleList(Order $order): void {
    $user = Auth::user();
    $page = (int)($_GET['page'] ?? 1);
    $perPage = (int)($_GET['per_page'] ?? 10);
    
    $result = $order->getForUser($user['id'], $page, $perPage);
    Response::success($result);
}

function handleDetail(Order $order): void {
    $id = (int)($_GET['id'] ?? 0);
    
    if (!$id) {
        Response::error('Order ID required', 400);
    }
    
    $data = $order->getWithDetails($id);
    
    if (!$data) {
        Response::notFound();
    }
    
    // Check if user owns this order or has admin permission
    $user = Auth::user();
    if ($data['user_id'] != $user['id'] && !Auth::hasPermission('orders.manage')) {
        Response::forbidden();
    }
    
    Response::success($data);
}

function handleCancel(Order $order): void {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $validator = new Validator();
    $rules = [
        'order_id' => 'required|integer|positive',
        'reason' => 'required|min:5'
    ];
    
    if (!$validator->validate($input, $rules)) {
        Response::error('Validation failed', 422, $validator->errors());
    }
    
    $user = Auth::user();
    $orderData = $order->find($input['order_id']);
    
    if (!$orderData) {
        Response::notFound();
    }
    
    // Check ownership
    if ($orderData['user_id'] != $user['id'] && !Auth::hasPermission('orders.manage')) {
        Response::forbidden();
    }
    
    $result = $order->cancel($input['order_id'], $user['id'], $input['reason']);
    
    if (!$result['success']) {
        Response::error($result['message'], 400);
    }
    
    Response::success([], $result['message']);
}

function handleUpdateStatus(Order $order): void {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $validator = new Validator();
    $rules = [
        'order_id' => 'required|integer|positive',
        'status' => 'required|in:placed,created,payment_pending,paid,confirmed,packed,assigned,out_for_delivery,delivered,picked_up,cancelled,refunded'
    ];
    
    if (!$validator->validate($input, $rules)) {
        Response::error('Validation failed', 422, $validator->errors());
    }
    
    $user = Auth::user();
    $result = $order->updateStatus($input['order_id'], $input['status'], $user['id'], $input['notes'] ?? '');
    
    if (!$result['success']) {
        Response::error($result['message'], 400);
    }
    
    Response::success([], $result['message']);
}

function handleAdminList(Order $order): void {
    $filters = [
        'status' => $_GET['status'] ?? null,
        'user_id' => $_GET['user_id'] ?? null,
        'date_from' => $_GET['date_from'] ?? null,
        'date_to' => $_GET['date_to'] ?? null,
        'search' => $_GET['search'] ?? null
    ];
    
    $page = (int)($_GET['page'] ?? 1);
    $perPage = (int)($_GET['per_page'] ?? 20);
    
    $result = $order->getAllFiltered(array_filter($filters), $page, $perPage);
    Response::success($result);
}

function handleDeliveryList(Order $order): void {
    $user = Auth::user();
    
    // Get delivery partner ID
    $db = Database::getInstance();
    $stmt = $db->prepare("SELECT id FROM delivery_partners WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $partner = $stmt->fetch();
    
    if (!$partner) {
        Response::error('Delivery partner record not found', 404);
    }
    
    $status = $_GET['status'] ?? null;
    $data = $order->getForDeliveryPartner($partner['id'], $status);
    
    Response::success(['orders' => $data]);
}

function handleAssignDelivery(Order $order): void {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $validator = new Validator();
    $rules = [
        'order_id' => 'required|integer|positive',
        'delivery_partner_id' => 'required|integer|positive'
    ];
    
    if (!$validator->validate($input, $rules)) {
        Response::error('Validation failed', 422, $validator->errors());
    }
    
    $user = Auth::user();
    $result = $order->assignDelivery($input['order_id'], $input['delivery_partner_id'], $user['id']);
    
    if (!$result['success']) {
        Response::error($result['message'], 400);
    }
    
    Response::success([], $result['message']);
}
