<?php

declare(strict_types=1);

session_start();
date_default_timezone_set('Asia/Kolkata');

require __DIR__.'/../src/Auth.php';
require __DIR__.'/../src/Crm.php';

$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec("CREATE TABLE crm_users (id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT NOT NULL,email TEXT NOT NULL UNIQUE,password TEXT NOT NULL,role TEXT NOT NULL,is_active INTEGER NOT NULL DEFAULT 1,created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE crm_leads (id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT NOT NULL,company_name TEXT,phone TEXT NOT NULL,email TEXT,city TEXT,lead_source TEXT,assigned_to INTEGER,stage TEXT NOT NULL,estimated_value REAL,next_follow_up TEXT,notes TEXT,lost_reason TEXT,created_by INTEGER NOT NULL,created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE crm_activities (id INTEGER PRIMARY KEY AUTOINCREMENT,lead_id INTEGER NOT NULL,user_id INTEGER NOT NULL,activity_type TEXT NOT NULL,activity_datetime TEXT NOT NULL,outcome TEXT,notes TEXT,next_follow_up TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP);");

$insertUser = $pdo->prepare('INSERT INTO crm_users (name,email,password,role,is_active) VALUES (?,?,?,?,?)');
$insertUser->execute(['Owner', 'owner@example.test', password_hash('Password123!', PASSWORD_DEFAULT), 'OWNER', 1]);
$ownerId = (int) $pdo->lastInsertId();
$insertUser->execute(['BDE One', 'one@example.test', password_hash('Password123!', PASSWORD_DEFAULT), 'BDE', 1]);
$bdeOneId = (int) $pdo->lastInsertId();
$insertUser->execute(['BDE Two', 'two@example.test', password_hash('Password123!', PASSWORD_DEFAULT), 'BDE', 1]);
$bdeTwoId = (int) $pdo->lastInsertId();
$insertUser->execute(['Inactive BDE', 'inactive@example.test', password_hash('Password123!', PASSWORD_DEFAULT), 'BDE', 0]);

$auth = new Auth($pdo);
$crm = new Crm($pdo, $auth);
$owner = ['id' => $ownerId, 'name' => 'Owner', 'role' => 'OWNER', 'is_active' => 1];
$bdeOne = ['id' => $bdeOneId, 'name' => 'BDE One', 'role' => 'BDE', 'is_active' => 1];
$bdeTwo = ['id' => $bdeTwoId, 'name' => 'BDE Two', 'role' => 'BDE', 'is_active' => 1];
$assertions = 0;

$assert = function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (! $condition) {
        throw new RuntimeException('Failed: '.$message);
    }
};

$ownerLeadId = $crm->saveLead([
    'name' => 'Acme Buyer', 'company_name' => 'Acme', 'phone' => '9999999999', 'email' => 'buyer@acme.test',
    'city' => 'Delhi', 'lead_source' => 'WEBSITE', 'assigned_to' => $bdeOneId, 'stage' => 'NEW',
    'estimated_value' => '125000', 'next_follow_up' => date('Y-m-d H:i:s', strtotime('+1 day')), 'notes' => 'Qualified inbound lead.',
], $owner);
$assert($ownerLeadId > 0, 'owner creates a lead');
$assert((int) $crm->lead($ownerLeadId, $owner)['assigned_to'] === $bdeOneId, 'owner assigns a lead');

$bdeLeadId = $crm->saveLead([
    'name' => 'Self-created lead', 'company_name' => '', 'phone' => '8888888888', 'email' => '', 'city' => 'Pune',
    'lead_source' => 'COLD_CALL', 'assigned_to' => $bdeTwoId, 'stage' => 'CONTACTED', 'estimated_value' => '',
    'next_follow_up' => '', 'notes' => '',
], $bdeOne);
$assert((int) $crm->lead($bdeLeadId, $bdeOne)['assigned_to'] === $bdeOneId, 'BDE-created lead is forced to the logged-in BDE');
$assert($crm->leads($bdeTwo, [])['total'] === 0, 'BDE cannot list another BDE’s leads');
$assert($crm->leads($bdeOne, [])['total'] === 2, 'BDE lists only assigned leads');

$nextFollowUp = date('Y-m-d H:i:s', strtotime('+3 days'));
$crm->addActivity($ownerLeadId, ['activity_type' => 'CALL', 'activity_datetime' => date('Y-m-d H:i:s'), 'outcome' => 'Interested', 'notes' => 'Send proposal.', 'next_follow_up' => $nextFollowUp], $bdeOne);
$assert(count($crm->leadActivities($ownerLeadId, $bdeOne)) === 1, 'activity is stored on an accessible lead');
$assert($crm->lead($ownerLeadId, $bdeOne)['next_follow_up'] === $nextFollowUp, 'activity updates the next follow-up');

$crm->createBde(['name' => 'New BDE', 'email' => ' NEW@EXAMPLE.TEST ', 'password' => 'StrongPass123!']);
$newBde = $pdo->query("SELECT * FROM crm_users WHERE email='new@example.test'")->fetch();
$assert((bool) $newBde, 'owner creates a normalized BDE account');
$assert(password_verify('StrongPass123!', $newBde['password']), 'BDE password is securely hashed');
$assert(! $auth->attempt('inactive@example.test', 'Password123!'), 'inactive BDE cannot sign in');
$assert($auth->attempt('OWNER@EXAMPLE.TEST', 'Password123!'), 'email sign-in is case insensitive');

$dashboard = $crm->dashboard($owner);
$assert($dashboard['metrics']['total_leads'] === 2, 'dashboard lead count is accurate');
$assert(array_keys($dashboard['pipeline']) === Crm::STAGES, 'pipeline includes every fixed stage');

$crm->deleteLead($bdeLeadId, $owner);
$assert($crm->leads($owner, [])['total'] === 1, 'owner deletes a lead');
$assert((int) $pdo->query('SELECT COUNT(*) FROM crm_activities')->fetchColumn() === 1, 'deleting an unrelated lead preserves activity history');

echo "Sales CRM tests passed: {$assertions} assertions.\n";
