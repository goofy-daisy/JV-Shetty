<?php
/**
 * Register Page
 */

require_once __DIR__ . '/config/app.php';

if (Auth::check()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Validator::validateCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Security token expired. Please refresh and try again.';
    } else {
        $validator = new Validator();
        $rules = [
            'email'            => 'required|email|campus_email',
            'password'         => 'required|password',
            'password_confirm' => 'required|match:password',
            'first_name'       => 'required|min:2|max:50',
            'last_name'        => 'required|min:2|max:50',
            'phone'            => 'phone'
        ];

        if (!$validator->validate($_POST, $rules)) {
            $error = $validator->firstError();
        } else {
            $result = Auth::register([
                'email'      => $_POST['email'],
                'password'   => $_POST['password'],
                'first_name' => $_POST['first_name'],
                'last_name'  => $_POST['last_name'],
                'phone'      => $_POST['phone'] ?? null
            ]);

            if ($result['success']) {
                // Send email verification link
                Auth::sendVerificationEmail($result['user_id']);
                $success = 'Registration successful! Please check your email to verify your account, then log in.';
            } else {
                $error = $result['message'];
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Create your JV Shetty account – Campus supermarket for BITS Pilani Goa.">
    <title>Create Account – <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="min-h-screen flex items-center justify-center bg-gradient-to-br from-purple-600 via-indigo-600 to-purple-800 py-8 relative overflow-hidden">

    <div class="absolute top-0 left-0 w-96 h-96 rounded-full bg-white/5 blur-3xl -translate-x-1/2 -translate-y-1/2"></div>
    <div class="absolute bottom-0 right-0 w-80 h-80 rounded-full bg-purple-300/10 blur-3xl translate-x-1/2 translate-y-1/2"></div>

    <div class="relative w-full max-w-lg mx-4 sm:mx-auto">
        <div class="bg-white rounded-3xl shadow-2xl overflow-hidden">
            <!-- Header -->
            <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-8 py-7 text-center">
                <a href="index.php" class="inline-flex items-center gap-2 mb-1">
                    <div class="w-10 h-10 rounded-2xl bg-white/20 flex items-center justify-center">
                        <i class="bi bi-shop text-white text-lg"></i>
                    </div>
                    <span class="font-extrabold text-2xl text-white"><?php echo APP_NAME; ?></span>
                </a>
                <p class="text-purple-200 text-sm mt-1">Create your campus account</p>
            </div>

            <div class="px-8 py-8">
                <?php if ($success): ?>
                    <div class="flex items-start gap-3 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm mb-6">
                        <i class="bi bi-check-circle-fill flex-shrink-0 mt-0.5"></i>
                        <span><?php echo htmlspecialchars($success); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="flex items-start gap-3 p-4 rounded-2xl bg-red-50 border border-red-200 text-red-700 text-sm mb-6">
                        <i class="bi bi-exclamation-circle-fill flex-shrink-0 mt-0.5"></i>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" class="space-y-5">
                    <input type="hidden" name="csrf_token" value="<?php echo Validator::generateCsrf(); ?>">

                    <!-- Name Row -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="first_name" class="block text-sm font-semibold text-gray-700 mb-1.5">First Name</label>
                            <input type="text" id="first_name" name="first_name" required minlength="2" maxlength="50"
                                   value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>"
                                   placeholder="Rahul"
                                   class="w-full px-3.5 py-3 border-2 border-gray-200 rounded-xl text-sm focus:outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-100 transition-all">
                        </div>
                        <div>
                            <label for="last_name" class="block text-sm font-semibold text-gray-700 mb-1.5">Last Name</label>
                            <input type="text" id="last_name" name="last_name" required minlength="2" maxlength="50"
                                   value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>"
                                   placeholder="Sharma"
                                   class="w-full px-3.5 py-3 border-2 border-gray-200 rounded-xl text-sm focus:outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-100 transition-all">
                        </div>
                    </div>

                    <!-- Email -->
                    <div>
                        <label for="email" class="block text-sm font-semibold text-gray-700 mb-1.5">Campus Email</label>
                        <div class="relative">
                            <i class="bi bi-envelope absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                            <input type="email" id="email" name="email" required
                                   pattern=".*@goa\.bits-pilani\.ac\.in$"
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                   placeholder="f20xxxxxx@goa.bits-pilani.ac.in"
                                   class="w-full pl-10 pr-4 py-3 border-2 border-gray-200 rounded-xl text-sm focus:outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-100 transition-all">
                        </div>
                        <p class="text-xs text-gray-400 mt-1">Must be a @goa.bits-pilani.ac.in address</p>
                    </div>

                    <!-- Phone -->
                    <div>
                        <label for="phone" class="block text-sm font-semibold text-gray-700 mb-1.5">Phone Number <span class="text-gray-400 font-normal">(optional)</span></label>
                        <div class="relative">
                            <i class="bi bi-phone absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                            <input type="tel" id="phone" name="phone" pattern="[0-9]{10}"
                                   value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                                   placeholder="10-digit mobile number"
                                   class="w-full pl-10 pr-4 py-3 border-2 border-gray-200 rounded-xl text-sm focus:outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-100 transition-all">
                        </div>
                    </div>

                    <!-- Password -->
                    <div>
                        <label for="regPassword" class="block text-sm font-semibold text-gray-700 mb-1.5">Password</label>
                        <div class="relative">
                            <i class="bi bi-lock absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                            <input type="password" id="regPassword" name="password" required
                                   minlength="8"
                                   pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}"
                                   placeholder="Min 8 chars, uppercase, number"
                                   class="w-full pl-10 pr-4 py-3 border-2 border-gray-200 rounded-xl text-sm focus:outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-100 transition-all">
                        </div>
                        <!-- Strength Meter -->
                        <div class="mt-2">
                            <div class="w-full bg-gray-100 rounded-full overflow-hidden">
                                <div id="regStrengthBar" class="h-1.5 rounded-full transition-all duration-300 w-0"></div>
                            </div>
                            <p id="regStrengthLabel" class="text-xs mt-1 font-medium text-gray-400"></p>
                        </div>
                    </div>

                    <!-- Confirm Password -->
                    <div>
                        <label for="password_confirm" class="block text-sm font-semibold text-gray-700 mb-1.5">Confirm Password</label>
                        <div class="relative">
                            <i class="bi bi-lock-fill absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                            <input type="password" id="password_confirm" name="password_confirm" required
                                   placeholder="Re-enter your password"
                                   class="w-full pl-10 pr-4 py-3 border-2 border-gray-200 rounded-xl text-sm focus:outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-100 transition-all">
                        </div>
                    </div>

                    <!-- Terms -->
                    <div class="flex items-start gap-3">
                        <input type="checkbox" id="terms" name="terms" required value="1"
                               class="w-4 h-4 mt-0.5 rounded border-gray-300 text-purple-600 focus:ring-purple-500 flex-shrink-0">
                        <label for="terms" class="text-sm text-gray-600 cursor-pointer">
                            I agree to the <a href="privacy.php" class="text-purple-600 hover:underline font-medium">Privacy Policy</a> and
                            <a href="faq.php" class="text-purple-600 hover:underline font-medium">Terms of Service</a>
                        </label>
                    </div>

                    <!-- Submit -->
                    <button type="submit"
                            class="w-full py-3.5 bg-gradient-to-r from-purple-600 to-indigo-600 text-white font-bold rounded-xl text-sm hover:from-purple-700 hover:to-indigo-700 shadow-lg shadow-purple-200 hover:shadow-xl transition-all duration-300">
                        <i class="bi bi-person-check mr-2"></i>Create Account
                    </button>
                </form>

                <p class="text-center text-sm text-gray-500 mt-6">
                    Already have an account?
                    <a href="login.php" class="font-semibold text-purple-600 hover:text-purple-700 transition-colors">Sign in</a>
                </p>
            </div>
        </div>

        <p class="text-center text-white/60 text-xs mt-5">
            <i class="bi bi-mortarboard-fill mr-1"></i>BITSian-exclusive platform
        </p>
    </div>

    <script>
        // Password strength meter
        const pwd = document.getElementById('regPassword');
        const bar = document.getElementById('regStrengthBar');
        const lbl = document.getElementById('regStrengthLabel');
        if (pwd && bar && lbl) {
            const colors = ['', '#ef4444', '#f97316', '#eab308', '#22c55e', '#16a34a'];
            const labels = ['', 'Very Weak', 'Weak', 'Fair', 'Strong', 'Very Strong'];
            pwd.addEventListener('input', () => {
                const v = pwd.value;
                let score = 0;
                if (v.length >= 8)           score++;
                if (/[A-Z]/.test(v))         score++;
                if (/[a-z]/.test(v))         score++;
                if (/\d/.test(v))            score++;
                if (/[^A-Za-z0-9]/.test(v)) score++;
                bar.style.width      = (score / 5 * 100) + '%';
                bar.style.background = colors[score] || '#e5e7eb';
                lbl.textContent      = labels[score] || '';
                lbl.style.color      = colors[score] || '#9ca3af';
            });
        }
    </script>
</body>
</html>
