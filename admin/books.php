<?php
session_start();
require_once '../config/db.php';

// Route Guard: Redirect guests or non-admin roles to login
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

// Dynamic color palette mapped by category ID
$category_colors = [
    'bg-indigo-50 text-indigo-700 border-indigo-200',
    'bg-emerald-50 text-emerald-700 border-emerald-200',
    'bg-amber-50 text-amber-700 border-amber-200',
    'bg-rose-50 text-rose-700 border-rose-200',
    'bg-purple-50 text-purple-700 border-purple-200',
    'bg-cyan-50 text-cyan-700 border-cyan-200',
    'bg-teal-50 text-teal-700 border-teal-200',
    'bg-fuchsia-50 text-fuchsia-700 border-fuchsia-200',
    'bg-sky-50 text-sky-700 border-sky-200'
];

// Fetch Admin Profile Image from Users Table
$admin_image = "default-admin.png"; 
$admin_img_stmt = $conn->prepare("SELECT profile_image FROM Users WHERE id = ?");
if ($admin_img_stmt) {
    $admin_img_stmt->bind_param("i", $admin_id);
    $admin_img_stmt->execute();
    $admin_img_res = $admin_img_stmt->get_result();
    if ($admin_img_res->num_rows > 0) {
        $admin_row = $admin_img_res->fetch_assoc();
        if (!empty($admin_row['profile_image'])) {
            $admin_image = $admin_row['profile_image'];
        }
    }
    $admin_img_stmt->close();
}

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
        $stock       = intval($_POST['stock']);
        // Description is optional
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        $book_image  = "";

        // Prevent negative stock numbers in backend
        if ($stock < 0) {
            $_SESSION['error'] = "စတော့ပမာဏ အနုတ် ကိန်းဂဏန်း ထည့်သွင်း၍မရပါ!";
        } elseif (empty($category_id) || empty($title) || empty($author) || empty($price)) {
            $_SESSION['error'] = "ကျေးဇူးပြု၍ လိုအပ်သော အချက်အလက်များကို အပြည့်အစုံဖြည့်ပါ။";
        } else {
            if (isset($_FILES['book_image']) && $_FILES['book_image']['error'] === 0) {
                $upload_dir = "../uploads/";
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

                $image_name = time() . "_" . basename($_FILES['book_image']['name']);
                $target_file = $upload_dir . $image_name;
                $allowed_types = ['jpg', 'jpeg', 'png', 'webp'];
                $image_ext = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

                if (!in_array($image_ext, $allowed_types)) {
                    $_SESSION['error'] = "JPG, JPEG, PNG, WEBP ဖိုင်အမျိုးအစားများကိုသာ ခွင့်ပြုထားပါသည်။";
                } else {
                    if (move_uploaded_file($_FILES['book_image']['tmp_name'], $target_file)) {
                        $book_image = $image_name;
                    } else {
                        $_SESSION['error'] = "စာအုပ်ကာဗာပုံ တင်ခြင်း မအောင်မြင်ပါ။";
                    }
                }
            } else {
                $_SESSION['error'] = "စာအုပ်ကာဗာပုံ ထည့်သွင်းရန် လိုအပ်ပါသည်။";
            }

            if (!isset($_SESSION['error'])) {
                $stmt = $conn->prepare("INSERT INTO Books (user_id, category_id, title, author, price, stock, book_image, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("iissdiss", $admin_id, $category_id, $title, $author, $price, $stock, $book_image, $description);
                if ($stmt->execute()) {
                    $_SESSION['message'] = "စာအုပ်အသစ် အောင်မြင်စွာ ထည့်သွင်းပြီးပါပြီ!";
                } else {
                    $_SESSION['error'] = "စာအုပ်အချက်အလက် ထည့်သွင်းခြင်း မအောင်မြင်ပါ။";
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
        $stock       = intval($_POST['stock']);
        // Description is optional
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        $book_image  = $_POST['old_image'];

        // Prevent negative stock numbers in backend
        if ($stock < 0) {
            $_SESSION['error'] = "စတော့ပမာဏ အနုတ် ကိန်းဂဏန်း ထည့်သွင်း၍မရပါ!";
        } elseif (empty($category_id) || empty($title) || empty($author) || empty($price)) {
            $_SESSION['error'] = "ကျေးဇူးပြု၍ လိုအပ်သော အချက်အလက်များကို အပြည့်အစုံဖြည့်ပါ။";
        } else {
            if (isset($_FILES['book_image']) && $_FILES['book_image']['error'] === 0) {
                $upload_dir = "../uploads/";
                $image_name = time() . "_" . basename($_FILES['book_image']['name']);
                $target_file = $upload_dir . $image_name;
                $allowed_types = ['jpg', 'jpeg', 'png', 'webp'];
                $image_ext = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

                if (!in_array($image_ext, $allowed_types)) {
                    $_SESSION['error'] = "JPG, JPEG, PNG, WEBP ဖိုင်အမျိုးအစားများကိုသာ ခွင့်ပြုထားပါသည်။";
                } else {
                    if (move_uploaded_file($_FILES['book_image']['tmp_name'], $target_file)) {
                        if (!empty($_POST['old_image']) && file_exists("../uploads/" . $_POST['old_image'])) {
                            unlink("../uploads/" . $_POST['old_image']);
                        }
                        $book_image = $image_name;
                    } else {
                        $_SESSION['error'] = "ကာဗာပုံအသစ် တင်ခြင်း မအောင်မြင်ပါ။";
                    }
                }
            }

            if (!isset($_SESSION['error'])) {
                $update = $conn->prepare("UPDATE Books SET category_id = ?, title = ?, author = ?, price = ?, stock = ?, book_image = ?, description = ? WHERE id = ?");
                $update->bind_param("issdissi", $category_id, $title, $author, $price, $stock, $book_image, $description, $book_id);
                if ($update->execute()) {
                    $_SESSION['message'] = "စာအုပ်အချက်အလက် ပြင်ဆင်ပြီးပါပြီ!";
                } else {
                    $_SESSION['error'] = "ပြင်ဆင်ခြင်း မအောင်မြင်ပါ။";
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
            $_SESSION['message'] = "စာအုပ်ကို အောင်မြင်စွာ ဖျက်ပြီးပါပြီ!";
        } else {
            $_SESSION['error'] = "စာအုပ်ဖျက်ခြင်း မအောင်မြင်ပါ။";
        }
        $stmt->close();

        header('Location: books.php');
        exit;
    }
}

// Retrieve session flash messages
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Fetch categories from database
$categories = $conn->query("SELECT * FROM Categories ORDER BY category_name ASC");

// -------------------------------------------------------------------------
// PAGINATION & STOCK FILTER SETUP
// -------------------------------------------------------------------------
$limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? intval($_GET['limit']) : 10;
$allowed_limits = [10, 20, 30, 50, 100];
if (!in_array($limit, $allowed_limits)) {
    $limit = 10;
}

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;

$stock_status = isset($_GET['stock_status']) ? trim($_GET['stock_status']) : '';

// Build dynamic WHERE clause based on Stock Status filter
$where_clause = "";
if ($stock_status === 'low_stock') {
    $where_clause = " WHERE Books.stock < 3 ";
} elseif ($stock_status === 'in_stock') {
    $where_clause = " WHERE Books.stock >= 3 ";
}

// Calculate total books count according to filter
$total_result = $conn->query("SELECT COUNT(*) AS total FROM Books" . $where_clause);
$total_books = $total_result ? $total_result->fetch_assoc()['total'] : 0;
$total_pages = ceil($total_books / $limit);
if ($total_pages < 1) $total_pages = 1;
if ($page > $total_pages) $page = $total_pages;

$offset = ($page - 1) * $limit;

// Fetch filtered and paginated books list
$sql = "SELECT Books.*, Categories.category_name 
        FROM Books
        LEFT JOIN Categories ON Books.category_id = Categories.id
        " . $where_clause . "
        ORDER BY Books.id DESC 
        LIMIT ? OFFSET ?";

$stmt_page = $conn->prepare($sql);
$stmt_page->bind_param("ii", $limit, $offset);
$stmt_page->execute();
$result = $stmt_page->get_result();
$allBooks = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
$stmt_page->close();

$low_stock_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM Books WHERE stock < 3");
$low_stock_count = mysqli_fetch_assoc($low_stock_query)['total'] ?? 0;

$pending_payments_query = mysqli_query($conn, "SELECT id, amount, status FROM Payment WHERE status = 'pending' ORDER BY id DESC LIMIT 3");
$pending_payments_count = $pending_payments_query ? mysqli_num_rows($pending_payments_query) : 0;

// Check edit mode
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

    // Fetch all categories
    $result = $conn->query("SELECT * FROM Categories ORDER BY id DESC");
    $allCategories = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $totalCategories = count($allCategories);

    // Fetch Alert Badge Notifications
    $low_stock_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM Books WHERE stock < 3");
    $low_stock_count = mysqli_fetch_assoc($low_stock_query)['total'] ?? 0;

    $pending_payments_query = mysqli_query($conn, "SELECT id, amount, status FROM Payment WHERE status = 'pending' ORDER BY id DESC LIMIT 3");
    $pending_payments_count = mysqli_num_rows($pending_payments_query);
}
?>
<!DOCTYPE html>
<html lang="my" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BookShop </title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    /* Active navigation link styling */
    .header-nav a.active,
    .header-nav button.active {
        font-weight: 700;
        color: #1e293b !important;
    }

    /* Desktop category dropdown behavior */
    @media (min-width: 768px) {
        .cat-dropdown:hover>.cat-dropdown-menu {
            display: block;
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Mobile sidebar menu slide animation */
    #mobileMenu {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease-in-out;
    }

    #mobileMenu.open {
        max-height: 85vh;
        overflow-y: auto;
    }

    /* Category dropdown menu transition */
    .cat-dropdown-menu {
        display: none;
        opacity: 0;
        transform: translateY(-2px);
        transition: opacity 0.15s ease;
    }

    .cat-dropdown.open>.cat-dropdown-menu {
        display: block;
        opacity: 1;
        transform: translateY(0);
    }

    /* Search bar input styles */
    .header-search {
        background-color: #ffffff !important;
        box-shadow: none !important;
    }

    .header-search:focus {
        outline: none !important;
        box-shadow: none !important;
    }

    .header-search::placeholder {
        color: #94a3b8;
    }

    /* Hamburger icon animation transition */
    .hamburger-bar {
        transition: transform 0.2s ease, opacity 0.2s ease;
    }

    /* Custom thin scrollbar track */
    ::-webkit-scrollbar {
        width: 5px;
        height: 5px;
    }

    /* Custom scrollbar background */
    ::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }

    /* Custom scrollbar thumb */
    ::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 10px;
    }

    /* Custom scrollbar hover state */
    ::-webkit-scrollbar-thumb:hover {
        background: #555;
    }
</style>
</head>
<body class="bg-gray-300 font-sans antialiased text-slate-800 h-full overflow-hidden">

<div class="flex h-screen overflow-hidden">
    <!-- Include Dynamic Sidebar Component -->
    <?php include '../auth/sidebar.php'; ?>
    
    <!-- Main Scroll Container: Wrapping both Nav Header and Main Content for full-height scrollbar -->
    <div class="flex-1 flex flex-col h-screen overflow-y-auto w-full custom-scrollbar">
        <!-- Dynamic Navigation Header -->
        <?php 
            $page_title = "Books Management";
            include '../auth/nav.php'; 
        ?>

        <!-- Main Workspace Canvas -->
        <main class="p-3 sm:p-6 md:p-8 max-w-[1600px] w-full mx-auto">
            
            <?php if (!empty($message)): ?>
                <div class="mb-4 p-3 rounded-xl text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center justify-between shadow-xs">
                    <span><i class="fa-solid fa-circle-check mr-2"></i><?= htmlspecialchars($message); ?></span>
                    <button onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-800"><i class="fa-solid fa-xmark"></i></button>
                </div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="mb-4 p-3 rounded-xl text-xs font-bold bg-rose-50 text-rose-700 border border-rose-200 flex items-center justify-between shadow-xs">
                    <span><i class="fa-solid fa-circle-exclamation mr-2"></i><?= htmlspecialchars($error); ?></span>
                    <button onclick="this.parentElement.remove()" class="text-rose-500 hover:text-rose-800"><i class="fa-solid fa-xmark"></i></button>
                </div>
            <?php endif; ?>

            <!-- Catalog Stock Records Table Container -->
            <div class="w-full bg-white rounded-2xl border border-slate-200 shadow-md overflow-hidden">
                <div class="px-4 sm:px-6 py-4 border-b border-slate-100 bg-gradient-to-r from-slate-50 via-white to-indigo-50/30 flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <h3 class="font-bold text-slate-900 flex items-center text-sm md:text-base shrink-0">
                        <i class="fa-solid fa-layer-group mr-2 text-indigo-600"></i>Catalog Stock Records
                    </h3>

                    <!-- Dual Search Input Group -->
                    <div class="flex flex-col sm:flex-row flex-1 gap-3 max-w-2xl mx-0 md:mx-4">
                        <!-- Primary Search Input (Title, Author, or Category) -->
                        <div class="relative flex-1">
                            <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                            <input type="text" id="searchInput" onkeyup="filterBooks()" placeholder="Title, Author and Category ဖြင့် ရှာရန်..." class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-700 focus:bg-white focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition shadow-xs">
                        </div>

                        <!-- Stock Filter Selection Bar -->
                        <div class="relative w-full sm:w-48 shrink-0">
                            <select id="stockSearchSelect" onchange="applyStockFilter(this.value)" class="w-full pl-3 pr-8 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-700 font-semibold focus:bg-white focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition shadow-xs cursor-pointer appearance-none">
                                <option value="" <?= $stock_status === '' ? 'selected' : ''; ?>>-- All Stock Status --</option>
                                <option value="in_stock" <?= $stock_status === 'in_stock' ? 'selected' : ''; ?>>In Stock</option>
                                <option value="low_stock" <?= $stock_status === 'low_stock' ? 'selected' : ''; ?>>Low Stock</option>
                            </select>
                            <i class="fa-solid fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                        </div>
                    </div>

                    <button onclick="openModal()" class="inline-flex items-center justify-center px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-xs sm:text-sm shadow-md shadow-indigo-600/20 transition cursor-pointer gap-2 shrink-0">
                        <i class="fa-solid fa-plus"></i> Add Book
                    </button>
                </div>

                <!-- Custom Horizontal Scroll Area with Thin Scrollbar -->
                <div class="overflow-x-auto w-full custom-scrollbar">
                    <table class="w-full text-left border-collapse min-w-[750px]">
                        <thead>
                            <tr class="bg-slate-700 text-white text-[11px] font-bold uppercase tracking-wider border-b border-slate-200">
                                <th class="px-4 py-3.5 w-12 text-center">No.</th>
                                <th class="px-6 py-3.5 w-20">Cover</th>
                                <th class="px-6 py-3.5">Title & Author</th>
                                <th class="px-6 py-3.5">Category</th>
                                <th class="px-6 py-3.5">Price</th>
                                <th class="px-6 py-3.5">Stock</th>
                                <th class="px-6 py-3.5 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="booksTableBody" class="divide-y divide-slate-100 text-xs text-slate-700 font-medium">
                            <?php if (!empty($allBooks)): ?>
                                <?php 
                                $rowNum = $offset + 1;
                                foreach ($allBooks as $row): 
                                    $cat_id = $row['category_id'] ?? 0;
                                    $color_class = $category_colors[$cat_id % count($category_colors)];
                                    $is_low_stock = ($row['stock'] < 3);
                                ?>
                                    <tr class="book-row hover:bg-indigo-50/30 transition">
                                        <td class="px-4 py-3 text-center font-bold text-slate-900 text-[11px]">
                                            <?= $rowNum++; ?>
                                        </td>
                                        <td class="px-6 py-3">
                                            <?php if (!empty($row['book_image'])): ?>
                                                <img src="../uploads/<?= htmlspecialchars($row['book_image']); ?>" alt="Cover" class="w-10 h-12 object-cover rounded-md shadow-xs">
                                            <?php else: ?>
                                                <span class="text-slate-400 text-[10px] italic">ပုံမရှိပါ</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-3">
                                            <div class="font-bold text-slate-900 text-sm book-title"><?= htmlspecialchars($row['title']); ?></div>
                                            <div class="text-slate-500 font-normal text-[11px] mt-0.5 book-author"><?= htmlspecialchars($row['author']); ?></div>
                                        </td>
                                        <td class="px-6 py-3">
                                            <span class="book-category inline-block px-2.5 py-1 rounded-full text-[11px] font-bold border <?= $color_class; ?>">
                                                <?= htmlspecialchars($row['category_name'] ?? 'မသတ်မှတ်ရသေး'); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-3 font-bold text-slate-900">
                                            <?= number_format($row['price']); ?> ကျပ်
                                        </td>
                                        <td class="px-6 py-3">
                                            <?php if(!$is_low_stock): ?>
                                                <span class="stock-badge inline-flex items-center px-2.5 py-0.5 rounded-md font-bold text-[11px] bg-emerald-50 text-emerald-700 border border-emerald-100">
                                                    <?= $row['stock']; ?> အုပ်
                                                </span>
                                            <?php else: ?>
                                                <span class="stock-badge inline-flex items-center px-2.5 py-0.5 rounded-md font-bold text-[11px] bg-rose-50 text-rose-700 border border-rose-100">
                                                    <?= $row['stock']; ?> အုပ်
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-3 text-center">
                                            <div class="flex items-center justify-center space-x-2">
                                                <a href="books.php?edit_id=<?= $row['id']; ?>" class="inline-flex items-center justify-center px-2.5 py-1.5 bg-blue-500 hover:bg-blue-600 text-white border border-blue-200/40 rounded-xl font-bold transition shadow-xs">
                                                    <i class="fa-solid fa-pen-to-square mr-1"></i> Edit
                                                </a>
                                                <form action="books.php" method="POST" class="inline">
                                                    <input type="hidden" name="book_id" value="<?= $row['id']; ?>">
                                                    <button type="submit" name="delete_book" onclick="return confirm('ဒီစာအုပ်ကို ဖျက်ရန် သေချာပါသလား?');" class="inline-flex items-center justify-center px-2.5 py-1.5 bg-red-500 hover:bg-red-600 text-white border border-rose-200/40 rounded-xl font-bold transition shadow-xs cursor-pointer">
                                                        <i class="fa-solid fa-trash-can mr-1"></i> Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center text-slate-500 font-semibold">စာအုပ် စာရင်းများ မရှိသေးပါ။</td>
                                </tr>
                            <?php endif; ?>
                            <!-- Empty Search Result Indicator Row -->
                            <tr id="noResultsRow" class="hidden">
                                <td colspan="7" class="px-6 py-12 text-center text-slate-500 font-semibold">ရှာဖွေမှု မတွေ့ရှိပါ။</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Custom Pagination Controls Section -->
                <div class="px-4 sm:px-6 py-4 border-t border-slate-100 bg-slate-50/50 flex flex-col md:flex-row items-center justify-between gap-4">
                    <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500 font-medium text-center md:text-left">
                        <span>Showing <span class="font-bold text-slate-700"><?= min($offset + 1, $total_books); ?></span> to <span class="font-bold text-slate-700"><?= min($offset + $limit, $total_books); ?></span> of <span class="font-bold text-slate-700"><?= $total_books; ?></span> entries</span>
                        
                        <!-- Limit Entries Dropdown -->
                        <span class="inline-flex items-center gap-1.5 ml-0 sm:ml-2 pl-0 sm:pl-2 border-t sm:border-t-0 sm:border-l border-slate-200 pt-2 sm:pt-0">
                            <label for="limitSelect" class="text-slate-600 font-semibold">Show</label>
                            <select id="limitSelect" onchange="changeLimit(this.value)" class="bg-white border border-slate-200 rounded-lg px-2 py-1 text-xs font-bold text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-200 transition shadow-xs cursor-pointer">
                                <?php foreach ($allowed_limits as $l): ?>
                                    <option value="<?= $l; ?>" <?= $limit == $l ? 'selected' : ''; ?>><?= $l; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="text-slate-600 font-semibold">entries</span>
                        </span>
                    </div>
                    
                    <?php if ($total_pages > 1): ?>
                        <div class="flex items-center space-x-1 flex-wrap justify-center">
                            <!-- Previous Button -->
                            <?php if ($page > 1): ?>
                                <a href="books.php?page=<?= $page - 1; ?>&limit=<?= $limit; ?>&stock_status=<?= urlencode($stock_status); ?>" class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 transition flex items-center">
                                    <i class="fa-solid fa-chevron-left mr-1"></i> Prev
                                </a>
                            <?php else: ?>
                                <span class="px-3 py-1.5 bg-slate-100 border border-slate-200 rounded-lg text-xs font-bold text-slate-400 cursor-not-allowed flex items-center">
                                    <i class="fa-solid fa-chevron-left mr-1"></i> Prev
                                </span>
                            <?php endif; ?>

                            <!-- Smart Dynamic Pagination Rendering -->
                            <?php
                            $pages_to_show = [];
                            
                            $pages_to_show[] = 1;

                            if ($page - 1 > 2) {
                                $pages_to_show[] = '...';
                            }

                            if ($page - 1 > 1) {
                                $pages_to_show[] = $page - 1;
                            }

                            if ($page != 1 && $page != $total_pages) {
                                $pages_to_show[] = $page;
                            }

                            if ($page + 1 < $total_pages) {
                                $pages_to_show[] = $page + 1;
                            }

                            if ($page + 1 < $total_pages - 1) {
                                $pages_to_show[] = '...';
                            }

                            if ($total_pages > 1) {
                                $pages_to_show[] = $total_pages;
                            }

                            foreach ($pages_to_show as $p):
                                if ($p === '...'): ?>
                                    <span class="px-2 py-1.5 text-xs text-slate-400 font-bold">...</span>
                                <?php elseif ($p == $page): ?>
                                    <span class="px-3 py-1.5 bg-indigo-600 border border-indigo-600 rounded-lg text-xs font-bold text-white shadow-xs">
                                        <?= $p; ?>
                                    </span>
                                <?php else: ?>
                                    <a href="books.php?page=<?= $p; ?>&limit=<?= $limit; ?>&stock_status=<?= urlencode($stock_status); ?>" class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 transition">
                                        <?= $p; ?>
                                    </a>
                                <?php endif;
                            endforeach;
                            ?>

                            <!-- Next Button -->
                            <?php if ($page < $total_pages): ?>
                                <a href="books.php?page=<?= $page + 1; ?>&limit=<?= $limit; ?>&stock_status=<?= urlencode($stock_status); ?>" class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 transition flex items-center">
                                    Next <i class="fa-solid fa-chevron-right ml-1"></i>
                                </a>
                            <?php else: ?>
                                <span class="px-3 py-1.5 bg-slate-100 border border-slate-200 rounded-lg text-xs font-bold text-slate-400 cursor-not-allowed flex items-center">
                                    Next <i class="fa-solid fa-chevron-right ml-1"></i>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

        </main>
    </div>
</div>

<!-- Modal Dialog Box: Add / Edit Book Form -->
<div id="bookModal" class="<?= $editBook ? 'flex' : 'hidden'; ?> fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs items-center justify-center p-3 sm:p-4 overflow-y-auto no-scrollbar">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl w-full max-w-md overflow-hidden transform transition-all my-4 custom-scrollbar max-h-[92vh] overflow-y-auto">
        
        <!-- Modal Header -->
        <div class="px-5 py-3.5 bg-slate-50 border-b border-slate-100 flex items-center justify-between sticky top-0 bg-white z-10">
            <h3 class="font-bold text-slate-900 text-sm md:text-base flex items-center">
                <i class="fa-solid <?= $editBook ? 'fa-pen-to-square text-amber-500' : 'fa-circle-plus text-indigo-600'; ?> mr-2"></i>
                <?= $editBook ? "စာအုပ်အချက်အလက် ပြင်ဆင်ရန်" : "Add New Book Assets"; ?>
            </h3>
            <button onclick="closeModal()" class="w-7 h-7 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center transition cursor-pointer">
                <i class="fa-solid fa-xmark text-xs"></i>
            </button>
        </div>

        <!-- Modal Body Form -->
        <form action="books.php" method="POST" enctype="multipart/form-data" class="p-4 sm:p-5 space-y-3 text-xs">
            <!-- Hidden Input Values Container -->
            <div class="hidden">
                <?php if ($editBook): ?>
                    <input type="hidden" name="book_id" value="<?= $editBook['id']; ?>">
                    <input type="hidden" name="old_image" value="<?= htmlspecialchars($editBook['book_image']); ?>">
                <?php endif; ?>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">အမျိုးအစား</label>
                <select name="category_id" required class="bg-white text-xs w-full rounded-xl p-2 border border-slate-200 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition shadow-xs">
                    <option value="">-- အမျိုးအစား ရွေးချယ်ပါ --</option>
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
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">စာအုပ်ခေါင်းစဉ်</label>
                <input type="text" name="title" required value="<?= $editBook ? htmlspecialchars($editBook['title']) : ''; ?>" placeholder="စာအုပ်အမည် ထည့်ပါ။" class="bg-white text-xs w-full rounded-xl p-2 border border-slate-200 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition shadow-xs">
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">စာရေးဆရာ</label>
                <input type="text" name="author" required value="<?= $editBook ? htmlspecialchars($editBook['author']) : ''; ?>" placeholder="စာရေးဆရာအမည် ထည့်ပါ။" class="bg-white text-xs w-full rounded-xl p-2 border border-slate-200 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition shadow-xs">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">ဈေးနှုန်း (ကျပ်)</label>
                    <input type="number" step="0.01" name="price" required value="<?= $editBook ? htmlspecialchars($editBook['price']) : ''; ?>" placeholder="0.00" class="bg-white text-xs w-full rounded-xl p-2 border border-slate-200 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition shadow-xs">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">စတော့ အရေအတွက်</label>
                    <input type="number" min="0" name="stock" required value="<?= $editBook ? htmlspecialchars($editBook['stock']) : '0'; ?>" class="bg-white text-xs w-full rounded-xl p-2 border border-slate-200 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition shadow-xs">
                </div>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">စာအုပ် ကာဗာပုံ</label>
                <div class="flex items-center gap-2.5 w-full">
                    <!-- Custom Choose File Button without default input string -->
                    <label for="book_image" class="inline-flex items-center justify-center gap-2 px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 hover:bg-slate-50 hover:border-indigo-400 cursor-pointer transition shadow-xs shrink-0">
                        <i class="fa-solid fa-upload text-indigo-600"></i>
                        <span>Choose File</span>
                    </label>

                    <!-- Hidden File Input -->
                    <input type="file" id="book_image" name="book_image" accept="image/*" <?= $editBook ? '' : 'required'; ?> class="hidden" onchange="previewBookImage(this)">

                    <!-- Small Thumbnail Image Preview Container (w-8 h-10) -->
                    <div id="bookImagePreviewWrapper" class="<?= ($editBook && !empty($editBook['book_image'])) ? 'flex' : 'hidden'; ?> items-center gap-1.5 shrink-0">
                        <div class="w-8 h-10 rounded border border-slate-200 bg-slate-50 overflow-hidden shrink-0 shadow-xs">
                            <img id="bookImagePreview"
                                 src="<?= ($editBook && !empty($editBook['book_image'])) ? '../uploads/' . htmlspecialchars($editBook['book_image']) : ''; ?>"
                                 alt="Book cover preview"
                                 class="w-full h-full object-cover">
                        </div>
                        <button type="button" onclick="clearBookImagePreview()" class="w-5 h-5 rounded-full bg-slate-100 hover:bg-rose-50 text-slate-400 hover:text-rose-600 flex items-center justify-center transition shrink-0" aria-label="Remove selected image">
                            <i class="fa-solid fa-xmark text-[10px]"></i>
                        </button>
                    </div>

                </div>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">အကြောင်းအရာ</label>
                <textarea name="description" rows="2.5" placeholder="စာအုပ်အကြောင်း အကျဉ်းချုပ် ရေးသားပါ။" class="bg-white text-xs w-full rounded-xl p-2 border border-slate-200 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition shadow-xs"><?= $editBook ? htmlspecialchars($editBook['description']) : ''; ?></textarea>
            </div>

            <div class="pt-2 flex items-center justify-end gap-2.5">
                <button type="button" onclick="closeModal()" class="px-3.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl font-bold text-xs transition cursor-pointer">
                    မလုပ်တော့ပါ
                </button>
                <button type="submit" name="<?= $editBook ? 'update_book' : 'add_book'; ?>" class="px-4 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-xs shadow-md shadow-indigo-600/20 transition cursor-pointer">
                    <?= $editBook ? 'ပြင်ဆင်ချက်များ သိမ်းမည်' : 'သိမ်းဆည်းမည်'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Instant client-side search by Title, Author or Category
function filterBooks() {
    const textQuery = document.getElementById('searchInput').value.toLowerCase().trim();
    const rows = document.querySelectorAll('.book-row');
    let visibleCount = 0;

    rows.forEach(row => {
        const title = row.querySelector('.book-title')?.textContent.toLowerCase() || '';
        const author = row.querySelector('.book-author')?.textContent.toLowerCase() || '';
        const category = row.querySelector('.book-category')?.textContent.toLowerCase() || '';

        const matchesText = title.includes(textQuery) || author.includes(textQuery) || category.includes(textQuery);

        if (matchesText) {
            row.classList.remove('hidden');
            visibleCount++;
        } else {
            row.classList.add('hidden');
        }
    });

    const noResultsRow = document.getElementById('noResultsRow');
    if (noResultsRow) {
        if (visibleCount === 0 && rows.length > 0) {
            noResultsRow.classList.remove('hidden');
        } else {
            noResultsRow.classList.add('hidden');
        }
    }
}

// Reload page with stock status query parameter for database-level stock filter
function applyStockFilter(stockValue) {
    const urlParams = new URLSearchParams(window.location.search);
    if (stockValue) {
        urlParams.set('stock_status', stockValue);
    } else {
        urlParams.delete('stock_status');
    }
    urlParams.set('page', '1');
    window.location.search = urlParams.toString();
}

// Book cover image preview handler
function previewBookImage(input) {
    const previewWrapper = document.getElementById('bookImagePreviewWrapper');
    const preview = document.getElementById('bookImagePreview');
    if (!input || !input.files || !input.files[0]) {
        clearBookImagePreview();
        return;
    }

    const file = input.files[0];

    if (!file.type.startsWith('image/')) {
        input.value = '';
        clearBookImagePreview();
        return;
    }

    const reader = new FileReader();

    reader.onload = function(e) {
        preview.src = e.target.result;
        previewWrapper.classList.remove('hidden');
        previewWrapper.classList.add('flex');
    };

    reader.readAsDataURL(file);
}

// Clear selected book cover image preview
function clearBookImagePreview() {
    const input = document.getElementById('book_image');
    const previewWrapper = document.getElementById('bookImagePreviewWrapper');
    const preview = document.getElementById('bookImagePreview');
    if (input) {
        input.value = '';
    }

    if (preview) {
        preview.removeAttribute('src');
    }

    if (previewWrapper) {
        previewWrapper.classList.remove('flex');
        previewWrapper.classList.add('hidden');
    }
}

// Pagination limit selector handler
function changeLimit(limitValue) {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('limit', limitValue);
    urlParams.set('page', '1');
    window.location.search = urlParams.toString();
}

// Modal open/close UI logic
function openModal() {
    const modal = document.getElementById('bookModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeModal() {
    const modal = document.getElementById('bookModal');
    modal.classList.remove('flex');
    modal.classList.add('hidden');
    if (window.location.search.includes('edit_id')) {
        window.location.href = 'books.php';
    }
}

// Notifications Dropdown Toggle
function toggleNotificationDropdown(e) {
    e.stopPropagation();
    const notiDropdown = document.getElementById('notiDropdown');
    const profileDropdown = document.getElementById('profileDropdown');
    if (notiDropdown) notiDropdown.classList.toggle('hidden');
    if (profileDropdown) profileDropdown.classList.add('hidden');
}

// Toggle Admin Profile Dropdown
function toggleProfileDropdown(e) {
    e.stopPropagation();
    const profileDropdown = document.getElementById('profileDropdown');
    const notiDropdown = document.getElementById('notiDropdown');

    if (profileDropdown) profileDropdown.classList.toggle('hidden');
    if (notiDropdown) notiDropdown.classList.add('hidden');
}

// Close Dropdowns on outside click
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