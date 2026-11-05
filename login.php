<?php
/**
 * Login Page
 */

require_once __DIR__ . '/config/app.php';

if (Auth::check()) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Validator::validateCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Security token expired. Please refresh the page.';
    } else {
        $result = Auth::login($_POST['email'], $_POST['password']);
        if ($result['success']) {
            header('Location: index.php');
            exit;
        } else {
            $error = $result['message'];
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Login to JV Shetty – Campus supermarket for BITS Pilani Goa.">
    <title>Login – <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="min-h-screen flex items-center justify-center bg-gradient-to-br from-purple-600 via-indigo-600 to-purple-800 relative overflow-hidden">
    <!-- Background blobs -->
    <div class="absolute top-0 left-0 w-96 h-96 rounded-full bg-white/5 blur-3xl -translate-x-1/2 -translate-y-1/2"></div>
    <div class="absolute bottom-0 right-0 w-80 h-80 rounded-full bg-purple-300/10 blur-3xl translate-x-1/2 translate-y-1/2"></div>

    <div class="relative w-full max-w-md mx-4 sm:mx-auto">
        <!-- Card -->
        <div class="bg-white rounded-3xl shadow-2xl overflow-hidden">
            <!-- Header -->
            <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-8 py-8 text-center">
                <a href="index.php" class="inline-flex items-center gap-2 mb-1">
                    <div class="w-10 h-10 rounded-2xl bg-white/20 flex items-center justify-center">
                        <i class="bi bi-shop text-white text-lg"></i>
                    </div>
                    <span class="font-extrabold text-2xl text-white tracking-tight"><?php echo APP_NAME; ?></span>
                </a>
                <p class="text-purple-200 text-sm mt-2">Sign in to your campus account</p>
            </div>

            <!-- Body -->
            <div class="px-8 py-8">
                <?php if ($error): ?>
                    <div class="flex items-start gap-3 p-4 rounded-2xl bg-red-50 border border-red-200 text-red-700 text-sm mb-6">
                        <i class="bi bi-exclamation-circle-fill flex-shrink-0 mt-0.5"></i>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" class="space-y-5">
                    <input type="hidden" name="csrf_token" value="<?php echo Validator::generateCsrf(); ?>">

                    <!-- Email -->
                    <div>
                        <label for="loginEmail" class="block text-sm font-semibold text-gray-700 mb-1.5">Campus Email</label>
                        <div class="relative">
                            <i class="bi bi-envelope absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                            <input type="email" id="loginEmail" name="email" required
                                   pattern=".*@goa\.bits-pilani\.ac\.in$"
                                   placeholder="f20xxxxxx@goa.bits-pilani.ac.in"
                                   class="w-full pl-10 pr-4 py-3 border-2 border-gray-200 rounded-xl text-sm focus:outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-100 transition-all"
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>
                        <p class="text-xs text-gray-400 mt-1">Only @goa.bits-pilani.ac.in addresses</p>
                    </div>

                    <!-- Password -->
                    <div>
                        <label for="loginPassword" class="block text-sm font-semibold text-gray-700 mb-1.5">Password</label>
                        <div class="relative">
                            <i class="bi bi-lock absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                            <input type="password" id="loginPassword" name="password" required
                                   placeholder="Enter your password"
                                   class="w-full pl-10 pr-10 py-3 border-2 border-gray-200 rounded-xl text-sm focus:outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-100 transition-all">
                            <button type="button" id="togglePwd" class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors">
                                <i class="bi bi-eye text-sm" id="togglePwdIcon"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Remember + Forgot -->
                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="remember" class="w-4 h-4 rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                            <span class="text-sm text-gray-600">Remember me</span>
                        </label>
                        <a href="forgot_password.php" class="text-sm font-semibold text-purple-600 hover:text-purple-700 transition-colors">
                            Forgot password?
                        </a>
                    </div>

                    <!-- Submit -->
                    <button type="submit"
                            class="w-full py-3.5 bg-gradient-to-r from-purple-600 to-indigo-600 text-white font-bold rounded-xl text-sm hover:from-purple-700 hover:to-indigo-700 shadow-lg shadow-purple-200 hover:shadow-xl transition-all duration-300">
                        <i class="bi bi-box-arrow-in-right mr-2"></i>Sign In
                    </button>
                </form>

                <p class="text-center text-sm text-gray-500 mt-6">
                    Don't have an account?
                    <a href="register.php" class="font-semibold text-purple-600 hover:text-purple-700 transition-colors">Create one</a>
                </p>
            </div>
        </div>

        <p class="text-center text-white/60 text-xs mt-6">
            <i class="bi bi-shield-check mr-1"></i>Secure campus-only access
        </p>
    </div>

    <script>
        const t = document.getElementById('togglePwd');
        const p = document.getElementById('loginPassword');
        const i = document.getElementById('togglePwdIcon');
        if (t) t.addEventListener('click', () => {
            p.type = p.type === 'password' ? 'text' : 'password';
            i.className = p.type === 'password' ? 'bi bi-eye text-sm' : 'bi bi-eye-slash text-sm';
        });
    </script>
</body>
</html>
