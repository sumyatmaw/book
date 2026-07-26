<?php
// 1. Session and Database Connection
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "config/db.php"; 

$is_logged_in = isset($_SESSION['user_id']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'customer';

// 2. Calculate items currently in Cart for Stock Sync
$cart_quantities = [];

if ($is_logged_in) {
    $uid = $_SESSION['user_id'];
    $cart_stmt = $conn->prepare("SELECT book_id, SUM(quantity) as total_qty FROM Cart_item WHERE user_id = ? GROUP BY book_id");
    $cart_stmt->bind_param("i", $uid);
    $cart_stmt->execute();
    $cart_res = $cart_stmt->get_result();
    while ($c_row = $cart_res->fetch_assoc()) {
        $cart_quantities[$c_row['book_id']] = intval($c_row['total_qty']);
    }
    $cart_stmt->close();
} else {
    foreach (($_SESSION['guest_cart'] ?? []) as $g_item) {
        $b_id = intval($g_item['book_id']);
        $cart_quantities[$b_id] = ($cart_quantities[$b_id] ?? 0) + intval($g_item['quantity']);
    }
}

// 3. Fetch categories for Header Dropdown
$categories = [];
$cat_result = mysqli_query($conn, "SELECT * FROM Categories ORDER BY category_name ASC");
if ($cat_result) {
    while ($row = mysqli_fetch_assoc($cat_result)) {
        $categories[] = $row;
    }
}

// 4. Logic: Read Parameters from URL
$cat_id = isset($_GET['cat_id']) ? intval($_GET['cat_id']) : 0;
$search_query = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : "";

// 5. Build SQL Query based on conditions
if ($cat_id > 0) {
    // If a specific category is selected from dropdown
    $query = "SELECT * FROM Books WHERE category_id = $cat_id ORDER BY id DESC";
    
    // Fetch current category name for page title
    $title_res = mysqli_query($conn, "SELECT category_name FROM Categories WHERE id = $cat_id");
    $title_row = mysqli_fetch_assoc($title_res);
    $page_title = "🗂️ " . ($title_row['category_name'] ?? 'Category') . " ကဏ္ဍမှ စာအုပ်များ";
} elseif (!empty($search_query)) {
    // If search query is performed
    $query = "SELECT * FROM Books WHERE title LIKE '%$search_query%' OR author LIKE '%$search_query%' ORDER BY id DESC";
    $page_title = "🔍 ရှာဖွေတွေ့ရှိသော စာအုပ်များ";
} else {
    // Default: Show all available books in the catalog
    $query = "SELECT * FROM Books ORDER BY id DESC";
    $page_title = "📚 ရှိသမျှ စာအုပ်အားလုံး";
}

$books_result = mysqli_query($conn, $query);
$currentPage = 'books';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Book Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<!-- Soft Cream Accent Body -->
<body class="bg-gray-300 text-slate-900 flex flex-col min-h-screen font-sans antialiased">

    <!-- Shared Header Navigation -->
    <?php include 'auth/header.php'; ?>

    <!-- 📚 MAIN CONTENT (Book Showcase Section) -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 py-6 sm:py-10 flex-1 w-full">
        
        <!-- Dynamic Page Title -->
        <h2 class="text-lg sm:text-xl font-black text-slate-900 mb-6 sm:mb-8 border-l-4 border-amber-500 pl-3">
            <?= $page_title; ?>
        </h2>

        <!-- Fully Responsive Book Shelf Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3 sm:gap-6">
            <?php 
            if (mysqli_num_rows($books_result) > 0) {
                while ($book = mysqli_fetch_assoc($books_result)) { 
                    $book_id = intval($book['id']);
                    $total_physical_stock = intval($book['stock']);
                    $in_cart_qty = $cart_quantities[$book_id] ?? 0;
                    
                    // Dynamically calculate available stock (Physical Stock - Cart Quantity)
                    $available_stock = max(0, $total_physical_stock - $in_cart_qty);
                    $is_out_of_stock = ($available_stock <= 0);
            ?>
                    <!-- Individual Book Card Element -->
                    <div class="bg-white p-3 sm:p-4 rounded-2xl shadow-xs border border-gray-100 flex flex-col justify-between hover:shadow-md transition duration-300 group relative">
                        
                        <!-- Book Cover Container with Stock Overlay Layout -->
                        <div class="relative overflow-hidden rounded-xl mb-3 sm:mb-4 bg-gray-50 aspect-[3/4]">
                            <img src="uploads/<?= htmlspecialchars($book['book_image'] ?? 'default.jpg'); ?>" 
                                 class="w-full h-full object-cover group-hover:scale-105 transition duration-300 <?= $is_out_of_stock ? 'opacity-40 blur-[1px]' : ''; ?>" 
                                 alt="<?= htmlspecialchars($book['title']); ?>">
                            
                            <?php if ($is_out_of_stock): ?>
                                <!-- Visual Overlay Badge for Out of Stock Status -->
                                <div class="absolute inset-0 flex items-center justify-center  rounded-xl">
                                    <span class="bg-rose-600 text-white text-[10px] sm:text-[11px] font-bold px-2 py-1 rounded-lg shadow-sm tracking-wide">
                                        <i class="fa-solid fa-ban mr-1"></i> Out of Stock
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Book Information Detail Block -->
                        <div>
                            <h3 class="font-bold text-xs sm:text-sm text-slate-900 line-clamp-1 leading-tight" title="<?= htmlspecialchars($book['title']); ?>">
                                <?= htmlspecialchars($book['title']); ?>
                            </h3>
                            <p class="text-[10px] sm:text-[11px] text-slate-600 mt-1 mb-2 line-clamp-1">
                                <i class="fa-regular fa-user mr-1"></i> <?= htmlspecialchars($book['author']); ?>
                            </p>
                        </div>

                        <!-- Price and Action Call-to-Action Buttons -->
                        <div class="mt-auto pt-2">
                            <p class="text-rose-600 font-black text-xs sm:text-sm mb-2.5">
                                <?= number_format($book['price']); ?> ကျပ်
                            </p>
                            
                            <?php if (!$is_out_of_stock): ?>
                                <!-- Standard Active Button for Available Items -->
                                <a href="user/bookdetail.php?id=<?= $book['id']; ?>" class="block text-center w-full bg-blue-500 hover:bg-blue-700 text-white text-xs font-bold py-2 sm:py-2.5 rounded-xl transition shadow-xs">
                                    Details & Buy
                                </a>
                            <?php else: ?>
                                <!-- Disabled State Button for Out of Stock Catalog Items -->
                                <button class="w-full bg-slate-200 text-slate-400 text-xs font-bold py-2 sm:py-2.5 rounded-xl cursor-not-allowed" disabled>
                                    Unavailable
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
            <?php 
                } 
            } else { ?>
                <!-- Fallback empty view template if no match is discovered -->
                <div class="col-span-full text-center py-16 sm:py-20 text-gray-400 italic text-xs sm:text-sm">
                    ⚠️ ရှာဖွေမှုနှင့် ကိုက်ညီသော စာအုပ်များ မရှိသေးပါဗျာ။
                </div>
            <?php } ?>
        </div>
    </main>

    <!-- Shared Footer Navigation -->
    <?php include 'auth/footer.php'; ?>
</body>
</html>