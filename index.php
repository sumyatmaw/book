<?php
session_start();
require_once "config/db.php"; 

// ၁။ Categories အားလုံးကို ဆွဲထုတ်ခြင်း
$categories_result = $conn->query("SELECT * FROM Categories ORDER BY category_name ASC");
$categories = $categories_result ? $categories_result->fetch_all(MYSQLI_ASSOC) : [];

// ၂။ URL ကလာမယ့် Dynamic Filter သတ်မှတ်ချက်များကို ဖမ်းယူခြင်း
$filter_category_id = isset($_GET['cat_id']) ? intval($_GET['cat_id']) : 0;
$view_all_type = isset($_GET['view_all']) ? $_GET['view_all'] : '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

$base_sql = "SELECT Books.*, Categories.category_name FROM Books LEFT JOIN Categories ON Books.category_id = Categories.id";
$is_filtered = false;
$page_title = "";
$display_books = [];

// ၃။ Logic အပိုင်း - View All သို့မဟုတ် Search သို့မဟုတ် Category Filter လုပ်ဆောင်ချက်များ
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
        /* Hero ပုံအရွယ်အစားကို 180px မှ 320px သို့ တိုးမြှင့်ထားပါသည် */
        .hero { 
            background: linear-gradient(rgba(15, 23, 42, 0.6), rgba(15, 23, 42, 0.6)), url('https://images.unsplash.com/photo-1524995997946-a1c2e315a42f') center/cover; 
            height: 320px; 
        }
        .section-title { font-size: 17px; font-weight: 800; color: #1e293b; position: relative; padding-left: 12px; }
        .section-title::before { content: ''; position: absolute; left: 0; top: 4px; bottom: 4px; width: 4px; background: #f59e0b; border-radius: 4px; }
        
        /* Dropdown ကွယ်မသွားစေရန် စင်များ၏ အလွှာထပ်မှုကို z-10 ဟု လျှော့ချသတ်မှတ်ထားပါသည် */
        .shelf-row { max-width: 1000px; margin: 20px auto 50px auto; position: relative; padding-bottom: 5px; z-index: 10; }
        .shelf-row::before { content: ""; position: absolute; bottom: 0; left: 0; width: 100%; height: 35px; background: #e2e8f0; border-top: 1px solid #cbd5e1; transform: perspective(500px) rotateX(35deg); transform-origin: bottom; z-index: 1; }
        .shelf-row::after { content: ""; position: absolute; top: 100%; left: -0.1%; width: 100.2%; height: 12px; background: linear-gradient(to bottom, #cbd5e1 0%, #94a3b8 100%); border-top: 1px solid #ffffff; z-index: 2; box-shadow: 0 10px 15px rgba(0,0,0,0.08); }
        
        .books-grid { display: grid; padding: 0 40px; align-items: flex-end; position: relative; z-index: 3; gap: 25px; }
        .grid-5 { grid-template-columns: repeat(5, 1fr); }
        .grid-4 { grid-template-columns: repeat(4, 1fr); max-width: 850px; margin: 0 auto; }
        .book-item { display: flex; flex-direction: column; justify-content: flex-end; align-items: center; perspective: 800px; cursor: pointer; }
        .book-cover-wrapper { width: 130px; height: 185px; display: flex; align-items: flex-end; justify-content: center; }
        .book-item img { width: 100%; height: 100%; object-fit: cover; border-radius: 2px 5px 5px 2px; transform: rotateY(14deg) rotateZ(-0.5deg); transform-origin: bottom center; transition: all 0.3s ease; filter: drop-shadow(-6px 6px 5px rgba(0,0,0,0.28)); }
        .book-item:hover img { transform: rotateY(0deg) rotateZ(0deg) translateY(-10px); filter: drop-shadow(-2px 12px 10px rgba(0,0,0,0.22)); }
        
        @media (max-width: 900px) { .grid-5, .grid-4 { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 550px) { .grid-5, .grid-4 { grid-template-columns: repeat(2, 1fr); padding: 0 10px; } .book-cover-wrapper { width: 110px; height: 155px; } }
        
        .star-rating i { cursor: pointer; transition: color 0.2s ease-in-out; }
        .star-rating i.active { color: #f59e0b; }
    </style>
</head>
<body class="text-slate-800">

    <!-- Header -->
    <?php include 'auth/header.php'; ?>

    <!-- Hero Section -->
    <div class="hero flex flex-col justify-center items-center text-white text-center">
        <h1 class="font-extrabold text-slate-900 bg-white/95 shadow-2xl px-8 py-4 rounded-xl text-2xl md:text-4xl border-b-4 border-amber-500 tracking-wide transition-all duration-300">
            📚 Online Book Shop
        </h1>
        <p class="mt-3 text-sm md:text-base text-gray-200 max-w-md px-4 font-medium drop-shadow">သင့်ဘဝကို မြှင့်တင်ပေးမယ့် စာကောင်းပေမွန်များကို တစ်နေရာတည်းမှာ ရရှိနိုင်ပါသည်</p>
    </div>

    <main class="container mx-auto px-6 my-10">
        
        <?php if ($is_filtered): ?>
            <div class="flex justify-between items-center mb-6 max-w-[1000px] mx-auto">
                <h2 class="section-title"><?= $page_title; ?></h2>
                <a href="index.php" class="text-xs text-blue-600 font-bold hover:underline"><i class="fas fa-arrow-left"></i> Home သို့ပြန်သွားရန်</a>
            </div>
            <?php 
            $book_chunks = array_chunk($display_books, 5); 
            if(!empty($book_chunks)):
                foreach($book_chunks as $chunk):
            ?>
                <div class="shelf-row">
                    <div class="books-grid grid-5">
                        <?php foreach ($chunk as $book): ?>
                            <div class="book-item" onclick="openBookModal(<?= htmlspecialchars(json_encode($book)); ?>)">
                                <div class="book-cover-wrapper">
                                    <img src="uploads/<?= !empty($book['book_image']) ? htmlspecialchars($book['book_image']) : 'default_cover.png'; ?>" alt="Cover">
                                </div>
                            </div>
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
            
            <!-- စင် ၁ - Available Books -->
            <div class="flex justify-between items-end mb-2 max-w-[850px] mx-auto">
                <h2 class="section-title">Available Books</h2>
                <a href="index.php?view_all=available" class="text-xs bg-slate-200 text-slate-700 px-3 py-1 rounded-md font-bold hover:bg-amber-500 hover:text-slate-900 transition">View All</a>
            </div>
            <div class="shelf-row">
                <div class="books-grid grid-4">
                    <?php if(!empty($available_books)): foreach ($available_books as $book): ?>
                        <div class="book-item" onclick="openBookModal(<?= htmlspecialchars(json_encode($book)); ?>)">
                            <div class="book-cover-wrapper"><img src="uploads/<?= htmlspecialchars($book['book_image']); ?>" alt="Cover"></div>
                        </div>
                    <?php endforeach; else: ?>
                        <div class="col-span-4 text-center text-xs text-gray-400 italic py-4">စာအုပ်များ မရှိသေးပါ။</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- စင် ၂ မှ ၇ -->
            <?php foreach ($section_books as $section_name => $books_list): ?>
                <div class="flex justify-between items-end mb-2 mt-6 max-w-[850px] mx-auto">
                    <h2 class="section-title"><?= htmlspecialchars($section_name); ?> စာအုပ်များ</h2>
                    <a href="index.php?view_all=<?= urlencode($section_name); ?>" class="text-xs bg-slate-200 text-slate-700 px-3 py-1 rounded-md font-bold hover:bg-amber-500 hover:text-slate-900 transition">View All</a>
                </div>
                <div class="shelf-row">
                    <div class="books-grid grid-4">
                        <?php if(!empty($books_list)): foreach ($books_list as $book): ?>
                            <div class="book-item" onclick="openBookModal(<?= htmlspecialchars(json_encode($book)); ?>)">
                                <div class="book-cover-wrapper"><img src="uploads/<?= htmlspecialchars($book['book_image']); ?>" alt="Cover"></div>
                            </div>
                        <?php endforeach; else: ?>
                            <div class="col-span-4 text-center text-xs text-gray-400 italic py-4"><?= htmlspecialchars($section_name); ?>ကဏ္ဍစာအုပ်များ မရှိသေးပါ။</div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>

    <!-- Modal Box ကဏ္ဍ -->
    <div id="bookModal" class="fixed inset-0 bg-black/70 z-50 flex items-center justify-center p-4 hidden backdrop-blur-sm">
        <div class="bg-white rounded-2xl max-w-3xl w-full overflow-hidden shadow-2xl relative flex flex-col md:flex-row max-h-[90vh] overflow-y-auto">
            <button onclick="closeBookModal()" class="absolute top-3 right-4 text-gray-500 text-xl z-10 hover:text-slate-800">✕</button>
            <div class="bg-slate-50 p-6 flex flex-col items-center justify-center md:w-2/5 border-r border-gray-100">
                <img id="modalImage" class="max-h-72 object-contain shadow-xl border rounded-md mb-2" src="" alt="Cover">
            </div>
            <div class="p-6 md:w-3/5 flex flex-col justify-between bg-white">
                <div>
                    <h3 id="modalTitle" class="text-xl font-black text-slate-950 mb-1 leading-snug"></h3>
                    <p id="modalAuthor" class="text-xs text-red-500 font-extrabold mb-4"></p>
                    <div class="grid grid-cols-2 gap-3 bg-slate-50 p-3 rounded-xl text-[11px] mb-4 border border-gray-100">
                        <div>📁 အမျိုးအစား: <span id="modalCategory" class="font-black text-slate-800"></span></div>
                        <div>📦 လက်ကျန်: <span id="modalStock" class="font-black text-slate-800"></span> အုပ်</div>
                    </div>
                    <div class="bg-amber-50/50 border border-amber-100 rounded-xl p-3 mb-4">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="text-slate-700 text-[11px] font-bold">စာအုပ်အဆင့်သတ်မှတ်ရန်:</span>
                            <div class="star-rating text-gray-300 text-sm flex gap-1" id="starContainer">
                                <i class="fas fa-star" onclick="rateStars(1)"></i>
                                <i class="fas fa-star" onclick="rateStars(2)"></i>
                                <i class="fas fa-star" onclick="rateStars(3)"></i>
                                <i class="fas fa-star" onclick="rateStars(4)"></i>
                                <i class="fas fa-star" onclick="rateStars(5)"></i>
                            </div>
                            <span id="ratingStatusText" class="text-amber-600 font-black text-[11px] ml-1">0/5</span>
                        </div>
                        <textarea id="modalComment" rows="2" placeholder="ဒီစာအုပ်လေးအပေါ် သင့်အမြင် သို့မဟုတ် မှတ်ချက်ရေးပေးပါ..." class="w-full bg-white border border-gray-200 rounded-lg p-2 text-xs focus:outline-none focus:border-amber-500 placeholder:text-gray-400 resize-none"></textarea>
                    </div>
                </div>
                <div class="border-t border-gray-100 pt-4">
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-xs text-gray-500 font-semibold">ရောင်းဈေး</span>
                        <span class="text-xl font-black text-slate-900"><span id="modalPrice"></span> ကျပ်</span>
                    </div>
                    <div class="flex flex-col gap-3">
                        <div class="flex items-center gap-3">
                            <span class="text-xs text-gray-600 font-bold">အရေအတွက်</span>
                            <div class="flex items-center border border-gray-200 rounded-xl overflow-hidden bg-slate-50">
                                <button onclick="changeQty(-1)" class="px-3 py-1.5 bg-gray-100 text-xs font-black hover:bg-gray-200 transition">-</button>
                                <input type="text" id="modalQty" value="1" readonly class="w-10 text-center text-xs font-bold bg-transparent text-slate-800">
                                <button onclick="changeQty(1)" class="px-3 py-1.5 bg-gray-100 text-xs font-black hover:bg-gray-200 transition">+</button>
                            </div>
                        </div>
                        <div class="flex gap-2 mt-2">
                            <button onclick="submitToCart()" class="flex-1 bg-amber-500 text-slate-900 py-3 rounded-xl text-xs font-black hover:bg-amber-600 shadow-md transition flex items-center justify-center gap-1.5">
                                🛒 ခြင်းတောင်းထဲထည့်မည်
                            </button>
                            <a href="index.php" class="flex-1 bg-slate-900 text-white py-3 rounded-xl text-xs font-bold hover:bg-slate-800 transition text-center flex items-center justify-center gap-1.5 shadow-md">
                                🛍️ ဈေးထပ်ဝယ်ရန်
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let cartCount = 0; let cartTotal = 0; let currentSelectedBook = null; let selectedRating = 0;

        function openBookModal(book) {
            currentSelectedBook = book;
            document.getElementById('modalTitle').innerText = book.title;
            document.getElementById('modalAuthor').innerText = "စာရေးဆရာ - " + (book.author || 'အမည်မသိ');
            document.getElementById('modalCategory').innerText = book.category_name || 'အထွေထွေ';
            document.getElementById('modalStock').innerText = book.stock || '0';
            document.getElementById('modalPrice').innerText = parseInt(book.price).toLocaleString();
            document.getElementById('modalImage').src = 'uploads/' + (book.book_image ? book.book_image : 'default_cover.png');
            document.getElementById('modalQty').value = "1";
            document.getElementById('modalComment').value = "";
            rateStars(0); 
            document.getElementById('bookModal').classList.remove('hidden');
        }

        function closeBookModal() { document.getElementById('bookModal').classList.add('hidden'); }

        function changeQty(amount) {
            let qtyInput = document.getElementById('modalQty');
            let targetQty = parseInt(qtyInput.value) + amount;
            if(targetQty >= 1 && currentSelectedBook && targetQty <= parseInt(currentSelectedBook.stock)) { qtyInput.value = targetQty; }
        }

        function rateStars(stars) {
            selectedRating = stars;
            const starIcons = document.querySelectorAll('#starContainer i');
            starIcons.forEach((star, index) => {
                if (index < stars) {
                    star.classList.add('active', 'text-amber-500');
                    star.classList.remove('text-gray-300');
                } else {
                    star.classList.remove('active', 'text-amber-500');
                    star.classList.add('text-gray-300');
                }
            });
            document.getElementById('ratingStatusText').innerText = `${stars}/5`;
        }

        function submitToCart() {
            if(!currentSelectedBook) return;
            let qty = parseInt(document.getElementById('modalQty').value);
            let comment = document.getElementById('modalComment').value.trim();
            cartCount += qty; cartTotal += (parseInt(currentSelectedBook.price) * qty);
            document.getElementById('cartCount').innerText = cartCount;
            document.getElementById('totalPrice').innerText = cartTotal.toLocaleString();
            let message = `🎉 ${currentSelectedBook.title} (${qty} အုပ်) ကို Cart ထဲထည့်ပြီးပါပြီ။`;
            if(selectedRating > 0) message += `\n⭐ သင်ပေးခဲ့သော Rating: ${selectedRating}/5`;
            if(comment !== "") message += `\n💬 မှတ်ချက်: "${comment}"`;
            closeBookModal();
            alert(message);
        }
    </script>

    <?php include 'auth/footer.php'; ?>