<?php
/**
 * API Response Helper
 */

class Response {
    
    public static function json(array $data, int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }
    
    public static function success(array $data = [], string $message = 'Success'): void {
        self::json(['success' => true, 'message' => $message, 'data' => $data]);
    }
    
    public static function error(string $message, int $statusCode = 400, array $errors = []): void {
        $response = ['success' => false, 'message' => $message];
        if (!empty($errors)) {
            $response['errors'] = $errors;
        }
        self::json($response, $statusCode);
    }
    
    public static function unauthorized(): void {
        self::error('Unauthorized', 401);
    }
    
    public static function forbidden(): void {
        self::error('Forbidden', 403);
    }
    
    public static function notFound(): void {
        self::error('Not found', 404);
    }
}
