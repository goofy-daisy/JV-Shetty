<?php
/**
 * User Profile Page
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/core/Auth.php';
require_once __DIR__ . '/models/User.php';

Auth::requireAuth();

$user      = Auth::user();
$userModel = new User();
$profile   = $userModel->getWithProfile($user['id']);
$addresses = $userModel->getAddresses($user['id']);

// Get hostels for addresses
$db    = Database::getInstance();
$stmt  = $db->query("SELECT * FROM hostel_list WHERE is_active = 1 ORDER BY type, name");
$hostels = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile – <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-gray-50">

    <?php require_once __DIR__ . '/includes/nav.php'; ?>

    <div class="page-content">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 mb-8 flex items-center gap-3">
                <i class="bi bi-person-circle text-purple-600"></i>My Profile
            </h1>

            <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
                <!-- Sidebar Card -->
                <div class="lg:col-span-1">
                    <div class="card-modern p-6 text-center">
                        <!-- Avatar -->
                        <div class="w-20 h-20 rounded-2xl bg-gradient-to-br from-purple-500 to-indigo-600 flex items-center justify-center mx-auto mb-4 text-3xl text-white font-extrabold shadow-lg shadow-purple-200">
                            <?php echo strtoupper(substr($profile['first_name'] ?? 'U', 0, 1)); ?>
                        </div>
                        <h2 class="font-bold text-gray-900 text-lg"><?php echo htmlspecialchars($profile['first_name'] . ' ' . $profile['last_name']); ?></h2>
                        <p class="text-gray-500 text-xs mt-0.5 truncate"><?php echo htmlspecialchars($profile['email']); ?></p>
                        <span class="inline-block mt-2 px-3 py-1 rounded-full bg-purple-100 text-purple-700 text-xs font-bold">
                            <?php echo ucfirst(str_replace('_', ' ', $profile['role_name'])); ?>
                        </span>

                        <div class="border-t border-gray-100 mt-5 pt-5 space-y-2">
                            <a href="orders.php" class="flex items-center gap-2 px-3 py-2.5 rounded-xl text-sm font-medium text-gray-600 hover:bg-purple-50 hover:text-purple-700 transition-colors">
                                <i class="bi bi-bag w-4"></i>My Orders
                            </a>
                            <a href="wishlist.php" class="flex items-center gap-2 px-3 py-2.5 rounded-xl text-sm font-medium text-gray-600 hover:bg-purple-50 hover:text-purple-700 transition-colors">
                                <i class="bi bi-heart w-4"></i>My Wishlist
                            </a>
                            <a href="logout.php" class="flex items-center gap-2 px-3 py-2.5 rounded-xl text-sm font-medium text-red-500 hover:bg-red-50 transition-colors">
                                <i class="bi bi-box-arrow-right w-4"></i>Logout
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="lg:col-span-3 space-y-6">
                    <!-- Personal Information -->
                    <div class="card-modern overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-50 flex items-center gap-2">
                            <i class="bi bi-person-fill text-purple-500"></i>
                            <h3 class="font-bold text-gray-900">Personal Information</h3>
                        </div>
                        <div class="p-6">
                            <form id="profileForm" class="space-y-4">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">First Name</label>
                                        <input type="text" name="first_name" class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl text-sm focus:outline-none focus:border-purple-500 transition-all"
                                               value="<?php echo htmlspecialchars($profile['first_name']); ?>" required>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">Last Name</label>
                                        <input type="text" name="last_name" class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl text-sm focus:outline-none focus:border-purple-500 transition-all"
                                               value="<?php echo htmlspecialchars($profile['last_name']); ?>" required>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">Email</label>
                                        <input type="email" class="w-full px-4 py-2.5 border-2 border-gray-100 rounded-xl text-sm bg-gray-50 text-gray-500 cursor-not-allowed"
                                               value="<?php echo htmlspecialchars($profile['email']); ?>" disabled>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">Phone</label>
                                        <input type="tel" name="phone" pattern="[0-9]{10}" class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl text-sm focus:outline-none focus:border-purple-500 transition-all"
                                               value="<?php echo htmlspecialchars($profile['phone'] ?? ''); ?>" placeholder="10-digit mobile">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">Student ID</label>
                                        <input type="text" name="student_id" class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl text-sm focus:outline-none focus:border-purple-500 transition-all"
                                               value="<?php echo htmlspecialchars($profile['student_id'] ?? ''); ?>">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">Department</label>
                                        <input type="text" name="department" class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl text-sm focus:outline-none focus:border-purple-500 transition-all"
                                               value="<?php echo htmlspecialchars($profile['department'] ?? ''); ?>">
                                    </div>
                                </div>
                                <button type="submit" class="px-6 py-2.5 bg-gradient-to-r from-purple-600 to-indigo-600 text-white font-bold rounded-xl text-sm hover:from-purple-700 hover:to-indigo-700 shadow-md transition-all">
                                    Save Changes
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Change Password -->
                    <div class="card-modern overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-50 flex items-center gap-2">
                            <i class="bi bi-lock-fill text-purple-500"></i>
                            <h3 class="font-bold text-gray-900">Change Password</h3>
                        </div>
                        <div class="p-6">
                            <form id="passwordForm" class="space-y-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">Current Password</label>
                                    <input type="password" name="current_password" required class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl text-sm focus:outline-none focus:border-purple-500 transition-all">
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">New Password</label>
                                        <input type="password" name="new_password" minlength="8" required class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl text-sm focus:outline-none focus:border-purple-500 transition-all">
                                        <p class="text-xs text-gray-400 mt-1">Min 8 chars, uppercase, number</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">Confirm New Password</label>
                                        <input type="password" name="confirm_password" required class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl text-sm focus:outline-none focus:border-purple-500 transition-all">
                                    </div>
                                </div>
                                <button type="submit" class="px-6 py-2.5 bg-gradient-to-r from-purple-600 to-indigo-600 text-white font-bold rounded-xl text-sm hover:from-purple-700 hover:to-indigo-700 shadow-md transition-all">
                                    Change Password
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Addresses -->
                    <div class="card-modern overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-50 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <i class="bi bi-geo-alt-fill text-purple-500"></i>
                                <h3 class="font-bold text-gray-900">My Addresses</h3>
                            </div>
                            <button class="px-3 py-1.5 bg-purple-600 text-white text-xs font-bold rounded-xl hover:bg-purple-700 transition-colors shadow-sm flex items-center gap-1"
                                    data-bs-toggle="modal" data-bs-target="#addAddressModal">
                                <i class="bi bi-plus"></i>Add Address
                            </button>
                        </div>
                        <div class="p-6">
                            <?php if (empty($addresses)): ?>
                                <p class="text-gray-500 text-sm text-center py-4">No addresses saved yet. Add one above!</p>
                            <?php else: ?>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <?php foreach ($addresses as $address): ?>
                                        <div class="border-2 border-gray-100 rounded-2xl p-4 hover:border-purple-200 transition-all">
                                            <div class="flex items-start justify-between gap-2 mb-2">
                                                <h4 class="font-semibold text-gray-900 text-sm"><?php echo htmlspecialchars($address['hostel_name']); ?></h4>
                                                <?php if ($address['is_default']): ?>
                                                    <span class="px-2 py-0.5 rounded-full bg-purple-100 text-purple-700 text-xs font-bold">Default</span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="text-sm text-gray-700">Room <?php echo htmlspecialchars($address['room_number']); ?></p>
                                            <p class="text-xs text-gray-500 mt-0.5"><?php echo htmlspecialchars($address['address_line1']); ?></p>
                                            <?php if ($address['instructions']): ?>
                                                <p class="text-xs text-gray-400 mt-1 flex items-center gap-1">
                                                    <i class="bi bi-info-circle"></i><?php echo htmlspecialchars($address['instructions']); ?>
                                                </p>
                                            <?php endif; ?>
                                            <div class="flex gap-2 mt-3">
                                                <button class="px-3 py-1.5 text-xs font-semibold border-2 border-purple-200 text-purple-700 rounded-xl hover:bg-purple-50 transition-colors"
                                                        onclick="editAddress(<?php echo htmlspecialchars(json_encode($address)); ?>)">Edit</button>
                                                <button class="px-3 py-1.5 text-xs font-semibold border-2 border-red-200 text-red-600 rounded-xl hover:bg-red-50 transition-colors"
                                                        onclick="deleteAddress(<?php echo $address['id']; ?>)">Delete</button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Address Modal -->
    <div class="modal fade" id="addAddressModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-3xl border-0 shadow-2xl overflow-hidden">
                <div class="modal-header bg-gradient-to-r from-purple-600 to-indigo-600 border-0 px-6 py-5">
                    <h5 class="modal-title text-white font-bold">Add New Address</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="addAddressForm">
                    <div class="modal-body p-6 space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">Hostel / Residence</label>
                            <select name="hostel_id" class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl text-sm focus:outline-none focus:border-purple-500 transition-all bg-white" required>
                                <option value="">Select hostel...</option>
                                <?php foreach ($hostels as $hostel): ?>
                                    <option value="<?php echo $hostel['id']; ?>"><?php echo htmlspecialchars($hostel['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">Room Number</label>
                            <input type="text" name="room_number" required class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl text-sm focus:outline-none focus:border-purple-500 transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">Address Line 1</label>
                            <input type="text" name="address_line1" required class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl text-sm focus:outline-none focus:border-purple-500 transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">Address Line 2 (Optional)</label>
                            <input type="text" name="address_line2" class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl text-sm focus:outline-none focus:border-purple-500 transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">Delivery Instructions (Optional)</label>
                            <textarea name="instructions" rows="2" class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl text-sm focus:outline-none focus:border-purple-500 transition-all resize-none"></textarea>
                        </div>
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" name="is_default" id="isDefault" value="1" class="w-4 h-4 rounded text-purple-600 border-gray-300 focus:ring-purple-500">
                            <span class="text-sm text-gray-700">Set as default address</span>
                        </label>
                    </div>
                    <div class="modal-footer border-0 px-6 pb-6 pt-0 gap-3">
                        <button type="button" class="px-5 py-2.5 border-2 border-gray-200 text-gray-700 rounded-xl text-sm font-semibold hover:bg-gray-50 transition-colors" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="px-5 py-2.5 bg-gradient-to-r from-purple-600 to-indigo-600 text-white font-bold rounded-xl text-sm hover:from-purple-700 hover:to-indigo-700 shadow-md transition-all">Save Address</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>
    <div class="toast-container"></div>
    <script src="assets/js/main.js"></script>
    <script>
        // Profile form
        document.getElementById('profileForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const data = Object.fromEntries(formData);
            try {
                const response = await fetch('api/user.php?action=update_profile', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data)
                });
                const result = await response.json();
                if (result.success) { showToast('Profile updated successfully'); }
                else { showToast(result.message || 'Failed to update profile', 'error'); }
            } catch (error) { showToast('Failed to update profile', 'error'); }
        });

        // Password form
        document.getElementById('passwordForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const data = Object.fromEntries(formData);
            if (data.new_password !== data.confirm_password) { showToast('Passwords do not match', 'error'); return; }
            try {
                const response = await fetch('api/user.php?action=change_password', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data)
                });
                const result = await response.json();
                if (result.success) { showToast('Password changed successfully'); this.reset(); }
                else { showToast(result.message || 'Failed to change password', 'error'); }
            } catch (error) { showToast('Failed to change password', 'error'); }
        });

        // Add address form
        document.getElementById('addAddressForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const data = Object.fromEntries(new FormData(this));
            try {
                const r = await fetch('api/user.php?action=add_address', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data)
                });
                const result = await r.json();
                if (result.success) { showToast('Address added successfully'); location.reload(); }
                else { showToast(result.message || 'Failed to add address', 'error'); }
            } catch (error) { showToast('Failed to add address', 'error'); }
        });

        async function deleteAddress(addressId) {
            if (!confirm('Are you sure you want to delete this address?')) return;
            try {
                const r = await fetch('api/user.php?action=delete_address', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ address_id: addressId })
                });
                const result = await r.json();
                if (result.success) { showToast('Address deleted'); location.reload(); }
                else { showToast(result.message || 'Failed to delete address', 'error'); }
            } catch (error) { showToast('Failed to delete address', 'error'); }
        }
    </script>
</body>
</html>
