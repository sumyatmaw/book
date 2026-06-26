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

// Category list ယူရန်
$categories = $conn->query("SELECT * FROM Categories ORDER BY category_name ASC");

// Admin user id (session ထဲမှာရှိရင်ယူမယ်, မရှိရင် 1 သတ်မှတ်)
$admin_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id     = $admin_id;
    $category_id = intval($_POST['category_id']);
    $title       = trim($_POST['title']);
    $author      = trim($_POST['author']);
    $price       = trim($_POST['price']);
    $stock       = trim($_POST['stock']);
    $description = trim($_POST['description']);

    $book_image = "";

    // Validation
    if (empty($category_id) || empty($title) || empty($author) || empty($price) || empty($stock) || empty($description)) {
        $error = "Please fill in all fields.";
    } else {
        // Image Upload
        if (isset($_FILES['book_image']) && $_FILES['book_image']['error'] === 0) {
            $upload_dir = "../uploads/";

            // uploads folder မရှိရင် create
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $image_name = time() . "_" . basename($_FILES['book_image']['name']);
            $target_file = $upload_dir . $image_name;

            $allowed_types = ['jpg', 'jpeg', 'png', 'webp'];
            $image_ext = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

            if (!in_array($image_ext, $allowed_types)) {
                $error = "Only JPG, JPEG, PNG, WEBP files are allowed.";
            } else {
                if (move_uploaded_file($_FILES['book_image']['tmp_name'], $target_file)) {
                    $book_image = $image_name;
                } else {
                    $error = "Failed to upload image.";
                }
            }
        } else {
            $error = "Book image is required.";
        }

        // Insert to database
        if (empty($error)) {
            $stmt = $conn->prepare("INSERT INTO Books (user_id, category_id, title, author, price, stock, book_image, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iissdiss", $user_id, $category_id, $title, $author, $price, $stock, $book_image, $description);

            if ($stmt->execute()) {
                $message = "Book added successfully!";
            } else {
                $error = "Failed to add book!";
            }

            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Book</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">

    <!-- Header -->
    <div class="bg-blue-600 text-white px-8 py-5 flex justify-between items-center shadow-md">
        <h1 class="text-2xl font-bold">Add Book</h1>
        <div class="flex gap-3">
            <a href="dashboard.php" class="bg-white text-blue-600 px-4 py-2 rounded-lg font-medium hover:bg-gray-100">
                Dashboard
            </a>
            <a href="books.php" class="bg-white text-blue-600 px-4 py-2 rounded-lg font-medium hover:bg-gray-100">
                View Books
            </a>
        </div>
    </div>

    <!-- Main Container -->
    <div class="max-w-3xl mx-auto mt-10 bg-white shadow-lg rounded-2xl p-8">
        <h2 class="text-2xl font-bold text-gray-800 mb-6">Add New Book</h2>

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

        <form action="" method="POST" enctype="multipart/form-data" class="space-y-5">

            <!-- Admin ID -->
            <!-- <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Admin ID</label>
                <input type="text" value="<?= $admin_id; ?>" readonly
                       class="w-full px-4 py-3 border border-gray-300 rounded-xl bg-gray-100 text-gray-500 cursor-not-allowed">
            </div> -->

            <!-- Category -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                <select name="category_id" required
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    <option value="">-- Select Category --</option>
                    <?php if ($categories && $categories->num_rows > 0): ?>
                        <?php while ($cat = $categories->fetch_assoc()): ?>
                            <option value="<?= $cat['id']; ?>">
                                <?= htmlspecialchars($cat['category_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>

            <!-- Title -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Book Title</label>
                <input type="text" name="title" placeholder="Enter book title" required
                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>

            <!-- Author -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Author</label>
                <input type="text" name="author" placeholder="Enter author name" required
                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>

            <!-- Price -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Price</label>
                <input type="number" step="0.01" name="price" placeholder="Enter price" required
                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>

            <!-- Stock -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Stock</label>
                <input type="number" name="stock" placeholder="Enter stock quantity" required
                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>

            <!-- Book Image -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Book Image</label>
                <input type="file" name="book_image" accept=".jpg,.jpeg,.png,.webp" required
                       class="w-full px-4 py-3 border border-gray-300 rounded-xl bg-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>

            <!-- Description -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                <textarea name="description" rows="5" placeholder="Enter book description" required
                          class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:outline-none"></textarea>
            </div>

            <!-- Buttons -->
            <div class="flex gap-4">
                <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-xl font-medium shadow">
                    Add Book
                </button>

                <a href="books.php"
                   class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-6 py-3 rounded-xl font-medium shadow">
                    Cancel
                </a>
            </div>
        </form>
    </div>

</body>
</html>