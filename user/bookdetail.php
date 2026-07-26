<?php
session_start();
require_once "../config/db.php";

$is_logged_in = isset($_SESSION['user_id']);
$is_customer = $is_logged_in && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'customer';

// Validate Book ID
if (!isset($_GET['id'])) {
    header("Location: ../books.php");
    exit();
}

$id = intval($_GET['id']);
$rating_msg = '';
$rating_err = '';

// Handle Rating Submission (Create Action for both Customers and Guests)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_rating'])) {
    $rating_val = intval($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');

    if ($rating_val < 1 || $rating_val > 5) {
        $rating_err = "Please select a star rating.";
    } else {
        // Set user_id to NULL if it is a guest user
        $user_id = $is_logged_in ? $_SESSION['user_id'] : null;

        if ($user_id !== null) {
            // Check for duplicate review only for registered users
            $dup = $conn->prepare("SELECT id FROM Ratings WHERE user_id = ? AND book_id = ?");
            $dup->bind_param("ii", $user_id, $id);
            $dup->execute();
            $dup_result = $dup->get_result();
            $dup->close();

            if ($dup_result->num_rows > 0) {
                $rating_err = "You have already reviewed this book.";
            }
        }

        if (empty($rating_err)) {
            // Insert rating into database
            $stmt = $conn->prepare("INSERT INTO Ratings (user_id, book_id, rating, comment, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("iiis", $user_id, $id, $rating_val, $comment);
            if ($stmt->execute()) {
                $review_success_flag = true; 
            } else {
                $rating_err = "Failed to submit rating. Please try again.";
            }
            $stmt->close();
        }
    }
}

// Handle Rating Updates (Update Action)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_rating'])) {
    if ($is_logged_in) {
        $review_id = intval($_POST['review_id']);
        $rating_val = intval($_POST['rating'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');
        $user_id = $_SESSION['user_id'];

        if ($rating_val < 1 || $rating_val > 5) {
            $rating_err = "Please select a star rating.";
        } else {
            // Secure update constraint checking owner user_id
            $stmt = $conn->prepare("UPDATE Ratings SET rating = ?, comment = ? WHERE id = ? AND user_id = ?");
            $stmt->bind_param("isii", $rating_val, $comment, $review_id, $user_id);
            if ($stmt->execute()) {
                header("Location: bookdetail.php?id=" . $id . "#reviews-area");
                exit();
            } else {
                $rating_err = "Failed to update review.";
            }
            $stmt->close();
        }
    }
}

// Handle Rating Removal (Delete Action)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_rating'])) {
    if ($is_logged_in) {
        $review_id = intval($_POST['review_id']);
        $user_id = $_SESSION['user_id'];

        // Secure delete constraint checking owner user_id
        $stmt = $conn->prepare("DELETE FROM Ratings WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $review_id, $user_id);
        if ($stmt->execute()) {
            header("Location: bookdetail.php?id=" . $id . "#reviews-area");
            exit();
        } else {
            $rating_err = "Failed to delete review.";
        }
        $stmt->close();
    }
}

// Check if current user already reviewed this book
$has_reviewed = false;
$user_review_data = null;
if ($is_logged_in) {
    $chk = $conn->prepare("SELECT * FROM Ratings WHERE user_id = ? AND book_id = ?");
    $chk->bind_param("ii", $_SESSION['user_id'], $id);
    $chk->execute();
    $chk_res = $chk->get_result();
    if ($chk_res->num_rows > 0) {
        $has_reviewed = true;
        $user_review_data = $chk_res->fetch_assoc();
    }
    $chk->close();
}

// Fetch Book Details from Database
$stmt = $conn->prepare("SELECT Books.*, Categories.category_name FROM Books LEFT JOIN Categories ON Books.category_id = Categories.id WHERE Books.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: ../books.php");
    exit();
}

$book = $result->fetch_assoc();
$stmt->close();

// Calculate total quantity of this book already present in user's cart (DB or Session)
$qty_in_cart = 0;
if ($is_logged_in) {
    $c_stmt = $conn->prepare("SELECT SUM(quantity) as cart_qty FROM Cart_item WHERE user_id = ? AND book_id = ?");
    $c_stmt->bind_param("ii", $_SESSION['user_id'], $id);
    $c_stmt->execute();
    $c_res = $c_stmt->get_result()->fetch_assoc();
    $qty_in_cart = $c_res['cart_qty'] ? intval($c_res['cart_qty']) : 0;
    $c_stmt->close();
} else {
    if (isset($_SESSION['guest_cart'])) {
        foreach ($_SESSION['guest_cart'] as $g_item) {
            if (isset($g_item['book_id']) && intval($g_item['book_id']) === $id) {
                $qty_in_cart += intval($g_item['quantity']);
            }
        }
    }
}

// Compute real-time available stock for purchase
$total_physical_stock = intval($book['stock']);
$available_stock = max(0, $total_physical_stock - $qty_in_cart);

// Fetch All Reviews for this specific book
$review_query = "SELECT Ratings.*, Users.name FROM Ratings LEFT JOIN Users ON Ratings.user_id = Users.id WHERE Ratings.book_id = ? ORDER BY Ratings.id DESC";
$review_stmt = $conn->prepare($review_query);
$review_stmt->bind_param("i", $id);
$review_stmt->execute();
$reviews = $review_stmt->get_result();
$review_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($book['title']); ?> - Online Book Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .star-rating-btn i, .edit-star-rating-btn i { cursor: pointer; transition: all 0.2s ease; }
        .star-rating-btn i:hover, .edit-star-rating-btn i:hover { transform: scale(1.25); }
        input[type=number]::-webkit-inner-spin-button, 
        input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
        input[type=number] { -moz-appearance: textfield; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen flex flex-col font-sans text-slate-800 antialiased relative">

    <?php include '../auth/header.php'; ?>

    <!-- Stock Alert Toast Notification -->
    <div id="stockToast" class="fixed top-5 right-5 z-50 transform translate-x-full opacity-0 transition-all duration-300 pointer-events-none max-w-sm w-[90%] sm:w-full mx-auto sm:mx-0">
        <div class="bg-white border-l-4 border-amber-500 rounded-xl shadow-xl p-4 flex items-start gap-3 border border-slate-100">
            <div class="bg-amber-50 p-2 rounded-lg text-amber-600 flex-shrink-0">
                <i class="fa-solid fa-layer-group text-lg"></i>
            </div>
            <div class="flex-1">
                <h4 class="font-bold text-slate-900 text-sm mb-0.5">Notification</h4>
                <p id="stockToastMsg" class="text-xs text-slate-600 leading-relaxed"></p>
            </div>
            <button onclick="dismissToast()" class="text-slate-400 hover:text-slate-600 transition p-1 flex-shrink-0">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>
    </div>

    <div class="max-w-5xl mx-auto py-6 sm:py-8 px-4 flex-1 w-full">

        <a href="../user/userdashboard.php" class="inline-flex items-center gap-2 text-slate-500 hover:text-blue-600 font-medium text-sm transition mb-6 group">
            <i class="fa-solid fa-arrow-left transition-transform group-hover:-translate-x-1"></i> Back to Catalog
        </a>

        <?php if ($rating_err): ?>
            <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm font-medium flex items-center gap-3 shadow-sm">
                <i class="fa-solid fa-circle-exclamation text-lg text-red-500"></i>
                <span><?= htmlspecialchars($rating_err); ?></span>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4 sm:p-6 md:p-8 grid grid-cols-1 md:grid-cols-12 gap-6 md:gap-12">
            
            <div class="col-span-1 md:col-span-5 flex flex-col justify-start max-w-sm mx-auto md:max-w-none w-full">
                <div class="overflow-hidden rounded-xl bg-slate-100 shadow-md border border-slate-100 group aspect-[3/4]">
                    <img src="../uploads/<?= htmlspecialchars($book['book_image']); ?>"
                         class="w-full h-full object-cover transition duration-500 group-hover:scale-105" alt="Book Cover">
                </div>
            </div>

            <div class="col-span-1 md:col-span-7 flex flex-col justify-between">
                <div>
                    <span class="inline-block px-3 py-1 bg-blue-50 text-blue-600 font-semibold text-xs rounded-full uppercase tracking-wider mb-3">
                        <?= htmlspecialchars($book['category_name']); ?>
                    </span>
                    
                    <h1 class="text-xl sm:text-2xl md:text-3xl font-bold text-slate-900 tracking-tight leading-tight mb-2 break-words">
                        <?= htmlspecialchars($book['title']); ?>
                    </h1>

                    <p class="text-slate-500 text-sm mb-4 md:mb-6">
                         <span class="font-semibold text-slate-800"><?= htmlspecialchars($book['author']); ?></span>
                    </p>

                    <div class="flex items-baseline gap-2 mb-4">
                        <span class="text-2xl sm:text-3xl font-black text-blue-600"><?= number_format($book['price']); ?></span>
                        <span class="text-sm font-bold text-slate-400">ကျပ်</span>
                    </div>

                    <div class="mb-6">
                        <?php 
                        // Check if real-time available stock is greater than 0
                        if ($available_stock > 0): 
                            $stock_color_class = ($available_stock < 4) ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700';
                            $dot_color_class = ($available_stock < 4) ? 'bg-rose-500' : 'bg-emerald-500';
                        ?>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 <?= $stock_color_class; ?> text-xs font-bold rounded-full">
                                <span class="w-1.5 h-1.5 rounded-full <?= $dot_color_class; ?> <?= ($available_stock < 4) ? '' : 'animate-pulse'; ?>"></span>
                                <span id="currentStockDisplay"><?= $available_stock; ?></span> အုပ်
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-rose-50 text-rose-700 text-xs font-bold rounded-full">
                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                Out of Stock
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="border-t border-slate-100 pt-5">
                        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Description</h3>
                        <p class="text-slate-600 leading-relaxed text-sm whitespace-pre-line break-words">
                            <?= htmlspecialchars($book['description']); ?>
                        </p>
                    </div>
                </div>

                <div class="mt-8 pt-6 border-t border-slate-100">
                    <?php if ($available_stock > 0): ?>
                    <form action="addtocart.php" method="POST" id="addToCartForm" class="flex flex-col sm:flex-row items-stretch sm:items-end gap-4" novalidate>
                        <input type="hidden" name="book_id" value="<?= $book['id']; ?>">
                        
                        <div class="w-full sm:w-32 relative">
                            <label class="block text-xs font-bold text-slate-400 tracking-wider mb-2">QTY</label>
                            
                            <div id="inlineStockAlert" class="absolute bottom-[56px] left-0 hidden w-64 z-10 animate-fade-in">
                                <div class="bg-[#E11D48] text-white text-xs font-medium py-2 px-3 rounded-xl shadow-md leading-relaxed relative after:content-[''] after:absolute after:top-full after:left-6 after:border-4 after:border-transparent after:border-t-[#E11D48]">
                                    စာအုပ်လက်ကျန်မှာ <span id="alertStockCount">0</span> ပဲရှိပါတော့သဖြင့် ထပ်မံဝယ်ယူ၍ မရနိုင်ပါ။
                                </div>
                            </div>

                            <div class="flex items-center border border-slate-200 bg-slate-50 rounded-xl overflow-hidden h-[46px]">
                                <button type="button" onclick="changeQty(-1)" class="w-12 sm:w-10 h-full flex items-center justify-center text-slate-500 hover:bg-slate-200 hover:text-slate-800 transition font-bold select-none cursor-pointer">
                                    <i class="fa-solid fa-minus text-xs"></i>
                                </button>
                                <input type="number" name="quantity" id="quantityInput" value="1" min="1" max="<?= $available_stock; ?>"
                                       class="flex-1 bg-transparent text-center text-sm font-bold text-slate-800 focus:outline-none h-full w-12">
                                <button type="button" onclick="changeQty(1)" class="w-12 sm:w-10 h-full flex items-center justify-center text-slate-500 hover:bg-slate-200 hover:text-slate-800 transition font-bold select-none cursor-pointer">
                                    <i class="fa-solid fa-plus text-xs"></i>
                                </button>
                            </div>
                        </div>
                        
                        <button type="submit"
                                class="flex-1 bg-blue-500 hover:bg-blue-600 text-white px-6 py-3.5 rounded-xl font-bold text-sm shadow-sm transition duration-150 flex items-center justify-center gap-2 h-[46px]">
                            <i class="fa-solid fa-bag-shopping text-sm"></i> Add to Cart
                        </button>
                    </form>
                    <?php else: ?>
                        <button type="button" disabled
                                class="w-full bg-red-600 text-white px-6 py-3.5 rounded-xl font-bold text-sm transition flex items-center justify-center gap-2 h-[46px] cursor-not-allowed">
                            <i class="fa-solid fa-ban text-sm"></i> Out of Stock 
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- REVIEWS & FEEDBACK SECTION -->
        <div class="mt-12" id="reviews-area">
            <h2 class="text-lg font-bold text-slate-900 mb-6 flex items-center gap-2 tracking-tight">
                <i class="fa-solid fa-star text-amber-500"></i> Reviews & Feedback
            </h2>

            <div class="bg-white rounded-2xl border border-slate-100 p-4 sm:p-6 shadow-sm mb-8">
                
                <div class="flex items-center justify-between mb-4 pb-4 border-b border-slate-50">
                    <h3 class="font-bold text-sm text-slate-800 flex items-center gap-2">
                        <?php if (isset($review_success_flag)): ?>
                            <i class="fa-solid fa-circle-check text-emerald-500 text-lg"></i> 
                            <span class="text-emerald-700">Review submitted successfully!</span>
                        <?php elseif ($has_reviewed && isset($_GET['action']) && $_GET['action'] == 'edit'): ?>
                            <i class="fa-solid fa-user-pen text-blue-500"></i>
                            <span class="text-blue-700">Modifying your review parameters below</span>
                        <?php else: ?>
                            <i class="fa-solid fa-pen-to-square text-slate-400"></i> 
                            <span>Share your thoughts <?php if(!$is_logged_in) echo "(Posting as Guest)"; ?></span>
                        <?php endif; ?>
                    </h3>
                </div>

                <?php if ($is_logged_in && $has_reviewed && isset($_GET['action']) && $_GET['action'] == 'edit'): ?>
                <form action="bookdetail.php?id=<?= $id; ?>#reviews-area" method="POST" id="editRatingForm" class="space-y-4 bg-slate-50/50 p-4 rounded-xl border border-slate-100">
                    <input type="hidden" name="update_rating" value="1">
                    <input type="hidden" name="review_id" value="<?= $user_review_data['id']; ?>">
                    
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Your Rating Star</label>
                        <div class="edit-star-rating-btn flex gap-2.5 text-slate-200 text-2xl py-1" id="editRatingStars">
                            <?php for ($i = 1; $i <= 5; $i++): 
                                $active_star_class = ($i <= $user_review_data['rating']) ? 'text-amber-400' : 'text-slate-200';
                            ?>
                                <i class="fa-solid fa-star <?= $active_star_class; ?>" data-star="<?= $i; ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <input type="hidden" name="rating" id="editRatingValue" value="<?= $user_review_data['rating']; ?>">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Your Comment</label>
                        <textarea name="comment" rows="3" placeholder="Update your review here..."
                                  class="w-full border border-slate-200 bg-white rounded-xl p-3.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none transition-all" id="editCommentBox"><?= htmlspecialchars($user_review_data['comment']); ?></textarea>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 pt-1">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-xl text-xs font-bold transition flex items-center gap-2 shadow-sm w-full sm:w-auto justify-center">
                            <i class="fa-solid fa-floppy-disk text-[10px]"></i> Update Review
                        </button>
                        <a href="bookdetail.php?id=<?= $id; ?>#reviews-area" class="bg-slate-200 hover:bg-slate-300 text-slate-700 px-5 py-2.5 rounded-xl text-xs font-bold transition flex items-center justify-center w-full sm:w-auto">
                            Cancel
                        </a>
                    </div>
                </form>
                
                <?php else: ?>
                    <?php if (!$has_reviewed || !$is_logged_in): ?>
                    <form action="bookdetail.php?id=<?= $id; ?>#reviews-area" method="POST" id="ratingForm" class="space-y-4">
                        <input type="hidden" name="submit_rating" value="1">
                        
                        <div>
                            <div class="star-rating-btn flex gap-2.5 text-slate-200 text-3xl py-1" id="ratingStars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fa-solid fa-star" data-star="<?= $i; ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <input type="hidden" name="rating" id="ratingValue" value="0">
                        </div>

                        <div>
                            <textarea name="comment" rows="3" placeholder="Write your review here (optional)..."
                                      class="w-full border border-slate-200 bg-slate-50 rounded-xl p-3.5 text-sm focus:outline-none focus:ring-2 focus:ring-slate-800 focus:bg-white resize-none transition-all" id="commentBox"></textarea>
                        </div>

                        <button type="submit" class="bg-slate-950 hover:bg-slate-800 text-white px-5 py-2.5 rounded-xl text-xs font-bold transition flex items-center gap-2 w-full sm:w-auto justify-center">
                            <i class="fa-solid fa-paper-plane text-[10px]"></i> Submit Review
                        </button>
                    </form>
                    <?php else: ?>
                        <div class="p-4 bg-slate-50 rounded-xl border border-slate-100 text-slate-500 text-sm text-center">
                            You have already submitted a review for this book.
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="space-y-4">
                <?php if ($reviews && $reviews->num_rows > 0): ?>
                    <?php while ($r = $reviews->fetch_assoc()): 
                        $is_my_review = ($is_logged_in && $r['user_id'] == $_SESSION['user_id']);
                        $display_name = !empty($r['name']) ? $r['name'] : 'Guest User';
                    ?>
                        <div class="bg-white rounded-xl border <?= $is_my_review ? 'border-blue-200 bg-gradient-to-r from-blue-50/10 to-transparent shadow-md' : 'border-slate-100' ?> p-4 sm:p-5 shadow-sm transition hover:border-slate-200 relative">
                            
                            <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 mb-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 bg-slate-100 border border-slate-200 rounded-full flex items-center justify-center text-xs font-bold text-slate-600 uppercase shadow-sm flex-shrink-0">
                                        <?= htmlspecialchars(substr($display_name, 0, 1)); ?>
                                    </div>
                                    <div class="min-w-0">
                                        <h4 class="font-bold text-sm text-slate-800 leading-tight truncate">
                                            <?= htmlspecialchars($display_name); ?>
                                        </h4>
                                        <p class="text-[11px] text-slate-400 mt-0.5"><?= date('M d, Y', strtotime($r['created_at'])); ?></p>
                                    </div>
                                </div>
                                
                                <div class="flex flex-row sm:flex-col items-center sm:items-end gap-2 justify-between sm:justify-start">
                                    <div class="flex text-amber-400 text-xs gap-0.5 bg-amber-50/60 px-2 py-1 rounded-md">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <?php if ($i <= $r['rating']): ?>
                                                <i class="fa-solid fa-star"></i>
                                            <?php else: ?>
                                                <i class="fa-regular fa-star text-slate-200"></i>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                    </div>
                                    
                                    <?php if ($is_my_review): ?>
                                        <span class="bg-blue-100 text-blue-700 font-bold text-[10px] px-2 py-0.5 rounded-md uppercase tracking-wider">My Review</span>
                                    <?php elseif (empty($r['user_id'])): ?>
                                        <span class="bg-slate-100 text-slate-500 font-bold text-[10px] px-2 py-0.5 rounded-md uppercase tracking-wider">Guest</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if (!empty($r['comment'])): ?>
                                <p class="text-slate-600 text-sm leading-relaxed pl-1 bg-slate-50/40 p-3 rounded-lg border border-slate-100/50 italic mb-3 break-words">
                                    " <?= htmlspecialchars($r['comment']); ?> "
                                </p>
                            <?php endif; ?>

                            <?php if ($is_my_review): ?>
                            <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-slate-100/60 mt-2">
                                <a href="bookdetail.php?id=<?= $id; ?>&action=edit#reviews-area" 
                                   class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 text-xs font-medium text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-lg transition shadow-sm border border-blue-100 w-full sm:w-auto">
                                    <i class="fa-solid fa-pen-to-square text-[11px]"></i> Update Review
                                </a>
                                
                                <form action="bookdetail.php?id=<?= $id; ?>#reviews-area" method="POST" onsubmit="return confirm('Are you sure you want to delete this review?');" class="inline w-full sm:w-auto">
                                    <input type="hidden" name="delete_rating" value="1">
                                    <input type="hidden" name="review_id" value="<?= $r['id']; ?>">
                                    <button type="submit" 
                                            class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 text-xs font-medium text-red-600 bg-red-50 hover:bg-red-100 border border-red-100 rounded-lg transition w-full sm:w-auto">
                                        <i class="fa-solid fa-trash-can text-[11px]"></i> Delete Review
                                    </button>
                                </form>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center py-12 bg-white rounded-xl border border-slate-100">
                        <i class="fa-solid fa-comment-slash text-2xl text-slate-300 mb-2 block"></i>
                        <p class="text-slate-400 text-sm">No reviews yet. Be the first to review!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include '../auth/footer.php'; ?>

    <script>
    let stockAlertTimeout;

    // Dynamically adjust purchase quantity in detail view
    function changeQty(amount) {
        const qtyInput = document.getElementById('quantityInput');
        const alertBox = document.getElementById('inlineStockAlert');
        const alertCount = document.getElementById('alertStockCount');
        
        if (qtyInput) {
            let currentVal = parseInt(qtyInput.value) || 1;
            let newVal = currentVal + amount;
            let min = parseInt(qtyInput.getAttribute('min')) || 1;
            let max = parseInt(qtyInput.getAttribute('max')) || <?= $available_stock; ?>;
            
            if (newVal >= min && newVal <= max) {
                qtyInput.value = newVal;
                if(alertBox) alertBox.classList.add('hidden');
            } else if (amount > 0 && currentVal >= max) {
                if (alertBox && alertCount) {
                    alertCount.innerText = max;
                    alertBox.classList.remove('hidden');
                    clearTimeout(stockAlertTimeout);
                    stockAlertTimeout = setTimeout(() => {
                        alertBox.classList.add('hidden');
                    }, 4000);
                }
            }
        }
    }

    // Input validation for quantity counter
    document.getElementById('quantityInput')?.addEventListener('input', function() {
        const max = parseInt(this.getAttribute('max')) || <?= $available_stock; ?>;
        const alertBox = document.getElementById('inlineStockAlert');
        const alertCount = document.getElementById('alertStockCount');
        let currentVal = parseInt(this.value) || 1;

        if (currentVal > max) {
            this.value = max;
            if (alertBox && alertCount) {
                alertCount.innerText = max;
                alertBox.classList.remove('hidden');
                clearTimeout(stockAlertTimeout);
                stockAlertTimeout = setTimeout(() => {
                    alertBox.classList.add('hidden');
                }, 4000);
            }
        } else if (currentVal < 1) {
            this.value = 1;
        }
    });

    // Display notification toast message
    function showToast(message) {
        const toast = document.getElementById('stockToast');
        document.getElementById('stockToastMsg').innerText = message;
        toast.classList.remove('translate-x-full', 'opacity-0', 'pointer-events-none');
        toast.classList.add('translate-x-0', 'opacity-100');
        setTimeout(dismissToast, 4000);
    }

    // Dismiss notification toast message
    function dismissToast() {
        const toast = document.getElementById('stockToast');
        if(toast) {
            toast.classList.add('translate-x-full', 'opacity-0', 'pointer-events-none');
            toast.classList.remove('translate-x-0', 'opacity-100');
        }
    }

    document.addEventListener("DOMContentLoaded", function() {
        const addToCartForm = document.getElementById('addToCartForm');
        if (addToCartForm) {
            addToCartForm.addEventListener('submit', function(e) {
                const qtyInput = document.getElementById('quantityInput');
                const requestedQty = parseInt(qtyInput.value) || 0;
                const maxAvailableStock = <?= $available_stock; ?>;

                if (requestedQty > maxAvailableStock) {
                    e.preventDefault(); 
                    showToast("Only " + maxAvailableStock + " copies available.");
                } else if (requestedQty < 1) {
                    e.preventDefault();
                    showToast("Please select at least 1 copy.");
                }
            });
        }
        
        // Setup rating star interaction logic
        const stars = document.querySelectorAll('#ratingStars i');
        const ratingInput = document.getElementById('ratingValue');
        let currentSelectedRating = 0;

        if (stars.length > 0) {
            setupStarInteraction(stars, ratingInput, (val) => { currentSelectedRating = val; });
        }

        const editStars = document.querySelectorAll('#editRatingStars i');
        const editRatingInput = document.getElementById('editRatingValue');
        
        if (editStars.length > 0) {
            setupStarInteraction(editStars, editRatingInput, (val) => { });
        }

        function setupStarInteraction(starElements, hiddenInput, setCallback) {
            starElements.forEach(star => {
                star.addEventListener('mouseover', function() {
                    const hoverValue = parseInt(this.getAttribute('data-star'));
                    renderHighlightState(starElements, hoverValue);
                });

                star.addEventListener('mouseout', function() {
                    renderHighlightState(starElements, parseInt(hiddenInput.value));
                });

                star.addEventListener('click', function() {
                    const selectedValue = parseInt(this.getAttribute('data-star'));
                    hiddenInput.value = selectedValue;
                    setCallback(selectedValue);
                    renderHighlightState(starElements, selectedValue);
                });
            });
        }

        function renderHighlightState(elements, targetValue) {
            elements.forEach(star => {
                const starVal = parseInt(star.getAttribute('data-star'));
                if (starVal <= targetValue) {
                    star.classList.add('text-amber-400', 'scale-110');
                    star.classList.remove('text-slate-200');
                } else {
                    star.classList.remove('text-amber-400', 'scale-110');
                    star.classList.add('text-slate-200');
                }
            });
        }
    });
    </script>
</body>
</html>