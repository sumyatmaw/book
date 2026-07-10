<?php
/**
 * How to Order Page — Online Book Shop
 */
$base_url = '/onlinebookshop';
?>
<!DOCTYPE html>
<html lang="my" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>How to Order - Online Book Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">
</head>
<body class="bg-slate-50 text-slate-800 font-['Inter',sans-serif] flex flex-col min-h-screen antialiased selection:bg-amber-500 selection:text-slate-900">

    <?php 
    // သင်၏ Navigation Bar / Header Component ကို ဤနေရာတွင် ချိတ်ဆက်ပါ
    include 'auth/header.php'; 
    ?>

    <div class="bg-slate-900 text-white py-12 md:py-16 border-b border-slate-800 relative overflow-hidden">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,_var(--tw-gradient-stops))] from-amber-500/10 via-transparent to-transparent"></div>
        <div class="container mx-auto px-4 max-w-4xl text-center relative z-10">
            <span class="text-amber-500 text-xs font-bold tracking-widest uppercase bg-amber-500/10 px-3 py-1.5 rounded-full border border-amber-500/20">Guide</span>
            <h1 class="text-3xl md:text-4xl font-black tracking-tight mt-3 mb-4">စာအုပ်မှာယူရန် အဆင့်ဆင့်လမ်းညွှန်</h1>
            <p class="text-sm text-slate-400 max-w-xl mx-auto font-light leading-relaxed">
                Online Book Shop တွင် မိမိနှစ်သက်သော စာအုပ်များကို အောက်ပါအဆင့်များအတိုင်း လွယ်ကူလျင်မြန်စွာ မှာယူဝယ်ယူနိုင်ပါသည်။
            </p>
        </div>
    </div>

    <main class="flex-grow container mx-auto px-4 max-w-4xl py-12">
        
        <div class="relative border-l-2 border-slate-200 ml-4 md:ml-6 space-y-12 pb-4">
            
            <div class="relative pl-8 md:pl-10 group">
                <div class="absolute -left-[17px] top-0 w-8 h-8 rounded-xl bg-white border-2 border-amber-500 flex items-center justify-center text-amber-500 font-bold text-sm shadow-sm group-hover:bg-amber-500 group-hover:text-slate-900 transition-colors duration-200">
                    ၁
                </div>
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 hover:shadow-md transition-shadow duration-200">
                    <div class="flex items-center gap-3 mb-3">
                        <span class="w-8 h-8 bg-amber-100 rounded-lg flex items-center justify-center text-amber-600">
                            <i class="fa-solid fa-magnifying-glass text-sm"></i>
                        </span>
                        <h2 class="text-base font-bold text-slate-900">စာအုပ်ရှာဖွေ ရွေးချယ်ခြင်း</h2>
                    </div>
                    <p class="text-xs md:text-sm text-slate-600 leading-relaxed">
                        ဝဘ်ဆိုက်၏ <a href="<?= $base_url; ?>/books.php" class="text-amber-600 font-medium hover:underline">Books</a> Menu ထဲသို့သွား၍ မိမိဖတ်ရှုလိုသော သုတ၊ ရသ စာအုပ်များကို ရှာဖွေပါ။ စာအုပ်အသေးစိတ်ကို ကြည့်ရှုပြီးနောက် <strong>"Add to Cart"</strong> ခလုတ်ကို နှိပ်၍ ခြင်းတောင်းထဲသို့ ထည့်ပါ။
                    </p>
                </div>
            </div>

            <div class="relative pl-8 md:pl-10 group">
                <div class="absolute -left-[17px] top-0 w-8 h-8 rounded-xl bg-white border-2 border-amber-500 flex items-center justify-center text-amber-500 font-bold text-sm shadow-sm group-hover:bg-amber-500 group-hover:text-slate-900 transition-colors duration-200">
                    ၂
                </div>
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 hover:shadow-md transition-shadow duration-200">
                    <div class="flex items-center gap-3 mb-3">
                        <span class="w-8 h-8 bg-amber-100 rounded-lg flex items-center justify-center text-amber-600">
                            <i class="fa-solid fa-shopping-cart text-sm"></i>
                        </span>
                        <h2 class="text-base font-bold text-slate-900">ခြင်းတောင်းထဲရှိ အမှာစာများကို စစ်ဆေးခြင်း</h2>
                    </div>
                    <p class="text-xs md:text-sm text-slate-600 leading-relaxed">
                        ညာဘက်အပေါ်ထောင့်ရှိ <a href="<?= $base_url; ?>/user/cart.php" class="text-amber-600 font-medium hover:underline">Shopping Cart</a> အိုင်ကွန်ကို နှိပ်ပြီး မိမိရွေးချယ်ထားသော စာအုပ်အရေအတွက်နှင့် စုစုပေါင်းကျသင့်ငွေ မှန်ကန်မှု ရှိ၊ မရှိ စစ်ဆေးပါ။ ထို့နောက် <strong>"Proceed to Checkout"</strong> ကို နှိပ်ပါ။
                    </p>
                </div>
            </div>

            <div class="relative pl-8 md:pl-10 group">
                <div class="absolute -left-[17px] top-0 w-8 h-8 rounded-xl bg-white border-2 border-amber-500 flex items-center justify-center text-amber-500 font-bold text-sm shadow-sm group-hover:bg-amber-500 group-hover:text-slate-900 transition-colors duration-200">
                    ၃
                </div>
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 hover:shadow-md transition-shadow duration-200">
                    <div class="flex items-center gap-3 mb-3">
                        <span class="w-8 h-8 bg-amber-100 rounded-lg flex items-center justify-center text-amber-600">
                            <i class="fa-solid fa-truck text-sm"></i>
                        </span>
                        <h2 class="text-base font-bold text-slate-900">ပို့ဆောင်ရမည့် လိပ်စာဖြည့်သွင်းခြင်း</h2>
                    </div>
                    <p class="text-xs md:text-sm text-slate-600 leading-relaxed">
                        စာအုပ်များ ပို့ဆောင်ပေးရမည့် လူကြီးမင်း၏ <strong>အမည်၊ ဆက်သွယ်ရန် ဖုန်းနံပါတ် နှင့် တိကျသော လိပ်စာအပြည့်အစုံ</strong> ကို စနစ်တကျ ဖြည့်သွင်းပေးပါ။
                    </p>
                </div>
            </div>

            <div class="relative pl-8 md:pl-10 group">
                <div class="absolute -left-[17px] top-0 w-8 h-8 rounded-xl bg-white border-2 border-amber-500 flex items-center justify-center text-amber-500 font-bold text-sm shadow-sm group-hover:bg-amber-500 group-hover:text-slate-900 transition-colors duration-200">
                    ၄
                </div>
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 hover:shadow-md transition-shadow duration-200">
                    <div class="flex items-center gap-3 mb-3">
                        <span class="w-8 h-8 bg-amber-100 rounded-lg flex items-center justify-center text-amber-600">
                            <i class="fa-solid fa-credit-card text-sm"></i>
                        </span>
                        <h2 class="text-base font-bold text-slate-900">ငွေပေးချေခြင်းနှင့် ပြေစာတင်ပြခြင်း</h2>
                    </div>
                    <p class="text-xs md:text-sm text-slate-600 leading-relaxed">
                        ဝဘ်ဆိုက်တွင် ပြသထားသော KPay, Wave Pay သို့မဟုတ် Mobile Banking အကောင့်များသို့ ကျသင့်ငွေ လွှဲအပ်ပါ။ ထို့နောက် ငွေလွှဲပြေစာ <strong>(Screenshot/Receipt)</strong> ကို စနစ်ထဲတွင် Upload တင်၍ အမှာစာကို အတည်ပြုပါ။
                    </p>
                </div>
            </div>

            <div class="relative pl-8 md:pl-10 group">
                <div class="absolute -left-[17px] top-0 w-8 h-8 rounded-xl bg-white border-2 border-amber-500 flex items-center justify-center text-amber-500 font-bold text-sm shadow-sm group-hover:bg-amber-500 group-hover:text-slate-900 transition-colors duration-200">
                    ၅
                </div>
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 hover:shadow-md transition-shadow duration-200">
                    <div class="flex items-center gap-3 mb-3">
                        <span class="w-8 h-8 bg-amber-100 rounded-lg flex items-center justify-center text-amber-600">
                            <i class="fa-solid fa-circle-check text-sm"></i>
                        </span>
                        <h2 class="text-base font-bold text-slate-900">စောင့်ဆိုင်းခြင်းနှင့် ပစ္စည်းလက်ခံရရှိခြင်း</h2>
                    </div>
                    <p class="text-xs md:text-sm text-slate-600 leading-relaxed">
                        ကျွန်ုပ်တို့အဖွဲ့သားများမှ ငွေလွှဲပြေစာကို စစ်ဆေးအတည်ပြုပြီးပါက စာအုပ်များကို ပါဆယ်ထုပ်ပိုး၍ ပို့ဆောင်ရေးလုပ်ငန်းများထံ အမြန်ဆုံး အပ်နှံပေးသွားမည် ဖြစ်ပါသည်။ လူကြီးမင်း၏ <strong>My Orders</strong> စာမျက်နှာတွင် မှာယူမှု အခြေအနေကို အချိန်နှင့်တပြေးညီ စောင့်ကြည့်နိုင်ပါသည်။
                    </p>
                </div>
            </div>

        </div>

        <div class="mt-12 bg-amber-50 border border-amber-200/60 rounded-2xl p-6 text-center space-y-4">
            <h3 class="text-base font-bold text-slate-900">အဆင်မပြေမှုတစ်စုံတစ်ရာ ရှိပါသလား။</h3>
            <p class="text-xs md:text-sm text-slate-600 max-w-xl mx-auto">
                စာအုပ်မှာယူရာတွင် ခက်ခဲခြင်း သို့မဟုတ် စနစ်ပိုင်းဆိုင်ရာ အမှားအယွင်းများ ရှိခဲ့ပါက ဖုန်း သို့မဟုတ် Viber/Telegram တို့မှတဆင့် ကျွန်ုပ်တို့ထံ တိုက်ရိုက် ဆက်သွယ်အကူအညီ တောင်းခံနိုင်ပါသည်။
            </p>
            <div class="flex flex-wrap items-center justify-center gap-4 pt-2">
                <span class="text-xs font-semibold bg-white px-4 py-2 rounded-xl border border-slate-200 text-slate-700 inline-flex items-center gap-2">
                    <i class="fa-solid fa-phone text-amber-500"></i> 09-123456789
                </span>
                <span class="text-xs font-semibold bg-white px-4 py-2 rounded-xl border border-slate-200 text-slate-700 inline-flex items-center gap-2">
                    <i class="fa-brands fa-viber text-purple-500"></i> Viber Support
                </span>
            </div>
        </div>

    </main>

    <?php 
    // သင်၏ Footer Component ကို ပြန်ခေါ်ပါသည်
    include 'auth/footer.php'; 
    ?>

</body>
</html>