<?php
/**
 * Auth API Endpoints
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../core/RateLimit.php';

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

switch ($action) {
    case 'register':
        if ($method !== 'POST') {
            Response::error('Method not allowed', 405);
        }
        handleRegister();
        break;
        
    case 'login':
        if ($method !== 'POST') {
            Response::error('Method not allowed', 405);
        }
        handleLogin();
        break;
        
    case 'logout':
        handleLogout();
        break;
        
    case 'me':
        handleMe();
        break;
        
    default:
        Response::error('Invalid action', 404);
}

function handleRegister(): void {
    // Rate limit: 5 register attempts per hour per IP
    if (RATE_LIMIT_ENABLED) RateLimit::enforce('register', 5, 3600);

    $input = json_decode(file_get_contents('php://input'), true);
    
    $validator = new Validator();
    $rules = [
        'email' => 'required|email|campus_email',
        'password' => 'required|password',
        'password_confirm' => 'required|match:password',
        'first_name' => 'required|min:2|max:50',
        'last_name' => 'required|min:2|max:50',
        'phone' => 'phone'
    ];
    
    if (!$validator->validate($input, $rules)) {
        Response::error('Validation failed', 422, $validator->errors());
    }
    
    $result = Auth::register([
        'email' => $input['email'],
        'password' => $input['password'],
        'first_name' => $input['first_name'],
        'last_name' => $input['last_name'],
        'phone' => $input['phone'] ?? null
    ]);
    
    if (!$result['success']) {
        Response::error($result['message'], 400);
    }
    
    Response::success(['user_id' => $result['user_id']], 'Registration successful');
}

function handleLogin(): void {
    // Rate limit: 10 login attempts per minute per IP
    if (RATE_LIMIT_ENABLED) RateLimit::enforce('login', 10, 60);

    $input = json_decode(file_get_contents('php://input'), true);
    
    $validator = new Validator();
    $rules = [
        'email' => 'required|email',
        'password' => 'required'
    ];
    
    if (!$validator->validate($input, $rules)) {
        Response::error('Validation failed', 422, $validator->errors());
    }
    
    $result = Auth::login($input['email'], $input['password']);
    
    if (!$result['success']) {
        Response::error($result['message'], 401);
    }
    
    Response::success([
        'user' => [
            'id' => $result['user']['id'],
            'email' => $result['user']['email'],
            'role' => $result['user']['role_name'],
            'first_name' => $result['user']['first_name'] ?? null,
            'last_name' => $result['user']['last_name'] ?? null
        ]
    ], 'Login successful');
}

function handleLogout(): void {
    Auth::logout();
    Response::success([], 'Logged out successfully');
}

function handleMe(): void {
    if (!Auth::check()) {
        Response::unauthorized();
    }
    
    $user = Auth::user();
    Response::success(['user' => $user]);
}
