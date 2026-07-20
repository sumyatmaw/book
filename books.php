<?php
// 1. Session and Database Connection
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "config/db.php"; 

// 2. Fetch categories for Header Dropdown
$categories = [];
$cat_result = mysqli_query($conn, "SELECT * FROM Categories ORDER BY category_name ASC");
if ($cat_result) {
    while ($row = mysqli_fetch_assoc($cat_result)) {
        $categories[] = $row;
    }
}

// 3. Logic: Read Parameters from URL
$cat_id = isset($_GET['cat_id']) ? intval($_GET['cat_id']) : 0;
$search_query = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : "";

// 4. Build SQL Query based on conditions
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
<body class="bg-gray-50 text-gray-800">

    <!-- Shared Header -->
    <?php include 'auth/header.php'; ?>

    <!-- 📚 MAIN CONTENT (Book Showcase Section) -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 py-10">
        
        <!-- Dynamic Page Title -->
        <h2 class="text-xl font-black text-slate-900 mb-8 border-l-4 border-amber-500 pl-3">
            <?= $page_title; ?>
        </h2>

        <!-- Fully Responsive Book Shelf Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6">
            <?php 
            if (mysqli_num_rows($books_result) > 0) {
                while ($book = mysqli_fetch_assoc($books_result)) { 
                    // Check stock condition dynamically
                    $is_out_of_stock = ($book['stock'] <= 0);
            ?>
                    <!-- Individual Book Card Element -->
                    <div class="bg-white p-4 rounded-2xl shadow-sm border border-gray-100 flex flex-col justify-between hover:shadow-md transition duration-300 group relative">
                        
                        <!-- Book Cover Container with Stock Overlay Layout -->
                        <div class="relative overflow-hidden rounded-xl mb-4">
                            <img src="uploads/<?= htmlspecialchars($book['book_image'] ?? 'default.jpg'); ?>" class="w-full h-56 object-cover bg-gray-50 shadow-sm group-hover:scale-105 transition duration-300 <?= $is_out_of_stock ? 'opacity-40 blur-[1px]' : ''; ?>">
                            
                            <?php if ($is_out_of_stock): ?>
                                <!-- Visual Overlay Badge for Out of Stock Status -->
                                <div class="absolute inset-0 flex items-center justify-center bg-black/5 rounded-xl">
                                    <span class="bg-rose-600 text-white text-[11px] font-bold px-2.5 py-1 rounded-lg shadow-sm tracking-wide">
                                        <i class="fa-solid fa-ban mr-1"></i> Out of Stock
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Book Information Detail Block -->
                        <div>
                            <h3 class="font-bold text-sm text-slate-800 line-clamp-1 leading-tight"><?= htmlspecialchars($book['title']); ?></h3>
                            <p class="text-[11px] text-gray-400 mt-1 mb-2"><i class="fa-regular fa-user mr-1"></i> <?= htmlspecialchars($book['author']); ?></p>
                        </div>

                        <!-- Price and Action Call-to-Action Buttons -->
                        <div class="mt-auto">
                            <p class="text-red-600 font-black text-sm mb-3"><?= number_format($book['price']); ?> ကျပ်</p>
                            
                            <?php if (!$is_out_of_stock): ?>
                                <!-- Standard Active Button for Available Items -->
                                <a href="user/bookdetail.php?id=<?= $book['id']; ?>" class="block text-center w-full bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold py-2.5 rounded-xl transition shadow-xs">
                                    Details & Buy
                                </a>
                            <?php else: ?>
                                <!-- Disabled State Button for Out of Stock Catalog Items -->
                                <button class="w-full bg-slate-200 text-slate-400 text-xs font-bold py-2.5 rounded-xl cursor-not-allowed" disabled>
                                    Unavailable
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
            <?php 
                } 
            } else { ?>
                <!-- Fallback empty view template if no match is discovered -->
                <div class="col-span-full text-center py-20 text-gray-400 italic text-sm">
                    ⚠️ ရှာဖွေမှုနှင့် ကိုက်ညီသော စာအုပ်များ မရှိသေးပါဗျာ။
                </div>
            <?php } ?>
        </div>
    </main>

    <!-- Shared Footer -->
    <?php include 'auth/footer.php'; ?>
</body>
</html>