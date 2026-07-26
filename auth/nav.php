<!-- TOP NAVIGATION BAR -->
<header class="h-16 bg-yellow-300 border-b border-slate-200/80 flex items-center justify-between px-4 md:px-8 z-30 shrink-0">
    <div class="flex items-center space-x-3">
        <button onclick="toggleSidebar()" class="p-2 rounded-xl text-slate-600 hover:bg-slate-50/50 md:hidden transition cursor-pointer">
            <i class="fa-solid fa-bars text-lg"></i>
        </button>
        <h1 class="text-base md:text-xl font-bold text-slate-800 truncate"><?php echo $page_title ?? 'Admin Dashboard'; ?></h1>
    </div>

    <div class="flex items-center space-x-3 md:space-x-4 relative">
        <!-- Notifications Bell Button -->
        <div class="relative">
            <button onclick="toggleNotificationDropdown(event)" id="notiBtn" class="w-10 h-10 rounded-full flex items-center justify-center text-slate-700 hover:text-indigo-600 hover:bg-white/60 transition cursor-pointer relative">
                <i class="fa-solid fa-bell text-base"></i>
                <?php if (($low_stock_count ?? 0) > 0 || ($pending_payments_count ?? 0) > 0): ?>
                    <span class="absolute top-2 right-2 w-2.5 h-2.5 bg-rose-500 rounded-full ring-2 ring-yellow-300"></span>
                <?php endif; ?>
            </button>

            <!-- Notifications Dropdown Menu -->
            <div id="notiDropdown" class="hidden absolute right-0 top-12 w-80 sm:w-88 bg-white border border-slate-200 shadow-2xl rounded-2xl overflow-hidden z-50">
                <div class="px-5 py-3.5 bg-slate-50 border-b border-slate-200 font-bold text-xs text-slate-700 tracking-wide">အသိပေးချက်များ</div>
                <div class="divide-y divide-slate-200 max-h-72 overflow-y-auto no-scrollbar">
                    <?php if (($pending_payments_count ?? 0) > 0 && isset($pending_payments_query)): ?>
                        <?php 
                        // Reset pointer if query was iterated before
                        if (mysqli_num_rows($pending_payments_query) > 0) mysqli_data_seek($pending_payments_query, 0);
                        while($payment = mysqli_fetch_assoc($pending_payments_query)): 
                        ?>
                        <a href="manage_payment.php" class="block px-5 py-4 hover:bg-slate-50 transition border-b border-slate-100 last:border-b-0">
                            <p class="text-xs font-bold text-indigo-600 flex items-center gap-2">
                                <i class="fa-solid fa-wallet"></i> ငွေလွှဲစစ်ဆေးရန်ကျန်ရှိပါသည်
                            </p>
                            <p class="text-[11px] text-slate-500 mt-1 pl-5">ပမာဏ: <?php echo number_format($payment['amount']); ?> ကျပ် အတည်ပြုရန် စောင့်ဆိုင်းနေသည်။</p>
                        </a>
                        <?php endwhile; ?>
                    <?php endif; ?>

                    <?php if (($low_stock_count ?? 0) > 0): ?>
                        <div class="block px-5 py-4 bg-amber-50/50 border-b border-slate-100 last:border-b-0">
                            <p class="text-xs font-bold text-amber-600 flex items-center gap-2">
                                <i class="fa-solid fa-triangle-exclamation"></i> စတော့နည်းနေသော သတိပေးချက်
                            </p>
                            <p class="text-[11px] text-slate-500 mt-1 pl-5">စာအုပ် <?= $low_stock_count; ?> အုပ် စတော့ကုန်လုနီးပါးဖြစ်နေပါသည်။</p>
                        </div>
                    <?php endif; ?>

                    <?php if (($low_stock_count ?? 0) == 0 && ($pending_payments_count ?? 0) == 0): ?>
                        <div class="p-6 text-center text-xs text-slate-400 font-medium">အသိပေးချက်အသစ်များ မရှိသေးပါ။</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Admin Profile Menu -->
        <div class="relative border-l border-slate-400/30 pl-3 md:pl-4">
            <button onclick="toggleProfileDropdown(event)" id="profileBtn" class="w-9 h-9 rounded-full overflow-hidden bg-slate-100 border border-slate-300 hover:border-indigo-500 flex items-center justify-center transition cursor-pointer">
                <?php if(!empty($_SESSION['user_image']) && file_exists("../uploads/profile/" . $_SESSION['user_image'])): ?>
                    <img src="../uploads/profile/<?= htmlspecialchars($_SESSION['user_image']); ?>" alt="Admin" class="w-full h-full object-cover">
                <?php elseif(!empty($admin_image) && file_exists("../uploads/profile/" . $admin_image)): ?>
                    <img src="../uploads/profile/<?= htmlspecialchars($admin_image); ?>" alt="Admin" class="w-full h-full object-cover">
                <?php else: ?>
                    <div class="w-full h-full bg-indigo-600 text-white flex items-center justify-center font-bold text-xs"><?= $admin_initial ?? 'A'; ?></div>
                <?php endif; ?>
            </button>

            <!-- Admin Profile Dropdown Menu -->
            <div id="profileDropdown" class="hidden absolute right-0 top-12 w-48 bg-white border border-slate-200 shadow-xl rounded-2xl overflow-hidden z-50">
                <div class="px-4 py-2.5 border-b border-slate-100 bg-slate-50/60">
                    <p class="text-xs font-bold text-slate-800 truncate"><?php echo htmlspecialchars($admin_name ?? 'Admin'); ?></p>
                    <p class="text-[10px] text-slate-400 truncate"><?php echo htmlspecialchars($admin_email ?? ''); ?></p>
                </div>
                <div class="py-1">
                    <a href="dashboard.php" class="flex items-center space-x-2 px-4 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50 hover:text-indigo-600 transition">
                        <i class="fa-solid fa-chart-pie w-4 text-slate-400"></i><span>Dashboard</span>
                    </a>
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