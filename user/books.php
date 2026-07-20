<?php
session_start();
require_once "../config/db.php";

// Customer Login Check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != "customer") {
    header("Location: ../auth/login.php");
    exit();
}

// Search
$search = "";

$sql = "SELECT Books.*, Categories.category_name
        FROM Books
        LEFT JOIN Categories
        ON Books.category_id = Categories.id";

if(isset($_GET['search']) && $_GET['search']!=""){
    $search = trim($_GET['search']);

    $sql .= " WHERE Books.title LIKE '%$search%'
              OR Books.author LIKE '%$search%'
              OR Categories.category_name LIKE '%$search%'";
}

$sql .= " ORDER BY Books.id DESC";

$result = mysqli_query($conn,$sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Books - Online Book Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen flex flex-col font-sans text-slate-800">

    <?php include '../auth/header.php'; ?>

<!-- Search -->
<div class="max-w-7xl mx-auto mt-8 px-6 w-full">
    <form method="GET">
        <div class="flex shadow-sm rounded-lg overflow-hidden">
            <input
                type="text"
                name="search"
                value="<?php echo htmlspecialchars($search); ?>"
                placeholder="Search Book..."
                class="border w-full px-4 py-3 outline-none focus:border-blue-500">
            <button class="bg-blue-600 text-white px-6 hover:bg-blue-700 transition">
                Search
            </button>
        </div>
    </form>
</div>

<!-- Books Content Area -->
<div class="max-w-7xl mx-auto px-6 mt-10 flex-1 w-full">
    <h2 class="text-3xl font-bold mb-8 text-gray-800">
        Available Books
    </h2>

    <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-8">
        <?php
        if(mysqli_num_rows($result) > 0){
            while($row = mysqli_fetch_assoc($result)){
        ?>
                <!-- flex flex-col h-full ထည့်သွင်း၍ Card အမြင့်များကို ညှိထားပါသည် -->
                <div class="bg-white rounded-xl shadow hover:shadow-xl overflow-hidden flex flex-col h-full transition-all duration-300">
                    
                    <img src="../uploads/<?php echo $row['book_image'];?>" class="w-full h-72 object-cover" alt="Book Cover">

                    <!-- flex-1 နှင့် flex flex-col ကြောင့် စာသားတိုသည်ဖြစ်စေ ရှည်သည်ဖြစ်စေ Content နေရာအပြည့် ယူထားမည်ဖြစ်သည် -->
                    <div class="p-5 flex flex-col flex-1">
                        <h3 class="text-lg font-bold text-gray-800 line-clamp-2 mb-1">
                            <?php echo $row['title'];?>
                        </h3>
                        
                        <p class="text-sm text-gray-500">
                            Author : <?php echo $row['author'];?>
                        </p>
                        
                        <p class="text-sm text-gray-500">
                            Category : <?php echo $row['category_name'];?>
                        </p>
                        
                        <p class="text-blue-600 font-bold mt-3 text-lg">
                            <?php echo number_format($row['price']);?> ကျပ်
                        </p>
                        
                        <p class="text-sm text-green-600 font-medium">
                            Stock : <?php echo $row['stock'];?>
                        </p>

                        <!-- mt-auto ကြောင့် View button သည် အမြဲတမ်း Card ရဲ့ အောက်ခြေဆုံးတွင် တန်းစီပြီး ညီနေမည် ဖြစ်သည် -->
                        <div class="flex gap-2 mt-auto pt-5">
                            <a href="bookdetail.php?id=<?php echo $row['id'];?>" 
                               class="flex-1 text-center bg-blue-600 text-white py-2 rounded-lg font-medium hover:bg-blue-700 transition">
                                View
                            </a>
                        </div>
                    </div>
                </div>
        <?php
            }
        } else {
        ?>
            <div class="col-span-full text-center text-red-500 text-xl py-12">
                No Books Found.
            </div>
        <?php
        }
        ?>
    </div>
</div>
    <?php include '../auth/footer.php'; ?>