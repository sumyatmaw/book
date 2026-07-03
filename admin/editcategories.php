<?php
session_start();
require_once '../config/db.php';

// Admin login check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$error = "";
$success = "";

// 1) URL က id ကိုယူ
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: categories.php");
    exit();
}

$id = (int) $_GET['id'];

// 2) အရင် category data ကိုယူ
$stmt = $conn->prepare("SELECT * FROM Categories WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: categories.php");
    exit();
}

$category = $result->fetch_assoc();
$stmt->close();

// 3) Form submit လုပ်ရင် update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_name = trim($_POST['category_name']);

    if (empty($category_name)) {
        $error = "Category name is required!";
    } else {
        $update = $conn->prepare("UPDATE Categories SET category_name = ? WHERE id = ?");
        $update->bind_param("si", $category_name, $id);

        if ($update->execute()) {
            header("Location: categories.php");
            exit();
        } else {
            $error = "Failed to update category!";
        }

        $update->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Category - Online Book Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen flex flex-col">

    <?php include '../auth/header.php'; ?>

    <div class="max-w-7xl mx-auto px-4 py-8 flex-1 w-full">

    <!-- Main Container -->
    <div class="max-w-2xl mx-auto mt-10 bg-white shadow-lg rounded-2xl p-8">
        <h2 class="text-2xl font-bold text-gray-800 mb-6">Update Category</h2>

        <!-- Error Message -->
        <?php if (!empty($error)): ?>
            <div class="mb-4 p-3 rounded-lg bg-red-100 text-red-700 border border-red-300">
                <?= $error; ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST" class="space-y-5">

            <!-- ID -->
            <!-- <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">ID</label>
                <input type="text" value="<?= $category['id']; ?>" readonly
                       class="w-full px-4 py-3 border border-gray-300 rounded-xl bg-gray-100 text-gray-500 cursor-not-allowed">
            </div> -->

            <!-- Category Name -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Category Name</label>
                <input type="text" name="category_name"
                       value="<?= htmlspecialchars($category['category_name']); ?>"
                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:outline-none"
                       required>
            </div>

            <!-- Buttons -->
            <div class="flex gap-4">
                <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-xl font-medium shadow">
                    Update
                </button>

                <a href="categories.php"
                   class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-6 py-3 rounded-xl font-medium shadow">
                    Cancel
                </a>
            </div>
        </form>
    <?php include '../auth/footer.php'; ?>