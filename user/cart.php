<?php
/**
 * Online Book Shop — Shopping Cart Page
 * Displays cart items for guests (session) and logged-in users (DB).
 * Supports quantity update (+/-) and item removal for both.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/db.php';

$is_logged_in = isset($_SESSION['user_id']) && $_SESSION['user_role'] === 'customer';

// Handle quantity update and item removal actions via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // 1. UPDATE QUANTITY LOGIC
    if ($action === 'update_qty' && isset($_POST['index'], $_POST['new_qty'])) {
        $idx = intval($_POST['index']);
        $qty = max(1, intval($_POST['new_qty']));

        if ($is_logged_in && isset($_POST['cart_item_id'])) {
            // Update DB cart item for logged-in user
            $cid = intval($_POST['cart_item_id']);
            $uid = $_SESSION['user_id'];
            
            $stmt = $conn->prepare("SELECT unit_price FROM Cart_item WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $cid, $uid);
            $stmt->execute();
            $r = $stmt->get_result()->fetch_assoc();
            if ($r) {
                $new_total = $r['unit_price'] * $qty;
                $upd = $conn->prepare("UPDATE Cart_item SET quantity = ?, totalprice = ? WHERE id = ? AND user_id = ?");
                $upd->bind_param("idii", $qty, $new_total, $cid, $uid);
                $upd->execute();
                $upd->close();
            }
            $stmt->close();
        } elseif (!$is_logged_in && isset($_SESSION['guest_cart'][$idx])) {
            // Update session cart item for guest user
            $_SESSION['guest_cart'][$idx]['quantity'] = $qty;
            $_SESSION['guest_cart'][$idx]['totalprice'] = $_SESSION['guest_cart'][$idx]['unit_price'] * $qty;
        }
    }

    // 2. REMOVE ITEM LOGIC (integrated into self file)
    if ($action === 'remove_item' && isset($_POST['index'])) {
        $idx = intval($_POST['index']);

        if ($is_logged_in && isset($_POST['cart_item_id'])) {
            // Delete item from Database for logged-in user
            $cid = intval($_POST['cart_item_id']);
            $uid = $_SESSION['user_id'];
            $del = $conn->prepare("DELETE FROM Cart_item WHERE id = ? AND user_id = ?");
            $del->bind_param("ii", $cid, $uid);
            $del->execute();
            $del->close();
        } elseif (!$is_logged_in && isset($_SESSION['guest_cart'][$idx])) {
            // Delete item from Session array for guest user and re-index array
            unset($_SESSION['guest_cart'][$idx]);
            $_SESSION['guest_cart'] = array_values($_SESSION['guest_cart']);
        }
    }

    // Refresh page to apply modifications safely
    header("Location: cart.php");
    exit;
}

// Load cart items along with real-time stock from Books table
if ($is_logged_in) {
    // Fetch from database for Logged-in Customer (JOIN Books to get stock)
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT Cart_item.*, Books.title, Books.book_image, Books.stock FROM Cart_item INNER JOIN Books ON Cart_item.book_id = Books.id WHERE Cart_item.user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $cart_items = [];
    while ($row = $result->fetch_assoc()) {
        $cart_items[] = $row;
    }
    $stmt->close();
} else {
    // Fetch from session for Guest user (And dynamically check current stock)
    $cart_items = isset($_SESSION['guest_cart']) ? $_SESSION['guest_cart'] : [];
    foreach ($cart_items as $index => $item) {
        $b_id = intval($item['book_id']);
        $st_stmt = $conn->prepare("SELECT stock FROM Books WHERE id = ?");
        $st_stmt->bind_param("i", $b_id);
        $st_stmt->execute();
        $st_res = $st_stmt->get_result()->fetch_assoc();
        $cart_items[$index]['stock'] = $st_res ? intval($st_res['stock']) : 0;
        $st_stmt->close();
    }
}

$grandTotal = 0;
$currentPage = 'cart';
$categories = [];
$cat_result = $conn->query("SELECT * FROM Categories ORDER BY category_name ASC");
if ($cat_result) {
    while ($row = $cat_result->fetch_assoc()) {
        $categories[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Cart - Online Book Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen flex flex-col font-sans text-slate-800 relative">

    <?php include __DIR__ . '/../auth/header.php'; ?>

    <!-- Custom Responsive Stock Notification Alert Component Box -->
    <div id="cartStockToast" class="fixed top-5 right-5 z-50 transform translate-x-full opacity-0 transition-all duration-300 pointer-events-none max-w-sm w-[90%] sm:w-full mx-auto sm:mx-0">
        <div class="bg-white border-l-4 border-rose-500 rounded-xl shadow-xl p-4 flex items-start gap-3 border border-slate-100">
            <div class="bg-rose-50 p-2 rounded-lg text-rose-600 flex-shrink-0">
                <i class="fa-solid fa-triangle-exclamation text-lg"></i>
            </div>
            <div class="flex-1">
                <h4 class="font-bold text-slate-900 text-sm mb-0.5">မရနိုင်ပါသဖြင့် တောင်းပန်အပ်ပါသည်။</h4>
                <p id="toastMessage" class="text-xs text-slate-600 leading-relaxed"></p>
            </div>
            <button onclick="hideCartToast()" class="text-slate-400 hover:text-slate-600 transition p-1 flex-shrink-0">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>
    </div>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-10 flex-1 w-full">

        <h1 class="text-2xl md:text-3xl font-black text-slate-900 mb-6 flex items-center gap-3">
            <i class="fa-solid fa-cart-shopping text-amber-500"></i> My Shopping Cart
        </h1>

        <?php if (!empty($cart_items)): ?>
            <!-- Cart Table Container -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-gray-500 uppercase text-[11px] tracking-wider">
                            <tr>
                                <th class="p-4 text-left font-semibold">Image</th>
                                <th class="p-4 text-left font-semibold">Book</th>
                                <th class="p-4 text-center font-semibold">Price</th>
                                <th class="p-4 text-center font-semibold">Qty</th>
                                <th class="p-4 text-center font-semibold">Total</th>
                                <th class="p-4 text-center font-semibold">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cart_items as $index => $item):
                                $grandTotal += $item['totalprice'];
                                $item_stock = isset($item['stock']) ? intval($item['stock']) : 999;
                            ?>
                                <tr class="cart-item-row border-b border-gray-100 hover:bg-gray-50/50 transition-colors duration-150" 
                                    data-title="<?= htmlspecialchars($item['title']); ?>" 
                                    data-stock="<?= $item_stock; ?>" 
                                    data-qty="<?= intval($item['quantity']); ?>">
                                    
                                    <td class="p-4">
                                        <img src="../uploads/<?= htmlspecialchars($item['book_image'] ?? 'default.jpg'); ?>"
                                             class="w-16 h-22 object-cover rounded-lg shadow-sm" alt="Cover">
                                    </td>
                                    <td class="p-4 font-bold text-slate-800">
                                        <?= htmlspecialchars($item['title']); ?>
                                    </td>
                                    <td class="p-4 text-center text-gray-600 font-medium">
                                        <?= number_format($item['unit_price']); ?> MMK
                                    </td>
                                    <td class="p-4 text-center">
                                        <div class="inline-flex items-center gap-0 bg-slate-100 rounded-lg border border-gray-200">
                                            <!-- minus button form -->
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="action" value="update_qty">
                                                <input type="hidden" name="index" value="<?= $index; ?>">
                                                <?php if ($is_logged_in && isset($item['id'])): ?>
                                                    <input type="hidden" name="cart_item_id" value="<?= $item['id']; ?>">
                                                <?php endif; ?>
                                                <input type="hidden" name="new_qty" value="<?= max(1, $item['quantity'] - 1); ?>">
                                                <button type="submit" class="w-8 h-8 flex items-center justify-center text-slate-500 hover:text-amber-600 hover:bg-amber-50 rounded-l-lg transition-colors duration-200 font-bold <?= $item['quantity'] <= 1 ? 'opacity-40 cursor-not-allowed' : '' ?>" <?= $item['quantity'] <= 1 ? 'disabled' : '' ?>>
                                                    <i class="fa-solid fa-minus text-[10px]"></i>
                                                </button>
                                            </form>
                                            
                                            <span class="w-8 h-8 flex items-center justify-center text-sm font-bold text-slate-800 border-x border-gray-200">
                                                <?= $item['quantity']; ?>
                                            </span>
                                            
                                            <!-- plus button form (Validates against available stock instead of hardcoded numbers) -->
                                            <form method="POST" class="inline plus-qty-form" onsubmit="return verifyPlusAction(this, <?= $item['quantity']; ?>, <?= $item_stock; ?>, '<?= htmlspecialchars(addslashes($item['title'])); ?>')">
                                                <input type="hidden" name="action" value="update_qty">
                                                <input type="hidden" name="index" value="<?= $index; ?>">
                                                <?php if ($is_logged_in && isset($item['id'])): ?>
                                                    <input type="hidden" name="cart_item_id" value="<?= $item['id']; ?>">
                                                <?php endif; ?>
                                                <input type="hidden" name="new_qty" value="<?= $item['quantity'] + 1; ?>">
                                                <button type="submit" class="w-8 h-8 flex items-center justify-center text-slate-500 hover:text-amber-600 hover:bg-amber-50 rounded-r-lg transition-colors duration-200 font-bold">
                                                    <i class="fa-solid fa-plus text-[10px]"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                    <td class="p-4 text-center font-black text-slate-900">
                                        <?= number_format($item['totalprice']); ?> MMK
                                    </td>
                                    <td class="p-4 text-center">
                                        <!-- Secure POST-based Removal Form ensuring clean responsive data destruction -->
                                        <form method="POST" class="inline" onsubmit="return confirm('ဒီစာအုပ်ကို ခြင်းတောင်းထဲကနေ ဖျက်မှာ သေချာပါသလား?');">
                                            <input type="hidden" name="action" value="remove_item">
                                            <input type="hidden" name="index" value="<?= $index; ?>">
                                            <?php if ($is_logged_in && isset($item['id'])): ?>
                                                <input type="hidden" name="cart_item_id" value="<?= $item['id']; ?>">
                                            <?php endif; ?>
                                            <button type="submit" class="inline-flex items-center gap-1.5 bg-red-500 hover:bg-red-600 text-white px-3 py-1.5 rounded-lg text-xs font-bold transition-colors duration-200 shadow-sm shadow-red-500/20">
                                                <i class="fa-solid fa-trash-can text-[10px]"></i> Remove
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Grand Total + Action Triggers -->
            <div class="mt-8 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <h2 class="text-xl md:text-2xl font-black text-slate-900">
                    Grand Total:
                    <span class="text-amber-600"><?= number_format($grandTotal); ?> MMK</span>
                </h2>
                <div class="flex gap-3">
                    <a href="books.php"
                       class="inline-flex items-center gap-2 bg-slate-200 hover:bg-slate-300 text-slate-700 px-5 py-2.5 rounded-xl text-sm font-bold transition-colors duration-200">
                        <i class="fa-solid fa-arrow-left text-xs"></i> Continue Shopping
                    </a>
                    <?php if ($is_logged_in): ?>
                        <a href="checkout.php" onclick="return verifyCheckoutStock(event, this.href)"
                           class="inline-flex items-center gap-2 bg-amber-500 hover:bg-amber-400 text-slate-900 px-5 py-2.5 rounded-xl text-sm font-bold transition-colors duration-200 shadow-sm shadow-amber-500/20">
                            Checkout <i class="fa-solid fa-arrow-right text-xs"></i>
                        </a>
                    <?php else: ?>
                        <a href="../auth/login.php?redirect=checkout.php" onclick="return verifyCheckoutStock(event, this.href)"
                           class="inline-flex items-center gap-2 bg-amber-500 hover:bg-amber-400 text-slate-900 px-5 py-2.5 rounded-xl text-sm font-bold transition-colors duration-200 shadow-sm shadow-amber-500/20">
                            Checkout <i class="fa-solid fa-arrow-right text-xs"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <!-- Empty Cart Visual Component -->
            <div class="bg-white p-12 rounded-2xl shadow-sm border border-gray-100 text-center">
                <i class="fa-solid fa-cart-shopping text-5xl text-gray-200 mb-4"></i>
                <h2 class="text-xl font-bold text-gray-500">Your Cart is Empty</h2>
                <p class="text-gray-400 text-sm mt-2">Browse our collection and add some books!</p>
                <a href="books.php"
                   class="inline-flex items-center gap-2 mt-6 bg-amber-500 hover:bg-amber-400 text-slate-900 px-6 py-3 rounded-xl font-bold transition-colors duration-200 shadow-sm shadow-amber-500/20">
                    <i class="fa-solid fa-book text-sm"></i> Browse Books
                </a>
            </div>
        <?php endif; ?>
    </div>

    <?php include __DIR__ . '/../auth/footer.php'; ?>

    <!-- Client-side script handling realtime stock intercept animations -->
    <script>
    // Trigger and fade in custom toast layout alert window
    function displayCartToast(msg) {
        const toast = document.getElementById('cartStockToast');
        document.getElementById('toastMessage').innerText = msg;
        toast.classList.remove('translate-x-full', 'opacity-0', 'pointer-events-none');
        toast.classList.add('translate-x-0', 'opacity-100');
        
        // Setup automatic timer to close window state
        setTimeout(hideCartToast, 4500);
    }

    // Dismiss custom warning alert notification component
    function hideCartToast() {
        const toast = document.getElementById('cartStockToast');
        if(toast) {
            toast.classList.add('translate-x-full', 'opacity-0', 'pointer-events-none');
            toast.classList.remove('translate-x-0', 'opacity-100');
        }
    }

    // Intercept single increment addition requests based on book stock
    function verifyPlusAction(formObj, currentQty, availableStock, bookTitle) {
        if ((currentQty + 1) > availableStock) {
            displayCartToast(`"${bookTitle}" အတွက် သင်မှာယူထားသော အရေအတွက်သည် ဆိုင်ရှိလက်ကျန်အရေအတွက် (${availableStock} အုပ်) ထက် များနေပါသဖြင့် ထပ်မံတိုးမြှင့်၍ မရနိုင်တော့ပါဗျာ။`);
            return false;
        }
        return true;
    }

    // Intercept validation checks before checkout actions
    function verifyCheckoutStock(event, redirectUrl) {
        const cartRows = document.querySelectorAll('.cart-item-row');
        let stockViolationDetected = false;
        let violationMsg = "";

        for (let row of cartRows) {
            const title = row.getAttribute('data-title');
            const stock = parseInt(row.getAttribute('data-stock')) || 0;
            const currentQty = parseInt(row.getAttribute('data-qty')) || 0;

            if (currentQty > stock) {
                stockViolationDetected = true;
                violationMsg = `"${title}" မှာ စတိုးဆိုင်တွင် လက်ကျန် ${stock} အုပ်သာ ကျန်ရှိပါတော့သည်။ သင်မှာယူထားသော အရေအတွက် (${currentQty} အုပ်) ထက် ကျော်လွန်နေသဖြင့် ရှေ့ဆက်သွား၍ မရနိုင်သေးပါဗျာ။`;
                break;
            }
        }

        if (stockViolationDetected) {
            event.preventDefault(); // Stop standard routing page transition
            displayCartToast(violationMsg);
            return false;
        }
        return true;
    }
    </script>
</body>
</html>