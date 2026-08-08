<?php
/**
 * Online Book Shop — User Profile & Order History Page
 * Manages customer account profile updates, password modifications, 
 * profile picture uploads, and paginated order history display.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/db.php'; 

// Require authenticated user session
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);

// Fetch active user details using prepared statements
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    header("Location: ../auth/login.php");
    exit();
}

// ==========================================
// HANDLE PROFILE UPDATE FORM SUBMISSION
// ==========================================
if (isset($_POST['update_user'])) {
    $name = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    // Sanitize and validate numeric phone input sequence
    $phone_input = trim($_POST['phone'] ?? '');
    $phone = preg_replace('/[^0-9]/', '', $phone_input);

    $address = trim($_POST['address'] ?? ''); 
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $profile_image = $user['profile_image'] ?? ''; 
    
    $is_changed = false;
    $error_msg = "";

    // Validate numeric character rule for phone number
    if (!empty($phone_input) && $phone_input !== $phone) {
        $error_msg = "Phone number must contain only numbers.";
    }

    if (empty($error_msg)) {
        // Handle profile avatar file upload
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

        // Check if any textual profile attribute changed
        if ($name !== $user['name'] || $email !== $user['email'] || $phone !== ($user['phone'] ?? '') || $address !== ($user['address'] ?? '')) {
            $is_changed = true;
        }

        // Handle password alteration verification
        $password_to_save = $user['password'];
        if (!empty($new_password)) {
            if (password_verify($current_password, $user['password']) || $current_password === $user['password']) {
                $password_to_save = password_hash($new_password, PASSWORD_BCRYPT);
                $is_changed = true;
            } else {
                $error_msg = "Current password is incorrect!";
            }
        }

        // Execute SQL Update query
        if (empty($error_msg)) {
            if ($is_changed) {
                $upd_stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, address = ?, password = ?, profile_image = ? WHERE id = ?");
                $upd_stmt->bind_param("ssssssi", $name, $email, $phone, $address, $password_to_save, $profile_image, $user_id);
                
                if ($upd_stmt->execute()) {
                    $_SESSION['user_image'] = $profile_image;
                    $_SESSION['success'] = "Profile updated successfully!";
                    $upd_stmt->close();
                    header("Location: userprofile.php");
                    exit();
                }
                $upd_stmt->close();
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

// Format dynamic profile image source path
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
$limit = 5; // Total orders listed per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Count aggregate total orders for logged-in customer
$count_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM orders WHERE user_id = ?");
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$total_orders = $count_stmt->get_result()->fetch_assoc()['total'] ?? 0;
$count_stmt->close();

$total_pages = max(1, ceil($total_orders / $limit));
if ($page > $total_pages) $page = $total_pages;

// Fetch paginated customer orders along with associated payment status and calculated book-only subtotal (excluding delivery charges)
$orders_stmt = $conn->prepare("SELECT orders.*, payment.status AS payment_status,
                               (SELECT COALESCE(SUM(order_item.price * order_item.quantity), 0) 
                                FROM order_item WHERE order_item.order_id = orders.id) AS books_subtotal 
                               FROM orders 
                               LEFT JOIN payment ON orders.id = payment.order_id 
                               WHERE orders.user_id = ? 
                               ORDER BY orders.id DESC 
                               LIMIT ? OFFSET ?");
$orders_stmt->bind_param("iii", $user_id, $limit, $offset);
$orders_stmt->execute();
$orders_result = $orders_stmt->get_result();
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
<body class="bg-gray-300 min-h-screen flex flex-col font-sans text-slate-800 antialiased">

    <?php include __DIR__ . '/../auth/header.php'; ?>

    <main class="max-w-6xl mx-auto px-4 sm:px-6 py-6 sm:py-10 flex-1 w-full space-y-6 sm:space-y-8">

        <!-- SUCCESS / ERROR FLASH ALERTS -->
        <?php if(isset($_SESSION['success'])) { ?>
            <div class="bg-emerald-100 border border-emerald-300 text-emerald-800 px-4 sm:px-5 py-3 rounded-xl font-medium shadow-sm flex items-center gap-2 text-xs sm:text-sm">
                <i class="fa-solid fa-circle-check text-emerald-600 shrink-0"></i> <span><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></span>
            </div>
        <?php } ?>

        <?php if(isset($_SESSION['error'])) { ?>
            <div class="bg-rose-100 border border-rose-300 text-rose-800 px-4 sm:px-5 py-3 rounded-xl font-medium shadow-sm flex items-center gap-2 text-xs sm:text-sm">
                <i class="fa-solid fa-triangle-exclamation text-rose-600 shrink-0"></i> <span><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></span>
            </div>
        <?php } ?>

        <!-- GRID SECTION: PROFILE FORM & MY ORDERS HISTORY -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 sm:gap-8 items-start">

            <!-- LEFT COLUMN: EDIT PROFILE FORM -->
            <div class="lg:col-span-5 bg-white p-4 sm:p-6 rounded-2xl shadow-sm border border-gray-100 w-full">

                <form action="userprofile.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                    <!-- Profile Picture Section with Image Preview & Custom Button -->
                    <div class="flex flex-col items-center">
                        <div class="relative group">
                            <img id="user_preview" 
                                 src="<?= htmlspecialchars($user_profile_src); ?>" 
                                 onerror="this.onerror=null; this.src='../uploads/profile/<?= htmlspecialchars(basename($raw_profile_img)); ?>'; if(this.src.includes('undefined')||this.src.endsWith('/')) this.src='<?= $default_avatar; ?>';"
                                 class="w-20 h-20 sm:w-24 sm:h-24 rounded-full object-cover border-4 border-green-500 shadow-sm bg-gray-100">
                        </div>

                        <!-- Custom Image Selection Trigger -->
                        <div class="mt-3 flex items-center gap-2">
                            <label class="relative cursor-pointer bg-green-600 text-white text-xs px-3 py-1.5 rounded-lg hover:bg-green-700 font-semibold transition flex items-center gap-1.5 shadow-sm">
                                <i class="fa-solid fa-camera"></i> Upload Photo
                                <input type="file" name="profile_image" id="profile_image_input" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" onchange="previewProfileImage(event)">
                            </label>

                            <!-- Dynamic Thumbnail Preview -->
                            <img id="small_preview" 
                                 src="" 
                                 alt="Selected Preview" 
                                 class="w-8 h-8 rounded-full object-cover border-2 border-green-500 hidden shadow-sm">
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

                    <button type="submit" name="update_user" class="w-full bg-blue-500 text-white py-2.5 rounded-xl font-bold text-sm hover:bg-blue-600 transition shadow-sm mt-3 flex items-center justify-center gap-1.5 cursor-pointer">
                        <i class="fa-solid fa-floppy-disk"></i> Save Profile
                    </button>
                </form>
            </div>

            <!-- RIGHT COLUMN: MY ORDERS HISTORY -->
            <div class="lg:col-span-7 space-y-4 w-full">
                <div class="bg-white p-4 sm:p-5 rounded-2xl shadow-sm border border-gray-100">
                    <h2 class="text-base sm:text-xl font-black text-slate-900 flex items-center justify-between">
                        <span><i class="fa-solid fa-box text-amber-500 mr-2"></i> My Order History</span>
                        <span class="text-xs font-bold bg-amber-100 text-amber-800 px-2.5 py-1 rounded-full whitespace-nowrap">
                            <?= $total_orders; ?> Orders
                        </span>
                    </h2>
                    <p class="text-xs text-gray-400 mt-1">ဝယ်ယူခဲ့သော စာအုပ်များနှင့် အော်ဒါအသေးစိတ် မှတ်တမ်းများ</p>
                </div>

                <?php if ($orders_result && $orders_result->num_rows > 0): ?>
                    <div class="space-y-4">
                        <?php while ($order = $orders_result->fetch_assoc()):
                            $order_id = $order['id'];
                            $order_status = strtolower($order['status'] ?? 'pending');
                            $payment_status = strtolower($order['payment_status'] ?? 'pending');

                            $badge_class = "bg-amber-50 text-amber-700 border-amber-200";
                            if ($order_status === 'completed' || $order_status === 'delivered') {
                                $badge_class = "bg-emerald-50 text-emerald-700 border-emerald-200";
                            } elseif ($order_status === 'cancelled') {
                                $badge_class = "bg-rose-50 text-rose-700 border-rose-200";
                            }

                            // Fetch itemized books linked to current order ID
                            $items_stmt = $conn->prepare("SELECT order_item.*, books.* 
                                                         FROM order_item 
                                                         JOIN books ON order_item.book_id = books.id 
                                                         WHERE order_item.order_id = ?");
                            $items_stmt->bind_param("i", $order_id);
                            $items_stmt->execute();
                            $items_result = $items_stmt->get_result();

                            // Calculate subtotal of items directly from items query as fallback verification
                            $calculated_books_total = 0;
                        ?>

                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                            <!-- Card Header -->
                            <div class="px-4 sm:px-5 py-3.5 bg-slate-50 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-bold text-slate-900 text-xs sm:text-sm font-mono">
                                        #<?= htmlspecialchars($order['order_number'] ?? $order['id']); ?>
                                    </span>
                                    <span class="text-[11px] text-gray-400">
                                        <i class="fa-regular fa-clock mr-1"></i>
                                        <?= isset($order['created_at']) ? date('d M Y, h:i A', strtotime($order['created_at'])) : ''; ?>
                                    </span>
                                </div>
                                <span class="inline-block px-2.5 py-0.5 rounded-md text-[11px] font-bold border capitalize <?= $badge_class; ?>">
                                    <?= $order_status; ?>
                                </span>
                            </div>

                            <!-- Purchased Books Itemized List -->
                            <div class="p-3 sm:p-4 divide-y divide-gray-100">
                                <?php if ($items_result && $items_result->num_rows > 0): ?>
                                    <?php while ($item = $items_result->fetch_assoc()): 
                                        $raw_cover = $item['cover_image'] ?? $item['image'] ?? $item['book_image'] ?? '';
                                        $book_title = $item['title'] ?? $item['name'] ?? $item['book_name'] ?? 'Book';
                                        
                                        $item_total = $item['price'] * $item['quantity'];
                                        $calculated_books_total += $item_total;

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
                                                    <?= number_format($item_total); ?> ကျပ်
                                                </p>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                                <?php $items_stmt->close(); ?>
                            </div>

                            <!-- Footer Price Total & Payment Status -->
                            <div class="px-4 sm:px-5 py-3 bg-slate-50/50 border-t border-gray-100 flex items-center justify-between text-xs flex-wrap gap-2">
                                <span class="text-gray-500">
                                    Payment: <strong class="text-slate-700 capitalize"><?= htmlspecialchars($payment_status); ?></strong>
                                </span>
                                <div>
                                    <span class="text-gray-500 mr-1">စုစုပေါင်း:</span>
                                    <span class="text-sm font-black text-green-600">
                                        <!-- Displays book items total price only without delivery charges -->
                                        <?= number_format($order['books_subtotal'] > 0 ? $order['books_subtotal'] : $calculated_books_total); ?> ကျပ်
                                    </span>
                                </div>
                            </div>
                        </div>

                        <?php endwhile; ?>
                    </div>

                    <!-- ADVANCED PAGINATION NAVIGATION CONTROLS -->
                    <?php if ($total_pages > 1): ?>
                        <div class="flex flex-wrap items-center justify-center gap-1 sm:gap-1.5 mt-6 pt-4 border-t border-slate-200/60 text-xs">
                            
                            <!-- Previous Page Link -->
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1; ?>" class="px-2.5 sm:px-3 py-1.5 bg-white border border-gray-200 text-slate-700 rounded-lg font-bold hover:bg-gray-50 transition flex items-center gap-1 shadow-sm">
                                    <i class="fa-solid fa-chevron-left text-[10px]"></i> Prev
                                </a>
                            <?php else: ?>
                                <span class="px-2.5 sm:px-3 py-1.5 bg-gray-100 border border-gray-200 text-gray-400 rounded-lg font-bold cursor-not-allowed flex items-center gap-1">
                                    <i class="fa-solid fa-chevron-left text-[10px]"></i> Prev
                                </span>
                            <?php endif; ?>

                            <!-- Output Page 1 Link -->
                            <?php if ($page == 1): ?>
                                <span class="px-3 py-1.5 bg-green-600 text-white font-bold rounded-lg shadow-sm">1</span>
                            <?php else: ?>
                                <a href="?page=1" class="px-3 py-1.5 bg-white border border-gray-200 text-slate-700 font-bold rounded-lg hover:bg-gray-50 transition">1</a>
                            <?php endif; ?>

                            <!-- Ellipsis before middle block -->
                            <?php if ($page > 3): ?>
                                <span class="px-1.5 py-1 text-gray-400 font-bold select-none">...</span>
                            <?php endif; ?>

                            <!-- Middle adjacent page range links -->
                            <?php 
                            $start_p = max(2, $page - 1);
                            $end_p = min($total_pages - 1, $page + 1);

                            for ($i = $start_p; $i <= $end_p; $i++): 
                                if ($i == 1 || $i == $total_pages) continue;
                            ?>
                                <?php if ($i == $page): ?>
                                    <span class="px-3 py-1.5 bg-green-600 text-white font-bold rounded-lg shadow-sm"><?= $i; ?></span>
                                <?php else: ?>
                                    <a href="?page=<?= $i; ?>" class="px-3 py-1.5 bg-white border border-gray-200 text-slate-700 font-bold rounded-lg hover:bg-gray-50 transition"><?= $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <!-- Ellipsis after middle block -->
                            <?php if ($page < $total_pages - 2): ?>
                                <span class="px-1.5 py-1 text-gray-400 font-bold select-none">...</span>
                            <?php endif; ?>

                            <!-- Output Last Page Link -->
                            <?php if ($total_pages > 1): ?>
                                <?php if ($page == $total_pages): ?>
                                    <span class="px-3 py-1.5 bg-green-600 text-white font-bold rounded-lg shadow-sm"><?= $total_pages; ?></span>
                                <?php else: ?>
                                    <a href="?page=<?= $total_pages; ?>" class="px-3 py-1.5 bg-white border border-gray-200 text-slate-700 font-bold rounded-lg hover:bg-gray-50 transition"><?= $total_pages; ?></a>
                                <?php endif; ?>
                            <?php endif; ?>

                            <!-- Next Page Link -->
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?= $page + 1; ?>" class="px-2.5 sm:px-3 py-1.5 bg-white border border-gray-200 text-slate-700 rounded-lg font-bold hover:bg-gray-50 transition flex items-center gap-1 shadow-sm">
                                    Next <i class="fa-solid fa-chevron-right text-[10px]"></i>
                                </a>
                            <?php else: ?>
                                <span class="px-2.5 sm:px-3 py-1.5 bg-gray-100 border border-gray-200 text-gray-400 rounded-lg font-bold cursor-not-allowed flex items-center gap-1">
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
                <?php $orders_stmt->close(); ?>
            </div>

        </div>

    </main>

    <?php include __DIR__ . '/../auth/footer.php'; ?>

    <!-- Client side Image Selection Preview Script -->
    <script>
        function previewProfileImage(event) {
            const input = event.target;
            const mainPreview = document.getElementById('user_preview');
            const smallPreview = document.getElementById('small_preview');

            if (input.files && input.files[0]) {
                const objectUrl = URL.createObjectURL(input.files[0]);
                
                // Update main user avatar image
                mainPreview.src = objectUrl;

                // Display dynamic thumbnail next to file button
                smallPreview.src = objectUrl;
                smallPreview.classList.remove('hidden');
            }
        }
    </script>

</body>
</html>