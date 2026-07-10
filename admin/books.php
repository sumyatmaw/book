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
$admin_name = $_SESSION['user_name'] ?? 'Admin User';
$admin_email = $_SESSION['user_email'] ?? 'admin@bookshop.com';
$admin_initial = strtoupper(substr($admin_name, 0, 1));

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
        $book_image  = $_POST['old_image'];

        if (empty($category_id) || empty($title) || empty($author) || empty($price) || empty($stock) || empty($description)) {
            $_SESSION['error'] = "Please fill in all fields.";
        } else {
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

// Session flash messages
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Fetch Dynamic Dropdowns / Tables
$categories = $conn->query("SELECT * FROM Categories ORDER BY category_name ASC");

$sql = "SELECT Books.*, Categories.category_name 
        FROM Books
        LEFT JOIN Categories ON Books.category_id = Categories.id
        ORDER BY Books.id DESC";
$result = $conn->query($sql);
$allBooks = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

$low_stock_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM Books WHERE stock < 3");
$low_stock_count = mysqli_fetch_assoc($low_stock_query)['total'] ?? 0;

$pending_payments_query = mysqli_query($conn, "SELECT id, amount, status FROM Payment WHERE status = 'pending' ORDER BY id DESC LIMIT 3");
$pending_payments_count = mysqli_num_rows($pending_payments_query);

// Check Edit Mode
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
    <title>Manage Books - BookShop Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-slate-50 font-sans antialiased text-slate-800">

<div class="flex h-screen overflow-hidden">
    
    <!-- SIDEBAR CONTAINER -->
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-64 bg-slate-900 text-slate-400 flex flex-col justify-between transform -translate-x-full transition-transform duration-300 md:relative md:translate-x-0 border-r border-slate-800 shrink-0">
        <div class="p-6 overflow-y-auto no-scrollbar flex-1">
            <div class="flex items-center justify-between mb-8 px-2">
                <div class="flex items-center space-x-3">
                    <div class="w-9 h-9 bg-indigo-600 rounded-xl flex items-center justify-center text-white shadow-lg shadow-indigo-600/30">
                        <i class="fa-solid fa-book-open text-sm"></i>
                    </div>
                    <span class="text-xl font-bold tracking-tight bg-gradient-to-r from-white to-slate-400 bg-clip-text text-transparent">BookShop</span>
                </div>
                <button onclick="toggleSidebar()" class="md:hidden text-slate-400 hover:text-white cursor-pointer">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>
            
            <nav class="space-y-1.5">
                <a href="dashboard.php" class="flex items-center space-x-3 px-4 py-3 hover:bg-slate-800 hover:text-white rounded-xl font-medium transition">
                    <i class="fa-solid fa-chart-pie w-5 text-slate-500"></i><span>Dashboard</span>
                </a>
                <a href="books.php" class="flex items-center space-x-3 px-4 py-3 bg-indigo-600 text-white rounded-xl font-medium shadow-sm shadow-indigo-600/10">
                    <i class="fa-solid fa-book w-5 text-indigo-200"></i><span>Manage Books</span>
                </a>
                <a href="categories.php" class="flex items-center space-x-3 px-4 py-3 hover:bg-slate-800 hover:text-white rounded-xl font-medium transition">
                    <i class="fa-solid fa-tags w-5"></i><span>Categories</span>
                </a>
                <a href="orders.php" class="flex items-center space-x-3 px-4 py-3 hover:bg-slate-800 hover:text-white rounded-xl font-medium transition">
                    <i class="fa-solid fa-cart-shopping w-5"></i><span>Orders</span>
                </a>
                <a href="manage_payment.php" class="flex items-center space-x-3 px-4 py-3 hover:bg-slate-800 hover:text-white rounded-xl font-medium transition">
                    <i class="fa-solid fa-credit-card w-5"></i><span>Payments</span>
                </a>
                <a href="delivery.php" class="flex items-center space-x-3 px-4 py-3 hover:bg-slate-800 hover:text-white rounded-xl font-medium transition">
                    <i class="fa-solid fa-truck w-5"></i><span>Deliveries</span>
                </a>
                <a href="customers.php" class="flex items-center space-x-3 px-4 py-3 hover:bg-slate-800 hover:text-white rounded-xl font-medium transition">
                    <i class="fa-solid fa-users w-5"></i><span>Customers</span>
                </a>
            </nav>
        </div>
        
        <!-- Premium Red Sign Out Button in Sidebar -->
        <div class="p-4 border-t border-slate-800 bg-slate-950/30">
            <a href="../auth/logout.php" class="flex items-center justify-center space-x-2 px-4 py-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold transition shadow-md shadow-rose-600/20 group">
                <i class="fa-solid fa-right-from-bracket group-hover:transform group-hover:translate-x-0.5 transition"></i><span>Sign Out</span>
            </a>
        </div>
    </aside>

    <div class="flex-1 flex flex-col overflow-hidden w-full">
        
        <!-- TOP NAVIGATION BAR -->
        <header class="h-16 bg-white border-b border-slate-200/80 flex items-center justify-between px-4 md:px-8 z-40 shrink-0">
            <div class="flex items-center space-x-3">
                <button onclick="toggleSidebar()" class="p-2 rounded-xl text-slate-600 hover:bg-slate-50 md:hidden transition cursor-pointer">
                    <i class="fa-solid fa-bars text-lg"></i>
                </button>
                <h1 class="text-lg font-bold text-slate-800 md:text-xl">Inventory Management</h1>
            </div>

            <div class="flex items-center space-x-4 relative">
                <!-- Notifications Bell Button -->
                <div class="relative">
                    <button onclick="toggleNotificationDropdown(event)" id="notiBtn" class="p-2 text-slate-500 hover:text-indigo-600 hover:bg-slate-50 rounded-xl transition cursor-pointer">
                        <i class="fa-solid fa-bell"></i>
                        <?php if ($low_stock_count > 0 || $pending_payments_count > 0): ?>
                            <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-rose-500 rounded-full ring-2 ring-white"></span>
                        <?php endif; ?>
                    </button>

                    <!-- Notifications Dropdown -->
                    <div id="notiDropdown" class="hidden absolute right-0 top-12 w-80 bg-white border border-slate-200 shadow-xl rounded-2xl overflow-hidden z-50">
                        <div class="px-4 py-3 bg-slate-50 border-b border-slate-100 font-bold text-xs text-slate-700">Notifications</div>
                        <div class="divide-y divide-slate-100 max-h-64 overflow-y-auto no-scrollbar">
                            <?php if ($pending_payments_count > 0): ?>
                                <?php while($payment = mysqli_fetch_assoc($pending_payments_query)): ?>
                                <a href="manage_payment.php" class="block p-3 hover:bg-slate-50 transition">
                                    <p class="text-xs font-bold text-indigo-600 flex items-center"><i class="fa-solid fa-wallet mr-1.5"></i> New Bank Transfer Pending</p>
                                    <p class="text-xxs text-slate-500 mt-0.5">Amount: <?php echo number_format($payment['amount']); ?> MMK awaiting approval.</p>
                                </a>
                                <?php endwhile; ?>
                            <?php endif; ?>

                            <?php if ($low_stock_count > 0): ?>
                                <div class="block p-3 bg-amber-50/40">
                                    <p class="text-xs font-bold text-amber-600 flex items-center"><i class="fa-solid fa-triangle-exclamation mr-1.5"></i> Critical Stock Warning</p>
                                    <p class="text-xxs text-slate-500 mt-0.5">You have <?php echo $low_stock_count; ?> books currently running low on stock.</p>
                                </div>
                            <?php endif; ?>

                            <?php if ($low_stock_count == 0 && $pending_payments_count == 0): ?>
                                <div class="p-4 text-center text-xs text-slate-400 font-medium">No new operational notifications.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Admin Profile Menu -->
                <div class="relative border-l border-slate-200 pl-4">
                    <button onclick="toggleProfileDropdown(event)" id="profileBtn" class="w-8 h-8 rounded-full bg-slate-100 border border-slate-200 text-slate-600 hover:text-indigo-600 hover:border-indigo-500 flex items-center justify-center transition cursor-pointer">
                        <i class="fa-solid fa-user text-sm"></i>
                    </button>

                    <!-- Admin Profile Dropdown Menu -->
                    <div id="profileDropdown" class="hidden absolute right-0 top-12 w-48 bg-white border border-slate-200 shadow-xl rounded-2xl overflow-hidden z-50">
                        <div class="px-4 py-2.5 border-b border-slate-100 bg-slate-50/60">
                            <p class="text-xs font-bold text-slate-800 truncate"><?php echo htmlspecialchars($admin_name); ?></p>
                            <p class="text-[10px] text-slate-400 truncate"><?php echo htmlspecialchars($admin_email); ?></p>
                        </div>
                        <div class="py-1">
                            <a href="dashboard.php" class="flex items-center space-x-2 px-4 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50 hover:text-indigo-600 transition">
                                <i class="fa-solid fa-chart-pie w-4 text-slate-400"></i><span>Dashboard</span>
                            </a>
                            <a href="adminprofile.php" class="flex items-center space-x-2 px-4 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50 hover:text-indigo-600 transition">
                                <i class="fa-solid fa-id-card w-4 text-slate-400"></i><span>My Profile</span>
                            </a>
                            <a href="../auth/logout.php" class="flex items-center space-x-2 px-4 py-2 text-xs font-medium text-rose-600 hover:bg-rose-50 transition">
                                <i class="fa-solid fa-right-from-bracket w-4 text-rose-500"></i><span>Sign Out</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- MAIN CANVAS -->
        <main class="flex-1 overflow-y-auto p-4 md:p-8 max-w-[1600px] w-full mx-auto">
            
            <div class="flex flex-col lg:flex-row gap-6 items-start">

                <!-- LEFT SIDE: FORM (ADD / EDIT) -->
                <div class="w-full lg:w-96 bg-white p-5 rounded-2xl border border-slate-200/70 shadow-sm shrink-0 h-fit">
                    <div class="pb-3 border-b border-slate-100 mb-4">
                        <h3 class="font-bold text-slate-900 text-sm flex items-center">
                            <i class="fa-solid <?= $editBook ? 'fa-pen-to-square text-amber-500' : 'fa-circle-plus text-indigo-500'; ?> mr-2"></i>
                            <?= $editBook ? "Update Book Details" : "Add New Book Asset"; ?>
                        </h3>
                    </div>

                    <?php if (!empty($message)): ?>
                        <div class="mb-4 p-2.5 rounded-xl text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-100"><?= $message; ?></div>
                    <?php endif; ?>
                    <?php if (!empty($error)): ?>
                        <div class="mb-4 p-2.5 rounded-xl text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-100"><?= $error; ?></div>
                    <?php endif; ?>

                    <form action="books.php" method="POST" enctype="multipart/form-data" class="space-y-3.5">
                        <?php if ($editBook): ?>
                            <input type="hidden" name="book_id" value="<?= $editBook['id']; ?>">
                            <input type="hidden" name="old_image" value="<?= $editBook['book_image']; ?>">
                        <?php endif; ?>

                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Category</label>
                            <select name="category_id" required class="bg-slate-50/80 text-xs w-full rounded-xl p-2.5 border border-slate-200 outline-none focus:border-indigo-500 transition">
                                <option value="">-- Select Category --</option>
                                <?php if ($categories && $categories->num_rows > 0): 
                                    $categories->data_seek(0);
                                    while ($cat = $categories->fetch_assoc()): ?>
                                        <option value="<?= $cat['id']; ?>" <?= ($editBook && $editBook['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                            <?= htmlspecialchars($cat['category_name']); ?>
                                        </option>
                                    <?php endwhile; 
                                endif; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Book Title</label>
                            <input type="text" name="title" required value="<?= $editBook ? htmlspecialchars($editBook['title']) : ''; ?>" class="bg-slate-50/80 text-xs w-full rounded-xl p-2.5 border border-slate-200 outline-none focus:border-indigo-500 transition">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Author</label>
                            <input type="text" name="author" required value="<?= $editBook ? htmlspecialchars($editBook['author']) : ''; ?>" class="bg-slate-50/80 text-xs w-full rounded-xl p-2.5 border border-slate-200 outline-none focus:border-indigo-500 transition">
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Price (MMK)</label>
                                <input type="number" step="0.01" name="price" required value="<?= $editBook ? $editBook['price'] : ''; ?>" class="bg-slate-50/80 text-xs w-full rounded-xl p-2.5 border border-slate-200 outline-none focus:border-indigo-500 transition">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Stock Qty</label>
                                <input type="number" name="stock" required value="<?= $editBook ? $editBook['stock'] : ''; ?>" class="bg-slate-50/80 text-xs w-full rounded-xl p-2.5 border border-slate-200 outline-none focus:border-indigo-500 transition">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Book Cover Image</label>
                            <?php if ($editBook && !empty($editBook['book_image'])): ?>
                                <div class="mb-2 flex items-center space-x-3 bg-slate-50 p-1.5 rounded-xl border border-slate-100">
                                    <img src="../uploads/<?= htmlspecialchars($editBook['book_image']); ?>" alt="Cover" class="w-10 h-12 object-cover rounded-lg border shadow-sm">
                                    <span class="text-[10px] text-slate-400 truncate max-w-[150px]"><?= htmlspecialchars($editBook['book_image']); ?></span>
                                </div>
                            <?php endif; ?>
                            <input type="file" name="book_image" accept=".jpg,.jpeg,.png,.webp" <?= $editBook ? '' : 'required'; ?> class="file:mr-4 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-indigo-600 hover:file:bg-indigo-100 bg-slate-50/80 text-[11px] text-slate-400 w-full rounded-xl p-1.5 border border-slate-200 outline-none focus:border-indigo-500 transition cursor-pointer">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Description summary</label>
                            <textarea name="description" rows="3" required class="bg-slate-50/80 text-xs w-full rounded-xl p-2.5 border border-slate-200 outline-none focus:border-indigo-500 transition resize-none"><?= $editBook ? htmlspecialchars($editBook['description']) : ''; ?></textarea>
                        </div>

                        <div class="pt-2 flex gap-2">
                            <?php if ($editBook): ?>
                                <button type="submit" name="update_book" class="flex-1 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-xs font-bold py-2.5 shadow-sm shadow-amber-500/10 transition cursor-pointer">Save Update</button>
                                <a href="books.php" class="flex-1 text-center bg-slate-100 text-slate-600 hover:bg-slate-200 rounded-xl text-xs font-bold py-2.5 transition">Cancel</a>
                            <?php else: ?>
                                <button type="submit" name="add_book" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-xs py-2.5 shadow-sm shadow-indigo-600/10 transition cursor-pointer">+ Register Book</button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- RIGHT SIDE: BOOKS LIST TABLE -->
                <div class="w-full lg:flex-1 bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50">
                        <h3 class="font-bold text-slate-900 flex items-center text-sm">
                            <i class="fa-solid fa-layer-group mr-2 text-indigo-500"></i>Catalog Stock Records
                        </h3>
                    </div>

                    <div class="overflow-x-auto w-full no-scrollbar">
                        <table class="w-full text-left border-collapse min-w-[700px]">
                            <thead>
                                <tr class="bg-slate-50/70 text-slate-400 text-[11px] font-bold uppercase tracking-wider border-b border-slate-100">
                                    <th class="px-6 py-3.5 w-20">Cover</th>
                                    <th class="px-6 py-3.5">Title & Author</th>
                                    <th class="px-6 py-3.5">Category</th>
                                    <th class="px-6 py-3.5">Price</th>
                                    <th class="px-6 py-3.5">Available Stock</th>
                                    <th class="px-6 py-3.5 text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-xs text-slate-700 font-medium">
                                <?php if (!empty($allBooks)): ?>
                                    <?php foreach ($allBooks as $row): ?>
                                        <tr class="hover:bg-slate-50/40 transition">
                                            <td class="px-6 py-3">
                                                <?php if (!empty($row['book_image'])): ?>
                                                    <img src="../uploads/<?= htmlspecialchars($row['book_image']); ?>" alt="Cover" class="w-10 h-12 object-cover rounded-lg border border-slate-200/80 shadow-xs">
                                                <?php else: ?>
                                                    <span class="text-slate-400 text-[10px] italic">No Img</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-3">
                                                <div class="font-bold text-slate-900 text-sm"><?= htmlspecialchars($row['title']); ?></div>
                                                <div class="text-slate-400 font-normal text-[11px] mt-0.5"><?= htmlspecialchars($row['author']); ?></div>
                                            </td>
                                            <td class="px-6 py-3 text-slate-500">
                                                <span class="bg-slate-100 px-2 py-1 rounded-md text-[11px]"><?= htmlspecialchars($row['category_name'] ?? 'Uncategorized'); ?></span>
                                            </td>
                                            <td class="px-6 py-3 font-bold text-slate-900">
                                                <?= number_format($row['price']); ?> MMK
                                            </td>
                                            <td class="px-6 py-3">
                                                <?php if($row['stock'] >= 3): ?>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md font-bold text-[11px] bg-emerald-50 text-emerald-700 border border-emerald-100">
                                                        <?= $row['stock']; ?> အုပ်
                                                    </span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md font-bold text-[11px] bg-rose-50 text-rose-700 border border-rose-100">
                                                        <?= $row['stock']; ?> အုပ်
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-3 text-center">
                                                <div class="flex items-center justify-center space-x-2.5">
                                                    <a href="books.php?edit_id=<?= $row['id']; ?>" class="inline-flex items-center justify-center px-2.5 py-1.5 bg-amber-50 hover:bg-amber-100 text-amber-700 border border-amber-200/40 rounded-xl font-bold transition">
                                                        <i class="fa-solid fa-pen-to-square mr-1"></i> Edit
                                                    </a>
                                                    <form action="books.php" method="POST" onsubmit="return confirm('Are you sure you want to completely remove this book?');" class="inline">
                                                        <input type="hidden" name="book_id" value="<?= $row['id']; ?>">
                                                        <button type="submit" name="delete_book" class="inline-flex items-center justify-center px-2.5 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200/40 rounded-xl font-bold transition cursor-pointer">
                                                            <i class="fa-solid fa-trash-can mr-1"></i> Delete
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-12 text-center text-slate-400 font-semibold">No catalog items discovered.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    </div>
</div>

<script>
    // Sidebar Toggle
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.toggle('-translate-x-full');
    }

    // Notifications Toggle
    function toggleNotificationDropdown(e) {
        e.stopPropagation();
        const notiDropdown = document.getElementById('notiDropdown');
        const profileDropdown = document.getElementById('profileDropdown');
        
        notiDropdown.classList.toggle('hidden');
        profileDropdown.classList.add('hidden');
    }

    // Profile Menu Toggle
    function toggleProfileDropdown(e) {
        e.stopPropagation();
        const profileDropdown = document.getElementById('profileDropdown');
        const notiDropdown = document.getElementById('notiDropdown');
        
        profileDropdown.classList.toggle('hidden');
        notiDropdown.classList.add('hidden');
    }

    // Window Click Outside Listener
    window.addEventListener('click', function(e) {
        const notiDropdown = document.getElementById('notiDropdown');
        const profileDropdown = document.getElementById('profileDropdown');
        const notiBtn = document.getElementById('notiBtn');
        const profileBtn = document.getElementById('profileBtn');

        if (notiDropdown && !notiDropdown.contains(e.target) && notiBtn && !notiBtn.contains(e.target)) {
            notiDropdown.classList.add('hidden');
        }
        if (profileDropdown && !profileDropdown.contains(e.target) && profileBtn && !profileBtn.contains(e.target)) {
            profileDropdown.classList.add('hidden');
        }
    });
</script>
</body>
</html>