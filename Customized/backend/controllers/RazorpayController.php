<?php
require_once __DIR__ . '/../helpers/TierGuard.php';

class RazorpayController
{
    // POST razorpay.order.create
    // body: { plan, extra_clients, extra_invoices }
    public function createOrder($input)
    {
        $userId = authenticateRequest();
        if (!$userId) return $this->unauth();

        $plan          = $input['plan']           ?? 'professional';
        $extraClients  = max(0, (int)($input['extra_clients']  ?? 0));
        $extraInvoices = max(0, (int)($input['extra_invoices'] ?? 0));

        $amountPaise = $this->calcAmount($plan, $extraClients, $extraInvoices);
        if ($amountPaise <= 0) {
            return ['success'=>false,'error_code'=>'INVALID_AMOUNT','message'=>'Invalid plan or amount','http_code'=>400];
        }

        $db   = getDB();
        $user = $db->prepare("SELECT name, email FROM users WHERE id=?")->execute([$userId])
              ? $db->query("SELECT name, email FROM users WHERE id=$userId")->fetch()
              : [];

        // Fetch user details properly
        $stmt = $db->prepare("SELECT name, email FROM users WHERE id=:uid");
        $stmt->execute([':uid' => $userId]);
        $user = $stmt->fetch();

        $payload = [
            'amount'   => $amountPaise,
            'currency' => 'INR',
            'receipt'  => 'inv_'.$userId.'_'.time(),
            'notes'    => [
                'user_id'        => (string)$userId,
                'plan'           => $plan,
                'extra_clients'  => (string)$extraClients,
                'extra_invoices' => (string)$extraInvoices,
            ],
        ];

        $ch = curl_init('https://api.razorpay.com/v1/orders');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_USERPWD        => RAZORPAY_KEY_ID.':'.RAZORPAY_KEY_SECRET,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $resp     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            return ['success'=>false,'error_code'=>'RAZORPAY_ERROR','message'=>'Razorpay order creation failed','http_code'=>502];
        }

        $order = json_decode($resp, true);

        $db->prepare("
            INSERT INTO razorpay_orders
                (user_id, razorpay_order_id, plan, amount, currency, extra_clients, extra_invoices)
            VALUES (:uid,:oid,:plan,:amt,'INR',:ec,:ei)
        ")->execute([
            ':uid'  => $userId,
            ':oid'  => $order['id'],
            ':plan' => $plan,
            ':amt'  => $amountPaise,
            ':ec'   => $extraClients,
            ':ei'   => $extraInvoices,
        ]);

        return [
            'success' => true,
            'data'    => [
                'order_id'   => $order['id'],
                'amount'     => $amountPaise,
                'currency'   => 'INR',
                'key_id'     => RAZORPAY_KEY_ID,
                'plan'       => $plan,
                'user_name'  => $user['name']  ?? '',
                'user_email' => $user['email'] ?? '',
            ]
        ];
    }

    // POST razorpay.payment.verify
    // body: { razorpay_order_id, razorpay_payment_id, razorpay_signature }
    public function verifyPayment($input)
    {
        $userId = authenticateRequest();
        if (!$userId) return $this->unauth();

        $orderId   = $input['razorpay_order_id']  ?? '';
        $paymentId = $input['razorpay_payment_id'] ?? '';
        $signature = $input['razorpay_signature']  ?? '';

        $expected = hash_hmac('sha256', $orderId.'|'.$paymentId, RAZORPAY_KEY_SECRET);
        if (!hash_equals($expected, $signature)) {
            return ['success'=>false,'error_code'=>'INVALID_SIGNATURE','message'=>'Payment signature mismatch','http_code'=>400];
        }

        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM razorpay_orders WHERE razorpay_order_id=:oid AND user_id=:uid");
        $stmt->execute([':oid'=>$orderId,':uid'=>$userId]);
        $order = $stmt->fetch();

        if (!$order) {
            return ['success'=>false,'error_code'=>'ORDER_NOT_FOUND','message'=>'Order not found','http_code'=>404];
        }

        $db->prepare("UPDATE razorpay_orders SET status='paid', razorpay_payment_id=:pid WHERE razorpay_order_id=:oid")
           ->execute([':pid'=>$paymentId,':oid'=>$orderId]);

        $this->activatePlan($userId, $order['plan'], (int)$order['extra_clients'], (int)$order['extra_invoices']);

        $guard = new TierGuard($db, $userId);
        return [
            'success' => true,
            'message' => 'Payment verified. Plan activated!',
            'data'    => $guard->getLimitsInfo()
        ];
    }

    // GET razorpay.pricing  (no auth needed – call from public)
    public function pricing($input)
    {
        return [
            'success' => true,
            'data'    => [
                'professional' => [
                    'name'          => 'Professional',
                    'price_paise'   => 99900,
                    'price_display' => '₹999/mo',
                    'billing'       => 'monthly',
                    'max_clients'   => 50,
                    'max_invoices'  => 100,
                ],
                'enterprise' => [
                    'name'               => 'Enterprise',
                    'base_price_paise'   => 299900,
                    'base_display'       => '₹2,999',
                    'billing'            => 'annual',
                    'billing_label'      => '/year',
                    'per_client_paise'   => 5000,
                    'per_client_display' => '₹50',
                    'per_invoice_paise'  => 2000,
                    'per_invoice_display'=> '₹20',
                    'base_clients'       => 200,
                    'base_invoices'      => 500,
                ],
            ]
        ];
    }

    // ── private ──────────────────────────────────────────────────────────────

    private function calcAmount(string $plan, int $extraClients, int $extraInvoices): int
    {
        if ($plan === 'professional') return 99900;
        if ($plan === 'enterprise')   return 299900 + ($extraClients * 5000) + ($extraInvoices * 2000);
        return 0;
    }

    private function activatePlan(int $userId, string $plan, int $extraClients, int $extraInvoices): void
    {
        $maxClients  = match($plan) {
            'professional' => 50,
            'enterprise'   => 200 + $extraClients,
            default        => 10,
        };
        $maxInvoices = match($plan) {
            'professional' => 100,
            'enterprise'   => 500 + $extraInvoices,
            default        => 20,
        };

        // Enterprise is annual — set expiry to exactly 1 year from now.
        // Professional is monthly — no expiry tracked (unlimited until cancelled).
        $expiresAt = ($plan === 'enterprise') ? date('Y-m-d H:i:s', strtotime('+1 year')) : null;

        $db = getDB();
        $db->prepare("
            INSERT INTO plan_subscriptions (user_id, plan, max_clients, max_invoices, expires_at)
            VALUES (:uid,:plan,:mc,:mi,:exp)
            ON DUPLICATE KEY UPDATE plan=VALUES(plan), max_clients=VALUES(max_clients),
                                    max_invoices=VALUES(max_invoices), expires_at=VALUES(expires_at),
                                    activated_at=CURRENT_TIMESTAMP
        ")->execute([':uid'=>$userId,':plan'=>$plan,':mc'=>$maxClients,':mi'=>$maxInvoices,':exp'=>$expiresAt]);

        $db->prepare("UPDATE users SET plan=:plan WHERE id=:uid")->execute([':plan'=>$plan,':uid'=>$userId]);
    }

    private function unauth(): array
    {
        return ['success'=>false,'error_code'=>'UNAUTHORIZED','message'=>'Not authenticated','http_code'=>401];
    }
}
