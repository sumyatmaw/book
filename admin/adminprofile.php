<?php
session_start();
require_once "../config/db.php";

// Admin login check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

// Get admin ID from session or fallback to email lookup
if (isset($_SESSION['user_id'])) {
    $admin_id = intval($_SESSION['user_id']);
} else {
    // Admin login doesn't set user_id, so look up by email
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

    // Store for future use
    $_SESSION['user_id'] = $admin_id;
}

$admin = $conn->prepare("SELECT * FROM Users WHERE id = ?");
$admin->bind_param("i", $admin_id);
$admin->execute();
$adminData = $admin->get_result()->fetch_assoc();
$admin->close();

if (!$adminData) {
    die("Admin profile not found.");
}

$success_msg = "";
$error_msg = "";

if (isset($_POST['update_profile'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($name) || empty($email)) {
        $error_msg = "Name and email are required.";
    } else {
        $profile_image = $adminData['profile_image'];

        // Handle profile image upload
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $target_dir = "../uploads/profile/";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            $file_name = time() . "_" . basename($_FILES["profile_image"]["name"]);
            $target_file = $target_dir . $file_name;
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            $ext = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

            if (in_array($ext, $allowed)) {
                if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_file)) {
                    // Delete old image
                    if (!empty($profile_image) && file_exists("../uploads/profile/" . $profile_image)) {
                        unlink("../uploads/profile/" . $profile_image);
                    }
                    $profile_image = $file_name;
                } else {
                    $error_msg = "Failed to upload image.";
                }
            } else {
                $error_msg = "Only JPG, JPEG, PNG, WEBP files are allowed.";
            }
        }

        if (empty($error_msg)) {
            if (!empty($password)) {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE Users SET name=?, email=?, phone=?, password=?, profile_image=? WHERE id=?");
                $stmt->bind_param("sssssi", $name, $email, $phone, $hashed, $profile_image, $admin_id);
            } else {
                $stmt = $conn->prepare("UPDATE Users SET name=?, email=?, phone=?, profile_image=? WHERE id=?");
                $stmt->bind_param("ssssi", $name, $email, $phone, $profile_image, $admin_id);
            }

            if ($stmt->execute()) {
                $success_msg = "Profile updated successfully!";
                // Refresh data
                $admin = $conn->prepare("SELECT * FROM Users WHERE id = ?");
                $admin->bind_param("i", $admin_id);
                $admin->execute();
                $adminData = $admin->get_result()->fetch_assoc();
                $admin->close();
            } else {
                $error_msg = "Failed to update profile.";
            }
            $stmt->close();
        }
    }
}

if (isset($_SESSION['success_msg'])) {
    $success_msg = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - Online Book Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        #adminSidebar { transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        @media (max-width: 1023px) {
            #adminSidebar { transform: translateX(-100%); position: fixed; top: 0; left: 0; bottom: 0; z-index: 40; }
            #adminSidebar.open { transform: translateX(0); }
            #sidebarOverlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 35; }
            #sidebarOverlay.open { display: block; }
        }
        .sidebar-link.active { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
        .sidebar-link.active i { color: #f59e0b; }
        .profile-card { animation: fadeUp 0.4s ease-out both; }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
        .avatar-ring { background: conic-gradient(from 0deg, #f59e0b, #f97316, #f59e0b, #fbbf24, #f59e0b); padding: 3px; border-radius: 9999px; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex flex-col font-sans text-slate-800">

    <?php include '../auth/header.php'; ?>

    <div id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <div class="flex flex-1">

        <!-- Sidebar -->
        <aside id="adminSidebar" class="w-64 bg-[#0a1128] text-gray-300 flex flex-col justify-between border-r border-slate-800 shrink-0 lg:relative lg:translate-x-0">
            <div class="p-4 space-y-2">
                <div class="px-4 py-3 mb-2">
                    <h2 class="text-[11px] font-bold uppercase tracking-widest text-slate-500">Admin Panel</h2>
                </div>
                <nav class="space-y-0.5">
                    <a href="dashboard.php" class="sidebar-link flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium hover:bg-slate-800/60 hover:text-white transition-all duration-200">
                        <i class="fa-solid fa-chart-pie text-sm w-5 text-center"></i> Dashboard
                    </a>
                    <a href="categories.php" class="sidebar-link flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium hover:bg-slate-800/60 hover:text-white transition-all duration-200">
                        <i class="fa-solid fa-tags text-sm w-5 text-center text-slate-500"></i> Categories
                    </a>
                    <a href="books.php" class="sidebar-link flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium hover:bg-slate-800/60 hover:text-white transition-all duration-200">
                        <i class="fa-solid fa-book text-sm w-5 text-center text-slate-500"></i> Books
                    </a>
                    <a href="orders.php" class="sidebar-link flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium hover:bg-slate-800/60 hover:text-white transition-all duration-200">
                        <i class="fa-solid fa-shopping-bag text-sm w-5 text-center text-slate-500"></i> Orders
                    </a>
                    <a href="payments.php" class="sidebar-link flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium hover:bg-slate-800/60 hover:text-white transition-all duration-200">
                        <i class="fa-solid fa-credit-card text-sm w-5 text-center text-slate-500"></i> Payments
                    </a>
                    <a href="customer.php" class="sidebar-link flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium hover:bg-slate-800/60 hover:text-white transition-all duration-200">
                        <i class="fa-solid fa-users text-sm w-5 text-center text-slate-500"></i> Customers
                    </a>
                    <hr class="border-slate-800 my-2">
                    <a href="adminprofile.php" class="sidebar-link active flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium transition-all duration-200">
                        <i class="fa-solid fa-user-gear text-sm w-5 text-center"></i> My Profile
                    </a>
                    <a href="../index.php" class="sidebar-link flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium hover:bg-slate-800/60 hover:text-white transition-all duration-200">
                        <i class="fa-solid fa-store text-sm w-5 text-center text-slate-500"></i> View Store
                    </a>
                </nav>
            </div>
            <div class="p-4 border-t border-slate-800">
                <div class="flex items-center gap-3 px-3 py-2">
                    <span class="w-8 h-8 bg-amber-500 rounded-lg flex items-center justify-center text-xs font-bold text-slate-900">A</span>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-bold text-white truncate">Admin</p>
                        <p class="text-[10px] text-slate-500 truncate"><?= htmlspecialchars($adminData['email'] ?? ''); ?></p>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main content -->
        <main class="flex-1 p-4 sm:p-6 lg:p-8 min-w-0 max-w-3xl">

            <!-- Mobile sidebar toggle -->
            <div class="lg:hidden flex items-center gap-3 mb-4">
                <button onclick="toggleSidebar()" class="w-10 h-10 bg-white rounded-xl border border-gray-200 flex items-center justify-center text-gray-600 hover:bg-gray-50 transition shadow-sm">
                    <i class="fa-solid fa-bars text-sm"></i>
                </button>
                <h1 class="text-lg font-black text-slate-900">Profile</h1>
            </div>

            <!-- Page header -->
            <div class="mb-6">
                <h2 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                    <i class="fa-solid fa-user-gear text-amber-500"></i> Account Settings
                </h2>
                <p class="text-xs text-gray-400 mt-1">Manage your profile details and security.</p>
            </div>

            <!-- Flash messages -->
            <?php if (!empty($success_msg)): ?>
                <div class="mb-5 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm font-semibold flex items-center gap-2 shadow-sm">
                    <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success_msg); ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($error_msg)): ?>
                <div class="mb-5 p-4 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm font-semibold flex items-center gap-2 shadow-sm">
                    <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error_msg); ?>
                </div>
            <?php endif; ?>

            <!-- Profile card -->
            <form action="adminprofile.php" method="POST" enctype="multipart/form-data" class="profile-card">

                <!-- Avatar + info card -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden mb-5">
                    <!-- Cover gradient -->
                    <div class="h-28 sm:h-32 bg-gradient-to-br from-[#0a1128] via-slate-800 to-slate-900 relative overflow-hidden">
                        <div class="absolute -top-10 -right-10 w-40 h-40 bg-amber-500/10 rounded-full"></div>
                        <div class="absolute bottom-0 left-1/3 w-24 h-24 bg-amber-500/5 rounded-full translate-y-1/2"></div>
                    </div>

                    <!-- Avatar + name -->
                    <div class="px-5 sm:px-8 -mt-14 sm:-mt-16 pb-6 relative">
                        <div class="flex flex-col sm:flex-row sm:items-end gap-4">
                            <!-- Avatar with animated ring -->
                            <div class="relative group shrink-0">
                                <?php
                                $avatar = !empty($adminData['profile_image']) ? '../uploads/profile/' . $adminData['profile_image'] : 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png';
                                ?>
                                <div class="avatar-ring">
                                    <img id="avatar-preview" src="<?= $avatar; ?>"
                                         class="w-24 h-24 sm:w-28 sm:h-28 rounded-full object-cover bg-white">
                                </div>
                                <label for="profile_image"
                                       class="absolute inset-0 rounded-full bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 cursor-pointer transition-opacity duration-200">
                                    <div class="bg-white rounded-full p-2.5 shadow-lg">
                                        <i class="fa-solid fa-camera text-amber-500 text-sm"></i>
                                    </div>
                                </label>
                                <input type="file" id="profile_image" name="profile_image" accept="image/*" class="hidden" onchange="previewImage(event)">
                            </div>

                            <div class="flex-1 sm:pb-1">
                                <h3 class="text-lg sm:text-xl font-black text-slate-900"><?= htmlspecialchars($adminData['name']); ?></h3>
                                <p class="text-xs text-gray-400 mt-0.5">Administrator</p>
                            </div>

                            <div class="sm:pb-1">
                                <span class="inline-flex items-center gap-1.5 text-[11px] font-bold text-amber-700 bg-amber-50 border border-amber-100 px-3 py-1.5 rounded-full">
                                    <i class="fa-solid fa-shield-check text-[10px]"></i> Admin Account
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Personal Information -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden mb-5">
                    <div class="px-5 sm:px-8 py-5 border-b border-gray-100">
                        <h3 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                            <span class="w-7 h-7 bg-amber-50 text-amber-600 rounded-lg flex items-center justify-center">
                                <i class="fa-solid fa-user text-[11px]"></i>
                            </span>
                            Personal Information
                        </h3>
                    </div>
                    <div class="p-5 sm:p-8">
                        <div class="grid sm:grid-cols-2 gap-5">
                            <!-- Name -->
                            <div>
                                <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-2">Full Name</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400 pointer-events-none">
                                        <i class="fa-solid fa-user text-xs"></i>
                                    </span>
                                    <input type="text" name="name" value="<?= htmlspecialchars($adminData['name']); ?>" required
                                           class="w-full pl-10 pr-4 py-3 rounded-xl border border-gray-200 bg-gray-50/50 text-sm text-slate-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-amber-500/40 focus:border-amber-400 transition-all duration-200">
                                </div>
                            </div>
                            <!-- Email -->
                            <div>
                                <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-2">Email Address</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400 pointer-events-none">
                                        <i class="fa-solid fa-envelope text-xs"></i>
                                    </span>
                                    <input type="email" name="email" value="<?= htmlspecialchars($adminData['email']); ?>" required
                                           class="w-full pl-10 pr-4 py-3 rounded-xl border border-gray-200 bg-gray-50/50 text-sm text-slate-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-amber-500/40 focus:border-amber-400 transition-all duration-200">
                                </div>
                            </div>
                            <!-- Phone -->
                            <div>
                                <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-2">Phone Number</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400 pointer-events-none">
                                        <i class="fa-solid fa-phone text-xs"></i>
                                    </span>
                                    <input type="text" name="phone" value="<?= htmlspecialchars($adminData['phone'] ?? ''); ?>"
                                           placeholder="09 403 502 687"
                                           class="w-full pl-10 pr-4 py-3 rounded-xl border border-gray-200 bg-gray-50/50 text-sm text-slate-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-amber-500/40 focus:border-amber-400 transition-all duration-200">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Security -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden mb-5">
                    <div class="px-5 sm:px-8 py-5 border-b border-gray-100">
                        <h3 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                            <span class="w-7 h-7 bg-amber-50 text-amber-600 rounded-lg flex items-center justify-center">
                                <i class="fa-solid fa-lock text-[11px]"></i>
                            </span>
                            Security
                        </h3>
                    </div>
                    <div class="p-5 sm:p-8">
                        <div class="max-w-md">
                            <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-2">New Password</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400 pointer-events-none">
                                    <i class="fa-solid fa-key text-xs"></i>
                                </span>
                                <input type="password" id="pwd" name="password" placeholder="Leave blank to keep current"
                                       class="w-full pl-10 pr-12 py-3 rounded-xl border border-gray-200 bg-gray-50/50 text-sm text-slate-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-amber-500/40 focus:border-amber-400 transition-all duration-200">
                                <button type="button" onclick="togglePassword()" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-amber-500 transition-colors duration-200">
                                    <i id="eye-icon" class="fa-solid fa-eye text-sm"></i>
                                </button>
                            </div>
                            <p class="text-[10px] text-gray-400 mt-2 flex items-center gap-1">
                                <i class="fa-solid fa-info-circle"></i> Minimum 6 characters recommended
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Action buttons -->
                <div class="flex flex-col sm:flex-row justify-end gap-3">
                    <button type="reset"
                            class="px-6 py-3 rounded-xl text-sm font-bold text-gray-500 bg-white border border-gray-200 hover:bg-gray-50 transition-all duration-200 order-2 sm:order-1">
                        <i class="fa-solid fa-rotate-left mr-1.5 text-xs"></i> Reset
                    </button>
                    <button type="submit" name="update_profile"
                            class="bg-amber-500 hover:bg-amber-400 text-slate-900 font-bold px-8 py-3 rounded-xl text-sm shadow-lg shadow-amber-500/25 hover:shadow-amber-500/40 transition-all duration-200 order-1 sm:order-2">
                        <i class="fa-solid fa-check mr-1.5"></i> Save Changes
                    </button>
                </div>
            </form>
        </main>
    </div>

    <?php include '../auth/footer.php'; ?>

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
