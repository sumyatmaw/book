<?php
/**
 * Public Footer Component — Online Book Shop
 *
 * Included by: index.php, books.php, payment.php, and user pages.
 */
$base_url = '/onlinebookshop';
?>
<footer class="bg-slate-900 text-gray-400 border-t border-slate-800 mt-auto selection:bg-amber-500 selection:text-slate-900">
    <!-- Top Decorative Gradient Line -->
    <div class="h-0.5 bg-gradient-to-r from-transparent via-amber-500 to-transparent opacity-50"></div>

    <div class="container mx-auto px-4 sm:px-6 py-12">
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-8 md:gap-12">
            
            <!-- Company Info & Socials -->
            <div class="space-y-4">
                <a href="<?= $base_url; ?>/index.php" class="flex items-center gap-2.5 group w-max">
                    <span class="w-9 h-9 bg-gradient-to-br from-amber-400 to-amber-500 rounded-xl flex items-center justify-center group-hover:rotate-6 transition-all duration-300 shadow-lg shadow-amber-500/20">
                        <i class="fa-solid fa-book-open text-sm text-slate-900"></i>
                    </span>
                    <span class="text-lg font-black tracking-tight text-white group-hover:text-amber-400 transition-colors duration-200">
                        Online<span class="text-amber-500">BookShop</span>
                    </span>
                </a>
                <p class="text-xs leading-relaxed text-gray-400 antialiased font-light">
                    လူကြီးမင်းတို့ စိတ်ကြိုက် သုတ၊ ရသ စာအုပ်အမျိုးမျိုးကို တစ်နေရာတည်းမှာ အလွယ်တကူ ဝယ်ယူဖတ်ရှုနိုင်မယ့် မြန်မာ့အကောင်းဆုံး အွန်လိုင်းစာအုပ်ဆိုင် ဖြစ်ပါတယ်။
                </p>
                <div class="flex items-center gap-2.5 pt-2">
                    <a href="https://facebook.com" target="_blank" class="w-8 h-8 rounded-lg bg-slate-800/80 border border-slate-700/50 flex items-center justify-center hover:bg-amber-500 hover:text-slate-900 hover:border-amber-500 text-sm transition-all duration-200 hover:-translate-y-0.5" aria-label="Facebook">
                        <i class="fa-brands fa-facebook-f"></i>
                    </a>
                    <a href="https://viber.com" target="_blank" class="w-8 h-8 rounded-lg bg-slate-800/80 border border-slate-700/50 flex items-center justify-center hover:bg-amber-500 hover:text-slate-900 hover:border-amber-500 text-sm transition-all duration-200 hover:-translate-y-0.5" aria-label="Viber">
                        <i class="fa-brands fa-viber"></i>
                    </a>
                    <a href="https://telegram.org" target="_blank" class="w-8 h-8 rounded-lg bg-slate-800/80 border border-slate-700/50 flex items-center justify-center hover:bg-amber-500 hover:text-slate-900 hover:border-amber-500 text-sm transition-all duration-200 hover:-translate-y-0.5" aria-label="Telegram">
                        <i class="fa-brands fa-telegram"></i>
                    </a>
                </div>
            </div>

            <!-- Quick Links -->
            <div>
                <h3 class="text-xs font-bold text-white tracking-widest uppercase mb-4 border-l-2 border-amber-500 pl-2.5">Quick Links</h3>
                <ul class="space-y-2.5 text-xs">
                    <li>
                        <a href="<?= $base_url; ?>/index.php" class="hover:text-amber-400 transition-colors duration-200 flex items-center gap-2 group">
                            <i class="fa-solid fa-chevron-right text-[8px] text-amber-500/50 group-hover:translate-x-1 transition-transform"></i> Home
                        </a>
                    </li>
                    <li>
                        <a href="<?= $base_url; ?>/books.php" class="hover:text-amber-400 transition-colors duration-200 flex items-center gap-2 group">
                            <i class="fa-solid fa-chevron-right text-[8px] text-amber-500/50 group-hover:translate-x-1 transition-transform"></i> Books
                        </a>
                    </li>
                    <li>
                        <a href="<?= $base_url; ?>/user/cart.php" class="hover:text-amber-400 transition-colors duration-200 flex items-center gap-2 group">
                            <i class="fa-solid fa-chevron-right text-[8px] text-amber-500/50 group-hover:translate-x-1 transition-transform"></i> Shopping Cart
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Support Links -->
            <div>
                <h3 class="text-xs font-bold text-white tracking-widest uppercase mb-4 border-l-2 border-amber-500 pl-2.5">Support</h3>
                <ul class="space-y-2.5 text-xs">
                    <li>
                        <a href="<?= $base_url; ?>/terms.php" class="hover:text-amber-400 transition-colors duration-200 flex items-center gap-2 group">
                            <i class="fa-solid fa-chevron-right text-[8px] text-amber-500/50 group-hover:translate-x-1 transition-transform"></i> Terms & Conditions
                        </a>
                    </li>
                    <li>
                        <a href="<?= $base_url; ?>/privacy.php" class="hover:text-amber-400 transition-colors duration-200 flex items-center gap-2 group">
                            <i class="fa-solid fa-chevron-right text-[8px] text-amber-500/50 group-hover:translate-x-1 transition-transform"></i> Privacy Policy
                        </a>
                    </li>
                    <li>
                        <a href="<?= $base_url; ?>/howtoorder.php" class="hover:text-amber-400 transition-colors duration-200 flex items-center gap-2 group">
                            <i class="fa-solid fa-chevron-right text-[8px] text-amber-500/50 group-hover:translate-x-1 transition-transform"></i> How to Order
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Contact Info -->
            <div>
                <h3 class="text-xs font-bold text-white tracking-widest uppercase mb-4 border-l-2 border-amber-500 pl-2.5">Contact Us</h3>
                <ul class="space-y-3.5 text-xs">
                    <li class="flex items-start gap-2.5 leading-relaxed">
                        <i class="fa-solid fa-location-dot text-amber-500 mt-0.5 shrink-0 text-sm"></i>
                        <span>အမှတ် (၁၂၃)၊ ကမ္ဘာအေးဘုရားလမ်း၊ မရမ်းကုန်းမြို့နယ်၊ ရန်ကုန်မြို့။</span>
                    </li>
                    <li class="flex items-center gap-2.5">
                        <i class="fa-solid fa-phone text-amber-500 shrink-0 text-sm"></i>
                        <span class="hover:text-white transition-colors">09-403502387, 09-760968003</span>
                    </li>
                    <li class="flex items-center gap-2.5">
                        <i class="fa-solid fa-envelope text-amber-500 shrink-0 text-sm"></i>
                        <a href="mailto:info@onlinebookshop.com" class="hover:text-amber-400 transition-colors">info@onlinebookshop.com</a>
                    </li>
                </ul>
            </div>

        </div>
    </div>

    <!-- Bottom Copyright Bar -->
    <div class="bg-slate-950 text-gray-500 text-center py-4 text-xs border-t border-slate-800/40">
        <div class="container mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-2.5">
            <p>&copy; <?= date('Y'); ?> Online Book Shop. All rights reserved.</p>
            <p class="text-[11px] text-gray-600 flex items-center gap-1.5">
                Developed with <i class="fa-solid fa-heart text-red-500/80 animate-pulse"></i> for Book Lovers
            </p>
        </div>
    </div>
</footer>