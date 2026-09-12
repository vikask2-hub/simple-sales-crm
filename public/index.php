<?php

declare(strict_types=1);

require __DIR__.'/../src/bootstrap.php';

$path = '/'.trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
$basePath = '/'.trim((string) parse_url($baseUrl, PHP_URL_PATH), '/');
$path = str_starts_with($path, $basePath) ? substr($path, strlen($basePath)) ?: '/' : $path;
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if (PHP_SAPI === 'cli-server' && $path !== '/' && is_file(__DIR__.$path)) {
    header('Content-Type: '.(str_ends_with($path, '.css') ? 'text/css; charset=UTF-8' : 'application/javascript; charset=UTF-8'));
    readfile(__DIR__.$path);
    exit;
}

if ($method === 'POST') {
    verify_csrf();
}

if ($path === '/' && $method === 'GET') {
    if ($auth->user()) {
        redirect('dashboard');
    }
    View::render('login', ['title' => 'Sign in', 'user' => null, 'demoMode' => (bool) ($config['demo_mode'] ?? false)]);
    exit;
}

if ($path === '/login' && $method === 'POST') {
    $attempts = $_SESSION['login_attempts'] ?? ['count' => 0, 'started_at' => time()];
    if (time() - $attempts['started_at'] > 60) {
        $attempts = ['count' => 0, 'started_at' => time()];
    }
    if ($attempts['count'] >= 8) {
        remember_form($_POST, ['email' => 'Too many sign-in attempts. Try again in one minute.'], '');
    }
    if (! $auth->attempt((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''))) {
        $attempts['count']++;
        $_SESSION['login_attempts'] = $attempts;
        remember_form($_POST, ['email' => 'The email or password is incorrect.'], '');
    }
    redirect('dashboard');
}

if ($path === '/demo' && $method === 'POST' && ($config['demo_mode'] ?? false)) {
    $role = in_array($_POST['role'] ?? '', ['OWNER', 'BDE'], true) ? $_POST['role'] : 'BDE';
    $email = $role === 'OWNER' ? 'owner@crm.demo' : 'rahul@crm.demo';
    if ($auth->attempt($email, 'Demo@123')) {
        redirect('dashboard');
    }
    remember_form([], ['email' => 'Demo account is unavailable.'], '');
}

$user = $auth->requireUser();

if ($path === '/logout' && $method === 'POST') {
    $auth->logout();
    session_regenerate_id(true);
    flash('success', 'You have been signed out.');
    redirect('');
}

if ($path === '/dashboard' && $method === 'GET') {
    View::render('dashboard', ['title' => 'Dashboard', 'user' => $user, ...$crm->dashboard($user)]);
    exit;
}

if ($path === '/leads' && $method === 'GET') {
    View::render('leads', [
        'title' => 'Leads', 'user' => $user, 'result' => $crm->leads($user, $_GET),
        'bdes' => $crm->bdes(true), 'stages' => Crm::STAGES, 'sources' => Crm::SOURCES,
    ]);
    exit;
}

if ($path === '/leads/create' && $method === 'GET') {
    View::render('lead-form', ['title' => 'Create lead', 'user' => $user, 'lead' => null, 'bdes' => $crm->bdes(true), 'stages' => Crm::STAGES, 'sources' => Crm::SOURCES]);
    exit;
}

if ($path === '/leads' && $method === 'POST') {
    $errors = validateLead($_POST, $user);
    if ($errors) {
        remember_form($_POST, $errors, 'leads/create');
    }
    try {
        $id = $crm->saveLead($_POST, $user);
    } catch (InvalidArgumentException $exception) {
        remember_form($_POST, ['assigned_to' => $exception->getMessage()], 'leads/create');
    }
    flash('success', 'Lead created successfully.');
    redirect('leads/'.$id);
}

if (preg_match('#^/leads/(\d+)$#', $path, $matches) && $method === 'GET') {
    $lead = $crm->lead((int) $matches[1], $user);
    View::render('lead-detail', ['title' => $lead['name'], 'user' => $user, 'lead' => $lead, 'activities' => $crm->leadActivities((int) $lead['id'], $user), 'activityTypes' => Crm::ACTIVITY_TYPES, 'stages' => Crm::STAGES]);
    exit;
}

if (preg_match('#^/leads/(\d+)/edit$#', $path, $matches) && $method === 'GET') {
    $lead = $crm->lead((int) $matches[1], $user);
    View::render('lead-form', ['title' => 'Edit lead', 'user' => $user, 'lead' => $lead, 'bdes' => $crm->bdes(true), 'stages' => Crm::STAGES, 'sources' => Crm::SOURCES]);
    exit;
}

if (preg_match('#^/leads/(\d+)/edit$#', $path, $matches) && $method === 'POST') {
    $leadId = (int) $matches[1];
    $errors = validateLead($_POST, $user);
    if ($errors) {
        remember_form($_POST, $errors, 'leads/'.$leadId.'/edit');
    }
    try {
        $crm->saveLead($_POST, $user, $leadId);
    } catch (InvalidArgumentException $exception) {
        remember_form($_POST, ['assigned_to' => $exception->getMessage()], 'leads/'.$leadId.'/edit');
    }
    flash('success', 'Lead updated successfully.');
    redirect('leads/'.$leadId);
}

if (preg_match('#^/leads/(\d+)/activity$#', $path, $matches) && $method === 'POST') {
    $leadId = (int) $matches[1];
    $errors = [];
    if (! in_array($_POST['activity_type'] ?? '', Crm::ACTIVITY_TYPES, true)) {
        $errors['activity_type'] = 'Choose a valid activity type.';
    }
    if (empty($_POST['activity_datetime']) || strtotime((string) $_POST['activity_datetime']) === false) {
        $errors['activity_datetime'] = 'Choose a valid activity date and time.';
    }
    if ($errors) {
        remember_form($_POST, $errors, 'leads/'.$leadId);
    }
    $crm->addActivity($leadId, $_POST, $user);
    flash('success', 'Activity added successfully.');
    redirect('leads/'.$leadId);
}

if (preg_match('#^/leads/(\d+)/delete$#', $path, $matches) && $method === 'POST') {
    requireOwner($user);
    $crm->deleteLead((int) $matches[1], $user);
    flash('success', 'Lead deleted successfully.');
    redirect('leads');
}

if ($path === '/activities' && $method === 'GET') {
    View::render('activities', ['title' => 'Activities', 'user' => $user, 'activities' => $crm->activities($user, $_GET), 'activityTypes' => Crm::ACTIVITY_TYPES, 'bdes' => $crm->bdes()]);
    exit;
}

if ($path === '/team' && $method === 'GET') {
    requireOwner($user);
    View::render('team', ['title' => 'Team', 'user' => $user, 'team' => $crm->teamSummary()]);
    exit;
}

if ($path === '/team' && $method === 'POST') {
    requireOwner($user);
    $errors = validateBde($_POST, $crm);
    if ($errors) {
        remember_form($_POST, $errors, 'team');
    }
    $crm->createBde($_POST);
    flash('success', 'BDE created successfully.');
    redirect('team');
}

if (preg_match('#^/team/(\d+)/toggle$#', $path, $matches) && $method === 'POST') {
    requireOwner($user);
    $crm->toggleBde((int) $matches[1]);
    flash('success', 'BDE access updated.');
    redirect('team');
}

http_response_code(404);
View::render('error', ['title' => 'Page not found', 'user' => $user, 'code' => 404, 'message' => 'The page you requested does not exist.']);

/** @param array<string, mixed> $input @param array<string, mixed> $user @return array<string, string> */
function validateLead(array $input, array $user): array
{
    $errors = [];
    if (mb_strlen(trim((string) ($input['name'] ?? ''))) < 2) {
        $errors['name'] = 'Lead name is required.';
    }
    if (trim((string) ($input['phone'] ?? '')) === '') {
        $errors['phone'] = 'Phone number is required.';
    }
    if (! empty($input['email']) && ! filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    }
    if (! in_array($input['stage'] ?? '', Crm::STAGES, true)) {
        $errors['stage'] = 'Choose a valid stage.';
    }
    if (! empty($input['lead_source']) && ! in_array($input['lead_source'], Crm::SOURCES, true)) {
        $errors['lead_source'] = 'Choose a valid lead source.';
    }
    if (($input['estimated_value'] ?? '') !== '' && (! is_numeric($input['estimated_value']) || (float) $input['estimated_value'] < 0)) {
        $errors['estimated_value'] = 'Estimated value must be zero or greater.';
    }
    if ($user['role'] === 'BDE' && ! empty($input['assigned_to']) && (int) $input['assigned_to'] !== (int) $user['id']) {
        $errors['assigned_to'] = 'BDEs can only create leads for themselves.';
    }

    return $errors;
}

/** @param array<string, mixed> $input @return array<string, string> */
function validateBde(array $input, Crm $crm): array
{
    $errors = [];
    if (mb_strlen(trim((string) ($input['name'] ?? ''))) < 2) {
        $errors['name'] = 'Enter the BDE name.';
    }
    if (! filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    } elseif ($crm->emailExists((string) $input['email'])) {
        $errors['email'] = 'That email is already in use.';
    }
    if (mb_strlen((string) ($input['password'] ?? '')) < 8 || ($input['password'] ?? '') !== ($input['password_confirmation'] ?? '')) {
        $errors['password'] = 'Use at least 8 characters and confirm the password.';
    }

    return $errors;
}

/** @param array<string, mixed> $user */
function requireOwner(array $user): void
{
    if ($user['role'] !== 'OWNER') {
        http_response_code(403);
        View::render('error', [
            'title' => 'Access denied',
            'user' => $user,
            'code' => 403,
            'message' => 'This page is available to the CRM owner only.',
        ]);
        exit;
    }
}
