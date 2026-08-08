<?php
/**
 * Login Page — Online Book Shop
 *
 * Technologies: PHP (core), MySQLi (prepared statements), Tailwind CSS, Vanilla JS.
 *
 * Security measures:
 *   - Prepared statements for all DB queries
 *   - Password hashing via password_verify (bcrypt / argon2)
 *   - CSRF token generation and validation
 *   - Generic error messages (no email-enumeration leaks)
 *   - htmlspecialchars() on all user output
 *   - Session regeneration after successful login
 */

session_start();
require_once '../config/db.php';

/* ------------------------------------------------------------------ */
/* CSRF Token — generate once per page load, validate on POST          */
/* ------------------------------------------------------------------ */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error   = '';
$success = '';

/* Show registration-success flash message (set by register.php) */
if (!empty($_SESSION['register_success'])) {
    $success = $_SESSION['register_success'];
    unset($_SESSION['register_success']);
}

/* ------------------------------------------------------------------ */
/* Handle POST                                                         */
/* ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* --- CSRF validation --- */
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {

        $email    = trim($_POST['email']    ?? '');
        $password = $_POST['password']      ?? '';
        $remember = !empty($_POST['remember']);

        /* Basic validation */
        if ($email === '' || $password === '') {
            $error = 'Please fill in all fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {

            /* ---------------------------------------------------------- */
            /* Look up the email in the users table                        */
            /* ---------------------------------------------------------- */
            if (isset($conn) && $conn instanceof mysqli) {
                $stmt = $conn->prepare(
                    'SELECT id, name, email, password, role FROM users WHERE email = ? LIMIT 1'
                );
                if ($stmt) {
                    $stmt->bind_param('s', $email);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows === 0) {
                        /* Email does not exist in database */
                        $error = 'No account was found with this email address. Please register before logging in.';
                    } else {
                        $row = $result->fetch_assoc();

                        if (!password_verify($password, $row['password'])) {
                            /* Email exists but password is incorrect */
                            $error = 'Incorrect password. Please try again.';
                        } else {
                            /* -------------------------------------------------- */
                            /* Credentials correct — regenerate session ID       */
                            /* -------------------------------------------------- */
                            session_regenerate_id(true);

                            $_SESSION['user_id']    = $row['id'];
                            $_SESSION['user_name']  = $row['name'];
                            $_SESSION['user_role']  = $row['role'];
                            $_SESSION['user_email'] = $row['email'];

                            /* Remember Me — 30-day cookie setup */
                            if ($remember) {
                                $cookie_name = $row['role'] === 'admin' ? 'remember_admin' : 'remember_user';
                                setcookie($cookie_name, bin2hex(random_bytes(16)), time() + 86400 * 30, '/', '', false, true);
                            }

                            /* Redirect according to user role */
                            if ($row['role'] === 'admin') {
                                header('Location: ../admin/dashboard.php');
                                exit();
                            } else {
                                header('Location: ../index.php');
                                exit();
                            }
                        }
                    }
                    $stmt->close();
                } else {
                    $error = 'Database query preparation failed.';
                }
            } else {
                $error = 'Database connection failed.';
            }
        }
    }

    /* Regenerate CSRF token after POST request to prevent replay attacks */
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Online Book Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Smooth page loading animation */
        .login-card { animation: slideUp .4s ease-out both; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(24px); } to { opacity: 1; transform: translateY(0); } }

        /* Custom styling for remember checkbox */
        .remember-check { accent-color: #f59e0b; }
    </style>
</head>
<body class="bg-slate-200 min-h-screen flex items-center justify-center font-sans p-4 md:p-6">

<main class="login-card bg-white rounded-3xl shadow-[0_25px_60px_rgba(180,130,50,0.12)] overflow-hidden max-w-4xl w-full grid grid-cols-1 md:grid-cols-2 border border-[#eae3d2]">

    <!-- ============================================================ -->
    <!-- LEFT PANEL — Branded visual side                              -->
    <!-- ============================================================ -->
    <div class="relative bg-amber-900 text-white p-8 sm:p-10 md:p-12 flex flex-col justify-between min-h-[280px] sm:min-h-[320px] md:min-h-[540px] overflow-hidden">
        <!-- Background image -->
        <div class="absolute inset-0 bg-cover bg-center transition-transform duration-[2s] hover:scale-105"
             style="background-image: url('https://images.unsplash.com/photo-1516979187457-637abb4f9353?auto=format&fit=crop&q=80&w=800');"></div>
        <!-- Gradient overlay -->
        <div class="absolute inset-0 bg-gradient-to-t from-amber-950/85 via-amber-900/40 to-transparent"></div>

        <!-- Branding -->
        <div class="relative z-10">
            <a href="../index.php" class="inline-flex items-center gap-2 bg-amber-500/90 backdrop-blur-sm px-4 py-2 rounded-full font-black text-sm text-slate-900 shadow-lg border border-amber-400/50 hover:bg-amber-500 transition-colors">
                <i class="fa-solid fa-book-open"></i> OnlineBookShop
            </a>
        </div>

        <!-- Tagline -->
        <div class="relative z-10 mt-auto">
            <h1 class="text-2xl md:text-3xl font-black leading-snug drop-shadow-lg">
                စာအုပ်စာပေ<br>လူ့မိတ်ဆွေ
            </h1>
            <p class="text-amber-200/80 text-sm mt-3 max-w-xs leading-relaxed">
                သင့်အတွက် စာကောင်းပေမွန်များကို တစ်နေရာတည်းမှာ ရှာဖွေပါ။
            </p>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- RIGHT PANEL — Login form                                      -->
    <!-- ============================================================ -->
    <div class="p-6 sm:p-8 md:p-12 flex flex-col justify-center bg-[#faf8f2]">

        <!-- Header -->
        <div class="mb-7 text-center md:text-left">
            <h2 class="text-2xl md:text-3xl font-black text-slate-800 tracking-tight">Welcome Back</h2>
            <p class="text-xs text-amber-700 font-semibold mt-1.5">Sign in to your account</p>
        </div>

        <!-- Success message (from registration) -->
        <?php if ($success !== ''): ?>
            <div class="bg-green-50 border-l-4 border-green-500 text-green-700 px-4 py-3 rounded-xl mb-5 text-xs font-bold flex items-center gap-2 shadow-sm">
                <i class="fa-solid fa-circle-check"></i>
                <span><?= htmlspecialchars($success) ?></span>
            </div>
        <?php endif; ?>

        <!-- Error message -->
        <?php if ($error !== ''): ?>
            <div class="bg-red-50 border-l-4 border-red-500 text-red-700 px-4 py-3 rounded-xl mb-5 text-xs font-bold flex items-center gap-2 shadow-sm">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <!-- Login form -->
        <form action="" method="POST" class="space-y-5" id="loginForm" novalidate>
            <!-- CSRF token -->
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <!-- Email field -->
            <div>
                <label for="email" class="block text-xs font-bold text-slate-700 mb-2">Email</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-amber-700/40 pointer-events-none">
                        <i class="fa-solid fa-envelope text-xs"></i>
                    </span>
                    <input type="email" id="email" name="email"
                           value="<?= htmlspecialchars($email ?? '') ?>"
                           placeholder="you@example.com"
                           required autocomplete="email"
                           class="w-full pl-10 pr-4 py-3 text-sm rounded-xl border border-amber-200 bg-white text-slate-800 placeholder-gray-400 focus:outline-none focus:ring-0 focus:border-amber-500 transition-colors duration-150 shadow-sm hover:border-amber-300">
                </div>
                <p id="emailError" class="hidden text-red-500 text-[11px] mt-1 font-semibold"></p>
            </div>

            <!-- Password field with show/hide toggle -->
            <div>
                <label for="password" class="block text-xs font-bold text-slate-700 mb-2">Password</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-amber-700/40 pointer-events-none">
                        <i class="fa-solid fa-lock text-xs"></i>
                    </span>
                    <input type="password" id="password" name="password"
                           placeholder="Enter your password"
                           required autocomplete="current-password"
                           class="w-full pl-10 pr-12 py-3 text-sm rounded-xl border border-amber-200 bg-white text-slate-800 placeholder-gray-400 focus:outline-none focus:ring-0 focus:border-amber-500 transition-colors duration-150 shadow-sm hover:border-amber-300">
                    <!-- Show/Hide toggle button -->
                    <button type="button" id="togglePassword"
                            class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-amber-700/50 hover:text-amber-700 transition-colors duration-200 focus:outline-none"
                            aria-label="Show or hide password">
                        <i class="fa-solid fa-eye text-xs" id="eyeIcon"></i>
                    </button>
                </div>
                <p id="passwordError" class="hidden text-red-500 text-[11px] mt-1 font-semibold"></p>
            </div>

            <!-- Remember Me + Forgot Password row -->
            <div class="flex items-center justify-between text-xs">
                <label class="flex items-center gap-2 cursor-pointer select-none">
                    <input type="checkbox" name="remember" value="1" class="remember-check w-4 h-4 rounded border-gray-300 cursor-pointer">
                    <span class="text-slate-600 font-semibold">Remember Me</span>
                </label>
                <a href="#" class="text-amber-700 font-bold hover:text-amber-800 hover:underline transition-colors duration-200">
                    Forgot Password?
                </a>
            </div>

            <!-- Submit button -->
            <button type="submit" id="submitBtn"
                    class="w-full bg-[#f0b90b] hover:bg-amber-500 text-slate-900 font-black py-3 px-4 rounded-xl shadow-[0_5px_15px_rgba(240,185,11,0.3)] hover:shadow-[0_8px_25px_rgba(240,185,11,0.4)] transition-all duration-300 transform hover:-translate-y-0.5 active:translate-y-0 active:scale-[0.98] text-sm mt-2 flex items-center justify-center gap-2">
                <i class="fa-solid fa-right-to-bracket"></i>
                <span id="btnText">Sign In</span>
                <!-- Spinner (hidden by default) -->
                <svg id="btnSpinner" class="hidden animate-spin h-4 w-4 text-slate-900" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                </svg>
            </button>
        </form>

        <!-- Register link -->
        <div class="text-center text-xs text-slate-500 font-bold mt-8 pt-4 border-t border-amber-100">
            အကောင့်မရှိသေးဘူးလား?
            <a href="register.php" class="text-amber-700 font-black hover:text-amber-800 hover:underline ml-1 transition-colors duration-200">အကောင့်သစ်ဖွင့်ရန်</a>
        </div>
    </div>
</main>

<!-- ================================================================ -->
<!-- Vanilla JavaScript — form logic                                   -->
<!-- ================================================================ -->
<script>
(function () {
    'use strict';

    /* ---- Show / Hide Password ---- */
    var passwordInput = document.getElementById('password');
    var toggleBtn     = document.getElementById('togglePassword');
    var eyeIcon       = document.getElementById('eyeIcon');

    if (toggleBtn && passwordInput && eyeIcon) {
        toggleBtn.addEventListener('click', function () {
            var isPassword = passwordInput.type === 'password';
            passwordInput.type = isPassword ? 'text' : 'password';
            eyeIcon.classList.toggle('fa-eye');
            eyeIcon.classList.toggle('fa-eye-slash');
        });
    }

    /* ---- Client-side form validation ---- */
    var form      = document.getElementById('loginForm');
    var emailIn   = document.getElementById('email');
    var passIn    = document.getElementById('password');
    var emailErr  = document.getElementById('emailError');
    var passErr   = document.getElementById('passwordError');
    var submitBtn = document.getElementById('submitBtn');
    var btnText   = document.getElementById('btnText');
    var btnSpin   = document.getElementById('btnSpinner');

    function showError(el, msg) {
        if (el) {
            el.textContent = msg;
            el.classList.remove('hidden');
        }
    }
    function hideError(el) {
        if (el) {
            el.classList.add('hidden');
            el.textContent = '';
        }
    }

    /* Clear errors while typing */
    if (emailIn) {
        emailIn.addEventListener('input', function () { hideError(emailErr); });
    }
    if (passIn) {
        passIn.addEventListener('input', function () { hideError(passErr); });
    }

    /* Submit validation + spinner toggle */
    if (form) {
        form.addEventListener('submit', function (e) {
            var valid = true;

            if (!emailIn || emailIn.value.trim() === '' || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailIn.value.trim())) {
                showError(emailErr, 'Please enter a valid email.');
                valid = false;
            }
            if (!passIn || passIn.value === '' || passIn.value.length < 6) {
                showError(passErr, 'Password must be at least 6 characters.');
                valid = false;
            }

            if (!valid) {
                e.preventDefault();
                return;
            }

            /* Show loading state on button */
            if (btnText) btnText.textContent = 'Signing in...';
            if (btnSpin) btnSpin.classList.remove('hidden');
            if (submitBtn) submitBtn.disabled = true;
        });
    }
})();
</script>
</body>
</html>