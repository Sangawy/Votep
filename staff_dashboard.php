<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
$conn = getDB();

// ----------------------------------------------------
// 1. Fetch Real Statistics from pirxdry_db
// ----------------------------------------------------

$stats = [
    'total_members'       => 0,
    'official_members'    => 0,
    'alive_members'       => 0,
    'deceased_members'    => 0,
    'official_with_phone' => 0,
    'official_no_phone'   => 0,
    'with_photo'          => 0,
];

if ($conn !== null) {
    // Core Member Stats
    $res = $conn->query("SELECT 
        COUNT(*) as total_members,
        SUM(CASE WHEN is_official_member = 1 OR (member_code IS NOT NULL AND member_code != '') THEN 1 ELSE 0 END) as official_members,
        SUM(CASE WHEN is_alive = 1 THEN 1 ELSE 0 END) as alive_members,
        SUM(CASE WHEN is_alive = 0 THEN 1 ELSE 0 END) as deceased_members,
        SUM(CASE WHEN (is_official_member = 1 OR (member_code IS NOT NULL AND member_code != '')) AND (phone IS NOT NULL AND phone != '' AND phone != '-') THEN 1 ELSE 0 END) as official_with_phone,
        SUM(CASE WHEN (is_official_member = 1 OR (member_code IS NOT NULL AND member_code != '')) AND (phone IS NULL OR phone = '' OR phone = '-') THEN 1 ELSE 0 END) as official_no_phone,
        SUM(CASE WHEN profile_image IS NOT NULL AND profile_image != '' AND profile_image NOT LIKE '%user.png%' THEN 1 ELSE 0 END) as with_photo
    FROM members");
    if ($res && $row = $res->fetch_assoc()) {
        $stats = array_merge($stats, $row);
    }

    // Families count
    $res_fam = $conn->query("SELECT COUNT(*) FROM families");
    $families_count = ($res_fam) ? (int)$res_fam->fetch_row()[0] : 0;

    // Congress members count (Elections / Congresses)
    $res_cong = $conn->query("SELECT COUNT(*) FROM congress_members");
    $congress_members_count = ($res_cong) ? (int)$res_cong->fetch_row()[0] : 0;

    // Books count
    $res_books = $conn->query("SELECT COUNT(*) FROM books");
    $books_count = ($res_books) ? (int)$res_books->fetch_row()[0] : 0;

    // Activities count
    $res_act = $conn->query("SELECT COUNT(*) FROM public_activities");
    $activities_count = ($res_act) ? (int)$res_act->fetch_row()[0] : 0;

    // 2. Rank Breakdown for Chart
    $rank_res = $conn->query("SELECT member_rank, COUNT(*) as cnt 
        FROM members 
        WHERE member_rank IS NOT NULL AND member_rank != '' AND member_rank != '-' 
        GROUP BY member_rank 
        ORDER BY cnt DESC");
    $ranks_data = [];
    if ($rank_res) {
        while ($r = $rank_res->fetch_assoc()) {
            $ranks_data[] = [
                'name' => $r['member_rank'],
                'count' => (int)$r['cnt']
            ];
        }
    }

    // 3. Smart Notifications: Official members without phone numbers
    $missing_phone_members = [];
    $res_missing_phone = $conn->query("SELECT id, full_name, member_code, member_rank 
        FROM members 
        WHERE (is_official_member = 1 OR (member_code IS NOT NULL AND member_code != '')) 
          AND (phone IS NULL OR phone = '' OR phone = '-') 
        ORDER BY CAST(member_code AS UNSIGNED) ASC 
        LIMIT 6");
    if ($res_missing_phone) {
        while ($row = $res_missing_phone->fetch_assoc()) {
            $missing_phone_members[] = $row;
        }
    }

    // 4. Leadership & Board Highlights
    $leadership_members = [];
    $res_lead = $conn->query("SELECT id, full_name, member_code, member_rank, member_post, profile_image, phone 
        FROM members 
        WHERE member_rank IN ('سەرۆکی دەستەی کارگیری', 'دەستەی کارگێڕی (هەمیشەیی)', 'دەستەی کارگیڕی (یەدەگ)', 'ئەنجومەنی ڕاوێژکاری')
        ORDER BY CASE member_rank 
            WHEN 'سەرۆکی دەستەی کارگیری' THEN 1 
            WHEN 'دەستەی کارگێڕی (هەمیشەیی)' THEN 2 
            WHEN 'دەستەی کارگیڕی (یەدەگ)' THEN 3 
            ELSE 4 END ASC, CAST(member_code AS UNSIGNED) ASC 
        LIMIT 6");
    if ($res_lead) {
        while ($row = $res_lead->fetch_assoc()) {
            $leadership_members[] = $row;
        }
    }

    // 5. Recent Official Members Added / Updated
    $recent_members = [];
    $res_recent = $conn->query("SELECT m.id, m.full_name, m.member_code, m.member_rank, m.member_post, m.profile_image, m.phone, m.created_at, f.family_name 
        FROM members m 
        LEFT JOIN families f ON m.family_id = f.id 
        WHERE m.is_official_member = 1 OR (m.member_code IS NOT NULL AND m.member_code != '') 
        ORDER BY m.id DESC 
        LIMIT 6");
    if ($res_recent) {
        while ($row = $res_recent->fetch_assoc()) {
            $recent_members[] = $row;
        }
    }
}

// Layout Config
$page_title = 'داشبۆردی سەرەکی - مەڵبەندی ڕۆشنبیری و کۆمەڵایەتی';
$active_nav = 'staff_dashboard';
$sys_module = 'nusinga';
require_once 'includes/layout.php';
?>

<style>
    /* Stats & Dashboard Styling */
    .dashboard-hero-title {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        background: #fff;
        padding: 20px 25px;
        border-radius: 16px;
        border: 1px solid var(--border);
        box-shadow: var(--shadow-sm);
    }
    .dashboard-hero-title h2 {
        margin: 0;
        font-size: 1.5rem;
        color: var(--maroon, #1a5653);
        font-family: 'Rabar013', sans-serif;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .stats-row { 
        display: grid; 
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); 
        gap: 20px; 
        margin-bottom: 25px; 
    }
    .stat-card { 
        background: #fff; 
        padding: 22px 25px; 
        border-radius: 16px; 
        box-shadow: var(--shadow-sm); 
        border: 1px solid var(--border); 
        display: flex; 
        align-items: center; 
        gap: 20px; 
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        text-decoration: none;
        color: inherit;
        position: relative;
        overflow: hidden;
    }
    .stat-card::after {
        content: '';
        position: absolute;
        bottom: 0;
        right: 0;
        left: 0;
        height: 4px;
        background: transparent;
        transition: background 0.3s ease;
    }
    .stat-card:hover { 
        transform: translateY(-5px); 
        box-shadow: var(--shadow-lg); 
        border-color: var(--gold, #C8A951);
    }
    .stat-card:hover::after {
        background: var(--gold, #C8A951);
    }
    .stat-icon { 
        width: 65px; 
        height: 65px; 
        border-radius: 18px; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        font-size: 2rem; 
        flex-shrink: 0;
        box-shadow: 0 4px 12px rgba(0,0,0,0.06);
    }
    .stat-icon.official { background: #e0f2f1; color: #00796b; }
    .stat-icon.families { background: #ede7f6; color: #512da8; }
    .stat-icon.congress { background: #fff3e0; color: #e65100; }
    .stat-icon.lineage  { background: #e8f5e9; color: #2e7d32; }

    .stat-info { flex: 1; }
    .stat-info h4 { 
        font-size: 2.2rem; 
        line-height: 1.1; 
        margin-bottom: 4px; 
        color: var(--maroon, #1a5653); 
        font-weight: 800; 
        font-family: 'Rabar022', sans-serif;
    }
    .stat-info .stat-label { 
        color: #333; 
        font-weight: 700; 
        font-size: 1.05rem; 
        margin: 0 0 3px 0; 
    }
    .stat-info .stat-sub { 
        color: #777; 
        font-size: 0.85rem; 
        margin: 0; 
    }

    /* Quick Mini Stats Bar */
    .mini-stats-bar {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 30px;
    }
    .mini-stat-item {
        background: #fff;
        padding: 14px 18px;
        border-radius: 12px;
        border: 1px solid #eee;
        display: flex;
        align-items: center;
        gap: 12px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.03);
    }
    .mini-stat-icon {
        font-size: 1.4rem;
    }
    .mini-stat-val {
        font-weight: 800;
        font-size: 1.15rem;
        color: var(--maroon, #1a5653);
    }
    .mini-stat-text {
        font-size: 0.85rem;
        color: #666;
    }

    /* Charts & Panels */
    .dashboard-grid { 
        display: grid; 
        grid-template-columns: 1fr 1fr; 
        gap: 25px; 
        margin-bottom: 30px; 
        align-items: stretch;
    }
    .lib-card {
        background: #fff;
        border-radius: 16px;
        border: 1px solid var(--border);
        box-shadow: var(--shadow-sm);
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }
    .card-header { 
        padding: 18px 25px; 
        border-bottom: 1px solid var(--border); 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        background: #fafafa;
    }
    .card-header h3 { 
        font-size: 1.15rem; 
        color: var(--maroon, #1a5653); 
        font-weight: 700; 
        margin: 0; 
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .alert-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 20px;
        border-bottom: 1px solid #f5f5f5;
        transition: background 0.2s;
    }
    .alert-item:hover {
        background: #fdfdfd;
    }
    .alert-item:last-child {
        border-bottom: none;
    }
    .member-thumb {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        overflow: hidden;
        border: 2px solid var(--gold, #C8A951);
        background: #f0f0f0;
        flex-shrink: 0;
    }
    .member-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    @media (max-width: 992px) {
        .dashboard-grid { grid-template-columns: 1fr; }
    }
</style>

<!-- Top Title Bar -->
<div class="dashboard-hero-title">
    <h2>
        <span>🏛️</span> داشبۆردی گشتی مەڵبەندی ڕۆشنبیری و کۆمەڵایەتی ساداتی پیرخدری شاهۆ
    </h2>
    <div style="display: flex; gap: 10px;">
        <a href="members_list.php" class="lib-btn lib-btn-primary"><i class="fas fa-users"></i> لیستی ئەندامان</a>
        <a href="members_new.php" class="lib-btn lib-btn-gold"><i class="fas fa-user-plus"></i> ئەندامی نوێ</a>
    </div>
</div>

<!-- 4 Main Stat Cards -->
<div class="stats-row">
    <!-- Card 1: Official Members -->
    <a href="members_list.php" class="stat-card">
        <div class="stat-icon official">👥</div>
        <div class="stat-info">
            <h4><?php echo number_format($stats['official_members']); ?></h4>
            <p class="stat-label">ئەندامانی فەرمی</p>
            <p class="stat-sub">خاوەن کۆدی ئەندامێتی و ناسنامە</p>
        </div>
    </a>
    
    <!-- Card 2: Families -->
    <a href="families_manage.php" class="stat-card">
        <div class="stat-icon families">🏛️</div>
        <div class="stat-info">
            <h4><?php echo number_format($families_count); ?></h4>
            <p class="stat-label">کۆی بنەماڵەکان</p>
            <p class="stat-sub">بنەماڵەی تۆمارکراو لە مەڵبەند</p>
        </div>
    </a>

    <!-- Card 3: Congress Board & Candidates -->
    <a href="elections_congress_members.php?election_id=3" class="stat-card">
        <div class="stat-icon congress">🗳️</div>
        <div class="stat-info">
            <h4><?php echo number_format($congress_members_count); ?></h4>
            <p class="stat-label">دەستەی کارگێڕی کۆنگرە</p>
            <p class="stat-sub">بەشداربووان و هەڵبژێردراوان</p>
        </div>
    </a>

    <!-- Card 4: Total Members & Lineage -->
    <a href="members_list.php" class="stat-card">
        <div class="stat-icon lineage">📜</div>
        <div class="stat-info">
            <h4><?php echo number_format($stats['total_members']); ?></h4>
            <p class="stat-label">کۆی نەسەب و ئەندامان</p>
            <p class="stat-sub">لە ژیاندا: <?php echo number_format($stats['alive_members']); ?> | کۆچکردوو: <?php echo number_format($stats['deceased_members']); ?></p>
        </div>
    </a>
</div>

<!-- Mini Stats Overview Bar -->
<div class="mini-stats-bar">
    <div class="mini-stat-item">
        <span class="mini-stat-icon">📚</span>
        <div>
            <div class="mini-stat-val"><?php echo number_format($books_count); ?> کتێب</div>
            <div class="mini-stat-text">کتێبخانەی دیجیتاڵی ساداتی پیرخدری شاهۆ</div>
        </div>
    </div>
    <div class="mini-stat-item">
        <span class="mini-stat-icon">📅</span>
        <div>
            <div class="mini-stat-val"><?php echo number_format($activities_count); ?> چالاکی</div>
            <div class="mini-stat-text">چالاکییە ڕۆشنبیری و کۆمەڵایەتییەکان</div>
        </div>
    </div>
    <div class="mini-stat-item">
        <span class="mini-stat-icon">📱</span>
        <div>
            <div class="mini-stat-val"><?php echo number_format($stats['official_with_phone']); ?> ئەندام</div>
            <div class="mini-stat-text">خاوەن ژمارەی مۆبایلی پەیوەندی</div>
        </div>
    </div>
    <div class="mini-stat-item">
        <span class="mini-stat-icon">🖼️</span>
        <div>
            <div class="mini-stat-val"><?php echo number_format($stats['with_photo']); ?> ئەندام</div>
            <div class="mini-stat-text">دۆسیەی خاوەن وێنەی فەرمی</div>
        </div>
    </div>
</div>

<!-- 2 Interactive Charts -->
<div class="dashboard-grid">
    <!-- Chart 1: Ranks Distribution -->
    <div class="lib-card">
        <div class="card-header">
            <h3>📊 دابەشبوونی پلە و پایەکانی دەستەی کارگێڕی</h3>
            <span class="lib-badge lib-badge-gold"><?php echo count($ranks_data); ?> پلەی جیاواز</span>
        </div>
        <div style="padding: 25px; height: 320px; position: relative;">
            <canvas id="ranksChart"></canvas>
        </div>
    </div>

    <!-- Chart 2: Member Records & Health -->
    <div class="lib-card">
        <div class="card-header">
            <h3>📈 ئاماری ڕەوشی ژیان و تۆماری پەیوەندی</h3>
            <span class="lib-badge lib-badge-primary"><?php echo number_format($stats['total_members']); ?> تۆمار</span>
        </div>
        <div style="padding: 25px; height: 320px; position: relative;">
            <canvas id="demographicsChart"></canvas>
        </div>
    </div>
</div>

<!-- 2 Middle Panels: Smart Notifications & Leadership Highlight -->
<div class="dashboard-grid">
    <!-- Smart Notifications Panel -->
    <div class="lib-card">
        <div class="card-header">
            <h3>🔔 پانێڵی ئاگادارکردنەوەی زیرەک (Smart Notifications)</h3>
            <?php if ($stats['official_no_phone'] > 0): ?>
                <span class="lib-badge lib-badge-red"><?php echo $stats['official_no_phone']; ?> کەمی زانیاری</span>
            <?php else: ?>
                <span class="lib-badge lib-badge-green">سیستەم تەواوە</span>
            <?php endif; ?>
        </div>
        <div style="padding: 10px 0; flex: 1;">
            <!-- Congress Notice -->
            <div class="alert-item" style="background: #fdfbf7; border-right: 4px solid var(--gold, #C8A951);">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <span style="font-size: 1.5rem;">🏛️</span>
                    <div>
                        <div style="font-weight: bold; color: #1a5653;">کۆنگرەی دووەمی مەڵبەند</div>
                        <div style="font-size: 0.85rem; color: #666;">٢٢ کاندید و ئەندامی دەستەی کارگێڕی بە سەرکەوتوویی جێگیر کراون</div>
                    </div>
                </div>
                <a href="elections_congress_members.php?election_id=3" class="lib-btn" style="padding: 5px 12px; font-size: 0.8rem; background: #1a5653; color: #fff;">بینین</a>
            </div>

            <!-- Missing Phone Alert -->
            <?php if ($stats['official_no_phone'] > 0): ?>
                <div style="padding: 12px 20px 6px 20px; font-weight: bold; color: #c62828; font-size: 0.9rem;">
                    ⚠️ ئەندامانی فەرمی بێ مۆبایل (پێویستیان بە تۆمارکردنی پەیوەندییە):
                </div>
                <?php foreach ($missing_phone_members as $m): ?>
                    <div class="alert-item">
                        <div>
                            <div style="font-weight: 700; color: #333;">
                                <?php echo htmlspecialchars($m['full_name']); ?>
                            </div>
                            <div style="font-size: 0.8rem; color: #888;">
                                کۆد: <b style="color: #1a5653;"><?php echo htmlspecialchars($m['member_code']); ?></b> 
                                <?php if (!empty($m['member_rank'])): ?>
                                    | <?php echo htmlspecialchars($m['member_rank']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <a href="members_edit.php?id=<?php echo $m['id']; ?>" class="lib-btn lib-btn-warning" style="padding: 4px 10px; font-size: 0.8rem;">✏️ زیادکردن</a>
                    </div>
                <?php endforeach; ?>
                <div style="padding: 10px 20px; text-align: left;">
                    <a href="members_list.php?filter_phone=empty" style="color: var(--maroon, #1a5653); font-size: 0.85rem; font-weight: bold; text-decoration: none;">
                        پیشاندانی هەموو ئەندامانی بێ مۆبایل (<?php echo $stats['official_no_phone']; ?>) ←
                    </a>
                </div>
            <?php else: ?>
                <div style="padding: 30px; text-align: center; color: #28a745;">
                    <i class="fas fa-check-circle" style="font-size: 2rem; margin-bottom: 10px;"></i>
                    <p style="margin: 0; font-weight: bold;">تەواوی ئەندامانی فەرمی ژمارەی مۆبایلیان تۆمارکراوە</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Leadership & Board Highlights -->
    <div class="lib-card">
        <div class="card-header">
            <h3>⭐ دەستەی بەڕێوەبردن و کارگێڕی مەڵبەند</h3>
            <a href="members_list.php?rank=<?php echo urlencode('دەستەی کارگێڕی (هەمیشەیی)'); ?>" class="lib-btn lib-btn-gold" style="padding: 5px 12px; font-size: 0.8rem;">لیستی کارگێڕی</a>
        </div>
        <div style="padding: 10px 0; flex: 1;">
            <?php if (!empty($leadership_members)): ?>
                <?php foreach ($leadership_members as $lead): ?>
                    <div class="alert-item">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div class="member-thumb">
                                <?php 
                                $lead_img = !empty($lead['profile_image']) ? $lead['profile_image'] : 'assets/imgae/user.png';
                                ?>
                                <img src="stream_image.php?path=<?php echo urlencode($lead_img); ?>" onerror="this.src='assets/imgae/user.png'">
                            </div>
                            <div>
                                <div style="font-weight: 700; color: #222;">
                                    <?php echo htmlspecialchars($lead['full_name']); ?>
                                </div>
                                <div style="font-size: 0.82rem; color: var(--gold, #C8A951); font-weight: 600;">
                                    <?php echo htmlspecialchars($lead['member_rank']); ?>
                                    <?php if (!empty($lead['member_post'])): ?>
                                        <span style="color: #666;">- <?php echo htmlspecialchars($lead['member_post']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div style="display: flex; gap: 5px; align-items: center;">
                            <span class="lib-badge lib-badge-primary"><?php echo htmlspecialchars($lead['member_code'] ?: $lead['id']); ?></span>
                            <a href="members_profile.php?id=<?php echo $lead['id']; ?>" class="lib-btn lib-btn-success" style="padding: 4px 8px; font-size: 0.8rem;">👁️</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="padding: 30px; text-align: center; color: #888;">هیچ ئەندامێکی دەستەی کارگێڕی بەردەست نییە</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Full Width: Recently Registered Members -->
<div class="lib-card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h3>📜 تۆماری دوایین ئەندامانی فەرمی لە مەڵبەند</h3>
        <a href="members_list.php" class="lib-btn lib-btn-primary" style="padding: 5px 15px; font-size: 0.85rem;">هەموو ئەندامان (<?php echo number_format($stats['official_members']); ?>)</a>
    </div>
    <div class="lib-table-wrapper">
        <table class="lib-table">
            <thead>
                <tr>
                    <th style="width: 70px;">وێنە</th>
                    <th style="width: 110px;">کۆدی ئەندام</th>
                    <th style="text-align: right;">ناوی تەواو</th>
                    <th>پلە و پێگە</th>
                    <th>بنەماڵە</th>
                    <th style="direction: ltr;">ژمارەی مۆبایل</th>
                    <th style="width: 130px;">کردارەکان</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($recent_members)): ?>
                    <?php foreach ($recent_members as $rm): ?>
                        <tr ondblclick="window.location.href='members_profile.php?id=<?php echo $rm['id']; ?>'" style="cursor: pointer;">
                            <td>
                                <div style="width: 42px; height: 42px; border-radius: 50%; overflow: hidden; margin: 0 auto; border: 2px solid var(--gold, #C8A951); background: #eee;">
                                    <?php 
                                    $r_img = !empty($rm['profile_image']) ? $rm['profile_image'] : 'assets/imgae/user.png';
                                    ?>
                                    <img src="stream_image.php?path=<?php echo urlencode($r_img); ?>" style="width: 100%; height: 100%; object-fit: contain; background: #fff;" onerror="this.src='assets/imgae/user.png'">
                                </div>
                            </td>
                            <td>
                                <span class="lib-badge lib-badge-primary">
                                    <?php echo htmlspecialchars(!empty($rm['member_code']) ? $rm['member_code'] : $rm['id']); ?>
                                </span>
                            </td>
                            <td style="text-align: right; font-weight: 700; color: #1a5653;">
                                <?php echo htmlspecialchars($rm['full_name']); ?>
                            </td>
                            <td>
                                <span style="font-weight: 600; color: var(--gold, #C8A951);"><?php echo htmlspecialchars($rm['member_rank'] ?: 'ئەندام'); ?></span>
                                <?php if (!empty($rm['member_post'])): ?>
                                    <div style="font-size: 0.8rem; color: #777;"><?php echo htmlspecialchars($rm['member_post']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($rm['family_name'] ?: '-'); ?></td>
                            <td>
                                <span style="direction: ltr; display: inline-block; font-weight: 600; color: #444;">
                                    <?php echo htmlspecialchars($rm['phone'] ?: '-'); ?>
                                </span>
                            </td>
                            <td>
                                <div style="display: flex; gap: 6px; justify-content: center;">
                                    <a href="members_profile.php?id=<?php echo $rm['id']; ?>" class="lib-btn lib-btn-success" style="padding: 4px 8px;" title="پڕۆفایل">👁️</a>
                                    <a href="members_edit.php?id=<?php echo $rm['id']; ?>" class="lib-btn lib-btn-warning" style="padding: 4px 8px;" title="دەستکاری">✏️</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" style="padding: 30px;">هیچ ئەندامێک نەدۆزرایەوە</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Chart.js Rendering -->
<script src="assets/js/chart.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Chart 1: Ranks Doughnut Chart
    const ranksData = <?php echo json_encode($ranks_data); ?>;
    const ctxRanks = document.getElementById('ranksChart').getContext('2d');
    
    new Chart(ctxRanks, {
        type: 'doughnut',
        data: {
            labels: ranksData.map(d => d.name),
            datasets: [{
                data: ranksData.map(d => d.count),
                backgroundColor: [
                    '#1a5653',
                    '#C8A951',
                    '#e67e22',
                    '#2980b9',
                    '#8e44ad',
                    '#16a085',
                    '#27ae60'
                ],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { 
                    position: 'bottom',
                    labels: { 
                        font: { family: 'Rabar022', size: 12 },
                        padding: 15
                    }
                }
            },
            cutout: '60%'
        }
    });

    // Chart 2: Demographics Bar Chart
    const ctxDemo = document.getElementById('demographicsChart').getContext('2d');
    new Chart(ctxDemo, {
        type: 'bar',
        data: {
            labels: [
                'لە ژیاندا ماون',
                'کۆچکردوو',
                'خاوەن مۆبایل',
                'بێ مۆبایل',
                'خاوەن وێنە'
            ],
            datasets: [{
                label: 'ژمارەی کەسەکان',
                data: [
                    <?php echo (int)$stats['alive_members']; ?>,
                    <?php echo (int)$stats['deceased_members']; ?>,
                    <?php echo (int)$stats['official_with_phone']; ?>,
                    <?php echo (int)$stats['official_no_phone']; ?>,
                    <?php echo (int)$stats['with_photo']; ?>
                ],
                backgroundColor: [
                    '#27ae60',
                    '#7f8c8d',
                    '#2980b9',
                    '#e74c3c',
                    '#f39c12'
                ],
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { font: { family: 'Rabar022' } }
                },
                x: {
                    ticks: { font: { family: 'Rabar022', size: 12 } }
                }
            }
        }
    });
});
</script>

<?php require_once 'includes/layout_end.php'; ?>
