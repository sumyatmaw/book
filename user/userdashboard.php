<?php
session_start();
require_once '../config/db.php';
// user_dashboard.php ရဲ့ အပေါ်နားက Query ကို ဒီလိုပြင်ပါ
$cat_sql = "SELECT DISTINCT category_name, id FROM Categories GROUP BY category_name ORDER BY category_name ASC";
$categories_result = $conn->query($cat_sql);

// Fetch Categories for Sidebar/Filter
//$cat_sql = "SELECT * FROM Categories ORDER BY category_name ASC";
//$categories_result = $conn->query($cat_sql);

// Handle Filter & Search parameters
$selected_category = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Base Query for Books
$book_sql = "SELECT Books.*, Categories.category_name FROM Books 
             LEFT JOIN Categories ON Books.category_id = Categories.id 
             WHERE 1=1";

// Apply category filter if selected
if ($selected_category > 0) {
    $book_sql .= " AND Books.category_id = $selected_category";
}

// Apply search filter if keyword entered
if (!empty($search_query)) {
    $safe_search = $conn->real_escape_string($search_query);
    $book_sql .= " AND (Books.title LIKE '%$safe_search%' OR Books.author LIKE '%$safe_search%')";
}

$book_sql .= " ORDER BY Books.id DESC";
$books_result = $conn->query($book_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Book Shop - Home</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen font-sans">

    <nav class="bg-white shadow-sm sticky top-0 z-50 border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="index.php" class="text-2xl font-bold text-blue-600 flex items-center gap-2">
                        <i class="fa-solid fa-book-open"></i> BOOKSHOP
                    </a>
                </div>

                <div class="flex-1 flex items-center justify-center px-6 max-w-md mx-auto hidden md:flex">
                    <form action="index.php" method="GET" class="w-full relative">
                        <?php if($selected_category > 0): ?>
                            <input type="hidden" name="category_id" value="<?= $selected_category; ?>">
                        <?php endif; ?>
                        <input type="text" name="search" value="<?= htmlspecialchars($search_query); ?>" placeholder="Search books or authors..." class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-full focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 text-sm">
                        <div class="absolute left-3.5 top-2.5 text-gray-400">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </div>
                    </form>
                </div>

                <div class="flex items-center gap-5">
                    <a href="cart.php" class="text-gray-600 hover:text-blue-600 relative p-2 transition">
                        <i class="fa-solid fa-cart-shopping text-xl"></i>
                        <span class="absolute top-0 right-0 bg-red-500 text-white text-xxs px-1.5 py-0.5 rounded-full font-bold text-[10px]">3</span>
                    </a>

                    <?php if (isset($_SESSION['user_role'])): ?>
                        <a href="my_orders.php" class="text-sm font-medium text-gray-700 hover:text-blue-600">My Orders</a>
                        <a href="auth/logout.php" class="text-sm font-medium text-red-600 hover:underline">Logout</a>
                    <?php else: ?>
                        <a href="auth/login.php" class="text-sm font-medium text-gray-700 hover:text-blue-600">Login</a>
                        <a href="auth/register.php" class="bg-blue-600 text-white px-4 py-2 rounded-full text-sm font-medium hover:bg-blue-700 transition">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <div class="bg-gradient-to-r from-blue-600 to-indigo-700 text-white py-12 px-4 text-center">
        <h2 class="text-3xl md:text-4xl font-extrabold mb-3">Welcome to Our Bookstore</h2>
        <p class="text-blue-100 max-w-xl mx-auto text-sm md:text-base">Discover your next great read. Browse through thousands of history, novel, and educational books.</p>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            
            <div class="space-y-2">
    <label for="category_select" class="block text-sm font-bold text-gray-700">Filter by Category:</label>
    <select id="category_select" onchange="location = this.value;" class="w-full bg-white border border-gray-300 text-gray-700 py-2.5 px-4 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm">
        
        
        
        <?php 
        // Query ကို တစ်ခါတည်း DISTINCT လုပ်ပြီး ဆွဲထုတ်ထားသည်
        $dropdown_cat_sql = "SELECT MIN(id) as id, category_name FROM Categories GROUP BY category_name ORDER BY category_name ASC";
        $dropdown_result = $conn->query($dropdown_cat_sql);
        
        if ($dropdown_result && $dropdown_result->num_rows > 0): 
            while ($cat = $dropdown_result->fetch_assoc()): 
        ?>
            <option value="user_dashboard.php?category_id=<?= $cat['id']; ?>&search=<?= urlencode($search_query); ?>" <?= $selected_category === (int)$cat['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($cat['category_name']); ?>
            </option>
        <?php 
            endwhile; 
        endif; 
        ?>
    </select>
</div>

            <div class="lg:col-span-3 space-y-6">
                <div class="flex justify-between items-center">
                    <h3 class="text-xl font-bold text-gray-800">Available Books</h3>
                    <span class="text-xs text-gray-500 font-medium">Found <?= $books_result ? $books_result->num_rows : 0; ?> books</span>
                </div>

                <?php if ($books_result && $books_result->num_rows > 0): ?>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-6">
                        <?php while ($book = $books_result->fetch_assoc()): ?>
                            <div class="bg-white border border-gray-100 rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition flex flex-col justify-between group">
                                <a href="book_detail.php?id=<?= $book['id']; ?>" class="block overflow-hidden bg-gray-50 aspect-[3/4]">
                                    <?php if (!empty($book['book_image'])): ?>
                                        <img src="../uploads/<?= htmlspecialchars($book['book_image']); ?>" alt="Book Cover" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                                    <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center text-gray-400">No Image</div>
                                    <?php endif; ?>
                                </a>
                                
                                <div class="p-4 space-y-1 flex-1 flex flex-col justify-between">
                                    <div>
                                        <span class="text-[11px] font-bold text-blue-500 uppercase tracking-wide"><?= htmlspecialchars($book['category_name'] ?? 'General'); ?></span>
                                        <a href="book_detail.php?id=<?= $book['id']; ?>" class="block font-semibold text-gray-800 hover:text-blue-600 text-sm line-clamp-2 mt-0.5">
                                            <?= htmlspecialchars($book['title']); ?>
                                        </a>
                                        <p class="text-xs text-gray-500 italic">by <?= htmlspecialchars($book['author']); ?></p>
                                    </div>

                                    <div class="pt-3 flex items-center justify-between mt-auto">
                                        <span class="text-sm font-bold text-gray-900"><?= number_format($book['price']); ?> MMK</span>
                                        
                                        <?php if ($book['stock'] > 0): ?>
                                            <a href="book_detail.php?id=<?= $book['id']; ?>" class="bg-blue-50 hover:bg-blue-600 text-blue-600 hover:text-white px-3 py-1.5 rounded-lg text-xs font-semibold transition flex items-center gap-1.5">
                                                <i class="fa-solid fa-plus text-[10px]"></i> View
                                            </a>
                                        <?php else: ?>
                                            <span class="bg-red-50 text-red-600 px-2 py-1 rounded text-xxs font-bold">Out of stock</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="bg-white text-center py-16 px-4 rounded-2xl border border-dashed border-gray-200">
                        <div class="text-gray-300 text-5xl mb-3">
                            <i class="fa-solid fa-box-open"></i>
                        </div>
                        <h4 class="text-lg font-bold text-gray-700">No Books Found</h4>
                        <p class="text-gray-400 text-xs mt-1">Try adjusting your filters or search keywords.</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

</body>
</html>