<?php
session_start();
require_once '../config/db.php';

// Admin login check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

// DELETE BOOK
if (isset($_GET['delete_id'])) {
    $delete_id = (int) $_GET['delete_id'];

    // optional: delete image file from folder
    $imgStmt = $conn->prepare("SELECT book_image FROM Books WHERE id = ?");
    $imgStmt->bind_param("i", $delete_id);
    $imgStmt->execute();
    $imgResult = $imgStmt->get_result();

    if ($imgResult->num_rows > 0) {
        $imgRow = $imgResult->fetch_assoc();
        if (!empty($imgRow['book_image']) && file_exists("../uploads/" . $imgRow['book_image'])) {
            unlink("../uploads/" . $imgRow['book_image']);
        }
    }
    $imgStmt->close();

    // delete from database
    $stmt = $conn->prepare("DELETE FROM Books WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $stmt->close();

    header("Location: books.php");
    exit();
}

// FETCH ALL BOOKS WITH CATEGORY NAME
$sql = "SELECT Books.*, Categories.category_name 
        FROM Books
        LEFT JOIN Categories ON Books.category_id = Categories.id
        ORDER BY Books.id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Books</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">

    <!-- Header -->
    <div class="bg-blue-600 text-white px-8 py-5 flex justify-between items-center shadow-md">
        <h1 class="text-2xl font-bold">Manage Books</h1>
        <div class="flex gap-3">
            <a href="dashboard.php" class="bg-white text-blue-600 px-4 py-2 rounded-lg font-medium hover:bg-gray-100">
                Dashboard
            </a>
            <a href="addbook.php" class="bg-white text-blue-600 px-4 py-2 rounded-lg font-medium hover:bg-gray-100">
                + Add Book
            </a>
        </div>
    </div>

    <!-- Main Container -->
    <div class="max-w-7xl mx-auto mt-10 bg-white shadow-lg rounded-2xl p-8">
        <h2 class="text-2xl font-bold text-gray-800 mb-6">Book List</h2>

        <div class="overflow-x-auto">
            <table class="w-full border border-gray-200 rounded-lg overflow-hidden text-sm">
                <thead class="bg-blue-100 text-gray-700">
                    <tr>
                        <!-- <th class="py-3 px-4 border">ID</th> -->
                        <th class="py-3 px-4 border">Image</th>
                        <th class="py-3 px-4 border">Title</th>
                        <th class="py-3 px-4 border">Author</th>
                        <th class="py-3 px-4 border">Category</th>
                        <th class="py-3 px-4 border">Price</th>
                        <th class="py-3 px-4 border">Stock</th>
                        <th class="py-3 px-4 border">Description</th>
                        <th class="py-3 px-4 border">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50 align-top">
                                <!-- ID -->
                                <!-- <td class="py-3 px-4 border text-center"><?= $row['id']; ?></td> -->

                                <!-- Image -->
                                <td class="py-3 px-4 border text-center">
                                    <?php if (!empty($row['book_image'])): ?>
                                        <img src="../uploads/<?= htmlspecialchars($row['book_image']); ?>" 
                                             alt="Book Image"
                                             class="w-16 h-20 object-cover rounded-md mx-auto border">
                                    <?php else: ?>
                                        <span class="text-gray-400">No Image</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Title -->
                                <td class="py-3 px-4 border font-medium text-gray-800">
                                    <?= htmlspecialchars($row['title']); ?>
                                </td>

                                <!-- Author -->
                                <td class="py-3 px-4 border">
                                    <?= htmlspecialchars($row['author']); ?>
                                </td>

                                <!-- Category -->
                                <td class="py-3 px-4 border">
                                    <?= htmlspecialchars($row['category_name'] ?? 'No Category'); ?>
                                </td>

                                <!-- Price -->
                                <td class="py-3 px-4 border text-center">
                                    <?= number_format($row['price'], 2); ?>
                                </td>

                                <!-- Stock -->
                                <td class="py-3 px-4 border text-center">
                                    <?= $row['stock']; ?>
                                </td>

                                <!-- Description -->
                                <td class="py-3 px-4 border max-w-xs">
                                    <div class="line-clamp-3">
                                        <?= htmlspecialchars($row['description']); ?>
                                    </div>
                                </td>

                                <!-- Actions -->
                                <td class="py-3 px-4 border">
                                    <div class="flex flex-col gap-2">
                                        <a href="editbook.php?id=<?= $row['id']; ?>"
                                           class="bg-yellow-400 hover:bg-yellow-500 text-white px-4 py-2 rounded-lg text-center font-medium">
                                            Edit
                                        </a>

                                        <a href="books.php?delete_id=<?= $row['id']; ?>"
                                           onclick="return confirm('Are you sure you want to delete this book?')"
                                           class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg text-center font-medium">
                                            Delete
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="py-6 text-center text-gray-500">
                                No books found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>