<?php
session_start();
require_once "../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'customer') {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$sql = "SELECT Cart_item.*, Books.title, Books.book_image
        FROM Cart_item
        INNER JOIN Books ON Cart_item.book_id = Books.id
        WHERE Cart_item.user_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

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

<body class="bg-gray-100 min-h-screen flex flex-col font-sans text-slate-800">

    <?php include '../auth/headeru.php'; ?>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-10 flex-1 w-full">

        <h1 class="text-2xl md:text-3xl font-black text-slate-900 mb-6 flex items-center gap-3">
            <i class="fa-solid fa-cart-shopping text-amber-500"></i> My Shopping Cart
        </h1>

        <?php if ($result->num_rows > 0): ?>
            <!-- Cart Table -->
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
                            <?php while ($row = $result->fetch_assoc()):
                                $grandTotal += $row['totalprice'];
                            ?>
                                <tr class="border-b border-gray-100 hover:bg-gray-50/50 transition-colors duration-150">
                                    <td class="p-4">
                                        <img src="../uploads/<?= htmlspecialchars($row['book_image']); ?>"
                                             class="w-16 h-22 object-cover rounded-lg shadow-sm" alt="Cover">
                                    </td>
                                    <td class="p-4 font-bold text-slate-800">
                                        <?= htmlspecialchars($row['title']); ?>
                                    </td>
                                    <td class="p-4 text-center text-gray-600 font-medium">
                                        <?= number_format($row['unit_price']); ?> MMK
                                    </td>
                                    <td class="p-4 text-center font-bold text-slate-800">
                                        <?= $row['quantity']; ?>
                                    </td>
                                    <td class="p-4 text-center font-black text-slate-900">
                                        <?= number_format($row['totalprice']); ?> MMK
                                    </td>
                                    <td class="p-4 text-center">
                                        <a href="remove_cart.php?id=<?= $row['id']; ?>"
                                           class="inline-flex items-center gap-1.5 bg-red-500 hover:bg-red-600 text-white px-3 py-1.5 rounded-lg text-xs font-bold transition-colors duration-200">
                                            <i class="fa-solid fa-trash-can text-[10px]"></i> Remove
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Grand Total + Actions -->
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
                    <a href="checkout.php"
                       class="inline-flex items-center gap-2 bg-amber-500 hover:bg-amber-400 text-slate-900 px-5 py-2.5 rounded-xl text-sm font-bold transition-colors duration-200 shadow-sm shadow-amber-500/20">
                        Checkout <i class="fa-solid fa-arrow-right text-xs"></i>
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Empty Cart -->
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

    <?php include '../auth/footer.php'; ?>
</body>
</html>
