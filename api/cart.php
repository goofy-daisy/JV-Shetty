<?php
/**
 * Cart API Endpoints
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Validator.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../models/Cart.php';
require_once __DIR__ . '/../models/Product.php';

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Cart requires authentication
Auth::requireAuth();

$user = Auth::user();
$cart = new Cart();
$userCart = $cart->getOrCreate($user['id']);

switch ($action) {
    case 'get':
        handleGet($cart, $userCart['id']);
        break;
        
    case 'add':
        if ($method !== 'POST') {
            Response::error('Method not allowed', 405);
        }
        handleAdd($cart, $userCart['id']);
        break;
        
    case 'update':
        if ($method !== 'POST') {
            Response::error('Method not allowed', 405);
        }
        handleUpdate($cart);
        break;
        
    case 'remove':
        if ($method !== 'POST') {
            Response::error('Method not allowed', 405);
        }
        handleRemove($cart);
        break;
        
    case 'clear':
        if ($method !== 'POST') {
            Response::error('Method not allowed', 405);
        }
        handleClear($cart, $userCart['id']);
        break;
        
    case 'count':
        handleCount($cart, $userCart['id']);
        break;
        
    case 'validate':
        handleValidate($cart, $userCart['id']);
        break;
        
    default:
        Response::error('Invalid action', 404);
}

function handleGet(Cart $cart, int $cartId): void {
    $data = $cart->getWithItems($cartId);
    
    if (!$data) {
        Response::notFound();
    }
    
    Response::success($data);
}

function handleAdd(Cart $cart, int $cartId): void {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $validator = new Validator();
    $rules = [
        'product_id' => 'required|integer|positive',
        'quantity' => 'required|integer|positive'
    ];
    
    if (!$validator->validate($input, $rules)) {
        Response::error('Validation failed', 422, $validator->errors());
    }
    
    $result = $cart->addItem(
        $cartId,
        $input['product_id'],
        $input['quantity'],
        $input['notes'] ?? ''
    );
    
    if (!$result['success']) {
        Response::error($result['message'], 400);
    }
    
    Response::success([], $result['message']);
}

function handleUpdate(Cart $cart): void {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $validator = new Validator();
    $rules = [
        'item_id' => 'required|integer|positive',
        'quantity' => 'required|integer'
    ];
    
    if (!$validator->validate($input, $rules)) {
        Response::error('Validation failed', 422, $validator->errors());
    }
    
    $result = $cart->updateItem($input['item_id'], $input['quantity']);
    
    if (!$result['success']) {
        Response::error($result['message'], 400);
    }
    
    Response::success([], $result['message']);
}

function handleRemove(Cart $cart): void {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $validator = new Validator();
    $rules = [
        'item_id' => 'required|integer|positive'
    ];
    
    if (!$validator->validate($input, $rules)) {
        Response::error('Validation failed', 422, $validator->errors());
    }
    
    $result = $cart->removeItem($input['item_id']);
    
    if (!$result['success']) {
        Response::error($result['message'], 400);
    }
    
    Response::success([], $result['message']);
}

function handleClear(Cart $cart, int $cartId): void {
    $success = $cart->clear($cartId);
    
    if (!$success) {
        Response::error('Failed to clear cart', 500);
    }
    
    Response::success([], 'Cart cleared');
}

function handleCount(Cart $cart, int $cartId): void {
    $count = $cart->getItemCount($cartId);
    Response::success(['count' => $count]);
}

function handleValidate(Cart $cart, int $cartId): void {
    $validation = $cart->validateStock($cartId);
    Response::success($validation);
}
