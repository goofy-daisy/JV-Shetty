<?php require_once __DIR__ . '/config/app.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Returns & Refunds - <?php echo APP_NAME; ?></title>
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
        <div class="container"><h1 class="fw-bold"><i class="bi bi-arrow-return-left me-2"></i>Returns & Refunds</h1></div>
    </div>
    <section class="py-5">
        <div class="container" style="max-width:800px;">
            <div class="card border-0 shadow-sm p-4 mb-4">
                <h4><i class="bi bi-clock-history text-primary me-2"></i>Order Cancellation</h4>
                <p class="text-muted">You can cancel your order within <strong>5 minutes</strong> of placing it via the My Orders page. After this window, cancellations must be handled by our support team.</p>
            </div>
            <div class="card border-0 shadow-sm p-4 mb-4">
                <h4><i class="bi bi-exclamation-triangle text-warning me-2"></i>Damaged or Wrong Items</h4>
                <p class="text-muted">If you receive a damaged or incorrect item, please contact us immediately at <a href="contact.php">support</a> or email <strong>support@jvshetty.bitsgoa.ac.in</strong> with your order number and a description of the issue.</p>
            </div>
            <div class="card border-0 shadow-sm p-4 mb-4">
                <h4><i class="bi bi-cash-stack text-success me-2"></i>Refund Policy</h4>
                <p class="text-muted">Refunds for eligible orders are processed within <strong>3–5 business days</strong> to the original payment method. COD orders will receive a store credit or cash refund at pickup.</p>
            </div>
            <div class="card border-0 shadow-sm p-4">
                <h4><i class="bi bi-x-circle text-danger me-2"></i>Non-Returnable Items</h4>
                <ul class="text-muted">
                    <li>Perishable food items and beverages (once delivered)</li>
                    <li>Personal care or hygiene products (once opened)</li>
                    <li>Items damaged due to customer misuse</li>
                </ul>
            </div>
            <div class="text-center mt-5">
                <a href="contact.php" class="btn btn-primary"><i class="bi bi-envelope me-2"></i>Contact Support</a>
            </div>
        </div>
    </section>
    <footer class="bg-dark text-white py-4 text-center">
        <small class="text-muted">&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>.</small>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
