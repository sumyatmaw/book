<?php
session_start();
require_once "../config/db.php"; 

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// လက်ရှိ User Data ယူခြင်း
$query = mysqli_query($conn, "SELECT * FROM Users WHERE id = '$user_id'");
$user = mysqli_fetch_assoc($query);

if (isset($_POST['update_user'])) {
    $name = mysqli_real_escape_string($conn, $_POST['username']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $password = $_POST['password'];
    $profile_image = $user['profile_image']; 

    // ပုံတင်ခြင်းအပိုင်း
    if (isset($_FILES['profile_image']['name']) && $_FILES['profile_image']['name'] != "") {
        $target_dir = "../uploads/profile/";
        if (!file_exists($target_dir)) { mkdir($target_dir, 0777, true); }
        
        $file_name = time() . "_" . basename($_FILES["profile_image"]["name"]);
        if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_dir . $file_name)) {
            $profile_image = $file_name;
        }
    }

    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        $updateQuery = "UPDATE Users SET name='$name', email='$email', phone='$phone', password='$hashed_password', profile_image='$profile_image' WHERE id='$user_id'";
    } else {
        $updateQuery = "UPDATE Users SET name='$name', email='$email', phone='$phone', profile_image='$profile_image' WHERE id='$user_id'";
    }

    if (mysqli_query($conn, $updateQuery)) {
        $_SESSION['success'] = "Profile updated successfully!";
        header("Location: profile.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Online Book Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen flex flex-col font-sans text-slate-800">

    <?php include '../auth/headeru.php'; ?>

    <main class="max-w-xl mx-auto px-4 py-10 flex-1 w-full">
        <h2 class="text-2xl font-bold mb-6">👤 My Account Profile</h2>

        <?php if(isset($_SESSION['success'])) { ?>
            <div class="bg-green-100 text-green-800 p-4 rounded-xl mb-4"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php } ?>

        <form action="userprofile.php" method="POST" enctype="multipart/form-data" class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 space-y-5">
            <div class="flex flex-col items-center">
                <img id="user_preview" src="<?php echo !empty($user['profile_image']) ? '../uploads/profile/'.$user['profile_image'] : 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png'; ?>" class="w-24 h-24 rounded-full object-cover border-4 border-green-500 shadow-sm">
                <label class="mt-3 cursor-pointer bg-green-600 text-white text-xs px-3 py-1.5 rounded-lg hover:bg-green-700">
                    Upload Photo
                    <input type="file" name="profile_image" class="hidden" onchange="document.getElementById('user_preview').src = window.URL.createObjectURL(this.files[0])">
                </label>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Username</label>
                <input type="text" name="username" value="<?php echo htmlspecialchars($user['name']); ?>" required class="w-full px-4 py-2 border rounded-xl outline-none focus:border-green-500">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Email</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required class="w-full px-4 py-2 border rounded-xl outline-none focus:border-green-500">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Phone</label>
                <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" class="w-full px-4 py-2 border rounded-xl outline-none focus:border-green-500">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">New Password (Optional)</label>
                <input type="password" name="password" placeholder="Leave blank to keep current" class="w-full px-4 py-2 border rounded-xl outline-none focus:border-green-500">
            </div>

            <button type="submit" name="update_user" class="w-full bg-green-600 text-white py-2.5 rounded-xl font-semibold hover:bg-green-700 transition">Update Profile</button>
        </form>
    </main>
    <?php include '../auth/footer.php'; ?>