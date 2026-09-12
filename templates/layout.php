<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="A simple, focused sales CRM for leads, activities and follow-ups.">
    <title><?= e($title) ?> · Simple Sales CRM</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= e(url('assets/app.css')) ?>?v=20260912-ui3">
</head>
<body>
<?php if (! $user): ?>
    <header class="crm-public-header"><a href="/" class="portfolio-back"><i class="bi bi-arrow-left"></i> Back to Portfolio Page</a><span><strong>Sales CRM</strong><small>Revenue workspace</small></span></header>
    <?php require $contentTemplate; ?>
<?php else: ?>
    <?php $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH); ?>
    <div class="offcanvas-lg offcanvas-start crm-sidebar" tabindex="-1" id="crmSidebar" aria-labelledby="crmSidebarLabel">
        <div class="offcanvas-header d-lg-none"><h2 class="offcanvas-title fs-6" id="crmSidebarLabel">Simple Sales CRM</h2><button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#crmSidebar" aria-label="Close"></button></div>
        <div class="offcanvas-body d-flex flex-column p-0 h-100">
            <a href="/" class="portfolio-back sidebar-portfolio-back"><i class="bi bi-arrow-left"></i> Back to Portfolio Page</a>
            <nav class="nav flex-column crm-nav" aria-label="CRM navigation">
                <small>WORKSPACE</small>
                <a class="nav-link <?= str_contains((string) $currentPath, '/dashboard') ? 'active' : '' ?>" href="<?= e(url('dashboard')) ?>"><i class="bi bi-grid-1x2"></i> Dashboard</a>
                <a class="nav-link <?= str_contains((string) $currentPath, '/leads') ? 'active' : '' ?>" href="<?= e(url('leads')) ?>"><i class="bi bi-people"></i> Leads</a>
                <a class="nav-link <?= str_contains((string) $currentPath, '/activities') ? 'active' : '' ?>" href="<?= e(url('activities')) ?>"><i class="bi bi-activity"></i> Activities</a>
                <?php if ($user['role'] === 'OWNER'): ?><a class="nav-link <?= str_contains((string) $currentPath, '/team') ? 'active' : '' ?>" href="<?= e(url('team')) ?>"><i class="bi bi-person-badge"></i> Team</a><?php endif; ?>
            </nav>
            <div class="crm-user mt-auto"><div class="crm-avatar"><?= e(implode('', array_map(fn ($part) => mb_substr($part, 0, 1), array_slice(explode(' ', $user['name']), 0, 2)))) ?></div><div class="min-w-0"><strong><?= e($user['name']) ?></strong><small><?= e($user['role'] === 'OWNER' ? 'Owner' : 'BDE') ?></small></div><form method="post" action="<?= e(url('logout')) ?>" class="ms-auto"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="btn btn-link text-secondary p-1" aria-label="Logout"><i class="bi bi-box-arrow-right"></i></button></form></div>
        </div>
    </div>
    <div class="crm-main">
        <header class="crm-header"><button class="btn btn-light d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#crmSidebar" aria-controls="crmSidebar" aria-label="Open navigation"><i class="bi bi-list"></i></button><div><small>SALES WORKSPACE</small><strong><?= e($title) ?></strong></div><?php if (! str_contains((string) $currentPath, '/leads')): ?><a class="btn btn-primary btn-sm" href="<?= e(url('leads/create')) ?>"><i class="bi bi-plus-lg"></i> Create lead</a><?php endif; ?></header>
        <main class="crm-content">
            <?php if (! empty($_SESSION['flash'])): ?><div class="alert alert-<?= e($_SESSION['flash']['type'] === 'success' ? 'success' : 'danger') ?> alert-dismissible fade show" role="alert"><?= e($_SESSION['flash']['message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>
            <?php require $contentTemplate; ?>
        </main>
    </div>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
<script src="<?= e(url('assets/app.js')) ?>?v=20260912-ui3"></script>
</body>
</html>
