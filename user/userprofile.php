<?php
session_start();
require_once "../config/db.php"; 

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch logged-in user details from the database
$query = mysqli_query($conn, "SELECT * FROM Users WHERE id = '$user_id'");
$user = mysqli_fetch_assoc($query);

if (isset($_POST['update_user'])) {
    $name = mysqli_real_escape_string($conn, $_POST['username']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    
    // Sanitize and validate numeric character sequence for phone number
    $phone_input = trim($_POST['phone'] ?? '');
    $phone = preg_replace('/[^0-9]/', '', $phone_input); // Keep only numeric digits
    $phone = mysqli_real_escape_string($conn, $phone);

    $address = mysqli_real_escape_string($conn, $_POST['address']); 
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $profile_image = $user['profile_image']; 
    
    $is_changed = false; // Track if any data actually changed
    $error_msg = "";

    // Validate if the input phone contains only integers
    if (!empty($phone_input) && $phone_input !== $phone) {
        $error_msg = "Phone number must contain only numbers.";
    }

    if (empty($error_msg)) {
        // Handle profile image file upload
        if (isset($_FILES['profile_image']['name']) && $_FILES['profile_image']['name'] != "") {
            $target_dir = "../uploads/profile/";
            if (!file_exists($target_dir)) { mkdir($target_dir, 0777, true); }
            
            $file_name = time() . "_" . basename($_FILES["profile_image"]["name"]);
            if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_dir . $file_name)) {
                $profile_image = $file_name;
                $is_changed = true;
                
                // Update navigation bar profile image inside session immediately
                $_SESSION['user_image'] = $profile_image;
            }
        }

        // Check if text fields have changed compared to current database values
        if ($name !== $user['name'] || $email !== $user['email'] || $phone !== ($user['phone'] ?? '') || $address !== ($user['address'] ?? '')) {
            $is_changed = true;
        }

        // Process update query based on password input and actual changes
        if (!empty($new_password)) {
            // Verify current password before allowing change
            if (password_verify($current_password, $user['password']) || $current_password === $user['password']) {
                $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
                $updateQuery = "UPDATE Users SET name='$name', email='$email', phone='$phone', address='$address', password='$hashed_password', profile_image='$profile_image' WHERE id='$user_id'";
                $is_changed = true; // Password update counts as a change
            } else {
                $error_msg = "Current password is incorrect!";
            }
        } else {
            // Update profile without changing password
            $updateQuery = "UPDATE Users SET name='$name', email='$email', phone='$phone', address='$address', profile_image='$profile_image' WHERE id='$user_id'";
        }

        // Only execute query and show success message if data has actually changed
        if (empty($error_msg)) {
            if ($is_changed) {
                if (mysqli_query($conn, $updateQuery)) {
                    // Update session image just to be fully sure database state matches session
                    $_SESSION['user_image'] = $profile_image;
                    
                    $_SESSION['success'] = "Profile updated successfully!";
                    header("Location: userprofile.php");
                    exit();
                }
            } else {
                // If nothing changed, just redirect back without success message
                header("Location: userprofile.php");
                exit();
            }
        }
    }

    if (!empty($error_msg)) {
        $_SESSION['error'] = $error_msg;
        header("Location: userprofile.php");
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

    <?php include '../auth/header.php'; ?>

    <main class="max-w-xl mx-auto px-4 py-10 flex-1 w-full">
        <h2 class="text-2xl font-bold mb-6">👤 My Account Profile</h2>

        <?php if(isset($_SESSION['success'])) { ?>
            <div class="bg-green-100 text-green-800 p-4 rounded-xl mb-4"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php } ?>

        <?php if(isset($_SESSION['error'])) { ?>
            <div class="bg-red-100 text-red-800 p-4 rounded-xl mb-4"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php } ?>

        <form action="userprofile.php" method="POST" enctype="multipart/form-data" class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 space-y-5 w-full">
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
                <input type="number" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="09XXXXXXXXX" class="w-full px-4 py-2 border rounded-xl outline-none focus:border-green-500 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Address</label>
                <textarea name="address" rows="3" placeholder="Enter your address..." class="w-full px-4 py-2 border rounded-xl outline-none focus:border-green-500 resize-none"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Current Password</label>
                <input type="password" name="current_password" value="" placeholder="Enter current password to verify" class="w-full px-4 py-2 border rounded-xl outline-none focus:border-green-500">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Update Password</label>
                <input type="password" name="new_password" placeholder="Enter new password" class="w-full px-4 py-2 border rounded-xl outline-none focus:border-green-500">
            </div>

            <button type="submit" name="update_user" class="w-full bg-green-600 text-white py-2.5 rounded-xl font-semibold hover:bg-green-700 transition">Update Profile</button>
        </form>
    </main>

    <?php include '../auth/footer.php'; ?>

</body>
</html>