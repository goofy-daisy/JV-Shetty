

<?php
/**
 * Product Model
 */


require_once __DIR__ . '/../core/BaseModel.php';

class Product extends BaseModel {
    protected string $table = 'products';
    protected array $fillable = [
        'name', 'slug', 'description', 'short_description', 'sku', 
        'category_id', 'subcategory_id', 'base_price', 'sale_price',
        'cost_price', 'tax_rate', 'weight_kg', 'brand', 'manufacturer',
        'is_veg', 'allergens', 'nutritional_info', 'barcode',
        'is_featured', 'is_active', 'meta_title', 'meta_description', 'created_by'
    ];
    protected bool $softDeletes = true;
    
    /**
     * Get product with inventory and images
     */
    public function getWithDetails(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT p.*, c.name as category_name, c.slug as category_slug,
                   s.name as subcategory_name, s.slug as subcategory_slug,
                   i.quantity_in_stock, i.quantity_reserved, i.quantity_available,
                   i.reorder_level, i.expiry_date
            FROM products p
            JOIN categories c ON p.category_id = c.id
            LEFT JOIN subcategories s ON p.subcategory_id = s.id
            LEFT JOIN inventory i ON p.id = i.product_id
            WHERE p.id = ? AND p.deleted_at IS NULL AND p.is_active = 1
        ");
        $stmt->execute([$id]);
        $product = $stmt->fetch();
        
        if (!$product) {
            return null;
        }
        
        // Get images
        $product['images'] = $this->getImages($id);
        
        return $product;
    }
    
    /**
     * Get product images
     */
    public function getImages(int $productId): array {
        $stmt = $this->db->prepare("
            SELECT * FROM product_images 
            WHERE product_id = ? 
            ORDER BY is_primary DESC, display_order ASC
        ");
        $stmt->execute([$productId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get products with filters
     */
    public function getFiltered(array $filters = [], int $page = 1, int $perPage = 20): array {
        $where = ['p.deleted_at IS NULL', 'p.is_active = 1'];
        $params = [];
        
        if (!empty($filters['category_id'])) {
            $where[] = 'p.category_id = ?';
            $params[] = $filters['category_id'];
        }
        
        if (!empty($filters['subcategory_id'])) {
            $where[] = 'p.subcategory_id = ?';
            $params[] = $filters['subcategory_id'];
        }
        
        if (!empty($filters['brand'])) {
            $where[] = 'p.brand = ?';
            $params[] = $filters['brand'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = '(p.name LIKE ? OR p.description LIKE ? OR p.sku LIKE ?)';
            $search = "%{$filters['search']}%";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        if (isset($filters['in_stock']) && $filters['in_stock']) {
            $where[] = 'i.quantity_available > 0';
        }
        
        if (!empty($filters['price_min'])) {
            $where[] = 'COALESCE(p.sale_price, p.base_price) >= ?';
            $params[] = $filters['price_min'];
        }
        
        if (!empty($filters['price_max'])) {
            $where[] = 'COALESCE(p.sale_price, p.base_price) <= ?';
            $params[] = $filters['price_max'];
        }
        
        $whereClause = implode(' AND ', $where);
        
        // Get total count
        $countSql = "SELECT COUNT(*) FROM products p LEFT JOIN inventory i ON p.id = i.product_id WHERE {$whereClause}";
        $stmt = $this->db->prepare($countSql);
        $stmt->execute($params);
        $total = $stmt->fetchColumn();
        
        // Get products
        $sql = "
            SELECT p.*, c.name as category_name, c.slug as category_slug,
                   i.quantity_available as stock,
                   (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
            FROM products p
            JOIN categories c ON p.category_id = c.id
            LEFT JOIN inventory i ON p.id = i.product_id
            WHERE {$whereClause}
        ";
        
        // Sorting
        $sortField = $filters['sort'] ?? 'created_at';
        $sortOrder = $filters['order'] ?? 'DESC';
        $allowedSort = ['name', 'base_price', 'created_at'];
        
        if (in_array($sortField, $allowedSort)) {
            $sql .= " ORDER BY p.{$sortField} {$sortOrder}";
        } else {
            $sql .= " ORDER BY p.created_at DESC";
        }
        
        // Pagination
        $offset = ($page - 1) * $perPage;
        $sql .= " LIMIT {$perPage} OFFSET {$offset}";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll();
        
        return [
            'data' => $products,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => ceil($total / $perPage)
        ];
    }
    
    /**
     * Get featured products
     */
    public function getFeatured(int $limit = 8): array {
        $stmt = $this->db->prepare("
            SELECT p.*, c.name as category_name,
                (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image,
                COALESCE(i.quantity_in_stock, 0) as stock
            FROM products p
            JOIN categories c ON p.category_id = c.id
            LEFT JOIN inventory i ON p.id = i.product_id
            WHERE p.is_featured = 1 
            AND p.is_active = 1 
            AND p.deleted_at IS NULL
            AND COALESCE(i.quantity_in_stock, 0) > 0
            ORDER BY p.created_at DESC
            LIMIT ?
        ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}
    
    /**
     * Get related products
     */
    public function getRelated(int $productId, int $limit = 4): array {
        $product = $this->find($productId);
        if (!$product) {
            return [];
        }
        
        $stmt = $this->db->prepare("
            SELECT p.*, c.name as category_name,
                   (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image,
                   i.quantity_available as stock
            FROM products p
            JOIN categories c ON p.category_id = c.id
            LEFT JOIN inventory i ON p.id = i.product_id
            WHERE p.category_id = ? AND p.id != ? 
            AND p.is_active = 1 AND p.deleted_at IS NULL
            AND i.quantity_available > 0
            ORDER BY RAND()
            LIMIT ?
        ");
        $stmt->execute([$product['category_id'], $productId, $limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get all brands
     */
    public function getBrands(): array {
        $stmt = $this->db->query("
            SELECT DISTINCT brand, COUNT(*) as product_count
            FROM products
            WHERE brand IS NOT NULL AND is_active = 1 AND deleted_at IS NULL
            GROUP BY brand
            ORDER BY brand
        ");
        return $stmt->fetchAll();
    }
    
    /**
     * Get product stock quantity (helper for backward compatibility)
     */
    public function getStock(int $productId): int {
        $stmt = $this->db->prepare("
            SELECT quantity_available 
            FROM inventory 
            WHERE product_id = ?
        ");
        $stmt->execute([$productId]);
        $stock = $stmt->fetchColumn();
        return $stock !== false ? (int)$stock : 0;
    }
    
    /**
     * Get product stock status
     */
    public function getStockStatus(int $productId): string {
        $stock = $this->getStock($productId);
        $stmt = $this->db->prepare("SELECT reorder_level FROM inventory WHERE product_id = ?");
        $stmt->execute([$productId]);
        $reorderLevel = $stmt->fetchColumn() ?: 10;
        
        if ($stock <= 0) {
            return 'Out of Stock';
        } elseif ($stock <= $reorderLevel) {
            return 'Low Stock';
        }
        return 'In Stock';
    }
    
    /**
     * Decrement stock when order placed
     */
    public function decrementStock(int $productId, int $quantity): bool {
        $stmt = $this->db->prepare("
            UPDATE inventory 
            SET quantity_in_stock = GREATEST(0, quantity_in_stock - ?),
                last_stock_update = NOW()
            WHERE product_id = ? AND quantity_in_stock >= ?
        ");
        $stmt->execute([$quantity, $productId, $quantity]);
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Increment stock when order cancelled
     */
    public function incrementStock(int $productId, int $quantity): bool {
        $stmt = $this->db->prepare("
            UPDATE inventory 
            SET quantity_in_stock = quantity_in_stock + ?,
                last_stock_update = NOW()
            WHERE product_id = ?
        ");
        $stmt->execute([$quantity, $productId]);
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Set stock quantity directly
     */
    public function setStock(int $productId, int $quantity): bool {
        $stmt = $this->db->prepare("
            INSERT INTO inventory (product_id, quantity_in_stock, quantity_reserved, reorder_level, last_stock_update, created_at)
            VALUES (?, ?, 0, 10, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
            quantity_in_stock = VALUES(quantity_in_stock),
            last_stock_update = NOW()
        ");
        $stmt->execute([$productId, $quantity]);
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Get or create inventory record for product
     */
    public function ensureInventory(int $productId): bool {
        $stmt = $this->db->prepare("SELECT id FROM inventory WHERE product_id = ?");
        $stmt->execute([$productId]);
        if (!$stmt->fetch()) {
            $stmt = $this->db->prepare("
                INSERT INTO inventory (product_id, quantity_in_stock, quantity_reserved, reorder_level, reorder_quantity, last_stock_update, created_at)
                VALUES (?, 0, 0, 10, 50, NOW(), NOW())
            ");
            $stmt->execute([$productId]);
            return true;
        }
        return false;
    }
    
    /**
     * Reserve stock
     */
    public function reserveStock(int $productId, int $quantity): bool {
        $stmt = $this->db->prepare("
            UPDATE inventory 
            SET quantity_reserved = quantity_reserved + ?
            WHERE product_id = ? AND quantity_available >= ?
        ");
        $stmt->execute([$quantity, $productId, $quantity]);
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Release reserved stock
     */
    public function releaseStock(int $productId, int $quantity): bool {
        $stmt = $this->db->prepare("
            UPDATE inventory 
            SET quantity_reserved = GREATEST(0, quantity_reserved - ?)
            WHERE product_id = ?
        ");
        $stmt->execute([$quantity, $productId]);
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Update stock
     */
    public function updateStock(int $productId, int $quantity, ?string $expiryDate = null): bool {
        $stmt = $this->db->prepare("
            INSERT INTO inventory (product_id, quantity_in_stock, expiry_date)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
            quantity_in_stock = VALUES(quantity_in_stock),
            expiry_date = COALESCE(VALUES(expiry_date), expiry_date),
            last_stock_update = NOW()
        ");
        $stmt->execute([$productId, $quantity, $expiryDate]);
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Get low stock products
     */
    public function getLowStock(): array {
        $stmt = $this->db->query("
            SELECT p.*, i.quantity_in_stock, i.quantity_reserved, i.reorder_level
            FROM products p
            JOIN inventory i ON p.id = i.product_id
            WHERE i.quantity_available <= i.reorder_level
            AND p.is_active = 1 AND p.deleted_at IS NULL
            ORDER BY i.quantity_available ASC
        ");
        return $stmt->fetchAll();
    }
    
    /**
     * Create product with images
     */
    public function createWithImages(array $productData, array $images = []): int {
        try {
            $this->db->beginTransaction();
            
            $productId = $this->create($productData);
            
            // Create inventory record
            $stmt = $this->db->prepare("
                INSERT INTO inventory (product_id, quantity_in_stock)
                VALUES (?, 0)
            ");
            $stmt->execute([$productId]);
            
            // Add images
            foreach ($images as $index => $image) {
                $stmt = $this->db->prepare("
                    INSERT INTO product_images (product_id, image_url, alt_text, is_primary, display_order)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $productId,
                    $image['url'],
                    $image['alt'] ?? $productData['name'],
                    $index === 0 ? 1 : 0,
                    $index
                ]);
            }
            
            $this->db->commit();
            return $productId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
