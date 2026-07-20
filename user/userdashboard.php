<?php
session_start();
require_once '../config/db.php';

// Customer Login Check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'customer') {
    header("Location: ../auth/login.php");
    exit();
}

$name = $_SESSION['user_name'];

// Fetch data for books display with category join
$book_sql = "SELECT Books.*, Categories.category_name FROM Books 
             LEFT JOIN Categories ON Books.category_id = Categories.id 
             ORDER BY Books.id DESC";
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

    <section class="bg-blue-600 text-white py-12 shadow-inner">
        <div class="max-w-7xl mx-auto px-6">
            <h2 class="text-3xl md:text-4xl font-bold mb-2">
                Welcome, <?php echo htmlspecialchars($name); ?> 👋
            </h2>
            <p class="text-blue-100 text-sm md:text-base">Find your favourite books anytime & discover your next great read.</p>
        </div>
    </section>

    <div class="max-w-7xl mx-auto px-4 md:px-6 mt-10 pb-20">
        <div class="flex justify-between items-center mb-6 border-b pb-4 border-gray-200">
            <h2 class="text-xl md:text-2xl font-bold text-slate-900 flex items-center gap-2">
                <i class="fa-solid fa-layer-group text-blue-600"></i> Available Books
            </h2>
            <span class="text-xs bg-slate-200 text-slate-700 px-3 py-1 rounded-full font-bold">
                <?= $books_result ? $books_result->num_rows : 0; ?> books found
            </span>
        </div>

        <?php if ($books_result && $books_result->num_rows > 0): ?>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3 md:gap-4">
                <?php while ($book = $books_result->fetch_assoc()): ?>
                    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm hover:shadow-md transition duration-300 flex flex-col aspect-[4/5] relative w-full max-w-[220px] mx-auto">
                        
                        <div class="w-full h-[55%] overflow-hidden bg-gray-100 relative flex-shrink-0 border-b border-gray-100">
                            <?php if (!empty($book['book_image'])): ?>
                                <img src="../uploads/<?= htmlspecialchars($book['book_image']); ?>" alt="Book Cover" class="w-full h-full object-cover block">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center text-gray-400 text-xs font-semibold bg-gray-200">No Image</div>
                            <?php endif; ?>
                            
                            <?php if ($book['stock'] <= 0): ?>
                                <div class="absolute inset-0 bg-black/60 flex items-center justify-center backdrop-blur-[1px]">
                                    <span class="bg-red-600 text-white text-[9px] px-1.5 py-0.5 rounded font-bold uppercase tracking-wider">Out of stock</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="p-2 flex-1 flex flex-col justify-between bg-white text-center">
                            <div class="space-y-0.5">
                                <h3 class="font-bold text-xs text-gray-800 line-clamp-1" title="<?= htmlspecialchars($book['title']); ?>">
                                    <?= htmlspecialchars($book['title']); ?>
                                </h3>
                                <p class="text-[10px] text-gray-500 line-clamp-1 italic">
                                    <?= htmlspecialchars($book['author']); ?>
                                </p>
                                <p class="text-xs font-black text-blue-600 pt-0.5">
                                    <?= number_format($book['price']); ?> ကျပ်
                                </p>
                            </div>

                            <div class="grid grid-cols-2 gap-1 mt-1.5">
                                <a href="bookdetail.php?id=<?= $book['id']; ?>" class="bg-gray-100 hover:bg-gray-200 text-gray-700 py-1 rounded text-[10px] font-bold text-center transition flex items-center justify-center gap-0.5">
                                    <i class="fa-solid fa-eye text-[8px]"></i> View
                                </a>
                                
                                <form action="addtocart.php" method="POST" class="flex">
                                    <input type="hidden" name="book_id" value="<?= $book['id']; ?>">
                                    <input type="hidden" name="quantity" value="1">
                                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-1 rounded text-[10px] font-bold transition shadow-sm flex items-center justify-center gap-0.5">
                                        <i class="fa-solid fa-cart-plus text-[8px]"></i> Add
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="bg-white text-center py-16 px-4 rounded-2xl border border-dashed border-gray-300 max-w-md mx-auto mt-6">
                <h4 class="text-lg font-bold text-gray-700">No books found</h4>
            </div>
        <?php endif; ?>
    </div>

    <?php include '../auth/footer.php'; ?>
</body>
</html>