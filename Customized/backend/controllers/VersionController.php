<?php
require_once __DIR__ . '/../helpers/TierGuard.php';

class VersionController
{
    // GET version.limits
    public function getLimits($input)
    {
        $userId = authenticateRequest();
        if (!$userId) return ['success'=>false,'error_code'=>'UNAUTHORIZED','message'=>'Not authenticated','http_code'=>401];

        return ['success'=>true,'data'=> TierGuard::getLimitsInfo($userId)];
    }

    // POST version.plan.set  (dev/admin only)
    public function setPlan($input)
    {
        $userId = authenticateRequest();
        if (!$userId) return ['success'=>false,'error_code'=>'UNAUTHORIZED','message'=>'Not authenticated','http_code'=>401];

        $plan    = $input['plan'] ?? null;
        $allowed = ['pro','professional','enterprise'];
        if (!in_array($plan, $allowed, true)) {
            return ['success'=>false,'error_code'=>'INVALID_PLAN','message'=>'Must be one of: '.implode(', ',$allowed),'http_code'=>400];
        }

        $config  = require __DIR__ . '/../version_config.php';
        $pcfg    = $config[$plan];
        $db      = getDB();
        $db->prepare("UPDATE users SET plan=:plan WHERE id=:uid")->execute([':plan'=>$plan,':uid'=>$userId]);
        $db->prepare("
            INSERT INTO plan_subscriptions (user_id, plan, max_clients, max_invoices)
            VALUES (:uid,:plan,:mc,:mi)
            ON DUPLICATE KEY UPDATE plan=VALUES(plan), max_clients=VALUES(max_clients), max_invoices=VALUES(max_invoices)
        ")->execute([':uid'=>$userId,':plan'=>$plan,':mc'=>$pcfg['max_clients'],':mi'=>$pcfg['max_invoices']]);

        return ['success'=>true,'message'=>'Plan updated to '.$pcfg['label'],'data'=> TierGuard::getLimitsInfo($userId)];
    }
}
