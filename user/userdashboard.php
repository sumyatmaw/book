<?php
session_start();
require_once '../config/db.php';

// Customer Login Check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'customer') {
    header("Location: ../auth/login.php");
    exit();
}

$name = $_SESSION['user_name'];

// ၁။ Dropdown ဘားအတွက် ဓာတ်ကူပြု Categories များကို DISTINCT ဖြင့် ဆွဲထုတ်ခြင်း
$dropdown_cat_sql = "SELECT MIN(id) as id, category_name FROM Categories GROUP BY category_name ORDER BY category_name ASC";
$dropdown_result = $conn->query($dropdown_cat_sql);

// ၂။ Dynamic Filter & Search parameters များကို URL ကနေ ဖမ်းယူခြင်း
$selected_category = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// ၃။ စာအုပ်များ ပြသရန်အတွက် Base SQL Query တည်ဆောက်ခြင်း
$book_sql = "SELECT Books.*, Categories.category_name FROM Books 
             LEFT JOIN Categories ON Books.category_id = Categories.id 
             WHERE 1=1";

// Category Filter သတ်မှတ်ထားလျှင် ထည့်သွင်းစစ်ဆေးခြင်း
if ($selected_category > 0) {
    $book_sql .= " AND Books.category_id = $selected_category";
}

// Search Keyword ပါရှိလျှင် ထည့်သွင်းရှာဖွေခြင်း
if (!empty($search_query)) {
    $safe_search = $conn->real_escape_string($search_query);
    $book_sql .= " AND (Books.title LIKE '%$safe_search%' OR Books.author LIKE '%$safe_search%')";
}

$book_sql .= " ORDER BY Books.id DESC";
$books_result = $conn->query($book_sql);
$currentPage = 'userdashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - Online Book Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen font-sans text-slate-800 flex flex-col">

    <?php include '../auth/header.php'; ?>

    <!-- 🔵 Welcome Hero-->
    <section class="bg-blue-600 text-white py-12 shadow-inner">
        <div class="max-w-7xl mx-auto px-6">
            <h2 class="text-3xl md:text-4xl font-bold mb-2">
                Welcome, <?php echo htmlspecialchars($name); ?> 👋
            </h2>
            <p class="text-blue-100 text-sm md:text-base">Find your favourite books anytime & discover your next great read.</p>
        </div>
    </section>

    <!-- 🔍 Search Bar & Dropdown Filter ကဏ္ဍ -->
    <div class="max-w-7xl mx-auto mt-8 px-6 grid grid-cols-1 md:grid-cols-4 gap-4">
        
        <!-- Search Input -->
        <div class="md:col-span-3">
            <form action="userdashboard.php" method="GET" class="w-full relative flex">
                <?php if($selected_category > 0): ?>
                    <input type="hidden" name="category_id" value="<?= $selected_category; ?>">
                <?php endif; ?>
                <input type="text" name="search" value="<?= htmlspecialchars($search_query); ?>" placeholder="Search books or authors..." class="w-full border rounded-l-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white text-sm shadow-sm">
                <button type="submit" class="bg-blue-600 text-white px-6 rounded-r-xl hover:bg-blue-700 font-bold transition">
                    <i class="fa-solid fa-magnifying-glass mr-1"></i> Search
                </button>
            </form>
        </div>

        <!-- Category Dropdown Filter -->
        <div class="w-full">
            <select id="category_select" onchange="location = this.value;" class="w-full bg-white border border-gray-300 text-gray-700 py-3 px-4 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm font-medium">
                <option value="userdashboard.php?category_id=0&search=<?= urlencode($search_query); ?>">-- All Categories --</option>
                <?php 
                if ($dropdown_result && $dropdown_result->num_rows > 0): 
                    while ($cat = $dropdown_result->fetch_assoc()): 
                ?>
                    <option value="userdashboard.php?category_id=<?= $cat['id']; ?>&search=<?= urlencode($search_query); ?>" <?= $selected_category === (int)$cat['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['category_name']); ?>
                    </option>
                <?php 
                    endwhile; 
                endif; 
                ?>
            </select>
        </div>
    </div>

    <!-- 🎴 Dashboard Shortcut Cards -->
    <div class="max-w-7xl mx-auto px-6 mt-10 grid grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6">
        <a href="books.php" class="bg-white shadow rounded-xl p-5 hover:shadow-xl hover:-translate-y-1 transition duration-200 group">
            <div class="text-4xl mb-2 group-hover:scale-110 transition duration-200">📚</div>
            <h3 class="font-bold text-base md:text-lg text-slate-800">Browse Books</h3>
            <p class="text-gray-400 text-xs mt-1 hidden sm:block">View all store collections.</p>
        </a>

        <a href="cart.php" class="bg-white shadow rounded-xl p-5 hover:shadow-xl hover:-translate-y-1 transition duration-200 group">
            <div class="text-4xl mb-2 group-hover:scale-110 transition duration-200">🛒</div>
            <h3 class="font-bold text-base md:text-lg text-slate-800">My Cart</h3>
            <p class="text-gray-400 text-xs mt-1 hidden sm:block">Books you've added.</p>
        </a>

        <a href="myorders.php" class="bg-white shadow rounded-xl p-5 hover:shadow-xl hover:-translate-y-1 transition duration-200 group">
            <div class="text-4xl mb-2 group-hover:scale-110 transition duration-200">📦</div>
            <h3 class="font-bold text-base md:text-lg text-slate-800">My Orders</h3>
            <p class="text-gray-400 text-xs mt-1 hidden sm:block">Track your orders.</p>
        </a>

        <a href="userprofile.php" class="bg-white shadow rounded-xl p-5 hover:shadow-xl hover:-translate-y-1 transition duration-200 group">
            <div class="text-4xl mb-2 group-hover:scale-110 transition duration-200">👤</div>
            <h3 class="font-bold text-base md:text-lg text-slate-800">Profile</h3>
            <p class="text-gray-400 text-xs mt-1 hidden sm:block">Manage your account.</p>
        </a>
    </div>

    <!-- 📖 Books Display Grid Section -->
    <div class="max-w-7xl mx-auto px-6 mt-14 pb-20">
        <div class="flex justify-between items-center mb-6 border-b pb-4 border-gray-200">
            <h2 class="text-2xl font-bold text-slate-900 flex items-center gap-2">
                <i class="fa-solid fa-layer-group text-blue-600"></i> Available Books
            </h2>
            <span class="text-xs bg-slate-200 text-slate-700 px-3 py-1 rounded-full font-bold">
                <?= $books_result ? $books_result->num_rows : 0; ?> books found
            </span>
        </div>

        <?php if ($books_result && $books_result->num_rows > 0): ?>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                <?php while ($book = $books_result->fetch_assoc()): ?>
                    <div class="bg-white border border-gray-100 rounded-2xl overflow-hidden shadow-sm hover:shadow-lg transition duration-300 flex flex-col justify-between group">
                        
                        <!-- မျက်နှာဖုံးပုံစံ -->
                        <a href="bookdetail.php?id=<?= $book['id']; ?>" class="block overflow-hidden bg-slate-50 aspect-[3/4] relative">
                            <?php if (!empty($book['book_image'])): ?>
                                <img src="../uploads/<?= htmlspecialchars($book['book_image']); ?>" alt="Book Cover" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center text-gray-400 bg-slate-100 text-xs font-bold">No Image</div>
                            <?php endif; ?>
                            
                            <?php if ($book['stock'] <= 0): ?>
                                <div class="absolute inset-0 bg-black/40 flex items-center justify-center backdrop-blur-[1px]">
                                    <span class="bg-red-600 text-white text-xs px-3 py-1 rounded-full font-extrabold shadow-md">Out of stock</span>
                                </div>
                            <?php endif; ?>
                        </a>
                        
                        <!-- အသေးစိတ်အချက်အလက်များ -->
                        <div class="p-4 flex-1 flex flex-col justify-between bg-white">
                            <div>
                                <span class="text-[10px] font-black text-blue-600 uppercase tracking-wider bg-blue-50 px-2 py-0.5 rounded">
                                    <?= htmlspecialchars($book['category_name'] ?? 'General'); ?>
                                </span>
                                <a href="bookdetail.php?id=<?= $book['id']; ?>" class="block font-bold text-gray-800 hover:text-blue-600 text-sm line-clamp-2 mt-2 leading-tight">
                                    <?= htmlspecialchars($book['title']); ?>
                                </a>
                                <p class="text-xs text-gray-400 mt-1 italic">by <?= htmlspecialchars($book['author']); ?></p>
                            </div>

                            <div class="pt-4 flex items-center justify-between border-t border-slate-50 mt-4">
                                <span class="text-sm font-black text-slate-900"><?= number_format($book['price']); ?> MMK</span>
                                
                                <?php if ($book['stock'] > 0): ?>
                                    <a href="bookdetail.php?id=<?= $book['id']; ?>" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1 shadow-sm">
                                        <i class="fa-solid fa-eye text-[10px]"></i> View
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <!-- စာအုပ်မရှိလျှင် ပြသမည့် Area -->
            <div class="bg-white text-center py-16 px-4 rounded-2xl border border-dashed border-gray-300 max-w-md mx-auto mt-6">
                <div class="text-gray-300 text-5xl mb-4">
                    <i class="fa-solid fa-box-open"></i>
                </div>
                <h4 class="text-lg font-bold text-gray-700">No matching books found</h4>
                <p class="text-gray-400 text-xs mt-1">Try searching with different keywords or categories.</p>
                <a href="userdashboard.php" class="inline-block mt-4 text-xs font-bold text-blue-600 hover:underline">Start Over</a>
            </div>
        <?php endif; ?>
    </div>

    <?php include '../auth/footer.php'; ?>
</body>
</html>