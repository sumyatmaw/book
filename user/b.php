<?php
session_start();
require_once 'config/db.php';

// customer role မဟုတ်ရင် login page ပြန်ပို့
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'customer') {
    header("Location: auth/login.php");
    exit();
}

$userName = $_SESSION['user_name'] ?? 'Customer';

// Books + Author + Category join query
$sql = "SELECT books.id, books.title, books.price, books.stock, books.image,
               authors.author_name,
               categories.category_name
        FROM books
        LEFT JOIN authors ON books.author_id = authors.id
        LEFT JOIN categories ON books.category_id = categories.id
        ORDER BY books.id DESC";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Books - Online Book Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen font-sans flex flex-col">

    <?php include '../auth/headeru.php'; ?>

    <div class="max-w-7xl mx-auto p-6 flex-1 w-full">

        <!-- Page Title -->
        <div class="mb-6">
            <h2 class="text-3xl font-bold text-gray-800">Available Books</h2>
            <p class="text-gray-500 mt-1">Browse and add your favorite books to cart.</p>
        </div>

        <?php if ($result && $result->num_rows > 0): ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">

                <?php while ($row = $result->fetch_assoc()): ?>
                    <div class="bg-white rounded-2xl shadow hover:shadow-lg transition overflow-hidden flex flex-col">
                        
                        <!-- Book Image -->
                        <div class="h-64 bg-gray-100 overflow-hidden">
                            <?php if (!empty($row['image'])): ?>
                                <img src="uploads/<?php echo htmlspecialchars($row['image']); ?>" 
                                     alt="<?php echo htmlspecialchars($row['title']); ?>" 
                                     class="w-full h-full object-cover">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center text-gray-400 text-sm">
                                    No Image
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Book Info -->
                        <div class="p-5 flex flex-col flex-1">
                            <h3 class="text-xl font-bold text-gray-800 mb-2 line-clamp-2">
                                <?php echo htmlspecialchars($row['title']); ?>
                            </h3>

                            <p class="text-sm text-gray-600 mb-1">
                                <span class="font-semibold">Author:</span>
                                <?php echo htmlspecialchars($row['author_name'] ?? 'Unknown'); ?>
                            </p>

                            <p class="text-sm text-gray-600 mb-1">
                                <span class="font-semibold">Category:</span>
                                <?php echo htmlspecialchars($row['category_name'] ?? 'No Category'); ?>
                            </p>

                            <p class="text-sm text-gray-600 mb-3">
                                <span class="font-semibold">Stock:</span>
                                <?php echo (int)$row['stock']; ?>
                            </p>

                            <div class="mt-auto">
                                <p class="text-2xl font-bold text-blue-600 mb-4">
                                    <?php echo number_format($row['price'], 2); ?> MMK
                                </p>

                                <?php if ((int)$row['stock'] > 0): ?>
                                    <a href="add_to_cart.php?book_id=<?php echo $row['id']; ?>"
                                       class="block w-full text-center bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-xl font-semibold transition">
                                        Add to Cart
                                    </a>
                                <?php else: ?>
                                    <button class="w-full bg-gray-300 text-gray-600 py-3 rounded-xl font-semibold cursor-not-allowed" disabled>
                                        Out of Stock
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>
                <?php endwhile; ?>

            </div>
        <?php else: ?>
            <div class="bg-white rounded-2xl shadow p-10 text-center">
                <h3 class="text-2xl font-bold text-gray-700 mb-2">No Books Found</h3>
                <p class="text-gray-500">There are no books available right now.</p>
            </div>
        <?php endif; ?>

    </div>

    <?php include '../auth/footer.php'; ?>