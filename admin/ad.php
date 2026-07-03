<?php
session_start();
require_once "../config/db.php";

// Admin Login Check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

// Count Data
$books = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM Books"));
$categories = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM Categories"));
$customers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM Users WHERE role='customer'"));
$orders = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM Orders"));
$payments = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM Payment"));
$deliveries = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM Delivery"));
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard - Online Book Shop</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="bg-gray-100 min-h-screen flex flex-col">

<?php include '../auth/header.php'; ?>

<div class="flex">

    <!-- Sidebar -->
    <div class="w-64 min-h-screen bg-blue-900 text-white">

        <h1 class="text-2xl font-bold text-center py-6 border-b border-blue-700">
            📚 Book Shop
        </h1>

        <ul class="mt-6 space-y-2">

            <li>
                <a href="dashboard.php" class="block px-6 py-3 hover:bg-blue-700">
                    Dashboard
                </a>
            </li>

            <li>
                <a href="books.php" class="block px-6 py-3 hover:bg-blue-700">
                    Manage Books
                </a>
            </li>

            <li>
                <a href="categories.php" class="block px-6 py-3 hover:bg-blue-700">
                    Categories
                </a>
            </li>

            <li>
                <a href="orders.php" class="block px-6 py-3 hover:bg-blue-700">
                    Orders
                </a>
            </li>

            <li>
                <a href="paymentmethod.php" class="block px-6 py-3 hover:bg-blue-700">
                    Payment Methods
                </a>
            </li>

            <li>
                <a href="payments.php" class="block px-6 py-3 hover:bg-blue-700">
                    Customer Payments
                </a>
            </li>

            <li>
                <a href="delivery.php" class="block px-6 py-3 hover:bg-blue-700">
                    Delivery
                </a>
            </li>

            <li>
                <a href="../logout.php" class="block px-6 py-3 hover:bg-red-600">
                    Logout
                </a>
            </li>

        </ul>

    </div>

    <!-- Main Content -->
    <div class="flex-1 p-8">

        <h2 class="text-3xl font-bold mb-8">
            Welcome Admin 👋
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

            <div class="bg-white rounded-xl shadow p-6">
                <h3 class="text-lg font-semibold">Books</h3>
                <p class="text-4xl font-bold text-blue-600 mt-3">
                    <?php echo $books['total']; ?>
                </p>
            </div>

            <div class="bg-white rounded-xl shadow p-6">
                <h3 class="text-lg font-semibold">Categories</h3>
                <p class="text-4xl font-bold text-green-600 mt-3">
                    <?php echo $categories['total']; ?>
                </p>
            </div>

            <div class="bg-white rounded-xl shadow p-6">
                <h3 class="text-lg font-semibold">Customers</h3>
                <p class="text-4xl font-bold text-purple-600 mt-3">
                    <?php echo $customers['total']; ?>
                </p>
            </div>

            <div class="bg-white rounded-xl shadow p-6">
                <h3 class="text-lg font-semibold">Orders</h3>
                <p class="text-4xl font-bold text-orange-500 mt-3">
                    <?php echo $orders['total']; ?>
                </p>
            </div>

            <div class="bg-white rounded-xl shadow p-6">
                <h3 class="text-lg font-semibold">Payments</h3>
                <p class="text-4xl font-bold text-red-500 mt-3">
                    <?php echo $payments['total']; ?>
                </p>
            </div>

            <div class="bg-white rounded-xl shadow p-6">
                <h3 class="text-lg font-semibold">Delivery</h3>
                <p class="text-4xl font-bold text-cyan-600 mt-3">
                    <?php echo $deliveries['total']; ?>
                </p>
            </div>

        </div>

    </div>

</div>

<?php include '../auth/footer.php'; ?>