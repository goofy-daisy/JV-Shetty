<?php
/**
 * Search Functionality
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../models/Product.php';

header('Content-Type: application/json');

$query = $_GET['q'] ?? '';
$type = $_GET['type'] ?? 'products'; // products, orders, customers
$limit = (int)($_GET['limit'] ?? 10);

if (strlen($query) < 2) {
    Response::error('Query too short');
}

switch ($type) {
    case 'products':
        $product = new Product();
        $results = $product->search($query, $limit);
        
        // Format results for autocomplete
        $formatted = array_map(function($item) {
            return [
                'id' => $item['id'],
                'name' => $item['name'],
                'sku' => $item['sku'],
                'price' => $item['sale_price'] ?? $item['base_price'],
                'image' => $item['primary_image'] ?? 'assets/images/product-default.jpg',
                'category' => $item['category_name'],
                'stock' => $item['stock'],
                'url' => BASE_URL . '/product.php?id=' . $item['id']
            ];
        }, $results);
        
        Response::success('Products found', $formatted);
        break;
        
    case 'orders':
        Auth::requireAuth();
        
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT o.*, u.email, p.first_name, p.last_name
            FROM orders o
            JOIN users u ON o.user_id = u.id
            LEFT JOIN user_profiles p ON u.id = p.user_id
            WHERE (o.order_number LIKE ? OR u.email LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ?)
            ORDER BY o.created_at DESC
            LIMIT ?
        ");
        
        $searchTerm = '%' . $query . '%';
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $limit]);
        $results = $stmt->fetchAll();
        
        $formatted = array_map(function($item) {
            return [
                'id' => $item['id'],
                'order_number' => $item['order_number'],
                'customer' => ($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? ''),
                'email' => $item['email'],
                'total' => $item['total_amount'],
                'status' => $item['status'],
                'date' => $item['created_at'],
                'url' => BASE_URL . '/admin/order_detail.php?id=' . $item['id']
            ];
        }, $results);
        
        Response::success('Orders found', $formatted);
        break;
        
    case 'customers':
        Auth::requirePermission('customers.view');
        
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT u.*, p.first_name, p.last_name, p.phone
            FROM users u
            LEFT JOIN user_profiles p ON u.id = p.user_id
            WHERE (u.email LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ? OR p.phone LIKE ?)
            ORDER BY u.created_at DESC
            LIMIT ?
        ");
        
        $searchTerm = '%' . $query . '%';
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $limit]);
        $results = $stmt->fetchAll();
        
        $formatted = array_map(function($item) {
            return [
                'id' => $item['id'],
                'name' => ($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? ''),
                'email' => $item['email'],
                'phone' => $item['phone'],
                'role' => $item['role_id'],
                'status' => $item['is_active'] ? 'Active' : 'Inactive',
                'joined' => $item['created_at'],
                'url' => BASE_URL . '/admin/customers.php?search=' . urlencode($item['email'])
            ];
        }, $results);
        
        Response::success('Customers found', $formatted);
        break;
        
    default:
        Response::error('Invalid search type');
}
?>
