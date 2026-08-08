<?php
session_start();
require_once '../config/db.php';

// Check admin authentication
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$message = "";
$error = "";
$admin_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;
$admin_name = $_SESSION['user_name'] ?? 'Admin User';
$admin_email = $_SESSION['user_email'] ?? 'admin@bookshop.com';
$admin_initial = strtoupper(substr($admin_name, 0, 1));

// Fetch profile picture from database if session variable is missing
if (!isset($_SESSION['user_image']) && isset($conn)) {
    $u_query = mysqli_query($conn, "SELECT profile_image FROM Users WHERE id = '$admin_id'");
    if ($u_query && $u_row = mysqli_fetch_assoc($u_query)) {
        $_SESSION['user_image'] = $u_row['profile_image'];
    }
}

// Upload directory for QR Code and Logo images
$upload_dir = "../uploads/qr_codes/";
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Helper to check if logo column exists in payment_method table, create if missing
$check_logo_col = $conn->query("SHOW COLUMNS FROM payment_method LIKE 'logo'");
if ($check_logo_col && $check_logo_col->num_rows == 0) {
    $conn->query("ALTER TABLE payment_method ADD COLUMN logo VARCHAR(255) DEFAULT NULL AFTER qr_code");
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Add new payment method
    if (isset($_POST['add_payment_method'])) {
        $method_name    = trim($_POST['method_name']);
        $account_number = trim($_POST['account_number']);
        $account_holder = trim($_POST['account_holder']);
        $description    = trim($_POST['description']);
        $is_active      = isset($_POST['is_active']) ? 1 : 0;
        $qr_code_file   = "";
        $logo_file      = "";

        // Check if QR Code is provided
        if (!isset($_FILES['qr_code']) || $_FILES['qr_code']['error'] !== UPLOAD_ERR_OK) {
            $error = "QR Code image is required!";
        } elseif (empty($method_name) || empty($account_number) || empty($account_holder)) {
            $error = "Method Name, Account Number, and Account Holder are required!";
        } elseif (!ctype_digit($account_number)) { 
            // Validate Account Number to allow digits/integers only
            $error = "Account Number must contain numbers only!";
        } else {
            // Process QR code image upload
            $file_ext = strtolower(pathinfo($_FILES['qr_code']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];

            if (in_array($file_ext, $allowed)) {
                $qr_code_file = strtolower(str_replace(' ', '_', $method_name)) . '_qr_' . time() . '.' . $file_ext;
                move_uploaded_file($_FILES['qr_code']['tmp_name'], $upload_dir . $qr_code_file);

                // Process Logo image upload (optional)
                if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                    $logo_ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
                    if (in_array($logo_ext, $allowed)) {
                        $logo_file = strtolower(str_replace(' ', '_', $method_name)) . '_logo_' . time() . '.' . $logo_ext;
                        move_uploaded_file($_FILES['logo']['tmp_name'], $upload_dir . $logo_file);
                    }
                }

                $stmt = $conn->prepare("INSERT INTO payment_method (method_name, account_number, account_holder, is_active, description, qr_code, logo) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssisss", $method_name, $account_number, $account_holder, $is_active, $description, $qr_code_file, $logo_file);

                if ($stmt->execute()) {
                    $message = "Payment method added successfully!";
                } else {
                    $error = "Failed to add payment method!";
                }
                $stmt->close();
                header("Location: manage_payment.php");
                exit();
            } else {
                $error = "Invalid file type for QR Code. Only JPG, JPEG, PNG, and WEBP allowed.";
            }
        }
    }

    // Update existing payment method
    if (isset($_POST['update_payment_method'])) {
        $idToUpdate     = (int)$_POST['method_id'];
        $method_name    = trim($_POST['method_name']);
        $account_number = trim($_POST['account_number']);
        $account_holder = trim($_POST['account_holder']);
        $description    = trim($_POST['description']);
        $is_active      = isset($_POST['is_active']) ? 1 : 0;
        $old_qr_code    = $_POST['old_qr_code'] ?? '';
        $old_logo       = $_POST['old_logo'] ?? '';
        $qr_code_file   = $old_qr_code;
        $logo_file      = $old_logo;

        if (empty($method_name) || empty($account_number) || empty($account_holder)) {
            $error = "Method Name, Account Number, and Account Holder are required!";
        } elseif (!ctype_digit($account_number)) { 
            // Validate Account Number to allow digits/integers only
            $error = "Account Number must contain numbers only!";
        } else {
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];

            // Process QR code image update if new file is uploaded
            if (isset($_FILES['qr_code']) && $_FILES['qr_code']['error'] === UPLOAD_ERR_OK) {
                $file_ext = strtolower(pathinfo($_FILES['qr_code']['name'], PATHINFO_EXTENSION));
                if (in_array($file_ext, $allowed)) {
                    $qr_code_file = strtolower(str_replace(' ', '_', $method_name)) . '_qr_' . time() . '.' . $file_ext;
                    move_uploaded_file($_FILES['qr_code']['tmp_name'], $upload_dir . $qr_code_file);

                    // Unlink old QR code file
                    if (!empty($old_qr_code) && file_exists($upload_dir . $old_qr_code)) {
                        unlink($upload_dir . $old_qr_code);
                    }
                }
            }

            // Process Logo image update if new file is uploaded
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $logo_ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
                if (in_array($logo_ext, $allowed)) {
                    $logo_file = strtolower(str_replace(' ', '_', $method_name)) . '_logo_' . time() . '.' . $logo_ext;
                    move_uploaded_file($_FILES['logo']['tmp_name'], $upload_dir . $logo_file);

                    // Unlink old Logo file
                    if (!empty($old_logo) && file_exists($upload_dir . $old_logo)) {
                        unlink($upload_dir . $old_logo);
                    }
                }
            }

            $update = $conn->prepare("UPDATE payment_method SET method_name = ?, account_number = ?, account_holder = ?, is_active = ?, description = ?, qr_code = ?, logo = ? WHERE id = ?");
            $update->bind_param("sssisssi", $method_name, $account_number, $account_holder, $is_active, $description, $qr_code_file, $logo_file, $idToUpdate);

            if ($update->execute()) {
                header("Location: manage_payment.php");
                exit();
            } else {
                $error = "Failed to update payment method!";
            }
            $update->close();
        }
    }

    // Delete payment method
    if (isset($_POST['delete_payment_method'])) {
        $idToDelete = (int)$_POST['method_id'];

        // Retrieve QR code and logo path prior to deletion
        $stmt_img = $conn->prepare("SELECT qr_code, logo FROM payment_method WHERE id = ?");
        $stmt_img->bind_param("i", $idToDelete);
        $stmt_img->execute();
        $res_img = $stmt_img->get_result();
        if ($row = $res_img->fetch_assoc()) {
            if (!empty($row['qr_code']) && file_exists($upload_dir . $row['qr_code'])) {
                unlink($upload_dir . $row['qr_code']);
            }
            if (!empty($row['logo']) && file_exists($upload_dir . $row['logo'])) {
                unlink($upload_dir . $row['logo']);
            }
        }
        $stmt_img->close();

        $stmt = $conn->prepare("DELETE FROM payment_method WHERE id = ?");
        $stmt->bind_param("i", $idToDelete);
        $stmt->execute();
        $stmt->close();

        header("Location: manage_payment.php");
        exit();
    }
}

// Fetch all payment methods
$all_methods_query = "SELECT * FROM payment_method ORDER BY id DESC";
$result = $conn->query($all_methods_query);
$allMethods = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
$totalMethods = count($allMethods);

// Fetch alert badge notifications
$low_stock_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM Books WHERE stock < 3");
$low_stock_count = mysqli_fetch_assoc($low_stock_query)['total'] ?? 0;

$pending_payments_query = mysqli_query($conn, "SELECT id, amount, status FROM Payment WHERE status = 'pending' ORDER BY id DESC LIMIT 3");
$pending_payments_count = $pending_payments_query ? mysqli_num_rows($pending_payments_query) : 0;

// Edit mode initialization
$editMethod = null;
if (isset($_GET['edit_id'])) {
    $editId = (int)$_GET['edit_id'];
    $stmt = $conn->prepare("SELECT * FROM payment_method WHERE id = ?");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $editMethod = $res->fetch_assoc();
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Payment Methods - Online Book Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    /* Active nav link highlight */
    .header-nav a.active,
    .header-nav button.active {
        font-weight: 700;
        color: #1e293b !important;
    }

    /* Desktop: category dropdown opens on hover */
    @media (min-width: 768px) {
        .cat-dropdown:hover>.cat-dropdown-menu {
            display: block;
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Mobile menu slide animation */
    #mobileMenu {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease-in-out;
    }

    #mobileMenu.open {
        max-height: 85vh;
        overflow-y: auto;
    }

    /* Category dropdown styling */
    .cat-dropdown-menu {
        display: none;
        opacity: 0;
        transform: translateY(-2px);
        transition: opacity 0.15s ease;
    }

    .cat-dropdown.open>.cat-dropdown-menu {
        display: block;
        opacity: 1;
        transform: translateY(0);
    }

    /* Search bar styling */
    .header-search {
        background-color: #ffffff !important;
        box-shadow: none !important;
    }

    .header-search:focus {
        outline: none !important;
        box-shadow: none !important;
    }

    .header-search::placeholder {
        color: #94a3b8;
    }

    /* Hamburger menu button bar animation */
    .hamburger-bar {
        transition: transform 0.2s ease, opacity 0.2s ease;
    }

    /* Scrollbar track and thumb styling */
    ::-webkit-scrollbar {
        width: 5px;
        height: 5px;
    }

    ::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: #555;
    }
</style>
</head>

<body class="bg-gray-300 font-sans antialiased text-slate-800 h-full overflow-hidden">

    <div class="flex h-screen overflow-hidden">

        <!-- Sidebar Navigation Include -->
        <?php include '../auth/sidebar.php'; ?>

        <!-- Main Workspace Container -->
        <div class="flex-1 flex flex-col h-screen overflow-y-auto w-full custom-scrollbar">

            <!-- Header Navigation Component Include -->
            <?php 
                $page_title = "Payment Methods Management";
                include '../auth/nav.php'; 
            ?>

            <!-- Main Content Area -->
            <main class="p-4 md:p-8 max-w-[1600px] w-full mx-auto">

                <!-- Page Header -->
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
                    <div>
                        <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                            <i class="fa-solid fa-wallet text-indigo-600"></i> Payment Methods
                        </h1>
                        <p class="text-xs text-slate-500 mt-1"><?= $totalMethods; ?> payment options configured</p>
                    </div>
                    <span class="text-xs bg-indigo-50 text-indigo-700 px-3 py-1.5 rounded-full font-bold border border-indigo-100 w-fit">
                        <i class="fa-solid fa-layer-group mr-1"></i> <?= $totalMethods; ?> Total
                    </span>
                </div>

                <!-- Flash Action Status Alerts -->
                <?php if (!empty($message)): ?>
                    <div class="mb-5 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm font-semibold flex items-center gap-2 shadow-sm">
                        <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($error)): ?>
                    <div class="mb-5 p-4 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm font-semibold flex items-center gap-2 shadow-sm">
                        <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <div class="flex flex-col lg:flex-row gap-6 items-start">

                    <!-- Form Panel Component -->
                    <div class="w-full lg:w-96 bg-white p-5 rounded-2xl border border-slate-200 shadow-sm shrink-0 h-fit">
                        <div class="pb-3 border-b border-slate-100 mb-4">
                            <h3 class="font-bold text-slate-900 text-sm flex items-center">
                                <i class="fa-solid <?= $editMethod ? 'fa-pen-to-square text-amber-500' : 'fa-circle-plus text-indigo-500'; ?> mr-2"></i>
                                <?= $editMethod ? 'Update Payment Method' : 'Add New Payment Method'; ?>
                            </h3>
                        </div>

                        <form action="manage_payment.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                            <?php if ($editMethod): ?>
                                <input type="hidden" name="method_id" value="<?= $editMethod['id']; ?>">
                                <input type="hidden" name="old_qr_code" value="<?= htmlspecialchars($editMethod['qr_code'] ?? ''); ?>">
                                <input type="hidden" name="old_logo" value="<?= htmlspecialchars($editMethod['logo'] ?? ''); ?>">
                            <?php endif; ?>

                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1">Method Name</label>
                                <input type="text" name="method_name" placeholder="KBZPay, WavePay,..."
                                    value="<?= $editMethod ? htmlspecialchars($editMethod['method_name']) : ''; ?>"
                                    required
                                    class="bg-slate-50 text-xs w-full rounded-xl p-2.5 border border-slate-200 outline-none focus:border-indigo-500 focus:bg-white transition">
                            </div>

                            <!-- Payment Method Logo Upload Field -->
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1">
                                    Method Logo / Icon
                                </label>
                                <div class="flex items-center gap-3 bg-slate-50 p-2 rounded-xl border border-slate-200">
                                    <label class="relative cursor-pointer bg-white border border-slate-300 hover:bg-indigo-50 hover:text-indigo-600 text-slate-600 px-3 py-1.5 rounded-lg text-xs font-semibold shadow-xs transition flex items-center gap-1.5 shrink-0">
                                        <i class="fa-solid fa-image text-indigo-500"></i> Choose Logo
                                        <input type="file" name="logo" id="logoInput" accept="image/*" onchange="previewLogo(event)" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                                    </label>
                                    
                                    <!-- Dynamic Logo Preview Image -->
                                    <img id="logoPreview" 
                                         src="<?= ($editMethod && !empty($editMethod['logo'])) ? '../uploads/qr_codes/' . htmlspecialchars($editMethod['logo']) : ''; ?>" 
                                         alt="Logo Preview" 
                                         class="w-8 h-8 object-contain rounded-lg border border-slate-200 shrink-0 bg-white p-0.5 <?= ($editMethod && !empty($editMethod['logo'])) ? '' : 'hidden'; ?>">
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1">Account Number</label>
                                <!-- Digit Validation (Digits Only) -->
                                <input type="text" name="account_number" placeholder="09xxxxxxxxx"
                                    value="<?= $editMethod ? htmlspecialchars($editMethod['account_number']) : ''; ?>"
                                    pattern="[0-9]+"
                                    inputmode="numeric"
                                    title="Please enter numbers only"
                                    required
                                    class="bg-slate-50 text-xs w-full rounded-xl p-2.5 border border-slate-200 outline-none focus:border-indigo-500 focus:bg-white transition">
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1">Account Holder</label>
                                <input type="text" name="account_holder" placeholder=""
                                    value="<?= $editMethod ? htmlspecialchars($editMethod['account_holder']) : ''; ?>"
                                    required
                                    class="bg-slate-50 text-xs w-full rounded-xl p-2.5 border border-slate-200 outline-none focus:border-indigo-500 focus:bg-white transition">
                            </div>

                            <!-- Custom QR Code Upload Field (Required on Add) -->
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1">
                                    QR CODE IMAGE <?= !$editMethod ? '<span class="text-red-500">*</span>' : ''; ?>
                                </label>
                                <div class="flex items-center gap-3 bg-slate-50 p-2 rounded-xl border border-slate-200">
                                    <label class="relative cursor-pointer bg-white border border-slate-300 hover:bg-indigo-50 hover:text-indigo-600 text-slate-600 px-3 py-1.5 rounded-lg text-xs font-semibold shadow-xs transition flex items-center gap-1.5 shrink-0">
                                        <i class="fa-solid fa-cloud-arrow-up text-indigo-500"></i> Choose File
                                        <input type="file" name="qr_code" id="qrCodeInput" accept="image/*" onchange="previewQRCode(event)" <?= !$editMethod ? 'required' : ''; ?> class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                                    </label>
                                    
                                    <!-- Dynamic QR Preview Image -->
                                    <img id="qrPreview" 
                                         src="<?= ($editMethod && !empty($editMethod['qr_code'])) ? '../uploads/qr_codes/' . htmlspecialchars($editMethod['qr_code']) : ''; ?>" 
                                         alt="Preview" 
                                         class="w-8 h-8 object-cover rounded-lg border border-slate-200 shrink-0 <?= ($editMethod && !empty($editMethod['qr_code'])) ? '' : 'hidden'; ?>">
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1">Description</label>
                                <textarea name="description" rows="3" placeholder="Transfer notes or instructions..."
                                    class="bg-slate-50 text-xs w-full rounded-xl p-2.5 border border-slate-200 outline-none focus:border-indigo-500 focus:bg-white transition"><?= $editMethod ? htmlspecialchars($editMethod['description']) : ''; ?></textarea>
                            </div>

                            <div class="flex items-center gap-2 pt-1">
                                <input type="checkbox" name="is_active" id="is_active" value="1" 
                                    <?= ($editMethod ? ($editMethod['is_active'] ? 'checked' : '') : 'checked'); ?>
                                    class="w-4 h-4 text-indigo-600 rounded border-slate-300 focus:ring-indigo-500 cursor-pointer">
                                <label for="is_active" class="text-xs font-bold text-slate-700 cursor-pointer">Active Method</label>
                            </div>

                            <div class="pt-2 flex gap-2">
                                <?php if ($editMethod): ?>
                                    <button type="submit" name="update_payment_method"
                                        class="flex-1 bg-blue-500 hover:bg-blue-600 text-white rounded-xl text-xs font-bold py-2.5 shadow-sm transition cursor-pointer">
                                        Save Update
                                    </button>
                                    <a href="manage_payment.php"
                                        class="flex-1 text-center bg-slate-100 text-slate-600 hover:bg-slate-200 rounded-xl text-xs font-bold py-2.5 transition">
                                        Cancel
                                    </a>
                                <?php else: ?>
                                    <button type="submit" name="add_payment_method"
                                        class="w-full bg-blue-500 hover:bg-blue-600 text-white font-bold rounded-xl text-xs py-2.5 shadow-sm transition cursor-pointer">
                                        + Add Payment Method
                                    </button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>

                    <!-- Payment Method List Panel -->
                    <div class="w-full lg:flex-1 bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col justify-between">

                        <div>
                            <!-- Desktop Table View -->
                            <div class="hidden sm:block">
                                <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50">
                                    <h3 class="font-bold text-slate-700 text-sm flex items-center">
                                        <i class="fa-solid fa-list text-slate-400 mr-2"></i> Payment Methods Registry
                                    </h3>
                                </div>

                                <?php if (!empty($allMethods)): ?>
                                    <div class="overflow-x-auto w-full no-scrollbar">
                                        <table class="w-full text-left border-collapse min-w-[700px]">
                                            <thead>
                                                <tr class="bg-white text-slate-900 text-[11px] font-bold uppercase tracking-wider border-b border-slate-200">
                                                    <th class="px-6 py-3.5 w-12">No</th>
                                                    <th class="px-6 py-3.5">Method Name</th>
                                                    <th class="px-6 py-3.5">Account Info</th>
                                                    <th class="px-6 py-3.5 text-center">QR Code</th>
                                                    <th class="px-6 py-3.5">Description</th>
                                                    <th class="px-6 py-3.5 text-center">Status</th>
                                                    <th class="px-6 py-3.5 text-center">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100 text-xs text-slate-700 font-medium">
                                                <?php 
                                                $no = 1;
                                                foreach ($allMethods as $method): 
                                                ?>
                                                    <tr class="hover:bg-slate-50/60 transition <?= $editMethod && $editMethod['id'] == $method['id'] ? 'bg-amber-50/50' : ''; ?>">
                                                        <td class="px-6 py-3.5 text-slate-900 font-semibold"><?= $no++; ?></td>
                                                        <td class="px-6 py-3.5 font-bold text-slate-900 text-sm">
                                                            <div class="flex items-center gap-2.5">
                                                                <?php if (!empty($method['logo'])): ?>
                                                                    <img src="../uploads/qr_codes/<?= htmlspecialchars($method['logo']); ?>" alt="Logo" class="w-7 h-7 object-contain rounded-md border border-slate-200 bg-white p-0.5 shrink-0">
                                                                <?php else: ?>
                                                                    <div class="w-7 h-7 rounded-md bg-indigo-50 border border-indigo-100 text-indigo-600 flex items-center justify-center font-black text-xs shrink-0">
                                                                        <?= strtoupper(substr($method['method_name'], 0, 1)); ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <span><?= htmlspecialchars($method['method_name']); ?></span>
                                                            </div>
                                                        </td>
                                                        <td class="px-6 py-3.5">
                                                            <div class="font-bold text-slate-800"><?= htmlspecialchars($method['account_number']); ?></div>
                                                            <div class="text-[11px] text-slate-500"><?= htmlspecialchars($method['account_holder']); ?></div>
                                                        </td>
                                                        <td class="px-6 py-3.5 text-center">
                                                            <?php if (!empty($method['qr_code'])): ?>
                                                                <img src="../uploads/qr_codes/<?= htmlspecialchars($method['qr_code']); ?>" alt="QR" class="w-10 h-10 object-cover rounded-lg border border-slate-200 mx-auto">
                                                            <?php else: ?>
                                                                <span class="text-slate-400 text-[11px]">No QR</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="px-6 py-3.5 text-slate-600 max-w-xs truncate" title="<?= htmlspecialchars($method['description']); ?>">
                                                            <?= htmlspecialchars($method['description']); ?>
                                                        </td>
                                                        <td class="px-6 py-3.5 text-center">
                                                            <?php if ($method['is_active']): ?>
                                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">
                                                                    Active
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600">
                                                                    Inactive
                                                                </span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="px-6 py-3.5 text-center">
                                                            <div class="flex items-center justify-center space-x-2">
                                                                <a href="manage_payment.php?edit_id=<?= $method['id']; ?>"
                                                                    class="inline-flex items-center justify-center px-2.5 py-1.5 bg-blue-500 hover:bg-blue-700 text-white rounded-xl font-bold transition">
                                                                    <i class="fa-solid fa-pen-to-square mr-1"></i> Edit
                                                                </a>
                                                                <form action="manage_payment.php" method="POST" onsubmit="return confirm('Are you sure you want to completely remove this payment method?');" class="inline">
                                                                    <input type="hidden" name="method_id" value="<?= $method['id']; ?>">
                                                                    <button type="submit" name="delete_payment_method"
                                                                        class="inline-flex items-center justify-center px-2.5 py-1.5 bg-red-500 hover:bg-red-700 text-white border border-red-200/60 rounded-xl font-bold transition cursor-pointer">
                                                                        <i class="fa-solid fa-trash-can mr-1"></i> Delete
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="py-16 text-center text-slate-400 font-semibold">
                                        <i class="fa-solid fa-wallet text-4xl text-slate-200 mb-3"></i>
                                        <p>No payment methods found.</p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Mobile View Cards -->
                            <div class="sm:hidden p-4 space-y-3">
                                <?php if (!empty($allMethods)): ?>
                                    <?php 
                                    $m_no = 1;
                                    foreach ($allMethods as $method): 
                                    ?>
                                        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm space-y-3 <?= $editMethod && $editMethod['id'] == $method['id'] ? 'ring-2 ring-amber-300 bg-amber-50/30' : ''; ?>">
                                            <div class="flex items-center justify-between gap-3">
                                                <div class="flex items-center gap-2 min-w-0">
                                                    <span class="w-7 h-7 bg-slate-100 rounded-lg flex items-center justify-center text-xs font-bold text-slate-500 shrink-0">
                                                        <?= $m_no++; ?>
                                                    </span>
                                                    <?php if (!empty($method['logo'])): ?>
                                                        <img src="../uploads/qr_codes/<?= htmlspecialchars($method['logo']); ?>" alt="Logo" class="w-6 h-6 object-contain rounded border border-slate-200 bg-white p-0.5 shrink-0">
                                                    <?php endif; ?>
                                                    <span class="font-bold text-sm text-slate-900 truncate"><?= htmlspecialchars($method['method_name']); ?></span>
                                                </div>
                                                <div class="flex items-center gap-1.5 shrink-0">
                                                    <?php if ($method['is_active']): ?>
                                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">Active</span>
                                                    <?php else: ?>
                                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600">Inactive</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <div class="flex items-center gap-3 bg-slate-50 p-2.5 rounded-lg border border-slate-100">
                                                <?php if (!empty($method['qr_code'])): ?>
                                                    <img src="../uploads/qr_codes/<?= htmlspecialchars($method['qr_code']); ?>" alt="QR" class="w-12 h-12 object-cover rounded-lg border border-slate-200 shrink-0">
                                                <?php endif; ?>
                                                <div class="text-xs text-slate-600 space-y-0.5">
                                                    <div><span class="font-bold text-slate-800">Acc No:</span> <?= htmlspecialchars($method['account_number']); ?></div>
                                                    <div><span class="font-bold text-slate-800">Holder:</span> <?= htmlspecialchars($method['account_holder']); ?></div>
                                                    <?php if(!empty($method['description'])): ?>
                                                        <div class="text-slate-500 text-[11px]"><?= htmlspecialchars($method['description']); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <div class="flex items-center justify-end gap-2 pt-1 border-t border-slate-100">
                                                <a href="manage_payment.php?edit_id=<?= $method['id']; ?>"
                                                    class="px-3 py-1.5 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-xs font-bold flex items-center gap-1">
                                                    <i class="fa-solid fa-pen-to-square"></i> Edit
                                                </a>
                                                <form action="manage_payment.php" method="POST" onsubmit="return confirm('Delete this payment method?');" class="inline">
                                                    <input type="hidden" name="method_id" value="<?= $method['id']; ?>">
                                                    <button type="submit" name="delete_payment_method"
                                                        class="px-3 py-1.5 bg-red-50 text-red-500 rounded-lg text-xs font-bold flex items-center gap-1">
                                                        <i class="fa-solid fa-trash-can"></i> Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="bg-white p-12 rounded-xl border border-slate-100 shadow-sm text-center">
                                        <i class="fa-solid fa-wallet text-4xl text-slate-200 mb-3"></i>
                                        <p class="text-slate-400 text-sm font-medium">No payment methods created yet.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Interface Controller Scripts -->
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('-translate-x-full');
        }

        function toggleNotificationDropdown(e) {
            e.stopPropagation();
            document.getElementById('notiDropdown').classList.toggle('hidden');
            document.getElementById('profileDropdown').classList.add('hidden');
        }

        function toggleProfileDropdown(e) {
            e.stopPropagation();
            document.getElementById('profileDropdown').classList.toggle('hidden');
            document.getElementById('notiDropdown').classList.add('hidden');
        }

        // Live Image Preview for QR Code Input
        function previewQRCode(event) {
            const input = event.target;
            const preview = document.getElementById('qrPreview');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.classList.remove('hidden');
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Live Image Preview for Logo Input
        function previewLogo(event) {
            const input = event.target;
            const preview = document.getElementById('logoPreview');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.classList.remove('hidden');
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        window.addEventListener('click', function(e) {
            const notiDropdown = document.getElementById('notiDropdown');
            const profileDropdown = document.getElementById('profileDropdown');

            if (notiDropdown && !notiDropdown.contains(e.target) && !document.getElementById('notiBtn').contains(e.target)) {
                notiDropdown.classList.add('hidden');
            }
            if (profileDropdown && !profileDropdown.contains(e.target) && !document.getElementById('profileBtn').contains(e.target)) {
                profileDropdown.classList.add('hidden');
            }
        });
    </script>

</body>

</html>