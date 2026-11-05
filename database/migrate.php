<?php
/**
 * Database Migration Script for JV Shetty E-commerce
 * Run this after updating the codebase to ensure database consistency
 */

require_once __DIR__ . '/../config/app.php';

echo "=== JV Shetty Database Migration ===\n\n";

try {
    $db = Database::getInstance();

    // 1. Update orders table to add 'placed' to enum if not exists
    echo "1. Checking orders table status enum...\n";
    $stmt = $db->query("SHOW COLUMNS FROM orders WHERE Field = 'status'");
    $statusColumn = $stmt->fetch();

    if ($statusColumn && strpos($statusColumn['Type'], 'placed') === false) {
        // Need to modify the enum to add 'placed'
        $db->query("ALTER TABLE orders MODIFY COLUMN status ENUM(
            'placed', 'created', 'payment_pending', 'paid', 'confirmed', 
            'packed', 'assigned', 'out_for_delivery', 'delivered', 
            'picked_up', 'cancelled', 'refunded'
        ) NOT NULL DEFAULT 'placed'");
        echo "   ✓ Added 'placed' status to orders.enum\n";
    }
    else {
        echo "   ✓ 'placed' status already exists\n";
    }

    // 2. Update existing orders with 'created' status to 'placed'
    echo "\n2. Migrating existing 'created' orders to 'placed'...\n";
    $stmt = $db->query("UPDATE orders SET status = 'placed' WHERE status = 'created'");
    $updated = $stmt->rowCount();
    echo "   ✓ Updated {$updated} orders\n";

    // 3. Ensure all products have inventory records
    echo "\n3. Creating missing inventory records...\n";
    $stmt = $db->query("
        INSERT INTO inventory (product_id, quantity_in_stock, quantity_reserved, reorder_level, reorder_quantity, created_at)
        SELECT p.id, 0, 0, 10, 50, NOW()
        FROM products p
        LEFT JOIN inventory i ON p.id = i.product_id
        WHERE i.id IS NULL AND p.deleted_at IS NULL
    ");
    $created = $stmt->rowCount();
    echo "   ✓ Created {$created} inventory records\n";

    // 4. Create inventory_adjustments table if not exists
    echo "\n4. Checking inventory_adjustments table...\n";
    $stmt = $db->query("
        CREATE TABLE IF NOT EXISTS inventory_adjustments (
            id INT PRIMARY KEY AUTO_INCREMENT,
            product_id INT NOT NULL,
            adjustment_type VARCHAR(20) NOT NULL,
            quantity INT NOT NULL,
            reason TEXT NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_product (product_id),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "   ✓ Inventory adjustments table ready\n";

    // 5. Update system settings for COD
    echo "\n5. Enabling Cash on Delivery...\n";
    $stmt = $db->prepare("
        UPDATE system_settings 
        SET setting_value = 'true' 
        WHERE setting_key = 'enable_cash_on_delivery'
    ");
    $stmt->execute();
    echo "   ✓ COD enabled\n";

    // 6. Verify delivery partners exist for admin assignment
    echo "\n6. Checking delivery partners...\n";
    $stmt = $db->query("SELECT COUNT(*) FROM delivery_partners WHERE is_available = 1");
    $partnerCount = $stmt->fetchColumn();
    echo "   ✓ Found {$partnerCount} active delivery partners\n";

    echo "\n=== Migration Complete ===\n";
    echo "\nNext steps:\n";
    echo "1. Import products with stock quantities\n";
    echo "2. Test placing a COD order\n";
    echo "3. Verify stock decrements correctly\n";
    echo "4. Test order cancellation restores stock\n";
    echo "5. Verify admin/delivery/user dashboard interconnection\n";

}
catch (Exception $e) {
    echo "\n❌ System Error: " . $e->getTraceAsString() . " \n";
    exit(1);
}