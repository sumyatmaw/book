<?php
/**
 * Registration Page — Online Book Shop (Compact & Fixed Layout)
 */

session_start();
require_once '../config/db.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error  = '';
$success = '';
$old_name  = '';
$old_email = '';

if (!empty($_SESSION['register_error'])) {
    $error = $_SESSION['register_error'];
    unset($_SESSION['register_error']);
}
if (!empty($_SESSION['register_success'])) {
    $success = $_SESSION['register_success'];
    unset($_SESSION['register_success']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {
        $name             = trim($_POST['name']             ?? '');
        $email            = trim($_POST['email']            ?? '');
        $password         = $_POST['password']              ?? '';
        $confirm_password = $_POST['confirm_password']      ?? '';

        $old_name  = $name;
        $old_email = $email;

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
                    $_SESSION['login_success'] = 'အကောင့်ဖွင့်ခြင်း အောင်မြင်ပါသည်။ ကျေးဇူးပြု၍ လော့ဂ်အင်ဝင်ပါ။';
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
        @keyframes slideUp { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
        .remember-check { accent-color: #f59e0b; }
    </style>
</head>
<body class="bg-[#fdfbf7] min-h-screen flex items-center justify-center font-sans p-3">

<main class="register-card bg-white rounded-2xl shadow-[0_20px_50px_rgba(180,130,50,0.1)] overflow-hidden max-w-4xl w-full grid md:grid-cols-2 border border-[#eae3d2] md:items-stretch">

    <!-- LEFT PANEL — Image Side (Always Fixed and Beautiful) -->
    <div class="relative bg-amber-950 text-white p-6 md:p-10 flex flex-col justify-between min-h-[180px] md:min-h-[520px] overflow-hidden">
        <div class="absolute inset-0 h-full bg-cover bg-center"
             style="background-image: url('https://images.unsplash.com/photo-1544947950-fa07a98d237f?auto=format&fit=crop&q=80&w=800');"></div>
        <div class="absolute inset-0 bg-gradient-to-tr from-amber-950/90 via-amber-900/40 to-transparent"></div>

        <div class="relative z-10">
            <a href="../index.php" class="inline-flex items-center gap-1.5 bg-amber-500/90 backdrop-blur-sm px-3 py-1.5 rounded-full font-black text-xs text-slate-900 shadow-md">
                <i class="fa-solid fa-book-open text-[11px]"></i> OnlineBookShop
            </a>
        </div>

        <div class="relative z-10 mt-auto">
            <h1 class="text-lg md:text-2xl font-black leading-tight drop-shadow-md">
                စာကောင်းပေမွန်<br class="hidden md:block">ဖတ်ချင်လား?
            </h1>
            <p class="text-amber-200/80 text-[11px] md:text-xs mt-1 max-w-xs leading-relaxed">
                အကောင့်သစ်ဖွင့်ပြီး သင့်အတွက် စာအုပ်များကို ရှာဖွေပါ။
            </p>
        </div>
    </div>

    <!-- RIGHT PANEL — Compact Registration Form -->
    <div class="p-5 md:p-10 flex flex-col justify-center bg-[#faf8f2]">

        <div class="mb-4 text-center md:text-left">
            <h2 class="text-lg md:text-2xl font-black text-slate-800 tracking-tight">အကောင့်အသစ်ဖွင့်ရန်</h2>
            <p class="text-[11px] text-amber-700 font-semibold mt-0.5">အောက်ပါဖောင်ကို ဖြည့်ပါ</p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="bg-red-50 border-l-4 border-red-500 text-red-700 px-3 py-2 rounded-xl mb-3 text-[11px] font-bold flex items-center gap-2 shadow-sm">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($success !== ''): ?>
            <div class="bg-green-50 border-l-4 border-green-500 text-green-700 px-3 py-2 rounded-xl mb-3 text-[11px] font-bold flex items-center gap-2 shadow-sm">
                <i class="fa-solid fa-circle-check"></i>
                <span><?= htmlspecialchars($success) ?></span>
            </div>
        <?php endif; ?>

        <form action="" method="POST" class="space-y-3" id="registerForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <!-- Full Name -->
            <div>
                <label for="name" class="block text-[11px] font-bold text-slate-700 mb-1">Full Name</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-amber-700/40 pointer-events-none">
                        <i class="fa-solid fa-user text-[11px]"></i>
                    </span>
                    <input type="text" id="name" name="name" value="<?= htmlspecialchars($old_name) ?>" placeholder="Enter your full name" required autocomplete="name"
                           class="w-full pl-9 pr-3 py-1.5 text-xs rounded-xl border border-amber-200/60 bg-white text-slate-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-amber-500/40 focus:border-amber-400 transition-all duration-200 shadow-sm">
                </div>
                <p id="nameError" class="hidden text-red-500 text-[10px] font-semibold mt-0.5"></p>
            </div>

            <!-- Email -->
            <div>
                <label for="email" class="block text-[11px] font-bold text-slate-700 mb-1">Email</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-amber-700/40 pointer-events-none">
                        <i class="fa-solid fa-envelope text-[11px]"></i>
                    </span>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($old_email) ?>" placeholder="you@example.com" required autocomplete="email"
                           class="w-full pl-9 pr-3 py-1.5 text-xs rounded-xl border border-amber-200/60 bg-white text-slate-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-amber-500/40 focus:border-amber-400 transition-all duration-200 shadow-sm">
                </div>
                <p id="emailError" class="hidden text-red-500 text-[10px] font-semibold mt-0.5"></p>
            </div>

            <!-- Password (Strength indicators completely removed) -->
            <div>
                <label for="password" class="block text-[11px] font-bold text-slate-700 mb-1">Password</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-amber-700/40 pointer-events-none">
                        <i class="fa-solid fa-lock text-[11px]"></i>
                    </span>
                    <input type="password" id="password" name="password" placeholder="At least 6 characters" required autocomplete="new-password"
                           class="w-full pl-9 pr-10 py-1.5 text-xs rounded-xl border border-amber-200/60 bg-white text-slate-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-amber-500/40 focus:border-amber-400 transition-all duration-200 shadow-sm">
                    <button type="button" id="togglePassword" class="absolute inset-y-0 right-0 flex items-center pr-3 text-amber-700/50 hover:text-amber-700 focus:outline-none">
                        <i class="fa-solid fa-eye text-[11px]" id="eyeIcon"></i>
                    </button>
                </div>
                <p id="passwordError" class="hidden text-red-500 text-[10px] font-semibold mt-0.5"></p>
            </div>

            <!-- Confirm Password -->
            <div>
                <label for="confirm_password" class="block text-[11px] font-bold text-slate-700 mb-1">Confirm Password</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-amber-700/40 pointer-events-none">
                        <i class="fa-solid fa-shield-halved text-[11px]"></i>
                    </span>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter your password" required autocomplete="new-password"
                           class="w-full pl-9 pr-10 py-1.5 text-xs rounded-xl border border-amber-200/60 bg-white text-slate-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-amber-500/40 focus:border-amber-400 transition-all duration-200 shadow-sm">
                    <button type="button" id="toggleConfirm" class="absolute inset-y-0 right-0 flex items-center pr-3 text-amber-700/50 hover:text-amber-700 focus:outline-none">
                        <i class="fa-solid fa-eye text-[11px]" id="eyeIcon2"></i>
                    </button>
                </div>
                <p id="confirmError" class="hidden text-red-500 text-[10px] font-semibold mt-0.5"></p>
            </div>

            <!-- Terms agreement -->
            <label class="flex items-start gap-2 cursor-pointer select-none pt-1">
                <input type="checkbox" name="agree" id="agree" required class="remember-check w-3.5 h-3.5 mt-0.5 rounded border-gray-300 cursor-pointer">
                <span class="text-[11px] text-slate-600 leading-tight">
                    I agree to the <a href="#" class="text-amber-700 font-bold hover:underline">Terms</a> and <a href="#" class="text-amber-700 font-bold hover:underline">Privacy Policy</a>
                </span>
            </label>
            <p id="agreeError" class="hidden text-red-500 text-[10px] font-semibold mt-0.5"></p>

            <!-- Submit button -->
            <button type="submit" id="submitBtn"
                    class="w-full bg-[#f0b90b] hover:bg-amber-500 text-slate-900 font-black py-2 px-4 rounded-xl shadow-md transition-all duration-300 transform hover:-translate-y-0.5 text-xs flex items-center justify-center gap-2 mt-2">
                <i class="fa-solid fa-user-plus"></i>
                <span id="btnText">Register</span>
                <svg id="btnSpinner" class="hidden animate-spin h-3.5 w-3.5 text-slate-900" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                </svg>
            </button>
        </form>

        <!-- Login link -->
        <div class="text-center text-[11px] text-slate-500 font-bold mt-4 pt-3 border-t border-amber-100">
            အကောင့်ရှိပြီးသားလား?
            <a href="login.php" class="text-amber-700 font-black hover:text-amber-800 hover:underline ml-1">လော့ဂ်အင်ဝင်ရန်</a>
        </div>
    </div>
</main>

<script>
(function () {
    'use strict';

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

    function show(el, msg) { el.textContent = msg; el.classList.remove('hidden'); }
    function hide(el) { el.classList.add('hidden'); el.textContent = ''; }

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

    /* Live input listeners to clear errors */
    nameIn.addEventListener('input',    function () { hide(nameErr); });
    emailIn.addEventListener('input',   function () { hide(emailErr); });
    passIn.addEventListener('input',    function () { hide(passErr); });
    confirmIn.addEventListener('input', function () { hide(confirmErr); });
    agreeIn.addEventListener('change',  function () { hide(agreeErr); });

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