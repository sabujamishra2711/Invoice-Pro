<?php
/**
 * TierGuard — enforces plan limits and feature flags.
 *
 * Usage in controllers:
 *   TierGuard::assertCanCreateClient($userId);
 *   TierGuard::assertCanCreateInvoice($userId);
 *   TierGuard::assertFeature($userId, 'export_reports');
 *   $info = TierGuard::getLimitsInfo($userId);
 */
class TierGuard
{
    // ── public static API ────────────────────────────────────────────────────

    public static function assertCanCreateClient(int $userId): void
    {
        $sub = self::getSub($userId);
        $max = (int)$sub['max_clients'];
        if ($max === -1) return;
        $count = self::countClients($userId);
        if ($count >= $max) self::limitReached('clients', $count, $max, $sub['plan']);
    }

    public static function assertCanCreateInvoice(int $userId): void
    {
        $sub = self::getSub($userId);
        $max = (int)$sub['max_invoices'];
        if ($max === -1) return;
        $count = self::countInvoices($userId);
        if ($count >= $max) self::limitReached('invoices', $count, $max, $sub['plan']);
    }

    public static function assertFeature(int $userId, string $feature): void
    {
        $sub    = self::getSub($userId);
        $config = self::config();
        $plan   = $sub['plan'];
        $has    = (bool)($config[$plan]['features'][$feature] ?? false);
        if (!$has) {
            http_response_code(403);
            echo json_encode([
                'success'    => false,
                'error_code' => 'FEATURE_LOCKED',
                'feature'    => $feature,
                'plan'       => $plan,
            ]);
            exit;
        }
    }

    public static function getLimitsInfo(int $userId): array
    {
        $sub    = self::getSub($userId);
        $plan   = $sub['plan'];
        $config = self::config();
        $planCfg = $config[$plan] ?? $config['pro'];

        $info = [
            'plan'         => $plan,
            'plan_label'   => $planCfg['label'],
            'max_clients'  => (int)$sub['max_clients'],
            'max_invoices' => (int)$sub['max_invoices'],
            'used_clients' => self::countClients($userId),
            'used_invoices'=> self::countInvoices($userId),
            'features'     => $planCfg['features'],
            'expires_at'   => $sub['expires_at'] ?? null,
        ];

        // Add human-readable renewal date for enterprise
        if ($plan === 'enterprise' && !empty($sub['expires_at'])) {
            $info['renews_on'] = date('j M Y', strtotime($sub['expires_at']));
        }

        return $info;
    }

    // ── private helpers ───────────────────────────────────────────────────────

    private static function getSub(int $userId): array
    {
        $db   = getDB();
        $stmt = $db->prepare("SELECT plan, max_clients, max_invoices, expires_at FROM plan_subscriptions WHERE user_id=:uid");
        $stmt->execute([':uid' => $userId]);
        $row  = $stmt->fetch();

        if (!$row) {
            // Auto-seed from users.plan
            $u = $db->prepare("SELECT plan FROM users WHERE id=:uid");
            $u->execute([':uid' => $userId]);
            $user   = $u->fetch();
            $plan   = $user['plan'] ?? 'pro';
            $cfg    = self::config();
            $pcfg   = $cfg[$plan] ?? $cfg['pro'];
            $db->prepare("
                INSERT IGNORE INTO plan_subscriptions (user_id, plan, max_clients, max_invoices)
                VALUES (:uid,:plan,:mc,:mi)
            ")->execute([':uid'=>$userId,':plan'=>$plan,':mc'=>$pcfg['max_clients'],':mi'=>$pcfg['max_invoices']]);
            return ['plan'=>$plan,'max_clients'=>$pcfg['max_clients'],'max_invoices'=>$pcfg['max_invoices'],'expires_at'=>null];
        }

        // Check if an enterprise plan has expired
        if ($row['plan'] === 'enterprise' && !empty($row['expires_at'])) {
            if (strtotime($row['expires_at']) < time()) {
                // Expired — downgrade to pro
                $cfg  = self::config();
                $pro  = $cfg['pro'];
                $db->prepare("
                    UPDATE plan_subscriptions
                    SET plan='pro', max_clients=:mc, max_invoices=:mi, expires_at=NULL
                    WHERE user_id=:uid
                ")->execute([':mc'=>$pro['max_clients'],':mi'=>$pro['max_invoices'],':uid'=>$userId]);
                $db->prepare("UPDATE users SET plan='pro' WHERE id=:uid")->execute([':uid'=>$userId]);
                return ['plan'=>'pro','max_clients'=>$pro['max_clients'],'max_invoices'=>$pro['max_invoices'],'expires_at'=>null];
            }
        }

        return $row;
    }

    private static function countClients(int $userId): int
    {
        $s = getDB()->prepare("SELECT COUNT(*) FROM clients WHERE user_id=:uid AND deleted_at IS NULL");
        $s->execute([':uid'=>$userId]);
        return (int)$s->fetchColumn();
    }

    private static function countInvoices(int $userId): int
    {
        $s = getDB()->prepare("SELECT COUNT(*) FROM invoices WHERE user_id=:uid AND deleted_at IS NULL");
        $s->execute([':uid'=>$userId]);
        return (int)$s->fetchColumn();
    }

    private static function config(): array
    {
        static $cfg = null;
        if ($cfg === null) $cfg = require __DIR__ . '/../version_config.php';
        return $cfg;
    }

    private static function limitReached(string $resource, int $current, int $limit, string $plan): void
    {
        http_response_code(403);
        echo json_encode([
            'success'    => false,
            'error_code' => 'LIMIT_REACHED',
            'resource'   => $resource,
            'current'    => $current,
            'limit'      => $limit,
            'plan'       => $plan,
        ]);
        exit;
    }
}
