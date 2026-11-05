<?php
/**
 * Wishlist API Endpoints
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Validator.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../models/Product.php';

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

Auth::requireAuth();

$user = Auth::user();
$db = Database::getInstance();

// Get or create wishlist for user
function getOrCreateWishlist(int $userId): int {
    $db = Database::getInstance();
    $stmt = $db->prepare("SELECT id FROM wishlists WHERE user_id = ? AND is_default = 1");
    $stmt->execute([$userId]);
    $wishlist = $stmt->fetch();
    
    if ($wishlist) {
        return $wishlist['id'];
    }
    
    // Create default wishlist
    $stmt = $db->prepare("INSERT INTO wishlists (user_id, name, is_default) VALUES (?, 'My Wishlist', 1)");
    $stmt->execute([$userId]);
    return $db->lastInsertId();
}

switch ($action) {
    case 'get':
        handleGet($db, $user['id']);
        break;
        
    case 'add':
        if ($method !== 'POST') {
            Response::error('Method not allowed', 405);
        }
        handleAdd($db, $user['id']);
        break;
        
    case 'remove':
        if ($method !== 'POST') {
            Response::error('Method not allowed', 405);
        }
        handleRemove($db, $user['id']);
        break;
        
    case 'move_to_cart':
        if ($method !== 'POST') {
            Response::error('Method not allowed', 405);
        }
        handleMoveToCart($db, $user['id']);
        break;
        
    case 'check':
        handleCheck($db, $user['id']);
        break;
        
    default:
        Response::error('Invalid action', 404);
}

function handleGet($db, int $userId): void {
    $wishlistId = getOrCreateWishlist($userId);
    
    $stmt = $db->prepare("
        SELECT wi.id as wishlist_item_id, wi.product_id, wi.added_at,
               p.name, p.slug, p.base_price, p.sale_price, p.is_active,
               (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image,
               i.quantity_available as stock
        FROM wishlist_items wi
        JOIN products p ON wi.product_id = p.id
        LEFT JOIN inventory i ON p.id = i.product_id
        WHERE wi.wishlist_id = ? AND p.deleted_at IS NULL
        ORDER BY wi.added_at DESC
    ");
    $stmt->execute([$wishlistId]);
    $items = $stmt->fetchAll();
    
    Response::success(['items' => $items]);
}

function handleAdd($db, int $userId): void {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $validator = new Validator();
    $rules = [
        'product_id' => 'required|integer|positive'
    ];
    
    if (!$validator->validate($input, $rules)) {
        Response::error('Validation failed', 422, $validator->errors());
    }
    
    $wishlistId = getOrCreateWishlist($userId);
    
    // Check if already in wishlist
    $stmt = $db->prepare("SELECT id FROM wishlist_items WHERE wishlist_id = ? AND product_id = ?");
    $stmt->execute([$wishlistId, $input['product_id']]);
    if ($stmt->fetch()) {
        Response::error('Product already in wishlist', 400);
    }
    
    // Add to wishlist
    $stmt = $db->prepare("INSERT INTO wishlist_items (wishlist_id, product_id) VALUES (?, ?)");
    $stmt->execute([$wishlistId, $input['product_id']]);
    
    Response::success([], 'Added to wishlist');
}

function handleRemove($db, int $userId): void {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $validator = new Validator();
    $rules = [
        'product_id' => 'required|integer|positive'
    ];
    
    if (!$validator->validate($input, $rules)) {
        Response::error('Validation failed', 422, $validator->errors());
    }
    
    $wishlistId = getOrCreateWishlist($userId);
    
    $stmt = $db->prepare("DELETE FROM wishlist_items WHERE wishlist_id = ? AND product_id = ?");
    $stmt->execute([$wishlistId, $input['product_id']]);
    
    Response::success([], 'Removed from wishlist');
}

function handleMoveToCart($db, int $userId): void {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $validator = new Validator();
    $rules = [
        'product_id' => 'required|integer|positive',
        'quantity' => 'integer|positive'
    ];
    
    if (!$validator->validate($input, $rules)) {
        Response::error('Validation failed', 422, $validator->errors());
    }
    
    $wishlistId = getOrCreateWishlist($userId);
    $quantity = $input['quantity'] ?? 1;
    
    // Check if product is in wishlist
    $stmt = $db->prepare("SELECT id FROM wishlist_items WHERE wishlist_id = ? AND product_id = ?");
    $stmt->execute([$wishlistId, $input['product_id']]);
    if (!$stmt->fetch()) {
        Response::error('Product not in wishlist', 404);
    }
    
    // Check stock
    $product = new Product();
    if (!$product->checkStock($input['product_id'], $quantity)) {
        Response::error('Insufficient stock', 400);
    }
    
    // Add to cart
    require_once __DIR__ . '/../models/Cart.php';
    $cart = new Cart();
    $userCart = $cart->getOrCreate($userId);
    $result = $cart->addItem($userCart['id'], $input['product_id'], $quantity);
    
    if (!$result['success']) {
        Response::error($result['message'], 400);
    }
    
    // Remove from wishlist
    $stmt = $db->prepare("DELETE FROM wishlist_items WHERE wishlist_id = ? AND product_id = ?");
    $stmt->execute([$wishlistId, $input['product_id']]);
    
    Response::success([], 'Moved to cart');
}

function handleCheck($db, int $userId): void {
    $productId = (int)($_GET['product_id'] ?? 0);
    
    if (!$productId) {
        Response::error('Product ID required', 400);
    }
    
    $wishlistId = getOrCreateWishlist($userId);
    
    $stmt = $db->prepare("SELECT id FROM wishlist_items WHERE wishlist_id = ? AND product_id = ?");
    $stmt->execute([$wishlistId, $productId]);
    $inWishlist = $stmt->fetch() ? true : false;
    
    Response::success(['in_wishlist' => $inWishlist]);
}
