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

$sixMonthApplications = array_sum(array_map('intval', $applications));
$sixMonthApproved = array_sum(array_map('intval', $approved));
$sixMonthRejected = array_sum(array_map('intval', $rejected));
$approvalRate = $sixMonthApplications > 0 ? (int)round(($sixMonthApproved / $sixMonthApplications) * 100) : 0;
$rejectionRate = $sixMonthApplications > 0 ? (int)round(($sixMonthRejected / $sixMonthApplications) * 100) : 0;
$pipelineTotal = max(1, (int)$pending_count + (int)$confirmed_count + (int)$cancelled_count);
$pendingShare = (int)round(((int)$pending_count / $pipelineTotal) * 100);
?>

<div class="admin-dashboard-page">
    <div class="dashboard-intro-card dashboard-card dashboard-hero mb-3">
        <div class="dashboard-intro-left">
            <p class="dashboard-kicker mb-1">Admin Overview</p>
            <h4 class="mb-1">Hostel operations at a glance</h4>
            <p class="text-muted mb-3">Track occupancy, booking flow, and key admin actions from one place.</p>
        </div>
        <div class="dashboard-intro-right dashboard-hero-actions">
            <a href="admin_dashboard_layout.php?page=application_management" data-spa-page="application_management" data-no-spinner="true" class="btn btn-sm btn-primary">
                <i class="bi bi-clipboard-check me-1"></i>Review Applications
            </a>
            <a href="admin_dashboard_layout.php?page=notice" data-spa-page="notice" data-no-spinner="true" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-megaphone me-1"></i>Open Notices
            </a>
        </div>
    </div>

    <div class="dashboard-highlight-row mb-3">
        <article class="dashboard-card highlight-card">
            <p class="highlight-label mb-1">6-Month Booking Throughput</p>
            <h3 class="highlight-value mb-2"><?= (int)$sixMonthApplications ?></h3>
            <p class="highlight-copy mb-0">Confirmed <strong><?= (int)$sixMonthApproved ?></strong> | Rejected <strong><?= (int)$sixMonthRejected ?></strong></p>
        </article>
        <article class="dashboard-card highlight-card">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <p class="highlight-label mb-0">Occupancy Pressure</p>
                <strong class="highlight-rate"><?= (int)$occupancy_rate ?>%</strong>
            </div>
            <div class="progress occupancy-progress mb-2" role="progressbar" aria-label="Occupancy rate" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int)$occupancy_rate ?>">
                <div class="progress-bar" style="width: <?= (int)$occupancy_rate ?>%;"></div>
            </div>
            <p class="highlight-copy mb-0"><?= (int)$full_rooms ?>/<?= (int)$total_rooms_count ?> rooms currently full</p>
        </article>
        <article class="dashboard-card highlight-card">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <p class="highlight-label mb-0">Pending Load</p>
                <strong class="highlight-rate"><?= (int)$pendingShare ?>%</strong>
            </div>
            <p class="highlight-copy mb-0"><?= (int)$pending_count ?> pending out of <?= (int)$pipelineTotal ?> total applications in the pipeline.</p>
        </article>
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
                <p class="stat-label">Approval Rate</p>
                <h3 class="stat-value"><?= (int)$approvalRate ?>%</h3>
                <small class="text-muted">Rejection <?= (int)$rejectionRate ?>%</small>
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
        <div class="dashboard-card chart-card chart-card-main">
            <div class="chart-card-head mb-3">
                <div>
                    <h5 class="mb-1"><i class="bi bi-graph-up-arrow me-2"></i>Application Trends (Last 6 Months)</h5>
                    <p class="chart-subtitle mb-0">Bar graph overview of total applications by month.</p>
                </div>
                <span class="badge <?= $trend_data_mode === 'demo' ? 'text-bg-warning' : 'text-bg-light' ?>">
                    <?= $trend_data_mode === 'demo' ? 'Demo Data' : 'Live Data' ?>
                </span>
            </div>
            <div class="chart-meta-pills mb-3">
                <span class="chart-meta-pill chart-meta-app"><i class="bi bi-bar-chart-line-fill"></i>Total <?= (int)$sixMonthApplications ?></span>
            </div>
            <canvas
                id="adminDashboardChart"
                height="104"
                data-labels='<?= htmlspecialchars(json_encode($months), ENT_QUOTES, 'UTF-8') ?>'
                data-applications='<?= htmlspecialchars(json_encode($applications), ENT_QUOTES, 'UTF-8') ?>'
                data-approved='<?= htmlspecialchars(json_encode($approved), ENT_QUOTES, 'UTF-8') ?>'
                data-rejected='<?= htmlspecialchars(json_encode($rejected), ENT_QUOTES, 'UTF-8') ?>'>
            </canvas>
        </div>

        <div class="dashboard-side-column">
            <div class="dashboard-card split-chart-card">
                <h6 class="summary-title mb-3"><i class="bi bi-pie-chart-fill me-2"></i>Booking Status Distribution</h6>
                <canvas
                    id="adminBookingSplitChart"
                    height="220"
                    data-pending="<?= (int)$pending_count ?>"
                    data-confirmed="<?= (int)$confirmed_count ?>"
                    data-cancelled="<?= (int)$cancelled_count ?>">
                </canvas>
                <div class="split-chart-legend mt-3">
                    <div class="split-legend-item"><span class="dot dot-pending"></span>Pending <strong><?= (int)$pending_count ?></strong></div>
                    <div class="split-legend-item"><span class="dot dot-confirmed"></span>Confirmed <strong><?= (int)$confirmed_count ?></strong></div>
                    <div class="split-legend-item"><span class="dot dot-cancelled"></span>Cancelled <strong><?= (int)$cancelled_count ?></strong></div>
                </div>
            </div>
        </div>
    </div>
</div>
