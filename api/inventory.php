<?php
/**
 * Inventory API
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../models/Product.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$product = new Product();

switch ($action) {
    case 'update_stock':
        Auth::requirePermission('inventory.manage');
        
        $productId = (int)($_POST['product_id'] ?? 0);
        $updateType = $_POST['update_type'] ?? '';
        $quantity = (int)($_POST['quantity'] ?? 0);
        $reason = $_POST['reason'] ?? '';
        
        if (!$productId || !$updateType || $quantity < 0) {
            Response::error('Invalid parameters');
        }
        
        // Get current inventory
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM inventory WHERE product_id = ?");
        $stmt->execute([$productId]);
        $inventory = $stmt->fetch();
        
        if (!$inventory) {
            // Create inventory record if not exists
            $stmt = $db->prepare("
                INSERT INTO inventory (product_id, quantity_in_stock, quantity_reserved, reorder_level, created_at)
                VALUES (?, 0, 0, 10, NOW())
            ");
            $stmt->execute([$productId]);
            $currentStock = 0;
        } else {
            $currentStock = $inventory['quantity_in_stock'];
        }
        
        // Calculate new stock
        $newStock = $currentStock;
        switch ($updateType) {
            case 'add':
                $newStock += $quantity;
                break;
            case 'remove':
                $newStock = max(0, $newStock - $quantity);
                break;
            case 'set':
                $newStock = $quantity;
                break;
        }
        
        // Update inventory - update quantity_in_stock (not quantity_available which is computed)
        $stmt = $db->prepare("
            UPDATE inventory 
            SET quantity_in_stock = ?, last_stock_update = NOW()
            WHERE product_id = ?
        ");
        $success = $stmt->execute([$newStock, $productId]);
        
        if ($success) {
            // Log the adjustment
            $stmt = $db->prepare("
                INSERT INTO inventory_adjustments (product_id, adjustment_type, quantity, reason, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$productId, $updateType, $quantity, $reason, Auth::user()['id']]);
            
            Response::success('Stock updated successfully');
        } else {
            Response::error('Failed to update stock');
        }
        break;
        
    case 'get_low_stock':
        Auth::requirePermission('inventory.view');
        
        $stmt = $db->query("
            SELECT p.*, i.quantity_available, i.reorder_level, c.name as category_name
            FROM products p
            JOIN inventory i ON p.id = i.product_id
            JOIN categories c ON p.category_id = c.id
            WHERE i.quantity_available <= i.reorder_level AND p.is_active = 1
            ORDER BY i.quantity_available ASC
            LIMIT 50
        ");
        $items = $stmt->fetchAll();
        
        Response::success('Low stock items retrieved', $items);
        break;
        
    case 'get_out_of_stock':
        Auth::requirePermission('inventory.view');
        
        $stmt = $db->query("
            SELECT p.*, i.quantity_available, c.name as category_name
            FROM products p
            JOIN inventory i ON p.id = i.product_id
            JOIN categories c ON p.category_id = c.id
            WHERE i.quantity_available = 0 AND p.is_active = 1
            ORDER BY p.name
            LIMIT 50
        ");
        $items = $stmt->fetchAll();
        
        Response::success('Out of stock items retrieved', $items);
        break;
        
    default:
        Response::error('Invalid action');
}
?>
