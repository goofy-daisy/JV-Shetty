<?php
/**
 * Products API Endpoints
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../models/Product.php';

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

$product = new Product();

switch ($action) {
    case 'list':
        handleList($product);
        break;
        
    case 'detail':
        handleDetail($product);
        break;
        
    case 'featured':
        handleFeatured($product);
        break;
        
    case 'categories':
        handleCategories();
        break;
        
    case 'brands':
        handleBrands($product);
        break;
        
    case 'search':
        handleSearch($product);
        break;
        
    case 'related':
        handleRelated($product);
        break;
        
    case 'create':
        Auth::requirePermission('products.manage');
        handleCreate($product);
        break;
        
    case 'update':
        Auth::requirePermission('products.manage');
        handleUpdate($product);
        break;
        
    case 'delete':
        Auth::requirePermission('products.manage');
        handleDelete($product);
        break;
        
    default:
        Response::error('Invalid action', 404);
}

function handleList(Product $product): void {
    $filters = [
        'category_id' => $_GET['category'] ?? null,
        'subcategory_id' => $_GET['subcategory'] ?? null,
        'brand' => $_GET['brand'] ?? null,
        'search' => $_GET['search'] ?? null,
        'in_stock' => isset($_GET['in_stock']) ? true : null,
        'price_min' => $_GET['price_min'] ?? null,
        'price_max' => $_GET['price_max'] ?? null,
        'sort' => $_GET['sort'] ?? 'created_at',
        'order' => $_GET['order'] ?? 'DESC'
    ];
    
    $page = (int)($_GET['page'] ?? 1);
    $perPage = (int)($_GET['per_page'] ?? 20);
    
    $result = $product->getFiltered(array_filter($filters), $page, $perPage);
    Response::success($result);
}

function handleDetail(Product $product): void {
    $id = (int)($_GET['id'] ?? 0);
    
    if (!$id) {
        Response::error('Product ID required', 400);
    }
    
    $data = $product->getWithDetails($id);
    
    if (!$data) {
        Response::notFound();
    }
    
    Response::success($data);
}

function handleFeatured(Product $product): void {
    $limit = (int)($_GET['limit'] ?? 8);
    $data = $product->getFeatured($limit);
    Response::success(['products' => $data]);
}

function handleCategories(): void {
    $db = Database::getInstance();
    
    $stmt = $db->query("
        SELECT c.*, 
               COUNT(p.id) as product_count,
               (SELECT image_url FROM product_images 
                JOIN products ON product_images.product_id = products.id 
                WHERE products.category_id = c.id AND products.is_active = 1 
                ORDER BY products.created_at DESC LIMIT 1) as sample_image
        FROM categories c
        LEFT JOIN products p ON c.id = p.category_id AND p.is_active = 1 AND p.deleted_at IS NULL
        WHERE c.is_active = 1
        GROUP BY c.id
        ORDER BY c.display_order ASC
    ");
    
    $categories = $stmt->fetchAll();
    
    // Get subcategories
    foreach ($categories as &$category) {
        $stmt = $db->prepare("
            SELECT s.*, COUNT(p.id) as product_count
            FROM subcategories s
            LEFT JOIN products p ON s.id = p.subcategory_id AND p.is_active = 1 AND p.deleted_at IS NULL
            WHERE s.category_id = ? AND s.is_active = 1
            GROUP BY s.id
            ORDER BY s.display_order ASC
        ");
        $stmt->execute([$category['id']]);
        $category['subcategories'] = $stmt->fetchAll();
    }
    
    Response::success(['categories' => $categories]);
}

function handleBrands(Product $product): void {
    $data = $product->getBrands();
    Response::success(['brands' => $data]);
}

function handleSearch(Product $product): void {
    $query = $_GET['q'] ?? '';
    
    if (strlen($query) < 2) {
        Response::success(['products' => []]);
    }
    
    $result = $product->getFiltered(['search' => $query], 1, 20);
    Response::success($result);
}

function handleRelated(Product $product): void {
    $id = (int)($_GET['id'] ?? 0);
    $limit = (int)($_GET['limit'] ?? 4);
    
    if (!$id) {
        Response::error('Product ID required', 400);
    }
    
    $data = $product->getRelated($id, $limit);
    Response::success(['products' => $data]);
}

function handleCreate(Product $product): void {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $user = Auth::user();
    $input['created_by'] = $user['id'];
    $input['slug'] = createSlug($input['name']);
    
    try {
        $id = $product->createWithImages($input, $input['images'] ?? []);
        Response::success(['product_id' => $id], 'Product created successfully');
    } catch (Exception $e) {
        Response::error('Failed to create product: ' . $e->getMessage(), 500);
    }
}

function handleUpdate(Product $product): void {
    $id = (int)($_GET['id'] ?? 0);
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$id) {
        Response::error('Product ID required', 400);
    }
    
    if (!empty($input['name'])) {
        $input['slug'] = createSlug($input['name']);
    }
    
    try {
        $product->update($id, $input);
        Response::success([], 'Product updated successfully');
    } catch (Exception $e) {
        Response::error('Failed to update product: ' . $e->getMessage(), 500);
    }
}

function handleDelete(Product $product): void {
    $id = (int)($_GET['id'] ?? 0);
    
    if (!$id) {
        Response::error('Product ID required', 400);
    }
    
    try {
        $product->delete($id);
        Response::success([], 'Product deleted successfully');
    } catch (Exception $e) {
        Response::error('Failed to delete product: ' . $e->getMessage(), 500);
    }
}

function createSlug(string $string): string {
    $slug = strtolower(trim($string));
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    return trim($slug, '-');
}
