<?php
/**
 * Contact Page
 */
require_once __DIR__ . '/config/app.php';

$sent = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($name) || empty($email) || empty($message)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Send via EmailService (uses SMTP if configured, falls back to mail())
        $emailService = EmailService::getInstance();
        $emailService->sendContactMessage($name, $email, $subject, $message);
        $sent = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php"><i class="bi bi-shop me-2"></i><?php echo APP_NAME; ?></a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="products.php">Products</a></li>
                    <li class="nav-item"><a class="nav-link active" href="contact.php">Contact</a></li>
                </ul>
                <div class="d-flex gap-2">
                    <?php if (Auth::check()): ?>
                        <a href="profile.php" class="btn btn-outline-light btn-sm"><i class="bi bi-person-circle me-1"></i><?php echo htmlspecialchars(Auth::user()['first_name'] ?? 'User'); ?></a>
                        <a href="logout.php" class="btn btn-outline-danger btn-sm">Logout</a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-primary btn-sm">Login</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <div style="margin-top: 56px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 60px 0;" class="text-white">
        <div class="container">
            <h1 class="fw-bold"><i class="bi bi-envelope me-2"></i>Contact Us</h1>
            <p class="lead mb-0">Get in touch with the JV Shetty team</p>
        </div>
    </div>

    <section class="py-5">
        <div class="container">
            <div class="row g-5">
                <div class="col-lg-5">
                    <h3 class="fw-bold mb-4">Get In Touch</h3>
                    <div class="d-flex align-items-start gap-3 mb-4">
                        <i class="bi bi-geo-alt-fill text-primary fs-4 mt-1"></i>
                        <div>
                            <strong>Location</strong>
                            <p class="text-muted mb-0">JV Shetty Store, BITS Pilani Goa Campus, Zuarinagar, Goa - 403726</p>
                        </div>
                    </div>
                    <div class="d-flex align-items-start gap-3 mb-4">
                        <i class="bi bi-clock-fill text-primary fs-4 mt-1"></i>
                        <div>
                            <strong>Store Hours</strong>
                            <p class="text-muted mb-0">Monday – Sunday: 4:00 PM – 9:00 PM</p>
                        </div>
                    </div>
                    <div class="d-flex align-items-start gap-3 mb-4">
                        <i class="bi bi-instagram text-primary fs-4 mt-1"></i>
                        <div>
                            <strong>Instagram</strong>
                            <p class="text-muted mb-0"><a href="https://instagram.com/goofy_kushz" target="_blank">goofy_kushz</a></p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7">
                    <?php if ($sent): ?>
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle me-2"></i>
                            <strong>Message sent!</strong> We'll get back to you as soon as possible.
                        </div>
                    <?php else: ?>
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <div class="card border-0 shadow-sm p-4">
                            <form method="POST" action="contact.php">
                                <input type="hidden" name="csrf_token" value="<?php echo Validator::generateCsrf(); ?>">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Your Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                                    <input type="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Subject</label>
                                    <input type="text" name="subject" class="form-control" value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>">
                                </div>
                                <div class="mb-4">
                                    <label class="form-label fw-semibold">Message <span class="text-danger">*</span></label>
                                    <textarea name="message" class="form-control" rows="5" required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-send me-2"></i>Send Message
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <footer class="bg-dark text-white py-4 text-center">
        <small class="text-muted">&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</small>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
