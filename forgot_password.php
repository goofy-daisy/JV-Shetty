<?php
/**
 * Forgot Password Page
 * Sends a secure password reset link to the user's campus email.
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/core/RateLimit.php';

if (Auth::check()) {
    header('Location: index.php');
    exit;
}

$message = '';
$type    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!Validator::validateCsrf($_POST['csrf_token'] ?? '')) {
        $message = 'Security token expired. Please try again.';
        $type    = 'danger';
    } else {
        // Rate limit — max 5 reset requests per hour per IP
        RateLimit::enforce('password_reset', 5, 3600);

        $email = trim($_POST['email'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please enter a valid email address.';
            $type    = 'danger';
        } else {
            $result  = Auth::requestPasswordReset($email);
            $message = $result['message'];
            $type    = $result['success'] ? 'success' : 'danger';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .auth-card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 16px 48px rgba(0,0,0,0.25);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5 col-lg-4">
                <div class="card auth-card">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width:60px;height:60px;">
                                <i class="bi bi-key fs-3"></i>
                            </div>
                            <h4 class="fw-bold"><?php echo APP_NAME; ?></h4>
                            <p class="text-muted small">Enter your campus email and we'll send you a reset link.</p>
                        </div>

                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $type; ?>">
                                <i class="bi bi-<?php echo $type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                                <?php echo htmlspecialchars($message); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($type !== 'success'): ?>
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo Validator::generateCsrf(); ?>">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Campus Email</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                    <input type="email" name="email" class="form-control"
                                           placeholder="your-id@goa.bits-pilani.ac.in"
                                           pattern=".*@goa\.bits-pilani\.ac\.in$"
                                           required autofocus>
                                </div>
                                <div class="form-text">Only @goa.bits-pilani.ac.in emails</div>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 mb-3">
                                <i class="bi bi-send me-2"></i>Send Reset Link
                            </button>
                        </form>
                        <?php endif; ?>

                        <div class="text-center">
                            <a href="login.php" class="text-decoration-none small text-muted">
                                <i class="bi bi-arrow-left me-1"></i>Back to Login
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
