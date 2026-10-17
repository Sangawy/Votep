<?php
/**
 * Shared Layout Include
 * 
 * Variables expected before including:
 * - $conn          : active DB connection (already fetched settings if needed)
 * - $page_title    : string — page title shown in <title> and topbar
 * - $active_nav    : string — one of: dashboard, books, borrows, reports, settings, users
 * - $settings      : array  — ['system_name' => '...', 'logo' => '...']  (optional, fetched here if not provided)
 */

// Define Base Path to avoid 404 errors in subdirectories
$base_path = '/pirxdry/';

if (!isset($settings)) {
    $defaults = [

        'system_name' => 'کتێبخانەی پێشمەرگە',
        'system_subtitle' => 'وەزارەتی پێشمەرگە',
        'sidebar_color' => '#5a0202',
        'logo'        => 'assets/logo.ico',
        'description' => '',
        'owner_name'  => ''
    ];
    
    if (isset($conn) && $conn !== null) {
        $_layout_res = $conn->query("SELECT * FROM settings WHERE id = 1");
        if ($_layout_res && $_layout_row = $_layout_res->fetch_assoc()) {
            $settings = array_merge($defaults, $_layout_row);
        } else {
            $settings = $defaults;
        }
        if ($_layout_res) $_layout_res->free();
    } else {
        $settings = $defaults;
    }
}
if (!isset($page_title)) $page_title = $settings['system_name'];
if (!isset($active_nav)) $active_nav = '';

$username = $_SESSION['username'] ?? 'بەکارهێنەر';

if (!isset($sys_module)) $sys_module = 'library';

$current_module_info = [
    'title' => $settings['system_name'],
    'description' => $settings['description']
];

if (isset($conn) && $conn !== null) {
    $_layout_stmt = $conn->prepare("SELECT section_title, section_description FROM system_sections WHERE section_key = ?");
    if ($_layout_stmt) {
        $_layout_stmt->bind_param("s", $sys_module);
        $_layout_stmt->execute();
        $_layout_res = $_layout_stmt->get_result();
        if ($_layout_row = $_layout_res->fetch_assoc()) {
            $current_module_info['title'] = $_layout_row['section_title'];
            $current_module_info['description'] = $_layout_row['section_description'];
        }
        $_layout_stmt->close();
    }
}

if ($sys_module === 'docs') {
    $nav_items = [
        'docs_dashboard' => ['label' => 'داشبۆردی نوسینگە و نوسراوەکان', 'href' => 'docs_dashboard.php', 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>'],
        'docs'           => ['label' => 'سەرجەم نوسراوەکان', 'href' => 'docs.php', 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>'],
        'docs_create'    => ['label' => 'دروستکردنی نوسراو', 'href' => 'docs_create.php', 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>'],
        'docs_reports'   => ['label' => 'ڕاپۆرتەکان', 'href' => 'docs_reports.php', 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg>'],
        'docs_settings'  => ['label' => 'سێتینگ', 'href' => 'doc_types_manage.php', 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1V15a2 2 0 0 1-2-2 2 2 0 0 1 2-2v-.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2v.09a1.65 1.65 0 0 0-1.51 1z"/></svg>'],
    ];
} else if ($sys_module === 'archive') {
    $nav_items = [
        'archive_dashboard' => ['label' => 'داشبۆردی ئەرشیف', 'href' => 'archive_dashboard.php', 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>'],
        'archive_list'      => ['label' => 'لیستی چالاکییەکان', 'href' => 'archive_list.php', 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>'],
        'archive_new'       => ['label' => 'تۆمارکردنی نوێ', 'href' => 'archive_new.php', 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>'],
        'archive_recycle'   => ['label' => 'ڕیسایکڵ بین', 'href' => 'archive_recycle_bin.php', 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><polyline points="23 20 23 14 17 14"/><path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 0 1 3.51 15"/></svg>'],
        'archive_settings'  => ['label' => 'سێتینگ', 'href' => 'doc_types_manage.php', 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1V15a2 2 0 0 1-2-2 2 2 0 0 1 2-2v-.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2v.09a1.65 1.65 0 0 0-1.51 1z"/></svg>'],
    ];
} else if ($sys_module === 'nusinga') {
    $nav_items = [
        'members_list'      => ['label' => 'لیستی ئەندامان', 'href' => 'members_list.php', 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>'],
        'members_new'       => ['label' => 'تۆمارکردنی ئەندام', 'href' => 'members_new.php', 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>'],
        'settings'          => ['label' => 'سێتینگ', 'href' => 'settings.php?tab=positions', 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1V15a2 2 0 0 1-2-2 2 2 0 0 1 2-2v-.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2v.09a1.65 1.65 0 0 0-1.51 1z"/></svg>'],
    ];
} else if ($sys_module === 'lineage') {
    $nav_items = [
        'lineage_dashboard' => ['label' => 'داشبۆردی نەسەب', 'href' => 'lineage_dashboard.php', 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/></svg>'],
        'lineage_explorer'  => ['label' => 'ناسینەوەی ڕەچەڵەک', 'href' => 'lineage_explorer.php', 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 19l7-7-7-7m-8 14l7-7-7-7"/></svg>'],
        'kinship_search'    => ['label' => 'ناسینەوەی کەسی', 'href' => 'kinship_search.php', 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>'],
        'lineage_tree'      => ['label' => 'داری خێزانی', 'href' => 'lineage_tree.php', 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M12 18V8"/><path d="M16 12l-4-4-4 4"/></svg>'],
        'families_manage'   => ['label' => 'بنەماڵەکان', 'href' => 'families_manage.php', 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>'],
        'member_requests_confirm' => ['label' => 'پەسەندکردنی داواکارییەکان', 'href' => 'member_requests_confirm.php', 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>'],
        'lineage_reports'   => ['label' => 'ڕاپۆرت و چاپکردن', 'href' => 'lineage_reports.php', 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>'],
        'lineage_settings'  => ['label' => 'سێتینگ', 'href' => 'lineage_settings.php', 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1V15a2 2 0 0 1-2-2 2 2 0 0 1 2-2v-.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2v.09a1.65 1.65 0 0 0-1.51 1z"/></svg>'],
    ];
} else if ($sys_module === 'settings') {
    $nav_items = [
        'general'   => ['label' => 'گشتی',             'href' => 'settings.php?tab=general',  'icon' => '⚙️'],
        'sections'  => ['label' => 'کارتەکانی پۆرتاڵ', 'href' => 'settings.php?tab=sections', 'icon' => '🧩'],
        'database'  => ['label' => 'داتابەیس',         'href' => 'settings.php?tab=database', 'icon' => '🗄️'],
        'backup'    => ['label' => 'باکئەپ',           'href' => 'settings.php?tab=backup',   'icon' => '💾'],
        'positions' => ['label' => 'پۆستەکان',         'href' => 'settings.php?tab=positions','icon' => '🎖️'],
        'audit'     => ['label' => 'تۆماری چالاکییەکان', 'href' => 'settings.php?tab=audit',    'icon' => '📜'],
        'update'    => ['label' => 'نوێکردنەوە',       'href' => 'settings.php?tab=update',   'icon' => '🚀'],
    ];
} else if ($sys_module === 'elections') {
    $nav_items = [
        'elections_dashboard' => ['label' => 'داشبۆردی هەڵبژاردن', 'href' => 'elections_dashboard.php', 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>'],
        'elections_manage'    => ['label' => 'کۆنگرەکان', 'href' => 'elections_manage.php', 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"/><path d="M3 7v1a3 3 0 0 0 6 0V7m6 0v1a3 3 0 1 0 6 0V7M3 7l9-5 9 5M4 10h16v11H4z"/></svg>'],
        'elections_candidates' => ['label' => 'بەربژێرەکان', 'href' => 'elections_candidates.php', 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>'],
        'elections_results'    => ['label' => 'ئەنجامەکان', 'href' => 'elections_results.php', 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 20V10M12 20V4M6 20v-6"/></svg>'],
    ];
} else {
    $nav_items = [
        'dashboard' => ['label' => 'داشبۆرد',       'href' => 'dashboard.php',  'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>'],
        'books'     => ['label' => 'کتێبەکان',       'href' => 'books.php',      'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>'],
        'borrows'   => ['label' => 'خواستنەکان',     'href' => 'borrows.php',    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><polyline points="17 11 19 13 23 9"/></svg>'],
        'publications' => ['label' => 'بڵاوکراوەکان', 'href' => 'publications.php', 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/><path d="M12 7v10"/><path d="M8 12h8"/></svg>'],
        'reports'   => ['label' => 'ڕاپۆرتەکان',    'href' => 'reports.php',    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>'],
        'lib_settings' => ['label' => 'سێتینگ', 'href' => 'library_settings.php', 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1V15a2 2 0 0 1-2-2 2 2 0 0 1 2-2v-.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2v.09a1.65 1.65 0 0 0-1.51 1z"/></svg>'],
    ];
}
?>
<!DOCTYPE html>
<html lang="ckb" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="<?php echo $base_path; ?>assets/logo.ico" type="image/x-icon">
    <title><?php echo htmlspecialchars($page_title); ?> - <?php echo htmlspecialchars($settings['system_name']); ?></title>
    
    <!-- SweetAlert2 -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/limonte-sweetalert2/11.12.1/sweetalert2.all.min.js"></script>
    <style>
        .swal2-popup {
            font-family: 'Rabar022', sans-serif !important;
            border-radius: 15px !important;
        }
        .swal2-styled.swal2-confirm {
            background-color: #5a0202 !important;
            border-radius: 8px !important;
        }
        .swal2-styled.swal2-cancel {
            border-radius: 8px !important;
        }
    </style>
    <script>
        // Global configuration for SweetAlert2 with safety checks
        let Toast = {
            fire: (opts) => { 
                if (typeof Swal !== 'undefined') return Swal.fire(opts);
                console.log('Toast (Fallback):', opts.title);
            }
        };

        if (typeof Swal !== 'undefined') {
            Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });
        }

        const showConfirm = (title, text, icon = 'question') => {
            if (typeof Swal !== 'undefined') {
                return Swal.fire({
                    title: title,
                    text: text,
                    icon: icon,
                    showCancelButton: true,
                    confirmButtonText: 'بەڵێ، دڵنیام',
                    cancelButtonText: 'پاشگەزبوونەوە',
                    reverseButtons: true,
                    customClass: {
                        confirmButton: 'lib-btn lib-btn-success',
                        cancelButton: 'lib-btn lib-btn-danger'
                    }
                });
            } else {
                return Promise.resolve({ isConfirmed: confirm(title + "\n" + text) });
            }
        };

        const showAlert = (title, text, icon = 'info') => {
            if (typeof Swal !== 'undefined') {
                return Swal.fire({
                    title: title,
                    text: text,
                    icon: icon,
                    confirmButtonText: 'باشە'
                });
            } else {
                alert(title + "\n" + text);
                return Promise.resolve();
            }
        };
    </script>
    <style>
        @font-face {
            font-family: 'Rabar013';
            src: url('<?php echo $base_path; ?>assets/font/Rabar_013.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
        }
        @font-face {
            font-family: 'Rabar013';
            src: url('<?php echo $base_path; ?>assets/font/Rabar_013.ttf') format('truetype');
            font-weight: bold;
            font-style: normal;
        }
        @font-face {
            font-family: 'Rabar005';
            src: url('<?php echo $base_path; ?>assets/font/Rabar_005.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
        }
        @font-face {
            font-family: 'Rabar005';
            src: url('<?php echo $base_path; ?>assets/font/Rabar_005.ttf') format('truetype');
            font-weight: bold;
            font-style: normal;
        }
        @font-face {
            font-family: 'Rabar022';
            src: url('<?php echo $base_path; ?>assets/font/Rabar_022.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
        }

        /* =========================================
           SHARED LIBRARY MANAGEMENT SYSTEM STYLES
           ========================================= */
        :root {
            --maroon:        <?php echo $settings['sidebar_color'] ?? '#5a0202'; ?>;
            --maroon-hover:  #7a2929;
            --gold:          #C8A951;
            --gold-dark:     #a88b3d;
            --bg:            #f4f5f7;
            --surface:       #ffffff;
            --border:        #e0e0e0;
            --text:          #1a1a1a;
            --text-muted:    #6b6b6b;
            --success:       #2e7d32;
            --danger:        #d32f2f;
            --sidebar-width: 260px;
            --topbar-height: 64px;
            --shadow-sm: 0 2px 8px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 16px rgba(0,0,0,0.10);
            --shadow-lg: 0 8px 32px rgba(0,0,0,0.13);
            --radius:    10px;
            --radius-lg: 14px;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Rabar005', 'Rabar022', 'Segoe UI', Tahoma, Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            overflow-x: hidden;
        }

        p, span, td, li, label, .content-text, .text-muted, .desc, .lib-nav-item span {
            font-family: 'Rabar005', 'Rabar022', Tahoma, Arial, sans-serif;
        }

        input, textarea, select, button {
            font-family: 'Rabar005', 'Rabar022', sans-serif !important;
        }

        .lib-input {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid var(--border);
            border-radius: var(--radius);
            font-family: 'Rabar022', sans-serif;
            font-size: 0.95rem;
            outline: none;
            transition: border-color 0.2s;
        }
        .lib-input:focus { border-color: var(--maroon); }

        h1, h2, h3, h4, h5, h6,
        .title, .page-title, .card-title, .modal-title, .section-title,
        .lib-topbar-title h1, .lib-sidebar-logo .logo-text h2,
        .stat-title, .widget-title, .box-title, .header-title {
            font-family: 'Rabar013', serif !important;
        }

        /* ── LAYOUT ── */
        .lib-wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* ── SIDEBAR ── */
        .lib-sidebar {
            width: var(--sidebar-width);
            background: var(--maroon);
            position: fixed;
            top: 0; right: 0;
            height: 100vh;
            display: flex;
            flex-direction: column;
            z-index: 200;
            overflow-y: auto;
            box-shadow: -4px 0 24px rgba(0,0,0,0.2);
        }

        .lib-sidebar::-webkit-scrollbar { width: 4px; }
        .lib-sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 4px; }

        .lib-sidebar-logo {
            padding: 24px 20px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid rgba(255,255,255,0.12);
        }

        .lib-sidebar-logo .logo-img {
            width: 48px; height: 48px;
            border-radius: 50%;
            border: 2px solid var(--gold);
            overflow: hidden;
            flex-shrink: 0;
            background: var(--gold);
            display: flex; align-items: center; justify-content: center;
        }

        .lib-sidebar-logo .logo-img img {
            width: 100%; height: 100%;
            object-fit: cover;
        }

        .lib-sidebar-logo .logo-text h2 {
            font-size: 0.9rem;
            color: #fff;
            font-weight: 700;
            line-height: 1.3;
        }

        .lib-sidebar-logo .logo-text span {
            font-size: 0.72rem;
            color: rgba(255,255,255,0.6);
        }

        .lib-sidebar-nav {
            flex: 1;
            padding: 16px 12px;
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .lib-sidebar-section {
            font-size: 0.68rem;
            color: var(--gold);
            font-weight: 700;
            letter-spacing: 0.8px;
            padding: 14px 10px 6px;
            opacity: 0.85;
        }

        .lib-nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            border-radius: var(--radius);
            color: rgba(255,255,255,0.75);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.93rem;
            transition: all 0.18s ease;
        }

        .lib-nav-item svg {
            width: 20px; height: 20px;
            flex-shrink: 0;
            transition: transform 0.18s;
        }

        .lib-nav-item:hover {
            background: rgba(255,255,255,0.10);
            color: #fff;
        }

        .lib-nav-item:hover svg { transform: scale(1.1); }

        .lib-nav-item.active {
            background: var(--gold);
            color: var(--maroon);
            box-shadow: 0 4px 12px rgba(200,169,81,0.35);
        }

        .lib-nav-item.portal-link {
            margin-bottom: 10px;
            border: 1px dashed rgba(255,255,255,0.25);
            background: rgba(255,255,255,0.06);
            color: #fff;
            font-weight: 700;
        }

        .lib-sidebar-footer {
            padding: 12px;
            border-top: 1px solid rgba(255,255,255,0.12);
        }

        .lib-nav-item.logout {
            color: #ff8a80;
        }

        .lib-nav-item.logout:hover {
            background: rgba(255,80,80,0.15);
            color: #ff5252;
        }

        /* ── MAIN CONTENT ── */
        .lib-main {
            flex: 1;
            margin-right: var(--sidebar-width);
            min-width: 0;
            display: flex;
            flex-direction: column;
            width: calc(100% - var(--sidebar-width));
            max-width: calc(100% - var(--sidebar-width));
            overflow-x: hidden;
        }

        /* ── TOPBAR ── */
        .lib-topbar {
            height: var(--topbar-height);
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 28px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: var(--shadow-sm);
        }

        .lib-topbar-title h1 {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--maroon);
        }

        .lib-topbar-title p {
            font-size: 0.78rem;
            color: var(--text-muted);
            margin-top: 1px;
        }

        .lib-topbar-user {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .lib-topbar-user .user-label {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .lib-topbar-user .user-name {
            font-size: 0.92rem;
            font-weight: 700;
            color: var(--maroon);
        }

        .lib-topbar-user .avatar {
            width: 38px; height: 38px;
            background: rgba(90,2,2,0.1);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: var(--maroon);
        }

        .lib-topbar-user .avatar svg { width: 20px; height: 20px; }

        /* ── PAGE BODY ── */
        .lib-page {
            flex: 1;
            padding: 28px 30px;
            overflow-x: hidden;
            width: 100%;
        }

        /* ── SHARED CARD ── */
        .lib-card {
            background: var(--surface);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border);
            overflow: hidden; /* Ensure header radius matches */
            margin-bottom: 30px;
        }

        .card-header {
            padding: 25px 30px;
            border-bottom: 1px solid var(--border);
            background: #fff;
        }

        .card-header h3 {
            margin: 0;
            color: var(--maroon);
            font-size: 1.25rem;
        }

        /* ── TABLE ── */
        .lib-table-wrapper {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .lib-table-wrapper::-webkit-scrollbar { height: 5px; }
        .lib-table-wrapper::-webkit-scrollbar-track { background: #f0f0f0; }
        .lib-table-wrapper::-webkit-scrollbar-thumb { background: var(--maroon); border-radius: 4px; }

        .lib-table {
            width: 100%;
            border-collapse: collapse;
        }

        .lib-table th {
            background: var(--maroon);
            color: #fff;
            padding: 14px 15px;
            text-align: center;
            font-weight: 700;
            font-size: 0.9rem;
            white-space: nowrap;
        }
        
        .lib-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #f2f2f2;
            text-align: center;
            color: #444;
            font-size: 0.95rem;
            vertical-align: middle;
        }

        .lib-table tbody tr:last-child td { border-bottom: none; }
        .lib-table tbody tr:hover { background: #fffaf5; }

        /* ── BUTTONS ── */
        .lib-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 18px;
            border-radius: var(--radius);
            border: none;
            font-family: inherit;
            font-size: 0.88rem;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.18s ease;
        }

        .lib-btn svg { width: 16px; height: 16px; }

        .lib-btn-primary { background: var(--maroon); color: #fff; }
        .lib-btn-primary:hover { background: var(--maroon-hover); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(90,2,2,0.25); }

        .lib-btn-gold { background: var(--gold); color: var(--maroon); }
        .lib-btn-gold:hover { background: var(--gold-dark); color: #fff; }

        .lib-btn-success { background: rgba(46,125,50,0.1); color: var(--success); border: 1px solid rgba(46,125,50,0.2); }
        .lib-btn-success:hover { background: var(--success); color: #fff; }

        .lib-btn-warning { background: rgba(200,169,81,0.15); color: #8e7323; border: 1px solid rgba(200,169,81,0.3); }
        .lib-btn-warning:hover { background: var(--gold); color: var(--maroon); }

        .lib-btn-danger { background: rgba(211,47,47,0.1); color: var(--danger); border: 1px solid rgba(211,47,47,0.2); }
        .lib-btn-danger:hover { background: var(--danger); color: #fff; }

        /* ── ACTION BUTTONS IN TABLE ── */
        .lib-actions {
            display: flex;
            flex-direction: column;
            gap: 6px;
            align-items: stretch;
            min-width: 130px;
            padding: 4px 0;
        }

        .lib-actions .lib-btn {
            padding: 7px 10px;
            font-size: 0.82rem;
            justify-content: center;
        }

        /* ── BADGES ── */
        .lib-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.82rem;
            font-weight: 600;
            background: #f0f0f0;
            color: #555;
        }

        .lib-badge-maroon { background: rgba(90,2,2,0.08); color: var(--maroon); }
        .lib-badge-green  { background: #e8f5e9; color: var(--success); }
        .lib-badge-gold   { background: rgba(200,169,81,0.15); color: var(--gold-dark); }
        .lib-badge-red    { background: #ffebee; color: var(--danger); }

        /* ── TOAST ── */
        .lib-toast-area {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 99999;
            display: flex;
            flex-direction: column;
            gap: 10px;
            pointer-events: none;
            min-width: 300px;
        }

        .lib-toast {
            padding: 15px 24px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.95rem;
            text-align: center;
            box-shadow: var(--shadow-lg);
            animation: toastIn 0.3s ease;
            pointer-events: auto;
        }

        .lib-toast.success { background: var(--success); color: #fff; }
        .lib-toast.error   { background: var(--danger);  color: #fff; }

        @keyframes toastIn {
            from { opacity: 0; transform: translateY(-20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── MODAL ── */
        .lib-overlay {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            backdrop-filter: blur(2px);
        }

        .lib-overlay.show { display: block; }

        .lib-modal {
            position: fixed;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%) scale(0.95);
            background: var(--surface);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            z-index: 1001;
            display: flex;
            flex-direction: column;
            opacity: 0;
            pointer-events: none;
            transition: all 0.22s ease;
            max-height: 92vh;
            overflow: hidden;
        }

        .lib-modal.show {
            opacity: 1;
            pointer-events: auto;
            transform: translate(-50%, -50%) scale(1);
        }

        .lib-modal-header {
            background: var(--maroon);
            color: #fff;
            padding: 22px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .lib-modal-header h3 { font-size: 1.1rem; font-weight: 700; }

        .lib-modal-close {
            background: none;
            border: none;
            color: #fff;
            font-size: 1.4rem;
            cursor: pointer;
            padding: 2px 8px;
            border-radius: 6px;
            transition: background 0.15s;
        }

        .lib-modal-close:hover { background: rgba(255,255,255,0.15); }

        .lib-modal-body {
            padding: 24px;
            overflow-y: auto;
            flex: 1;
        }

        .lib-modal-footer {
            padding: 16px 24px;
            border-top: 1px solid var(--border);
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            background: var(--surface);
        }

        /* ── PAGINATION ── */
        .lib-pagination {
            display: flex;
            justify-content: center;
            gap: 6px;
            padding: 18px;
            flex-wrap: wrap;
        }

        .lib-page-btn {
            padding: 7px 14px;
            border: 2px solid var(--border);
            border-radius: 8px;
            background: var(--surface);
            font-weight: 600;
            color: #555;
            cursor: pointer;
            font-family: inherit;
            transition: all 0.15s;
        }

        .lib-page-btn:hover { border-color: var(--maroon); color: var(--maroon); }
        .lib-page-btn.active { background: var(--maroon); color: #fff; border-color: var(--maroon); }
        .lib-page-btn.disabled { opacity: 0.45; cursor: not-allowed; }

        /* ── FORM ELEMENTS (shared) ── */
        .lib-form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
        }

        .lib-field label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #444;
            font-size: 0.88rem;
        }

        .lib-field input,
        .lib-field select,
        .lib-field textarea {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid var(--border);
            border-radius: var(--radius);
            font-family: inherit;
            font-size: 0.95rem;
            color: var(--text);
            background: #fcfcfc;
            transition: border-color 0.18s;
        }

        .lib-field input:focus,
        .lib-field select:focus,
        .lib-field textarea:focus {
            outline: none;
            border-color: var(--maroon);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(90,2,2,0.08);
        }
    </style>
</head>
<body>

<div class="lib-toast-area" id="libToastArea"></div>

<div class="lib-wrapper">

    <!-- ── SIDEBAR ── -->
    <aside class="lib-sidebar">
        <div class="lib-sidebar-logo">
            <div class="logo-img">
                <img src="<?php echo $base_path . htmlspecialchars($settings['logo']); ?>" alt="Logo" onerror="this.style.display='none'">
            </div>
            <div class="logo-text">
                <h2><?php echo htmlspecialchars($settings['system_name']); ?></h2>
                <span><?php echo htmlspecialchars($settings['system_subtitle'] ?? 'وەزارەتی پێشمەرگە'); ?></span>
            </div>
        </div>

        <nav class="lib-sidebar-nav">
            <a href="<?php echo $base_path; ?>portal.php" class="lib-nav-item portal-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:20px;height:20px;"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                پۆرتاڵی سەرەکی
            </a>

            <?php if ($sys_module === 'archive'): ?>
                <div class="lib-sidebar-section">ئەرشیفی دەرەکی</div>
                <?php foreach ($nav_items as $key => $item): ?>
                    <a href="<?php echo $item['href']; ?>" class="lib-nav-item <?php echo $active_nav === $key ? 'active' : ''; ?>">
                        <?php echo $item['icon']; ?>
                        <span><?php echo $item['label']; ?></span>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="lib-sidebar-section"><?php echo htmlspecialchars($current_module_info['title'] ?? 'بەشی گشتی'); ?></div>
                <?php foreach ($nav_items as $key => $item): ?>
                    <a href="<?php echo $base_path . $item['href']; ?>" class="lib-nav-item <?php echo $active_nav === $key ? 'active' : ''; ?>">
                        <?php echo $item['icon']; ?>
                        <span><?php echo $item['label']; ?></span>
                    </a>
                <?php endforeach; ?>

                <?php endif; ?>



        </nav>

        <div class="lib-sidebar-footer">
            <a href="<?php echo $base_path; ?>logout.php" class="lib-nav-item logout">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:20px;height:20px;"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                <span>دەرچوون</span>
            </a>
        </div>
    </aside>

    <!-- ── MAIN ── -->
    <div class="lib-main">

        <!-- TOPBAR -->
        <header class="lib-topbar">
            <div class="lib-topbar-title">
                <h1><?php echo htmlspecialchars($current_module_info['title']); ?></h1>
                <p><?php echo htmlspecialchars($current_module_info['description']); ?></p>
            </div>

            <!-- Global Search -->
            <div class="global-search-container" style="position: relative; flex: 1; max-width: 450px; margin: 0 30px;">
                <div style="position: relative;">
                    <span style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); color: #888;">🔍</span>
                    <input type="text" id="globalSearchInput" placeholder="گەڕانی گشتی (ناو، فایل، فەرمان...)" 
                           style="width: 100%; padding: 12px 40px 12px 15px; border-radius: 30px; border: 2px solid #eee; font-family: 'Rabar022'; outline: none; transition: 0.3s; background: #fcfcfc;">
                </div>
                <div id="globalSearchResults" style="display:none; position: absolute; top: 55px; left: 0; right: 0; background: #fff; border: 1px solid var(--border); border-radius: 12px; box-shadow: var(--shadow-lg); z-index: 9999; max-height: 450px; overflow-y: auto;"></div>
            </div>

            <div class="lib-topbar-user">
                <div>
                    <div class="user-label">بەکارهێنەر:</div>
                    <div class="user-name"><?php echo htmlspecialchars($username); ?></div>
                </div>
                <div class="avatar">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </div>
            </div>
        </header>

        <!-- PAGE CONTENT starts here -->
        <div class="lib-page">
            
            <!-- Session Messages (Toasts) -->
            <?php if (isset($_SESSION['msg'])): ?>
                <div id="sessionToast" class="lib-toast <?php echo $_SESSION['msg_type'] ?? 'success'; ?>" 
                     style="position: fixed; top: 20px; left: 50%; transform: translateX(-50%); z-index: 10000; box-shadow: 0 10px 30px rgba(0,0,0,0.2); min-width: 300px; text-align: center;">
                    <?php 
                        echo $_SESSION['msg']; 
                        unset($_SESSION['msg']);
                        unset($_SESSION['msg_type']);
                    ?>
                </div>
                <script>
                    setTimeout(() => {
                        const toast = document.getElementById('sessionToast');
                        if (toast) {
                            toast.style.transition = 'opacity 0.5s ease';
                            toast.style.opacity = '0';
                            setTimeout(() => toast.remove(), 500);
                        }
                    }, 4000);
                </script>
            <?php endif; ?>
            
            <!-- Global Activity Notifications -->
            <!-- Global Activity Notifications -->
            <style>
                .global-notif-container {
                    position: fixed;
                    bottom: 25px;
                    left: 25px;
                    z-index: 1100;
                    display: flex;
                    flex-direction: column-reverse;
                    gap: 15px;
                    max-width: 350px;
                    pointer-events: none;
                }
                .activity-notif {
                    background: rgba(255, 255, 255, 0.95);
                    border-right: 5px solid var(--gold);
                    padding: 20px;
                    border-radius: 15px;
                    box-shadow: 0 15px 35px rgba(0,0,0,0.15);
                    animation: notif-slide-in 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
                    position: relative;
                    pointer-events: auto;
                    backdrop-filter: blur(10px);
                    border: 1px solid rgba(200, 169, 81, 0.2);
                    overflow: hidden;
                }
                .activity-notif.is-hidden {
                    display: none;
                }
                .activity-notif::before {
                    content: '';
                    position: absolute;
                    top: 0; right: 0;
                    width: 4px; height: 100%;
                    background: var(--gold);
                    box-shadow: 0 0 10px var(--gold);
                }
                @keyframes notif-slide-in {
                    from { transform: translateX(-120%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                .notif-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 10px;
                }
                .notif-hide-btn {
                    width: 28px;
                    height: 28px;
                    border: none;
                    border-radius: 50%;
                    background: #f5f0df;
                    color: var(--gold-dark);
                    cursor: pointer;
                    font-size: 1.2rem;
                    line-height: 1;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    transition: .2s;
                }
                .notif-hide-btn:hover {
                    background: var(--gold);
                    color: #fff;
                }
                .notif-label {
                    background: #fff4e5;
                    color: #d35400;
                    padding: 4px 10px;
                    border-radius: 20px;
                    font-size: 0.75rem;
                    font-weight: 800;
                    border: 1px solid #ffe0b2;
                }
                .notif-title {
                    font-size: 1rem;
                    font-weight: 800;
                    color: var(--maroon);
                    margin-bottom: 8px;
                    line-height: 1.4;
                }
                .notif-meta {
                    display: flex;
                    gap: 15px;
                    font-size: 0.8rem;
                    color: #666;
                    font-weight: 600;
                }
                .notif-action {
                    margin-top: 12px;
                    text-align: left;
                }
                .notif-link {
                    color: var(--gold-dark);
                    text-decoration: none;
                    font-weight: 800;
                    font-size: 0.85rem;
                    transition: all 0.2s;
                    display: inline-flex;
                    align-items: center;
                    gap: 5px;
                }
                .notif-link:hover {
                    color: var(--maroon);
                    gap: 8px;
                }
            </style>
            <div class="global-notif-container">
                <?php
                $db_notif = getDB();
                $today_val = date('Y-m-d');
                $target_val = date('Y-m-d', strtotime('+3 days'));
                
                $notif_res = $db_notif->query("SELECT * FROM activities WHERE is_deleted = 0 AND status = 0 AND activity_date BETWEEN '$today_val' AND '$target_val' ORDER BY activity_date ASC LIMIT 3");
                if ($notif_res && $notif_res->num_rows > 0) {
                    while($n = $notif_res->fetch_assoc()) {
                        $diff = (strtotime($n['activity_date']) - strtotime($today_val)) / 86400;
                        $days_left = ceil($diff);
                        
                        if ($days_left == 0) $label = "🔔 ئەمڕۆ ئەنجام دەدرێت!";
                        elseif ($days_left == 1) $label = "⏳ بەیانی ئەنجام دەدرێت";
                        else $label = "📅 $days_left ڕۆژی ماوە";
                        
                        echo '
                        <div class="activity-notif" data-notif-id="'.$n['id'].'">
                            <div class="notif-header">
                                <span class="notif-label">'.$label.'</span>
                                <button type="button" class="notif-hide-btn" onclick="hideActivityNotif(this)" title="شاردنەوە">&times;</button>
                            </div>
                            <div class="notif-title">'.htmlspecialchars($n['name']).'</div>
                            <div class="notif-meta">
                                <span>📍 '.htmlspecialchars($n['location']).'</span>
                                <span>📅 '.htmlspecialchars($n['activity_date']).'</span>
                            </div>
                            <div class="notif-action">
                                <a href="archive_list.php" class="notif-link">بینینی وردەکاری ←</a>
                            </div>
                        </div>';
                    }
                }
                ?>
            </div>
            <script>
                // Global Search Logic
                const searchInput = document.getElementById('globalSearchInput');
                const resultsBox = document.getElementById('globalSearchResults');

                let searchTimer;
                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimer);
                    const q = this.value.trim();
                    if (q.length < 2) {
                        resultsBox.style.display = 'none';
                        return;
                    }

                    searchTimer = setTimeout(() => {
                        fetch(`<?php echo $base_path; ?>members_search_api.php?q=${encodeURIComponent(q)}`)
                            .then(res => res.json())
                            .then(data => {
                                resultsBox.innerHTML = '';
                                if (data.length === 0) {
                                    resultsBox.innerHTML = '<div style="padding: 20px; text-align: center; color: #999;">هیچ ئەنجامێک نەدۆزرایەوە</div>';
                                } else {
                                    data.forEach(item => {
                                        const div = document.createElement('a');
                                        div.href = item.link;
                                        div.style.display = 'block';
                                        div.style.padding = '12px 20px';
                                        div.style.borderBottom = '1px solid #f5f5f5';
                                        div.style.textDecoration = 'none';
                                        div.style.transition = '0.2s';
                                        div.onmouseover = () => div.style.background = '#fff8f0';
                                        div.onmouseout = () => div.style.background = '#fff';

                                        const typeLabels = { 'staff': '👤 کارمەند', 'member': '👤 ئەندام', 'doc': '📄 بەڵگە', 'service': '📜 فەرمان' };
                                        const typeLabel = typeLabels[item.type] || '🔍 ئەنجام';

                                        const highlightedTitle = item.title.replace(new RegExp(q, 'gi'), match => `<mark style="background: #fff3cd; color: #856404; padding: 0 2px; border-radius: 4px;">${match}</mark>`);

                                        div.innerHTML = `
                                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                                <div style="font-weight: 700; color: var(--maroon);">${highlightedTitle}</div>
                                                <div style="font-size: 0.75rem; background: #eee; padding: 3px 8px; border-radius: 10px; color: #666;">${typeLabel}</div>
                                            </div>
                                            <div style="font-size: 0.8rem; color: #888; margin-top: 3px;">${item.sub}</div>
                                        `;
                                        resultsBox.appendChild(div);
                                    });
                                }
                                resultsBox.style.display = 'block';
                            });
                    }, 300);
                });

                document.addEventListener('click', function(e) {
                    if (!searchInput.contains(e.target) && !resultsBox.contains(e.target)) {
                        resultsBox.style.display = 'none';
                    }
                });

                searchInput.addEventListener('focus', function() {
                    if (this.value.trim().length >= 2 && resultsBox.innerHTML !== '') {
                        resultsBox.style.display = 'block';
                    }
                });
            </script>
            <script>
                const ACTIVITY_NOTIF_HIDE_MS = 60 * 60 * 1000;

                function hideActivityNotif(btn) {
                    const notif = btn.closest('.activity-notif');
                    if (!notif) return;

                    const key = 'activity_notif_hidden_until_' + notif.dataset.notifId;
                    const showAt = Date.now() + ACTIVITY_NOTIF_HIDE_MS;
                    localStorage.setItem(key, String(showAt));
                    notif.classList.add('is-hidden');

                    setTimeout(() => {
                        localStorage.removeItem(key);
                        notif.classList.remove('is-hidden');
                    }, ACTIVITY_NOTIF_HIDE_MS);
                }

                document.addEventListener('DOMContentLoaded', () => {
                    document.querySelectorAll('.activity-notif[data-notif-id]').forEach(notif => {
                        const key = 'activity_notif_hidden_until_' + notif.dataset.notifId;
                        const hiddenUntil = Number(localStorage.getItem(key) || 0);

                        if (hiddenUntil > Date.now()) {
                            notif.classList.add('is-hidden');
                            setTimeout(() => {
                                localStorage.removeItem(key);
                                notif.classList.remove('is-hidden');
                            }, hiddenUntil - Date.now());
                        } else {
                            localStorage.removeItem(key);
                        }
                    });
                });
            </script>
<?php
// NOTE: The page must close </div></div></div></body></html> at the end
// or include includes/layout_end.php
?>
