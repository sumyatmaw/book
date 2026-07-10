<?php
// admin/delivery-manage.php

session_start();

// စမ်းသပ်ရန် အော်ဒါများစာရင်း (တကယ်တမ်းတွင် Database မှ SELECT * FROM orders WHERE status != 'Pending' ဟု ခေါ်ယူရပါမည်)
// အလွယ်တကူ စမ်းသပ်ပြီး အလုပ်လုပ်ပုံ မြင်ရအောင် Session ကို အသုံးပြုထားပါတယ်
if (!isset($_SESSION['admin_orders'])) {
    $_SESSION['admin_orders'] = [
        [
            'order_id' => 'ORD-8942',
            'customer' => 'မောင်မောင်',
            'phone' => '09123456789',
            'address' => 'အမှတ် (၁၂)၊ ဗဟိုလမ်း၊ ကမာရွတ်မြို့နယ်၊ ရန်ကုန်။',
            'amount' => 31500,
            'status' => 'Paid' // Status များ - Paid (ငွေချေပြီး/ပြင်ဆင်ဆဲ), Shipped (ပို့ဆောင်ဆဲ), Delivered (ရောက်ရှိပြီ)
        ],
        [
            'order_id' => 'ORD-8941',
            'customer' => 'မစုစု',
            'phone' => '09987654321',
            'address' => 'တိုက် ၄၊ အခန်း ၂၀၂၊ လှိုင်ရတနာအိမ်ရာ၊ လှိုင်မြို့နယ်။',
            'amount' => 12500,
            'status' => 'Shipped'
        ]
    ];
}

// Admin က "Update Status" Button ကို နှိပ်လိုက်သောအခါ လုပ်ဆောင်မည့် Logic
if (isset($_POST['update_status'])) {
    $order_id = $_POST['order_id'];
    $new_status = $_POST['status'];
    
    // Session ထဲက အော်ဒါကို လိုက်ရှာပြီး Status ပြောင်းပေးခြင်း
    foreach ($_SESSION['admin_orders'] as $key => $order) {
        if ($order['order_id'] == $order_id) {
            $_SESSION['admin_orders'][$key]['status'] = $new_status;
            break;
        }
    }
    
    /* 👉 AI နာမည်ဖြင့် မှတ်ချက် - တကယ့် Database တွင် အောက်ပါ SQL Query ကို ပတ်ရပါမည်။
    
    $query = "UPDATE orders SET status = '$new_status' WHERE id = '$order_id'";
    mysqli_query($conn, $query);
    */
    
    // အောင်မြင်ကြောင်း သဝဏ်လွှာနှင့်အတူ Page ကို Refresh ပြန်လုပ်ခြင်း
    header("Location: delivery_manage.php?msg=updated");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Deliveries - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans min-h-screen">

    <div class="max-w-6xl mx-auto px-4 py-8">
        
        <div class="mb-6">
            <a href="dashboard.php" class="text-sm font-semibold text-indigo-600 hover:underline">⬅️ Back to Dashboard</a>
        </div>

        <?php if (isset($_GET['msg']) && $_GET['msg'] == 'updated'): ?>
            <div class="mb-6 p-4 bg-sky-50 border border-sky-200 text-sky-700 rounded-xl font-bold text-sm shadow-sm">
                ✓ Delivery Status ကို အောင်မြင်စွာ ပြောင်းလဲလိုက်ပါပြီ။ Customer ဘက်တွင် ချက်ချင်း မြင်တွေ့ရပါမည်။
            </div>
        <?php endif; ?>

        <div class="mb-8 bg-white p-6 rounded-2xl border border-gray-200 shadow-sm">
            <h1 class="text-2xl font-black text-gray-900">🚚 ပို့ဆောင်မှုစနစ် စီမံခန့်ခွဲခြင်း (Delivery Management)</h1>
            <p class="text-xs text-gray-500 mt-1">ငွေချေပြီးသား အော်ဒါများကို ပစ္စည်းထုပ်ပိုးပြီး Delivery အခြေအနေ လိုက်လံအပ်နှံ/ပြောင်းလဲပေးပါ။</p>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-50 text-xs font-bold text-gray-500 uppercase border-b border-gray-200">
                            <th class="p-4">Order ID</th>
                            <th class="p-4">ဝယ်ယူသူ / ဖုန်း</th>
                            <th class="p-4">ပို့ဆောင်မည့် လိပ်စာ</th>
                            <th class="p-4">တန်ဖိုး</th>
                            <th class="p-4">လက်ရှိအခြေအနေ</th>
                            <th class="p-4 text-center">အခြေအနေပြောင်းရန်</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm divide-y divide-gray-100">
                        <?php if(empty($_SESSION['admin_orders'])): ?>
                            <tr>
                                <td colspan="6" class="p-8 text-center text-gray-400 font-medium">ပို့ဆောင်ရန် အော်ဒါစာရင်း မရှိသေးပါ။</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($_SESSION['admin_orders'] as $order): ?>
                            <tr class="hover:bg-gray-50/50 transition">
                                <td class="p-4 font-mono font-bold text-gray-700"><?= $order['order_id'] ?></td>
                                
                                <td class="p-4">
                                    <span class="font-semibold text-gray-800 block"><?= $order['customer'] ?></span>
                                    <span class="text-xs text-gray-400 font-mono"><?= $order['phone'] ?></span>
                                </td>
                                
                                <td class="p-4 text-gray-600 max-w-xs truncate" title="<?= $order['address'] ?>">
                                    <?= $order['address'] ?>
                                </td>
                                
                                <td class="p-4 text-indigo-600 font-semibold"><?= number_format($order['amount']) ?> ကျပ်</td>
                                
                                <td class="p-4">
                                    <span class="px-2.5 py-1 text-xs font-bold rounded-full 
                                        <?= $order['status'] == 'Delivered' ? 'bg-green-100 text-green-700' : ($order['status'] == 'Shipped' ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700') ?>">
                                        <?= $order['status'] == 'Paid' ? 'Preparing (ပြင်ဆင်ဆဲ)' : $order['status'] ?>
                                    </span>
                                </td>
                                
                                <td class="p-4 text-center">
                                    <form action="" method="POST" class="flex items-center justify-center gap-2">
                                        <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                        
                                        <select name="status" class="bg-gray-50 border border-gray-300 text-gray-700 text-xs rounded-xl focus:ring-indigo-500 focus:border-indigo-500 p-2 outline-none">
                                            <option value="Paid" <?= $order['status'] == 'Paid' ? 'selected' : '' ?>>Preparing (ပြင်ဆင်ဆဲ)</option>
                                            <option value="Shipped" <?= $order['status'] == 'Shipped' ? 'selected' : '' ?>>Shipped (ပို့ဆောင်ဆဲ)</option>
                                            <option value="Delivered" <?= $order['status'] == 'Delivered' ? 'selected' : '' ?>>Delivered (ရောက်ရှိပြီ)</option>
                                        </select>
                                        
                                        <button type="submit" name="update_status" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs px-3 py-2 rounded-xl transition shadow-sm">
                                            Update
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</body>
</html>