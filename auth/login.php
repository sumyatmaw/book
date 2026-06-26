<?php
session_start();
require_once '../config/db.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    // =========================
    // 1) ADMIN LOGIN (fixed)
    // =========================
    if ($email === 'admin@gmail.com') {
        if ($password === '123123') {
            $_SESSION['user_role'] = 'admin';
            $_SESSION['user_email'] = $email;

            header("Location: ../admin/dashboard.php");
            exit();
        } else {
            $error = "Invalid email or password";
        }
    }

    // =========================
    // 2) CUSTOMER LOGIN
    // =========================
    else {
        $stmt = $conn->prepare("SELECT id, name, email, password FROM users WHERE email = ? AND role = 'customer'");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        // Customer account ရှိ/မရှိ စစ်
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();

            // register.php မှာ password_hash() နဲ့သိမ်းထားရမယ်
            if (password_verify($password, $row['password'])) {
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['user_name'] = $row['name'];
                $_SESSION['user_email'] = $row['email'];
                $_SESSION['user_role'] = 'customer';

                header("Location: ../user/user_dashboard.php");
                exit();
            } else {
                $error = "Invalid email or password";
            }
        } else {
            // customer account မရှိရင် register page ကိုပို့
            header("Location: register.php");
            exit();
        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Book Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-[#f5f7fa] to-[#e4e8f0] min-h-screen flex items-center justify-center font-sans p-4">

    <div class="bg-white rounded-3xl shadow-2xl overflow-hidden max-w-4xl w-full grid md:grid-cols-2">

        <!-- Left Panel -->
        <div class="bg-gradient-to-br from-[#1e63e9] to-[#2575fc] text-white p-12 flex flex-col justify-center min-h-[350px] md:min-h-[450px]">
            <h1 class="text-4xl font-bold mb-4 tracking-wide">Online Book Shop</h1>
            <p class="text-lg text-white/80 leading-relaxed">
                Login to access your dashboard and explore books, orders, and more.
            </p>
        </div>

        <!-- Right Panel -->
        <div class="p-8 md:p-12 flex flex-col justify-center">
            <h2 class="text-3xl font-bold text-center text-[#2575fc] mb-6">Login</h2>

            <?php if (!empty($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl text-center mb-4 text-sm">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST" class="space-y-5">
                <div>
                    <label class="block text-sm font-semibold text-gray-600 mb-1">Email</label>
                    <input type="email" name="email" placeholder="Enter your email" required
                           class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-[#2575fc] focus:border-transparent transition-all">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-600 mb-1">Password</label>
                    <input type="password" name="password" placeholder="Enter your password" required
                           class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-[#2575fc] focus:border-transparent transition-all">
                </div>

                <button type="submit"
                        class="w-full bg-[#2575fc] hover:bg-[#1a5cc7] text-white font-bold py-3 rounded-xl shadow-lg transition-all transform hover:-translate-y-0.5 active:translate-y-0">
                    Login
                </button>

                <div class="text-center text-sm text-gray-500 mt-4">
                    Don't have an account?
                    <a href="register.php" class="text-[#2575fc] font-bold hover:underline ml-1">Register</a>
                </div>
            </form>
        </div>

    </div>

</body>
</html>