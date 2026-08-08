<?php
/**
 * User Profile & Orders Management Page
 * Handles user profile updates, profile image uploads, password verification,
 * and paginated order history display.
 */

session_start();
require_once "../config/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);

// Fetch logged-in user details from database
$query = mysqli_query($conn, "SELECT * FROM users WHERE id = '$user_id'");
$user = mysqli_fetch_assoc($query);

// ==========================================
// HANDLE PROFILE UPDATE FORM SUBMISSION
// ==========================================
if (isset($_POST['update_user'])) {
    $name = mysqli_real_escape_string($conn, $_POST['username']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);

    // Sanitize and validate numeric character sequence for phone number
    $phone_input = trim($_POST['phone'] ?? '');
    $phone = preg_replace('/[^0-9]/', '', $phone_input);
    $phone = mysqli_real_escape_string($conn, $phone);

    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $profile_image = $user['profile_image'] ?? '';

    $is_changed = false;
    $error_msg = "";

    if (!empty($phone_input) && $phone_input !== $phone) {
        $error_msg = "Phone number must contain only numbers.";
    }

    if (empty($error_msg)) {
        // Handle profile image file upload
        if (isset($_FILES['profile_image']['name']) && $_FILES['profile_image']['name'] != "") {
            $target_dir = "../assets/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }

            $file_name = time() . "_" . basename($_FILES["profile_image"]["name"]);
            if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_dir . $file_name)) {
                $profile_image = $file_name;
                $is_changed = true;
                $_SESSION['user_image'] = $profile_image;
            }
        }

        if ($name !== $user['name'] || $email !== $user['email'] || $phone !== ($user['phone'] ?? '') || $address !== ($user['address'] ?? '')) {
            $is_changed = true;
        }

        if (!empty($new_password)) {
            if (password_verify($current_password, $user['password']) || $current_password === $user['password']) {
                $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
                $updateQuery = "UPDATE users SET name='$name', email='$email', phone='$phone', address='$address', password='$hashed_password', profile_image='$profile_image' WHERE id='$user_id'";
                $is_changed = true;
            } else {
                $error_msg = "Current password is incorrect!";
            }
        } else {
            $updateQuery = "UPDATE users SET name='$name', email='$email', phone='$phone', address='$address', profile_image='$profile_image' WHERE id='$user_id'";
        }

        if (empty($error_msg)) {
            if ($is_changed) {
                if (mysqli_query($conn, $updateQuery)) {
                    $_SESSION['user_image'] = $profile_image;
                    $_SESSION['success'] = "Profile updated successfully!";
                    header("Location: userprofile.php");
                    exit();
                }
            } else {
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

// Profile Image Dynamic Path Formatting
$raw_profile_img = $user['profile_image'] ?? '';
$default_avatar = 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png';

if (empty($raw_profile_img)) {
    $user_profile_src = $default_avatar;
} elseif (strpos($raw_profile_img, 'http') === 0) {
    $user_profile_src = $raw_profile_img;
} elseif (strpos($raw_profile_img, 'assets/') === 0) {
    $user_profile_src = '../' . $raw_profile_img;
} else {
    $user_profile_src = '../assets/' . ltrim($raw_profile_img, '/');
}

// ==========================================
// PAGINATION SETUP FOR ORDERS HISTORY
// ==========================================
$limit = 5; // Orders per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Count total orders for logged-in user
$total_orders_query = "SELECT COUNT(*) AS total FROM orders WHERE user_id = '$user_id'";
$total_orders_res = mysqli_query($conn, $total_orders_query);
$total_orders = mysqli_fetch_assoc($total_orders_res)['total'] ?? 0;
$total_pages = ceil($total_orders / $limit);

// Fetch paginated orders history with payment status
$orders_query = "SELECT orders.*, 
                        payment.status AS payment_status 
                 FROM orders 
                 LEFT JOIN payment ON orders.id = payment.order_id 
                 WHERE orders.user_id = '$user_id' 
                 ORDER BY orders.id DESC 
                 LIMIT $limit OFFSET $offset";
$orders_result = mysqli_query($conn, $orders_query);
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

<body class="bg-gray-300 min-h-screen flex flex-col font-sans text-slate-800 antialiased">

    <?php include '../auth/header.php'; ?>

    <main class="max-w-4xl mx-auto px-4 py-6 sm:py-8 flex-1 w-full space-y-4">

        <!-- SUCCESS / ERROR FLASH ALERTS -->
        <?php if (isset($_SESSION['success'])) { ?>
            <div class="bg-emerald-100 border border-emerald-300 text-emerald-800 px-3.5 py-2.5 rounded-xl text-xs sm:text-sm font-medium shadow-xs flex items-center gap-2 max-w-sm mx-auto">
                <i class="fa-solid fa-circle-check text-emerald-600"></i> <?php echo $_SESSION['success'];
                unset($_SESSION['success']); ?>
            </div>
        <?php } ?>

        <?php if (isset($_SESSION['error'])) { ?>
            <div class="bg-rose-100 border border-rose-300 text-rose-800 px-3.5 py-2.5 rounded-xl text-xs sm:text-sm font-medium shadow-xs flex items-center gap-2 max-w-sm mx-auto">
                <i class="fa-solid fa-triangle-exclamation text-rose-600"></i> <?php echo $_SESSION['error'];
                unset($_SESSION['error']); ?>
            </div>
        <?php } ?>

        <!-- CENTERED COMPACT & RESPONSIVE EDIT PROFILE CONTAINER -->
        <div class="flex justify-center items-center w-full">
            <div class="bg-white p-5 sm:p-6 rounded-2xl shadow-sm border border-gray-200 w-full max-w-md">

                <h2 class="text-base sm:text-lg font-bold text-slate-800 mb-4 text-center flex items-center justify-center gap-2">
                    <i class="fa-solid fa-user-gear text-blue-500"></i> Edit Profile
                </h2>

                <form action="userprofile.php" method="POST" enctype="multipart/form-data" class="space-y-3">
                    
                    <!-- Profile Picture Section with Selected Image Preview -->
                    <div class="flex flex-col items-center">
                        <div class="relative group">
                            <img id="user_preview"
                                src="<?= htmlspecialchars($user_profile_src); ?>"
                                onerror="this.onerror=null; this.src='../uploads/profile/<?= htmlspecialchars(basename($raw_profile_img)); ?>'; if(this.src.includes('undefined')||this.src.endsWith('/')) this.src='<?= $default_avatar; ?>';"
                                class="w-20 h-20 sm:w-24 sm:h-24 rounded-full object-cover border-2 border-blue-400 shadow-xs bg-gray-50">
                        </div>

                        <!-- Custom File Upload Button -->
                        <div class="mt-2.5 flex items-center gap-2">
                            <label class="relative cursor-pointer bg-blue-500 text-white text-xs px-3 py-1.5 rounded-lg hover:bg-blue-600 font-medium transition flex items-center gap-1.5 shadow-xs">
                                <i class="fa-solid fa-camera"></i> Upload Photo
                                <input type="file" name="profile_image" id="profile_image_input" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" onchange="previewProfileImage(event)">
                            </label>

                            <!-- Selected Small Image Preview Container -->
                            <img id="small_preview"
                                src=""
                                alt="Selected Preview"
                                class="w-7 h-7 rounded-full object-cover border border-blue-400 hidden shadow-xs">
                        </div>
                    </div>

                    <!-- Compact Form Fields -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Username</label>
                        <input type="text" name="username" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required class="w-full px-3 py-1.5 text-xs sm:text-sm border border-gray-300 rounded-lg outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required class="w-full px-3 py-1.5 text-xs sm:text-sm border border-gray-300 rounded-lg outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Phone</label>
                        <input type="number" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="09XXXXXXXXX" class="w-full px-3 py-1.5 text-xs sm:text-sm border border-gray-300 rounded-lg outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Address</label>
                        <textarea name="address" rows="2" placeholder="Enter your address..." class="w-full px-3 py-1.5 text-xs sm:text-sm border border-gray-300 rounded-lg outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 resize-none transition"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                    </div>
                    
                    <!-- Password Section -->
                    <div class="pt-2 border-t border-gray-100 space-y-2">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Current Password (Required for new password)</label>
                            <input type="password" name="current_password" value="" placeholder="Enter current password" class="w-full px-3 py-1.5 text-xs sm:text-sm border border-gray-300 rounded-lg outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">New Password</label>
                            <input type="password" name="new_password" placeholder="Enter new password" class="w-full px-3 py-1.5 text-xs sm:text-sm border border-gray-300 rounded-lg outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition">
                        </div>
                    </div>

                    <button type="submit" name="update_user" class="w-full bg-blue-600 text-white py-2 rounded-lg font-semibold text-xs sm:text-sm hover:bg-blue-700 transition shadow-xs mt-2 flex items-center justify-center gap-1.5 cursor-pointer">
                        <i class="fa-solid fa-floppy-disk"></i> Save Profile
                    </button>
                </form>
            </div>
        </div>

    </main>

    <?php include '../auth/footer.php'; ?>

    <!-- Image Selection Preview JavaScript -->
    <script>
        function previewProfileImage(event) {
            const input = event.target;
            const mainPreview = document.getElementById('user_preview');
            const smallPreview = document.getElementById('small_preview');

            if (input.files && input.files[0]) {
                const objectUrl = URL.createObjectURL(input.files[0]);

                // Update main profile picture avatar
                mainPreview.src = objectUrl;

                // Update small preview image beside Upload button
                // smallPreview.src = objectUrl;
                // smallPreview.classList.remove('hidden');
            }
        }
    </script>

</body>

</html>