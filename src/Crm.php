<?php

declare(strict_types=1);

final class Crm
{
    public const STAGES = ['NEW', 'CONTACTED', 'QUALIFIED', 'PROPOSAL', 'NEGOTIATION', 'WON', 'LOST'];
    public const SOURCES = ['REFERRAL', 'WEBSITE', 'WHATSAPP', 'SOCIAL_MEDIA', 'COLD_CALL', 'WALK_IN', 'OTHER'];
    public const ACTIVITY_TYPES = ['CALL', 'MEETING', 'WHATSAPP', 'EMAIL', 'FOLLOW_UP', 'NOTE', 'OTHER'];

    public function __construct(private PDO $pdo, private Auth $auth) {}

    /** @param array<string, mixed> $user @return array<string, mixed> */
    public function dashboard(array $user): array
    {
        [$scope, $bindings] = $this->leadScope($user, 'l');
        $todayStart = date('Y-m-d 00:00:00');
        $tomorrowStart = date('Y-m-d 00:00:00', strtotime('+1 day'));
        $metricSql = "SELECT
            COUNT(*) total_leads,
            SUM(CASE WHEN l.stage = 'NEW' THEN 1 ELSE 0 END) new_leads,
            SUM(CASE WHEN l.next_follow_up >= ? AND l.next_follow_up < ? AND l.stage NOT IN ('WON','LOST') THEN 1 ELSE 0 END) due_today,
            SUM(CASE WHEN l.next_follow_up < ? AND l.stage NOT IN ('WON','LOST') THEN 1 ELSE 0 END) overdue,
            SUM(CASE WHEN l.stage = 'WON' THEN 1 ELSE 0 END) won,
            SUM(CASE WHEN l.stage = 'LOST' THEN 1 ELSE 0 END) lost
            FROM crm_leads l WHERE {$scope}";
        $statement = $this->pdo->prepare($metricSql);
        $statement->execute([$todayStart, $tomorrowStart, $todayStart, ...$bindings]);
        $metrics = $statement->fetch() ?: [];

        $activityScope = $user['role'] === 'OWNER' ? '1=1' : 'a.user_id = ?';
        $activityBindings = $user['role'] === 'OWNER' ? [] : [(int) $user['id']];
        $statement = $this->pdo->prepare("SELECT
            SUM(CASE WHEN a.activity_type = 'CALL' THEN 1 ELSE 0 END) calls_today,
            SUM(CASE WHEN a.activity_type = 'MEETING' THEN 1 ELSE 0 END) meetings_today
            FROM crm_activities a WHERE a.activity_datetime >= ? AND a.activity_datetime < ? AND {$activityScope}");
        $statement->execute([$todayStart, $tomorrowStart, ...$activityBindings]);
        $metrics = array_merge($metrics, $statement->fetch() ?: []);

        $pipelineStatement = $this->pdo->prepare("SELECT l.stage, COUNT(*) total FROM crm_leads l WHERE {$scope} GROUP BY l.stage");
        $pipelineStatement->execute($bindings);
        $pipeline = array_fill_keys(self::STAGES, 0);
        foreach ($pipelineStatement->fetchAll() as $row) {
            $pipeline[$row['stage']] = (int) $row['total'];
        }

        $followUpStatement = $this->pdo->prepare("SELECT l.*, u.name assigned_name FROM crm_leads l LEFT JOIN crm_users u ON u.id = l.assigned_to WHERE {$scope} AND l.next_follow_up < ? AND l.stage NOT IN ('WON','LOST') ORDER BY l.next_follow_up ASC LIMIT 6");
        $followUpStatement->execute([...$bindings, $tomorrowStart]);

        return [
            'metrics' => array_map('intval', $metrics),
            'pipeline' => $pipeline,
            'followUps' => $followUpStatement->fetchAll(),
            'team' => $user['role'] === 'OWNER' ? $this->teamSummary() : [],
        ];
    }

    /** @param array<string, mixed> $user @param array<string, mixed> $filters @return array{rows:array<int,array<string,mixed>>, total:int, page:int, pages:int} */
    public function leads(array $user, array $filters): array
    {
        [$scope, $bindings] = $this->leadScope($user, 'l');
        $where = [$scope];
        $params = $bindings;

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $where[] = '(l.name LIKE ? OR l.company_name LIKE ? OR l.phone LIKE ? OR l.email LIKE ?)';
            array_push($params, ...array_fill(0, 4, '%'.$search.'%'));
        }
        foreach (['stage', 'city', 'lead_source'] as $field) {
            if (! empty($filters[$field])) {
                $where[] = "l.{$field} = ?";
                $params[] = $filters[$field];
            }
        }
        if ($user['role'] === 'OWNER' && ! empty($filters['assigned_to'])) {
            $where[] = 'l.assigned_to = ?';
            $params[] = (int) $filters['assigned_to'];
        }
        if (! empty($filters['follow_up_date'])) {
            $where[] = 'l.next_follow_up >= ? AND l.next_follow_up < ?';
            $params[] = $filters['follow_up_date'].' 00:00:00';
            $params[] = date('Y-m-d 00:00:00', strtotime($filters['follow_up_date'].' +1 day'));
        }

        $whereSql = implode(' AND ', $where);
        $count = $this->pdo->prepare("SELECT COUNT(*) FROM crm_leads l WHERE {$whereSql}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = 20;
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        $query = $this->pdo->prepare("SELECT l.*, u.name assigned_name,
            (SELECT MAX(a.activity_datetime) FROM crm_activities a WHERE a.lead_id = l.id) last_activity
            FROM crm_leads l LEFT JOIN crm_users u ON u.id = l.assigned_to
            WHERE {$whereSql} ORDER BY l.updated_at DESC, l.id DESC LIMIT {$perPage} OFFSET {$offset}");
        $query->execute($params);

        return ['rows' => $query->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /** @param array<string, mixed> $user @return array<string, mixed> */
    public function lead(int $id, array $user): array
    {
        [$scope, $bindings] = $this->leadScope($user, 'l');
        $statement = $this->pdo->prepare("SELECT l.*, u.name assigned_name FROM crm_leads l LEFT JOIN crm_users u ON u.id = l.assigned_to WHERE l.id = ? AND {$scope} LIMIT 1");
        $statement->execute([$id, ...$bindings]);
        $lead = $statement->fetch();
        if (! $lead) {
            http_response_code(404);
            exit('Lead not found.');
        }

        return $lead;
    }

    /** @param array<string, mixed> $input @param array<string, mixed> $user */
    public function saveLead(array $input, array $user, ?int $id = null): int
    {
        $assignedTo = $user['role'] === 'OWNER' ? ($input['assigned_to'] ?: null) : (int) $user['id'];
        if ($assignedTo && ! $this->isActiveBde((int) $assignedTo)) {
            throw new InvalidArgumentException('Choose an active BDE.');
        }

        $values = [
            trim((string) $input['name']), trim((string) ($input['company_name'] ?? '')) ?: null,
            trim((string) $input['phone']), trim((string) ($input['email'] ?? '')) ?: null,
            trim((string) ($input['city'] ?? '')) ?: null, $input['lead_source'] ?: null, $assignedTo,
            $input['stage'], $input['estimated_value'] !== '' ? $input['estimated_value'] : null,
            $this->normalizeDateTime($input['next_follow_up'] ?? null), trim((string) ($input['notes'] ?? '')) ?: null,
            $input['stage'] === 'LOST' ? (trim((string) ($input['lost_reason'] ?? '')) ?: null) : null,
        ];

        if ($id) {
            $this->lead($id, $user);
            $statement = $this->pdo->prepare('UPDATE crm_leads SET name=?, company_name=?, phone=?, email=?, city=?, lead_source=?, assigned_to=?, stage=?, estimated_value=?, next_follow_up=?, notes=?, lost_reason=?, updated_at=CURRENT_TIMESTAMP WHERE id=?');
            $statement->execute([...$values, $id]);

            return $id;
        }

        $statement = $this->pdo->prepare('INSERT INTO crm_leads (name, company_name, phone, email, city, lead_source, assigned_to, stage, estimated_value, next_follow_up, notes, lost_reason, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $statement->execute([...$values, (int) $user['id']]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $input @param array<string, mixed> $user */
    public function addActivity(int $leadId, array $input, array $user): void
    {
        $this->lead($leadId, $user);
        $activityDateTime = $this->normalizeDateTime($input['activity_datetime']) ?: date('Y-m-d H:i:s');
        $nextFollowUp = $this->normalizeDateTime($input['next_follow_up'] ?? null);

        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare('INSERT INTO crm_activities (lead_id, user_id, activity_type, activity_datetime, outcome, notes, next_follow_up) VALUES (?,?,?,?,?,?,?)');
            $statement->execute([$leadId, (int) $user['id'], $input['activity_type'], $activityDateTime, trim((string) ($input['outcome'] ?? '')) ?: null, trim((string) ($input['notes'] ?? '')) ?: null, $nextFollowUp]);
            if ($nextFollowUp) {
                $update = $this->pdo->prepare('UPDATE crm_leads SET next_follow_up=?, updated_at=CURRENT_TIMESTAMP WHERE id=?');
                $update->execute([$nextFollowUp, $leadId]);
            } else {
                $update = $this->pdo->prepare('UPDATE crm_leads SET updated_at=CURRENT_TIMESTAMP WHERE id=?');
                $update->execute([$leadId]);
            }
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    /** @param array<string, mixed> $user */
    public function deleteLead(int $leadId, array $user): void
    {
        if ($user['role'] !== 'OWNER') {
            http_response_code(403);
            exit('You do not have permission to delete leads.');
        }

        $this->lead($leadId, $user);
        $statement = $this->pdo->prepare('DELETE FROM crm_leads WHERE id = ?');
        $statement->execute([$leadId]);
    }

    /** @param array<string, mixed> $user @return array<int, array<string, mixed>> */
    public function leadActivities(int $leadId, array $user): array
    {
        $this->lead($leadId, $user);
        $statement = $this->pdo->prepare('SELECT a.*, u.name user_name FROM crm_activities a JOIN crm_users u ON u.id=a.user_id WHERE a.lead_id=? ORDER BY a.activity_datetime DESC, a.id DESC');
        $statement->execute([$leadId]);

        return $statement->fetchAll();
    }

    /** @param array<string, mixed> $user @param array<string, mixed> $filters @return array<int, array<string, mixed>> */
    public function activities(array $user, array $filters): array
    {
        $where = ['1=1'];
        $params = [];
        if ($user['role'] !== 'OWNER') {
            $where[] = 'l.assigned_to = ?';
            $params[] = (int) $user['id'];
        }
        if (($filters['tab'] ?? 'today') === 'today') {
            $where[] = 'a.activity_datetime >= ? AND a.activity_datetime < ?';
            $params[] = date('Y-m-d 00:00:00');
            $params[] = date('Y-m-d 00:00:00', strtotime('+1 day'));
        }
        if (! empty($filters['activity_type'])) {
            $where[] = 'a.activity_type = ?';
            $params[] = $filters['activity_type'];
        }
        if ($user['role'] === 'OWNER' && ! empty($filters['user_id'])) {
            $where[] = 'a.user_id = ?';
            $params[] = (int) $filters['user_id'];
        }
        $statement = $this->pdo->prepare('SELECT a.*, l.name lead_name, u.name user_name FROM crm_activities a JOIN crm_leads l ON l.id=a.lead_id JOIN crm_users u ON u.id=a.user_id WHERE '.implode(' AND ', $where).' ORDER BY a.activity_datetime DESC, a.id DESC LIMIT 100');
        $statement->execute($params);

        return $statement->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function bdes(bool $activeOnly = false): array
    {
        $sql = "SELECT id, name, email, role, is_active FROM crm_users WHERE role='BDE'".($activeOnly ? ' AND is_active=1' : '').' ORDER BY name';

        return $this->pdo->query($sql)->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function teamSummary(): array
    {
        $today = date('Y-m-d 00:00:00');
        $tomorrow = date('Y-m-d 00:00:00', strtotime('+1 day'));
        $statement = $this->pdo->prepare("SELECT u.id,u.name,u.email,u.is_active,
            SUM(CASE WHEN l.stage NOT IN ('WON','LOST') THEN 1 ELSE 0 END) active_leads,
            SUM(CASE WHEN l.stage='WON' THEN 1 ELSE 0 END) won_leads,
            SUM(CASE WHEN l.next_follow_up>=? AND l.next_follow_up<? AND l.stage NOT IN ('WON','LOST') THEN 1 ELSE 0 END) follow_ups,
            (SELECT COUNT(*) FROM crm_activities a WHERE a.user_id=u.id AND a.activity_type='CALL' AND a.activity_datetime>=? AND a.activity_datetime<?) calls_today,
            (SELECT COUNT(*) FROM crm_activities a WHERE a.user_id=u.id AND a.activity_type='MEETING' AND a.activity_datetime>=? AND a.activity_datetime<?) meetings_today
            FROM crm_users u LEFT JOIN crm_leads l ON l.assigned_to=u.id WHERE u.role='BDE' GROUP BY u.id,u.name,u.email,u.is_active ORDER BY u.name");
        $statement->execute([$today, $tomorrow, $today, $tomorrow, $today, $tomorrow]);

        return $statement->fetchAll();
    }

    /** @param array<string, mixed> $input */
    public function createBde(array $input): void
    {
        $statement = $this->pdo->prepare("INSERT INTO crm_users (name,email,password,role,is_active) VALUES (?,?,?,'BDE',1)");
        $statement->execute([trim((string) $input['name']), mb_strtolower(trim((string) $input['email'])), password_hash((string) $input['password'], PASSWORD_DEFAULT)]);
    }

    public function toggleBde(int $id): void
    {
        $statement = $this->pdo->prepare("UPDATE crm_users SET is_active=CASE WHEN is_active=1 THEN 0 ELSE 1 END, updated_at=CURRENT_TIMESTAMP WHERE id=? AND role='BDE'");
        $statement->execute([$id]);
    }

    public function emailExists(string $email): bool
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM crm_users WHERE LOWER(email)=LOWER(?)');
        $statement->execute([trim($email)]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function isActiveBde(int $id): bool
    {
        $statement = $this->pdo->prepare("SELECT COUNT(*) FROM crm_users WHERE id=? AND role='BDE' AND is_active=1");
        $statement->execute([$id]);

        return (int) $statement->fetchColumn() > 0;
    }

    /** @param array<string, mixed> $user @return array{string,array<int,int>} */
    private function leadScope(array $user, string $alias): array
    {
        return $user['role'] === 'OWNER' ? ['1=1', []] : ["{$alias}.assigned_to = ?", [(int) $user['id']]];
    }

    private function normalizeDateTime(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        $timestamp = strtotime((string) $value);

        return $timestamp ? date('Y-m-d H:i:s', $timestamp) : null;
    }
}
