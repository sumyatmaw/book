<?php
session_start();
require_once '../config/db.php';

// Customer Login Check (မူရင်း Logic အတိုင်း စစ်ဆေးခြင်း)
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'customer') {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$base_url = '/onlinebookshop';

// Orders ဒေတာ ဆွဲထုတ်ခြင်း (သင့် Database Structure အတိုင်း)
$orders_query = "SELECT * FROM Orders WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($orders_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - Online Book Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen font-sans text-slate-800 flex flex-col">

    <?php include '../auth/header.php'; ?>

    <div class="flex-1 container mx-auto px-4 sm:px-6 py-8">
        
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
            <div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">My Orders</h1>
                <p class="text-xs text-gray-500 mt-1">သင်မှာယူထားသော စာအုပ်အမှာစာရင်းများနှင့် အခြေအနေများ</p>
            </div>
            <a href="<?= $base_url; ?>/index.php" class="text-xs font-bold text-blue-600 hover:text-blue-700 bg-blue-50 hover:bg-blue-100 px-3 py-2 rounded-xl transition self-start sm:self-center">
                <i class="fa-solid fa-arrow-left mr-1"></i> Back to Home
            </a>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <?php if ($result && $result->num_rows > 0): ?>
                
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-gray-100 text-xs font-bold uppercase tracking-wider text-slate-600">
                                <th class="py-4 px-6">Order Number</th>
                                <th class="py-4 px-6">Date</th>
                                <th class="py-4 px-6">Total Amount</th>
                                <th class="py-4 px-6">Status</th>
                                <th class="py-4 px-6 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-sm font-medium text-slate-700">
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr class="hover:bg-slate-50/50 transition">
                                    <td class="py-4 px-6 font-bold text-slate-900">
                                        #<?= htmlspecialchars($row['order_number'] ?? $row['id']); ?>
                                    </td>
                                    <td class="py-4 px-6 text-xs text-gray-500">
                                        <?= date('d M Y, h:i A', strtotime($row['created_at'])); ?>
                                    </td>
                                    <td class="py-4 px-6 font-bold text-blue-600">
                                        <?= number_format($row['total_amount']); ?> MMK
                                    </td>
                                    <td class="py-4 px-6">
                                        <?php if ($row['status'] === 'pending'): ?>
                                            <span class="inline-flex items-center gap-1.5 py-1 px-2.5 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Pending
                                            </span>
                                        <?php elseif ($row['status'] === 'completed'): ?>
                                            <span class="inline-flex items-center gap-1.5 py-1 px-2.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Completed
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1.5 py-1 px-2.5 rounded-full text-xs font-bold bg-gray-50 text-gray-600 border border-gray-200">
                                                <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span> Cancelled
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 px-6 text-center">
                                        <?php if ($row['status'] === 'pending'): ?>
                                            <a href="payment.php?order=<?= $row['id']; ?>" class="inline-flex items-center gap-1 bg-blue-600 text-white text-xs font-bold px-4 py-2 rounded-xl hover:bg-blue-700 transition shadow-sm">
                                                <i class="fa-solid fa-credit-card text-[10px]"></i> Pay Now
                                            </a>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-400 font-normal">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <div class="block md:hidden divide-y divide-gray-100">
                    <?php 
                    $result->data_seek(0); 
                    while ($row = $result->fetch_assoc()): 
                    ?>
                        <div class="p-5 space-y-3.5">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-black text-slate-900">#<?= htmlspecialchars($row['order_number'] ?? $row['id']); ?></span>
                                <span class="text-[11px] font-bold text-gray-400"><?= date('d M Y', strtotime($row['created_at'])); ?></span>
                            </div>
                            
                            <div class="flex items-center justify-between">
                                <div class="text-xs font-bold text-gray-500">Total Amount:</div>
                                <div class="text-sm font-black text-blue-600"><?= number_format($row['total_amount']); ?> MMK</div>
                            </div>

                            <div class="flex items-center justify-between pt-1">
                                <div>
                                    <?php if ($row['status'] === 'pending'): ?>
                                        <span class="inline-flex items-center gap-1.5 py-1 px-2.5 rounded-full text-[11px] font-bold bg-amber-50 text-amber-700 border border-amber-200">Pending</span>
                                    <?php elseif ($row['status'] === 'completed'): ?>
                                        <span class="inline-flex items-center gap-1.5 py-1 px-2.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Completed</span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1.5 py-1 px-2.5 rounded-full text-[11px] font-bold bg-gray-50 text-gray-600 border border-gray-200">Cancelled</span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($row['status'] === 'pending'): ?>
                                    <a href="payment.php?order=<?= $row['id']; ?>" class="bg-blue-600 text-white text-xs font-bold px-4 py-2 rounded-xl hover:bg-blue-700 transition shadow-sm">
                                        Pay Now
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>

            <?php else: ?>
                <div class="p-12 text-center max-w-sm mx-auto">
                    <div class="w-16 h-16 bg-slate-50 border border-slate-100 rounded-2xl flex items-center justify-center mx-auto mb-4 text-gray-400">
                        <i class="fa-solid fa-box-open text-xl"></i>
                    </div>
                    <h3 class="text-base font-bold text-slate-900 mb-1">အမှာစာရင်း မရှိသေးပါ</h3>
                    <p class="text-xs text-gray-400 leading-relaxed mb-5">လူကြီးမင်း ဝယ်ယူထားသည့် စာအုပ်အမှာစာရင်းများ မရှိသေးပါ။</p>
                    <a href="<?= $base_url; ?>/books.php" class="inline-flex bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold px-5 py-2.5 rounded-xl shadow-sm transition">
                        Browse Books
                    </a>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <?php include '../auth/footer.php'; ?>

</body>
</html>
<?php 
$stmt->close();
$conn->close();
?>