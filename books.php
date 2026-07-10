<?php
// ၁။ Session နှင့် Database ချိတ်ဆက်ခြင်း
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "config/db.php"; // သင့် config ဖိုင်လမ်းကြောင်းအတိုင်း လိုအပ်သလို စစ်ဆေးပါ

// ၂။ Header Dropdown အတွက် ကဏ္ဍများ ကြိုတင်ဆွဲထုတ်ခြင်း
$categories = [];
$cat_result = mysqli_query($conn, "SELECT * FROM Categories ORDER BY category_name ASC");
if ($cat_result) {
    while ($row = mysqli_fetch_assoc($cat_result)) {
        $categories[] = $row;
    }
}

// ၃။ Logic: URL ကလာမည့် Parameters များကို ဖတ်ခြင်း
$cat_id = isset($_GET['cat_id']) ? intval($_GET['cat_id']) : 0;
$search_query = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : "";

// ၄။ SQL Query တည်ဆောက်ခြင်း (Books ကို နှိပ်ရင် အားလုံးကျစေမည့် အပိုင်း)
if ($cat_id > 0) {
    // Dropdown ထဲက အမျိုးအစားတစ်ခုခုကို နှိပ်ခဲ့လျှင်
    $query = "SELECT * FROM Books WHERE category_id = $cat_id ORDER BY id DESC";
    
    // လက်ရှိရွေးထားတဲ့ အမျိုးအစားအမည်ကို ခေါင်းစဉ်ပြရန် ဆွဲထုတ်ခြင်း
    $title_res = mysqli_query($conn, "SELECT category_name FROM Categories WHERE id = $cat_id");
    $title_row = mysqli_fetch_assoc($title_res);
    $page_title = "🗂️ " . ($title_row['category_name'] ?? 'အမျိုးအစား') . " ကဏ္ဍမှ စာအုပ်များ";
} elseif (!empty($search_query)) {
    // ရှာဖွေမှုပြုလုပ်ထားလျှင်
    $query = "SELECT * FROM Books WHERE title LIKE '%$search_query%' OR author LIKE '%$search_query%' ORDER BY id DESC";
    $page_title = "🔍 ရှာဖွေတွေ့ရှိသော စာအုပ်များ";
} else {
    // 📚 Books သို့မဟုတ် Home ကို နှိပ်ခဲ့လျှင် (ရှိသမျှစာအုပ်အားလုံးကျမည်)
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

    <!-- 📚 MAIN CONTENT (စာအုပ်များ ပြသခန်း) -->
    <main class="max-w-7xl mx-auto px-6 py-10">
        
        <!-- ပြောင်းလဲမည့် ခေါင်းစဉ် -->
        <h2 class="text-xl font-black text-slate-900 mb-8 border-l-4 border-amber-500 pl-3">
            <?= $page_title; ?>
        </h2>

        <!-- Book Shelf Grid -->
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-6">
            <?php 
            if (mysqli_num_rows($books_result) > 0) {
                while ($book = mysqli_fetch_assoc($books_result)) { 
            ?>
                    <!-- Book Card -->
                    <a href="user/bookdetail.php?id=<?= $book['id']; ?>" class="bg-white p-4 rounded-2xl shadow-sm border border-gray-100 flex flex-col justify-between hover:shadow-md transition duration-300 group">
                        <div>
                            <img src="uploads/<?= htmlspecialchars($book['book_image'] ?? 'default.jpg'); ?>" class="w-full h-52 object-cover rounded-xl mb-4 bg-gray-50 shadow-sm group-hover:scale-105 transition duration-300">
                            <h3 class="font-bold text-sm text-slate-800 line-clamp-1 leading-tight"><?= htmlspecialchars($book['title']); ?></h3>
                            <p class="text-[11px] text-gray-400 mt-1 mb-2"><i class="fa-regular fa-user mr-1"></i> <?= htmlspecialchars($book['author']); ?></p>
                        </div>
                        <div>
                            <p class="text-red-600 font-black text-sm mb-3"><?= number_format($book['price']); ?> ကျပ်</p>
                        </div>
                    </a>
            <?php 
                } 
            } else { ?>
                <!-- စာအုပ်မရှိလျှင် ပြသမည့်စာသား -->
                <div class="col-span-full text-center py-20 text-gray-400 italic text-sm">
                    ⚠️ ရှာဖွေမှုနှင့် ကိုက်ညီသော စာအုပ်များ မရှိသေးပါဗျာ။
                </div>
            <?php } ?>
        </div>
    </main>

    <?php include 'auth/footer.php'; ?>
</body>
</html>