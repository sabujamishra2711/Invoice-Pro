<?php
function post($url, $data) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}

function authGet($url, $token) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}

$base = 'http://localhost/invoice-management/backend/api/index.php?route=';
$email = 'testauth_' . time() . '@example.com';

echo "=== 1. REGISTER ===\n";
$r = post($base . 'auth.register', ['name'=>'Test Auth User','email'=>$email,'password'=>'Test1234','phone'=>'+91 99999 00000']);
echo json_encode($r, JSON_PRETTY_PRINT) . "\n";
$token = $r['data']['token'] ?? null;

echo "\n=== 2. LOGIN (before verify) ===\n";
$r2 = post($base . 'auth.login', ['email'=>$email,'password'=>'Test1234']);
echo json_encode($r2, JSON_PRETTY_PRINT) . "\n";

echo "\n=== 3. LOGIN wrong password ===\n";
$r3 = post($base . 'auth.login', ['email'=>$email,'password'=>'WrongPass']);
echo json_encode($r3, JSON_PRETTY_PRINT) . "\n";

echo "\n=== 4. OTP SEND (reset) ===\n";
$r4 = post($base . 'auth.otp.send', ['email'=>$email,'purpose'=>'reset']);
echo json_encode($r4, JSON_PRETTY_PRINT) . "\n";

echo "\n=== 5. PROFILE UPDATE ===\n";
$r5 = post($base . 'auth.profile.update', ['name'=>'Updated Name','phone'=>'+91 88888 77777']);
echo "No auth header: " . ($r5['message'] ?? 'no message') . "\n";

echo "\n=== 6. DUPLICATE REGISTER ===\n";
$r6 = post($base . 'auth.register', ['name'=>'Dup','email'=>$email,'password'=>'Test5678']);
echo "Expected 409: " . ($r6['message'] ?? '?') . "\n";

echo "\nDone.\n";
