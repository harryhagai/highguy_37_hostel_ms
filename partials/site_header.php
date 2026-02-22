<?php
$navPrefix = isset($navPrefix) ? $navPrefix : '';
$activeNav = isset($activeNav) ? $activeNav : '';

$homeHref = $navPrefix === '' ? '#home' : $navPrefix . '#home';
$aboutHref = $navPrefix === '' ? '#about' : $navPrefix . '#about';
$hostelsHref = $navPrefix === '' ? '#hostels' : $navPrefix . '#hostels';
$contactHref = $navPrefix === '' ? '#contact' : $navPrefix . '#contact';

$resolveAuthBase = static function (string $prefix): string {
    $value = trim($prefix);
    if ($value === '') {
        return '';
    }

    if (substr($value, -1) === '/') {
        return $value;
    }

    if (preg_match('/\.php(?:[#?].*)?$/i', $value)) {
        $slashPos = strrpos($value, '/');
        return $slashPos === false ? '' : substr($value, 0, $slashPos + 1);
    }

    return rtrim($value, '/') . '/';
};

$authBase = $resolveAuthBase($navPrefix);
$loginHref = $authBase . 'auth/login.php';
$registerHref = $authBase . 'auth/register.php';
?>
<nav class="navbar navbar-expand-lg fixed-top site-header">
    <div class="container">
        <a class="navbar-brand header-brand" href="<?= htmlspecialchars($homeHref) ?>">
            <span class="header-brand-icon rounded-circle">
                <img src="assets/images/logo.png" alt="HostelPro Logo" class="header-brand-logo rounded-circle">
            </span>
            <span class="header-brand-text">
                <strong>Hostel<span class="brand-pro">Pro</span></strong>
                <small>Student Finder</small>
            </span>
        </a>
        <button
            class="navbar-toggler header-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#navbarNav"
            aria-controls="navbarNav"
            aria-expanded="false"
            aria-label="Toggle navigation"
        >
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <div class="header-collapse-inner w-100">
                <ul class="navbar-nav header-nav mx-lg-auto my-2 my-lg-0">
                    <li class="nav-item">
                        <a class="nav-link<?= $activeNav === 'home' ? ' is-active' : '' ?>" href="<?= htmlspecialchars($homeHref) ?>">
                            <i class="bi bi-house-door header-link-icon" aria-hidden="true"></i>
                            <span>Home</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link<?= $activeNav === 'about' ? ' is-active' : '' ?>" href="<?= htmlspecialchars($aboutHref) ?>">
                            <i class="bi bi-info-circle header-link-icon" aria-hidden="true"></i>
                            <span>About</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link<?= $activeNav === 'hostels' ? ' is-active' : '' ?>" href="<?= htmlspecialchars($hostelsHref) ?>">
                            <i class="bi bi-buildings header-link-icon" aria-hidden="true"></i>
                            <span>Hostels</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link<?= $activeNav === 'contact' ? ' is-active' : '' ?>" href="<?= htmlspecialchars($contactHref) ?>">
                            <i class="bi bi-envelope header-link-icon" aria-hidden="true"></i>
                            <span>Contact</span>
                        </a>
                    </li>
                </ul>

                <div class="header-actions d-flex flex-column flex-lg-row gap-2 mt-2 mt-lg-0">
                    <a class="btn btn-header-outline" href="<?= htmlspecialchars($loginHref) ?>">
                        <i class="bi bi-box-arrow-in-right header-btn-icon" aria-hidden="true"></i>
                        <span>Login</span>
                    </a>
                    <a class="btn btn-header-solid" href="<?= htmlspecialchars($registerHref) ?>">
                        <i class="bi bi-person-plus header-btn-icon" aria-hidden="true"></i>
                        <span>Create Account</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</nav>
