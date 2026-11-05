<?php
/**
 * Reset Password Page
 * Called via link: reset_password.php?token=xxxxx
 */

require_once __DIR__ . '/config/app.php';

if (Auth::check()) {
    header('Location: index.php');
    exit;
}

$token   = trim($_GET['token'] ?? '');
$message = '';
$type    = '';
$done    = false;

if (empty($token)) {
    $message = 'Invalid or missing reset token.';
    $type    = 'danger';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Validator::validateCsrf($_POST['csrf_token'] ?? '')) {
        $message = 'Security token expired. Please try again.';
        $type    = 'danger';
    } else {
        $token    = trim($_POST['token'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['password_confirm'] ?? '';

        $validator = new Validator();
        $errors    = [];

        if (!$validator->validate(['password' => $password, 'password_confirm' => $confirm], [
            'password'         => 'required|password',
            'password_confirm' => 'required|match:password'
        ])) {
            $message = $validator->firstError();
            $type    = 'danger';
        } else {
            $result  = Auth::resetPassword($token, $password);
            $message = $result['message'];
            $type    = $result['success'] ? 'success' : 'danger';
            $done    = $result['success'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - <?php echo APP_NAME; ?></title>
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
        .strength-bar { height: 5px; border-radius: 3px; transition: all 0.3s; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5 col-lg-4">
                <div class="card auth-card">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <div class="bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width:60px;height:60px;">
                                <i class="bi bi-shield-lock fs-3"></i>
                            </div>
                            <h4 class="fw-bold"><?php echo APP_NAME; ?></h4>
                            <p class="text-muted small">Choose a new password for your account.</p>
                        </div>

                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $type; ?>">
                                <i class="bi bi-<?php echo $type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                                <?php echo htmlspecialchars($message); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($done): ?>
                            <div class="text-center mt-3">
                                <a href="login.php" class="btn btn-primary w-100">
                                    <i class="bi bi-box-arrow-in-right me-2"></i>Login Now
                                </a>
                            </div>
                        <?php elseif (!empty($token) && $type !== 'danger'): ?>
                        <form method="POST" action="" id="resetForm">
                            <input type="hidden" name="csrf_token" value="<?php echo Validator::generateCsrf(); ?>">
                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                            <div class="mb-3">
                                <label class="form-label fw-semibold">New Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                    <input type="password" name="password" id="newPassword" class="form-control"
                                           minlength="8" required autofocus>
                                </div>
                                <div class="mt-1">
                                    <div class="strength-bar bg-secondary w-0" id="strengthBar"></div>
                                </div>
                                <div class="form-text" id="strengthText">Min 8 chars, uppercase, lowercase, number</div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold">Confirm Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                                    <input type="password" name="password_confirm" id="confirmPassword" class="form-control" required>
                                </div>
                                <div class="form-text text-danger d-none" id="matchError">Passwords do not match</div>
                            </div>

                            <button type="submit" class="btn btn-success w-100">
                                <i class="bi bi-check-circle me-2"></i>Set New Password
                            </button>
                        </form>
                        <?php endif; ?>

                        <div class="text-center mt-3">
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
    <script>
        // Password strength indicator
        const pwd = document.getElementById('newPassword');
        const bar = document.getElementById('strengthBar');
        const txt = document.getElementById('strengthText');
        const cfm = document.getElementById('confirmPassword');
        const matchErr = document.getElementById('matchError');

        if (pwd) {
            pwd.addEventListener('input', () => {
                const v = pwd.value;
                let score = 0;
                if (v.length >= 8) score++;
                if (/[A-Z]/.test(v)) score++;
                if (/[a-z]/.test(v)) score++;
                if (/\d/.test(v)) score++;
                if (/[^A-Za-z0-9]/.test(v)) score++;
                const labels = ['', 'Very Weak', 'Weak', 'Fair', 'Strong', 'Very Strong'];
                const colors = ['', '#dc3545', '#fd7e14', '#ffc107', '#28a745', '#155724'];
                bar.style.width   = (score / 5 * 100) + '%';
                bar.style.background = colors[score] || '#dee2e6';
                txt.textContent  = labels[score] || '';
            });

            cfm && cfm.addEventListener('input', () => {
                matchErr.classList.toggle('d-none', cfm.value === pwd.value);
            });
        }
    </script>
</body>
</html>
