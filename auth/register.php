<?php
session_start();
require_once '../config/db.php';

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "All fields are required.";
    }
    elseif ($password !== $confirm_password) {
        $error = "Password and Confirm Password do not match.";
    }
    else {
        $checkStmt = $conn->prepare("SELECT id FROM Users WHERE email = ? ");
        $checkStmt->bind_param("s", $email);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows > 0) {
            $error = "This email is already registered.";
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            $default_phone = "";
            $default_address = "";
            $default_role = "customer";

            // VALUES ထဲတွင် ? (၆) ခု အတိအကျ ပြောင်းလဲပြင်ဆင်ထားပါသည်
            $stmt = $conn->prepare("INSERT INTO Users (name, email, password, phone, address, role) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $name, $email, $hashedPassword, $default_phone, $default_address, $default_role);

            if ($stmt->execute()) {
                $_SESSION['register_success'] = "Registration successful! Please login.";
                header("Location: ../auth/login.php"); // login.php နှင့် တစ် folder တည်းရှိသဖြင့် လမ်းကြောင်းညှိထားသည်
                exit();
            } else {
                $error = "Registration failed. Please try again.";
            }
            $stmt->close();
        }
        $checkStmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Book Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-[#f5f7fa] to-[#e4e8f0] min-h-screen flex items-center justify-center font-sans p-4">

    <div class="bg-white rounded-3xl shadow-2xl overflow-hidden max-w-5xl w-full grid md:grid-cols-2">

        <div class="bg-gradient-to-br from-[#1e63e9] to-[#2575fc] text-white p-12 flex flex-col justify-center min-h-[400px] md:min-h-[550px]">
            <h1 class="text-4xl font-bold mb-4 tracking-wide">Create Account</h1>
            <p class="text-lg text-white/80 leading-relaxed">
                Register as a customer to explore books, add items to cart, place orders and track deliveries.
            </p>
        </div>

        <div class="p-8 md:p-12 flex flex-col justify-center">
            <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center mb-4 mx-auto">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#2575fc" class="w-7 h-7">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 20.625a7.125 7.125 0 0114.25 0"/>
                </svg>
            </div>

            <h2 class="text-3xl font-bold text-center text-[#2575fc] mb-2">Customer Register</h2>
            <p class="text-center text-gray-500 text-sm mb-6">Create your account to start shopping books.</p>

            <?php if (!empty($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl text-center mb-4 text-sm">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-600 mb-1">Full Name</label>
                    <input type="text" name="name" placeholder="Enter your full name" required
                           value="<?php echo isset($name) ? htmlspecialchars($name) : ''; ?>"
                           class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-[#2575fc] focus:border-transparent transition-all">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-600 mb-1">Email</label>
                    <input type="email" name="email" placeholder="Enter your email" required
                           value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>"
                           class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-[#2575fc] focus:border-transparent transition-all">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-600 mb-1">Password</label>
                    <input type="password" name="password" placeholder="Enter password" required
                           class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-[#2575fc] focus:border-transparent transition-all">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-600 mb-1">Confirm Password</label>
                    <input type="password" name="confirm_password" placeholder="Confirm password" required
                           class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-[#2575fc] focus:border-transparent transition-all">
                </div>

                <button type="submit"
                        class="w-full bg-[#2575fc] hover:bg-[#1a5cc7] text-white font-bold py-3 rounded-xl shadow-lg transition-all transform hover:-translate-y-0.5 active:translate-y-0">
                    Register
                </button>

                <div class="text-center text-sm text-gray-500 mt-4">
                    Already have an account?
                    <a href="login.php" class="text-[#2575fc] font-bold hover:underline ml-1">Login</a>
                </div>
            </form>
        </div>

    </div>

</body>
</html>