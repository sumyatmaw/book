<?php
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
            if (!file_exists($target_dir)) { mkdir($target_dir, 0777, true); }
            
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
    <title>My Profile & Orders - Online Book Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-300 min-h-screen flex flex-col font-sans text-slate-800">

    <?php include '../auth/header.php'; ?>

    <main class="max-w-6xl mx-auto px-4 py-8 flex-1 w-full space-y-10">

        <!-- SUCCESS / ERROR FLASH ALERTS -->
        <?php if(isset($_SESSION['success'])) { ?>
            <div class="bg-emerald-100 border border-emerald-300 text-emerald-800 px-5 py-3 rounded-xl font-medium shadow-sm flex items-center gap-2">
                <i class="fa-solid fa-circle-check text-emerald-600"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php } ?>

        <?php if(isset($_SESSION['error'])) { ?>
            <div class="bg-rose-100 border border-rose-300 text-rose-800 px-5 py-3 rounded-xl font-medium shadow-sm flex items-center gap-2">
                <i class="fa-solid fa-triangle-exclamation text-rose-600"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php } ?>

        <!-- GRID SECTION: PROFILE FORM & MY ORDERS -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

            <!-- LEFT COLUMN: EDIT PROFILE FORM -->
            <div class="lg:col-span-5 bg-white p-6 rounded-2xl shadow-sm border border-gray-100 w-full">

                <form action="userprofile.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                    <!-- Profile Picture Section with Small Selected Image Preview & Hidden Filename -->
                    <div class="flex flex-col items-center">
                        <div class="relative group">
                            <img id="user_preview" 
                                 src="<?= htmlspecialchars($user_profile_src); ?>" 
                                 onerror="this.onerror=null; this.src='../uploads/profile/<?= htmlspecialchars(basename($raw_profile_img)); ?>'; if(this.src.includes('undefined')||this.src.endsWith('/')) this.src='<?= $default_avatar; ?>';"
                                 class="w-24 h-24 rounded-full object-cover border-4 border-green-500 shadow-sm bg-gray-100">
                        </div>

                        <!-- Custom File Button showing small image without filename text -->
                        <div class="mt-3 flex items-center gap-2">
                            <label class="relative cursor-pointer bg-green-600 text-white text-xs px-3 py-1.5 rounded-lg hover:bg-green-700 font-semibold transition flex items-center gap-1.5 shadow-xs">
                                <i class="fa-solid fa-camera"></i> Upload Photo
                                <input type="file" name="profile_image" id="profile_image_input" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" onchange="previewProfileImage(event)">
                            </label>

                            <!-- Selected Small Image Preview Container -->
                            <img id="small_preview" 
                                 src="" 
                                 alt="Selected Preview" 
                                 class="w-8 h-8 rounded-full object-cover border-2 border-green-500 hidden shadow-xs">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Username</label>
                        <input type="text" name="username" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required class="w-full px-3.5 py-2 text-sm border rounded-xl outline-none focus:border-green-500 transition">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required class="w-full px-3.5 py-2 text-sm border rounded-xl outline-none focus:border-green-500 transition">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Phone</label>
                        <input type="number" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="09XXXXXXXXX" class="w-full px-3.5 py-2 text-sm border rounded-xl outline-none focus:border-green-500 transition [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Address</label>
                        <textarea name="address" rows="2" placeholder="Enter your address..." class="w-full px-3.5 py-2 text-sm border rounded-xl outline-none focus:border-green-500 resize-none transition"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                    </div>
                    <div class="pt-2 border-t border-gray-100">
                        <label class="block text-xs font-bold text-gray-700 mb-1">Current Password (Required for new password)</label>
                        <input type="password" name="current_password" value="" placeholder="Enter current password" class="w-full px-3.5 py-2 text-sm border rounded-xl outline-none focus:border-green-500 transition">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">New Password</label>
                        <input type="password" name="new_password" placeholder="Enter new password" class="w-full px-3.5 py-2 text-sm border rounded-xl outline-none focus:border-green-500 transition">
                    </div>

                    <button type="submit" name="update_user" class="w-full bg-green-600 text-white py-2.5 rounded-xl font-bold text-sm hover:bg-green-700 transition shadow-sm mt-3 flex items-center justify-center gap-1.5 cursor-pointer">
                        <i class="fa-solid fa-floppy-disk"></i> Save Profile
                    </button>
                </form>
            </div>

            <!-- RIGHT COLUMN: MY ORDERS HISTORY -->
            <div class="lg:col-span-7 space-y-4 w-full">
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100">
                    <h2 class="text-xl font-black text-slate-900 flex items-center justify-between">
                        <span><i class="fa-solid fa-box text-amber-500 mr-2"></i> My Order History</span>
                        <span class="text-xs font-bold bg-amber-100 text-amber-800 px-3 py-1 rounded-full">
                            <?= $total_orders; ?> Orders
                        </span>
                    </h2>
                    <p class="text-xs text-gray-400 mt-1">ဝယ်ယူခဲ့သော စာအုပ်များနှင့် အော်ဒါအသေးစိတ် မှတ်တမ်းများ</p>
                </div>

                <?php if ($orders_result && mysqli_num_rows($orders_result) > 0): ?>
                    <div class="space-y-4">
                        <?php while ($order = mysqli_fetch_assoc($orders_result)):
                            $order_id = $order['id'];
                            $order_status = strtolower($order['status'] ?? 'pending');
                            $payment_status = strtolower($order['payment_status'] ?? 'pending');

                            $badge_class = "bg-amber-50 text-amber-700 border-amber-200";
                            if ($order_status === 'completed' || $order_status === 'delivered') {
                                $badge_class = "bg-emerald-50 text-emerald-700 border-emerald-200";
                            } elseif ($order_status === 'cancelled') {
                                $badge_class = "bg-rose-50 text-rose-700 border-rose-200";
                            }

                            // Query matching database structure for order items
                            $items_query = "SELECT order_item.*, books.* 
                                            FROM order_item 
                                            JOIN books ON order_item.book_id = books.id 
                                            WHERE order_item.order_id = '$order_id'";
                            $items_result = mysqli_query($conn, $items_query);
                        ?>

                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                            <!-- Card Header -->
                            <div class="px-5 py-3.5 bg-slate-50 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <span class="font-bold text-slate-900 text-sm font-mono">
                                        #<?= htmlspecialchars($order['order_number'] ?? $order['id']); ?>
                                    </span>
                                    <span class="text-[11px] text-gray-400 ml-2">
                                        <i class="fa-regular fa-clock mr-1"></i>
                                        <?= isset($order['created_at']) ? date('d M Y, h:i A', strtotime($order['created_at'])) : ''; ?>
                                    </span>
                                </div>
                                <span class="inline-block px-2.5 py-0.5 rounded-md text-[11px] font-bold border capitalize <?= $badge_class; ?>">
                                    <?= $order_status; ?>
                                </span>
                            </div>

                            <!-- Purchased Books Itemized List -->
                            <div class="p-4 divide-y divide-gray-100">
                                <?php if ($items_result && mysqli_num_rows($items_result) > 0): ?>
                                    <?php while ($item = mysqli_fetch_assoc($items_result)): 
                                        $raw_cover = $item['cover_image'] ?? $item['image'] ?? $item['book_image'] ?? '';
                                        $book_title = $item['title'] ?? $item['name'] ?? $item['book_name'] ?? 'Book';

                                        if (empty($raw_cover)) {
                                            $img_src = '';
                                        } elseif (strpos($raw_cover, 'http') === 0) {
                                            $img_src = $raw_cover;
                                        } elseif (strpos($raw_cover, 'assets/') === 0) {
                                            $img_src = '../' . $raw_cover;
                                        } else {
                                            $img_src = '../assets/' . ltrim($raw_cover, '/');
                                        }
                                    ?>
                                        <div class="py-2.5 first:pt-0 last:pb-0 flex items-center justify-between gap-3">
                                            <div class="flex items-center gap-3 min-w-0">
                                                <div class="w-10 h-14 bg-slate-100 rounded-md overflow-hidden shrink-0 border border-slate-200 flex items-center justify-center">
                                                    <?php if (!empty($img_src)): ?>
                                                        <img src="<?= htmlspecialchars($img_src); ?>" 
                                                             class="w-full h-full object-cover" 
                                                             onerror="this.onerror=null; this.src='../uploads/<?= htmlspecialchars(basename($raw_cover)); ?>';">
                                                    <?php else: ?>
                                                        <i class="fa-solid fa-book text-slate-300"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="min-w-0">
                                                    <h4 class="text-xs font-bold text-slate-800 truncate">
                                                        <?= htmlspecialchars($book_title); ?>
                                                    </h4>
                                                    <p class="text-[11px] text-gray-500 mt-0.5">
                                                        <?= number_format($item['price']); ?> ကျပ် × <?= $item['quantity']; ?> အုပ်
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="text-right shrink-0">
                                                <p class="text-xs font-bold text-slate-900">
                                                    <?= number_format($item['price'] * $item['quantity']); ?> ကျပ်
                                                </p>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </div>

                            <!-- Footer Price Total & Payment Info -->
                            <div class="px-5 py-3 bg-slate-50/50 border-t border-gray-100 flex items-center justify-between text-xs">
                                <span class="text-gray-500">
                                    Payment: <strong class="text-slate-700 capitalize"><?= htmlspecialchars($payment_status); ?></strong>
                                </span>
                                <div>
                                    <span class="text-gray-500 mr-1">စုစုပေါင်း:</span>
                                    <span class="text-sm font-black text-green-600">
                                        <?= number_format($order['total_amount'] ?? $order['total_price'] ?? 0); ?> ကျပ်
                                    </span>
                                </div>
                            </div>
                        </div>

                        <?php endwhile; ?>
                    </div>

                    <!-- Pagination Navigation Controls -->
                    <?php if ($total_pages > 1): ?>
                        <div class="flex items-center justify-center gap-1 mt-6 pt-4 border-t border-slate-200/60">
                            <!-- Previous Page Button -->
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1; ?>" class="px-3 py-1.5 bg-white border border-gray-200 text-slate-700 rounded-lg text-xs font-bold hover:bg-gray-50 transition flex items-center gap-1">
                                    <i class="fa-solid fa-chevron-left text-[10px]"></i> Prev
                                </a>
                            <?php else: ?>
                                <span class="px-3 py-1.5 bg-gray-100 border border-gray-200 text-gray-400 rounded-lg text-xs font-bold cursor-not-allowed flex items-center gap-1">
                                    <i class="fa-solid fa-chevron-left text-[10px]"></i> Prev
                                </span>
                            <?php endif; ?>

                            <!-- Page Numbers Links -->
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <?php if ($i == $page): ?>
                                    <span class="px-3 py-1.5 bg-green-600 text-white font-bold rounded-lg text-xs shadow-xs">
                                        <?= $i; ?>
                                    </span>
                                <?php else: ?>
                                    <a href="?page=<?= $i; ?>" class="px-3 py-1.5 bg-white border border-gray-200 text-slate-700 font-bold rounded-lg text-xs hover:bg-gray-50 transition">
                                        <?= $i; ?>
                                    </a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <!-- Next Page Button -->
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?= $page + 1; ?>" class="px-3 py-1.5 bg-white border border-gray-200 text-slate-700 rounded-lg text-xs font-bold hover:bg-gray-50 transition flex items-center gap-1">
                                    Next <i class="fa-solid fa-chevron-right text-[10px]"></i>
                                </a>
                            <?php else: ?>
                                <span class="px-3 py-1.5 bg-gray-100 border border-gray-200 text-gray-400 rounded-lg text-xs font-bold cursor-not-allowed flex items-center gap-1">
                                    Next <i class="fa-solid fa-chevron-right text-[10px]"></i>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="bg-white rounded-2xl p-8 text-center border border-gray-100 shadow-sm">
                        <i class="fa-solid fa-box-open text-3xl text-gray-300 mb-2"></i>
                        <h3 class="text-sm font-bold text-slate-700">ဝယ်ယူထားသော စာအုပ်မှတ်တမ်း မရှိသေးပါ</h3>
                        <p class="text-xs text-gray-400 mt-1">စာအုပ်များ စတင်ဝယ်ယူပြီးမှ ဤနေရာတွင် ပြသမည် ဖြစ်သည်။</p>
                    </div>
                <?php endif; ?>
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
                smallPreview.src = objectUrl;
                smallPreview.classList.remove('hidden');
            }
        }
    </script>

</body>
</html>