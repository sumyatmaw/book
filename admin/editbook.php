<?php
session_start();
require_once '../config/db.php';

// Admin login check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$error = "";
$message = "";

// Book ID စစ်
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: books.php");
    exit();
}

$book_id = (int) $_GET['id'];

// Categories list ယူ
$categories = $conn->query("SELECT * FROM Categories ORDER BY category_name ASC");

// Book အဟောင်း data ယူ
$stmt = $conn->prepare("SELECT * FROM Books WHERE id = ?");
$stmt->bind_param("i", $book_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: books.php");
    exit();
}

$book = $result->fetch_assoc();
$stmt->close();

// Form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_id = intval($_POST['category_id']);
    $title       = trim($_POST['title']);
    $author      = trim($_POST['author']);
    $price       = trim($_POST['price']);
    $stock       = trim($_POST['stock']);
    $description = trim($_POST['description']);

    $book_image = $book['book_image']; // default = old image

    // Validation
    if (empty($category_id) || empty($title) || empty($author) || empty($price) || empty($stock) || empty($description)) {
        $error = "Please fill in all fields.";
    } else {
        // New image upload ရှိရင်
        if (isset($_FILES['book_image']) && $_FILES['book_image']['error'] === 0) {
            $upload_dir = "../uploads/";

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
                    // old image delete
                    if (!empty($book['book_image']) && file_exists("../uploads/" . $book['book_image'])) {
                        unlink("../uploads/" . $book['book_image']);
                    }

                    $book_image = $image_name;
                } else {
                    $error = "Failed to upload new image.";
                }
            }
        }

        // Update database
        if (empty($error)) {
            $update = $conn->prepare("UPDATE Books SET category_id = ?, title = ?, author = ?, price = ?, stock = ?, book_image = ?, description = ? WHERE id = ?");
            $update->bind_param("issdissi", $category_id, $title, $author, $price, $stock, $book_image, $description, $book_id);

            if ($update->execute()) {
                header("Location: books.php");
                exit();
            } else {
                $error = "Failed to update book!";
            }

            $update->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Book</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">

    <!-- Header -->
    <div class="bg-blue-600 text-white px-8 py-5 flex justify-between items-center shadow-md">
        <h1 class="text-2xl font-bold">Edit Book</h1>
        <div class="flex gap-3">
            <a href="dashboard.php" class="bg-white text-blue-600 px-4 py-2 rounded-lg font-medium hover:bg-gray-100">
                Dashboard
            </a>
            <a href="books.php" class="bg-white text-blue-600 px-4 py-2 rounded-lg font-medium hover:bg-gray-100">
                Back to Books
            </a>
        </div>
    </div>

    <!-- Main -->
    <div class="max-w-3xl mx-auto mt-10 bg-white shadow-lg rounded-2xl p-8">
        <h2 class="text-2xl font-bold text-gray-800 mb-6">Update Book</h2>

        <?php if (!empty($error)): ?>
            <div class="mb-4 p-3 rounded-lg bg-red-100 text-red-700 border border-red-300">
                <?= $error; ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST" enctype="multipart/form-data" class="space-y-5">

            <!-- Book ID -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Book ID</label>
                <input type="text" value="<?= $book['id']; ?>" readonly
                       class="w-full px-4 py-3 border border-gray-300 rounded-xl bg-gray-100 text-gray-500 cursor-not-allowed">
            </div>

            <!-- Category -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                <select name="category_id" required
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    <option value="">-- Select Category --</option>
                    <?php if ($categories && $categories->num_rows > 0): ?>
                        <?php while ($cat = $categories->fetch_assoc()): ?>
                            <option value="<?= $cat['id']; ?>" <?= ($book['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($cat['category_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>

            <!-- Title -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Book Title</label>
                <input type="text" name="title" value="<?= htmlspecialchars($book['title']); ?>" required
                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>

            <!-- Author -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Author</label>
                <input type="text" name="author" value="<?= htmlspecialchars($book['author']); ?>" required
                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>

            <!-- Price -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Price</label>
                <input type="number" step="0.01" name="price" value="<?= $book['price']; ?>" required
                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>

            <!-- Stock -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Stock</label>
                <input type="number" name="stock" value="<?= $book['stock']; ?>" required
                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>

            <!-- Current Image -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Current Image</label>
                <?php if (!empty($book['book_image'])): ?>
                    <img src="../uploads/<?= htmlspecialchars($book['book_image']); ?>" 
                         alt="Book Image"
                         class="w-28 h-36 object-cover rounded-lg border">
                <?php else: ?>
                    <p class="text-gray-500">No image available</p>
                <?php endif; ?>
            </div>

            <!-- New Image -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Upload New Image (optional)</label>
                <input type="file" name="book_image" accept=".jpg,.jpeg,.png,.webp"
                       class="w-full px-4 py-3 border border-gray-300 rounded-xl bg-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>

            <!-- Description -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                <textarea name="description" rows="5" required
                          class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:outline-none"><?= htmlspecialchars($book['description']); ?></textarea>
            </div>

            <!-- Buttons -->
            <div class="flex gap-4">
                <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-xl font-medium shadow">
                    Update Book
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