<?php
/**
 * FAQ Page
 */
require_once __DIR__ . '/config/app.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQ - <?php echo APP_NAME; ?></title>
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
                </ul>
            </div>
        </div>
    </nav>

    <div style="margin-top:56px; background:linear-gradient(135deg,#667eea 0%,#764ba2 100%); padding:60px 0;" class="text-white">
        <div class="container">
            <h1 class="fw-bold"><i class="bi bi-question-circle me-2"></i>Frequently Asked Questions</h1>
        </div>
    </div>

    <section class="py-5">
        <div class="container" style="max-width:800px;">
            <div class="accordion" id="faqAccordion">
                <?php
                $faqs = [
                    ['Q: What are the store hours?',               'The store is open daily from 4:00 PM to 9:00 PM. Orders can only be placed and delivered during these hours.'],
                    ['Q: Who can shop at JV Shetty?',             'JV Shetty is exclusively for BITS Pilani Goa campus community. You need a valid @goa.bits-pilani.ac.in email to register.'],
                    ['Q: How do I place an order?',               'Browse products, add them to your cart, and proceed to checkout. You can choose home delivery or in-store pickup.'],
                    ['Q: What payment methods are accepted?',      'We currently accept Cash on Delivery (COD). Online payment via UPI/card will be available soon.'],
                    ['Q: What is the delivery fee?',              'Delivery is free for orders above ₹500. A standard fee of ₹20 applies for orders below that.'],
                    ['Q: Can I cancel my order?',                 'You can cancel an order within 5 minutes of placing it. After that, cancellations are only possible with admin approval.'],
                    ['Q: Where is my order delivered?',           'We deliver to all hostels and quarters on the BITS Pilani Goa campus. Make sure to add your correct hostel and room number.'],
                    ['Q: What if an item is out of stock?',       'Out-of-stock items will show an "Out of Stock" badge and cannot be added to cart. Check back later as we restock regularly.'],
                ];
                foreach ($faqs as $index => [$q, $a]):
                ?>
                <div class="accordion-item border-0 mb-2 shadow-sm rounded">
                    <h2 class="accordion-header">
                        <button class="accordion-button <?php echo $index > 0 ? 'collapsed' : ''; ?> rounded" 
                                type="button" data-bs-toggle="collapse" 
                                data-bs-target="#faq<?php echo $index; ?>">
                            <?php echo htmlspecialchars($q); ?>
                        </button>
                    </h2>
                    <div id="faq<?php echo $index; ?>" class="accordion-collapse collapse <?php echo $index === 0 ? 'show' : ''; ?>" data-bs-parent="#faqAccordion">
                        <div class="accordion-body text-muted"><?php echo htmlspecialchars($a); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="text-center mt-5">
                <p class="text-muted">Still have questions? <a href="contact.php">Contact us</a>.</p>
            </div>
        </div>
    </section>

    <footer class="bg-dark text-white py-4 text-center">
        <small class="text-muted">&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>.</small>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
