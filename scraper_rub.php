<?php

$url = 'https://www.mbbru.ru/fizicheskim-litsam/mb-rial/';

$opts = [
    "http" => [
        "method" => "GET",
        "header" => "User-Agent: Mozilla/5.0\r\n"
    ]
];

$context = stream_context_create($opts);
$html = @file_get_contents($url, false, $context);

if (!$html) {
    die('خطا در دریافت سایت');
}

// 1️⃣ پیدا کردن تمام ردیف‌ها
if (preg_match_all('/<div class="phys-detail-table-row">(.*?)<\/div>/s', $html, $rows)) {

    foreach ($rows[1] as $row) {

        // 2️⃣ فقط ردیف RUB/IRR
        if (strpos($row, 'RUB/IRR') !== false) {

            // 3️⃣ گرفتن همه span ها
            if (preg_match_all('/<span.*?>(.*?)<\/span>/s', $row, $spans)) {

                // span سوم = قیمت فروش
                if (isset($spans[1][2])) {

                    $price_raw = trim($spans[1][2]);
                    $price = (int) str_replace(' ', '', $price_raw);

                    echo 'قیمت فروش RUB: ' . $price;
                    exit;
                }
            }
        }
    }
}

echo 'قیمت پیدا نشد';
