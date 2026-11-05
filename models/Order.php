<?php
/**
 * Order Model
 */

require_once __DIR__ . '/../core/BaseModel.php';

class Order extends BaseModel {
    protected string $table = 'orders';
    protected array $fillable = [
        'order_number', 'user_id', 'status', 'fulfillment_type', 'address_id',
        'delivery_partner_id', 'pickup_slot_id', 'subtotal', 'tax_amount',
        'delivery_fee', 'discount_amount', 'total_amount', 'coupon_code',
        'notes', 'placed_at', 'confirmed_at', 'packed_at', 'assigned_at',
        'out_for_delivery_at', 'delivered_at', 'cancelled_at', 'cancellation_reason',
        'cancelled_by', 'ip_address', 'user_agent'
    ];
    
    /**
     * Create order from cart
     */
    public function createFromCart(int $userId, int $cartId, array $orderData): array {
        $db = Database::getInstance();
        
        try {
            $db->beginTransaction();
            
            // Validate store hours
            $storeStatus = StoreHours::validateCheckout();
            if (!$storeStatus['allowed']) {
                return ['success' => false, 'message' => $storeStatus['message']];
            }
            
            // Get cart with items
            $cart = new Cart();
            $cartData = $cart->getWithItems($cartId);
            
            if (!$cartData || empty($cartData['items'])) {
                return ['success' => false, 'message' => 'Cart is empty'];
            }
            
            // Validate stock
            $stockValidation = $cart->validateStock($cartId);
            if (!$stockValidation['valid']) {
                return [
                    'success' => false, 
                    'message' => 'Some items are out of stock',
                    'stock_errors' => $stockValidation['errors']
                ];
            }
            
            // Generate order number
            $orderNumber = $this->generateOrderNumber();
            
            // For COD orders, status is 'placed' immediately
            // For online payment, status would be 'payment_pending'
            $initialStatus = 'placed';
            
            // Create order
            $orderId = $this->create([
                'order_number' => $orderNumber,
                'user_id' => $userId,
                'status' => $initialStatus,
                'fulfillment_type' => $orderData['fulfillment_type'],
                'address_id' => $orderData['address_id'] ?? null,
                'pickup_slot_id' => $orderData['pickup_slot_id'] ?? null,
                'subtotal' => $cartData['subtotal'],
                'tax_amount' => $cartData['tax_amount'],
                'delivery_fee' => $cartData['delivery_fee'],
                'discount_amount' => $cartData['discount_amount'],
                'total_amount' => $cartData['total_amount'],
                'coupon_code' => $cartData['coupon_code'],
                'notes' => $orderData['notes'] ?? null,
                'placed_at' => date('Y-m-d H:i:s'),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            
            // Create order items and decrement stock
            foreach ($cartData['items'] as $item) {
                $stmt = $db->prepare("
                    INSERT INTO order_items 
                    (order_id, product_id, quantity, unit_price, total_price, tax_amount)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $orderId,
                    $item['product_id'],
                    $item['quantity'],
                    $item['unit_price'],
                    $item['total_price'],
                    $item['total_price'] * 0.05 // 5% tax
                ]);
                
                // Decrement stock immediately for COD orders
                $product = new Product();
                $product->decrementStock($item['product_id'], $item['quantity']);
            }
            
            // Log status change
            $this->logStatusChange($orderId, null, $initialStatus, $userId, 'Order placed (COD)');
            
            // Clear cart
            $cart->clear($cartId);
            
            $db->commit();
            
            return [
                'success' => true,
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'message' => 'Order created successfully'
            ];
            
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Order creation error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to create order'];
        }
    }
    
    /**
     * Get order with all details
     */
    public function getWithDetails(int $orderId): ?array {
        $stmt = $this->db->prepare("
            SELECT o.*, 
                   u.email as user_email,
                   p.first_name, p.last_name, p.phone as user_phone,
                   a.address_line1, a.address_line2, a.room_number,
                   h.name as hostel_name,
                   ps.slot_date as pickup_date, ps.start_time as pickup_start, ps.end_time as pickup_end,
                   dp.employee_id,
                   dp_user.email as delivery_email,
                   dp_profile.first_name as delivery_first_name,
                   dp_profile.last_name as delivery_last_name,
                   dp_profile.phone as delivery_phone
            FROM orders o
            JOIN users u ON o.user_id = u.id
            LEFT JOIN user_profiles p ON u.id = p.user_id
            LEFT JOIN addresses a ON o.address_id = a.id
            LEFT JOIN hostel_list h ON a.hostel_id = h.id
            LEFT JOIN pickup_slots ps ON o.pickup_slot_id = ps.id
            LEFT JOIN delivery_partners dp ON o.delivery_partner_id = dp.id
            LEFT JOIN users dp_user ON dp.user_id = dp_user.id
            LEFT JOIN user_profiles dp_profile ON dp_user.id = dp_profile.user_id
            WHERE o.id = ?
        ");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        
        if (!$order) {
            return null;
        }
        
        // Get items
        $order['items'] = $this->getItems($orderId);
        
        // Get status history
        $order['status_history'] = $this->getStatusHistory($orderId);
        
        // Get payment
        $order['payment'] = $this->getPayment($orderId);
        
        return $order;
    }
    
    /**
     * Get order items
     */
    public function getItems(int $orderId): array {
        $stmt = $this->db->prepare("
            SELECT oi.*, p.name, p.slug,
                   (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
        ");
        $stmt->execute([$orderId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get status history
     */
    public function getStatusHistory(int $orderId): array {
        $stmt = $this->db->prepare("
            SELECT osh.*, u.email as changed_by_email, p.first_name, p.last_name
            FROM order_status_history osh
            LEFT JOIN users u ON osh.changed_by = u.id
            LEFT JOIN user_profiles p ON u.id = p.user_id
            WHERE osh.order_id = ?
            ORDER BY osh.created_at ASC
        ");
        $stmt->execute([$orderId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get payment
     */
    public function getPayment(int $orderId): ?array {
        $stmt = $this->db->prepare("SELECT * FROM payments WHERE order_id = ? LIMIT 1");
        $stmt->execute([$orderId]);
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Update order status
     */
    public function updateStatus(int $orderId, string $newStatus, ?int $changedBy = null, string $notes = ''): array {
        $db = Database::getInstance();
        
        try {
            $order = $this->find($orderId);
            if (!$order) {
                return ['success' => false, 'message' => 'Order not found'];
            }
            
            $oldStatus = $order['status'];
            
            // Validate status transition
            $validTransitions = $this->getValidTransitions($oldStatus);
            if (!in_array($newStatus, $validTransitions)) {
                return [
                    'success' => false, 
                    'message' => "Cannot transition from {$oldStatus} to {$newStatus}"
                ];
            }
            
            $db->beginTransaction();
            
            // Update order status
            $timestampField = $this->getTimestampField($newStatus);
            $updateData = ['status' => $newStatus];
            if ($timestampField) {
                $updateData[$timestampField] = date('Y-m-d H:i:s');
            }
            
            $this->update($orderId, $updateData);
            
            // Log status change
            $this->logStatusChange($orderId, $oldStatus, $newStatus, $changedBy, $notes);
            
            // Handle status-specific actions
            $this->handleStatusActions($orderId, $newStatus, $order);
            
            $db->commit();
            
            return ['success' => true, 'message' => 'Status updated to ' . $newStatus];
            
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Status update error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update status'];
        }
    }
    
    /**
     * Cancel order
     */
    public function cancel(int $orderId, int $cancelledBy, string $reason): array {
        $order = $this->find($orderId);
        if (!$order) {
            return ['success' => false, 'message' => 'Order not found'];
        }
        
        // Check if order can be cancelled
        $cancellableStatuses = ['placed', 'created', 'payment_pending', 'paid', 'confirmed'];
        if (!in_array($order['status'], $cancellableStatuses)) {
            return ['success' => false, 'message' => 'Order cannot be cancelled at this stage'];
        }
        
        // Check cancellation window for customers
        $user = Auth::user();
        if ($user && $user['role_name'] === 'customer') {
            // Use placed_at (not created_at) for the cancellation window
            $placedAt = strtotime($order['placed_at'] ?: $order['created_at']);
            $minutesSince = (time() - $placedAt) / 60;
            $window = 300; // 5 minutes from settings
            
            if ($minutesSince > $window) {
                return ['success' => false, 'message' => 'Cancellation window has expired'];
            }
        }
        
        $db = Database::getInstance();
        
        try {
            $db->beginTransaction();
            
            // Restore stock for cancelled order
            $items = $this->getItems($orderId);
            $product = new Product();
            foreach ($items as $item) {
                $product->incrementStock($item['product_id'], $item['quantity']);
            }
            
            // Update order
            $this->update($orderId, [
                'status' => 'cancelled',
                'cancelled_at' => date('Y-m-d H:i:s'),
                'cancellation_reason' => $reason,
                'cancelled_by' => $cancelledBy
            ]);
            
            // Log status change
            $this->logStatusChange($orderId, $order['status'], 'cancelled', $cancelledBy, $reason);
            
            $db->commit();
            
            return ['success' => true, 'message' => 'Order cancelled'];
            
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Order cancel error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to cancel order'];
        }
    }
    
    /**
     * Assign delivery partner
     */
    public function assignDelivery(int $orderId, int $deliveryPartnerId, int $assignedBy): array {
        $db = Database::getInstance();
        
        try {
            // Check if delivery partner is available
            $stmt = $db->prepare("SELECT is_available FROM delivery_partners WHERE id = ?");
            $stmt->execute([$deliveryPartnerId]);
            $partner = $stmt->fetch();
            
            if (!$partner || !$partner['is_available']) {
                return ['success' => false, 'message' => 'Delivery partner not available'];
            }
            
            $db->beginTransaction();
            
            // Update order
            $this->update($orderId, [
                'delivery_partner_id' => $deliveryPartnerId,
                'status' => 'assigned',
                'assigned_at' => date('Y-m-d H:i:s')
            ]);
            
            // Create delivery assignment
            $stmt = $db->prepare("
                INSERT INTO delivery_assignments (order_id, delivery_partner_id, assigned_by)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$orderId, $deliveryPartnerId, $assignedBy]);
            
            // Log status change
            $this->logStatusChange($orderId, 'packed', 'assigned', $assignedBy);
            
            $db->commit();
            
            return ['success' => true, 'message' => 'Delivery partner assigned'];
            
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Delivery assign error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to assign delivery partner'];
        }
    }
    
    /**
     * Get orders for user
     */
    public function getForUser(int $userId, int $page = 1, int $perPage = 10): array {
        $offset = ($page - 1) * $perPage;
        
        $stmt = $this->db->prepare("
            SELECT o.*, 
                   (SELECT COUNT(*) FROM orders WHERE user_id = ?) as total,
                   a.address_line1, h.name as hostel_name
            FROM orders o
            LEFT JOIN addresses a ON o.address_id = a.id
            LEFT JOIN hostel_list h ON a.hostel_id = h.id
            WHERE o.user_id = ?
            ORDER BY o.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$userId, $userId, $perPage, $offset]);
        $orders = $stmt->fetchAll();
        
        $total = $orders[0]['total'] ?? 0;
        
        return [
            'data' => $orders,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => ceil($total / $perPage)
        ];
    }
    
    /**
     * Get orders for delivery partner
     */
    public function getForDeliveryPartner(int $partnerId, string $status = null): array {
        $sql = "
            SELECT o.*, 
                   u.email as user_email,
                   p.first_name, p.last_name, p.phone as user_phone,
                   a.address_line1, a.room_number, h.name as hostel_name
            FROM orders o
            JOIN users u ON o.user_id = u.id
            LEFT JOIN user_profiles p ON u.id = p.user_id
            LEFT JOIN addresses a ON o.address_id = a.id
            LEFT JOIN hostel_list h ON a.hostel_id = h.id
            WHERE o.delivery_partner_id = ?
        ";
        $params = [$partnerId];
        
        if ($status) {
            $sql .= " AND o.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY o.assigned_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get all orders (for admin)
     */
    public function getAllFiltered(array $filters = [], int $page = 1, int $perPage = 20): array {
        $where = ['1=1'];
        $params = [];
        
        if (!empty($filters['status'])) {
            $where[] = 'o.status = ?';
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['user_id'])) {
            $where[] = 'o.user_id = ?';
            $params[] = $filters['user_id'];
        }
        
        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(o.created_at) >= ?';
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(o.created_at) <= ?';
            $params[] = $filters['date_to'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = '(o.order_number LIKE ? OR u.email LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ?)';
            $search = "%{$filters['search']}%";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        $whereClause = implode(' AND ', $where);
        
        // Get count
        $countSql = "
            SELECT COUNT(*) FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
            LEFT JOIN user_profiles p ON u.id = p.user_id
            WHERE {$whereClause}
        ";
        $stmt = $this->db->prepare($countSql);
        $stmt->execute($params);
        $total = $stmt->fetchColumn();
        
        // Get orders
        $sql = "
            SELECT o.*, u.email as user_email, p.first_name, p.last_name,
                   h.name as hostel_name
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
            LEFT JOIN user_profiles p ON u.id = p.user_id
            LEFT JOIN addresses a ON o.address_id = a.id
            LEFT JOIN hostel_list h ON a.hostel_id = h.id
            WHERE {$whereClause}
            ORDER BY o.created_at DESC
            LIMIT ? OFFSET ?
        ";
        $params[] = $perPage;
        $params[] = ($page - 1) * $perPage;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll();
        
        return [
            'data' => $orders,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => ceil($total / $perPage)
        ];
    }
    
    /**
     * Generate order number
     */
    private function generateOrderNumber(): string {
        $prefix = 'JVS';
        $date = date('Ymd');
        $random = strtoupper(substr(uniqid(), -4));
        return "{$prefix}-{$date}-{$random}";
    }
    
    /**
     * Log status change
     */
    private function logStatusChange(int $orderId, ?string $from, string $to, ?int $changedBy, string $notes = ''): void {
        $stmt = $this->db->prepare("
            INSERT INTO order_status_history (order_id, status_from, status_to, changed_by, notes)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$orderId, $from, $to, $changedBy, $notes]);
    }
    
    /**
     * Get valid status transitions
     */
    private function getValidTransitions(string $currentStatus): array {
        $transitions = [
            'placed' => ['confirmed', 'cancelled'],
            'created' => ['payment_pending', 'cancelled'],
            'payment_pending' => ['paid', 'cancelled'],
            'paid' => ['confirmed', 'refunded'],
            'confirmed' => ['packed', 'cancelled'],
            'packed' => ['assigned'],
            'assigned' => ['out_for_delivery'],
            'out_for_delivery' => ['delivered', 'picked_up'],
            'delivered' => [],
            'picked_up' => [],
            'cancelled' => ['refunded'],
            'refunded' => []
        ];
        
        return $transitions[$currentStatus] ?? [];
    }
    
    /**
     * Get timestamp field for status
     */
    private function getTimestampField(string $status): ?string {
        $fields = [
            'paid' => 'paid_at',
            'confirmed' => 'confirmed_at',
            'packed' => 'packed_at',
            'assigned' => 'assigned_at',
            'out_for_delivery' => 'out_for_delivery_at',
            'delivered' => 'delivered_at',
            'picked_up' => 'delivered_at',
            'cancelled' => 'cancelled_at'
        ];
        
        return $fields[$status] ?? null;
    }
    
    /**
     * Handle actions for specific statuses
     */
    private function handleStatusActions(int $orderId, string $status, array $order): void {
        switch ($status) {
            case 'confirmed':
                // TODO: Send notification to user
                break;
            case 'packed':
                // TODO: Send packing notification
                break;
            case 'assigned':
                // TODO: Notify delivery partner
                break;
            case 'delivered':
            case 'picked_up':
                // TODO: Mark as complete, send notification
                break;
            case 'cancelled':
                // NOTE: Do NOT restore stock here.
                // Stock is already restored in Order::cancel() which
                // calls this method. Restoring here again would double-restore.
                break;
        }
    }
}
