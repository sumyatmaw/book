<?php
session_start();
require_once '../config/db.php';

// Customer Login Check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'customer') {
    header("Location: ../auth/login.php");
    exit();
}

$error = "";
$message = "";

// URL Query ကနေ Order ID ပါမပါ စစ်ဆေးခြင်း
if (!isset($_GET['order']) || empty($_GET['order'])) {
    header("Location: myorders.php");
    exit();
}

$order_id = intval($_GET['order']);
$user_id = $_SESSION['user_id'];

// ၁။ လက်ရှိ Order သည် ယခု Login ဝင်ထားသည့် Customer ၏ Pending ဖြစ်နေသော Order ဟုတ်မဟုတ် စစ်ဆေးခြင်း
$order_stmt = $conn->prepare("SELECT * FROM Orders WHERE id = ? AND user_id = ? AND status = 'pending'");
$order_stmt->bind_param("ii", $order_id, $user_id);
$order_stmt->execute();
$order_result = $order_stmt->get_result();

if ($order_result->num_rows === 0) {
    header("Location: myorders.php");
    exit();
}

$order_data = $order_result->fetch_assoc();
$total_amount = $order_data['total_amount'];
$order_stmt->close();

// ၂။ အသုံးပြုနိုင်သော Payment Methods (KPay, Wave စသည်) ကို ဆွဲထုတ်ခြင်း
$methods_result = $conn->query("SELECT * FROM payment_method WHERE is_active = 1");

// ၃။ Form ကို Submit (Confirm Payment) လုပ်လာသောအခါ လုပ်ဆောင်ချက်
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'])) {
    $payment_method_id = intval($_POST['payment_method_id']);
    $transaction_ref = trim($_POST['transaction_ref']);
    
    // Validation စစ်ဆေးခြင်း
    if ($payment_method_id <= 0) {
        $error = "ကျေးဇူးပြု၍ Payment Method တစ်ခုခုကို ရွေးချယ်ပေးပါရန်။";
    } elseif (empty($transaction_ref)) {
        $error = "ကျေးဇူးပြု၍ Transaction ID ဖြည့်သွင်းပေးပါရန်။";
    } elseif (!isset($_FILES['payment_slip']) || $_FILES['payment_slip']['error'] !== UPLOAD_ERR_OK) {
        $error = "ကျေးဇူးပြု၍ ငွေလွှဲပြေစာ (Slip) ဓာတ်ပုံ တင်ပေးပါရန်။";
    } else {
        // File Upload ပိုင်း လုပ်ဆောင်ခြင်း
        $file_name = $_FILES['payment_slip']['name'];
        $file_tmp = $_FILES['payment_slip']['tmp_name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed_extensions = ['jpg', 'jpeg', 'png'];
        
        if (!in_array($file_ext, $allowed_extensions)) {
            $error = "Slip အတွက် JPG, JPEG သို့မဟုတ် PNG ပုံစံ ဓာတ်ပုံများသာ လက်ခံပါသည်။";
        } else {
            // ပုံနာမည် တူမသွားစေရန် unique name ပြောင်းခြင်း
            $new_file_name = "slip_" . time() . "_" . uniqid() . "." . $file_ext;
            $upload_dir = "../uploads/";
            
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            if (move_uploaded_file($file_tmp, $upload_dir . $new_file_name)) {
                // လက်ရှိအချိန်ကို ယူခြင်း (payment_date အတွက်)
                $current_time = date('Y-m-d H:i:s');
                
                // ဒေတာဘေ့စ်ထဲသို့ ဒေတာ သိမ်းဆည်းခြင်း 
                // (သင့် SQL အရ payment_date က NOT NULL ဖြစ်ပြီး default မပါလို့ ဤနေရာတွင် current_time ကို တိုက်ရိုက်ထည့်သွင်းပေးထားပါသည်)
                $insert_stmt = $conn->prepare("INSERT INTO Payment (order_id, payment_method_id, amount, status, payment_date, transaction_ref, payment_slip) VALUES (?, ?, ?, 'pending', ?, ?, ?)");
                $insert_stmt->bind_param("iidsss", $order_id, $payment_method_id, $total_amount, $current_time, $transaction_ref, $new_file_name);
                
                if ($insert_stmt->execute()) {
                    $message = "ငွေလွှဲပြေစာ ပေးပို့မှု အောင်မြင်ပါသည်။ Admin ဘက်မှ စစ်ဆေးပြီးပါက အကြောင်းကြားပေးပါမည်။";
                    header("refresh:2; url=myorders.php");
                } else {
                    $error = "ဒေတာဘေ့စ်အတွင်း သိမ်းဆည်းရန် ပျက်ကွက်ခဲ့ပါသည်: " . $conn->error;
                }
                $insert_stmt->close();
            } else {
                $error = "ဓာတ်ပုံကို Upload Folder အတွင်းသို့ ရွှေ့ပြောင်းရန် ပျက်ကွက်ခဲ့ပါသည်။";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - Online Book Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen font-sans text-slate-800 flex flex-col">

    <?php include '../auth/headeru.php'; ?>

    <div class="flex-1 flex items-center justify-center p-4 sm:p-6 my-6">
        <div class="w-full max-w-lg bg-white rounded-2xl shadow-md border border-gray-100 p-6 sm:p-8">
            
            <h1 class="text-2xl font-black text-slate-900 mb-2">Payment</h1>
            <p class="text-sm font-bold text-gray-500 mb-6">
                Total Amount : <span class="text-blue-600 text-lg"><?= number_format($total_amount); ?> MMK</span>
            </p>

            <!-- Error & Success Message Alerts -->
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

            <!-- Payment Form -->
            <form action="" method="POST" enctype="multipart/form-data" class="space-y-5">
                
                <!-- Payment Method Dropdown -->
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Payment Method</label>
                    <select name="payment_method_id" class="w-full bg-white border border-gray-300 rounded-xl py-3 px-4 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm font-medium" required>
                        <option value="">Select Payment Method</option>
                        <?php if ($methods_result && $methods_result->num_rows > 0): ?>
                            <?php while ($method = $methods_result->fetch_assoc()): ?>
                                <option value="<?= $method['id']; ?>">
                                    <?= htmlspecialchars($method['method_name']); ?> (<?= htmlspecialchars($method['account_holder']); ?> - <?= htmlspecialchars($method['account_number']); ?>)
                                </option>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <option value="" disabled>အသုံးပြုနိုင်သော ငွေချေစနစ် မတွေ့ရှိသေးပါ</option>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- Transaction ID Input -->
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Transaction ID / Ref</label>
                    <input type="text" name="transaction_ref" placeholder="Enter Transaction ID" class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white text-sm shadow-sm" required>
                </div>

                <!-- Payment Slip File Upload -->
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Upload Payment Slip</label>
                    <div class="w-full border border-gray-300 rounded-xl p-2 bg-white shadow-sm">
                        <input type="file" name="payment_slip" accept="image/*" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 transition cursor-pointer" required>
                    </div>
                    <p class="text-[11px] text-gray-400 mt-1">※ JPG, JPEG သို့မဟုတ် PNG ပုံစံများသာ တင်ပေးပါရန်။</p>
                </div>

                <!-- Submit Button -->
                <button type="submit" name="confirm_payment" class="w-full bg-blue-600 text-white py-3.5 rounded-xl hover:bg-blue-700 font-bold transition shadow-md flex items-center justify-center gap-2 mt-2">
                    <i class="fa-solid fa-lock text-xs"></i> Confirm Payment
                </button>
                
            </form>

        </div>
    </div>

    <?php include '../auth/footer.php'; ?>

</body>
</html>