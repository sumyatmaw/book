<?php
/**
 * Terms & Conditions Page — Online Book Shop
 */
$base_url = '/onlinebookshop';
?>
<!DOCTYPE html>
<html lang="my" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms & Conditions - Online Book Shop</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts (Pyidaungsu or Inter if needed) -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">
</head>
<body class="bg-slate-50 text-slate-800 font-['Inter',sans-serif] flex flex-col min-h-screen antialiased selection:bg-amber-500 selection:text-slate-900">

    <?php 
    // သင်၏ Navigation Bar / Header Component ကို ဤနေရာတွင် ချိတ်ဆက်ပါ
    // include 'components/header.php'; 
    ?>

    <!-- Page Header Hero Section -->
    <div class="bg-slate-900 text-white py-12 md:py-16 border-b border-slate-800 relative overflow-hidden">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,_var(--tw-gradient-stops))] from-amber-500/10 via-transparent to-transparent"></div>
        <div class="container mx-auto px-4 max-w-4xl text-center relative z-10">
            <span class="text-amber-500 text-xs font-bold tracking-widest uppercase bg-amber-500/10 px-3 py-1.5 rounded-full border border-amber-500/20">Legal Information</span>
            <h1 class="text-3xl md:text-4xl font-black tracking-tight mt-3 mb-4">Terms & Conditions</h1>
            <p class="text-sm text-slate-400 max-w-xl mx-auto font-light leading-relaxed">
                Online Book Shop ဝန်ဆောင်မှုများကို အသုံးပြုရာတွင် လိုက်နာရမည့် စည်းကမ်းချက်များနှင့် သတ်မှတ်ချက်များ။
            </p>
        </div>
    </div>

    <!-- Main Content Section -->
    <main class="flex-grow container mx-auto px-4 max-w-4xl py-12">
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 md:p-10 space-y-8">
            
            <!-- Effective Date Notification -->
            <div class="flex items-center gap-3 bg-slate-50 border border-slate-200 p-4 rounded-xl text-xs text-slate-500">
                <i class="fa-solid fa-clock-rotate-left text-amber-500 text-sm"></i>
                <span>နောက်ဆုံးပြင်ဆင်ခဲ့သည့်ရက်စွဲခန့်မှန်းခြေ- <strong>ဇူလိုင်လ၊ ၂၀၂၆ ခုနှစ်</strong></span>
            </div>

            <!-- Intro Text -->
            <p class="text-sm leading-relaxed text-slate-600">
                Online Book Shop ဝဘ်ဆိုက်သို့ လာရောက်အားပေးမှုကို ကျေးဇူးတင်ရှိပါသည်။ ဤဝဘ်ဆိုက်ကို အသုံးပြုခြင်း၊ စာအုပ်များ ဝယ်ယူခြင်းတို့သည် အောက်ပါ စည်းကမ်းချက်များကို သဘောတူလက်ခံရာ ရောက်ပါသည်။ ကျေးဇူးပြု၍ သေချာစွာ ဖတ်ရှုပေးပါရန် မေတ္တာရပ်ခံအပ်ပါသည်။
            </p>

            <hr class="border-slate-100">

            <!-- Section 1 -->
            <div class="space-y-3">
                <h2 class="text-base font-bold text-slate-900 flex items-center gap-2.5">
                    <span class="w-6 h-6 bg-amber-100 text-amber-700 text-xs font-bold rounded-lg flex items-center justify-center shrink-0">၁</span>
                    အကောင့်မှတ်ပုံတင်ခြင်းနှင့် လုံခြုံရေး
                </h2>
                <div class="pl-8 text-xs md:text-sm text-slate-600 space-y-2 leading-relaxed">
                    <p>• ဝဘ်ဆိုက်ပေါ်တွင် စာအုပ်ဝယ်ယူရန်အတွက် မှန်ကန်သော အချက်အလက်များဖြင့် အကောင့်ဖွင့်လှစ်ရပါမည်။</p>
                    <p>• မိမိအကောင့်၏ Password လုံခြုံရေးကို မိမိကိုယ်တိုင် တာဝန်ယူရမည်ဖြစ်ပြီး၊ တခြားသူအား ပေးသုံးခြင်းကြောင့် ဖြစ်ပေါ်လာသည့် ကိစ္စရပ်များကို ဆိုင်မှ တာဝန်ယူမည်မဟုတ်ပါ။</p>
                </div>
            </div>

            <!-- Section 2 -->
            <div class="space-y-3">
                <h2 class="text-base font-bold text-slate-900 flex items-center gap-2.5">
                    <span class="w-6 h-6 bg-amber-100 text-amber-700 text-xs font-bold rounded-lg flex items-center justify-center shrink-0">၂</span>
                    အမှာစာများနှင့် ငွေပေးချေမှုစနစ်
                </h2>
                <div class="pl-8 text-xs md:text-sm text-slate-600 space-y-2 leading-relaxed">
                    <p>• စာအုပ်များမှာယူပြီးနောက် သတ်မှတ်ထားသော အချိန်အတွင်း ငွေပေးချေမှု (Payment) ကို လုပ်ဆောင်ရပါမည်။</p>
                    <p>• ငွေလွှဲပြေစာ (Receipt) အတုများဖြင့် လိမ်လည်တင်ပြခြင်း ရှိခဲ့ပါက အဆိုပါ အကောင့်ကို အပြီးပိုင် ပိတ်သိမ်းခြင်း (Ban) ပြုလုပ်သွားမည်ဖြစ်ပြီး ဥပဒေအရ အရေးယူဆောင်ရွက်နိုင်ပါသည်။</p>
                </div>
            </div>

            <!-- Section 3 -->
            <div class="space-y-3">
                <h2 class="text-base font-bold text-slate-900 flex items-center gap-2.5">
                    <span class="w-6 h-6 bg-amber-100 text-amber-700 text-xs font-bold rounded-lg flex items-center justify-center shrink-0">၃</span>
                    ပို့ဆောင်ရေးစနစ် (Shipping & Delivery)
                </h2>
                <div class="pl-8 text-xs md:text-sm text-slate-600 space-y-2 leading-relaxed">
                    <p>• ငွေပေးချေမှု အောင်မြင်ပြီးနောက် သတ်မှတ်ထားသော အလုပ်လုပ်ရက် (Working Days) အတွင်း ပို့ဆောင်ရေးလုပ်ငန်းများထံ စာအုပ်များ အပ်နှံပေးသွားမည်ဖြစ်ပါသည်။</p>
                    <p>• အဝေးသင် လိပ်စာအမှားများကြောင့် ပစ္စည်းပြန်လည်ရောက်ရှိလာပါက ပြန်လည်ပို့ဆောင်ခကို ဝယ်ယူသူမှ ထပ်မံပေးဆောင်ရပါမည်။</p>
                </div>
            </div>

            <!-- Section 4 -->
            <div class="space-y-3">
                <h2 class="text-base font-bold text-slate-900 flex items-center gap-2.5">
                    <span class="w-6 h-6 bg-amber-100 text-amber-700 text-xs font-bold rounded-lg flex items-center justify-center shrink-0">၄</span>
                    မူပိုင်ခွင့်နှင့် ကန့်သတ်ချက်များ
                </h2>
                <div class="pl-8 text-xs md:text-sm text-slate-600 space-y-2 leading-relaxed">
                    <p>• ဤဝဘ်ဆိုက်ပေါ်ရှိ စာအုပ်မျက်နှာဖုံးများ၊ အကြောင်းအရာများနှင့် ကုဒ်များကို ခွင့်ပြုချက်မရှိဘဲ ကူးယူ အသုံးပြုခြင်း မပြုလုပ်ရပါ။</p>
                </div>
            </div>

            <hr class="border-slate-100">

            <!-- Help Callout -->
            <div class="bg-amber-50 border border-amber-200/60 rounded-xl p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="space-y-1">
                    <h4 class="text-sm font-bold text-slate-900">စည်းကမ်းချက်များနှင့် ပတ်သက်၍ မေးမြန်းလိုပါသလား။</h4>
                    <p class="text-xs text-slate-600">ကျွန်ုပ်တို့၏ Support Team ထံသို့ အချိန်မရွေး ဆက်သွယ်မေးမြန်းနိုင်ပါသည်။</p>
                </div>
                <a href="mailto:info@onlinebookshop.com" class="inline-flex items-center justify-center gap-2 bg-slate-900 hover:bg-slate-800 text-white text-xs font-medium px-4 py-2.5 rounded-lg transition-colors duration-200 shrink-0 shadow-sm">
                    <i class="fa-solid fa-envelope"></i> Contact Support
                </a>
            </div>

        </div>
    </main>

    <?php 
    // စောစောက ပြင်ဆင်ခဲ့သော လှပသည့် Footer Component ကို ဤနေရာတွင် ပြန်ခေါ်ပါမည်
    include 'auth/footer.php'; 
    ?>

</body>
</html>