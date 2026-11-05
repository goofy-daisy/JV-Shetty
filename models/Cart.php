<?php
/**
 * Cart Model
 */

require_once __DIR__ . '/../core/BaseModel.php';

class Cart extends BaseModel {
    protected string $table = 'carts';
    protected array $fillable = ['user_id', 'session_id', 'coupon_code', 'discount_amount', 'subtotal', 'tax_amount', 'delivery_fee', 'total_amount'];

    /**
     * Get or create cart for user
     */
    public function getOrCreate(int $userId): array {
        $cart = $this->findBy('user_id', $userId);

        if (!$cart) {
            $cartId = $this->create([
                'user_id'    => $userId,
                'session_id' => session_id()
            ]);
            $cart = $this->find($cartId);
        }

        return $cart;
    }

    /**
     * Get cart with items
     */
    public function getWithItems(int $cartId): ?array {
        $cart = $this->find($cartId);
        if (!$cart) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT ci.*, p.name, p.slug, p.base_price, p.sale_price,
                   (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image,
                   i.quantity_available as stock
            FROM cart_items ci
            JOIN products p ON ci.product_id = p.id
            LEFT JOIN inventory i ON p.id = i.product_id
            WHERE ci.cart_id = ?
            ORDER BY ci.created_at DESC
        ");
        $stmt->execute([$cartId]);
        $cart['items'] = $stmt->fetchAll();

        $this->recalculate($cartId);

        return $this->find($cartId);
    }

    /**
     * Add item to cart
     * FIX: checkStock() does not exist on Product — use getStock() instead
     */
    public function addItem(int $cartId, int $productId, int $quantity, string $notes = ''): array {
        try {
            $this->db->beginTransaction();

            $product     = new Product();
            $productData = $product->find($productId);

            if (!$productData || !$productData['is_active']) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Product not available'];
            }

            // Use getStock() — the method that actually exists
            $availableStock = $product->getStock($productId);
            if ($availableStock < $quantity) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Insufficient stock'];
            }

            $unitPrice = !empty($productData['sale_price']) ? $productData['sale_price'] : $productData['base_price'];

            // Check if item already in cart
            $stmt = $this->db->prepare("
                SELECT id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ?
            ");
            $stmt->execute([$cartId, $productId]);
            $existing = $stmt->fetch();

            if ($existing) {
                $newQuantity = $existing['quantity'] + $quantity;
                // Re-check stock for combined quantity
                if ($availableStock < $newQuantity) {
                    $this->db->rollBack();
                    return ['success' => false, 'message' => 'Insufficient stock'];
                }
                $stmt = $this->db->prepare("
                    UPDATE cart_items
                    SET quantity = ?, total_price = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$newQuantity, $newQuantity * $unitPrice, $existing['id']]);
            } else {
                $stmt = $this->db->prepare("
                    INSERT INTO cart_items (cart_id, product_id, quantity, unit_price, total_price, notes)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$cartId, $productId, $quantity, $unitPrice, $quantity * $unitPrice, $notes]);
            }

            $this->db->commit();
            $this->recalculate($cartId);

            return ['success' => true, 'message' => 'Item added to cart'];
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Cart add error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to add item'];
        }
    }

    /**
     * Update item quantity
     */
    public function updateItem(int $itemId, int $quantity): array {
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                SELECT ci.*, p.base_price, p.sale_price, i.quantity_available as stock
                FROM cart_items ci
                JOIN products p ON ci.product_id = p.id
                LEFT JOIN inventory i ON p.id = i.product_id
                WHERE ci.id = ?
            ");
            $stmt->execute([$itemId]);
            $item = $stmt->fetch();

            if (!$item) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Item not found'];
            }

            if ($quantity <= 0) {
                $stmt = $this->db->prepare("DELETE FROM cart_items WHERE id = ?");
                $stmt->execute([$itemId]);
            } else {
                if ($item['stock'] < $quantity) {
                    $this->db->rollBack();
                    return ['success' => false, 'message' => 'Insufficient stock'];
                }
                $unitPrice = !empty($item['sale_price']) ? $item['sale_price'] : $item['base_price'];
                $stmt = $this->db->prepare("
                    UPDATE cart_items
                    SET quantity = ?, total_price = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$quantity, $quantity * $unitPrice, $itemId]);
            }

            $this->db->commit();
            $this->recalculate($item['cart_id']);

            return ['success' => true, 'message' => 'Cart updated'];
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Cart update error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update cart'];
        }
    }

    /**
     * Remove item from cart
     */
    public function removeItem(int $itemId): array {
        try {
            $stmt = $this->db->prepare("SELECT cart_id FROM cart_items WHERE id = ?");
            $stmt->execute([$itemId]);
            $item = $stmt->fetch();

            if (!$item) {
                return ['success' => false, 'message' => 'Item not found'];
            }

            $stmt = $this->db->prepare("DELETE FROM cart_items WHERE id = ?");
            $stmt->execute([$itemId]);

            $this->recalculate($item['cart_id']);

            return ['success' => true, 'message' => 'Item removed'];
        } catch (Exception $e) {
            error_log("Cart remove error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to remove item'];
        }
    }

    /**
     * Clear cart
     */
    public function clear(int $cartId): bool {
        try {
            $stmt = $this->db->prepare("DELETE FROM cart_items WHERE cart_id = ?");
            $stmt->execute([$cartId]);

            $this->update($cartId, [
                'subtotal'        => 0,
                'tax_amount'      => 0,
                'delivery_fee'    => 0,
                'discount_amount' => 0,
                'total_amount'    => 0,
                'coupon_code'     => null
            ]);

            return true;
        } catch (Exception $e) {
            error_log("Cart clear error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Recalculate cart totals from system_settings
     */
    private function recalculate(int $cartId): void {
        $stmt = $this->db->prepare("
            SELECT SUM(total_price) as subtotal FROM cart_items WHERE cart_id = ?
        ");
        $stmt->execute([$cartId]);
        $subtotal = (float)($stmt->fetchColumn() ?: 0);

        $settingsStmt = $this->db->query("
            SELECT setting_key, setting_value
            FROM system_settings
            WHERE setting_key IN ('delivery_fee', 'free_delivery_threshold', 'tax_rate')
        ");
        $settings = [];
        foreach ($settingsStmt->fetchAll() as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        $deliveryFee   = (float)($settings['delivery_fee'] ?? 20.00);
        $freeThreshold = (float)($settings['free_delivery_threshold'] ?? 500.00);
        $taxRate       = (float)($settings['tax_rate'] ?? 0.00);

        if ($subtotal >= $freeThreshold) {
            $deliveryFee = 0;
        }

        $taxAmount = $subtotal * $taxRate;
        $cart      = $this->find($cartId);
        $discount  = 0;

        $total = $subtotal + $taxAmount + $deliveryFee - $discount;

        $this->update($cartId, [
            'subtotal'        => $subtotal,
            'tax_amount'      => $taxAmount,
            'delivery_fee'    => $deliveryFee,
            'discount_amount' => $discount,
            'total_amount'    => $total
        ]);
    }

    /**
     * Get item count
     */
    public function getItemCount(int $cartId): int {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM cart_items WHERE cart_id = ?
        ");
        $stmt->execute([$cartId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Validate stock for all items in cart
     */
    public function validateStock(int $cartId): array {
        $stmt = $this->db->prepare("
            SELECT ci.id, ci.quantity, p.name, i.quantity_available as stock
            FROM cart_items ci
            JOIN products p ON ci.product_id = p.id
            LEFT JOIN inventory i ON p.id = i.product_id
            WHERE ci.cart_id = ?
        ");
        $stmt->execute([$cartId]);
        $items = $stmt->fetchAll();

        $errors = [];
        foreach ($items as $item) {
            if (($item['stock'] ?? 0) < $item['quantity']) {
                $errors[] = [
                    'item_id'      => $item['id'],
                    'product_name' => $item['name'],
                    'requested'    => $item['quantity'],
                    'available'    => $item['stock'] ?? 0
                ];
            }
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    /**
     * Transfer session cart to user cart
     */
    public function transferSessionCart(string $sessionId, int $userId): void {
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("SELECT id FROM carts WHERE session_id = ? AND user_id IS NULL");
            $stmt->execute([$sessionId]);
            $sessionCart = $stmt->fetch();

            if (!$sessionCart) {
                $this->db->commit();
                return;
            }

            $userCart = $this->getOrCreate($userId);

            $stmt = $this->db->prepare("SELECT * FROM cart_items WHERE cart_id = ?");
            $stmt->execute([$sessionCart['id']]);
            $items = $stmt->fetchAll();

            foreach ($items as $item) {
                $stmt = $this->db->prepare("
                    SELECT id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ?
                ");
                $stmt->execute([$userCart['id'], $item['product_id']]);
                $existing = $stmt->fetch();

                if ($existing) {
                    $stmt = $this->db->prepare("
                        UPDATE cart_items
                        SET quantity = quantity + ?, total_price = (quantity + ?) * unit_price
                        WHERE id = ?
                    ");
                    $stmt->execute([$item['quantity'], $item['quantity'], $existing['id']]);
                } else {
                    $stmt = $this->db->prepare("
                        INSERT INTO cart_items (cart_id, product_id, quantity, unit_price, total_price, notes)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $userCart['id'],
                        $item['product_id'],
                        $item['quantity'],
                        $item['unit_price'],
                        $item['total_price'],
                        $item['notes']
                    ]);
                }
            }

            $stmt = $this->db->prepare("DELETE FROM cart_items WHERE cart_id = ?");
            $stmt->execute([$sessionCart['id']]);
            $stmt = $this->db->prepare("DELETE FROM carts WHERE id = ?");
            $stmt->execute([$sessionCart['id']]);

            $this->db->commit();
            $this->recalculate($userCart['id']);

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Cart transfer error: " . $e->getMessage());
        }
    }
}
