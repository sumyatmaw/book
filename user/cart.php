<?php
/**
 * Online Book Shop — Shopping Cart Page
 * Displays cart items for guests (session) and logged-in users (DB).
 * Supports AJAX-based quantity update (+/-) and item removal with stock validation.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/db.php';

$is_logged_in = isset($_SESSION['user_id']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'customer';

// Handle AJAX Request for live quantity updating
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'update_qty') {
    header('Content-Type: application/json');
    $idx = intval($_POST['index']);
    $new_qty = max(1, intval($_POST['new_qty']));
    $response = ['success' => false, 'message' => ''];

    if ($is_logged_in && isset($_POST['cart_item_id'])) {
        $cid = intval($_POST['cart_item_id']);
        $uid = $_SESSION['user_id'];
        
        // Fetch cart item and current book stock level from database
        $stock_stmt = $conn->prepare("SELECT Cart_item.book_id, Cart_item.quantity, Cart_item.unit_price, Books.stock FROM Cart_item INNER JOIN Books ON Cart_item.book_id = Books.id WHERE Cart_item.id = ? AND Cart_item.user_id = ?");
        $stock_stmt->bind_param("ii", $cid, $uid);
        $stock_stmt->execute();
        $r = $stock_stmt->get_result()->fetch_assoc();
        $stock_stmt->close();

        if ($r) {
            $available_stock = max(0, intval($r['stock']));

            if ($new_qty <= $available_stock) {
                $new_total = $r['unit_price'] * $new_qty;
                
                // Update Cart_item record in Database
                $upd = $conn->prepare("UPDATE Cart_item SET quantity = ?, totalprice = ? WHERE id = ? AND user_id = ?");
                $upd->bind_param("idii", $new_qty, $new_total, $cid, $uid);
                $upd->execute();
                $upd->close();

                $response = ['success' => true, 'new_item_total' => number_format($new_total) . ' ကျပ်'];
            } else {
                $response = ['success' => false, 'message' => 'Requested quantity exceeds available stock level. Only ' . $available_stock . ' items available.'];
            }
        }
    } elseif (!$is_logged_in && isset($_SESSION['guest_cart'][$idx])) {
        $b_id = intval($_SESSION['guest_cart'][$idx]['book_id']);
        
        // Fetch current book stock level for guest user
        $st_stmt = $conn->prepare("SELECT stock FROM Books WHERE id = ?");
        $st_stmt->bind_param("i", $b_id);
        $st_stmt->execute();
        $st_res = $st_stmt->get_result()->fetch_assoc();
        $available_stock = $st_res ? intval($st_res['stock']) : 0;
        $st_stmt->close();

        if ($new_qty <= $available_stock) {
            $_SESSION['guest_cart'][$idx]['quantity'] = $new_qty;
            $new_total = $_SESSION['guest_cart'][$idx]['unit_price'] * $new_qty;
            $_SESSION['guest_cart'][$idx]['totalprice'] = $new_total;

            $response = ['success' => true, 'new_item_total' => number_format($new_total) . ' ကျပ်'];
        } else {
            $response = ['success' => false, 'message' => 'Requested quantity exceeds available stock level. Only ' . $available_stock . ' items available.'];
        }
    }

    // Recalculate Grand Total and Total Cart Items Count for Response JSON
    $grand = 0;
    $total_cart_items_count = 0;

    if ($is_logged_in) {
        $g_stmt = $conn->prepare("SELECT SUM(totalprice) as total, SUM(quantity) as total_qty FROM Cart_item WHERE user_id = ?");
        $g_stmt->bind_param("i", $_SESSION['user_id']);
        $g_stmt->execute();
        $g_res = $g_stmt->get_result()->fetch_assoc();
        $grand = $g_res['total'] ?? 0;
        $total_cart_items_count = $g_res['total_qty'] ?? 0;
        $g_stmt->close();
    } else {
        foreach (($_SESSION['guest_cart'] ?? []) as $item) {
            $grand += $item['totalprice'];
            $total_cart_items_count += $item['quantity'];
        }
    }
    
    $response['grand_total'] = number_format($grand) . ' ကျပ်';
    $response['cart_count'] = $total_cart_items_count;
    
    echo json_encode($response);
    exit;
}

// Handle Post Back for items removal (Clearing item automatically increases available stock on books.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'remove_item' && isset($_POST['index'])) {
        $idx = intval($_POST['index']);

        if ($is_logged_in && isset($_POST['cart_item_id'])) {
            $cid = intval($_POST['cart_item_id']);
            $uid = $_SESSION['user_id'];

            // Delete item record from Cart_item table
            $del = $conn->prepare("DELETE FROM Cart_item WHERE id = ? AND user_id = ?");
            $del->bind_param("ii", $cid, $uid);
            $del->execute();
            $del->close();
        } elseif (!$is_logged_in && isset($_SESSION['guest_cart'][$idx])) {
            // Delete item from Guest Session Cart
            unset($_SESSION['guest_cart'][$idx]);
            $_SESSION['guest_cart'] = array_values($_SESSION['guest_cart']);
        }
    }
    header("Location: cart.php");
    exit;
}

// Load cart data from database or session
if ($is_logged_in) {
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT Cart_item.*, Books.title, Books.book_image, Books.stock FROM Cart_item INNER JOIN Books ON Cart_item.book_id = Books.id WHERE Cart_item.user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $cart_items = [];
    while ($row = $result->fetch_assoc()) {
        $row['stock'] = max(0, intval($row['stock']));
        $cart_items[] = $row;
    }
    $stmt->close();
} else {
    $cart_items = isset($_SESSION['guest_cart']) ? $_SESSION['guest_cart'] : [];
    foreach ($cart_items as $index => $item) {
        $b_id = intval($item['book_id']);
        $st_stmt = $conn->prepare("SELECT stock FROM Books WHERE id = ?");
        $st_stmt->bind_param("i", $b_id);
        $st_stmt->execute();
        $st_res = $st_stmt->get_result()->fetch_assoc();
        $original_stock = $st_res ? intval($st_res['stock']) : 0;
        $st_stmt->close();
        $cart_items[$index]['stock'] = max(0, $original_stock);
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
<body class="bg-gray-300 min-h-screen flex flex-col font-sans text-slate-800 relative antialiased">

    <?php include __DIR__ . '/../auth/header.php'; ?>

    <!-- Stock Alert Toast Notification -->
    <div id="cartStockToast" class="fixed top-5 right-5 z-50 transform translate-x-full opacity-0 transition-all duration-300 pointer-events-none max-w-sm w-[90%] sm:w-full mx-auto sm:mx-0">
        <div class="bg-gray-500 border-l-4 border-rose-500 rounded-xl shadow-xl p-4 flex items-start gap-3 border border-slate-100">
            <div class="bg-rose-50 p-2 rounded-lg text-rose-600 flex-shrink-0">
                <i class="fa-solid fa-triangle-exclamation text-lg"></i>
            </div>
            <div class="flex-1">
                <h4 class="font-bold text-slate-900 text-sm mb-0.5">Notification</h4>
                <p id="toastMessage" class="text-xs text-slate-600 leading-relaxed"></p>
            </div>
            <button onclick="hideCartToast()" class="text-slate-400 hover:text-slate-600 transition p-1 flex-shrink-0">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>
    </div>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-6 sm:py-10 flex-1 w-full">
        <h1 class="text-xl sm:text-2xl md:text-3xl font-black text-slate-900 mb-6 flex items-center gap-3">
            <i class="fa-solid fa-cart-shopping text-amber-500"></i> My Shopping Cart
        </h1>

        <?php if (!empty($cart_items)): ?>
            <div class="bg-gray-50 rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs sm:text-sm min-w-[600px]">
                        <thead class="bg-gray-500 text-white uppercase text-[10px] sm:text-[11px] tracking-wider">
                            <tr>
                                <th class="p-3 sm:p-4 text-left font-bold">Image</th>
                                <th class="p-3 sm:p-4 text-left font-bold">Book</th>
                                <th class="p-3 sm:p-4 text-center font-bold">Price</th>
                                <th class="p-3 sm:p-4 text-center font-bold">Qty</th>
                                <th class="p-3 sm:p-4 text-center font-bold">Total</th>
                                <th class="p-3 sm:p-4 text-center font-bold">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($cart_items as $index => $item):
                                $grandTotal += $item['totalprice'];
                                $item_stock = isset($item['stock']) ? intval($item['stock']) : 0;
                                $cart_item_id = $item['id'] ?? 0;
                            ?>
                                <tr class="cart-item-row hover:bg-slate-50/80 transition duration-150" 
                                    id="row-<?= $index; ?>"
                                    data-title="<?= htmlspecialchars($item['title']); ?>" 
                                    data-stock="<?= $item_stock; ?>">
                                    
                                    <td class="p-3 sm:p-4">
                                        <img src="../uploads/<?= htmlspecialchars($item['book_image'] ?? 'default.jpg'); ?>"
                                             class="w-12 h-16 sm:w-16 sm:h-20 object-cover rounded-lg shadow-xs bg-slate-100" alt="Cover">
                                    </td>
                                    <td class="p-3 sm:p-4 font-bold text-slate-800 break-words max-w-[180px] sm:max-w-none">
                                        <?= htmlspecialchars($item['title']); ?>
                                    </td>
                                    <td class="p-3 sm:p-4 text-center text-slate-600 font-medium whitespace-nowrap">
                                        <?= number_format($item['unit_price']); ?> ကျပ်
                                    </td>
                                    <td class="p-3 sm:p-4 text-center whitespace-nowrap">
                                        <div class="inline-flex items-center gap-0 bg-slate-100 rounded-lg border border-slate-200">
                                            <!-- Minus Button -->
                                            <button type="button" 
                                                    onclick="changeQuantity(<?= $index; ?>, <?= $cart_item_id; ?>, -1)"
                                                    id="btn-minus-<?= $index; ?>"
                                                    class="w-7 h-7 sm:w-8 sm:h-8 flex items-center justify-center text-slate-500 hover:text-amber-600 hover:bg-amber-50 rounded-l-lg transition font-bold <?= $item['quantity'] <= 1 ? 'opacity-40 cursor-not-allowed' : '' ?>" <?= $item['quantity'] <= 1 ? 'disabled' : '' ?>>
                                                <i class="fa-solid fa-minus text-[10px]"></i>
                                            </button>
                                            
                                            <!-- Live Counter View -->
                                            <span id="qty-val-<?= $index; ?>" data-current-qty="<?= $item['quantity']; ?>" class="w-7 h-7 sm:w-8 sm:h-8 flex items-center justify-center text-xs sm:text-sm font-bold text-slate-800 border-x border-slate-200">
                                                <?= $item['quantity']; ?>
                                            </span>
                                            
                                            <!-- Plus Button -->
                                            <button type="button" 
                                                    onclick="changeQuantity(<?= $index; ?>, <?= $cart_item_id; ?>, 1)"
                                                    id="btn-plus-<?= $index; ?>"
                                                    class="w-7 h-7 sm:w-8 sm:h-8 flex items-center justify-center text-slate-500 hover:text-amber-600 hover:bg-amber-50 rounded-r-lg transition font-bold">
                                                <i class="fa-solid fa-plus text-[10px]"></i>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="p-3 sm:p-4 text-center font-black text-slate-900 item-total-price whitespace-nowrap">
                                        <?= number_format($item['totalprice']); ?> ကျပ်
                                    </td>
                                    <td class="p-3 sm:p-4 text-center whitespace-nowrap">
                                        <form method="POST" class="inline" onsubmit="return confirm('ဒီစာအုပ်ကို ခြင်းတောင်းထဲကနေ ဖျက်မှာ သေချာပါသလား?');">
                                            <input type="hidden" name="action" value="remove_item">
                                            <input type="hidden" name="index" value="<?= $index; ?>">
                                            <?php if ($is_logged_in): ?>
                                                <input type="hidden" name="cart_item_id" value="<?= $item['id']; ?>">
                                            <?php endif; ?>
                                            <button type="submit" class="inline-flex items-center gap-1.5 bg-rose-500 hover:bg-rose-600 text-white px-2.5 sm:px-3 py-1.5 rounded-lg text-xs font-bold transition shadow-xs">
                                                <i class="fa-solid fa-trash-can text-[10px]"></i> <span class="hidden sm:inline">Remove</span>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Responsive Bottom Actions Section -->
            <div class="mt-6 sm:mt-8 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <h2 class="text-lg sm:text-xl md:text-2xl font-black text-slate-900">
                    စုစုပေါင်း =
                    <span id="cart-grand-total" class="text-amber-600"><?= number_format($grandTotal); ?> ကျပ်</span>
                </h2>
                <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto justify-end">
                    <a href="../books.php"
                       class="inline-flex items-center justify-center gap-2 bg-yellow-500 text-slate-700 px-5 py-2.5 sm:py-3 rounded-xl text-xs sm:text-sm font-bold transition w-full sm:w-auto">
                        <i class="fa-solid fa-arrow-left text-xs"></i> Continue Shopping
                    </a>
                    <a href="checkout.php"
                       class="inline-flex items-center justify-center gap-2 bg-blue-500 text-white px-5 py-2.5 sm:py-3 rounded-xl text-xs sm:text-sm font-bold transition shadow-sm w-full sm:w-auto">
                        Checkout <i class="fa-solid fa-arrow-right text-xs"></i>
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-white p-8 sm:p-12 rounded-2xl shadow-sm border border-slate-200 text-center">
                <i class="fa-solid fa-cart-shopping text-4xl sm:text-5xl text-amber-500 mb-4"></i>
                <h2 class="text-lg sm:text-xl font-bold text-gray-900">Your Cart is Empty</h2>
                <p class="text-gray-900 text-xs sm:text-sm mt-1">Browse our collection and add some books!</p>
                <a href="../books.php"
                   class="inline-flex items-center gap-2 mt-6 bg-blue-500 hover:bg-blue-600 text-white px-6 py-2.5 sm:py-3 rounded-xl font-bold text-xs sm:text-sm transition shadow-sm">
                    <i class="fa-solid fa-book text-sm"></i> Browse Books
                </a>
            </div>
        <?php endif; ?>
    </div>

    <?php include __DIR__ . '/../auth/footer.php'; ?>

    <script>
    // Display custom stock warning toast
    function displayCartToast(msg) {
        const toast = document.getElementById('cartStockToast');
        document.getElementById('toastMessage').innerText = msg;
        toast.classList.remove('translate-x-full', 'opacity-0', 'pointer-events-none');
        toast.classList.add('translate-x-0', 'opacity-100');
        setTimeout(hideCartToast, 4500);
    }

    // Hide custom stock warning toast
    function hideCartToast() {
        const toast = document.getElementById('cartStockToast');
        if (toast) {
            toast.classList.add('translate-x-full', 'opacity-0', 'pointer-events-none');
            toast.classList.remove('translate-x-0', 'opacity-100');
        }
    }

    // Handle AJAX Quantity Increment and Decrement
    function changeQuantity(index, cartItemId, adjustment) {
        const qtyElement = document.getElementById(`qty-val-${index}`);
        const rowElement = document.getElementById(`row-${index}`);
        const currentQty = parseInt(qtyElement.getAttribute('data-current-qty'));
        const availableStock = parseInt(rowElement.getAttribute('data-stock'));
        const bookTitle = rowElement.getAttribute('data-title');
        
        const newQty = currentQty + adjustment;

        if (newQty < 1) return;
        
        // Check physical stock limit before sending request
        if (newQty > availableStock) {
            displayCartToast(`"${bookTitle}" အတွက် သင်မှာယူထားသော အရေအတွက်သည် ဆိုင်ရှိလက်ကျန်အရေအတွက်ထက် များနေပါသဖြင့် ထပ်မံတိုးမြှင့်၍ မရနိုင်တော့ပါဗျာ။`);
            return;
        }

        // Send AJAX Request to update cart quantity
        const formData = new FormData();
        formData.append('ajax_action', 'update_qty');
        formData.append('index', index);
        formData.append('new_qty', newQty);
        if (cartItemId > 0) {
            formData.append('cart_item_id', cartItemId);
        }

        fetch('cart.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update item quantity on UI
                qtyElement.innerText = newQty;
                qtyElement.setAttribute('data-current-qty', newQty);

                // Update Row Total and Grand Total on UI
                rowElement.querySelector('.item-total-price').innerText = data.new_item_total;
                document.getElementById('cart-grand-total').innerText = data.grand_total;

                // Handle minus button state
                const minusBtn = document.getElementById(`btn-minus-${index}`);
                if (newQty <= 1) {
                    minusBtn.classList.add('opacity-40', 'cursor-not-allowed');
                    minusBtn.setAttribute('disabled', 'true');
                } else {
                    minusBtn.classList.remove('opacity-40', 'cursor-not-allowed');
                    minusBtn.removeAttribute('disabled');
                }

                // Synchronize Header Navigation Cart Badge and Total Price
                if (typeof window.refreshCartBadge === 'function') {
                    window.refreshCartBadge();
                } else {
                    // Fallback to directly update cart badge element if function is not available
                    const cartBadge = document.querySelector('.cart-badge-count');
                    if (cartBadge && data.cart_count !== undefined) {
                        cartBadge.innerText = data.cart_count;
                    }
                }
            } else {
                displayCartToast(data.message || 'ပစ္စည်းလက်ကျန် မလုံလောက်ပါသဖြင့် မအောင်မြင်ပါ။');
            }
        })
        .catch(err => {
            console.error('AJAX quantity update error:', err);
        });
    }
    </script>
</body>
</html>