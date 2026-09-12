<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__.'/../src/bootstrap.php';

$schema = file_get_contents(__DIR__.'/schema.sql');
if ($schema === false) {
    throw new RuntimeException('Unable to read schema.sql.');
}

foreach (array_filter(array_map('trim', explode(';', $schema))) as $statement) {
    $database->pdo()->exec($statement);
}

$users = [
    ['Demo Owner', 'owner@crm.demo', 'OWNER'],
    ['Rahul Sharma', 'rahul@crm.demo', 'BDE'],
    ['Priya Singh', 'priya@crm.demo', 'BDE'],
    ['Arjun Mehta', 'arjun@crm.demo', 'BDE'],
];
$userStatement = $database->pdo()->prepare('INSERT INTO crm_users (name,email,password,role,is_active) VALUES (?,?,?,?,1) ON DUPLICATE KEY UPDATE name=VALUES(name), role=VALUES(role), is_active=1');
foreach ($users as [$name, $email, $role]) {
    $userStatement->execute([$name, $email, password_hash('Demo@123', PASSWORD_DEFAULT), $role]);
}

if ((int) $database->pdo()->query('SELECT COUNT(*) FROM crm_leads')->fetchColumn() === 0) {
    $ids = [];
    foreach ($database->pdo()->query('SELECT id,email FROM crm_users')->fetchAll() as $user) {
        $ids[$user['email']] = (int) $user['id'];
    }
    $names = ['Aarav Kapoor','Meera Joshi','Vikram Rao','Ishita Shah','Kabir Malhotra','Naina Desai','Rohan Bhat','Sara Khan','Dev Patel','Ananya Bose','Arjun Nair','Diya Verma','Karan Gill','Maya Iyer','Neil Sethi','Riya Das','Sameer Jain','Tara Roy','Veer Arora','Zoya Menon'];
    $stages = ['NEW','CONTACTED','QUALIFIED','PROPOSAL','NEGOTIATION','WON','LOST'];
    $cities = ['Delhi','Mumbai','Bengaluru','Pune','Chennai','Hyderabad'];
    $sources = ['REFERRAL','WEBSITE','WHATSAPP','SOCIAL_MEDIA','COLD_CALL','WALK_IN','OTHER'];
    $bdes = [$ids['rahul@crm.demo'], $ids['priya@crm.demo'], $ids['arjun@crm.demo']];
    $leadStatement = $database->pdo()->prepare('INSERT INTO crm_leads (name,company_name,phone,email,city,lead_source,assigned_to,stage,estimated_value,next_follow_up,notes,lost_reason,created_by,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    for ($index = 0; $index < 20; $index++) {
        $stage = $stages[$index % count($stages)];
        $followOffset = ($index % 7) - 3;
        $followUp = in_array($stage, ['WON','LOST'], true) ? null : date('Y-m-d H:i:s', strtotime(($followOffset >= 0 ? '+' : '').$followOffset.' days 11:00'));
        $leadStatement->execute([$names[$index], 'Company '.chr(65 + $index), '+91 98'.str_pad((string) (10000000 + $index), 8, '0', STR_PAD_LEFT), strtolower(str_replace(' ', '.', $names[$index])).'@example.test', $cities[$index % count($cities)], $sources[$index % count($sources)], $bdes[$index % 3], $stage, 50000 + ($index * 17500), $followUp, 'Demo opportunity with a clear next action.', $stage === 'LOST' ? 'Timing was not right.' : null, $ids['owner@crm.demo'], date('Y-m-d H:i:s', strtotime('-'.(20 - $index).' days')), date('Y-m-d H:i:s', strtotime('-'.($index % 5).' days'))]);
    }
    $leadIds = $database->pdo()->query('SELECT id,assigned_to FROM crm_leads ORDER BY id')->fetchAll();
    $activityStatement = $database->pdo()->prepare('INSERT INTO crm_activities (lead_id,user_id,activity_type,activity_datetime,outcome,notes,next_follow_up) VALUES (?,?,?,?,?,?,?)');
    $types = ['CALL','MEETING','WHATSAPP','EMAIL','FOLLOW_UP','NOTE','OTHER'];
    for ($index = 0; $index < 36; $index++) {
        $lead = $leadIds[$index % count($leadIds)];
        $activityStatement->execute([(int) $lead['id'], (int) $lead['assigned_to'], $types[$index % count($types)], date('Y-m-d H:i:s', strtotime('-'.($index % 12).' days +'.($index % 8).' hours')), ['Connected with prospect','Proposal shared','Follow-up agreed','Requirements captured'][$index % 4], 'A concise demo interaction note.', $index % 4 === 0 ? date('Y-m-d H:i:s', strtotime('+'.(($index % 5) + 1).' days 10:30')) : null]);
    }
}

echo "Sales CRM schema and demo data are ready.\n";
