<?php
// إعدادات البوت
$bot_token = "8099690773:AAGP-yu9PjMbfTmRRRPj_nrZ6daPPdeKwRg";
$admin_id = 6873334348;
$channel_id = "-1002530096487";
$data_file = "subs.json";

// استقبال التحديثات
$content = file_get_contents("php://input");
$update = json_decode($content, true);

function sendMessage($chat_id, $text, $keyboard = null) {
    global $bot_token;
    $url = "https://api.telegram.org/bot$bot_token/sendMessage";
    $post = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => "HTML"
    ];
    if ($keyboard) {
        $post['reply_markup'] = json_encode(['inline_keyboard' => $keyboard]);
    }
    file_get_contents($url . "?" . http_build_query($post));
}

function kickUser($user_id) {
    global $bot_token, $channel_id;
    $url = "https://api.telegram.org/bot$bot_token/kickChatMember";
    file_get_contents($url . "?chat_id=$channel_id&user_id=$user_id");
}

function unbanUser($user_id) {
    global $bot_token, $channel_id;
    $url = "https://api.telegram.org/bot$bot_token/unbanChatMember";
    file_get_contents($url . "?chat_id=$channel_id&user_id=$user_id");
}

function loadSubs() {
    global $data_file;
    if (!file_exists($data_file)) file_put_contents($data_file, '{}');
    return json_decode(file_get_contents($data_file), true);
}

function saveSubs($subs) {
    global $data_file;
    file_put_contents($data_file, json_encode($subs));
}

$chat_id = $update['message']['chat']['id'] ?? $update['callback_query']['message']['chat']['id'] ?? null;
$user_id = $update['message']['from']['id'] ?? $update['callback_query']['from']['id'] ?? null;
$username = $update['message']['from']['username'] ?? $update['callback_query']['from']['username'] ?? null;
$text = $update['message']['text'] ?? null;

// عند الضغط على /start
if ($text == "/start") {
    sendMessage($chat_id, "👋 مرحباً بك في بوت إدارة اشتراك قناة التلجرام!
اضغط زر الاشتراك لبدء طلب الاشتراك.", [
        [['text' => "🔔 طلب اشتراك", 'callback_data' => "subscribe"]]
    ]);
    exit;
}

// عند الضغط على زر الاشتراك
if (isset($update['callback_query']) && $update['callback_query']['data'] == "subscribe") {
    $subs = loadSubs();
    if (isset($subs[$user_id])) {
        sendMessage($chat_id, "✅ لديك طلب اشتراك جاري أو اشتراك فعال بالفعل.");
    } else {
        $subs[$user_id] = [
            "status" => "pending",
            "username" => $username,
            "start" => null,
            "end" => null
        ];
        saveSubs($subs);
        sendMessage($chat_id, "⏳ تم إرسال طلب الاشتراك للمشرف. انتظر الموافقة.");
        // إشعار للمشرف
        sendMessage($admin_id, "طلب اشتراك جديد من @$username (ID: $user_id)", [
            [['text' => "✅ تفعيل الاشتراك", 'callback_data' => "activate_$user_id"]],
            [['text' => "❌ رفض", 'callback_data' => "reject_$user_id"]]
        ]);
    }
    exit;
}

// عند تفعيل الاشتراك من المشرف
if (isset($update['callback_query']) && strpos($update['callback_query']['data'], "activate_") === 0 && $user_id == $admin_id) {
    $target_id = intval(str_replace("activate_", "", $update['callback_query']['data']));
    $subs = loadSubs();
    if (isset($subs[$target_id])) {
        $subs[$target_id]['status'] = "active";
        $subs[$target_id]['start'] = time();
        $subs[$target_id]['end'] = time() + 30 * 86400;
        saveSubs($subs);
        // دعوة المستخدم للقناة
        unbanUser($target_id);
        sendMessage($target_id, "✅ تم تفعيل اشتراكك في القناة لمدة 30 يوماً.\nانضم للقناة: https://t.me/c/" . ltrim($channel_id, "-100"));
        sendMessage($admin_id, "✅ تم تفعيل اشتراك @$subs[$target_id][username]");
    }
    exit;
}

// عند رفض الاشتراك من المشرف
if (isset($update['callback_query']) && strpos($update['callback_query']['data'], "reject_") === 0 && $user_id == $admin_id) {
    $target_id = intval(str_replace("reject_", "", $update['callback_query']['data']));
    $subs = loadSubs();
    if (isset($subs[$target_id])) {
        unset($subs[$target_id]);
        saveSubs($subs);
        sendMessage($target_id, "❌ تم رفض طلب اشتراكك من قبل المشرف.");
        sendMessage($admin_id, "❌ تم رفض طلب @$subs[$target_id][username]");
    }
    exit;
}

// زر تجديد الاشتراك
if (isset($update['callback_query']) && $update['callback_query']['data'] == "renew") {
    $subs = loadSubs();
    if (!isset($subs[$user_id]) || $subs[$user_id]['status'] != "active") {
        sendMessage($chat_id, "❗️ لا يوجد لديك اشتراك فعال لتجديده.");
    } else {
        $subs[$user_id]['status'] = "renew_pending";
        saveSubs($subs);
        sendMessage($chat_id, "⏳ تم إرسال طلب تجديد الاشتراك للمشرف.");
        sendMessage($admin_id, "طلب تجديد اشتراك من @$username (ID: $user_id)", [
            [['text' => "✅ الموافقة على التجديد", 'callback_data' => "renewok_$user_id"]],
            [['text' => "❌ رفض التجديد", 'callback_data' => "renewreject_$user_id"]]
        ]);
    }
    exit;
}

// المشرف يوافق على تجديد الاشتراك
if (isset($update['callback_query']) && strpos($update['callback_query']['data'], "renewok_") === 0 && $user_id == $admin_id) {
    $target_id = intval(str_replace("renewok_", "", $update['callback_query']['data']));
    $subs = loadSubs();
    if (isset($subs[$target_id]) && $subs[$target_id]['status'] == "renew_pending") {
        $subs[$target_id]['status'] = "active";
        $subs[$target_id]['start'] = time();
        $subs[$target_id]['end'] = time() + 30 * 86400;
        saveSubs($subs);
        sendMessage($target_id, "✅ تم تجديد اشتراكك لمدة 30 يومًا إضافية.");
        sendMessage($admin_id, "✅ تم تجديد اشتراك @$subs[$target_id][username]");
    }
    exit;
}

// المشرف يرفض تجديد الاشتراك
if (isset($update['callback_query']) && strpos($update['callback_query']['data'], "renewreject_") === 0 && $user_id == $admin_id) {
    $target_id = intval(str_replace("renewreject_", "", $update['callback_query']['data']));
    $subs = loadSubs();
    if (isset($subs[$target_id])) {
        $subs[$target_id]['status'] = "active"; // يعود كما كان
        saveSubs($subs);
        sendMessage($target_id, "❌ تم رفض تجديد اشتراكك من قبل المشرف.");
        sendMessage($admin_id, "❌ تم رفض تجديد @$subs[$target_id][username]");
    }
    exit;
}

// إشعار قبل انتهاء الاشتراك بـ5 أيام أو الطرد
if (php_sapi_name() === 'cli') {
    // تشغيل هذه الجزئية تلقائياً عبر كرن أو خدمة مثل UptimeRobot (مثلاً كل ساعة)
    $subs = loadSubs();
    foreach ($subs as $uid => $info) {
        if ($info['status'] == "active") {
            $remaining = $info['end'] - time();
            if ($remaining <= 5 * 86400 && $remaining > 4.8 * 86400 && empty($info['warned'])) {
                sendMessage($uid, "⏰ تبقى 5 أيام على انتهاء اشتراكك! يرجى التجديد لتجنب الطرد. يمكنك الضغط على الزر لتجديد الاشتراك.", [
                    [['text' => "🔄 تجديد الاشتراك", 'callback_data' => "renew"]]
                ]);
                $subs[$uid]['warned'] = true;
            }
            if ($remaining <= 0) {
                sendMessage($uid, "🚫 انتهى اشتراكك وتم طردك من القناة.");
                kickUser($uid);
                unset($subs[$uid]);
            }
        }
    }
    saveSubs($subs);
    exit;
}
?>
