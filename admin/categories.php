<?php
session_start();
require_once '../config/db.php';

// Admin login check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

// DELETE CATEGORY
if (isset($_GET['delete_id'])) {
    $delete_id = (int) $_GET['delete_id'];

    $stmt = $conn->prepare("DELETE FROM Categories WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $stmt->close();

    header("Location: categories.php");
    exit();
}

// FETCH ALL CATEGORIES
$result = $conn->query("SELECT * FROM Categories ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">

    <!-- Header -->
    <div class="bg-blue-600 text-white px-8 py-5 flex justify-between items-center shadow-md">
        <h1 class="text-2xl font-bold">Manage Categories</h1>
        <div class="flex gap-3">
            <a href="dashboard.php" class="bg-white text-blue-600 px-4 py-2 rounded-lg font-medium hover:bg-gray-100">
                Dashboard
            </a>
            <a href="addcategories.php" class="bg-white text-blue-600 px-4 py-2 rounded-lg font-medium hover:bg-gray-100">
                + Add Category
            </a>
        </div>
    </div>

    <!-- Main Container -->
    <div class="max-w-6xl mx-auto mt-10 bg-white shadow-lg rounded-2xl p-8">

        <h2 class="text-2xl font-bold text-gray-800 mb-6">Category List</h2>

        <div class="overflow-x-auto">
            <table class="w-full border border-gray-200 rounded-lg overflow-hidden">
                <thead class="bg-blue-100 text-gray-700">
                    <tr>
                        <!-- <th class="py-3 px-4 border">ID</th> -->
                        <th class="py-3 px-4 border">Category Name</th>
                        <th class="py-3 px-4 border">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr class="text-center hover:bg-gray-50">
                                <!-- <td class="py-3 px-4 border"><?= $row['id']; ?></td> -->
                                <td class="py-3 px-4 border"><?= htmlspecialchars($row['category_name']); ?></td>
                                <td class="py-3 px-4 border">
                                    <div class="flex justify-center gap-2">
                                        <!-- Edit Button -->
                                        <a href="editcategories.php?id=<?= $row['id']; ?>"
                                           class="bg-yellow-400 hover:bg-yellow-500 text-white px-4 py-2 rounded-lg text-sm font-medium">
                                            Edit
                                        </a>

                                        <!-- Delete Button -->
                                        <a href="categories.php?delete_id=<?= $row['id']; ?>"
                                           onclick="return confirm('Are you sure you want to delete this category?')"
                                           class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
                                            Delete
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="py-6 text-center text-gray-500">
                                No categories found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

</body>
</html>