<?php

if (!function_exists('red_admin_audit_retention_days')) {
    function red_admin_audit_retention_days()
    {
        return 180;
    }
}

if (!function_exists('red_admin_audit_event_allowed')) {
    function red_admin_audit_event_allowed($eventName)
    {
        return in_array($eventName, [
            'administrator.created',
            'administrator.updated',
            'administrator.deleted',
            'administrator.owner_bootstrapped',
            'component.public_placed',
            'article.created',
        ], true);
    }
}

if (!function_exists('red_admin_audit_target_allowed')) {
    function red_admin_audit_target_allowed($targetType)
    {
        return in_array(
            $targetType,
            ['administrator', 'component', 'article'],
            true
        );
    }
}

if (!function_exists('red_admin_audit_event_target_allowed')) {
    function red_admin_audit_event_target_allowed($eventName, $targetType)
    {
        if ($eventName === 'component.public_placed') {
            return $targetType === 'component';
        }
        if ($eventName === 'article.created') {
            return $targetType === 'article';
        }
        return str_starts_with($eventName, 'administrator.')
            && $targetType === 'administrator';
    }
}

if (!function_exists('red_admin_audit_cleanup')) {
    function red_admin_audit_cleanup($connection)
    {
        try {
            $retentionDays = red_admin_audit_retention_days();
            $stmt = mysqli_prepare(
                $connection,
                'DELETE FROM RED_Admin_Activity_Log WHERE OccurredAt < (CURRENT_TIMESTAMP - INTERVAL ' . $retentionDays . ' DAY) ORDER BY OccurredAt ASC LIMIT 500'
            );
            if (!$stmt) {
                return false;
            }

            $cleaned = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            return $cleaned;
        } catch (mysqli_sql_exception $e) {
            error_log('RED administrator audit retention cleanup failed: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('red_admin_audit_record')) {
    function red_admin_audit_record($connection, $eventName, $targetType, $targetRecordId, $actorAdminRecordId = 0)
    {
        $eventName = is_scalar($eventName) ? trim((string) $eventName) : '';
        $targetType = is_scalar($targetType) ? trim((string) $targetType) : '';
        $targetRecordId = (int) $targetRecordId;
        $actorAdminRecordId = (int) $actorAdminRecordId;
        if ($actorAdminRecordId <= 0) {
            $actorAdminRecordId = (int) ($_SESSION['AdminRecordID'] ?? 0);
        }

        if (
            $actorAdminRecordId <= 0
            || $targetRecordId <= 0
            || !red_admin_audit_event_allowed($eventName)
            || !red_admin_audit_target_allowed($targetType)
            || !red_admin_audit_event_target_allowed($eventName, $targetType)
        ) {
            return false;
        }

        red_admin_audit_cleanup($connection);

        try {
            $stmt = mysqli_prepare(
                $connection,
                'INSERT INTO RED_Admin_Activity_Log (EventName, ActorAdminRecordID, TargetType, TargetRecordID) VALUES (?, ?, ?, ?)'
            );
            if (!$stmt) {
                return false;
            }

            mysqli_stmt_bind_param(
                $stmt,
                'sisi',
                $eventName,
                $actorAdminRecordId,
                $targetType,
                $targetRecordId
            );
            $recorded = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            return $recorded;
        } catch (mysqli_sql_exception $e) {
            error_log('RED administrator audit insert failed: ' . $e->getMessage());
            return false;
        }
    }
}

?>
