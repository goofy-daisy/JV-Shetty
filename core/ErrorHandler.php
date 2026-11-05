<?php
/**
 * Centralized Error & Exception Handler
 *
 * Registers PHP's global exception + error + shutdown handlers.
 * - API requests → JSON error response
 * - Page requests → redirect to error.php
 *
 * Register early: ErrorHandler::register() in config/app.php
 */

class ErrorHandler {

    public static function register(): void {
        set_exception_handler([self::class, 'handleException']);
        set_error_handler([self::class, 'handleError']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    /**
     * Handle uncaught exceptions
     */
    public static function handleException(Throwable $e): void {
        $message = $e->getMessage();
        $trace   = $e->getFile() . ':' . $e->getLine();
        error_log('[Uncaught Exception] ' . get_class($e) . ': ' . $message . ' @ ' . $trace);

        if (self::isApiRequest()) {
            self::sendJsonError('An internal server error occurred.', 500);
        } else {
            self::redirectToError(500, 'An unexpected error occurred. Please try again.');
        }
    }

    /**
     * Handle PHP errors (converts to exceptions)
     */
    public static function handleError(int $severity, string $message, string $file, int $line): bool {
        // Only handle errors that are included in error_reporting
        if (!(error_reporting() & $severity)) {
            return false;
        }
        error_log('[PHP Error] ' . $message . ' in ' . $file . ':' . $line);
        throw new ErrorException($message, 0, $severity, $file, $line);
    }

    /**
     * Handle fatal errors in shutdown
     */
    public static function handleShutdown(): void {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            error_log('[Fatal Error] ' . $error['message'] . ' in ' . $error['file'] . ':' . $error['line']);

            if (self::isApiRequest()) {
                self::sendJsonError('A fatal server error occurred.', 500);
            } else {
                self::redirectToError(500, 'A critical error occurred. Our team has been notified.');
            }
        }
    }

    // ------------------------------------------------------------------ helpers

    private static function isApiRequest(): bool {
        $uri    = $_SERVER['REQUEST_URI']  ?? '';
        $accept = $_SERVER['HTTP_ACCEPT']  ?? '';
        $ctype  = $_SERVER['CONTENT_TYPE'] ?? '';
        return strpos($uri, '/api/')    !== false
            || strpos($accept, 'application/json') !== false
            || strpos($ctype,  'application/json') !== false;
    }

    private static function sendJsonError(string $message, int $code = 500): void {
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code($code);
        }
        echo json_encode(['success' => false, 'error' => $message, 'code' => $code]);
        exit;
    }

    private static function redirectToError(int $code, string $message): void {
        if (!headers_sent()) {
            http_response_code($code);
            $url = (defined('BASE_URL') ? BASE_URL : '') . '/error.php?code=' . $code
                 . '&message=' . urlencode($message);
            header('Location: ' . $url);
        }
        exit;
    }
}
