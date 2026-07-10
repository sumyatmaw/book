<?php
// includes/db.php ကို ချိတ်ဆက်ပါ
// include '../includes/db.php';

// စမ်းသပ်ရန် Dummy Data (တကယ်တမ်းတွင် SELECT * FROM payments WHERE status = 'Pending' ဟုဆွဲရမည်)
// အလွယ်တကူ စမ်းသပ်နိုင်ရန် Session ဖြင့် Revenue စာရင်းကို ခေတ္တထိန်းသိမ်းထားခြင်း
session_start();
if (!isset($_SESSION['admin_revenue'])) {
    $_SESSION['admin_revenue'] = 450000; // ကနဦး ဝင်ငွေ
}

$pending_payments = [
    [
        'payment_id' => 1,
        'order_id' => 'ORD-8942',
        'customer' => 'မောင်မောင်',
        'amount' => 31500,
        'method' => 'KBZPay',
        'transaction_id' => '202607061122',
        'slip_image' => 'https://placeholder.co/400x600?text=KPay+Slip+Demo' // စမ်းသပ်ရန် ပုံနမူနာ
    ],
    [
        'payment_id' => 2,
        'order_id' => 'ORD-8941',
        'customer' => 'မစုစု',
        'amount' => 12500,
        'method' => 'WaveMoney',
        'transaction_id' => 'WAVE99887766',
        'slip_image' => 'https://placeholder.co/400x600?text=Wave+Slip+Demo'
    ]
];

// Admin က "Approve" Button ကို နှိပ်လိုက်သောအခါ လုပ်ဆောင်မည့် Logic
if (isset($_POST['approve_payment'])) {
    $p_id = $_POST['payment_id'];
    $amount = $_POST['amount'];
    $order_id = $_POST['order_id'];
    
    // 💰 ၁။ Admin ၏ စုစုပေါင်းဝင်ငွေ (Revenue) ကို တိုးမြှင့်ခြင်း
    $_SESSION['admin_revenue'] += $amount;
    
    /* 👉 AI နာမည်ဖြင့် မှတ်ချက် - တကယ့် Database တွင် အောက်ပါ SQL Query များ ပတ်ရပါမည်။
    
    // (က) Payment Status ကို Success ပြောင်းခြင်း
    $query1 = "UPDATE payments SET status = 'Success' WHERE id = '$p_id'";
    mysqli_query($conn, $query1);
    
    // (ခ) Order Status ကို Paid (ငွေချေပြီး - Delivery ပို့ရန်ပြင်ဆင်) ပြောင်းခြင်း
    $query2 = "UPDATE orders SET status = 'Paid' WHERE id = '$order_id'";
    mysqli_query($conn, $query2);
    
    // (ဂ) Admin Wallet တွင် ငွေသွားတိုးခြင်း
    $query3 = "UPDATE admin_wallets SET total_balance = total_balance + '$amount' WHERE id = 1";
    mysqli_query($conn, $query3);
    */
    
    // အောင်မြင်ကြောင်း သဝဏ်လွှာနှင့်အတူ Page ကို Refresh ပြန်လုပ်ခြင်း
    header("Location: payment_approve.php?msg=success");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approve Payments - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans min-h-screen">

    <div class="max-w-6xl mx-auto px-4 py-8">
        
        <div class="mb-6">
            <a href="dashboard.php" class="text-sm font-semibold text-indigo-600 hover:underline">⬅️ Back to Dashboard</a>
        </div>

        <?php if (isset($_GET['msg']) && $_GET['msg'] == 'success'): ?>
            <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl font-bold text-sm shadow-sm">
                ✓ ငွေလွှဲပြေစာအား အတည်ပြုလိုက်ပါပြီ။ Admin ဝင်ငွေစာရင်းထဲသို့ ပိုက်ဆံ တိုးသွားပါပြီ။
            </div>
        <?php endif; ?>

        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8 bg-white p-6 rounded-2xl border border-gray-200 shadow-sm">
            <div>
                <h1 class="text-2xl font-black text-gray-900">💳 ငွေလွှဲပြေစာများ စစ်ဆေးအတည်ပြုခြင်း</h1>
                <p class="text-xs text-gray-500 mt-1">Customer များ တင်ထားသော Slip များအား စစ်ဆေးပြီး အော်ဒါများကို အတည်ပြုပေးပါ။</p>
            </div>
            <div class="bg-indigo-50 border border-indigo-100 px-5 py-3 rounded-xl text-right">
                <span class="text-[11px] text-indigo-500 font-medium block">လက်ရှိ စုစုပေါင်းဝင်ငွေ (Current Revenue)</span>
                <span class="text-xl font-black text-indigo-700"><?= number_format($_SESSION['admin_revenue']) ?> ကျပ်</span>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-50 text-xs font-bold text-gray-500 uppercase border-b border-gray-200">
                            <th class="p-4">Order ID</th>
                            <th class="p-4">ဝယ်ယူသူ</th>
                            <th class="p-4">လွှဲရမည့်ပမာဏ</th>
                            <th class="p-4">Method</th>
                            <th class="p-4">Transaction ID</th>
                            <th class="p-4">ငွေလွှဲဖြတ်ပိုင်း (Slip)</th>
                            <th class="p-4 text-center">လုပ်ဆောင်ချက်</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm divide-y divide-gray-100">
                        <?php if(empty($pending_payments)): ?>
                            <tr>
                                <td colspan="7" class="p-8 text-center text-gray-400 font-medium">စစ်ဆေးရန် ငွေလွှဲပြေစာ အသစ်များ မရှိသေးပါ။</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($pending_payments as $payment): ?>
                            <tr class="hover:bg-gray-50/50 transition">
                                <td class="p-4 font-mono font-bold text-gray-700"><?= $payment['order_id'] ?></td>
                                <td class="p-4 font-semibold text-gray-800"><?= $payment['customer'] ?></td>
                                <td class="p-4 text-emerald-600 font-bold"><?= number_format($payment['amount']) ?> ကျပ်</td>
                                <td class="p-4"><span class="bg-gray-100 px-2 py-0.5 rounded text-xs text-gray-600 font-medium"><?= $payment['method'] ?></span></td>
                                <td class="p-4 font-mono text-gray-600"><?= $payment['transaction_id'] ?></td>
                                <td class="p-4">
                                    <button onclick="openModal('<?= $payment['slip_image'] ?>')" class="text-xs bg-indigo-50 hover:bg-indigo-100 text-indigo-600 font-bold px-3 py-1.5 rounded-lg border border-indigo-100 transition">
                                        🖼️ View Slip
                                    </button>
                                </td>
                                <td class="p-4 text-center">
                                    <form action="" method="POST" onsubmit="return confirm('ငွေဝင်တာ သေချာပြီလား? အတည်ပြုမှာလား။');">
                                        <input type="hidden" name="payment_id" value="<?= $payment['payment_id'] ?>">
                                        <input type="hidden" name="order_id" value="<?= $payment['order_id'] ?>">
                                        <input type="hidden" name="amount" value="<?= $payment['amount'] ?>">
                                        <button type="submit" name="approve_payment" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs px-4 py-2 rounded-xl shadow-md shadow-emerald-50 transition">
                                            ✓ Approve
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

    <div id="imageModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-md w-full p-4 relative shadow-2xl">
            <button onclick="closeModal()" class="absolute top-3 right-3 text-gray-400 hover:text-gray-700 text-xl font-bold">&times;</button>
            <h3 class="font-bold text-gray-800 mb-3 text-sm">ငွေလွှဲပြေစာ အသေးစိတ်ကြည့်ရှုခြင်း</h3>
            <img id="modalImage" src="" alt="Slip Screenshot" class="w-full h-auto max-h-[70vh] object-contain rounded-xl border border-gray-100">
        </div>
    </div>

    <script>
        function openModal(imageSrc) {
            document.getElementById('modalImage').src = imageSrc;
            document.getElementById('imageModal').classList.remove('hidden');
        }
        function closeModal() {
            document.getElementById('imageModal').classList.add('hidden');
        }
    </script>

</body>
</html>