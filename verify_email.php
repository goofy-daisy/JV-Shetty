<?php
/**
 * Email Verification Page
 * Called via link: verify_email.php?token=xxxxx
 */

require_once __DIR__ . '/config/app.php';

$token   = trim($_GET['token'] ?? '');
$message = 'Invalid or missing verification token.';
$type    = 'danger';
$success = false;

if (!empty($token)) {
    $result  = Auth::verifyEmail($token);
    $message = $result['message'];
    $type    = $result['success'] ? 'success' : 'danger';
    $success = $result['success'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email - <?php echo APP_NAME; ?></title>
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
        .icon-circle {
            width: 80px; height: 80px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5 col-lg-4">
                <div class="card auth-card">
                    <div class="card-body p-5 text-center">
                        <?php if ($success): ?>
                            <div class="icon-circle bg-success text-white mb-4">
                                <i class="bi bi-check-lg"></i>
                            </div>
                            <h4 class="fw-bold text-success">Email Verified!</h4>
                            <p class="text-muted"><?php echo htmlspecialchars($message); ?></p>
                            <a href="login.php" class="btn btn-success w-100 mt-3">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Login to Your Account
                            </a>
                        <?php else: ?>
                            <div class="icon-circle bg-danger text-white mb-4">
                                <i class="bi bi-x-lg"></i>
                            </div>
                            <h4 class="fw-bold text-danger">Verification Failed</h4>
                            <p class="text-muted"><?php echo htmlspecialchars($message); ?></p>
                            <a href="register.php" class="btn btn-primary w-100 mt-2">Register Again</a>
                        <?php endif; ?>

                        <div class="mt-3">
                            <a href="index.php" class="text-decoration-none small text-muted">
                                <i class="bi bi-house me-1"></i>Back to Home
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
