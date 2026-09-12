<div class="page-heading">
    <div><span class="eyebrow">OWNER CONTROL</span><h1>Sales team</h1><p>Create BDE access and understand individual workload.</p></div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createBdeModal"><i class="bi bi-person-plus"></i> Add BDE</button>
</div>
<section class="panel"><div class="table-responsive"><table class="table align-middle">
    <thead><tr><th>Team member</th><th>Active leads</th><th>Calls today</th><th>Meetings today</th><th>Follow-ups</th><th>Won</th><th>Status</th><th></th></tr></thead>
    <tbody><?php foreach ($team as $member): ?><tr>
        <td><div class="member-cell"><span><?= e(mb_substr($member['name'], 0, 1)) ?></span><div><strong><?= e($member['name']) ?></strong><small><?= e($member['email']) ?></small></div></div></td>
        <td><?= (int) $member['active_leads'] ?></td><td><?= (int) $member['calls_today'] ?></td><td><?= (int) $member['meetings_today'] ?></td><td><?= (int) $member['follow_ups'] ?></td><td><?= (int) $member['won_leads'] ?></td>
        <td><span class="badge <?= $member['is_active'] ? 'text-bg-success-subtle text-success-emphasis' : 'text-bg-secondary-subtle text-secondary-emphasis' ?>"><?= $member['is_active'] ? 'Active' : 'Inactive' ?></span></td>
        <td><form method="post" action="<?= e(url('team/'.$member['id'].'/toggle')) ?>"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="btn btn-sm btn-light"><?= $member['is_active'] ? 'Deactivate' : 'Activate' ?></button></form></td>
    </tr><?php endforeach; ?></tbody>
</table></div></section>

<div class="modal fade" id="createBdeModal" tabindex="-1" aria-labelledby="createBdeTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form method="post" action="<?= e(url('team')) ?>" class="modal-content">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <div class="modal-header"><div><span class="eyebrow">SALES TEAM</span><h2 class="modal-title fs-5" id="createBdeTitle">Add a BDE</h2><p class="text-secondary small mb-0">Create secure access for a sales executive.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body">
        <div class="mb-3"><label class="form-label">Full name *</label><input class="form-control <?= validation_error('name') ? 'is-invalid' : '' ?>" name="name" value="<?= e((string) old('name')) ?>" required><?php if ($error = validation_error('name')): ?><div class="invalid-feedback"><?= e($error) ?></div><?php endif; ?></div>
        <div class="mb-3"><label class="form-label">Work email *</label><input type="email" class="form-control <?= validation_error('email') ? 'is-invalid' : '' ?>" name="email" value="<?= e((string) old('email')) ?>" required><?php if ($error = validation_error('email')): ?><div class="invalid-feedback"><?= e($error) ?></div><?php endif; ?></div>
        <div class="row g-3"><div class="col-md-6"><label class="form-label">Password *</label><input type="password" class="form-control <?= validation_error('password') ? 'is-invalid' : '' ?>" name="password" minlength="8" required><?php if ($error = validation_error('password')): ?><div class="invalid-feedback"><?= e($error) ?></div><?php endif; ?></div><div class="col-md-6"><label class="form-label">Confirm password *</label><input type="password" class="form-control" name="password_confirmation" minlength="8" required></div></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Create BDE</button></div>
</form></div></div>
<?php if (! empty($_SESSION['errors'])): ?><script>document.addEventListener('DOMContentLoaded', () => bootstrap.Modal.getOrCreateInstance(document.getElementById('createBdeModal')).show());</script><?php endif; ?>
