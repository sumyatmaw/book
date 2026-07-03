<?php
session_start();
require_once "../config/db.php";

// Customer Login Check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'customer') {
    header("Location: ../auth/login.php");
    exit();
}

// Book ID
if(!isset($_GET['id'])){
    header("Location: books.php");
    exit();
}

$id = intval($_GET['id']);

// Book Detail
$sql = "SELECT Books.*, Categories.category_name
        FROM Books
        LEFT JOIN Categories
        ON Books.category_id = Categories.id
        WHERE Books.id='$id'";

$result = mysqli_query($conn,$sql);

if(mysqli_num_rows($result)==0){
    die("Book not found.");
}

$book = mysqli_fetch_assoc($result);

// Average Rating
$avg_sql = "SELECT ROUND(AVG(rating),1) AS avg_rating
            FROM Ratings
            WHERE book_id='$id'";

$avg_result = mysqli_query($conn,$avg_sql);
$avg = mysqli_fetch_assoc($avg_result);

// Reviews
$review_sql = "SELECT Ratings.*, Users.name
               FROM Ratings
               JOIN Users
               ON Ratings.user_id=Users.id
               WHERE Ratings.book_id='$id'
               ORDER BY Ratings.id DESC";

$reviews = mysqli_query($conn,$review_sql);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($book['title']); ?> - Online Book Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen flex flex-col font-sans text-slate-800">

    <?php include '../auth/headeru.php'; ?>

    <div class="max-w-6xl mx-auto py-10 px-4 flex-1 w-full">

<a href="books.php"
class="text-blue-600 font-semibold">
← Back to Books
</a>

<div class="bg-white rounded-xl shadow-lg mt-5 p-8 grid md:grid-cols-2 gap-8">

<div>

<img
src="../uploads/<?php echo $book['book_image'];?>"
class="w-full rounded-xl shadow">

</div>

<div>

<h1 class="text-4xl font-bold">

<?php echo $book['title'];?>

</h1>

<p class="text-gray-600 mt-3">

Author :
<b><?php echo $book['author'];?></b>

</p>

<p class="text-gray-600 mt-2">

Category :
<b><?php echo $book['category_name'];?></b>

</p>

<p class="text-blue-600 text-3xl font-bold mt-5">

<?php echo number_format($book['price']);?>

MMK

</p>

<p class="mt-3">

Stock :

<span class="text-green-600 font-bold">

<?php echo $book['stock'];?>

</span>

</p>

<p class="mt-5 text-gray-700 leading-7">

<?php echo nl2br($book['description']);?>

</p>

<div class="mt-6">

<p class="font-bold">

⭐ Average Rating :

<?php

if($avg['avg_rating']==""){

echo "No Rating";

}else{

echo $avg['avg_rating']." / 5";

}

?>

</p>

</div>

<form
action="addtocart.php"
method="POST"
class="mt-8">

<input
type="hidden"
name="book_id"
value="<?php echo $book['id'];?>">

<label class="font-semibold">

Quantity

</label>

<input
type="number"
name="quantity"
value="1"
min="1"
max="<?php echo $book['stock'];?>"
class="border rounded w-24 p-2 block mt-2">

<button
class="mt-6 bg-green-600 hover:bg-green-700 text-white px-8 py-3 rounded-lg">

🛒 Add to Cart

</button>

</form>

</div>

</div>

<!-- Reviews -->

<div class="bg-white rounded-xl shadow-lg mt-10 p-8">

<h2 class="text-2xl font-bold mb-6">

Customer Reviews

</h2>

<?php

if(mysqli_num_rows($reviews)>0){

while($r=mysqli_fetch_assoc($reviews)){

?>

<div class="border-b py-5">

<h4 class="font-bold">

<?php echo $r['name'];?>

</h4>

<p class="text-yellow-500">

<?php

for($i=1;$i<=5;$i++){

if($i<=$r['rating']){

echo "⭐";

}else{

echo "☆";

}

}

?>

</p>

<p class="mt-2 text-gray-700">

<?php echo $r['comment'];?>

</p>

</div>

<?php

}

}else{

?>

<p class="text-gray-500">

No reviews yet.

</p>

<?php

}

?>

</div>

</div>

    <?php include '../auth/footer.php'; ?>