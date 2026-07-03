<?php
session_start();
require_once "../config/db.php";

// Admin Login Check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != "admin") {
    header("Location: ../auth/login.php");
    exit();
}

$message = "";
$error = "";

// Add Payment Method
if (isset($_POST['save'])) {

    $method_name    = trim($_POST['method_name']);
    $account_number = trim($_POST['account_number']);
    $account_holder = trim($_POST['account_holder']);
    $is_active      = $_POST['is_active'];
    $description    = trim($_POST['description']);

    if (
        empty($method_name) ||
        empty($account_number) ||
        empty($account_holder)
    ) {

        $error = "Please fill all required fields.";

    } else {

        $stmt = $conn->prepare("INSERT INTO payment_method(method_name,account_number,account_holder,is_active,description)
        VALUES(?,?,?,?,?)");

        $stmt->bind_param(
            "sssis",
            $method_name,
            $account_number,
            $account_holder,
            $is_active,
            $description
        );

        if($stmt->execute()){

            $message="Payment Method Added Successfully.";

        }else{

            $error="Insert Failed.";

        }

    }

}
?>

<!DOCTYPE html>
<html>

<head>

<meta charset="UTF-8">

<title>Payment Method</title>

<script src="https://cdn.tailwindcss.com"></script>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

</head>

<body class="bg-gray-100">

<div class="max-w-2xl mx-auto mt-10">

<div class="bg-white shadow-lg rounded-xl p-8">

<h2 class="text-3xl font-bold mb-6 text-center">

Add Payment Method

</h2>

<?php if($message!=""){ ?>

<div class="bg-green-100 text-green-700 p-3 rounded mb-4">

<?php echo $message; ?>

</div>

<?php } ?>

<?php if($error!=""){ ?>

<div class="bg-red-100 text-red-700 p-3 rounded mb-4">

<?php echo $error; ?>

</div>

<?php } ?>

<form method="POST">

<div class="mb-4">

<label class="font-semibold">

Payment Method

</label>

<input
type="text"
name="method_name"
class="w-full border rounded-lg p-3 mt-2"
placeholder="KBZ Pay / Wave Pay / CB Pay"
required>

</div>

<div class="mb-4">

<label class="font-semibold">

Account Number

</label>

<input
type="text"
name="account_number"
class="w-full border rounded-lg p-3 mt-2"
placeholder="09xxxxxxxxx"
required>

</div>

<div class="mb-4">

<label class="font-semibold">

Account Holder

</label>

<input
type="text"
name="account_holder"
class="w-full border rounded-lg p-3 mt-2"
placeholder="Online Book Shop"
required>

</div>

<div class="mb-4">

<label class="font-semibold">

Status

</label>

<select
name="is_active"
class="w-full border rounded-lg p-3 mt-2">

<option value="1">

Active

</option>

<option value="0">

Inactive

</option>

</select>

</div>

<div class="mb-4">

<label class="font-semibold">

Description

</label>

<textarea
name="description"
rows="4"
class="w-full border rounded-lg p-3 mt-2"
placeholder="Description"></textarea>

</div>

<button
type="submit"
name="save"
class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-lg font-bold">

<i class="fa fa-save"></i>

Save Payment Method

</button>

</form>

</div>

</div>

</body>

</html>