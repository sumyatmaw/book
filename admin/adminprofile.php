<?php
session_start();
require_once "../config/db.php";

// Admin login check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$admin_id = $_SESSION['user_id'] ?? 0;

if ($admin_id === 0) {
    // Fallback if user_id is not set but session is admin
    $email = $_SESSION['user_email'] ?? 'admin@gmail.com';
    $stmt = $conn->prepare("SELECT id FROM Users WHERE email = ? LIMIT 1");
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

// Fetch current admin details
$admin_stmt = $conn->prepare("SELECT * FROM Users WHERE id = ?");
$admin_stmt->bind_param("i", $admin_id);
$admin_stmt->execute();
$adminData = $admin_stmt->get_result()->fetch_assoc();
$admin_stmt->close();

if (!$adminData) {
    die("Admin profile not found.");
}

// Store image in session for header sync
$_SESSION['admin_image'] = $adminData['profile_image'];

$success_msg = "";
$error_msg = "";

if (isset($_POST['update_profile'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    // Sanitize and validate numeric character sequence for phone number
    $phone_input = trim($_POST['phone'] ?? '');
    $phone = preg_replace('/[^0-9]/', '', $phone_input); // Keep only numeric digits

    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $profile_image = $adminData['profile_image'];

    $is_changed = false;

    // Validate phone number format
    if (!empty($phone_input) && $phone_input !== $phone) {
        $error_msg = "Phone number must contain only numbers.";
    }

    // Handle profile image file upload
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
                // Delete old image if exists
                if (!empty($profile_image) && file_exists($target_dir . $profile_image)) {
                    unlink($target_dir . $profile_image);
                }
                $profile_image = $file_name;
                $_SESSION['user_image'] = $profile_image; // Sync to header session immediately
                $is_changed = true;
            } else {
                $error_msg = "Failed to upload image.";
            }
        } else {
            $error_msg = "Only JPG, JPEG, PNG, WEBP files are allowed.";
        }
    }

    // Check if text fields have changed
    if (empty($error_msg) && ($name !== $adminData['name'] || $email !== $adminData['email'] || $phone !== ($adminData['phone'] ?? ''))) {
        $is_changed = true;
    }

    if (empty($error_msg)) {
        if (!empty($new_password)) {
            // Verify current password before allowing change
            if (password_verify($current_password, $adminData['password']) || $current_password === $adminData['password']) {
                $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
                $stmt = $conn->prepare("UPDATE Users SET name=?, email=?, phone=?, password=?, profile_image=? WHERE id=?");
                $stmt->bind_param("sssssi", $name, $email, $phone, $hashed_password, $profile_image, $admin_id);
                $is_changed = true;
            } else {
                $error_msg = "Current password is incorrect!";
            }
        } else {
            // Update profile without changing password
            $stmt = $conn->prepare("UPDATE Users SET name=?, email=?, phone=?, profile_image=? WHERE id=?");
            $stmt->bind_param("ssssi", $name, $email, $phone, $profile_image, $admin_id);
        }

        // Only execute if data changed and no previous errors
        if (empty($error_msg)) {
            if ($is_changed) {
                if ($stmt->execute()) {
                    $_SESSION['success_msg'] = "Profile updated successfully!";
                    header("Location: adminprofile.php");
                    exit();
                } else {
                    $error_msg = "Failed to update profile data.";
                }
                $stmt->close();
            } else {
                // If nothing changed, just refresh page cleanly
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

// Avatar Logic Setup
$avatar = !empty($adminData['profile_image']) ? '../uploads/profile/' . $adminData['profile_image'] : 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png';
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile | Premium Control Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        #adminSidebar { transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
        @media (max-width: 1023px) {
            #adminSidebar { transform: translateX(-100%); position: fixed; top: 0; left: 0; bottom: 0; z-index: 50; }
            #adminSidebar.open { transform: translateX(0); }
            #sidebarOverlay { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.4); backdrop-filter: blur(8px); z-index: 40; }
            #sidebarOverlay.open { display: block; }
        }
        .sidebar-link.active { background: #f59e0b; color: #0f172a; font-weight: 700; box-shadow: 0 10px 20px -5px rgba(245, 158, 11, 0.3); }
        .sidebar-link.active i { color: #0f172a; }
        .profile-card-animate { animation: slideInUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) both; }
        @keyframes slideInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        .glow-avatar::after { content: ''; position: absolute; inset: -4px; border-radius: 9999px; background: linear-gradient(135deg, #f59e0b, #ef4444); z-index: -1; opacity: 0.8; }
    </style>
</head>
<body class="bg-[#f8fafc] min-h-screen flex flex-col font-sans text-slate-800 antialiased selection:bg-amber-500 selection:text-slate-900">

    <?php include '../auth/header.php'; ?>

    <div id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <div class="flex flex-1">

        <aside id="adminSidebar" class="w-68 bg-slate-900 text-slate-300 flex flex-col justify-between border-r border-slate-800/60 shrink-0 lg:relative lg:translate-x-0 shadow-2xl lg:shadow-none">
            <div class="p-6 space-y-4">
                <div class="px-3 py-2 mb-4 border-b border-slate-800/80 pb-4">
                    <h2 class="text-[10px] font-black uppercase tracking-widest text-slate-500">Navigation</h2>
                </div>
                <nav class="space-y-1.5">
                    <a href="dashboard.php" class="sidebar-link flex items-center gap-3.5 px-4 py-3.5 rounded-2xl text-sm font-semibold hover:bg-slate-800 hover:text-white transition-all duration-300">
                        <i class="fa-solid fa-chart-pie text-sm w-5 text-center text-slate-400"></i> Dashboard
                    </a>
                    <a href="categories.php" class="sidebar-link flex items-center gap-3.5 px-4 py-3.5 rounded-2xl text-sm font-semibold hover:bg-slate-800 hover:text-white transition-all duration-300">
                        <i class="fa-solid fa-tags text-sm w-5 text-center text-slate-400"></i> Categories
                    </a>
                    <a href="books.php" class="sidebar-link flex items-center gap-3.5 px-4 py-3.5 rounded-2xl text-sm font-semibold hover:bg-slate-800 hover:text-white transition-all duration-300">
                        <i class="fa-solid fa-book text-sm w-5 text-center text-slate-400"></i> Books
                    </a>
                    <a href="orders.php" class="sidebar-link flex items-center gap-3.5 px-4 py-3.5 rounded-2xl text-sm font-semibold hover:bg-slate-800 hover:text-white transition-all duration-300">
                        <i class="fa-solid fa-shopping-bag text-sm w-5 text-center text-slate-400"></i> Orders
                    </a>
                    <a href="payments.php" class="sidebar-link flex items-center gap-3.5 px-4 py-3.5 rounded-2xl text-sm font-semibold hover:bg-slate-800 hover:text-white transition-all duration-300">
                        <i class="fa-solid fa-credit-card text-sm w-5 text-center text-slate-400"></i> Payments
                    </a>
                    <a href="delivery.php" class="sidebar-link flex items-center gap-3.5 px-4 py-3.5 rounded-2xl text-sm font-semibold hover:bg-slate-800 hover:text-white transition-all duration-300">
                        <i class="fa-solid fa-truck text-sm w-5 text-center text-slate-400"></i> Delivery
                    </a>
                    <a href="customer.php" class="sidebar-link flex items-center gap-3.5 px-4 py-3.5 rounded-2xl text-sm font-semibold hover:bg-slate-800 hover:text-white transition-all duration-300">
                        <i class="fa-solid fa-users text-sm w-5 text-center text-slate-400"></i> Customers
                    </a>
                    <hr class="border-slate-800/80 my-4">
                    <a href="adminprofile.php" class="sidebar-link active flex items-center gap-3.5 px-4 py-3.5 rounded-2xl text-sm font-bold transition-all duration-300">
                        <i class="fa-solid fa-user-gear text-sm w-5 text-center"></i> My Profile
                    </a>
                    <a href="../index.php" class="sidebar-link flex items-center gap-3.5 px-4 py-3.5 rounded-2xl text-sm font-semibold hover:bg-slate-800 hover:text-white transition-all duration-300">
                        <i class="fa-solid fa-store text-sm w-5 text-center text-slate-400"></i> View Store
                    </a>
                </nav>
            </div>
            <div class="p-5 border-t border-slate-800/80 bg-slate-950/30">
                <div class="flex items-center gap-3 px-1.5 py-1">
                    <!-- Updated: Letter "A" changed to actual profile image thumbnail -->
                    <img src="<?= $avatar; ?>" class="w-10 h-10 rounded-2xl object-cover shadow-lg ring-1 ring-slate-700">
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-bold text-white truncate"><?= htmlspecialchars($adminData['name'] ?? 'Administrator'); ?></p>
                        <p class="text-[11px] text-slate-500 truncate mt-0.5"><?= htmlspecialchars($adminData['email'] ?? ''); ?></p>
                    </div>
                </div>
            </div>
        </aside>

        <main class="flex-1 p-5 sm:p-8 lg:p-12 min-w-0 max-w-5xl mx-auto w-full">

            <div class="lg:hidden flex items-center gap-4 mb-6 bg-white p-4 rounded-3xl border border-slate-100 shadow-sm">
                <button onclick="toggleSidebar()" class="w-11 h-11 bg-slate-50 rounded-2xl flex items-center justify-center text-slate-700 hover:bg-slate-100 transition-colors">
                    <i class="fa-solid fa-bars text-sm"></i>
                </button>
                <h1 class="text-md font-extrabold text-slate-900">Admin Profile</h1>
            </div>

            <div class="mb-8">
                <div class="flex items-center gap-3">
                    <span class="w-2.5 h-7 bg-amber-500 rounded-full inline-block"></span>
                    <h2 class="text-2xl font-black text-slate-900 tracking-tight">Profile Settings</h2>
                </div>
                <p class="text-sm text-slate-500 mt-1.5 pl-5">Configure and update your administrator credentials, security verification, and contact data.</p>
            </div>

            <?php if (!empty($success_msg)): ?>
                <div class="mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm font-semibold flex items-center gap-3.5 shadow-sm">
                    <i class="fa-solid fa-circle-check text-emerald-500 text-lg"></i> <?= htmlspecialchars($success_msg); ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($error_msg)): ?>
                <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-100 text-rose-800 text-sm font-semibold flex items-center gap-3.5 shadow-sm">
                    <i class="fa-solid fa-circle-exclamation text-rose-500 text-lg"></i> <?= htmlspecialchars($error_msg); ?>
                </div>
            <?php endif; ?>

            <form action="adminprofile.php" method="POST" enctype="multipart/form-data" class="profile-card-animate space-y-6">

                <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
                    <div class="h-30 sm:h-48 bg-gradient-to-r from-slate-900 via-slate-800 to-slate-950 relative overflow-hidden">
                        <div class="absolute -top-10 -right-10 w-44 h-44 bg-amber-500/15 rounded-full blur-2xl"></div>
                        <div class="absolute bottom-0 left-1/3 w-36 h-36 bg-emerald-500/10 rounded-full blur-3xl"></div>
                    </div>

                    <div class="px-6 sm:px-10 -mt-16 sm:-mt-20 pb-8 relative z-10">
                        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-6">
                            <div class="flex flex-col sm:flex-row sm:items-end gap-5">
                                <div class="relative group shrink-0 glow-avatar">
                                    <img id="avatar-preview" src="<?= $avatar; ?>"
                                         class="w-28 h-28 sm:w-32 sm:h-32 rounded-full object-cover bg-white ring-4 ring-white shadow-lg">
                                    
                                    <label for="profile_image"
                                           class="absolute inset-0 rounded-full bg-slate-900/60 flex items-center justify-center opacity-0 group-hover:opacity-100 cursor-pointer transition-all duration-300 backdrop-blur-[2px]">
                                        <div class="bg-white rounded-full p-3 shadow-md transform scale-90 group-hover:scale-100 transition-transform duration-300">
                                            <i class="fa-solid fa-camera text-amber-500 text-sm"></i>
                                        </div>
                                    </label>
                                    <input type="file" id="profile_image" name="profile_image" accept="image/*" class="hidden" onchange="previewImage(event)">
                                </div>

                                <div class="sm:pb-3">
                                    <h3 class="text-xl sm:text-2xl font-black text-amber-600 tracking-tight"><?= htmlspecialchars($adminData['name']); ?></h3>
                                    <p class="text-[11px] text-amber-600 font-extrabold tracking-widest uppercase mt-1">Super Administrator</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    
                    <div class="lg:col-span-2 bg-white rounded-3xl border border-slate-100 shadow-sm">
                        <div class="px-6 py-4.5 border-b border-slate-50">
                            <h3 class="text-sm font-bold text-slate-800 flex items-center gap-2.5">
                                <i class="fa-solid fa-address-card text-amber-500"></i> General Information
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
                                        <input type="text" name="name" value="<?= htmlspecialchars($adminData['name']); ?>" required
                                               class="w-full pl-11 pr-4 py-3.5 rounded-2xl border border-slate-200 bg-white text-sm text-slate-800 focus:outline-none focus:ring-4 focus:ring-amber-500/15 focus:border-amber-500 transition-all duration-300">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Email</label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400 pointer-events-none">
                                            <i class="fa-solid fa-envelope text-xs"></i>
                                        </span>
                                        <input type="email" name="email" value="<?= htmlspecialchars($adminData['email']); ?>" required
                                               class="w-full pl-11 pr-4 py-3.5 rounded-2xl border border-slate-200 bg-white text-sm text-slate-800 focus:outline-none focus:ring-4 focus:ring-amber-500/15 focus:border-amber-500 transition-all duration-300">
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Contact Number</label>
                                <div class="relative max-w-sm">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400 pointer-events-none">
                                        <i class="fa-solid fa-phone text-xs"></i>
                                    </span>
                                    <input type="number" name="phone" value="<?= htmlspecialchars($adminData['phone'] ?? ''); ?>" placeholder="09XXXXXXXXX" required
                                           class="w-full pl-11 pr-4 py-3.5 rounded-2xl border border-slate-200 bg-white text-sm text-slate-800 focus:outline-none focus:ring-4 focus:ring-amber-500/15 focus:border-amber-500 transition-all duration-300 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-3xl border border-slate-100 shadow-sm">
                        <div class="px-6 py-4.5 border-b border-slate-50">
                            <h3 class="text-sm font-bold text-slate-800 flex items-center gap-2.5">
                                <i class="fa-solid fa-lock text-amber-500"></i> Password & Security
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
                                           class="w-full pl-11 pr-12 py-3.5 rounded-2xl border border-slate-200 bg-white text-sm text-slate-800 focus:outline-none focus:ring-4 focus:ring-amber-500/15 focus:border-amber-500 transition-all duration-300">
                                    <button type="button" onclick="toggleCurrentPassword()" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-amber-500 transition-colors">
                                        <i id="current-eye-icon" class="fa-solid fa-eye text-sm"></i>
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
                                           class="w-full pl-11 pr-12 py-3.5 rounded-2xl border border-slate-200 bg-white text-sm text-slate-800 focus:outline-none focus:ring-4 focus:ring-amber-500/15 focus:border-amber-500 transition-all duration-300">
                                    <button type="button" onclick="togglePassword()" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-amber-500 transition-colors">
                                        <i id="eye-icon" class="fa-solid fa-eye text-sm"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row justify-end gap-3 pt-6 border-t border-slate-100">
                    <button type="reset"
                            class="px-6 py-3.5 rounded-2xl text-sm font-bold text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 active:scale-[0.98] transition-all order-2 sm:order-1">
                        Cancel
                    </button>
                    <button type="submit" name="update_profile"
                            class="bg-amber-500 hover:bg-amber-400 text-slate-900 font-extrabold px-8 py-3.5 rounded-2xl text-sm shadow-xl shadow-amber-500/10 active:scale-[0.98] transition-all order-1 sm:order-2">
                        Save Changes
                    </button>
                </div>
            </form>
        </main>
    </div>

    <script>
    function toggleSidebar() {
        document.getElementById('adminSidebar').classList.toggle('open');
        document.getElementById('sidebarOverlay').classList.toggle('open');
    }
    function previewImage(event) {
        var reader = new FileReader();
        reader.onload = function() {
            document.getElementById('avatar-preview').src = reader.result;
        };
        reader.readAsDataURL(event.target.files[0]);
    }
    function toggleCurrentPassword() {
        var pwd = document.getElementById('current_pwd');
        var icon = document.getElementById('current-eye-icon');
        if (pwd.type === 'password') {
            pwd.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            pwd.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }
    function togglePassword() {
        var pwd = document.getElementById('pwd');
        var icon = document.getElementById('eye-icon');
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