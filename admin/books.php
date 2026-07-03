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
$admin_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;

// -------------------------------------------------------------------------
// POST ACTIONS (ADD, UPDATE, DELETE)
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // 1. ADD BOOK
    if (isset($_POST['add_book'])) {
        $category_id = intval($_POST['category_id']);
        $title       = trim($_POST['title']);
        $author      = trim($_POST['author']);
        $price       = trim($_POST['price']);
        $stock       = trim($_POST['stock']);
        $description = trim($_POST['description']);
        $book_image  = "";

        if (empty($category_id) || empty($title) || empty($author) || empty($price) || empty($stock) || empty($description)) {
            $_SESSION['error'] = "Please fill in all fields.";
        } else {
            // Image Upload
            if (isset($_FILES['book_image']) && $_FILES['book_image']['error'] === 0) {
                $upload_dir = "../uploads/";
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

                $image_name = time() . "_" . basename($_FILES['book_image']['name']);
                $target_file = $upload_dir . $image_name;
                $allowed_types = ['jpg', 'jpeg', 'png', 'webp'];
                $image_ext = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

                if (!in_array($image_ext, $allowed_types)) {
                    $_SESSION['error'] = "Only JPG, JPEG, PNG, WEBP files are allowed.";
                } else {
                    if (move_uploaded_file($_FILES['book_image']['tmp_name'], $target_file)) {
                        $book_image = $image_name;
                    } else {
                        $_SESSION['error'] = "Failed to upload image.";
                    }
                }
            } else {
                $_SESSION['error'] = "Book image is required.";
            }

            if (!isset($_SESSION['error'])) {
                $stmt = $conn->prepare("INSERT INTO Books (user_id, category_id, title, author, price, stock, book_image, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("iissdiss", $admin_id, $category_id, $title, $author, $price, $stock, $book_image, $description);
                if ($stmt->execute()) {
                    $_SESSION['message'] = "Book added successfully!";
                } else {
                    $_SESSION['error'] = "Failed to add book!";
                }
                $stmt->close();
            }
        }
        header('Location: books.php');
        exit;
    }

    // 2. UPDATE BOOK
    if (isset($_POST['update_book'])) {
        $book_id     = intval($_POST['book_id']);
        $category_id = intval($_POST['category_id']);
        $title       = trim($_POST['title']);
        $author      = trim($_POST['author']);
        $price       = trim($_POST['price']);
        $stock       = trim($_POST['stock']);
        $description = trim($_POST['description']);
        $book_image  = $_POST['old_image']; // default old image

        if (empty($category_id) || empty($title) || empty($author) || empty($price) || empty($stock) || empty($description)) {
            $_SESSION['error'] = "Please fill in all fields.";
        } else {
            // New image upload check
            if (isset($_FILES['book_image']) && $_FILES['book_image']['error'] === 0) {
                $upload_dir = "../uploads/";
                $image_name = time() . "_" . basename($_FILES['book_image']['name']);
                $target_file = $upload_dir . $image_name;
                $allowed_types = ['jpg', 'jpeg', 'png', 'webp'];
                $image_ext = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

                if (!in_array($image_ext, $allowed_types)) {
                    $_SESSION['error'] = "Only JPG, JPEG, PNG, WEBP files are allowed.";
                } else {
                    if (move_uploaded_file($_FILES['book_image']['tmp_name'], $target_file)) {
                        if (!empty($_POST['old_image']) && file_exists("../uploads/" . $_POST['old_image'])) {
                            unlink("../uploads/" . $_POST['old_image']);
                        }
                        $book_image = $image_name;
                    } else {
                        $_SESSION['error'] = "Failed to upload new image.";
                    }
                }
            }

            if (!isset($_SESSION['error'])) {
                $update = $conn->prepare("UPDATE Books SET category_id = ?, title = ?, author = ?, price = ?, stock = ?, book_image = ?, description = ? WHERE id = ?");
                $update->bind_param("issdissi", $category_id, $title, $author, $price, $stock, $book_image, $description, $book_id);
                if ($update->execute()) {
                    $_SESSION['message'] = "Book updated successfully!";
                } else {
                    $_SESSION['error'] = "Failed to update book!";
                }
                $update->close();
            }
        }
        header('Location: books.php');
        exit;
    }

    // 3. DELETE BOOK
    if (isset($_POST['delete_book'])) {
        $idToDelete = intval($_POST['book_id']);

        // delete old image file
        $imgStmt = $conn->prepare("SELECT book_image FROM Books WHERE id = ?");
        $imgStmt->bind_param("i", $idToDelete);
        $imgStmt->execute();
        $imgResult = $imgStmt->get_result();
        if ($imgResult->num_rows > 0) {
            $imgRow = $imgResult->fetch_assoc();
            if (!empty($imgRow['book_image']) && file_exists("../uploads/" . $imgRow['book_image'])) {
                unlink("../uploads/" . $imgRow['book_image']);
            }
        }
        $imgStmt->close();

        // delete database record
        $stmt = $conn->prepare("DELETE FROM Books WHERE id = ?");
        $stmt->bind_param("i", $idToDelete);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Book deleted successfully!";
        } else {
            $_SESSION['error'] = "Failed to delete book!";
        }
        $stmt->close();

        header('Location: books.php');
        exit;
    }
}

// Session flash messages handling
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

// -------------------------------------------------------------------------
// GET DATA FOR DISPLAY & EDIT
// -------------------------------------------------------------------------
$categories = $conn->query("SELECT * FROM Categories ORDER BY category_name ASC");

$sql = "SELECT Books.*, Categories.category_name 
        FROM Books
        LEFT JOIN Categories ON Books.category_id = Categories.id
        ORDER BY Books.id DESC";
$result = $conn->query($sql);
$allBooks = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Check if Edit Mode
$editBook = null;
if (isset($_GET['edit_id'])) {
    $editId = intval($_GET['edit_id']);
    $stmt = $conn->prepare('SELECT * FROM Books WHERE id = ?');
    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $editBook = $res->fetch_assoc();
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Books</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body class="bg-slate-100 min-h-screen p-6 text-slate-800">
    <?php include '../auth/header.php' ?>
    <!-- Header -->
    <!-- <div class="max-w-7xl mx-auto mb-6 bg-blue-600 text-white px-6 py-4 flex justify-between items-center shadow-sm rounded-xl">
        <h1 class="text-xl font-bold flex items-center gap-2">
            <i class="fas fa-book"></i> Book Operations Hub
        </h1>
        <a href="dashboard.php" class="bg-white text-blue-600 px-4 py-2 rounded-lg font-medium text-sm hover:bg-gray-100 shadow-sm">
            Dashboard
        </a>
    </div> -->

    <div class="max-w-7xl mx-auto flex flex-col lg:flex-row gap-6">

        <!-- LEFT SIDE: FORM (ADD / EDIT) -->
        <div class="lg:w-1/3 bg-white p-6 rounded-xl shadow-sm border border-slate-200 h-fit">
            <h2 class="text-lg font-bold mb-4 border-b pb-2 text-slate-700">
                <?php echo $editBook ? "Update Book Details" : "Add New Book"; ?>
            </h2>

            <?php if (!empty($message)): ?>
                <div class="mb-4 p-2 rounded text-sm bg-green-100 text-green-700 border border-green-300"><?= $message; ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="mb-4 p-2 rounded text-sm bg-red-100 text-red-700 border border-red-300"><?= $error; ?></div>
            <?php endif; ?>

            <form action="books.php" method="POST" enctype="multipart/form-data" class="flex flex-col gap-4">
                <?php if ($editBook): ?>
                    <input type="hidden" name="book_id" value="<?php echo $editBook['id']; ?>">
                    <input type="hidden" name="old_image" value="<?php echo $editBook['book_image']; ?>">
                <?php endif; ?>

                <div>
                    <label class="block text-xs font-semibold mb-1 text-slate-500">Category</label>
                    <select name="category_id" required class="bg-slate-50 text-sm w-full rounded-md p-2 border border-slate-300 outline-none">
                        <option value="">-- Select Category --</option>
                        <?php if ($categories && $categories->num_rows > 0): ?>
                            <?php
                            // Rewind database pointer to reuse
                            $categories->data_seek(0);
                            while ($cat = $categories->fetch_assoc()):
                            ?>
                                <option value="<?= $cat['id']; ?>" <?= ($editBook && $editBook['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($cat['category_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold mb-1 text-slate-500">Book Title</label>
                    <input type="text" name="title" required value="<?php echo $editBook ? htmlspecialchars($editBook['title']) : ''; ?>" placeholder="Enter book title" class="bg-slate-50 text-sm w-full rounded-md p-2 border border-slate-300 outline-none">
                </div>

                <div>
                    <label class="block text-xs font-semibold mb-1 text-slate-500">Author</label>
                    <input type="text" name="author" required value="<?php echo $editBook ? htmlspecialchars($editBook['author']) : ''; ?>" placeholder="Enter author name" class="bg-slate-50 text-sm w-full rounded-md p-2 border border-slate-300 outline-none">
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-xs font-semibold mb-1 text-slate-500">Price</label>
                        <input type="number" step="0.01" name="price" required value="<?php echo $editBook ? $editBook['price'] : ''; ?>" placeholder="0.00" class="bg-slate-50 text-sm w-full rounded-md p-2 border border-slate-300 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1 text-slate-500">Stock</label>
                        <input type="number" name="stock" required value="<?php echo $editBook ? $editBook['stock'] : ''; ?>" placeholder="Qty" class="bg-slate-50 text-sm w-full rounded-md p-2 border border-slate-300 outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold mb-1 text-slate-500">Book Cover Image</label>
                    <?php if ($editBook && !empty($editBook['book_image'])): ?>
                        <div class="mb-2">
                            <img src="../uploads/<?= htmlspecialchars($editBook['book_image']); ?>" alt="Cover" class="w-14 h-16 object-cover rounded border">
                        </div>
                    <?php endif; ?>
                    <input type="file" name="book_image" accept=".jpg,.jpeg,.png,.webp" <?= $editBook ? '' : 'required'; ?> class="bg-slate-50 text-xs w-full rounded-md p-1.5 border border-slate-300 outline-none">
                </div>

                <div>
                    <label class="block text-xs font-semibold mb-1 text-slate-500">Description</label>
                    <textarea name="description" rows="3" required placeholder="Enter description..." class="bg-slate-50 text-sm w-full rounded-md p-2 border border-slate-300 outline-none"><?php echo $editBook ? htmlspecialchars($editBook['description']) : ''; ?></textarea>
                </div>

                <?php if ($editBook): ?>
                    <div class="flex gap-2 mt-2">
                        <button type="submit" name="update_book" class="flex-1 bg-green-600 text-white rounded-md text-sm font-medium p-2 hover:bg-green-700 transition">Update</button>
                        <a href="books.php" class="flex-1 text-center bg-slate-300 text-slate-700 rounded-md text-sm font-medium p-2 hover:bg-slate-400 transition">Cancel</a>
                    </div>
                <?php else: ?>
                    <button type="submit" name="add_book" class="bg-blue-600 mt-2 text-white font-medium rounded-md text-sm p-2 hover:bg-blue-700 transition">+ Add Book</button>
                <?php endif; ?>
            </form>
        </div>

        <!-- RIGHT SIDE: BOOKS LIST TABLE -->
        <div class="lg:w-2/3 w-full">
            <div class="bg-slate-900 p-6 rounded-xl shadow-md border border-slate-800">
                <div class="flex justify-between items-center mb-4 border-b border-slate-800 pb-3">
                    <h2 class="text-white font-bold text-md flex items-center gap-2">
                        <i class="fas fa-list text-cyan-400"></i> Book Stock Records
                    </h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left text-gray-300">
                        <thead class="border-b border-slate-800 text-gray-400 uppercase text-[10px] tracking-wider">
                            <tr>
                                <th class="p-3">Cover</th>
                                <th class="p-3">Title / Author</th>
                                <th class="p-3">Category</th>
                                <th class="p-3">Price</th>
                                <th class="p-3">Stock</th>
                                <th class="p-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($allBooks)): ?>
                                <?php foreach ($allBooks as $row): ?>
                                    <tr class="border-b border-slate-800/60 hover:bg-slate-800/50 transition">
                                        <td class="p-3">
                                            <?php if (!empty($row['book_image'])): ?>
                                                <img src="../uploads/<?= htmlspecialchars($row['book_image']); ?>" alt="Cover" class="w-10 h-12 object-cover rounded-md border border-slate-700 shadow-sm">
                                            <?php else: ?>
                                                <span class="text-gray-500 italic">No Img</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-3">
                                            <div class="font-bold text-white text-sm"><?= htmlspecialchars($row['title']); ?></div>
                                            <div class="text-gray-400 text-[11px]"><?= htmlspecialchars($row['author']); ?></div>
                                        </td>
                                        <td class="p-3 text-gray-400"><?= htmlspecialchars($row['category_name'] ?? 'Uncategorized'); ?></td>
                                        <td class="p-3 font-semibold text-emerald-400">$<?= number_format($row['price'], 2); ?></td>
                                        <td class="p-3">
                                            <span class="<?= $row['stock'] > 5 ? 'text-blue-400' : 'text-amber-400 font-bold'; ?>">
                                                <?= $row['stock']; ?> pcs
                                            </span>
                                        </td>
                                        <td class="p-3 text-right">
                                            <div class="flex items-center justify-end gap-3">
                                                <a href="../admin/books.php?edit_id=<?= $row['id']; ?>" class="text-yellow-500 hover:text-yellow-400 font-semibold flex items-center gap-1 transition">
                                                    <i class="fa-solid fa-pen-to-square"></i> Edit
                                                </a>

                                                <form action="books.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this book?');" class="inline">
                                                    <input type="hidden" name="book_id" value="<?= $row['id']; ?>">
                                                    <button type="submit" name="delete_book" class="text-red-500 hover:text-red-400 font-semibold flex items-center gap-1 transition">
                                                        <i class="fa-solid fa-trash-can"></i> Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="p-6 text-center text-gray-500">
                                        No book records found.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
    <?php include '../auth/footer.php' ?>
</body>

</html>