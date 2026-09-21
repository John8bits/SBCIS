<?php

declare(strict_types=1);

namespace App\Services;

use JsonException;
use PDO;
use Throwable;

final class AuditLogger
{
    public static function record(
        PDO $database,
        string $action,
        string $entityType,
        int|string|null $entityId,
        array $details = [],
        ?int $adminId = null
    ): void {
        try {
            $json = $details ? json_encode($details, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
            $statement = $database->prepare('INSERT INTO audit_log
                (admin_id, action, entity_type, entity_id, details_json)
                VALUES (:admin_id, :action, :entity_type, :entity_id, :details_json)');
            $statement->execute([
                ':admin_id' => $adminId,
                ':action' => mb_substr($action, 0, 50),
                ':entity_type' => mb_substr($entityType, 0, 50),
                ':entity_id' => $entityId === null ? null : mb_substr((string) $entityId, 0, 100),
                ':details_json' => $json,
            ]);
        } catch (JsonException $error) {
            error_log('Audit JSON encoding failed: ' . $error->getMessage());
        } catch (Throwable $error) {
            // Audit availability must be monitored, but must not turn a committed
            // domain transaction into a misleading application failure.
            error_log('Audit logging failed: ' . $error->getMessage());
        }
    }
}
