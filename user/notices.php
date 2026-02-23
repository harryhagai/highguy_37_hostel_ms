<?php
require_once __DIR__ . '/../permission/role_permission.php';
rp_require_roles(['user'], '../auth/login.php');

$state = require __DIR__ . '/../controllers/user/notices_controller.php';
$message = $state['message'];
$notices = $state['notices'];
$stats = $state['stats'];
$filters = $state['filters'];
?>
<div class="container-fluid px-0 user-notices-page">
    <div class="users-quick-stats mb-3">
        <article class="users-mini-stat">
            <p class="users-mini-label mb-1">Total Notices</p>
            <h5 class="mb-0"><?= (int)$stats['total'] ?></h5>
        </article>
        <article class="users-mini-stat">
            <p class="users-mini-label mb-1">Today</p>
            <h5 class="mb-0"><?= (int)$stats['today'] ?></h5>
        </article>
        <article class="users-mini-stat">
            <p class="users-mini-label mb-1">Last 7 Days</p>
            <h5 class="mb-0"><?= (int)$stats['this_week'] ?></h5>
        </article>
    </div>

    <div class="dashboard-card mb-3">
        <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
            <div>
                <h4 class="mb-1"><i class="bi bi-megaphone me-2"></i>My Notices</h4>
                <p class="text-muted mb-0">All announcements relevant to your account.</p>
            </div>
            <a href="user_dashboard_layout.php?page=dashboard" data-spa-page="dashboard" data-no-spinner="true" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
            </a>
        </div>
    </div>

    <div class="dashboard-card mb-3">
        <form method="get" action="user_dashboard_layout.php" class="row g-2 align-items-end">
            <input type="hidden" name="page" value="notices">
            <div class="col-lg-8">
                <label class="form-label form-label-sm mb-1">Search Notice</label>
                <input
                    type="search"
                    name="q"
                    class="form-control form-control-sm"
                    value="<?= htmlspecialchars((string)$filters['q']) ?>"
                    placeholder="Search by title or content">
            </div>
            <div class="col-lg-4 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                    <i class="bi bi-search me-1"></i>Search
                </button>
                <a href="user_dashboard_layout.php?page=notices" data-spa-page="notices" data-no-spinner="true" class="btn btn-sm btn-outline-secondary">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?= htmlspecialchars((string)$message['type']) ?> mb-3">
            <?= htmlspecialchars((string)$message['text']) ?>
        </div>
    <?php endif; ?>

    <?php if (empty($notices)): ?>
        <div class="dashboard-card my-room-empty-state text-center">
            <div class="mb-2"><i class="bi bi-megaphone my-room-empty-icon"></i></div>
            <h5 class="mb-2">No notices matched your search</h5>
            <p class="text-muted mb-0">Try another keyword or clear your search.</p>
        </div>
    <?php else: ?>
        <div class="user-notice-list">
            <?php foreach ($notices as $notice): ?>
                <article class="user-notice-card">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2 flex-wrap">
                        <h5 class="mb-0"><?= htmlspecialchars((string)($notice['title'] !== '' ? $notice['title'] : 'Untitled Notice')) ?></h5>
                        <span class="badge text-bg-light"><?= htmlspecialchars((string)($notice['created_at_display'] ?? '-')) ?></span>
                    </div>
                    <p class="mb-0 text-muted"><?= nl2br(htmlspecialchars((string)($notice['content'] ?? ''))) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
