<?php
session_start();
require_once "../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != "customer") {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Cart Items
$sql = "SELECT Cart_item.*, Books.title
        FROM Cart_item
        JOIN Books ON Cart_item.book_id=Books.id
        WHERE Cart_item.user_id=?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i",$user_id);
$stmt->execute();
$cart = $stmt->get_result();

if($cart->num_rows==0){
    header("Location: cart.php");
    exit();
}

$total = 0;

while($item=$cart->fetch_assoc()){
    $total += $item['totalprice'];
}

if(isset($_POST['place_order'])){

    $receiver = trim($_POST['receiver_name']);
    $phone    = trim($_POST['receiver_phone']);
    $address  = trim($_POST['address']);
    $city     = trim($_POST['city']);

    $order_no = "ORD".date("YmdHis").rand(100,999);

    // Orders
    $stmt = $conn->prepare("INSERT INTO Orders(user_id,total_amount,status,created_at,order_number)
    VALUES(?,?,'Pending',NOW(),?)");

    $stmt->bind_param("ids",$user_id,$total,$order_no);
    $stmt->execute();

    $order_id = $conn->insert_id;

    // Cart ပြန်ယူ
    $cart2 = $conn->prepare("SELECT * FROM Cart_item WHERE user_id=?");
    $cart2->bind_param("i",$user_id);
    $cart2->execute();

    $items = $cart2->get_result();

    while($row=$items->fetch_assoc()){

        $insert = $conn->prepare("INSERT INTO Order_item(order_id,book_id,quantity,price)
        VALUES(?,?,?,?)");

        $insert->bind_param(
        "iiid",
        $order_id,
        $row['book_id'],
        $row['quantity'],
        $row['unit_price']);

        $insert->execute();
    }

    // Payment placeholder
    $pay = $conn->prepare("INSERT INTO Payment(order_id,payment_method_id,amount,status)
    VALUES(?,1,?,'unpaid')");

    $pay->bind_param("id",$order_id,$total);
    $pay->execute();

    $payment_id = $conn->insert_id;

    // Delivery
    $delivery = $conn->prepare("INSERT INTO Delivery
    (payment_id,receiver_name,receiver_phone,address_details,city,delivery_status)
    VALUES(?,?,?,?,?,'Pending')");

    $delivery->bind_param(
    "issss",
    $payment_id,
    $receiver,
    $phone,
    $address,
    $city);

    $delivery->execute();

    // Cart Clear
    $delete = $conn->prepare("DELETE FROM Cart_item WHERE user_id=?");
    $delete->bind_param("i",$user_id);
    $delete->execute();

    header("Location: payment.php?order=".$order_id);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Online Book Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen flex flex-col font-sans text-slate-800">

    <?php include '../auth/headeru.php'; ?>

    <div class="max-w-5xl mx-auto mt-10 px-4 flex-1 w-full">

<div class="bg-white rounded-xl shadow p-8">

<h2 class="text-3xl font-bold mb-6">

Checkout

</h2>

<form method="POST">

<div class="grid md:grid-cols-2 gap-5">

<div>

<label>Receiver Name</label>

<input
type="text"
name="receiver_name"
required
class="w-full border rounded p-3 mt-2">

</div>

<div>

<label>Phone</label>

<input
type="text"
name="receiver_phone"
required
class="w-full border rounded p-3 mt-2">

</div>

<div class="md:col-span-2">

<label>Address</label>

<textarea
name="address"
required
class="w-full border rounded p-3 mt-2"></textarea>

</div>

<div>

<label>City</label>

<input
type="text"
name="city"
required
class="w-full border rounded p-3 mt-2">

</div>

</div>

<div class="mt-8">

<h3 class="text-2xl font-bold">

Grand Total :

<span class="text-blue-600">

<?php echo number_format($total); ?>

MMK

</span>

</h3>

</div>

<div class="mt-8">

<button
name="place_order"
class="bg-green-600 hover:bg-green-700 text-white px-8 py-3 rounded">

Place Order

</button>

</div>

</form>

</div>

</div>

    <?php include '../auth/footer.php'; ?>