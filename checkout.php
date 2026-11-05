<?php
/**
 * Checkout Page
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/core/Auth.php';
require_once __DIR__ . '/core/StoreHours.php';
require_once __DIR__ . '/models/Cart.php';
require_once __DIR__ . '/models/User.php';

Auth::requireAuth();

// Check store hours
$storeStatus = StoreHours::validateCheckout();
if (!$storeStatus['allowed']) {
    header('Location: cart.php?error=store_closed');
    exit;
}

$user = Auth::user();
$cart = new Cart();
$userCart = $cart->getOrCreate($user['id']);
$cartData = $cart->getWithItems($userCart['id']);

if (empty($cartData['items'])) {
    header('Location: cart.php');
    exit;
}

// Validate stock
$stockValidation = $cart->validateStock($userCart['id']);
if (!$stockValidation['valid']) {
    header('Location: cart.php?error=stock');
    exit;
}

// Get user addresses
$userModel = new User();
$addresses = $userModel->getAddresses($user['id']);

// Get hostels for new address
$db = Database::getInstance();
$stmt = $db->query("SELECT * FROM hostel_list WHERE is_active = 1 ORDER BY type, name");
$hostels = $stmt->fetchAll();

// Get pickup slots
$stmt = $db->prepare("
    SELECT * FROM pickup_slots 
    WHERE slot_date >= CURDATE() 
    AND slot_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)
    AND is_active = 1
    AND booked_orders < max_orders
    ORDER BY slot_date, start_time
");
$stmt->execute();
$slots = $stmt->fetchAll();

// Group slots by date
$groupedSlots = [];
foreach ($slots as $slot) {
    $date = $slot['slot_date'];
    if (!isset($groupedSlots[$date])) {
        $groupedSlots[$date] = [];
    }
    $groupedSlots[$date][] = $slot;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="bi bi-shop me-2"></i><?php echo APP_NAME; ?>
            </a>
            <div class="d-flex align-items-center gap-3">
                <a href="cart.php" class="btn btn-outline-light">
                    <i class="bi bi-cart3 me-1"></i>Cart
                </a>
                <a href="profile.php" class="btn btn-outline-light"><i class="bi bi-person"></i></a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div style="margin-top: 76px;">
        <div class="container py-4">
            <h1 class="h3 mb-4">Checkout</h1>
            
            <form id="checkoutForm" method="POST" action="api/orders.php?action=create">
                <div class="row g-4">
                    <!-- Left Column -->
                    <div class="col-lg-8">
                        <!-- Fulfillment Type -->
                        <div class="card mb-4">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Delivery or Pickup</h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="form-check card p-3 h-100">
                                            <input class="form-check-input" type="radio" name="fulfillment_type" id="delivery" value="delivery" checked>
                                            <label class="form-check-label w-100" for="delivery">
                                                <div class="d-flex align-items-center">
                                                    <i class="bi bi-truck fs-3 me-3 text-primary"></i>
                                                    <div>
                                                        <strong>Home/Hostel Delivery</strong>
                                                        <p class="text-muted small mb-0">Delivered to your doorstep</p>
                                                        <?php if ($cartData['delivery_fee'] == 0): ?>
                                                            <span class="badge bg-success">FREE</span>
                                                        <?php else: ?>
                                                            <span class="text-muted">₹<?php echo number_format($cartData['delivery_fee'], 2); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check card p-3 h-100">
                                            <input class="form-check-input" type="radio" name="fulfillment_type" id="pickup" value="pickup">
                                            <label class="form-check-label w-100" for="pickup">
                                                <div class="d-flex align-items-center">
                                                    <i class="bi bi-shop fs-3 me-3 text-primary"></i>
                                                    <div>
                                                        <strong>In-Store Pickup</strong>
                                                        <p class="text-muted small mb-0">Pick up at JV Shetty</p>
                                                        <span class="badge bg-success">FREE</span>
                                                    </div>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Delivery Address Section -->
                        <div class="card mb-4" id="deliverySection">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Delivery Address</h5>
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addAddressModal">
                                    <i class="bi bi-plus me-1"></i>Add New
                                </button>
                            </div>
                            <div class="card-body">
                                <?php if (empty($addresses)): ?>
                                    <div class="alert alert-info">
                                        <i class="bi bi-info-circle me-2"></i>Please add a delivery address.
                                    </div>
                                <?php else: ?>
                                    <div class="row g-3">
                                        <?php foreach ($addresses as $address): ?>
                                            <div class="col-md-6">
                                                <div class="form-check address-card p-3" onclick="selectAddress(this, <?php echo $address['id']; ?>)">
                                                    <input class="form-check-input" type="radio" name="address_id" id="address<?php echo $address['id']; ?>" 
                                                           value="<?php echo $address['id']; ?>" <?php echo $address['is_default'] ? 'checked' : ''; ?>>
                                                    <label class="form-check-label w-100" for="address<?php echo $address['id']; ?>">
                                                        <div class="d-flex justify-content-between">
                                                            <strong><?php echo htmlspecialchars($address['hostel_name']); ?></strong>
                                                            <?php if ($address['is_default']): ?>
                                                                <span class="badge bg-primary">Default</span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <p class="mb-1">Room <?php echo htmlspecialchars($address['room_number']); ?></p>
                                                        <p class="text-muted small mb-0"><?php echo htmlspecialchars($address['address_line1']); ?></p>
                                                        <?php if ($address['instructions']): ?>
                                                            <p class="text-muted small mb-0"><i class="bi bi-info-circle me-1"></i><?php echo htmlspecialchars($address['instructions']); ?></p>
                                                        <?php endif; ?>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Pickup Slots Section (Hidden by default) -->
                        <div class="card mb-4 d-none" id="pickupSection">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Select Pickup Time</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($groupedSlots)): ?>
                                    <div class="alert alert-warning">No pickup slots available.</div>
                                <?php else: ?>
                                    <div class="accordion" id="pickupAccordion">
                                        <?php 
                                        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                                        $index = 0;
                                        foreach ($groupedSlots as $date => $daySlots): 
                                            $slotDate = new DateTime($date);
                                            $isToday = $slotDate->format('Y-m-d') === date('Y-m-d');
                                            $dayName = $isToday ? 'Today' : $days[$slotDate->format('w')];
                                        ?>
                                            <div class="accordion-item">
                                                <h2 class="accordion-header">
                                                    <button class="accordion-button <?php echo $index > 0 ? 'collapsed' : ''; ?>" type="button" 
                                                            data-bs-toggle="collapse" data-bs-target="#slots<?php echo $index; ?>">
                                                        <strong><?php echo $dayName; ?></strong>
                                                        <span class="ms-2 text-muted"><?php echo $slotDate->format('j M'); ?></span>
                                                    </button>
                                                </h2>
                                                <div id="slots<?php echo $index; ?>" class="accordion-collapse collapse <?php echo $index === 0 ? 'show' : ''; ?>"
                                                     data-bs-parent="#pickupAccordion">
                                                    <div class="accordion-body">
                                                        <div class="row g-2">
                                                            <?php foreach ($daySlots as $slot): ?>
                                                                <div class="col-md-4">
                                                                    <div class="form-check">
                                                                        <input class="form-check-input" type="radio" name="pickup_slot_id" 
                                                                               id="slot<?php echo $slot['id']; ?>" value="<?php echo $slot['id']; ?>">
                                                                        <label class="form-check-label" for="slot<?php echo $slot['id']; ?>">
                                                                            <?php 
                                                                            $start = new DateTime($slot['start_time']);
                                                                            $end = new DateTime($slot['end_time']);
                                                                            echo $start->format('g:i A') . ' - ' . $end->format('g:i A');
                                                                            ?>
                                                                            <span class="badge bg-secondary ms-1"><?php echo $slot['max_orders'] - $slot['booked_orders']; ?> left</span>
                                                                        </label>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php 
                                            $index++;
                                        endforeach; 
                                        ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Order Notes -->
                        <div class="card mb-4">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Additional Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Order Notes (Optional)</label>
                                    <textarea class="form-control" name="notes" rows="3" placeholder="Any special instructions for your order..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Column - Order Summary -->
                    <div class="col-lg-4">
                        <div class="card sticky-top" style="top: 80px;">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Order Summary</h5>
                            </div>
                            <div class="card-body">
                                <?php foreach ($cartData['items'] as $item): ?>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted"><?php echo htmlspecialchars($item['name']); ?> × <?php echo $item['quantity']; ?></span>
                                        <span>₹<?php echo number_format($item['total_price'], 2); ?></span>
                                    </div>
                                <?php endforeach; ?>
                                <hr>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Subtotal</span>
                                    <span>₹<?php echo number_format($cartData['subtotal'], 2); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Tax (5%)</span>
                                    <span>₹<?php echo number_format($cartData['tax_amount'], 2); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2" id="deliveryFeeRow">
                                    <span>Delivery Fee</span>
                                    <span id="deliveryFee">
                                        <?php if ($cartData['delivery_fee'] > 0): ?>
                                            ₹<?php echo number_format($cartData['delivery_fee'], 2); ?>
                                        <?php else: ?>
                                            <span class="text-success">FREE</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between mb-4">
                                    <span class="fw-bold h5 mb-0">Total</span>
                                    <span class="fw-bold h5 mb-0 text-primary" id="totalAmount">₹<?php echo number_format($cartData['total_amount'], 2); ?></span>
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100 btn-lg" id="placeOrderBtn">
                                    Place Order
                                </button>
                                
                                <p class="text-muted small text-center mt-3 mb-0">
                                    <i class="bi bi-shield-check me-1"></i>Secure checkout
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Add Address Modal -->
    <div class="modal fade" id="addAddressModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Address</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="api/user.php?action=add_address">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Hostel/Residence</label>
                            <select name="hostel_id" class="form-select" required>
                                <option value="">Select...</option>
                                <?php foreach ($hostels as $hostel): ?>
                                    <option value="<?php echo $hostel['id']; ?>"><?php echo htmlspecialchars($hostel['name']); ?> (<?php echo $hostel['type']; ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Room Number</label>
                            <input type="text" name="room_number" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address Line 1</label>
                            <input type="text" name="address_line1" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address Line 2 (Optional)</label>
                            <input type="text" name="address_line2" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Delivery Instructions (Optional)</label>
                            <textarea name="instructions" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_default" id="isDefault" value="1">
                            <label class="form-check-label" for="isDefault">Set as default address</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Address</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Toast Container -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3"></div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
        // Toggle between delivery and pickup
        document.querySelectorAll('input[name="fulfillment_type"]').forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.value === 'delivery') {
                    document.getElementById('deliverySection').classList.remove('d-none');
                    document.getElementById('pickupSection').classList.add('d-none');
                    document.getElementById('deliveryFeeRow').classList.remove('d-none');
                } else {
                    document.getElementById('deliverySection').classList.add('d-none');
                    document.getElementById('pickupSection').classList.remove('d-none');
                    document.getElementById('deliveryFeeRow').classList.add('d-none');
                    // Recalculate total without delivery fee
                    const subtotal = <?php echo $cartData['subtotal']; ?>;
                    const tax = <?php echo $cartData['tax_amount']; ?>;
                    const total = subtotal + tax;
                    document.getElementById('totalAmount').textContent = '₹' + total.toFixed(2);
                }
            });
        });
        
        function selectAddress(card, addressId) {
            document.querySelectorAll('.address-card').forEach(c => c.classList.remove('selected'));
            card.classList.add('selected');
            document.getElementById('address' + addressId).checked = true;
        }
        
        // Form submission
        document.getElementById('checkoutForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const btn = document.getElementById('placeOrderBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
            
            const formData = new FormData(this);
            const data = Object.fromEntries(formData);
            
            try {
                const response = await fetch(this.action, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    window.location.href = 'order_confirmation.php?order_id=' + result.order_id;
                } else {
                    showToast(result.message, 'error');
                    btn.disabled = false;
                    btn.innerHTML = 'Place Order';
                }
            } catch (error) {
                showToast('Failed to place order', 'error');
                btn.disabled = false;
                btn.innerHTML = 'Place Order';
            }
        });
    </script>
</body>
</html>
