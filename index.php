<?php
session_start();
require_once "config/db.php"; 

// 1. Fetch all available categories ordered alphabetically
$categories_result = $conn->query("SELECT * FROM Categories ORDER BY category_name ASC");
$categories = $categories_result ? $categories_result->fetch_all(MYSQLI_ASSOC) : [];

// 2. Capture incoming dynamic URL request filter parameters
$filter_category_id = isset($_GET['cat_id']) ? intval($_GET['cat_id']) : 0;
$view_all_type = isset($_GET['view_all']) ? $_GET['view_all'] : '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

$base_sql = "SELECT Books.*, Categories.category_name FROM Books LEFT JOIN Categories ON Books.category_id = Categories.id";
$is_filtered = false;
$page_title = "";
$display_books = [];

// 3. Evaluation core query logic based on active navigation filters
if (!empty($search_query)) {
    $stmt = $conn->prepare("$base_sql WHERE Books.title LIKE ? OR Books.author LIKE ? OR Categories.category_name LIKE ? ORDER BY Books.id DESC");
    $search_param = "%" . $search_query . "%";
    $stmt->bind_param("sss", $search_param, $search_param, $search_param);
    $stmt->execute();
    $display_books = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $page_title = "🔎 Search Results for: '" . htmlspecialchars($search_query) . "'";
    $is_filtered = true;
} elseif ($filter_category_id > 0) {
    $stmt = $conn->prepare("$base_sql WHERE Books.category_id = ? ORDER BY Books.id DESC");
    $stmt->bind_param("i", $filter_category_id);
    $stmt->execute();
    $display_books = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $cat_name = "Category";
    foreach ($categories as $c) { 
        if($c['id'] == $filter_category_id) { 
            $cat_name = $c['category_name']; 
            break; 
        } 
    }
    $page_title = "📚 ကဏ္ဍ - " . htmlspecialchars($cat_name);
    $is_filtered = true;
} elseif (!empty($view_all_type)) {
    $is_filtered = true;
    if ($view_all_type === 'available') {
        $res = $conn->query("$base_sql WHERE Books.stock > 0 ORDER BY Books.id DESC");
        $page_title = "📖 Available Books (အားလုံး)";
        $display_books = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    } elseif ($view_all_type === 'new_arrivals') {
        $res = $conn->query("$base_sql WHERE Books.stock > 0 ORDER BY Books.id DESC");
        $page_title = "✨ New Arrivals စာအုပ်များအားလုံး";
        $display_books = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    } elseif ($view_all_type === 'best_sellers') {
        $best_sellers_all_sql = "SELECT Books.*, Categories.category_name, COALESCE(SUM(Order_item.quantity), 0) AS total_sold
                                 FROM Books
                                 LEFT JOIN Order_item ON Books.id = Order_item.book_id
                                 LEFT JOIN Categories ON Books.category_id = Categories.id
                                 WHERE Books.stock > 0
                                 GROUP BY Books.id
                                 ORDER BY total_sold DESC, Books.id DESC";
        $res = $conn->query($best_sellers_all_sql);
        $page_title = "🔥 Best Sellers စာအုပ်များအားလုံး";
        $display_books = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    } else {
        $stmt = $conn->prepare("$base_sql WHERE Categories.category_name LIKE ? ORDER BY Books.id DESC");
        $search_cat = "%" . $view_all_type . "%";
        $stmt->bind_param("s", $search_cat);
        $stmt->execute();
        $display_books = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $page_title = "✨ " . htmlspecialchars($view_all_type) . " စာအုပ်များအားလုံး";
    }
} else {
    $is_filtered = false;
    $available_books = $conn->query("$base_sql WHERE Books.stock > 0 ORDER BY Books.id DESC LIMIT 4")->fetch_all(MYSQLI_ASSOC);
    
    $defined_sections = ['တရားဓမ္မ', 'ကျန်းမာရေး', 'ပုံပြင်', 'စိတ်ပညာ', 'ဝတ္ထု', 'စီးပွားရေး'];
    $section_books = [];
    
    foreach ($defined_sections as $section) {
        $stmt = $conn->prepare("$base_sql WHERE Categories.category_name LIKE ? ORDER BY Books.id DESC LIMIT 4");
        $param = "%" . $section . "%";
        $stmt->bind_param("s", $param);
        $stmt->execute();
        $section_books[$section] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // New Arrivals - latest 4 books
    $new_arrivals = $conn->query("$base_sql WHERE Books.stock > 0 ORDER BY Books.id DESC LIMIT 4")->fetch_all(MYSQLI_ASSOC);

    // Best Sellers - top 4 books by total quantity sold
    $best_sellers_sql = "SELECT Books.*, Categories.category_name, COALESCE(SUM(Order_item.quantity), 0) AS total_sold
                         FROM Books
                         LEFT JOIN Order_item ON Books.id = Order_item.book_id
                         LEFT JOIN Categories ON Books.category_id = Categories.id
                         WHERE Books.stock > 0
                         GROUP BY Books.id
                         ORDER BY total_sold DESC, Books.id DESC
                         LIMIT 4";
    $best_sellers = $conn->query($best_sellers_sql)->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Book Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f8fafc; }
        .hero { 
            background: linear-gradient(rgba(15, 23, 42, 0.6), rgba(15, 23, 42, 0.6)), url('https://images.unsplash.com/photo-1524995997946-a1c2e315a42f') center/cover; 
            height: 320px; 
        }
        .section-title { font-size: 17px; font-weight: 800; color: #1e293b; position: relative; padding-left: 12px; }
        .section-title::before { content: ''; position: absolute; left: 0; top: 4px; bottom: 4px; width: 4px; background: #f59e0b; border-radius: 4px; }
        
        /* New Arrivals & Best Seller Titles Premium Styling */
        .premium-title-new::before { background: #3b82f6 !important; box-shadow: 0 0 10px #3b82f6; }
        .premium-title-best::before { background: #ef4444 !important; box-shadow: 0 0 10px #ef4444; }

        /* Shelf Row Structure */
        .shelf-row { max-width: 1000px; margin: 20px auto 50px auto; position: relative; padding-bottom: 5px; z-index: 10; }
        .shelf-row::before { content: ""; position: absolute; bottom: 0; left: 0; width: 100%; height: 35px; background: #e2e8f0; border-top: 1px solid #cbd5e1; transform: perspective(500px) rotateX(35deg); transform-origin: bottom; z-index: 1; }
        .shelf-row::after { content: ""; position: absolute; top: 100%; left: -0.1%; width: 100.2%; height: 12px; background: linear-gradient(to bottom, #cbd5e1 0%, #94a3b8 100%); border-top: 1px solid #ffffff; z-index: 2; box-shadow: 0 10px 15px rgba(0,0,0,0.08); }
        
        .books-grid { display: grid; padding: 0 40px; align-items: flex-end; position: relative; z-index: 3; gap: 25px; }
        .grid-5 { grid-template-columns: repeat(5, 1fr); }
        .grid-4 { grid-template-columns: repeat(4, 1fr); max-width: 850px; margin: 0 auto; }
        .book-item { display: flex; flex-direction: column; justify-content: flex-end; align-items: center; perspective: 800px; cursor: pointer; }
        .book-cover-wrapper { width: 130px; height: 185px; display: flex; align-items: flex-end; justify-content: center; position: relative; }
        
        /* Unified 3D Leaning Animation Style for ALL Books on every single shelf */
        .book-item img { width: 100%; height: 100%; object-fit: cover; border-radius: 2px 5px 5px 2px; transform-origin: bottom center; transition: all 0.3s ease; filter: drop-shadow(-6px 6px 5px rgba(0,0,0,0.28)); }
        
        /* Specific 3D leaning angles based on client requirements */
        .lean-right img { transform: rotateY(14deg) rotateZ(-0.5deg); }
        .lean-left img { transform: rotateY(-14deg) rotateZ(0.5deg); }

        /* Standardized elegant Hover effects for all types of book items */
        .book-item:hover img { transform: rotateY(0deg) rotateZ(0deg) translateY(-10px) !important; filter: drop-shadow(-2px 12px 10px rgba(0,0,0,0.22)); }

        @media (max-width: 900px) { .grid-5, .grid-4 { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 550px) { .grid-5, .grid-4 { grid-template-columns: repeat(2, 1fr); padding: 0 10px; } .book-cover-wrapper { width: 110px; height: 155px; } }
    </style>
</head>
<body class="text-slate-800">

    <?php include 'auth/header.php'; ?>

    <div class="hero flex flex-col justify-center items-center text-white text-center">
        <h1 class="font-extrabold text-slate-900 bg-white/95 shadow-2xl px-8 py-4 rounded-xl text-2xl md:text-4xl border-b-4 border-amber-500 tracking-wide transition-all duration-300">
            📚 Online Book Shop
        </h1>
        <p class="mt-3 text-sm md:text-base text-gray-200 max-w-md px-4 font-medium drop-shadow">သင့်ဘဝကို မြှင့်တင်ပေးမယ့် စာကောင်းပေမွန်များကို တစ်နေရာတည်းမှာ ရရှိနိုင်ပါသည်</p>
    </div>

    <div class="max-w-[850px] mx-auto px-6 mt-10 mb-4">
        <div class="flex flex-wrap gap-2.5 max-h-[160px] overflow-y-auto pr-1">
            <?php if (!empty($categories)): foreach ($categories as $cat): 
                $is_active = ($filter_category_id === intval($cat['id']));
            ?>
                <a href="index.php?cat_id=<?= $cat['id']; ?>" 
                   class="px-4 py-2 rounded-full text-xs font-bold tracking-wide transition-all duration-300 border flex items-center gap-2 transform active:scale-95 <?= $is_active ? 'bg-amber-500 text-slate-950 border-amber-500 font-extrabold shadow-md shadow-amber-500/20' : 'bg-white text-slate-600 border-slate-200/80 hover:border-amber-500 hover:text-amber-600 shadow-sm shadow-slate-100/50' ?>">
                    <span class="w-1.5 h-1.5 rounded-full <?= $is_active ? 'bg-slate-950 animate-pulse' : 'bg-slate-300 group-hover:bg-amber-500' ?>"></span>
                    <span><?= htmlspecialchars($cat['category_name']); ?></span>
                </a>
            <?php endforeach; else: ?>
                <span class="text-xs text-slate-400 italic py-1">No categories listed.</span>
            <?php endif; ?>
        </div>
    </div>

    <main class="container mx-auto px-6 my-10">
        
        <?php if ($is_filtered): ?>
            <div class="flex justify-between items-center mb-6 max-w-[1000px] mx-auto">
                <h2 class="section-title"><?= $page_title; ?></h2>
            </div>
            <?php 
            $book_chunks = array_chunk($display_books, 5); 
            if(!empty($book_chunks)):
                foreach($book_chunks as $chunk):
            ?>
                <div class="shelf-row">
                    <div class="books-grid grid-5">
                        <?php foreach ($chunk as $index => $book): ?>
                            <a href="user/bookdetail.php?id=<?= $book['id']; ?>" class="book-item lean-right">
                                <div class="book-cover-wrapper">
                                    <img src="uploads/<?= !empty($book['book_image']) ? htmlspecialchars($book['book_image']) : 'default_cover.png'; ?>" alt="Cover">
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php 
                endforeach;
            else: 
            ?>
                <div class="text-center text-sm text-gray-400 py-10 italic">ကိုက်ညီသော စာအုပ်မတွေ့ရှိပါ။</div>
            <?php endif; ?>

        <?php else: ?>
            
            <div class="flex justify-between items-center mb-2 max-w-[850px] mx-auto">
                <div class="flex items-center gap-2">
                    <h2 class="section-title premium-title-new">New Arrivals</h2>
                    <span class="bg-gradient-to-r from-cyan-500 to-blue-600 text-white text-[11px] font-black px-3 py-1 rounded-bl-xl rounded-tr-xl shadow-md shadow-blue-400/30 uppercase tracking-widest flex items-center gap-1 border border-cyan-300/30">
                        <i class="fa-solid fa-wand-magic-sparkles text-[10px] animate-bounce"></i> NEW
                    </span>
                </div>
                <a href="index.php?view_all=new_arrivals" class="text-xs bg-gradient-to-r inline-flex items-center gap-1 from-blue-500 to-indigo-600 text-white px-3 py-1 rounded-full font-bold hover:from-blue-600 hover:to-indigo-700 shadow-sm shadow-blue-500/20 transition-all duration-300">View All <i class="fa-solid fa-chevron-right text-[9px]"></i></a>
            </div>
            <div class="shelf-row">
                <div class="books-grid grid-4">
                    <?php if(!empty($new_arrivals)): foreach ($new_arrivals as $index => $book): 
                        // First 2 books lean left, last 2 books lean right
                        $lean_class = ($index < 2) ? 'lean-left' : 'lean-right';
                    ?>
                        <a href="user/bookdetail.php?id=<?= $book['id']; ?>" class="book-item <?= $lean_class; ?>">
                            <div class="book-cover-wrapper">
                                <img src="uploads/<?= htmlspecialchars($book['book_image'] ?: 'default_cover.png'); ?>" alt="Cover">
                            </div>
                        </a>
                    <?php endforeach; else: ?>
                        <div class="col-span-4 text-center text-xs text-gray-400 italic py-4">စာအုပ်များ မရှိသေးပါ။</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="flex justify-between items-center mb-2 mt-6 max-w-[850px] mx-auto">
                <div class="flex items-center gap-2">
                    <h2 class="section-title premium-title-best">Best Sellers</h2>
                    <span class="bg-gradient-to-r from-red-500 to-amber-500 text-white text-[11px] font-black px-3 py-1 rounded-tl-xl rounded-br-xl shadow-md shadow-red-500/40 uppercase tracking-widest flex items-center gap-1 border border-red-400/30">
                        <i class="fa-solid fa-fire-flame-curved text-[10px] animate-pulse text-yellow-300"></i> HOT
                    </span>
                </div>
                <a href="index.php?view_all=best_sellers" class="text-xs bg-gradient-to-r inline-flex items-center gap-1 from-red-500 to-orange-500 text-white px-3 py-1 rounded-full font-bold hover:from-red-600 hover:to-orange-600 shadow-sm shadow-red-500/20 transition-all duration-300">View All <i class="fa-solid fa-chevron-right text-[9px]"></i></a>
            </div>
            <div class="shelf-row">
                <div class="books-grid grid-4">
                    <?php if(!empty($best_sellers)): foreach ($best_sellers as $index => $book): 
                        // Outer 2 books (index 0, 3) lean right, middle 2 books (index 1, 2) lean left
                        $lean_class = ($index == 0 || $index == 3) ? 'lean-right' : 'lean-left';
                    ?>
                        <a href="user/bookdetail.php?id=<?= $book['id']; ?>" class="book-item <?= $lean_class; ?>">
                            <div class="book-cover-wrapper">
                                <img src="uploads/<?= htmlspecialchars($book['book_image'] ?: 'default_cover.png'); ?>" alt="Cover">
                            </div>
                        </a>
                    <?php endforeach; else: ?>
                        <div class="col-span-4 text-center text-xs text-gray-400 italic py-4">စာအုပ်များ မရှိသေးပါ။</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="flex justify-between items-end mb-2 mt-6 max-w-[850px] mx-auto">
                <h2 class="section-title">Available Books</h2>
                <a href="index.php?view_all=available" class="text-xs bg-slate-200 text-slate-700 px-3 py-1 rounded-md font-bold hover:bg-amber-500 hover:text-slate-900 transition">View All</a>
            </div>
            <div class="shelf-row">
                <div class="books-grid grid-4">
                    <?php if(!empty($available_books)): foreach ($available_books as $index => $book): 
                        // First 2 books lean left, last 2 books lean right
                        $lean_class = ($index < 2) ? 'lean-left' : 'lean-right';
                    ?>
                        <a href="user/bookdetail.php?id=<?= $book['id']; ?>" class="book-item <?= $lean_class; ?>">
                            <div class="book-cover-wrapper"><img src="uploads/<?= htmlspecialchars($book['book_image'] ?: 'default_cover.png'); ?>" alt="Cover"></div>
                        </a>
                    <?php endforeach; else: ?>
                        <div class="col-span-4 text-center text-xs text-gray-400 italic py-4">စာအုပ်များ မရှိသေးပါ။</div>
                    <?php endif; ?>
                </div>
            </div>

            <?php foreach ($section_books as $section_name => $books_list): ?>
                <div class="flex justify-between items-end mb-2 mt-6 max-w-[850px] mx-auto">
                    <h2 class="section-title"><?= htmlspecialchars($section_name); ?> စာအုပ်များ</h2>
                    <a href="index.php?view_all=<?= urlencode($section_name); ?>" class="text-xs bg-slate-200 text-slate-700 px-3 py-1 rounded-md font-bold hover:bg-amber-500 hover:text-slate-900 transition">View All</a>
                </div>
                <div class="shelf-row">
                    <div class="books-grid grid-4">
                        <?php if(!empty($books_list)): foreach ($books_list as $index => $book): 
                            // If category is "ပုံပြင်", first 2 books lean left, last 2 lean right. Otherwise default to lean-right.
                            if ($section_name === 'ပုံပြင်') {
                                $lean_class = ($index < 2) ? 'lean-left' : 'lean-right';
                            } else {
                                $lean_class = 'lean-right';
                            }
                        ?>
                            <a href="user/bookdetail.php?id=<?= $book['id']; ?>" class="book-item <?= $lean_class; ?>">
                                <div class="book-cover-wrapper"><img src="uploads/<?= htmlspecialchars($book['book_image'] ?: 'default_cover.png'); ?>" alt="Cover"></div>
                            </a>
                        <?php endforeach; else: ?>
                            <div class="col-span-4 text-center text-xs text-gray-400 italic py-4"><?= htmlspecialchars($section_name); ?>ကဏ္ဍစာအုပ်များ မရှိသေးပါ။</div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>

    <?php include 'auth/footer.php'; ?>
</body>
</html>