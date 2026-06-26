<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Book Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            background: #f4f4f4;
        }

        /* Navbar */
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #2c3e50;
            padding: 15px 30px;
            color: white;
        }

        .navbar ul {
            list-style: none;
            display: flex;
            gap: 20px;
        }

        .navbar ul li {
            cursor: pointer;
        }

        .navbar ul li:hover {
            color: #f39c12;
        }

        /* Hero */
        .hero {
            background: url('https://images.unsplash.com/photo-1524995997946-a1c2e315a42f') center/cover;
            height: 300px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
            text-align: center;
        }

        .hero h1 {
            font-size: 40px;
        }

        .hero button {
            margin-top: 10px;
            padding: 10px 20px;
            border: none;
            background: #f39c12;
            color: white;
            cursor: pointer;
        }

        /* Search */
        .search {
            text-align: center;
            margin: 20px;
        }

        .search input {
            width: 60%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        /* Section Title */
        .section-title {
            text-align: center;
            margin: 30px 0 10px;
            font-size: 24px;
        }

        /* Books Grid */
        .books {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            padding: 20px;
        }

        /* Book Card */
        .book {
            background: white;
            padding: 10px;
            text-align: center;
            border-radius: 8px;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.1);
        }

        .book img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 5px;
        }

        .book h4 {
            margin: 8px 0 5px;
            font-size: 16px;
            color: #2c3e50;
        }

        .book .author {
            font-size: 13px;
            color: #7f8c8d;
            margin-bottom: 5px;
        }

        .stars {
            color: #f1c40f;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .book .price {
            font-weight: bold;
            color: #27ae60;
            margin-bottom: 8px;
        }

        .book button {
            margin-top: 5px;
            padding: 8px;
            width: 100%;
            border: none;
            background: #27ae60;
            color: white;
            cursor: pointer;
            border-radius: 5px;
        }

        .book button:hover {
            background: #219150;
        }

        /* Wishlist Button */
        .wishlist {
            background: transparent;
            border: 1px solid #e74c3c;
            color: #e74c3c;
            padding: 6px;
            width: 100%;
            margin-top: 5px;
            cursor: pointer;
            border-radius: 5px;
            transition: 0.3s;
        }

        .wishlist:hover {
            background: #e74c3c;
            color: white;
        }

        /* Categories */
        .categories {
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
            margin: 20px;
        }

        .cat {
            background: #3498db;
            color: white;
            padding: 10px 20px;
            border-radius: 20px;
            cursor: pointer;
        }

        /* Footer */
        .footer {
            background: #2c3e50;
            color: white;
            text-align: center;
            padding: 15px;
            margin-top: 20px;
        }
    </style>

</head>

<body>

    <!-- Navbar -->
    <header class="bg-slate-800 text-white sticky top-0 z-50 shadow-md">
        <div class="container mx-auto px-6 py-2 flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-book-open text-xl text-blue-400"></i>
                <span class="text-xl font-bold tracking-wider text-white">BookShop</span>
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

    <!-- Hero -->
    <div class="hero">
        <h1>Welcome to Online Book Shop</h1>
        <p>Find your favorite books here</p>
        <button>Shop Now</button>
    </div>
    <!-- Search -->
            <div class="search">
                <input type="text" placeholder="Search books...">
            </div>



    <!-- Categories -->
    <h2 class="section-title">Categories</h2>
    <div class="categories">
        <div class="cat">တရားဓမ္မ</div>
        <div class="cat">ကျန်းမာရေး</div>
        <div class="cat">အတ္ထုပ္ပတ္တိ</div>
        <div class="cat">ပုံပြင်</div>
        <div class="cat">ဝတ္ထု</div>
    </div>

    <!-- Books -->
    <h2 class="section-title">Books</h2>

    <div class="books">

        <!-- Book 1 -->
        <div class="book">
            <img src="../assets/w1.png">
            <h4>အကြင်သူသည်</h4>
            <div class="author">သိုးဆောင်း</div>
            <div class="stars">⭐⭐⭐⭐☆ </div>
            <div class="price">12500(ကျပ်)</div>
            <button>Add to Cart</button>

        </div>

        <!-- Book 2 -->
        <div class="book">
            <img src="../assets/w5.png">
            <h4>တိမ်နဲ့ချည်တဲ့ကြိုး</h4>
            <div class="author">ဂျူး</div>
            <div class="stars">⭐⭐⭐⭐⭐ </div>
            <div class="price">11000(ကျပ်)</div>
            <button>Add to Cart</button>

        </div>

        <!-- Book 3 -->
        <div class="book">
            <img src="../assets/w3.png">
            <h4>လက်ညှိးထိုး၍ပန်းဝေသည်</h4>
            <div class="author">သိုးဆောင်း</div>
            <div class="stars">⭐⭐⭐☆☆ </div>
            <div class="price">11000(ကျပ်)</div>
            <button>Add to Cart</button>

        </div>

        <!-- Book 4 -->
        <div class="book">
            <img src="../assets/w6.png">
            <h4>အမှတ်တရ</h4>
            <div class="author">ဂျူး</div>
            <div class="stars">⭐⭐⭐⭐☆ </div>
            <div class="price">12000(ကျပ်)</div>
            <button>Add to Cart</button>

        </div>


    </div>
   

            
           



    <!-- Footer -->
    <div class="footer">
        <p>© 2026 Online Book Shop | All Rights Reserved</p>
    </div>

</body>

</html>