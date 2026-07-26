<?php
session_start();
require_once '../config/db.php';

// Check if user is logged in and has admin privileges
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

// Dynamic color palette array mapped by category ID for visual grouping
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
        $description = trim($_POST['description']);
        $book_image  = "";

        // Prevent negative stock numbers in backend
        if ($stock < 0) {
            $_SESSION['error'] = "စတော့ပမာဏ အနုတ် ကိန်းဂဏန်း ထည့်သွင်း၍မရပါ!";
        } elseif (empty($category_id) || empty($title) || empty($author) || empty($price) || empty($description)) {
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
        $description = trim($_POST['description']);
        $book_image  = $_POST['old_image'];

        // Prevent negative stock numbers in backend
        if ($stock < 0) {
            $_SESSION['error'] = "စတော့ပမာဏ အနုတ် ကိန်းဂဏန်း ထည့်သွင်း၍မရပါ!";
        } elseif (empty($category_id) || empty($title) || empty($author) || empty($price) || empty($description)) {
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

// Retrieve flash messages stored in session
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
// PAGINATION SETUP
// -------------------------------------------------------------------------
$limit = 10; // Number of items per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Calculate total books count
$total_result = $conn->query("SELECT COUNT(*) AS total FROM Books");
$total_books = $total_result ? $total_result->fetch_assoc()['total'] : 0;
$total_pages = ceil($total_books / $limit);
if ($total_pages < 1) $total_pages = 1;

// Fetch paginated books list from database
$sql = "SELECT Books.*, Categories.category_name 
        FROM Books
        LEFT JOIN Categories ON Books.category_id = Categories.id
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

// Check if page is loaded in edit mode
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
<html lang="my">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>စာအုပ်များ စီမံရန် - BookShop Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-gray-300 font-sans antialiased text-slate-800" idm-members="never">

<div class="flex h-screen overflow-hidden">
    <!-- Include Dynamic Sidebar Component -->
    <?php include '../auth/sidebar.php'; ?>
    
    <div class="flex-1 flex flex-col overflow-hidden w-full">
        <!-- Dynamic Header Navigation Component Include -->
        <?php 
            $page_title = "Books Management";
            include '../auth/nav.php'; 
        ?>

        <!-- Main Workspace Canvas -->
        <main class="flex-1 overflow-y-auto p-4 md:p-8 max-w-[1600px] w-full mx-auto">
            
            <?php if (!empty($message)): ?>
                <div class="mb-4 p-3 rounded-xl text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center justify-between">
                    <span><i class="fa-solid fa-circle-check mr-2"></i><?= $message; ?></span>
                    <button onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-800"><i class="fa-solid fa-xmark"></i></button>
                </div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="mb-4 p-3 rounded-xl text-xs font-bold bg-rose-50 text-rose-700 border border-rose-200 flex items-center justify-between">
                    <span><i class="fa-solid fa-circle-exclamation mr-2"></i><?= $error; ?></span>
                    <button onclick="this.parentElement.remove()" class="text-rose-500 hover:text-rose-800"><i class="fa-solid fa-xmark"></i></button>
                </div>
            <?php endif; ?>

            <!-- Catalog Stock Records Table Container -->
            <div class="w-full bg-white rounded-2xl border border-slate-200/80 shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 bg-gradient-to-r from-slate-50 via-white to-indigo-50/30 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                    <h3 class="font-bold text-slate-900 flex items-center text-sm md:text-base shrink-0">
                        <i class="fa-solid fa-layer-group mr-2 text-indigo-600"></i>Catalog Stock Records
                    </h3>

                    <!-- Dual Search Input Group for Responsive Layout -->
                    <div class="flex flex-col sm:flex-row flex-1 gap-3 max-w-2xl mx-0 lg:mx-4">
                        <!-- Primary Search Bar (Title, Author, or Category Name) -->
                        <div class="relative flex-1">
                            <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                            <input type="text" id="searchInput" onkeyup="filterBooks()" placeholder="Title, Author and Category ဖြင့် ရှာရန်..." class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-700 focus:bg-white focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition shadow-xs">
                        </div>

                        <!-- Secondary Stock Status Search Bar (In Stock / Low Stock) -->
                        <div class="relative flex-1 sm:max-w-[200px]">
                            <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                            <input type="text" id="stockSearchInput" onkeyup="filterBooks()" placeholder="Stock Status...." class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-700 focus:bg-white focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition shadow-xs">
                        </div>
                    </div>

                    <button onclick="openModal()" class="inline-flex items-center justify-center px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-xs sm:text-sm shadow-md shadow-indigo-600/20 transition cursor-pointer gap-2 shrink-0">
                        <i class="fa-solid fa-plus"></i> Add Book
                    </button>
                </div>

                <div class="overflow-x-auto w-full no-scrollbar">
                    <table class="w-full text-left border-collapse min-w-[700px]">
                        <thead>
                            <tr class="bg-slate-50/80 text-slate-900 text-[11px] font-bold uppercase tracking-wider border-b border-slate-100">
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
                                    // Dynamic Color Mapping per Category ID
                                    $cat_id = $row['category_id'] ?? 0;
                                    $color_class = $category_colors[$cat_id % count($category_colors)];
                                    $is_low_stock = ($row['stock'] < 3);
                                    $stock_status_text = $is_low_stock ? "Low Stock" : "In Stock";
                                ?>
                                    <tr class="book-row hover:bg-indigo-50/30 transition" data-stock-status="<?= strtolower($stock_status_text); ?>">
                                        <td class="px-4 py-3 text-center font-bold text-slate-900 text-[11px]">
                                            <?= $rowNum++; ?>
                                        </td>
                                        <td class="px-6 py-3">
                                            <?php if (!empty($row['book_image'])): ?>
                                                <img src="../uploads/<?= htmlspecialchars($row['book_image']); ?>" alt="Cover" class="w-10 h-12 object-cover rounded-lg border border-slate-200/80 shadow-xs">
                                            <?php else: ?>
                                                <span class="text-slate-900 text-[10px] italic">ပုံမရှိပါ</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-3">
                                            <div class="font-bold text-slate-900 text-sm book-title"><?= htmlspecialchars($row['title']); ?></div>
                                            <div class="text-slate-900 font-normal text-[11px] mt-0.5 book-author"><?= htmlspecialchars($row['author']); ?></div>
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
                                                <a href="books.php?edit_id=<?= $row['id']; ?>" class="inline-flex items-center justify-center px-2.5 py-1.5 bg-blue-500 hover:bg-blue-600 text-white border border-blue-200/40 rounded-xl font-bold transition">
                                                    <i class="fa-solid fa-pen-to-square mr-1"></i> Edit
                                                </a>
                                                <form action="books.php" method="POST" class="inline">
                                                    <input type="hidden" name="book_id" value="<?= $row['id']; ?>">
                                                    <button type="submit" name="delete_book" onclick="return confirm('ဒီစာအုပ်ကို ဖျက်ရန် သေချာပါသလား?');" class="inline-flex items-center justify-center px-2.5 py-1.5 bg-red-500 hover:bg-red-600 text-white border border-rose-200/40 rounded-xl font-bold transition cursor-pointer">
                                                        <i class="fa-solid fa-trash-can mr-1"></i> Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center text-slate-900 font-semibold">စာအုပ် စာရင်းများ မရှိသေးပါ။</td>
                                </tr>
                            <?php endif; ?>
                            <!-- No Results Row for Filter Search -->
                            <tr id="noResultsRow" class="hidden">
                                <td colspan="7" class="px-6 py-12 text-center text-slate-900 font-semibold">ရှာဖွေမှု မတွေ့ရှိပါ။</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Navigation Controls Container -->
                <?php if ($total_pages > 1): ?>
                    <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/50 flex flex-col sm:flex-row items-center justify-between gap-4">
                        <p class="text-xs text-slate-500 font-medium text-center sm:text-left">
                            Showing <span class="font-bold text-slate-700"><?= min($offset + 1, $total_books); ?></span> to <span class="font-bold text-slate-700"><?= min($offset + $limit, $total_books); ?></span> of <span class="font-bold text-slate-700"><?= $total_books; ?></span> entries
                        </p>
                        <div class="flex items-center space-x-1">
                            <!-- Previous Page Button -->
                            <?php if ($page > 1): ?>
                                <a href="books.php?page=<?= $page - 1; ?>" class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 transition">
                                    <i class="fa-solid fa-chevron-left mr-1"></i> Prev
                                </a>
                            <?php else: ?>
                                <span class="px-3 py-1.5 bg-slate-100 border border-slate-200 rounded-lg text-xs font-bold text-slate-400 cursor-not-allowed">
                                    <i class="fa-solid fa-chevron-left mr-1"></i> Prev
                                </span>
                            <?php endif; ?>

                            <!-- Page Numbers Loop -->
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <?php if ($i == $page): ?>
                                    <span class="px-3 py-1.5 bg-indigo-600 border border-indigo-600 rounded-lg text-xs font-bold text-white shadow-xs">
                                        <?= $i; ?>
                                    </span>
                                <?php else: ?>
                                    <a href="books.php?page=<?= $i; ?>" class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 transition">
                                        <?= $i; ?>
                                    </a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <!-- Next Page Button -->
                            <?php if ($page < $total_pages): ?>
                                <a href="books.php?page=<?= $page + 1; ?>" class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 transition">
                                    Next <i class="fa-solid fa-chevron-right ml-1"></i>
                                </a>
                            <?php else: ?>
                                <span class="px-3 py-1.5 bg-slate-100 border border-slate-200 rounded-lg text-xs font-bold text-slate-400 cursor-not-allowed">
                                    Next <i class="fa-solid fa-chevron-right ml-1"></i>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

            </div>

        </main>
    </div>
</div>

<!-- Modal Dialog Alert Box: Add / Edit Book Assets Form -->
<div id="bookModal" class="<?= $editBook ? 'flex' : 'hidden'; ?> fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl w-full max-w-lg overflow-hidden transform transition-all my-8">
        
        <!-- Modal Header -->
        <div class="px-6 py-4 bg-slate-50 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-bold text-slate-900 text-sm md:text-base flex items-center">
                <i class="fa-solid <?= $editBook ? 'fa-pen-to-square text-amber-500' : 'fa-circle-plus text-indigo-600'; ?> mr-2"></i>
                <?= $editBook ? "စာအုပ်အချက်အလက် ပြင်ဆင်ရန်" : "Add New Book Assets"; ?>
            </h3>
            <button onclick="closeModal()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center transition cursor-pointer">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Modal Body Form -->
        <form action="books.php" method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
            <?php if ($editBook): ?>
                <input type="hidden" name="book_id" value="<?= $editBook['id']; ?>">
                <input type="hidden" name="old_image" value="<?= $editBook['book_image']; ?>">
            <?php endif; ?>

            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">အမျိုးအစား (Category)</label>
                <select name="category_id" required class="bg-white text-xs w-full rounded-xl p-2.5 border border-slate-200 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition shadow-xs">
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
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">စာအုပ်ခေါင်းစဉ်</label>
                <input type="text" name="title" required value="<?= $editBook ? htmlspecialchars($editBook['title']) : ''; ?>" class="bg-white text-xs w-full rounded-xl p-2.5 border border-slate-200 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition shadow-xs">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">စာရေးဆရာ</label>
                <input type="text" name="author" required value="<?= $editBook ? htmlspecialchars($editBook['author']) : ''; ?>" class="bg-white text-xs w-full rounded-xl p-2.5 border border-slate-200 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition shadow-xs">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">ဈေးနှုန်း (ကျပ်)</label>
                    <input type="number" step="0.01" min="0" onkeydown="preventNegativeInput(event)" name="price" required value="<?= $editBook ? $editBook['price'] : ''; ?>" class="bg-white text-xs w-full rounded-xl p-2.5 border border-slate-200 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition shadow-xs">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">စတော့ အရေအတွက်</label>
                    <input type="number" min="0" onkeydown="preventNegativeInput(event)" oninput="this.value = this.value.replace(/[^0-9]/g, '')" name="stock" required value="<?= $editBook ? $editBook['stock'] : '0'; ?>" class="bg-white text-xs w-full rounded-xl p-2.5 border border-slate-200 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition shadow-xs">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">စာအုပ်ကာဗာပုံ</label>
                <?php if ($editBook && !empty($editBook['book_image'])): ?>
                    <div class="mb-2 flex items-center space-x-3 bg-slate-50 p-1.5 rounded-xl border border-slate-200">
                        <img src="../uploads/<?= htmlspecialchars($editBook['book_image']); ?>" alt="Cover" class="w-10 h-12 object-cover rounded-lg border shadow-xs">
                        <span class="text-[10px] text-slate-400 truncate max-w-[150px]"><?= htmlspecialchars($editBook['book_image']); ?></span>
                    </div>
                <?php endif; ?>
                <input type="file" name="book_image" accept=".jpg,.jpeg,.png,.webp" <?= $editBook ? '' : 'required'; ?> class="file:mr-4 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-indigo-600 hover:file:bg-indigo-100 bg-white text-[11px] text-slate-400 w-full rounded-xl p-1.5 border border-slate-200 outline-none focus:border-indigo-500 transition cursor-pointer shadow-xs">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">အသေးစိတ် အကျဉ်းချုပ်</label>
                <textarea name="description" rows="3" required class="bg-white text-xs w-full rounded-xl p-2.5 border border-slate-200 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition resize-none shadow-xs"><?= $editBook ? htmlspecialchars($editBook['description']) : ''; ?></textarea>
            </div>

            <div class="pt-3 flex gap-2 border-t border-slate-100">
                <?php if ($editBook): ?>
                    <button type="submit" name="update_book" class="flex-1 bg-amber-500 hover:bg-amber-600 text-white font-bold rounded-xl text-xs py-3 shadow-md shadow-amber-500/20 transition cursor-pointer">သိမ်းဆည်းမည်</button>
                    <a href="books.php" class="flex-1 text-center bg-slate-100 text-slate-600 hover:bg-slate-200 rounded-xl text-xs font-bold py-3 transition">မလုပ်တော့ပါ</a>
                <?php else: ?>
                    <button type="submit" name="add_book" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-xs py-3.5 shadow-md shadow-indigo-600/20 transition cursor-pointer">
                    သိမ်းဆည်းမည်
                    </button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<script>
    // Real-time Dynamic Table Filter for Title, Author, Category, and Stock Status
    function filterBooks() {
        const textInput = document.getElementById("searchInput").value.toLowerCase().trim();
        const stockInput = document.getElementById("stockSearchInput").value.toLowerCase().trim();
        const rows = document.querySelectorAll(".book-row");
        const noResults = document.getElementById("noResultsRow");
        let visibleCount = 0;

        rows.forEach(row => {
            const title = row.querySelector(".book-title") ? row.querySelector(".book-title").textContent.toLowerCase() : "";
            const author = row.querySelector(".book-author") ? row.querySelector(".book-author").textContent.toLowerCase() : "";
            const category = row.querySelector(".book-category") ? row.querySelector(".book-category").textContent.toLowerCase() : "";
            const stockStatus = row.getAttribute("data-stock-status") || "";

            const matchesText = title.includes(textInput) || author.includes(textInput) || category.includes(textInput);
            const matchesStock = stockInput === "" || stockStatus.includes(stockInput);

            if (matchesText && matchesStock) {
                row.style.display = "";
                visibleCount++;
            } else {
                row.style.display = "none";
            }
        });

        if (noResults) {
            if (visibleCount === 0 && rows.length > 0) {
                noResults.classList.remove("hidden");
            } else {
                noResults.classList.add("hidden");
            }
        }
    }

    // Prevent typing minus sign (-), plus (+), and exponent (e/E) on number fields
    function preventNegativeInput(event) {
        if (event.key === '-' || event.key === '+' || event.key === 'e' || event.key === 'E') {
            event.preventDefault();
        }
    }

    // Open Form Alert Modal Popup Window
    function openModal() {
        const modal = document.getElementById('bookModal');
        if (modal) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }
    }

    // Close Form Alert Modal Popup Window
    function closeModal() {
        const modal = document.getElementById('bookModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            // Reset URL if closing during edit mode
            if (window.location.search.includes('edit_id')) {
                window.location.href = 'books.php';
            }
        }
    }

    // Toggle Mobile Sidebar Navigation
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        if (sidebar) {
            sidebar.classList.toggle('-translate-x-full');
        }
    }

    // Toggle Notifications Dropdown Menu Visibility
    function toggleNotificationDropdown(e) {
        e.stopPropagation();
        const notiDropdown = document.getElementById('notiDropdown');
        const profileDropdown = document.getElementById('profileDropdown');
        
        if (notiDropdown) notiDropdown.classList.toggle('hidden');
        if (profileDropdown) profileDropdown.classList.add('hidden');
    }

    // Toggle Profile Dropdown Menu Visibility
    function toggleProfileDropdown(e) {
        e.stopPropagation();
        const profileDropdown = document.getElementById('profileDropdown');
        const notiDropdown = document.getElementById('notiDropdown');
        
        if (profileDropdown) profileDropdown.classList.toggle('hidden');
        if (notiDropdown) notiDropdown.classList.add('hidden');
    }

    // Global Window Click Event Listener for Closing Menus Outside Focus
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