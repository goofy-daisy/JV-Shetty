<?php require_once __DIR__ . '/config/app.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shipping & Delivery - <?php echo APP_NAME; ?></title>
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
        <div class="container"><h1 class="fw-bold"><i class="bi bi-truck me-2"></i>Shipping & Delivery</h1></div>
    </div>
    <section class="py-5">
        <div class="container" style="max-width:800px;">
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100 p-4">
                        <i class="bi bi-clock text-primary fs-1 mb-3"></i>
                        <h5>Delivery Hours</h5>
                        <p class="text-muted">Orders are delivered daily between <strong>4:00 PM and 9:00 PM</strong>. Orders placed on time will be delivered the same day.</p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100 p-4">
                        <i class="bi bi-geo-alt text-success fs-1 mb-3"></i>
                        <h5>Delivery Area</h5>
                        <p class="text-muted">We deliver to <strong>all hostels and quarters</strong> on the BITS Pilani Goa campus.</p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100 p-4">
                        <i class="bi bi-cash text-warning fs-1 mb-3"></i>
                        <h5>Delivery Fee</h5>
                        <p class="text-muted">A flat fee of <strong>₹5</strong> applies on all delivery orders. Choose <strong>self-pickup</strong> at checkout for just <strong>₹3</strong>. There is no minimum or maximum order value.</p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100 p-4">
                        <i class="bi bi-box-seam text-info fs-1 mb-3"></i>
                        <h5>In-Store Pickup</h5>
                        <p class="text-muted">Prefer to pick up yourself? Choose the <strong>pickup option</strong> at checkout and collect at the store during opening hours.</p>
                    </div>
                </div>
            </div>
            <div class="alert alert-info mt-5">
                <i class="bi bi-info-circle me-2"></i>
                Make sure to provide your correct <strong>hostel name and room number</strong> when placing a delivery order to avoid delays.
            </div>
        </div>
    </section>
    <footer class="bg-dark text-white py-4 text-center">
        <small class="text-muted">&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. <a href="contact.php" class="text-muted">Contact Us</a></small>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
