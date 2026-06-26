<?php
session_start();
require_once '../config/db.php';

// Admin login check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$message = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_name = trim($_POST['category_name']);

    if (empty($category_name)) {
        $error = "Category name is required!";
    } else {
        // Insert into Categories table
        $stmt = $conn->prepare("INSERT INTO Categories (category_name) VALUES (?)");
        $stmt->bind_param("s", $category_name);

        if ($stmt->execute()) {
            $message = "Category added successfully!";
        } else {
            $error = "Failed to add category!";
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
    <title>Add Category</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">

    <!-- Header -->
    <div class="bg-blue-600 text-white px-8 py-5 flex justify-between items-center shadow-md">
        <h1 class="text-2xl font-bold">Add Category</h1>
        <div class="flex gap-3">
            <a href="dashboard.php" class="bg-white text-blue-600 px-4 py-2 rounded-lg font-medium hover:bg-gray-100">
                Dashboard
            </a>
            <a href="categories.php" class="bg-white text-blue-600 px-4 py-2 rounded-lg font-medium hover:bg-gray-100">
                View Categories
            </a>
        </div>
    </div>

    <!-- Main Container -->
    <div class="max-w-2xl mx-auto mt-10 bg-white shadow-lg rounded-2xl p-8">
        <h2 class="text-2xl font-bold text-gray-800 mb-6">Add New Category</h2>

        <!-- Success Message -->
        <?php if (!empty($message)): ?>
            <div class="mb-4 p-3 rounded-lg bg-green-100 text-green-700 border border-green-300">
                <?= $message; ?>
            </div>
        <?php endif; ?>

        <!-- Error Message -->
        <?php if (!empty($error)): ?>
            <div class="mb-4 p-3 rounded-lg bg-red-100 text-red-700 border border-red-300">
                <?= $error; ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST" class="space-y-5">

            <!-- ID Field (readonly just for display) -->
            <!-- <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">ID</label>
                <input type="text" value="Auto Generate" readonly
                       class="w-full px-4 py-3 border border-gray-300 rounded-xl bg-gray-100 text-gray-500 cursor-not-allowed">
            </div> -->

            <!-- Category Name -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Category Name</label>
                <input type="text" name="category_name" placeholder="Enter category name"
                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:outline-none"
                       required>
            </div>

            <!-- Buttons -->
            <div class="flex gap-4">
                <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-xl font-medium shadow">
                    Add
                </button>

                <a href="categories.php"
                   class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-6 py-3 rounded-xl font-medium shadow">
                    Cancel
                </a>
            </div>
        </form>
    </div>

</body>
</html>