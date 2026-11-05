<?php
/**
 * About Page
 */
require_once __DIR__ . '/config/app.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - <?php echo APP_NAME; ?></title>
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
                    <li class="nav-item"><a class="nav-link active" href="about.php">About</a></li>
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

    <div style="margin-top: 56px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 80px 0;" class="text-white">
        <div class="container text-center">
            <h1 class="fw-bold display-4"><i class="bi bi-shop me-2"></i><?php echo APP_NAME; ?></h1>
            <p class="lead">Your Campus Supermarket at BITS Pilani Goa</p>
        </div>
    </div>

    <section class="py-5">
        <div class="container">
            <div class="row g-5 align-items-center">
                <div class="col-lg-6">
                    <h2 class="fw-bold mb-4">About JV Shetty</h2>
                    <p class="lead text-muted">JV Shetty is the beloved campus store at BITS Pilani Goa — your one-stop destination for snacks, daily essentials, stationery, personal care products, and much more.</p>
                    <p class="text-muted">We know campus life — the late-night cravings, the exam prep sessions, the need for a quick toothpaste run. That's why we made ordering as simple as a few taps, with delivery right to your hostel room.</p>
                    <hr>
                    <div class="row g-3 mt-2">
                        <div class="col-sm-6">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width:50px;height:50px;flex-shrink:0;">
                                    <i class="bi bi-clock-fill"></i>
                                </div>
                                <div>
                                    <strong>Store Hours</strong>
                                    <div class="text-muted small">4:00 PM – 9:00 PM Daily</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center" style="width:50px;height:50px;flex-shrink:0;">
                                    <i class="bi bi-truck-fill"></i>
                                </div>
                                <div>
                                    <strong>Delivery</strong>
                                    <div class="text-muted small">To All Hostels on Campus</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-warning text-dark rounded-circle d-flex align-items-center justify-content-center" style="width:50px;height:50px;flex-shrink:0;">
                                    <i class="bi bi-geo-alt-fill"></i>
                                </div>
                                <div>
                                    <strong>Location</strong>
                                    <div class="text-muted small">BITS Pilani, Goa Campus</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-info text-white rounded-circle d-flex align-items-center justify-content-center" style="width:50px;height:50px;flex-shrink:0;">
                                    <i class="bi bi-shield-check-fill"></i>
                                </div>
                                <div>
                                    <strong>Students Only</strong>
                                    <div class="text-muted small">BITS Goa email required</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 text-center">
                    <img src="assets/images/hero-store.png" alt="JV Shetty Store" class="img-fluid rounded-3 shadow-lg" style="max-height:400px;" onerror="this.src='https://via.placeholder.com/500x400/667eea/ffffff?text=JV+Shetty+Store'">
                </div>
            </div>
        </div>
    </section>

    <section class="py-5 bg-light">
        <div class="container text-center">
            <h2 class="fw-bold mb-5">Why Choose JV Shetty?</h2>
            <div class="row g-4">
                <div class="col-md-3">
                    <i class="bi bi-lightning-charge-fill text-primary fs-1 mb-3 d-block"></i>
                    <h5>Fast Delivery</h5>
                    <p class="text-muted small">Quick delivery to your hostel room within campus hours.</p>
                </div>
                <div class="col-md-3">
                    <i class="bi bi-patch-check-fill text-success fs-1 mb-3 d-block"></i>
                    <h5>Quality Products</h5>
                    <p class="text-muted small">Curated selection of quality products at fair prices.</p>
                </div>
                <div class="col-md-3">
                    <i class="bi bi-phone-fill text-warning fs-1 mb-3 d-block"></i>
                    <h5>Easy Ordering</h5>
                    <p class="text-muted small">Order online in minutes and track your delivery.</p>
                </div>
                <div class="col-md-3">
                    <i class="bi bi-cash-stack text-danger fs-1 mb-3 d-block"></i>
                    <h5>Cash on Delivery</h5>
                    <p class="text-muted small">Pay when your order arrives — no hassle.</p>
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
