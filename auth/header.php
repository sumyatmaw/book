<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <header class="bg-slate-800 text-white sticky top-0 z-50 shadow-md">
        <div class="container mx-auto px-6 py-2 flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-book-open text-xl text-blue-400"></i>
                <span class="text-xl font-bold tracking-wider text-white">BookShop</span>
            </div>
            <!-- Search -->
            <div class="search">
                <input type="text" placeholder="Search books...">
            </div>

            <nav class="flex items-center gap-6 text-sm font-medium">
                <a href="../auth/login.php" class="text-gray-300 hover:text-blue-400 transition">Home</a>
                <a href="../auth/login.php" class="text-gray-300 hover:text-blue-400 transition">Books</a>
                <a href="../auth/login.php" class="text-gray-300 hover:text-blue-400 transition">Categories</a>
                <a href="../auth/login.php" class="text-gray-300 hover:text-blue-400 transition">Login</a>
                <a href="../auth/register.php" class="text-gray-300 hover:text-blue-400 transition">Register</a>

                <a href="../auth/login.php" class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-1.5 rounded-full text-xs font-semibold shadow transition">
                    <i class="fa-solid fa-basket-shopping"></i>
                    <span>Cart 🛒</span>
                </a>
            </nav>
        </div>
    </header>
</body>
</html>