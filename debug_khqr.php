<?php

require __DIR__ . '/backend/vendor/autoload.php';
require __DIR__ . '/backend/app/Services/KhqrService.php';

use App\Services\KhqrService;

$service = new KhqrService();

$id = "vorak_yun@bkrt";
$amount = 1.00;
$orderId = "ORD-TEST";
$name = "Vorak Shop";
$city = "Phnom Penh";

echo "Generating KHQR for: $id\n";

$qr = $service->generateKhqrString($id, $amount, $orderId, $name, $city);

echo "Raw QR String:\n$qr\n\n";

// Manual Breakdown
echo "Breakdown:\n";
$i = 0;
while ($i < strlen($qr)) {
    $tag = substr($qr, $i, 2);
    $len = substr($qr, $i + 2, 2);
    $val = substr($qr, $i + 4, intval($len));

    echo "Tag: $tag | Len: $len | Val: $val\n";

    if ($tag == '29') {
        echo "  --- Tag 29 Inner ---\n";
        $j = 0;
        while ($j < strlen($val)) {
            $stag = substr($val, $j, 2);
            $slen = substr($val, $j + 2, 2);
            $sval = substr($val, $j + 4, intval($slen));
            echo "  SubTag: $stag | Len: $slen | Val: $sval\n";
            $j += 4 + intval($slen);
        }
        echo "  --------------------\n";
    }

    $i += 4 + intval($len);
}

echo "\nCRC Check:\n";
$crcCalculated = substr($qr, -4);
echo "CRC in String: $crcCalculated\n";
