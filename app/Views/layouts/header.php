<?php
$navigationPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
if (!isset($activeSection)) {
    $activeSection = preg_match('~/(dashboard|projects|crm|sales|reports|templates|settings|profile)(?:/|$)~', $navigationPath, $navigationMatch)
        ? $navigationMatch[1]
        : 'dashboard';
}
$sections = ['dashboard' => 'Dashboard', 'projects' => 'Proyek', 'crm' => 'CRM', 'sales' => 'Aktivitas', 'reports' => 'Laporan','templates' => 'Template', 'settings' => 'Pengaturan'];
$isTeamMember = ($_SESSION['role'] ?? '') === 'user';
$sections['team'] = 'Tim & penugasan';
$sections['inbox'] = 'Kotak masuk';
if ($isTeamMember) $sections = ['team' => 'Prospek saya','inbox'=>'Kotak masuk'];
$pageSectionLabel = $sections[$activeSection] ?? ($activeSection === 'profile' ? 'Profil' : 'Dashboard');
$reminderTotal = $reminderTotal ?? ($isTeamMember ? 0 : (int)((new \App\Models\SalesModel())->reminderCounts()['total'] ?? 0));
$appBrand = APP_NAME;
$accountName = trim($_SESSION['user_name'] ?? 'Pengguna');
preg_match('/^\X/u', $accountName, $initialMatch);
$accountInitial = $initialMatch[0] ?? 'P';
$accountInitial = function_exists('mb_strtoupper') ? mb_strtoupper($accountInitial, 'UTF-8') : strtoupper($accountInitial);
$navIcons = [
    'inbox' => 'M3 5h18v14H3z M3 6l9 7 9-7',
    'team' => 'M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2 M9 11a4 4 0 100-8 4 4 0 000 8 M22 21v-2a4 4 0 00-3-3.87',
    'dashboard' => 'M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z',
    'projects' => 'M3 7h6l2-3h4l2 3h4v13H3z M3 10h18',
    'crm' => 'M4 4h16v4H4z M4 11h10v4H4z M4 18h6v3H4z',
    'templates' => 'M4 3h16v14H9l-5 4V3z M8 7h8 M8 11h6',
    'sales' => 'M4 5h16v16H4z M8 3v4 M16 3v4 M4 10h16 M8 15l2 2 4-4',
    'reports' => 'M4 20h16 M6 16v-5 M11 16V7 M16 16v-8 M20 16V4',
    'settings' => 'M4 7h16 M4 17h16 M8 4v6 M16 14v6'
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#056b71">
    <title><?= e($pageTitle ?? $appBrand) ?></title>
    <link rel="stylesheet" href="<?= base_url('assets/css/tailwind.css?v=b2b-1') ?>">
    <script nonce="<?= e(csp_nonce()) ?>">
        try {
            if (localStorage.getItem('gmap-sidebar-collapsed') === '1') {
                document.documentElement.classList.add('sidebar-collapsed');
            }
        } catch (error) { /* The toggle still works when storage is unavailable. */ }
    </script>
    <link rel="stylesheet" href="<?= base_url('assets/css/custom.css?v=funnel-27') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/panel.css?v=20261026-closing') ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/theme.css?v=20261021-status-1')) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/panel-ui.css?v=5')) ?>">
    <script src="<?= e(base_url('assets/js/csp-actions.js?v=1')) ?>" defer></script>
    <script src="<?= e(base_url('assets/js/account.js?v=1')) ?>" defer></script>
    <script src="<?= e(base_url('assets/js/extras.js?v=2')) ?>" defer></script>
<?php if(in_array($activeSection,['inbox','crm'],true)): ?><link rel="stylesheet" href="<?= e(base_url('assets/css/inbox.css?v=1.3.0')) ?>"><?php endif ?>
</head>
<body class="app-shell">
    <a href="#main-content" class="skip-link">Langsung ke konten</a>
    <button id="sidebarBackdrop" class="sidebar-backdrop" type="button" aria-label="Tutup menu navigasi" tabindex="-1" hidden></button>
    <aside id="app-sidebar" class="app-sidebar" aria-label="Navigasi utama">
        <button id="sidebarClose" class="sidebar-close" type="button" aria-label="Tutup menu navigasi"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg></button>
        <a class="app-brand" href="<?= base_url('dashboard') ?>" aria-label="<?= e($appBrand) ?> - Dashboard" title="<?= e($appBrand) ?>">
            <span class="workspace-brand-expanded">
                <?php require __DIR__.'/brand-mark.php'; ?>
                <span><?= e($appBrand) ?></span>
            </span>
            <span class="workspace-brand-compact" aria-hidden="true"><?php require __DIR__.'/brand-mark.php'; ?></span>
        </a>
        <p class="nav-caption">WORKSPACE</p>
        <nav class="workspace-nav">
            <?php foreach ($sections as $route => $label): ?>
                <a href="<?= base_url($route) ?>" aria-label="<?= e($label) ?>" title="<?= e($label) ?>" class="nav-item <?= $activeSection === $route ? 'is-active' : '' ?>" <?= $activeSection === $route ? 'aria-current="page"' : '' ?>>
                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"><path d="<?= $navIcons[$route] ?>"/></svg>
                    <span><?= $label ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-user">
            <span class="user-avatar"><?= e($accountInitial) ?></span>
            <div class="sidebar-user-info"><?= e($_SESSION['user_name'] ?? 'Pengguna') ?><small><?= $isTeamMember ? 'Anggota tim' : 'Admin' ?></small></div>
            <form method="POST" action="<?= e(base_url('logout')) ?>">
                <?= csrf_field() ?>
                <button class="logout-button" type="submit" aria-label="Keluar dari akun" title="Keluar dari akun"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 4H4v16h5M14 8l4 4-4 4m-6-4h10"/></svg></button>
            </form>
        </div>
    </aside>
    <header class="app-topbar">
        <?php if (!$isTeamMember): ?><a class="reminder-link" href="<?= e(base_url('sales')) ?>" aria-label="Aktivitas" title="Pengingat hari ini dan terlambat" data-reminder-api="<?= e(base_url('reminders/counts')) ?>"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M18 8a6 6 0 00-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg><span data-reminder-count><?= $reminderTotal ?></span></a><?php endif ?>
        <div class="topbar-leading">
            <button id="sidebarToggle" class="sidebar-toggle" type="button" aria-controls="app-sidebar" aria-expanded="true" aria-label="Minimalkan sidebar" title="Minimalkan sidebar">
                <svg class="sidebar-desktop-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16"/><path class="sidebar-toggle-arrow" d="m16 9-3 3 3 3"/></svg>
                <svg class="sidebar-mobile-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <div class="topbar-title"><small class="mobile-product"><?= e($appBrand) ?></small><span>Workspace</span><span aria-hidden="true">/</span><strong><?= e($pageSectionLabel) ?></strong></div>
        </div>
        <details class="account-dropdown" id="accountDropdown">
            <summary class="account-avatar" aria-label="Menu akun <?= e($accountName) ?>" title="Menu akun" aria-expanded="false" aria-controls="accountMenu"><?= e($accountInitial) ?></summary>
            <div class="account-menu" id="accountMenu">
                <div class="account-menu-heading"><strong><?= e($accountName) ?></strong><small><?= e($_SESSION['user_email'] ?? '') ?></small></div>
                <a href="<?= e(base_url('profile')) ?>" <?= $activeSection === 'profile' ? 'aria-current="page"' : '' ?>><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0116 0v2"/></svg>Profil</a>            </div>
        </details>
    </header>
    <!-- Global Toast / Flash Messages -->
    <div class="app-flashes">
        <?php foreach (get_flashes() as $flash): ?>
            <?php
                $bgClass = 'bg-blue-50 text-blue-800 border-blue-200';
                $iconColor = 'text-blue-500';
                if ($flash['type'] === 'success') {
                    $bgClass = 'bg-emerald-50 text-emerald-800 border-emerald-200';
                    $iconColor = 'text-emerald-500';
                } elseif ($flash['type'] === 'error') {
                    $bgClass = 'bg-rose-50 text-rose-800 border-rose-200';
                    $iconColor = 'text-rose-500';
                } elseif ($flash['type'] === 'warning') {
                    $bgClass = 'bg-amber-50 text-amber-800 border-amber-200';
                    $iconColor = 'text-amber-500';
                }
            ?>
            <div role="<?= $flash['type'] === 'error' ? 'alert' : 'status' ?>" class="flex items-center justify-between p-4 mb-3 rounded-lg border <?= $bgClass ?> shadow-sm animate-fade-in">
                <div class="flex items-center space-x-3">
                    <svg class="w-5 h-5 <?= $iconColor ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="text-sm font-medium"><?= e($flash['message']) ?></span>
                </div>
                <button type="button" aria-label="Tutup pemberitahuan" data-dismiss-parent class="text-gray-400 hover:text-gray-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Main App Content Container -->
    <main id="main-content" class="app-content" tabindex="-1">
