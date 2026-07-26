<?php
// Session and Database Connection Validation
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/db.php'; 

// Restrict access to Admins only
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

// Active Sidebar Navigation Key
$current_page = 'adminprofile';

// Get Admin ID from Session
$admin_id = $_SESSION['user_id'] ?? 0;

if ($admin_id === 0) {
    // Fallback if user_id is not set in session
    $email = $_SESSION['user_email'] ?? 'admin@gmail.com';
    $stmt = $conn->prepare("SELECT id FROM Users WHERE email = ? AND role = 'admin' LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $admin_id = $row ? intval($row['id']) : 0;
    $stmt->close();

    if ($admin_id === 0) {
        die("Admin account not found in database.");
    }
    $_SESSION['user_id'] = $admin_id;
}

// Fetch current Admin data from Database
$admin_stmt = $conn->prepare("SELECT * FROM Users WHERE id = ?");
$admin_stmt->bind_param("i", $admin_id);
$admin_stmt->execute();
$adminData = $admin_stmt->get_result()->fetch_assoc();
$admin_stmt->close();

if (!$adminData) {
    die("Admin profile not found.");
}

// Notifications Queries for Top Header Bell
$low_stock_query = mysqli_query($conn, "SELECT id, title, stock FROM Books WHERE stock < 3 ORDER BY stock ASC");
$low_stock_count = $low_stock_query ? mysqli_num_rows($low_stock_query) : 0;

$pending_payments_query = mysqli_query($conn, "SELECT id, amount, status FROM Payment WHERE status = 'pending' ORDER BY id DESC LIMIT 3");
$pending_payments_count = $pending_payments_query ? mysqli_num_rows($pending_payments_query) : 0;

// Dynamic Header Session Sync
$admin_name = $adminData['name'] ?? ($_SESSION['user_name'] ?? 'Admin User');
$admin_email = $adminData['email'] ?? ($_SESSION['user_email'] ?? 'admin@gmail.com');
$_SESSION['user_image'] = $adminData['profile_image'] ?? ($_SESSION['user_image'] ?? '');

$success_msg = "";
$error_msg = "";

// Handle Profile Update Form Submission
if (isset($_POST['update_profile'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    // Sanitize phone input (keep digits only)
    $phone_input = trim($_POST['phone'] ?? '');
    $phone = preg_replace('/[^0-9]/', '', $phone_input);

    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $profile_image = $adminData['profile_image'];

    $is_changed = false;

    // Validate Phone Number
    if (!empty($phone_input) && $phone_input !== $phone) {
        $error_msg = "Phone number must contain numeric digits only.";
    }

    // Handle Profile Image Upload
    if (empty($error_msg) && isset($_FILES['profile_image']['name']) && $_FILES['profile_image']['name'] != "") {
        $target_dir = "../uploads/profile/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_name = time() . "_" . basename($_FILES["profile_image"]["name"]);
        $target_file = $target_dir . $file_name;
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        if (in_array($ext, $allowed)) {
            if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_file)) {
                // Remove old profile picture if exists
                if (!empty($profile_image) && file_exists($target_dir . $profile_image)) {
                    unlink($target_dir . $profile_image);
                }
                $profile_image = $file_name;
                $_SESSION['user_image'] = $profile_image;
                $is_changed = true;
            } else {
                $error_msg = "Failed to upload image file.";
            }
        } else {
            $error_msg = "Only JPG, JPEG, PNG, and WEBP formats are allowed.";
        }
    }

    // Check if textual fields were changed
    if (empty($error_msg) && ($name !== $adminData['name'] || $email !== $adminData['email'] || $phone !== ($adminData['phone'] ?? ''))) {
        $is_changed = true;
    }

    if (empty($error_msg)) {
        if (!empty($new_password)) {
            // Verify Current Password before update
            if (password_verify($current_password, $adminData['password']) || $current_password === $adminData['password']) {
                $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
                $stmt = $conn->prepare("UPDATE Users SET name=?, email=?, phone=?, password=?, profile_image=? WHERE id=?");
                $stmt->bind_param("sssssi", $name, $email, $phone, $hashed_password, $profile_image, $admin_id);
                $is_changed = true;
            } else {
                $error_msg = "Current password provided is incorrect!";
            }
        } else {
            // Update Profile Info without password change
            $stmt = $conn->prepare("UPDATE Users SET name=?, email=?, phone=?, profile_image=? WHERE id=?");
            $stmt->bind_param("ssssi", $name, $email, $phone, $profile_image, $admin_id);
        }

        if (empty($error_msg)) {
            if ($is_changed) {
                if ($stmt->execute()) {
                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_email'] = $email;
                    $_SESSION['success_msg'] = "Profile settings updated successfully!";
                    header("Location: adminprofile.php");
                    exit();
                } else {
                    $error_msg = "Failed to update profile data in database.";
                }
                $stmt->close();
            } else {
                header("Location: adminprofile.php");
                exit();
            }
        }
    }
}

if (isset($_SESSION['success_msg'])) {
    $success_msg = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
}

// Avatar Image Fallback Setup
$avatar = !empty($adminData['profile_image']) ? '/onlinebookshop/uploads/profile/' . $adminData['profile_image'] : 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - BookShop Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        .glow-avatar::after { 
            content: ''; 
            position: absolute; 
            inset: -4px; 
            border-radius: 9999px; 
            background: linear-gradient(135deg, #f59e0b, #ef4444); 
            z-index: -1; 
            opacity: 0.8; 
        }
    </style>
</head>
<body class="bg-slate-50 font-sans antialiased text-slate-800">

<div class="flex h-screen overflow-hidden">
    
    <!-- ================= DYNAMIC SIDEBAR INCLUDE ================= -->
    <?php include '../auth/sidebar.php'; ?>

    <div class="flex-1 flex flex-col overflow-hidden w-full">
        
        <!-- TOP NAVIGATION BAR (Matching Dashboard UI) -->
        <header class="h-16 bg-yellow-300 text-slate-900 font-bold flex items-center justify-between px-4 md:px-8 z-40 shrink-0">
            <div class="flex items-center space-x-3">
                <button onclick="toggleSidebar()" class="p-2 rounded-xl text-slate-600 hover:bg-slate-50 md:hidden transition cursor-pointer">
                    <i class="fa-solid fa-bars text-lg"></i>
                </button>
                <h1 class="text-lg font-bold text-slate-800 md:text-xl">Admin Profile</h1>
            </div>

            <div class="flex items-center space-x-4 relative">
                <!-- Notifications Bell Button -->
                <div class="relative">
                    <button onclick="toggleNotificationDropdown(event)" id="notiBtn" class="p-2 text-slate-500 hover:text-indigo-600 hover:bg-slate-50 rounded-xl transition cursor-pointer">
                        <i class="fa-solid fa-bell"></i>
                        <?php if ($low_stock_count > 0 || $pending_payments_count > 0): ?>
                            <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-rose-500 rounded-full ring-2 ring-white"></span>
                        <?php endif; ?>
                    </button>

                    <!-- Notifications Dropdown -->
                    <div id="notiDropdown" class="hidden absolute right-0 top-12 w-80 bg-amber-50 border border-amber-200 shadow-xl rounded-2xl overflow-hidden z-50">
                        <div class="px-4 py-3 bg-slate-50 border-b border-slate-100 font-bold text-xs text-slate-700">Notifications</div>
                        <div class="divide-y divide-slate-100 max-h-64 overflow-y-auto no-scrollbar">
                            <?php if ($pending_payments_count > 0): ?>
                                <?php while($payment = mysqli_fetch_assoc($pending_payments_query)): ?>
                                <a href="manage_payment.php" class="block p-3 hover:bg-slate-50 transition">
                                    <p class="text-xs font-bold text-indigo-600 flex items-center"><i class="fa-solid fa-wallet mr-1.5"></i> New Bank Transfer Pending</p>
                                    <p class="text-[10px] text-slate-500 mt-0.5">Amount: <?php echo number_format($payment['amount']); ?> MMK awaiting approval.</p>
                                </a>
                                <?php endwhile; ?>
                            <?php endif; ?>

                            <?php if ($low_stock_count > 0): ?>
                                <div class="block p-3 bg-amber-50/40">
                                    <p class="text-xs font-bold text-amber-600 flex items-center"><i class="fa-solid fa-triangle-exclamation mr-1.5"></i> Critical Stock Warning</p>
                                    <p class="text-[10px] text-slate-500 mt-0.5">You have <?php echo $low_stock_count; ?> books currently running low on stock.</p>
                                </div>
                            <?php endif; ?>

                            <?php if ($low_stock_count == 0 && $pending_payments_count == 0): ?>
                                <div class="p-4 text-center text-xs text-slate-400 font-medium">No new operational notifications.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Admin Profile Dropdown Menu -->
                <div class="relative border-l border-slate-200 pl-4">
                    <button onclick="toggleProfileDropdown(event)" id="profileBtn" class="w-8 h-8 rounded-full border border-slate-200 hover:border-indigo-500 flex items-center justify-center transition cursor-pointer overflow-hidden">
                        <img src="<?php echo $avatar; ?>" 
                             class="w-full h-full object-cover" 
                             alt="Admin Profile">
                    </button>

                    <div id="profileDropdown" class="hidden absolute right-0 top-12 w-48 bg-white border border-slate-200 shadow-xl rounded-2xl overflow-hidden z-50">
                        <div class="px-4 py-2.5 border-b border-slate-100 bg-slate-50/60">
                            <p class="text-xs font-bold text-slate-800 truncate"><?php echo htmlspecialchars($admin_name); ?></p>
                            <p class="text-[10px] text-slate-400 truncate"><?php echo htmlspecialchars($admin_email); ?></p>
                        </div>
                        <div class="py-1">
                            <a href="adminprofile.php" class="flex items-center space-x-2 px-4 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50 hover:text-indigo-600 transition">
                                <i class="fa-solid fa-id-card w-4 text-slate-400"></i><span>My Profile</span>
                            </a>
                            <a href="../auth/logout.php" class="flex items-center space-x-2 px-4 py-2 text-xs font-medium text-rose-600 hover:bg-rose-50 transition">
                                <i class="fa-solid fa-right-from-bracket w-4 text-rose-500"></i><span>Sign Out</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- MAIN CANVAS -->
        <main class="flex-1 overflow-y-auto p-4 md:p-8 space-y-6 max-w-[1600px] w-full mx-auto">
            
            <div class="mb-4">
                <div class="flex items-center gap-3">
                    <span class="w-2.5 h-7 bg-amber-500 rounded-full inline-block"></span>
                    <h2 class="text-2xl font-black text-slate-900 tracking-tight">Profile Settings</h2>
                </div>
                <p class="text-xs text-slate-500 mt-1 pl-5">Configure and update your administrator credentials, security verification, and contact data.</p>
            </div>

            <!-- Feedback Messages -->
            <?php if (!empty($success_msg)): ?>
                <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-xs font-semibold flex items-center gap-3 shadow-sm">
                    <i class="fa-solid fa-circle-check text-emerald-500 text-base"></i> <?php echo htmlspecialchars($success_msg); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error_msg)): ?>
                <div class="p-4 rounded-2xl bg-rose-50 border border-rose-100 text-rose-800 text-xs font-semibold flex items-center gap-3 shadow-sm">
                    <i class="fa-solid fa-circle-exclamation text-rose-500 text-base"></i> <?php echo htmlspecialchars($error_msg); ?>
                </div>
            <?php endif; ?>

            <!-- Profile Settings Form -->
            <form action="adminprofile.php" method="POST" enctype="multipart/form-data" class="space-y-6">

                <!-- Header Profile Banner -->
                <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm overflow-hidden">
                    <div class="h-32 sm:h-44 bg-gradient-to-r from-slate-900 via-slate-800 to-slate-950 relative overflow-hidden">
                        <div class="absolute -top-10 -right-10 w-44 h-44 bg-amber-500/15 rounded-full blur-2xl"></div>
                        <div class="absolute bottom-0 left-1/3 w-36 h-36 bg-emerald-500/10 rounded-full blur-3xl"></div>
                    </div>

                    <div class="px-6 sm:px-10 -mt-14 sm:-mt-16 pb-6 relative z-10">
                        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
                            <div class="flex flex-col sm:flex-row sm:items-end gap-5">
                                <div class="relative group shrink-0 glow-avatar">
                                    <img id="avatar-preview" src="<?php echo $avatar; ?>"
                                         class="w-24 h-24 sm:w-28 sm:h-28 rounded-full object-cover bg-white ring-4 ring-white shadow-md">
                                    
                                    <label for="profile_image"
                                           class="absolute inset-0 rounded-full bg-slate-900/60 flex items-center justify-center opacity-0 group-hover:opacity-100 cursor-pointer transition-all duration-300 backdrop-blur-[2px]">
                                        <div class="bg-white rounded-full p-2.5 shadow-md transform scale-90 group-hover:scale-100 transition-transform duration-300">
                                            <i class="fa-solid fa-camera text-amber-500 text-xs"></i>
                                        </div>
                                    </label>
                                    <input type="file" id="profile_image" name="profile_image" accept="image/*" class="hidden" onchange="previewImage(event)">
                                </div>

                                <div class="sm:pb-2">
                                    <h3 class="text-xl sm:text-2xl font-black text-amber-600 tracking-tight"><?php echo htmlspecialchars($adminData['name']); ?></h3>
                                    <p class="text-[10px] text-amber-600 font-extrabold tracking-widest uppercase mt-0.5">Super Administrator</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Profile Form Sections -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    
                    <!-- General Information -->
                    <div class="lg:col-span-2 bg-white rounded-3xl border border-slate-200/80 shadow-sm">
                        <div class="px-6 py-4 border-b border-slate-100">
                            <h3 class="text-xs font-bold text-slate-800 flex items-center gap-2 uppercase tracking-wider">
                                <i class="fa-solid fa-id-card text-amber-500"></i> General Information
                            </h3>
                        </div>
                        <div class="p-6 space-y-5">
                            <div class="grid sm:grid-cols-2 gap-5">
                                <div>
                                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Name</label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400 pointer-events-none">
                                            <i class="fa-solid fa-user text-xs"></i>
                                        </span>
                                        <input type="text" name="name" value="<?php echo htmlspecialchars($adminData['name']); ?>" required
                                               class="w-full pl-11 pr-4 py-3 rounded-2xl border border-slate-200 bg-white text-xs text-slate-800 focus:outline-none focus:ring-4 focus:ring-amber-500/15 focus:border-amber-500 transition-all">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Email</label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400 pointer-events-none">
                                            <i class="fa-solid fa-envelope text-xs"></i>
                                        </span>
                                        <input type="email" name="email" value="<?php echo htmlspecialchars($adminData['email']); ?>" required
                                               class="w-full pl-11 pr-4 py-3 rounded-2xl border border-slate-200 bg-white text-xs text-slate-800 focus:outline-none focus:ring-4 focus:ring-amber-500/15 focus:border-amber-500 transition-all">
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Contact Number</label>
                                <div class="relative max-w-sm">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400 pointer-events-none">
                                        <i class="fa-solid fa-phone text-xs"></i>
                                    </span>
                                    <input type="text" name="phone" value="<?php echo htmlspecialchars($adminData['phone'] ?? ''); ?>" placeholder="09XXXXXXXXX" required
                                           class="w-full pl-11 pr-4 py-3 rounded-2xl border border-slate-200 bg-white text-xs text-slate-800 focus:outline-none focus:ring-4 focus:ring-amber-500/15 focus:border-amber-500 transition-all">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Password & Security -->
                    <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm">
                        <div class="px-6 py-4 border-b border-slate-100">
                            <h3 class="text-xs font-bold text-slate-800 flex items-center gap-2 uppercase tracking-wider">
                                <i class="fa-solid fa-shield-halved text-amber-500"></i> Password & Security
                            </h3>
                        </div>
                        <div class="p-6 space-y-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Current Password</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400 pointer-events-none">
                                        <i class="fa-solid fa-shield text-xs"></i>
                                    </span>
                                    <input type="password" id="current_pwd" name="current_password" placeholder="Current Password"
                                           class="w-full pl-11 pr-12 py-3 rounded-2xl border border-slate-200 bg-white text-xs text-slate-800 focus:outline-none focus:ring-4 focus:ring-amber-500/15 focus:border-amber-500 transition-all">
                                    <button type="button" onclick="toggleCurrentPassword()" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-amber-500 transition-colors">
                                        <i id="current-eye-icon" class="fa-solid fa-eye text-xs"></i>
                                    </button>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">New Password</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400 pointer-events-none">
                                        <i class="fa-solid fa-key text-xs"></i>
                                    </span>
                                    <input type="password" id="pwd" name="new_password" placeholder="Enter new password"
                                           class="w-full pl-11 pr-12 py-3 rounded-2xl border border-slate-200 bg-white text-xs text-slate-800 focus:outline-none focus:ring-4 focus:ring-amber-500/15 focus:border-amber-500 transition-all">
                                    <button type="button" onclick="togglePassword()" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-amber-500 transition-colors">
                                        <i id="eye-icon" class="fa-solid fa-eye text-xs"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Controls -->
                <div class="flex flex-col sm:flex-row justify-end gap-3 pt-4 border-t border-slate-200/60">
                    <button type="reset"
                            class="px-6 py-3 rounded-2xl text-xs font-bold text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 transition cursor-pointer order-2 sm:order-1">
                        Cancel
                    </button>
                    <button type="submit" name="update_profile"
                            class="bg-amber-500 hover:bg-amber-400 text-slate-900 font-extrabold px-8 py-3 rounded-2xl text-xs shadow-lg shadow-amber-500/10 transition cursor-pointer order-1 sm:order-2">
                        Save Changes
                    </button>
                </div>
            </form>
        </main>
    </div>
</div>

<!-- JavaScript Interactivity -->
<script>
    // Toggle Mobile Sidebar Drawer
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        if (sidebar) {
            sidebar.classList.toggle('-translate-x-full');
        }
    }

    // Toggle Top Bar Dropdowns
    function toggleNotificationDropdown(e) {
        e.stopPropagation();
        const notiDropdown = document.getElementById('notiDropdown');
        const profileDropdown = document.getElementById('profileDropdown');
        
        notiDropdown.classList.toggle('hidden');
        profileDropdown.classList.add('hidden'); 
    }

    function toggleProfileDropdown(e) {
        e.stopPropagation();
        const profileDropdown = document.getElementById('profileDropdown');
        const notiDropdown = document.getElementById('notiDropdown');
        
        profileDropdown.classList.toggle('hidden');
        notiDropdown.classList.add('hidden'); 
    }

    window.addEventListener('click', function(e) {
        const notiDropdown = document.getElementById('notiDropdown');
        const profileDropdown = document.getElementById('profileDropdown');
        const notiBtn = document.getElementById('notiBtn');
        const profileBtn = document.getElementById('profileBtn');

        if (notiDropdown && !notiDropdown.contains(e.target) && notiBtn && !notiBtn.contains(e.target)) {
            notiDropdown.classList.add('hidden');
        }
        if (profileDropdown && !profileDropdown.contains(e.target) && profileBtn && !profileBtn.contains(e.target)) {
            profileDropdown.classList.add('hidden');
        }
    });

    // Profile Image Instant Preview
    function previewImage(event) {
        const reader = new FileReader();
        reader.onload = function() {
            document.getElementById('avatar-preview').src = reader.result;
        };
        if (event.target.files[0]) {
            reader.readAsDataURL(event.target.files[0]);
        }
    }

    // Toggle Password Input Visibility
    function toggleCurrentPassword() {
        const pwd = document.getElementById('current_pwd');
        const icon = document.getElementById('current-eye-icon');
        if (pwd.type === 'password') {
            pwd.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            pwd.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }

    function togglePassword() {
        const pwd = document.getElementById('pwd');
        const icon = document.getElementById('eye-icon');
        if (pwd.type === 'password') {
            pwd.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            pwd.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }
</script>
</body>
</html>