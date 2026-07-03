<?php
/**
 * Public Footer Component — Online Book Shop
 *
 * Included by: index.php, books.php, payment.php, and user pages.
 */
$base_url = '/onlinebookshop';
?>
<footer class="bg-slate-900 text-gray-400 border-t border-slate-800 mt-auto">
    <div class="container mx-auto px-4 sm:px-6 py-12">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
            
            <div class="space-y-4">
                <a href="<?= $base_url; ?>/index.php" class="flex items-center gap-2.5 group">
                    <span class="w-8 h-8 bg-amber-500 rounded-lg flex items-center justify-center group-hover:scale-105 transition-transform duration-200 shadow-md shadow-amber-500/30">
                        <i class="fa-solid fa-book-open text-xs text-slate-900"></i>
                    </span>
                    <span class="text-base font-black tracking-tight text-white">
                        Online<span class="text-amber-500">BookShop</span>
                    </span>
                </a>
                <p class="text-xs leading-relaxed text-gray-400">
                    လူကြီးမင်းတို့ စိတ်ကြိုက် သုတ၊ ရသ စာအုပ်အမျိုးမျိုးကို တစ်နေရာတည်းမှာ အလွယ်တကူ ဝယ်ယူဖတ်ရှုနိုင်မယ့် မြန်မာ့အကောင်းဆုံး အွန်လိုင်းစာအုပ်ဆိုင် ဖြစ်ပါတယ်။
                </p>
                <div class="flex items-center gap-3 pt-2">
                    <a href="https://facebook.com" target="_blank" class="w-8 h-8 rounded-lg bg-slate-800 flex items-center justify-center hover:bg-amber-500 hover:text-slate-900 text-sm transition-colors duration-200" aria-label="Facebook">
                        <i class="fa-brands fa-facebook-f"></i>
                    </a>
                    <a href="https://viber.com" target="_blank" class="w-8 h-8 rounded-lg bg-slate-800 flex items-center justify-center hover:bg-amber-500 hover:text-slate-900 text-sm transition-colors duration-200" aria-label="Viber">
                        <i class="fa-brands fa-viber"></i>
                    </a>
                    <a href="https://telegram.org" target="_blank" class="w-8 h-8 rounded-lg bg-slate-800 flex items-center justify-center hover:bg-amber-500 hover:text-slate-900 text-sm transition-colors duration-200" aria-label="Telegram">
                        <i class="fa-brands fa-telegram"></i>
                    </a>
                </div>
            </div>

            <div>
                <h3 class="text-sm font-bold text-white tracking-wider uppercase mb-4 border-l-2 border-amber-500 pl-2">Quick Links</h3>
                <ul class="space-y-2 text-xs">
                    <li>
                        <a href="<?= $base_url; ?>/index.php" class="hover:text-amber-400 transition-colors duration-150 flex items-center gap-1.5">
                            <i class="fa-solid fa-chevron-right text-[8px] opacity-50"></i> Home
                        </a>
                    </li>
                    <li>
                        <a href="<?= $base_url; ?>/books.php" class="hover:text-amber-400 transition-colors duration-150 flex items-center gap-1.5">
                            <i class="fa-solid fa-chevron-right text-[8px] opacity-50"></i> Books
                        </a>
                    </li>
                    <li>
                        <a href="<?= $base_url; ?>/user/cart.php" class="hover:text-amber-400 transition-colors duration-150 flex items-center gap-1.5">
                            <i class="fa-solid fa-chevron-right text-[8px] opacity-50"></i> Shopping Cart
                        </a>
                    </li>
                </ul>
            </div>

            <div>
                <h3 class="text-sm font-bold text-white tracking-wider uppercase mb-4 border-l-2 border-amber-500 pl-2">Support</h3>
                <ul class="space-y-2 text-xs">
                    <li>
                        <a href="#" class="hover:text-amber-400 transition-colors duration-150 flex items-center gap-1.5">
                            <i class="fa-solid fa-chevron-right text-[8px] opacity-50"></i> Terms & Conditions
                        </a>
                    </li>
                    <li>
                        <a href="#" class="hover:text-amber-400 transition-colors duration-150 flex items-center gap-1.5">
                            <i class="fa-solid fa-chevron-right text-[8px] opacity-50"></i> Privacy Policy
                        </a>
                    </li>
                    <li>
                        <a href="#" class="hover:text-amber-400 transition-colors duration-150 flex items-center gap-1.5">
                            <i class="fa-solid fa-chevron-right text-[8px] opacity-50"></i> How to Order
                        </a>
                    </li>
                </ul>
            </div>

            <div>
                <h3 class="text-sm font-bold text-white tracking-wider uppercase mb-4 border-l-2 border-amber-500 pl-2">Contact Us</h3>
                <ul class="space-y-3 text-xs">
                    <li class="flex items-start gap-2.5">
                        <i class="fa-solid fa-location-dot text-amber-500 mt-0.5 shrink-0"></i>
                        <span>အမှတ် (၁၂၃)၊ ကမ္ဘာအေးဘုရားလမ်း၊ မရမ်းကုန်းမြို့နယ်၊ ရန်ကုန်မြို့။</span>
                    </li>
                    <li class="flex items-center gap-2.5">
                        <i class="fa-solid fa-phone text-amber-500 shrink-0"></i>
                        <span class="hover:text-white transition-colors">09-123456789, 09-987654321</span>
                    </li>
                    <li class="flex items-center gap-2.5">
                        <i class="fa-solid fa-envelope text-amber-500 shrink-0"></i>
                        <a href="mailto:info@onlinebookshop.com" class="hover:text-amber-400 transition-colors">info@onlinebookshop.com</a>
                    </li>
                </ul>
            </div>

        </div>
    </div>

    <div class="bg-slate-950 text-gray-500 text-center py-4 text-xs border-t border-slate-800/60">
        <div class="container mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-2">
            <p>&copy; <?= date('Y'); ?> Online Book Shop. All rights reserved.</p>
            <p class="text-[10px] text-gray-600">Developed with <i class="fa-solid fa-heart text-red-500/70"></i> for Book Lovers</p>
        </div>
    </div>
</footer>