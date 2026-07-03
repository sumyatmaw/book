<?php
/**
 * Registration Page — Online Book Shop
 *
 * Technologies: PHP (core), MySQLi (prepared statements), Tailwind CSS, Vanilla JS.
 *
 * Security measures:
 *   - Prepared statements for all DB queries
 *   - password_hash() with PASSWORD_DEFAULT (bcrypt)
 *   - CSRF token generation and validation
 *   - htmlspecialchars() on all output
 *   - Server-side input validation
 */

session_start();
require_once '../config/db.php';

/* ------------------------------------------------------------------ */
/* CSRF Token                                                          */
/* ------------------------------------------------------------------ */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error  = '';
$success = '';
$old_name  = '';
$old_email = '';

/* Flash messages from other pages */
if (!empty($_SESSION['register_error'])) {
    $error = $_SESSION['register_error'];
    unset($_SESSION['register_error']);
}
if (!empty($_SESSION['register_success'])) {
    $success = $_SESSION['register_success'];
    unset($_SESSION['register_success']);
}

/* ------------------------------------------------------------------ */
/* Handle POST                                                         */
/* ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {

        $name             = trim($_POST['name']             ?? '');
        $email            = trim($_POST['email']            ?? '');
        $password         = $_POST['password']              ?? '';
        $confirm_password = $_POST['confirm_password']      ?? '';

        /* Keep old values on error so the user doesn't re-type everything */
        $old_name  = $name;
        $old_email = $email;

        /* ---- Validation ---- */
        if ($name === '' || $email === '' || $password === '' || $confirm_password === '') {
            $error = 'Please fill in all fields.';
        } elseif (mb_strlen($name) < 2) {
            $error = 'Name must be at least 2 characters.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } else {

            /* Check if email is already registered */
            $check = $conn->prepare('SELECT id FROM Users WHERE email = ? LIMIT 1');
            $check->bind_param('s', $email);
            $check->execute();
            $checkResult = $check->get_result();

            if ($checkResult->num_rows > 0) {
                $error = 'This email is already registered.';
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                $default_phone   = '';
                $default_address = '';
                $default_role    = 'customer';

                $stmt = $conn->prepare(
                    'INSERT INTO Users (name, email, password, phone, address, role) VALUES (?, ?, ?, ?, ?, ?)'
                );
                $stmt->bind_param('ssssss', $name, $email, $hashedPassword, $default_phone, $default_address, $default_role);

                if ($stmt->execute()) {
                    $_SESSION['register_success'] = 'Registration successful! Please log in.';
                    header('Location: login.php');
                    exit();
                } else {
                    $error = 'Registration failed. Please try again.';
                }
                $stmt->close();
            }
            $check->close();
        }
    }

    /* Regenerate CSRF token after POST */
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — Online Book Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .register-card { animation: slideUp .4s ease-out both; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(24px); } to { opacity: 1; transform: translateY(0); } }
        .strength-bar { transition: width .35s ease, background-color .35s ease; }
        .remember-check { accent-color: #f59e0b; }
    </style>
</head>
<body class="bg-[#fdfbf7] min-h-screen flex items-center justify-center font-sans p-4">

<main class="register-card bg-white rounded-3xl shadow-[0_25px_60px_rgba(180,130,50,0.12)] overflow-hidden max-w-5xl w-full grid md:grid-cols-2 border border-[#eae3d2]">

    <!-- ============================================================ -->
    <!-- LEFT PANEL — Branded visual side                              -->
    <!-- ============================================================ -->
    <div class="relative bg-amber-950 text-white p-10 md:p-12 flex flex-col justify-between min-h-[280px] md:min-h-[620px] overflow-hidden">
        <!-- Background image -->
        <div class="absolute inset-0 bg-cover bg-center transition-transform duration-[2s] hover:scale-105"
             style="background-image: url('https://images.unsplash.com/photo-1544947950-fa07a98d237f?auto=format&fit=crop&q=80&w=800');"></div>
        <!-- Gradient overlay -->
        <div class="absolute inset-0 bg-gradient-to-tr from-amber-950/90 via-amber-900/40 to-transparent"></div>

        <!-- Branding -->
        <div class="relative z-10">
            <a href="../index.php" class="inline-flex items-center gap-2 bg-amber-500/90 backdrop-blur-sm px-4 py-2 rounded-full font-black text-sm text-slate-900 shadow-lg border border-amber-400/50">
                <i class="fa-solid fa-book-open"></i> OnlineBookShop
            </a>
        </div>

        <!-- Tagline -->
        <div class="relative z-10 mt-auto">
            <h1 class="text-2xl md:text-3xl font-black leading-snug drop-shadow-lg">
                စာကောင်းပေမွန်<br>ဖတ်ချင်လား?
            </h1>
            <p class="text-amber-200/80 text-sm mt-3 max-w-xs leading-relaxed">
                အကောင့်သစ်ဖွင့်ပြီး သင့်အတွက် စာအုပ်များကို ရှာဖွေပါ။
            </p>
            <!-- Feature bullets -->
            <ul class="mt-5 space-y-2 text-amber-200/70 text-xs">
                <li class="flex items-center gap-2">
                    <i class="fa-solid fa-check text-amber-400 text-[10px]"></i> Free account creation
                </li>
                <li class="flex items-center gap-2">
                    <i class="fa-solid fa-check text-amber-400 text-[10px]"></i> Browse &amp; purchase books online
                </li>
                <li class="flex items-center gap-2">
                    <i class="fa-solid fa-check text-amber-400 text-[10px]"></i> Track your orders in real time
                </li>
            </ul>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- RIGHT PANEL — Registration form                               -->
    <!-- ============================================================ -->
    <div class="p-8 md:p-12 flex flex-col justify-center bg-[#faf8f2]">

        <!-- Header -->
        <div class="mb-6 text-center md:text-left">
            <h2 class="text-2xl md:text-3xl font-black text-slate-800 tracking-tight">အကောင့်အသစ်ဖွင့်ရန်</h2>
            <p class="text-xs text-amber-700 font-semibold mt-1.5">အောက်ပါဖောင်ကို ဖြည့်ပါ</p>
        </div>

        <!-- Error message -->
        <?php if ($error !== ''): ?>
            <div class="bg-red-50 border-l-4 border-red-500 text-red-700 px-4 py-3 rounded-xl mb-5 text-xs font-bold flex items-center gap-2 shadow-sm">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <!-- Success message -->
        <?php if ($success !== ''): ?>
            <div class="bg-green-50 border-l-4 border-green-500 text-green-700 px-4 py-3 rounded-xl mb-5 text-xs font-bold flex items-center gap-2 shadow-sm">
                <i class="fa-solid fa-circle-check"></i>
                <span><?= htmlspecialchars($success) ?></span>
            </div>
        <?php endif; ?>

        <!-- Registration form -->
        <form action="" method="POST" class="space-y-4" id="registerForm" novalidate>
            <!-- CSRF token -->
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <!-- Full Name -->
            <div>
                <label for="name" class="block text-xs font-bold text-slate-700 mb-2">Full Name</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-amber-700/40 pointer-events-none">
                        <i class="fa-solid fa-user text-xs"></i>
                    </span>
                    <input type="text" id="name" name="name"
                           value="<?= htmlspecialchars($old_name) ?>"
                           placeholder="Enter your full name"
                           required autocomplete="name"
                           class="w-full pl-10 pr-4 py-2.5 text-sm rounded-xl border border-amber-200/60 bg-white text-slate-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-400 transition-all duration-200 shadow-sm hover:border-amber-300">
                </div>
                <p id="nameError" class="hidden text-red-500 text-[11px] mt-1 font-semibold"></p>
            </div>

            <!-- Email -->
            <div>
                <label for="email" class="block text-xs font-bold text-slate-700 mb-2">Email</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-amber-700/40 pointer-events-none">
                        <i class="fa-solid fa-envelope text-xs"></i>
                    </span>
                    <input type="email" id="email" name="email"
                           value="<?= htmlspecialchars($old_email) ?>"
                           placeholder="you@example.com"
                           required autocomplete="email"
                           class="w-full pl-10 pr-4 py-2.5 text-sm rounded-xl border border-amber-200/60 bg-white text-slate-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-400 transition-all duration-200 shadow-sm hover:border-amber-300">
                </div>
                <p id="emailError" class="hidden text-red-500 text-[11px] mt-1 font-semibold"></p>
            </div>

            <!-- Password with show/hide + strength bar -->
            <div>
                <label for="password" class="block text-xs font-bold text-slate-700 mb-2">Password</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-amber-700/40 pointer-events-none">
                        <i class="fa-solid fa-lock text-xs"></i>
                    </span>
                    <input type="password" id="password" name="password"
                           placeholder="At least 6 characters"
                           required autocomplete="new-password"
                           class="w-full pl-10 pr-12 py-2.5 text-sm rounded-xl border border-amber-200/60 bg-white text-slate-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-400 transition-all duration-200 shadow-sm hover:border-amber-300">
                    <button type="button" id="togglePassword"
                            class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-amber-700/50 hover:text-amber-700 transition-colors duration-200 focus:outline-none"
                            aria-label="Show or hide password">
                        <i class="fa-solid fa-eye text-xs" id="eyeIcon"></i>
                    </button>
                </div>
                <!-- Strength indicator -->
                <div class="flex items-center gap-2 mt-2">
                    <div class="flex-1 h-1.5 bg-gray-200 rounded-full overflow-hidden">
                        <div id="strengthBar" class="strength-bar h-full w-0 rounded-full"></div>
                    </div>
                    <span id="strengthText" class="text-[10px] font-bold text-gray-400 w-16 text-right"></span>
                </div>
                <p id="passwordError" class="hidden text-red-500 text-[11px] mt-1 font-semibold"></p>
            </div>

            <!-- Confirm Password with show/hide -->
            <div>
                <label for="confirm_password" class="block text-xs font-bold text-slate-700 mb-2">Confirm Password</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-amber-700/40 pointer-events-none">
                        <i class="fa-solid fa-shield-halved text-xs"></i>
                    </span>
                    <input type="password" id="confirm_password" name="confirm_password"
                           placeholder="Re-enter your password"
                           required autocomplete="new-password"
                           class="w-full pl-10 pr-12 py-2.5 text-sm rounded-xl border border-amber-200/60 bg-white text-slate-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-400 transition-all duration-200 shadow-sm hover:border-amber-300">
                    <button type="button" id="toggleConfirm"
                            class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-amber-700/50 hover:text-amber-700 transition-colors duration-200 focus:outline-none"
                            aria-label="Show or hide confirm password">
                        <i class="fa-solid fa-eye text-xs" id="eyeIcon2"></i>
                    </button>
                </div>
                <!-- Match indicator -->
                <div class="flex items-center gap-1.5 mt-1.5">
                    <i id="matchIcon" class="hidden text-xs"></i>
                    <span id="matchText" class="text-[11px] font-semibold hidden"></span>
                </div>
                <p id="confirmError" class="hidden text-red-500 text-[11px] mt-1 font-semibold"></p>
            </div>

            <!-- Terms agreement -->
            <label class="flex items-start gap-2 cursor-pointer select-none">
                <input type="checkbox" name="agree" id="agree" required class="remember-check w-4 h-4 mt-0.5 rounded border-gray-300 cursor-pointer">
                <span class="text-xs text-slate-600 leading-relaxed">
                    I agree to the <a href="#" class="text-amber-700 font-bold hover:underline">Terms of Service</a>
                    and <a href="#" class="text-amber-700 font-bold hover:underline">Privacy Policy</a>
                </span>
            </label>
            <p id="agreeError" class="hidden text-red-500 text-[11px] -mt-2 font-semibold"></p>

            <!-- Submit button -->
            <button type="submit" id="submitBtn"
                    class="w-full bg-[#f0b90b] hover:bg-amber-500 text-slate-900 font-black py-3 px-4 rounded-xl shadow-[0_5px_15px_rgba(240,185,11,0.3)] hover:shadow-[0_8px_25px_rgba(240,185,11,0.4)] transition-all duration-300 transform hover:-translate-y-0.5 active:translate-y-0 active:scale-[0.98] text-sm mt-2 flex items-center justify-center gap-2">
                <i class="fa-solid fa-user-plus"></i>
                <span id="btnText">Register</span>
                <svg id="btnSpinner" class="hidden animate-spin h-4 w-4 text-slate-900" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                </svg>
            </button>
        </form>

        <!-- Login link -->
        <div class="text-center text-xs text-slate-500 font-bold mt-6 pt-4 border-t border-amber-100">
            အကောင့်ရှိပြီးသားလား?
            <a href="login.php" class="text-amber-700 font-black hover:text-amber-800 hover:underline ml-1 transition-colors duration-200">လော့ဂ်အင်ဝင်ရန်</a>
        </div>
    </div>
</main>

<!-- ================================================================ -->
<!-- Vanilla JavaScript — no frameworks                                -->
<!-- ================================================================ -->
<script>
(function () {
    'use strict';

    /* ---- Element references ---- */
    var form          = document.getElementById('registerForm');
    var nameIn        = document.getElementById('name');
    var emailIn       = document.getElementById('email');
    var passIn        = document.getElementById('password');
    var confirmIn     = document.getElementById('confirm_password');
    var agreeIn       = document.getElementById('agree');
    var submitBtn     = document.getElementById('submitBtn');
    var btnText       = document.getElementById('btnText');
    var btnSpin       = document.getElementById('btnSpinner');

    var nameErr       = document.getElementById('nameError');
    var emailErr      = document.getElementById('emailError');
    var passErr       = document.getElementById('passwordError');
    var confirmErr    = document.getElementById('confirmError');
    var agreeErr      = document.getElementById('agreeError');

    var strengthBar   = document.getElementById('strengthBar');
    var strengthText  = document.getElementById('strengthText');
    var matchIcon     = document.getElementById('matchIcon');
    var matchText     = document.getElementById('matchText');

    /* ---- Helpers ---- */
    function show(el, msg) { el.textContent = msg; el.classList.remove('hidden'); }
    function hide(el) { el.classList.add('hidden'); el.textContent = ''; }

    /* ---- Show / Hide Password ---- */
    function setupToggle(inputId, iconId) {
        var input = document.getElementById(inputId);
        var icon  = document.getElementById(iconId);
        icon.parentElement.addEventListener('click', function () {
            var isPass = input.type === 'password';
            input.type = isPass ? 'text' : 'password';
            icon.classList.toggle('fa-eye');
            icon.classList.toggle('fa-eye-slash');
        });
    }
    setupToggle('password', 'eyeIcon');
    setupToggle('confirm_password', 'eyeIcon2');

    /* ---- Password strength meter ---- */
    function evaluateStrength(pw) {
        var score = 0;
        if (pw.length >= 6)  score++;
        if (pw.length >= 10) score++;
        if (/[A-Z]/.test(pw) && /[a-z]/.test(pw)) score++;
        if (/\d/.test(pw))   score++;
        if (/[^A-Za-z0-9]/.test(pw)) score++;
        return score; /* 0-5 */
    }

    function updateStrength() {
        var pw = passIn.value;
        if (pw === '') {
            strengthBar.style.width = '0';
            strengthText.textContent = '';
            strengthText.className = 'text-[10px] font-bold text-gray-400 w-16 text-right';
            return;
        }
        var score = evaluateStrength(pw);
        var levels = [
            { w: '10%', bg: 'bg-red-500',     label: 'Weak' },
            { w: '25%', bg: 'bg-orange-500',   label: 'Fair' },
            { w: '50%', bg: 'bg-yellow-500',   label: 'Good' },
            { w: '75%', bg: 'bg-lime-500',     label: 'Strong' },
            { w: '100%', bg: 'bg-green-500',   label: 'Very strong' }
        ];
        var lvl = levels[Math.min(score, levels.length) - 1] || levels[0];
        strengthBar.style.width = lvl.w;
        strengthBar.className = 'strength-bar h-full rounded-full ' + lvl.bg;
        strengthText.textContent = lvl.label;
        strengthText.className = 'text-[10px] font-bold w-16 text-right ' + (score <= 1 ? 'text-red-500' : score <= 3 ? 'text-yellow-600' : 'text-green-600');
    }

    passIn.addEventListener('input', function () {
        hide(passErr);
        updateStrength();
        checkMatch();
    });

    /* ---- Confirm-password match indicator ---- */
    function checkMatch() {
        var pw  = passIn.value;
        var cpw = confirmIn.value;
        if (cpw === '') {
            matchIcon.classList.add('hidden');
            matchText.classList.add('hidden');
            return;
        }
        var match = pw === cpw;
        matchIcon.className = 'text-xs ' + (match ? 'fa-solid fa-circle-check text-green-500' : 'fa-solid fa-circle-xmark text-red-500');
        matchText.className = 'text-[11px] font-semibold ' + (match ? 'text-green-600' : 'text-red-500');
        matchText.textContent = match ? 'Passwords match' : 'Passwords do not match';
        matchIcon.classList.remove('hidden');
        matchText.classList.remove('hidden');
    }

    confirmIn.addEventListener('input', function () { hide(confirmErr); checkMatch(); });

    /* ---- Live validation on blur ---- */
    nameIn.addEventListener('blur', function () {
        if (nameIn.value.trim().length < 2) show(nameErr, 'Name must be at least 2 characters.');
        else hide(nameErr);
    });
    emailIn.addEventListener('blur', function () {
        if (emailIn.value.trim() === '') show(emailErr, 'Email is required.');
        else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailIn.value.trim())) show(emailErr, 'Please enter a valid email.');
        else hide(emailErr);
    });
    passIn.addEventListener('blur', function () {
        if (passIn.value === '') show(passErr, 'Password is required.');
        else if (passIn.value.length < 6) show(passErr, 'Password must be at least 6 characters.');
        else hide(passErr);
    });

    /* Clear errors while typing */
    nameIn.addEventListener('input',    function () { hide(nameErr); });
    emailIn.addEventListener('input',   function () { hide(emailErr); });
    agreeIn.addEventListener('change',  function () { hide(agreeErr); });

    /* ---- Submit validation + spinner ---- */
    form.addEventListener('submit', function (e) {
        var valid = true;

        if (nameIn.value.trim().length < 2) { show(nameErr, 'Name must be at least 2 characters.'); valid = false; }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailIn.value.trim())) { show(emailErr, 'Please enter a valid email.'); valid = false; }
        if (passIn.value.length < 6) { show(passErr, 'Password must be at least 6 characters.'); valid = false; }
        if (passIn.value !== confirmIn.value) { show(confirmErr, 'Passwords do not match.'); valid = false; }
        if (!agreeIn.checked) { show(agreeErr, 'You must agree to the terms.'); valid = false; }

        if (!valid) { e.preventDefault(); return; }

        btnText.textContent = 'Creating account...';
        btnSpin.classList.remove('hidden');
        submitBtn.disabled = true;
    });
})();
</script>
</body>
</html>
