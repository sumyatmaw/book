<?php
/**
 * Logout Page — Online Book Shop
 *
 * Steps:
 *   1. Clear all session variables
 *   2. Destroy the session
 *   3. Clear "remember me" cookies
 *   4. Show a styled confirmation page with auto-redirect
 */

session_start();

/* Clear every session variable */
$_SESSION = [];

/* Delete the session cookie if it exists */
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

/* Destroy the session on the server */
session_destroy();

/* Clear "remember me" cookies */
setcookie('remember_user', '', time() - 3600, '/');
setcookie('remember_admin', '', time() - 3600, '/');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logged Out — Online Book Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .logout-card { animation: fadeUp .5s ease-out both; }
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px) scale(.97); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }
        .pulse-ring {
            animation: pulseRing 2s ease-out infinite;
        }
        @keyframes pulseRing {
            0%   { box-shadow: 0 0 0 0 rgba(245,158,11,.4); }
            70%  { box-shadow: 0 0 0 18px rgba(245,158,11,0); }
            100% { box-shadow: 0 0 0 0 rgba(245,158,11,0); }
        }
    </style>
</head>
<body class="bg-[#fdfbf7] min-h-screen flex items-center justify-center font-sans p-4">

<div class="logout-card bg-white rounded-3xl shadow-[0_25px_60px_rgba(180,130,50,0.12)] overflow-hidden max-w-md w-full border border-[#eae3d2]">

    <!-- Top accent bar -->
    <div class="h-1.5 bg-gradient-to-r from-amber-400 via-amber-500 to-amber-600"></div>

    <!-- Content -->
    <div class="p-10 md:p-14 flex flex-col items-center text-center">

        <!-- Animated icon -->
        <div class="pulse-ring w-20 h-20 rounded-full bg-amber-50 flex items-center justify-center mb-6">
            <i class="fa-solid fa-right-from-bracket text-3xl text-amber-500"></i>
        </div>

        <!-- Heading -->
        <h1 class="text-2xl md:text-3xl font-black text-slate-800 tracking-tight mb-2">
            Logged Out
        </h1>
        <p class="text-sm text-slate-500 font-medium leading-relaxed max-w-xs mb-8">
            You have been successfully logged out of your account.
            <br>
            <span class="text-amber-700 font-bold">Thank you for visiting Online Book Shop!</span>
        </p>

        <!-- Auto-redirect countdown -->
        <div class="bg-amber-50 border border-amber-100 rounded-xl px-4 py-2.5 mb-8">
            <p class="text-xs text-amber-800 font-semibold">
                <i class="fa-solid fa-clock mr-1"></i>
                Redirecting to login in <span id="countdown" class="font-black text-amber-600">5</span> seconds...
            </p>
        </div>

        <!-- Action buttons -->
        <div class="flex flex-col sm:flex-row gap-3 w-full">
            <a href="login.php"
               class="flex-1 bg-[#f0b90b] hover:bg-amber-500 text-slate-900 font-black py-3 px-5 rounded-xl shadow-[0_5px_15px_rgba(240,185,11,0.3)] hover:shadow-[0_8px_25px_rgba(240,185,11,0.4)] transition-all duration-300 transform hover:-translate-y-0.5 active:translate-y-0 text-sm flex items-center justify-center gap-2">
                <i class="fa-solid fa-right-to-bracket"></i> Login Again
            </a>
            <a href="../index.php"
               class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold py-3 px-5 rounded-xl shadow-sm transition-all duration-300 transform hover:-translate-y-0.5 active:translate-y-0 text-sm flex items-center justify-center gap-2">
                <i class="fa-solid fa-house"></i> Back to Home
            </a>
        </div>
    </div>

    <!-- Bottom brand -->
    <div class="bg-[#faf8f2] border-t border-amber-100 px-6 py-4 text-center">
        <a href="../index.php" class="inline-flex items-center gap-2 text-xs font-bold text-slate-500 hover:text-amber-700 transition-colors duration-200">
            <i class="fa-solid fa-book-open text-amber-500"></i>
            OnlineBookShop
        </a>
    </div>
</div>

<script>
(function () {
    var seconds = 5;
    var el = document.getElementById('countdown');
    var timer = setInterval(function () {
        seconds--;
        el.textContent = seconds;
        if (seconds <= 0) {
            clearInterval(timer);
            window.location.href = 'login.php';
        }
    }, 1000);
})();
</script>

</body>
</html>
