<?php require_once __DIR__ . '/config/app.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php"><i class="bi bi-shop me-2"></i><?php echo APP_NAME; ?></a>
        </div>
    </nav>
    <div style="margin-top:56px; background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);padding:60px 0;" class="text-white">
        <div class="container"><h1 class="fw-bold"><i class="bi bi-shield-lock me-2"></i>Privacy Policy</h1>
        <p class="text-white-50 mb-0">Last updated: <?php echo date('F Y'); ?></p></div>
    </div>
    <section class="py-5">
        <div class="container" style="max-width:800px;">
            <div class="mb-5">
                <h4>1. Information We Collect</h4>
                <p class="text-muted">We collect the information you provide during registration: your campus email address (@goa.bits-pilani.ac.in), name, phone number, and hostel/delivery address. We also record order history and payment records.</p>
            </div>
            <div class="mb-5">
                <h4>2. How We Use Your Information</h4>
                <p class="text-muted">Your information is used solely for the purpose of processing and delivering your orders, sending order notifications, and improving our services. We do not share or sell your personal data to third parties.</p>
            </div>
            <div class="mb-5">
                <h4>3. Campus Email Restriction</h4>
                <p class="text-muted">Access to JV Shetty is restricted to BITS Pilani Goa campus members. We verify this via your institutional email address at registration.</p>
            </div>
            <div class="mb-5">
                <h4>4. Data Security</h4>
                <p class="text-muted">We implement industry-standard security measures including password hashing (Argon2id), HTTPS, secure session management, and CSRF protection to safeguard your account.</p>
            </div>
            <div class="mb-5">
                <h4>5. Cookies & Sessions</h4>
                <p class="text-muted">We use secure, HttpOnly session cookies to maintain your login session. No tracking or advertising cookies are used.</p>
            </div>
            <div class="mb-5">
                <h4>6. Data Retention</h4>
                <p class="text-muted">Order data is retained for accounting and dispute resolution purposes. You may request deletion of your account by contacting our support team.</p>
            </div>
            <div class="mb-5">
                <h4>7. Contact</h4>
                <p class="text-muted">For privacy-related questions, contact us at <a href="contact.php">support</a> or email <strong>support@jvshetty.bitsgoa.ac.in</strong>.</p>
            </div>
        </div>
    </section>
    <footer class="bg-dark text-white py-4 text-center">
        <small class="text-muted">&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>.</small>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
