<?php
session_start();
require_once "../config/db.php";

$book_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// စာအုပ်ဒေတာကို ဆွဲယူခြင်း
$stmt = $conn->prepare("SELECT Books.*, Categories.category_name FROM Books LEFT JOIN Categories ON Books.category_id = Categories.id WHERE Books.id = ?");
$stmt->bind_param("i", $book_id);
$stmt->execute();
$book = $stmt->get_result()->fetch_assoc();

if (!$book) {
    header("Location: index.php");
    exit();
}

// ခြင်းတောင်းထဲသို့ ထည့်သွင်းခြင်း Logic
if (isset($_POST['add_to_cart'])) {
    $qty = intval($_POST['quantity']);
    if($qty < 1) $qty = 1;
    
    if(!isset($_SESSION['cart'])) { $_SESSION['cart'] = []; }
    $_SESSION['cart'][$book_id] = [
        'title' => $book['title'],
        'price' => $book['price'],
        'image' => $book['book_image'],
        'qty' => (isset($_SESSION['cart'][$book_id]) ? $_SESSION['cart'][$book_id]['qty'] + $qty : $qty)
    ];
    echo "<script>alert('🎉 စာအုပ်ကို ခြင်းတောင်းထဲသို့ အောင်မြင်စွာ ထည့်သွင်းပြီးပါပြီ။'); window.location.href='index.php';</script>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($book['title']); ?> - Details</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .star-rating i { cursor: pointer; transition: transform 0.1s; }
        .star-rating i:hover { transform: scale(1.2); }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 flex flex-col min-h-screen">

    <?php include '../auth/headeru.php'; ?>

    <!-- Main Section -->
    <main class="container mx-auto px-4 max-w-4xl my-10 flex-1">
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden border border-gray-100 flex flex-col md:flex-row">
            
            <!-- Left Cover Panel -->
            <div class="bg-amber-50/40 p-8 flex items-center justify-center md:w-2/5 border-b md:border-b-0 md:border-r border-gray-100">
                <img class="max-h-80 object-contain shadow-2xl border rounded-md" src="uploads/<?= !empty($book['book_image']) ? htmlspecialchars($book['book_image']) : 'default_cover.png'; ?>" alt="Cover">
            </div>

            <!-- Right Details Panel -->
            <div class="p-8 md:w-3/5 flex flex-col justify-between">
                <div>
                    <span class="bg-amber-100 text-amber-900 font-bold px-2.5 py-1 rounded text-[10px] uppercase tracking-wide"><?= htmlspecialchars($book['category_name'] ?: 'အထွေထွေ'); ?></span>
                    <h1 class="text-2xl font-black text-slate-950 mt-2 mb-1"><?= htmlspecialchars($book['title']); ?></h1>
                    <p class="text-xs text-red-500 font-extrabold mb-4">စာရေးဆရာ - <?= htmlspecialchars($book['author'] ?: 'အမည်မသိ'); ?></p>
                    
                    <!-- ⭐ စိတ်ကြိုက်နှိပ်နိုင်သော ကြယ်အရှင်စနစ် (Live Customer Rating System) -->
                    <div class="flex items-center gap-2 mb-6 bg-slate-50 p-2.5 rounded-lg w-fit border border-gray-100">
                        <span class="text-slate-600 text-[11px] font-bold">စာအုပ်အဆင့်သတ်မှတ်ရန်:</span>
                        <div class="star-rating text-gray-300 text-base flex gap-1.5" id="starContainer">
                            <i class="fas fa-star" onclick="rateStars(1)"></i>
                            <i class="fas fa-star" onclick="rateStars(2)"></i>
                            <i class="fas fa-star" onclick="rateStars(3)"></i>
                            <i class="fas fa-star" onclick="rateStars(4)"></i>
                            <i class="fas fa-star" onclick="rateStars(5)"></i>
                        </div>
                        <span id="ratingStatusText" class="text-amber-600 font-black text-[11px] ml-1">0/5</span>
                    </div>

                    <div class="text-xs text-gray-600 space-y-1.5 border-l-2 border-amber-500 pl-3 mb-6">
                        <div>လက်ကျန်ပမာဏ: <span class="font-bold text-slate-900"><?= $book['stock']; ?> အုပ်</span></div>
                        <div>ရောင်းဈေး: <span class="font-extrabold text-slate-900 text-sm"><?= number_format($book['price']); ?> ကျပ်</span></div>
                    </div>
                </div>

                <!-- Submission Form -->
                <form method="POST" action="" class="border-t pt-5">
                    <div class="flex items-center gap-4 mb-4">
                        <span class="text-xs text-gray-500 font-medium">ဝယ်ယူမည့် အရေအတွက်</span>
                        <div class="flex items-center border rounded-xl bg-gray-50 overflow-hidden shadow-sm">
                            <button type="button" onclick="changeQty(-1)" class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 font-bold text-xs">-</button>
                            <input type="text" name="quantity" id="bookQty" value="1" readonly class="w-10 text-center font-bold text-xs bg-transparent text-slate-900">
                            <button type="button" onclick="changeQty(1)" class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 font-bold text-xs">+</button>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-3">
                        <button type="submit" name="add_to_cart" class="flex-1 bg-amber-500 text-slate-900 py-3 rounded-xl text-xs font-black hover:bg-amber-600 shadow transition text-center">
                            🛒 ခြင်းတောင်းထဲသို့ ထည့်မည်
                        </button>
                        <!-- 🎯 "စျေးထပ်ဝယ်ရန်" နှိပ်လျှင် Home Page သို့ တိုက်ရိုက်ပြန်လည်ရောက်ရှိစေမည့် ခလုတ် -->
                        <a href="index.php" class="flex-1 bg-slate-900 text-white py-3 rounded-xl text-xs font-bold hover:bg-slate-800 transition text-center flex items-center justify-center gap-1">
                            🛍️ ဈေးထပ်ဝယ်ရန်
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- 💬 Comment / Review Area -->
        <div class="bg-white rounded-2xl shadow-xl p-6 mt-8 border border-gray-100">
            <h3 class="text-sm font-bold text-slate-900 mb-4 flex items-center gap-2"><i class="far fa-comments text-amber-500"></i> စာဖတ်သူများ၏ မှတ်ချက်များ</h3>
            <div id="commentBox" class="space-y-3 mb-4 max-h-48 overflow-y-auto pr-2 text-xs">
                <div class="bg-gray-50 p-3 rounded-xl border border-gray-100">
                    <div class="flex justify-between font-bold text-slate-700 mb-1"><span>ကိုမင်းခန့်</span> <span class="text-gray-400 font-normal text-[10px]">ယနေ့</span></div>
                    <p class="text-gray-600">တကယ့်ကို ဖတ်ရတာ တန်ဖိုးရှိတဲ့ စာအုပ်ကောင်းတစ်အုပ်ပါ။ လက်မလွတ်တမ်း ဝယ်ဖတ်သင့်ပါတယ်။</p>
                </div>
            </div>
            <div class="flex gap-2">
                <input type="text" id="commentText" placeholder="ဒီစာအုပ်အပေါ် သင့်အမြင် မှတ်ချက် ရေးသားပါ..." class="flex-1 border text-xs px-4 py-2.5 rounded-xl bg-gray-50 focus:outline-none focus:border-amber-500">
                <button type="button" onclick="addComment()" class="bg-slate-900 text-white px-5 text-xs font-bold rounded-xl hover:bg-slate-800 transition">ပို့ရန်</button>
            </div>
        </div>
    </main>

    <!-- 🧾 Footer (Detail Page) -->
    <footer class="bg-slate-900 text-gray-500 text-xs text-center py-5 mt-20">
        <p>© 2026 Online Book Shop. All Rights Reserved.</p>
    </footer>

    <!-- Interactive Scripts -->
    <script>
        const maxStock = <?= intval($book['stock']); ?>;
        
        function changeQty(amt) {
            let qtyInput = document.getElementById('bookQty');
            let nextVal = parseInt(qtyInput.value) + amt;
            if (nextVal >= 1 && nextVal <= maxStock) {
                qtyInput.value = nextVal;
            } else if (nextVal > maxStock) {
                alert("⚠️ ဆိုင်တွင် လက်ကျန်ရှိသော အရေအတွက်ထက် ပို၍မှာယူလို့မရနိုင်ပါဗျာ။");
            }
        }

        // 🌟 Live Rating Script (Customer စိတ်ကြိုက် ကြယ်ပွင့်နှိပ်ပြီး အဝါရောင်ပြောင်းလဲမှုထိန်းချုပ်ရန်)
        function rateStars(rating) {
            let stars = document.getElementById('starContainer').getElementsByTagName('i');
            document.getElementById('ratingStatusText').innerText = rating + "/5 ပေးပြီး";
            for (let i = 0; i < stars.length; i++) {
                if (i < rating) {
                    stars[i].classList.remove('text-gray-300');
                    stars[i].classList.add('text-amber-500');
                } else {
                    stars[i].classList.remove('text-amber-500');
                    stars[i].classList.add('text-gray-300');
                }
            }
        }

        function addComment() {
            let input = document.getElementById('commentText');
            let txt = input.value.trim();
            if(!txt) return;

            let box = document.getElementById('commentBox');
            let newDiv = document.createElement('div');
            newDiv.className = "bg-amber-50/50 p-3 rounded-xl border border-amber-100";
            newDiv.innerHTML = `<div class="flex justify-between font-bold text-slate-700 mb-1"><span>ဧည့်သည်တော် (ဖတ်ရှုသူ)</span> <span class="text-gray-400 font-normal text-[10px]">ခုနကတင်</span></div><p class="text-gray-600">${txt}</p>`;
            box.appendChild(newDiv);
            box.scrollTop = box.scrollHeight;
            input.value = "";
        }
    <?php include '../auth/footer.php'; ?>