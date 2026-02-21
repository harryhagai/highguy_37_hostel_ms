<?php
require_once __DIR__ . '/../permission/role_permission.php';
rp_require_roles(['admin'], '../auth/login.php');

$state = require __DIR__ . '/../controllers/admin/dashboard_controller.php';
$total_users = $state['total_users'];
$total_hostels = $state['total_hostels'];
$rooms_available = $state['rooms_available'];
$full_rooms = $state['full_rooms'];
$pending_count = $state['pending_count'];
$confirmed_count = $state['confirmed_count'];
$cancelled_count = $state['cancelled_count'];
$months = $state['months'];
$applications = $state['applications'];
$approved = $state['approved'];
$rejected = $state['rejected'];
$trend_data_mode = $state['trend_data_mode'];
$total_rooms_count = $state['total_rooms_count'];
$occupancy_rate = $state['occupancy_rate'];
?>

<div class="dashboard-intro-card dashboard-card mb-3">
    <div class="dashboard-intro-left">
        <p class="dashboard-kicker mb-1">Admin Overview</p>
        <h4 class="mb-1">Hostel operations at a glance</h4>
        <p class="text-muted mb-0">Track occupancy, booking flow, and key admin actions from one place.</p>
    </div>
    <div class="dashboard-intro-right">
        <a href="admin_dashboard_layout.php?page=application_management" data-spa-page="application_management" data-no-spinner="true" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-clipboard-check me-1"></i>Review Applications
        </a>
    </div>
</div>

<div class="stats-grid mb-3">
    <article class="stat-card stat-card-users">
        <div class="stat-icon"><i class="bi bi-people"></i></div>
        <div>
            <p class="stat-label">Total Users</p>
            <h3 class="stat-value"><?= (int)$total_users ?></h3>
        </div>
    </article>

    <article class="stat-card stat-card-hostels">
        <div class="stat-icon"><i class="bi bi-building"></i></div>
        <div>
            <p class="stat-label">Total Hostels</p>
            <h3 class="stat-value"><?= (int)$total_hostels ?></h3>
        </div>
    </article>

    <article class="stat-card stat-card-available">
        <div class="stat-icon"><i class="bi bi-door-open"></i></div>
        <div>
            <p class="stat-label">Rooms Available</p>
            <h3 class="stat-value"><?= (int)$rooms_available ?></h3>
        </div>
    </article>

    <article class="stat-card stat-card-full">
        <div class="stat-icon"><i class="bi bi-door-closed"></i></div>
        <div>
            <p class="stat-label">Full Rooms</p>
            <h3 class="stat-value"><?= (int)$full_rooms ?></h3>
        </div>
    </article>

    <article class="stat-card stat-card-occupancy">
        <div class="stat-icon"><i class="bi bi-pie-chart"></i></div>
        <div>
            <p class="stat-label">Occupancy Rate</p>
            <h3 class="stat-value"><?= (int)$occupancy_rate ?>%</h3>
            <small class="text-muted"><?= (int)$full_rooms ?>/<?= (int)$total_rooms_count ?> rooms full</small>
        </div>
    </article>

    <article class="stat-card stat-card-pending">
        <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
        <div>
            <p class="stat-label">Pending Applications</p>
            <h3 class="stat-value"><?= (int)$pending_count ?></h3>
        </div>
    </article>
</div>

<div class="dashboard-analytics-grid">
    <div class="dashboard-card chart-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0"><i class="bi bi-graph-up-arrow me-2"></i>Application Trends (Last 6 Months)</h5>
            <span class="badge <?= $trend_data_mode === 'demo' ? 'text-bg-warning' : 'text-bg-light' ?>">
                <?= $trend_data_mode === 'demo' ? 'Demo Data' : 'Live' ?>
            </span>
        </div>
        <canvas
            id="adminDashboardChart"
            height="95"
            data-labels='<?= htmlspecialchars(json_encode($months), ENT_QUOTES, "UTF-8") ?>'
            data-applications='<?= htmlspecialchars(json_encode($applications), ENT_QUOTES, "UTF-8") ?>'
            data-approved='<?= htmlspecialchars(json_encode($approved), ENT_QUOTES, "UTF-8") ?>'
            data-rejected='<?= htmlspecialchars(json_encode($rejected), ENT_QUOTES, "UTF-8") ?>'>
        </canvas>
    </div>

    <div class="dashboard-card summary-card">
        <h6 class="summary-title mb-3"><i class="bi bi-clipboard-data me-2"></i>Booking Pipeline</h6>
        <div class="summary-row">
            <span>Pending</span>
            <strong class="text-warning"><?= (int)$pending_count ?></strong>
        </div>
        <div class="summary-row">
            <span>Confirmed</span>
            <strong class="text-success"><?= (int)$confirmed_count ?></strong>
        </div>
        <div class="summary-row mb-3">
            <span>Cancelled</span>
            <strong class="text-danger"><?= (int)$cancelled_count ?></strong>
        </div>
        <a href="admin_dashboard_layout.php?page=notice" data-spa-page="notice" data-no-spinner="true" class="btn btn-sm btn-outline-secondary w-100 mb-2">
            <i class="bi bi-megaphone me-1"></i>Manage Notices
        </a>
        <a href="admin_dashboard_layout.php?page=manage_beds" data-spa-page="manage_beds" data-no-spinner="true" class="btn btn-sm btn-outline-primary w-100">
            <i class="bi bi-grid-3x3-gap me-1"></i>Manage Beds
        </a>
    </div>
</div>
