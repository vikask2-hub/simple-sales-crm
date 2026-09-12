<?php $activityIcons = ['CALL' => 'telephone', 'MEETING' => 'camera-video', 'WHATSAPP' => 'whatsapp', 'EMAIL' => 'envelope', 'FOLLOW_UP' => 'calendar2-check', 'NOTE' => 'journal-text', 'OTHER' => 'three-dots']; ?>
<div class="page-heading">
    <div>
        <a class="back-link" href="<?= e(url('leads')) ?>"><i class="bi bi-arrow-left"></i> All leads</a>
        <div class="d-flex align-items-center gap-2"><h1 class="mb-0"><?= e($lead['name']) ?></h1><span class="stage-badge stage-<?= e(strtolower($lead['stage'])) ?>"><?= e(ucfirst(strtolower($lead['stage']))) ?></span></div>
        <p><?= e($lead['company_name'] ?: 'Independent lead') ?> · <?= e($lead['city'] ?: 'City not added') ?></p>
    </div>
    <div class="d-flex gap-2"><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#activityModal"><i class="bi bi-plus-lg"></i> Log activity</button><a class="btn btn-light" href="<?= e(url('leads/'.$lead['id'].'/edit')) ?>"><i class="bi bi-pencil"></i> Edit</a><?php if ($user['role'] === 'OWNER'): ?><form method="post" action="<?= e(url('leads/'.$lead['id'].'/delete')) ?>" onsubmit="return confirm('Delete this lead and its activity history?')"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="btn btn-outline-danger" aria-label="Delete lead"><i class="bi bi-trash"></i></button></form><?php endif; ?></div>
</div>
<div class="row g-3">
    <div class="col-xl-4"><section class="panel lead-profile">
        <div class="profile-top"><div class="lead-avatar"><?= e(mb_substr($lead['name'], 0, 1)) ?></div><div><h2><?= e($lead['name']) ?></h2><p><?= e($lead['company_name'] ?: 'No company added') ?></p></div></div>
        <dl>
            <div><dt>Phone</dt><dd><a href="tel:<?= e($lead['phone']) ?>"><?= e($lead['phone']) ?></a></dd></div>
            <div><dt>Email</dt><dd><?= $lead['email'] ? '<a href="mailto:'.e($lead['email']).'">'.e($lead['email']).'</a>' : '—' ?></dd></div>
            <div><dt>Assigned BDE</dt><dd><?= e($lead['assigned_name'] ?: 'Unassigned') ?></dd></div>
            <div><dt>Lead source</dt><dd><?= e($lead['lead_source'] ? ucwords(strtolower(str_replace('_', ' ', $lead['lead_source']))) : '—') ?></dd></div>
            <div><dt>Estimated value</dt><dd><?= $lead['estimated_value'] !== null ? '₹'.number_format((float) $lead['estimated_value'], 2) : '—' ?></dd></div>
            <div><dt>Next follow-up</dt><dd><?= $lead['next_follow_up'] ? e(date('d M Y, g:i A', strtotime($lead['next_follow_up']))) : 'Not scheduled' ?></dd></div>
        </dl>
        <?php if ($lead['notes']): ?><div class="profile-notes"><small>NOTES</small><p><?= nl2br(e($lead['notes'])) ?></p></div><?php endif; ?>
    </section></div>
    <div class="col-xl-8"><section class="panel timeline-panel">
        <div class="panel-head"><div><span class="eyebrow">HISTORY</span><h2>Activity timeline</h2></div><button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#activityModal">Add update</button></div>
        <div class="quick-actions"><button data-activity="CALL"><i class="bi bi-telephone"></i> Call</button><button data-activity="MEETING"><i class="bi bi-camera-video"></i> Meeting</button><button data-activity="WHATSAPP"><i class="bi bi-whatsapp"></i> WhatsApp</button><button data-activity="EMAIL"><i class="bi bi-envelope"></i> Email</button><button data-activity="NOTE"><i class="bi bi-journal-text"></i> Note</button><button data-activity="FOLLOW_UP"><i class="bi bi-calendar-plus"></i> Follow-up</button></div>
        <div class="timeline">
            <?php foreach ($activities as $activity): ?><article><span class="timeline-icon"><i class="bi bi-<?= e($activityIcons[$activity['activity_type']] ?? 'activity') ?>"></i></span><div><div class="timeline-meta"><strong><?= e(ucwords(strtolower(str_replace('_', ' ', $activity['activity_type'])))) ?></strong><span><?= e(date('d M Y, g:i A', strtotime($activity['activity_datetime']))) ?></span></div><?php if ($activity['outcome']): ?><h3><?= e($activity['outcome']) ?></h3><?php endif; ?><?php if ($activity['notes']): ?><p><?= nl2br(e($activity['notes'])) ?></p><?php endif; ?><small>By <?= e($activity['user_name']) ?></small></div></article><?php endforeach; ?>
            <?php if (! $activities): ?><div class="empty-state"><i class="bi bi-activity"></i><p>No activity yet. Log the first interaction.</p></div><?php endif; ?>
        </div>
    </section></div>
</div>
<div class="modal fade" id="activityModal" tabindex="-1" aria-labelledby="activityModalLabel" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form method="post" action="<?= e(url('leads/'.$lead['id'].'/activity')) ?>" class="modal-content"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><div class="modal-header"><div><span class="eyebrow">SALES UPDATE</span><h2 class="modal-title fs-5" id="activityModalLabel">Log activity</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><div class="row g-3"><div class="col-md-6"><label class="form-label">Activity type *</label><select class="form-select" name="activity_type" required><?php foreach ($activityTypes as $type): ?><option value="<?= e($type) ?>"><?= e(ucwords(strtolower(str_replace('_', ' ', $type)))) ?></option><?php endforeach; ?></select></div><div class="col-md-6"><label class="form-label">Date & time *</label><input type="datetime-local" class="form-control" name="activity_datetime" value="<?= e(date('Y-m-d\TH:i')) ?>" required></div><div class="col-12"><label class="form-label">Outcome</label><input class="form-control" name="outcome" maxlength="255" placeholder="What happened?"></div><div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="3" maxlength="5000" placeholder="Add useful context"></textarea></div><div class="col-12"><label class="form-label">Next follow-up</label><input type="datetime-local" class="form-control" name="next_follow_up"></div></div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save activity</button></div></form></div></div>
