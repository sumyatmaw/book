<?php
// Session and Database Connection Validation
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/db.php';

// Restrict access to Admins only
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Sidebar Active Link Indicator
$current_page = 'reports'; // Set active menu for sidebar

// Fetch Admin Details from Session
$admin_name = $_SESSION['user_name'] ?? 'Admin User';
$admin_email = $_SESSION['user_email'] ?? 'admin@bookshop.com';
$admin_initial = strtoupper(substr($admin_name, 0, 1));

// Sync Profile Image from Database if not available in current session
if (!isset($_SESSION['user_image']) && isset($conn) && isset($_SESSION['user_id'])) {
    $uid = mysqli_real_escape_string($conn, $_SESSION['user_id']);
    $u_query = mysqli_query($conn, "SELECT profile_image FROM Users WHERE id = '$uid'");
    if ($u_query && $u_row = mysqli_fetch_assoc($u_query)) {
        $_SESSION['user_image'] = $u_row['profile_image'];
    }
}

/*
|--------------------------------------------------------------------------
| Report Settings & Input Sanitization
|--------------------------------------------------------------------------
*/
$report_type = $_GET['report_type'] ?? 'daily';

// Default values or user inputs
$selected_date  = !empty($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$selected_week  = !empty($_GET['week']) ? $_GET['week'] : date('Y-\WW');
$selected_month = !empty($_GET['month']) ? $_GET['month'] : date('Y-m');
$selected_year  = !empty($_GET['year']) ? $_GET['year'] : date('Y');

$total_orders = 0;
$total_sales  = 0;

$report_rows = [];

$report_title = "Daily Sales Report";
$report_description = "View completed orders and total sales performance for a selected day.";

/*
|--------------------------------------------------------------------------
| DAILY REPORT
|--------------------------------------------------------------------------
*/
if ($report_type === 'daily') {
    $report_title = "Daily Sales Report";
    $report_description = "View completed orders and total sales performance for a selected day.";

    $sql = "
        SELECT
            COUNT(DISTINCT o.id) AS total_orders,
            COALESCE(SUM(oi.price * oi.quantity), 0) AS total_sales
        FROM Orders o
        JOIN Order_item oi ON o.id = oi.order_id
        WHERE o.status = 'completed'
        AND DATE(o.created_at) = ?
    ";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $selected_date);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $total_orders = (int)($row['total_orders'] ?? 0);
        $total_sales  = (float)($row['total_sales'] ?? 0);
        $stmt->close();
    }

    $detail_sql = "
        SELECT
            o.id,
            o.order_number,
            o.status,
            o.created_at,
            COALESCE((SELECT SUM(price * quantity) FROM Order_item WHERE order_id = o.id), o.total_amount) AS total_amount
        FROM Orders o
        WHERE o.status = 'completed'
        AND DATE(o.created_at) = ?
        ORDER BY o.created_at DESC
    ";

    $detail_stmt = $conn->prepare($detail_sql);
    if ($detail_stmt) {
        $detail_stmt->bind_param("s", $selected_date);
        $detail_stmt->execute();
        $detail_result = $detail_stmt->get_result();
        while ($detail_row = $detail_result->fetch_assoc()) {
            $report_rows[] = $detail_row;
        }
        $detail_stmt->close();
    }
}

/*
|--------------------------------------------------------------------------
| WEEKLY REPORT
|--------------------------------------------------------------------------
*/
elseif ($report_type === 'weekly') {
    $report_title = "Weekly Sales Report";
    $report_description = "View sales performance breakdown from Monday to Sunday.";

    if (preg_match('/^(\d{4})-W(\d{2})$/', $selected_week, $matches)) {
        $week_year = (int)$matches[1];
        $week_number = (int)$matches[2];
        $date_obj = new DateTime();
        $date_obj->setISODate($week_year, $week_number);
        $week_start = $date_obj->format('Y-m-d');
        $date_obj->modify('+6 days');
        $week_end = $date_obj->format('Y-m-d');
    } else {
        $date_obj = new DateTime();
        $date_obj->modify('monday this week');
        $week_start = $date_obj->format('Y-m-d');
        $date_obj->modify('+6 days');
        $week_end = $date_obj->format('Y-m-d');
    }

    $sql = "
        SELECT
            COUNT(DISTINCT o.id) AS total_orders,
            COALESCE(SUM(oi.price * oi.quantity), 0) AS total_sales
        FROM Orders o
        JOIN Order_item oi ON o.id = oi.order_id
        WHERE o.status = 'completed'
        AND DATE(o.created_at) BETWEEN ? AND ?
    ";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ss", $week_start, $week_end);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $total_orders = (int)($row['total_orders'] ?? 0);
        $total_sales  = (float)($row['total_sales'] ?? 0);
        $stmt->close();
    }

    $detail_sql = "
        SELECT
            DATE(o.created_at) AS report_date,
            COUNT(DISTINCT o.id) AS orders_count,
            COALESCE(SUM(oi.price * oi.quantity), 0) AS sales_amount
        FROM Orders o
        JOIN Order_item oi ON o.id = oi.order_id
        WHERE o.status = 'completed'
        AND DATE(o.created_at) BETWEEN ? AND ?
        GROUP BY DATE(o.created_at)
        ORDER BY report_date ASC
    ";

    $detail_stmt = $conn->prepare($detail_sql);
    if ($detail_stmt) {
        $detail_stmt->bind_param("ss", $week_start, $week_end);
        $detail_stmt->execute();
        $detail_result = $detail_stmt->get_result();
        while ($detail_row = $detail_result->fetch_assoc()) {
            $report_rows[] = $detail_row;
        }
        $detail_stmt->close();
    }
}

/*
|--------------------------------------------------------------------------
| MONTHLY REPORT
|--------------------------------------------------------------------------
*/
elseif ($report_type === 'monthly') {
    $report_title = "Monthly Sales Report";
    $report_description = "View daily sales performance for a selected month.";

    if (preg_match('/^\d{4}-\d{2}$/', $selected_month)) {
        $month_start = $selected_month . "-01";
        $month_end   = date('Y-m-t', strtotime($month_start));
    } else {
        $month_start = date('Y-m-01');
        $month_end   = date('Y-m-t');
    }

    $sql = "
        SELECT
            COUNT(DISTINCT o.id) AS total_orders,
            COALESCE(SUM(oi.price * oi.quantity), 0) AS total_sales
        FROM Orders o
        JOIN Order_item oi ON o.id = oi.order_id
        WHERE o.status = 'completed'
        AND DATE(o.created_at) BETWEEN ? AND ?
    ";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ss", $month_start, $month_end);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $total_orders = (int)($row['total_orders'] ?? 0);
        $total_sales  = (float)($row['total_sales'] ?? 0);
        $stmt->close();
    }

    $detail_sql = "
        SELECT
            DATE(o.created_at) AS report_date,
            COUNT(DISTINCT o.id) AS orders_count,
            COALESCE(SUM(oi.price * oi.quantity), 0) AS sales_amount
        FROM Orders o
        JOIN Order_item oi ON o.id = oi.order_id
        WHERE o.status = 'completed'
        AND DATE(o.created_at) BETWEEN ? AND ?
        GROUP BY DATE(o.created_at)
        ORDER BY report_date ASC
    ";

    $detail_stmt = $conn->prepare($detail_sql);
    if ($detail_stmt) {
        $detail_stmt->bind_param("ss", $month_start, $month_end);
        $detail_stmt->execute();
        $detail_result = $detail_stmt->get_result();
        while ($detail_row = $detail_result->fetch_assoc()) {
            $report_rows[] = $detail_row;
        }
        $detail_stmt->close();
    }
}

/*
|--------------------------------------------------------------------------
| YEARLY REPORT
|--------------------------------------------------------------------------
*/
elseif ($report_type === 'yearly') {
    $report_title = "Yearly Sales Report";
    $report_description = "View monthly sales performance for a selected year.";

    if (preg_match('/^\d{4}$/', $selected_year)) {
        $year = $selected_year;
    } else {
        $year = date('Y');
        $selected_year = $year;
    }

    $year_start = $year . "-01-01";
    $year_end   = $year . "-12-31";

    $sql = "
        SELECT
            COUNT(DISTINCT o.id) AS total_orders,
            COALESCE(SUM(oi.price * oi.quantity), 0) AS total_sales
        FROM Orders o
        JOIN Order_item oi ON o.id = oi.order_id
        WHERE o.status = 'completed'
        AND DATE(o.created_at) BETWEEN ? AND ?
    ";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ss", $year_start, $year_end);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $total_orders = (int)($row['total_orders'] ?? 0);
        $total_sales  = (float)($row['total_sales'] ?? 0);
        $stmt->close();
    }

    $detail_sql = "
        SELECT
            DATE_FORMAT(o.created_at, '%Y-%m') AS report_month,
            COUNT(DISTINCT o.id) AS orders_count,
            COALESCE(SUM(oi.price * oi.quantity), 0) AS sales_amount
        FROM Orders o
        JOIN Order_item oi ON o.id = oi.order_id
        WHERE o.status = 'completed'
        AND DATE(o.created_at) BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(o.created_at, '%Y-%m')
        ORDER BY report_month ASC
    ";

    $detail_stmt = $conn->prepare($detail_sql);
    if ($detail_stmt) {
        $detail_stmt->bind_param("ss", $year_start, $year_end);
        $detail_stmt->execute();
        $detail_result = $detail_stmt->get_result();
        while ($detail_row = $detail_result->fetch_assoc()) {
            $report_rows[] = $detail_row;
        }
        $detail_stmt->close();
    }
}

// Format Currency Helper Function
function formatMoney($amount) {
    return number_format((float)$amount, 0);
}

// Display Period String Formatting
$display_period = "";
if ($report_type === 'daily') {
    $display_period = date('d F Y', strtotime($selected_date));
} elseif ($report_type === 'weekly') {
    $display_period = date('d M Y', strtotime($week_start)) . " - " . date('d M Y', strtotime($week_end));
} elseif ($report_type === 'monthly') {
    $display_period = date('F Y', strtotime($month_start));
} elseif ($report_type === 'yearly') {
    $display_period = $selected_year;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Reports - BookShop Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: white !important;
            }
            .print-card {
                box-shadow: none !important;
                border: 1px solid #e2e8f0 !important;
            }
        }
    </style>
</head>

<body class="bg-gray-100 sm:bg-gray-300 font-sans antialiased text-slate-800">

    <div class="flex h-screen overflow-hidden bg-white">

        <!-- Dynamic Sidebar Include -->
        <?php include '../auth/sidebar.php'; ?>

        <!-- Outer wrapper -->
        <div class="flex-1 flex flex-col h-screen overflow-y-auto w-full bg-gray-100 sm:bg-gray-300">

            <!-- Dynamic Header Navigation Include -->
            <?php
            $page_title = "Sales Analytics Reports";
            include '../auth/nav.php';
            ?>

            <!-- Main Content Area -->
            <main class="p-3 sm:p-4 md:p-8 space-y-4 sm:space-y-6 md:space-y-8 max-w-[1600px] w-full mx-auto bg-gray-100 sm:bg-gray-300 flex-1">

                <!-- Header Title & Print Action Banner -->
                <div class="bg-white p-4 sm:p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="space-y-1">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-100 text-amber-800 text-xs font-bold uppercase tracking-wider">
                            <i class="fa-solid fa-chart-pie"></i> Executive Overview
                        </span>
                        <h1 class="text-xl sm:text-2xl md:text-3xl font-extrabold text-slate-900 tracking-tight">
                            <?= htmlspecialchars($report_title); ?>
                        </h1>
                        <p class="text-xs sm:text-sm text-slate-500">
                            <?= htmlspecialchars($report_description); ?>
                        </p>
                    </div>

                    <div class="flex items-center gap-3 no-print">
                        <button onclick="window.print()" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-slate-900 hover:bg-slate-800 text-white px-4 py-2.5 rounded-xl font-bold text-xs shadow-sm transition">
                            <i class="fa-solid fa-print"></i>
                            <span>Print Report</span>
                        </button>
                    </div>
                </div>

                <!-- Report Period Tabs -->
                <div class="bg-slate-200/80 p-1.5 rounded-2xl no-print border border-slate-300/60 shadow-inner">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-1.5">
                        <a href="?report_type=daily&date=<?= urlencode($selected_date); ?>" class="flex items-center justify-center gap-2 px-3 sm:px-4 py-2.5 rounded-xl text-xs font-bold transition <?= $report_type === 'daily' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900 hover:bg-white/50' ?>">
                            <i class="fa-solid fa-calendar-day <?= $report_type === 'daily' ? 'text-amber-500' : '' ?>"></i> Daily
                        </a>
                        <a href="?report_type=weekly&week=<?= urlencode($selected_week); ?>" class="flex items-center justify-center gap-2 px-3 sm:px-4 py-2.5 rounded-xl text-xs font-bold transition <?= $report_type === 'weekly' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900 hover:bg-white/50' ?>">
                            <i class="fa-solid fa-calendar-week <?= $report_type === 'weekly' ? 'text-amber-500' : '' ?>"></i> Weekly
                        </a>
                        <a href="?report_type=monthly&month=<?= urlencode($selected_month); ?>" class="flex items-center justify-center gap-2 px-3 sm:px-4 py-2.5 rounded-xl text-xs font-bold transition <?= $report_type === 'monthly' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900 hover:bg-white/50' ?>">
                            <i class="fa-solid fa-calendar-days <?= $report_type === 'monthly' ? 'text-amber-500' : '' ?>"></i> Monthly
                        </a>
                        <a href="?report_type=yearly&year=<?= urlencode($selected_year); ?>" class="flex items-center justify-center gap-2 px-3 sm:px-4 py-2.5 rounded-xl text-xs font-bold transition <?= $report_type === 'yearly' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900 hover:bg-white/50' ?>">
                            <i class="fa-solid fa-calendar <?= $report_type === 'yearly' ? 'text-amber-500' : '' ?>"></i> Yearly
                        </a>
                    </div>
                </div>

                <!-- Filter Controls Card -->
                <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200 shadow-sm no-print">
                    <div class="flex items-center gap-2.5 mb-4 border-b border-slate-100 pb-3">
                        <div class="w-8 h-8 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-filter text-xs"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-sm text-slate-900">Filter Parameters</h3>
                            <p class="text-xs text-slate-500">Select target timeline to calculate report</p>
                        </div>
                    </div>

                    <form method="GET" action="" class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
                        <input type="hidden" name="report_type" value="<?= htmlspecialchars($report_type); ?>">

                        <?php if ($report_type === 'daily'): ?>
                            <div>
                                <label for="date" class="block text-xs font-bold text-slate-700 mb-1.5 uppercase">Select Date</label>
                                <input type="date" id="date" name="date" value="<?= htmlspecialchars($selected_date); ?>" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs font-semibold text-slate-800 focus:outline-none focus:border-amber-400 focus:bg-white transition">
                            </div>
                        <?php elseif ($report_type === 'weekly'): ?>
                            <div>
                                <label for="week" class="block text-xs font-bold text-slate-700 mb-1.5 uppercase">Select Week</label>
                                <input type="week" id="week" name="week" value="<?= htmlspecialchars($selected_week); ?>" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs font-semibold text-slate-800 focus:outline-none focus:border-amber-400 focus:bg-white transition">
                            </div>
                        <?php elseif ($report_type === 'monthly'): ?>
                            <div>
                                <label for="month" class="block text-xs font-bold text-slate-700 mb-1.5 uppercase">Select Month</label>
                                <input type="month" id="month" name="month" value="<?= htmlspecialchars($selected_month); ?>" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs font-semibold text-slate-800 focus:outline-none focus:border-amber-400 focus:bg-white transition">
                            </div>
                        <?php elseif ($report_type === 'yearly'): ?>
                            <div>
                                <label for="year" class="block text-xs font-bold text-slate-700 mb-1.5 uppercase">Select Year</label>
                                <input type="number" id="year" name="year" min="2000" max="2100" value="<?= htmlspecialchars($selected_year); ?>" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs font-semibold text-slate-800 focus:outline-none focus:border-amber-400 focus:bg-white transition">
                            </div>
                        <?php endif; ?>

                        <div>
                            <button type="submit" class="w-full bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold px-4 py-2.5 rounded-xl text-xs transition shadow-sm flex items-center justify-center gap-2">
                                <i class="fa-solid fa-sliders"></i> Generate Report
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Summary Analytics Stat Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
                    <!-- Orders Count Card -->
                    <div class="bg-white p-5 sm:p-6 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
                        <div class="space-y-1">
                            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Completed Orders</span>
                            <h3 class="text-2xl md:text-3xl font-extrabold text-slate-900"><?= number_format($total_orders); ?> <span class="text-xs font-bold text-slate-500">Orders</span></h3>
                            <p class="text-xs text-slate-500 flex items-center gap-1.5 mt-1">
                                <i class="fa-regular fa-clock text-amber-500"></i> Period: <strong class="text-slate-800"><?= htmlspecialchars($display_period); ?></strong>
                            </p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-lg font-bold shrink-0 border border-blue-100">
                            <i class="fa-solid fa-bag-shopping"></i>
                        </div>
                    </div>

                    <!-- Revenue Sales Card -->
                    <div class="bg-slate-900 text-white p-5 sm:p-6 rounded-2xl shadow-md flex items-center justify-between relative overflow-hidden">
                        <div class="space-y-1 z-10">
                            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Sales Calculated</span>
                            <h3 class="text-2xl md:text-3xl font-extrabold text-amber-400"><?= formatMoney($total_sales); ?> <span class="text-xs font-bold text-white">ကျပ်</span></h3>
                            <p class="text-xs text-slate-400 flex items-center gap-1.5 mt-1">
                                <i class="fa-solid fa-circle-check text-emerald-400"></i> Verified completed revenue (Items Subtotal Only)
                            </p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-amber-400 text-slate-950 flex items-center justify-center text-lg font-bold shrink-0 z-10 shadow-sm">
                            <i class="fa-solid fa-money-bill-trend-up"></i>
                        </div>
                    </div>
                </div>

                <!-- Detailed Breakdown Table -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden print-card">
                    <div class="px-4 sm:px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex flex-col sm:flex-row sm:items-center justify-between gap-2 sm:gap-0">
                        <div>
                            <h3 class="font-bold text-slate-900 text-base">Breakdown Details</h3>
                            <p class="text-xs text-slate-500">Individual transaction breakdown for selected period</p>
                        </div>
                        <span class="self-start sm:self-auto px-2.5 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-lg text-xs font-bold">
                            <i class="fa-solid fa-check text-[10px] mr-1"></i> Completed Sales Only
                        </span>
                    </div>

                    <div class="overflow-x-auto no-scrollbar">
                        <table class="w-full text-left border-collapse min-w-[500px]">
                            <thead>
                                <tr class="bg-slate-100 text-slate-700 text-xs font-bold uppercase tracking-wider border-b border-slate-200">
                                    <?php if ($report_type === 'daily'): ?>
                                        <th class="px-4 sm:px-6 py-3.5">Order Number</th>
                                        <th class="px-4 sm:px-6 py-3.5">Date & Time</th>
                                        <th class="px-4 sm:px-6 py-3.5 text-right">Amount</th>
                                        <th class="px-4 sm:px-6 py-3.5 text-center">Status</th>
                                    <?php elseif ($report_type === 'weekly' || $report_type === 'monthly'): ?>
                                        <th class="px-4 sm:px-6 py-3.5">Date</th>
                                        <th class="px-4 sm:px-6 py-3.5 text-center">Completed Orders</th>
                                        <th class="px-4 sm:px-6 py-3.5 text-right">Total Revenue</th>
                                    <?php elseif ($report_type === 'yearly'): ?>
                                        <th class="px-4 sm:px-6 py-3.5">Month</th>
                                        <th class="px-4 sm:px-6 py-3.5 text-center">Completed Orders</th>
                                        <th class="px-4 sm:px-6 py-3.5 text-right">Total Revenue</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-xs text-slate-700 font-medium">
                                <?php if (!empty($report_rows)): ?>
                                    <?php foreach ($report_rows as $row): ?>
                                        <tr class="hover:bg-amber-50/40 transition">
                                            <?php if ($report_type === 'daily'): ?>
                                                <td class="px-4 sm:px-6 py-4 font-mono font-bold text-slate-900 whitespace-nowrap">
                                                    #<?= htmlspecialchars($row['order_number'] ?? $row['id']); ?>
                                                </td>
                                                <td class="px-4 sm:px-6 py-4 text-slate-600 whitespace-nowrap">
                                                    <?= date('d M Y, h:i A', strtotime($row['created_at'])); ?>
                                                </td>
                                                <td class="px-4 sm:px-6 py-4 text-right font-extrabold text-slate-900 whitespace-nowrap">
                                                    <?= formatMoney($row['total_amount']); ?> ကျပ်
                                                </td>
                                                <td class="px-4 sm:px-6 py-4 text-center whitespace-nowrap">
                                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-800 text-[11px] font-bold">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Completed
                                                    </span>
                                                </td>
                                            <?php elseif ($report_type === 'weekly' || $report_type === 'monthly'): ?>
                                                <td class="px-4 sm:px-6 py-4 font-bold text-slate-800 whitespace-nowrap">
                                                    <?= date('d M Y', strtotime($row['report_date'])); ?>
                                                </td>
                                                <td class="px-4 sm:px-6 py-4 text-center whitespace-nowrap">
                                                    <span class="px-2.5 py-1 rounded-lg bg-blue-50 text-blue-700 font-bold border border-blue-100">
                                                        <?= number_format($row['orders_count']); ?>
                                                    </span>
                                                </td>
                                                <td class="px-4 sm:px-6 py-4 text-right font-extrabold text-slate-900 whitespace-nowrap">
                                                    <?= formatMoney($row['sales_amount']); ?> ကျပ်
                                                </td>
                                            <?php elseif ($report_type === 'yearly'): ?>
                                                <td class="px-4 sm:px-6 py-4 font-bold text-slate-800 whitespace-nowrap">
                                                    <?= date('F Y', strtotime($row['report_month'] . '-01')); ?>
                                                </td>
                                                <td class="px-4 sm:px-6 py-4 text-center whitespace-nowrap">
                                                    <span class="px-2.5 py-1 rounded-lg bg-blue-50 text-blue-700 font-bold border border-blue-100">
                                                        <?= number_format($row['orders_count']); ?>
                                                    </span>
                                                </td>
                                                <td class="px-4 sm:px-6 py-4 text-right font-extrabold text-slate-900 whitespace-nowrap">
                                                    <?= formatMoney($row['sales_amount']); ?> ကျပ်
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="px-6 py-12 text-center text-slate-400">
                                            <i class="fa-solid fa-chart-line text-2xl mb-2 text-slate-300"></i>
                                            <p class="font-bold text-slate-600 text-sm">No Sales Data Found</p>
                                            <p class="text-xs">There are no completed orders recorded for this period.</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="px-4 sm:px-6 py-4 bg-slate-50 border-t border-slate-100 flex items-center justify-between text-xs font-bold text-slate-800">
                        <span>Total Revenue Generated:</span>
                        <span class="text-sm text-amber-600 font-extrabold"><?= formatMoney($total_sales); ?> ကျပ်</span>
                    </div>
                </div>

            </main>
        </div>
    </div>
    <script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        if (sidebar) sidebar.classList.toggle('-translate-x-full');
    }

    function toggleNotificationDropdown(e) {
        e.stopPropagation();
        const notiDropdown = document.getElementById('notiDropdown');
        const profileDropdown = document.getElementById('profileDropdown');
        if (notiDropdown) notiDropdown.classList.toggle('hidden');
        if (profileDropdown) profileDropdown.classList.add('hidden');
    }

    function toggleProfileDropdown(e) {
        e.stopPropagation();
        const profileDropdown = document.getElementById('profileDropdown');
        const notiDropdown = document.getElementById('notiDropdown');
        if (profileDropdown) profileDropdown.classList.toggle('hidden');
        if (notiDropdown) notiDropdown.classList.add('hidden');
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
</script>
</body>
</html>