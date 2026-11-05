<?php
/**
 * Error Pages
 */

// 404 Error Page
function show404() {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Page Not Found - <?php echo APP_NAME; ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    </head>
    <body class="bg-light">
        <div class="container vh-100 d-flex align-items-center justify-content-center">
            <div class="text-center">
                <div class="display-1 text-primary mb-4">
                    <i class="bi bi-emoji-frown"></i>
                </div>
                <h1 class="display-4 mb-3">404 - Page Not Found</h1>
                <p class="lead text-muted mb-4">The page you're looking for doesn't exist or has been moved.</p>
                <a href="index.php" class="btn btn-primary btn-lg">
                    <i class="bi bi-house me-2"></i>Go Home
                </a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 403 Error Page
function show403() {
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Access Denied - <?php echo APP_NAME; ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    </head>
    <body class="bg-light">
        <div class="container vh-100 d-flex align-items-center justify-content-center">
            <div class="text-center">
                <div class="display-1 text-danger mb-4">
                    <i class="bi bi-shield-exclamation"></i>
                </div>
                <h1 class="display-4 mb-3">403 - Access Denied</h1>
                <p class="lead text-muted mb-4">You don't have permission to access this page.</p>
                <div class="d-flex gap-2 justify-content-center">
                    <a href="javascript:history.back()" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-2"></i>Go Back
                    </a>
                    <a href="index.php" class="btn btn-primary">
                        <i class="bi bi-house me-2"></i>Go Home
                    </a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 500 Error Page
function show500() {
    http_response_code(500);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Server Error - <?php echo APP_NAME; ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    </head>
    <body class="bg-light">
        <div class="container vh-100 d-flex align-items-center justify-content-center">
            <div class="text-center">
                <div class="display-1 text-warning mb-4">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>
                <h1 class="display-4 mb-3">500 - Server Error</h1>
                <p class="lead text-muted mb-4">Something went wrong on our end. Please try again later.</p>
                <div class="d-flex gap-2 justify-content-center">
                    <button onclick="location.reload()" class="btn btn-outline-primary">
                        <i class="bi bi-arrow-clockwise me-2"></i>Try Again
                    </button>
                    <a href="index.php" class="btn btn-primary">
                        <i class="bi bi-house me-2"></i>Go Home
                    </a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Handle error based on request
$errorType = $_GET['error'] ?? '';
switch ($errorType) {
    case '404':
        show404();
        break;
    case '403':
        show403();
        break;
    case '500':
        show500();
        break;
    default:
        show404();
}
?>
