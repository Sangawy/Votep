<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
require_once 'lineage_core.php';
require_once 'lineage_ai.php';
$conn = getDB();

// Handle AJAX Search Suggestions
if (isset($_GET['action']) && $_GET['action'] === 'search_ajax') {
    header('Content-Type: application/json; charset=utf-8');
    $q = trim($_GET['q'] ?? '');
    if (mb_strlen($q) < 1) {
        echo json_encode([]);
        exit;
    }
    
    $words = preg_split('/\s+/u', $q, -1, PREG_SPLIT_NO_EMPTY);
    if (empty($words)) {
        echo json_encode([]);
        exit;
    }

    $conditions = [];
    $params = [];
    $types = "";

    foreach ($words as $w) {
        $cleanW = normalizeName($w);
        $rawW = $w;
        $arW = str_replace('ی', 'ي', $cleanW);
        $kuW = str_replace('ي', 'ی', $cleanW);

        $likeClean = "%$cleanW%";
        $likeAr = "%$arW%";
        $likeKu = "%$kuW%";
        $likeRaw = "%$rawW%";

        $conditions[] = "(m.normalized_name LIKE ? 
                         OR fat.normalized_name LIKE ?
                         OR gfat.normalized_name LIKE ?
                         OR m.full_name LIKE ? 
                         OR m.full_name LIKE ?
                         OR fat.full_name LIKE ? 
                         OR fat.full_name LIKE ?
                         OR gfat.full_name LIKE ?
                         OR gfat.full_name LIKE ?
                         OR m.member_code LIKE ?)";
        $params[] = $likeClean;
        $params[] = $likeClean;
        $params[] = $likeClean;
        $params[] = $likeAr;
        $params[] = $likeKu;
        $params[] = $likeAr;
        $params[] = $likeKu;
        $params[] = $likeAr;
        $params[] = $likeKu;
        $params[] = $likeRaw;
        $types .= "ssssssssss";
    }

    $whereSql = implode(" AND ", $conditions);
    $sql = "SELECT m.id, m.full_name, m.first_name, m.gender, m.father_name as m_father, m.grandfather_name as m_grand,
                   m.member_code, m.member_status, m.profile_image, 
                   f.family_name, 
                   fat.full_name as father_name,
                   gfat.full_name as grandfather_name 
            FROM members m 
            LEFT JOIN families f ON m.family_id = f.id 
            LEFT JOIN members fat ON m.parent_id = fat.id 
            LEFT JOIN members gfat ON fat.parent_id = gfat.id 
            WHERE $whereSql 
            ORDER BY (m.member_status = 'active') DESC, m.id DESC 
            LIMIT 15";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $results = [];
    while ($row = $res->fetch_assoc()) {
        $personName = trim($row['full_name']);
        $fatherName = trim($row['father_name'] ?: ($row['m_father'] ?: ''));
        $grandName = trim($row['grandfather_name'] ?: ($row['m_grand'] ?: ''));
        $isFemale = (isset($row['gender']) && strtolower($row['gender']) === 'female');
        $sep1 = $isFemale ? ' بنت ' : ' بن ';

        if (!empty($fatherName) && !empty($grandName)) {
            $tripletName = $personName . $sep1 . $fatherName . ' بن ' . $grandName;
        } elseif (!empty($fatherName)) {
            $tripletName = $personName . $sep1 . $fatherName;
        } else {
            $tripletName = $personName;
        }

        $results[] = [
            'id' => (int)$row['id'],
            'full_name' => $row['full_name'],
            'triplet_name' => $tripletName,
            'father_name' => $fatherName ?: 'نادیار',
            'grandfather_name' => $grandName ?: 'نادیار',
            'family_name' => $row['family_name'] ?: 'ساداتی پیرخدری شاهۆ',
            'member_code' => $row['member_code'] ?: (string)$row['id'],
            'status' => $row['member_status'] ?: 'inactive',
            'image' => $row['profile_image'] ?: ''
        ];
    }
    $stmt->close();
    echo json_encode($results);
    exit;
}

// Toggle status directly from here if requested
if (isset($_GET['toggle_status'])) {
    $targetId = (int)$_GET['toggle_status'];
    $newStatus = ($_GET['new_status'] === 'active') ? 'active' : 'inactive';
    $conn->query("UPDATE members SET member_status = '$newStatus' WHERE id = $targetId");
    header("Location: kinship_search.php?id=$targetId");
    exit;
}

// Resolve Selected Person Kinship
$selectedId = (int)($_GET['id'] ?? 0);
$kinshipData = null;

if ($selectedId > 0) {
    $stmt = $conn->prepare("SELECT m.*, f.family_name, f.origin_area 
                            FROM members m 
                            LEFT JOIN families f ON m.family_id = f.id 
                            WHERE m.id = ?");
    $stmt->bind_param("i", $selectedId);
    $stmt->execute();
    $person = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($person) {
        $fatherId = (int)($person['parent_id'] ?? 0);
        $father = null;
        $grandfather = null;
        $uncles = [];
        $siblings = [];
        $children = [];
        $cousins = [];
        $ancestorsPath = [];

        // 1. Children
        $cRes = $conn->query("SELECT id, full_name, member_code, member_status, profile_image FROM members WHERE parent_id = $selectedId ORDER BY id ASC");
        while ($row = $cRes->fetch_assoc()) $children[] = $row;

        // 2. Father & Siblings
        if ($fatherId > 0) {
            $fRes = $conn->query("SELECT id, full_name, member_code, parent_id, family_id, member_status, profile_image FROM members WHERE id = $fatherId");
            if ($fRes && $fRow = $fRes->fetch_assoc()) {
                $father = $fRow;
                $grandId = (int)($father['parent_id'] ?? 0);

                // Siblings
                $sRes = $conn->query("SELECT id, full_name, member_code, member_status, profile_image FROM members WHERE parent_id = $fatherId AND id != $selectedId ORDER BY id ASC");
                while ($sRow = $sRes->fetch_assoc()) $siblings[] = $sRow;

                // Grandfather & Uncles
                if ($grandId > 0) {
                    $gRes = $conn->query("SELECT id, full_name, member_code, parent_id, family_id, member_status, profile_image FROM members WHERE id = $grandId");
                    if ($gRes && $gRow = $gRes->fetch_assoc()) {
                        $grandfather = $gRow;

                        // Uncles
                        $uRes = $conn->query("SELECT id, full_name, member_code, member_status, profile_image FROM members WHERE parent_id = $grandId AND id != $fatherId ORDER BY id ASC");
                        $uncleIds = [];
                        while ($uRow = $uRes->fetch_assoc()) {
                            $uncles[] = $uRow;
                            $uncleIds[] = (int)$uRow['id'];
                        }

                        // Cousins
                        if (!empty($uncleIds)) {
                            $uIdsList = implode(',', $uncleIds);
                            $couRes = $conn->query("SELECT m.id, m.full_name, m.member_code, m.parent_id, m.member_status, u.full_name as uncle_name 
                                                    FROM members m 
                                                    LEFT JOIN members u ON m.parent_id = u.id 
                                                    WHERE m.parent_id IN ($uIdsList) 
                                                    ORDER BY m.id ASC");
                            while ($couRow = $couRes->fetch_assoc()) $cousins[] = $couRow;
                        }
                    }
                }
            }
        }

        // 3. Ancestors Lineage Path up to Pirkhidri Shaho
        $currId = $fatherId;
        $visited = [];
        $shahoGen = -1;
        $step = 0;
        while ($currId > 0 && !isset($visited[$currId])) {
            $visited[$currId] = true;
            $step++;
            $aRes = $conn->query("SELECT id, full_name, member_code, parent_id FROM members WHERE id = $currId");
            if ($aRes && $aRow = $aRes->fetch_assoc()) {
                $ancestorsPath[] = $aRow;
                if ($aRow['id'] == 1 || isShahoName($aRow['full_name'])) {
                    if ($shahoGen === -1) $shahoGen = $step;
                }
                $currId = (int)($aRow['parent_id'] ?? 0);
            } else {
                break;
            }
        }

        $propheticAncestors = [
            ['name' => 'السيد محمود المدني', 'title' => 'باوکی پیرخدری شاهۆ'],
            ['name' => 'السيد جعفر', 'title' => 'باپیری پیرخدری شاهۆ'],
            ['name' => 'السيد حسين', 'title' => 'باپیرە گەورە'],
            ['name' => 'السيد محمود الينبوعي العاصم', 'title' => 'باپیرە گەورە'],
            ['name' => 'السيد رحمة الله (العارف بالله)', 'title' => 'العارف بالله'],
            ['name' => 'السيد موسى (الداعي الى الله)', 'title' => 'الداعي الى الله'],
            ['name' => 'السيد حسين الصامت', 'title' => 'الصامت'],
            ['name' => 'السيد هلال', 'title' => 'باپیرە گەورە'],
            ['name' => 'السيد محمد (الملقب بالخلف)', 'title' => 'الملقب بالخلف'],
            ['name' => 'السيد منهال', 'title' => 'باپیرە گەورە'],
            ['name' => 'السيد ماجد', 'title' => 'باپیرە گەورە'],
            ['name' => 'السيد عبدالرحمن', 'title' => 'باپیرە گەورە'],
            ['name' => 'السيد قاسم', 'title' => 'باپیرە گەورە'],
            ['name' => 'السيد ادريس', 'title' => 'باپیرە گەورە'],
            ['name' => 'السلطان جعفر', 'title' => 'السلطان'],
            ['name' => 'السيد الامام علي النقي (الهادي)', 'title' => 'ئیمامی دەیەم (ع)'],
            ['name' => 'السيد الامام محمد الجواد (التقي)', 'title' => 'ئیمامی نۆیەم (ع)'],
            ['name' => 'السيد الامام علي الرضا', 'title' => 'ئیمامی هەشتەم (ع)'],
            ['name' => 'السيد الامام موسى الكاظم', 'title' => 'ئیمامی حەوتەم (ع)'],
            ['name' => 'السيد الامام جعفر الصادق', 'title' => 'ئیمامی شەشەم (ع)'],
            ['name' => 'السيد الامام محمد الباقر', 'title' => 'ئیمامی پێنجەم (ع)'],
            ['name' => 'السيد الامام علي زين العابدين', 'title' => 'ئیمامی چوارەم (ع)'],
            ['name' => 'السيد الامام حسين الشهيد', 'title' => 'سید شباب اهل الجنة (ع)'],
            ['name' => 'السيدة فاطمة الزهراء (و الامام علي بن ابي طالب)', 'title' => 'سیدة نساء العالمين'],
            ['name' => 'پێغەمبەری ئیسلام محمد المصطفى ﷺ', 'title' => 'خاتم الأنبياء والمرسلين (ابن عبدالله)']
        ];

        $prophetGen = ($shahoGen > 0) ? ($shahoGen + count($propheticAncestors)) : ($selectedId == 1 ? count($propheticAncestors) : 0);

        $kinshipData = [
            'person' => $person,
            'father' => $father,
            'grandfather' => $grandfather,
            'children' => $children,
            'siblings' => $siblings,
            'uncles' => $uncles,
            'cousins' => $cousins,
            'ancestors_path' => $ancestorsPath,
            'generations_to_shaho' => $shahoGen,
            'generations_to_prophet' => $prophetGen,
            'prophetic_ancestors' => $propheticAncestors
        ];
    }
}

$page_title = 'ناسینەوەی کەسی و بازنەی خزمایەتی';
$active_nav = 'kinship_search';
$sys_module = 'lineage';
require_once 'includes/layout.php';
?>

<style>
    .kin-search-hero {
        background: linear-gradient(135deg, #1a5653 0%, #103b39 100%);
        color: #fff;
        padding: 40px 30px;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(26, 86, 83, 0.2);
        margin-bottom: 30px;
        position: relative;
        z-index: 100;
        overflow: visible;
    }
    .kin-search-hero::after {
        content: "🧬";
        position: absolute;
        left: 25px;
        bottom: 10px;
        font-size: 110px;
        opacity: 0.08;
        pointer-events: none;
    }
    .search-input-box {
        position: relative;
        max-width: 780px;
        margin-top: 20px;
        z-index: 110;
    }
    .search-input-box input {
        width: 100%;
        padding: 16px 50px 16px 20px;
        border-radius: 35px;
        border: 2px solid rgba(255,255,255,0.3);
        background: rgba(255, 255, 255, 0.98);
        color: #1e293b;
        font-size: 1.1rem;
        font-family: inherit;
        outline: none;
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        transition: all 0.3s ease;
    }
    .search-input-box input:focus {
        background: #fff;
        border-color: #f1c40f;
        box-shadow: 0 10px 30px rgba(0,0,0,0.25);
    }
    .search-input-box i.search-icon {
        position: absolute;
        right: 20px;
        top: 50%;
        transform: translateY(-50%);
        color: #1a5653;
        font-size: 1.3rem;
    }
    .autocomplete-dropdown {
        position: absolute;
        top: calc(100% + 10px);
        left: 0;
        right: 0;
        background: #ffffff;
        border-radius: 16px;
        box-shadow: 0 20px 45px rgba(0,0,0,0.25);
        border: 2px solid #1a5653;
        max-height: 380px;
        overflow-y: auto;
        z-index: 99999;
        display: none;
    }
    .autocomplete-item {
        padding: 12px 18px;
        display: flex;
        align-items: center;
        gap: 14px;
        border-bottom: 1px solid #f1f5f9;
        cursor: pointer;
        transition: background 0.15s;
        text-decoration: none;
        color: inherit;
    }
    .autocomplete-item:hover {
        background: #f8fafc;
    }
    .autocomplete-avatar {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        background: #e0f2fe;
        color: #0369a1;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        flex-shrink: 0;
    }

    /* Person Profile Card */
    .profile-hero-card {
        background: #fff;
        border-radius: 20px;
        padding: 30px;
        box-shadow: 0 6px 20px rgba(0,0,0,0.05);
        border: 1px solid #e2e8f0;
        margin-bottom: 30px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 25px;
        flex-wrap: wrap;
    }
    .profile-main-info {
        display: flex;
        align-items: center;
        gap: 20px;
    }
    .profile-main-avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: #1a5653;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.2rem;
        box-shadow: 0 6px 15px rgba(26, 86, 83, 0.25);
        flex-shrink: 0;
    }
    .status-tag {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 0.8rem;
        font-weight: bold;
    }
    .status-tag.active {
        background: #ecfdf5;
        color: #047857;
        border: 1px solid #a7f3d0;
    }
    .status-tag.ancestor {
        background: #f1f5f9;
        color: #475569;
        border: 1px solid #cbd5e1;
    }

    /* Kinship Sections Grid */
    .kin-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 25px;
        margin-bottom: 30px;
    }
    .kin-card {
        background: #fff;
        border-radius: 18px;
        padding: 22px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.04);
        border: 1px solid #e2e8f0;
        display: flex;
        flex-direction: column;
    }
    .kin-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding-bottom: 12px;
        border-bottom: 2px solid #f1f5f9;
        margin-bottom: 15px;
    }
    .kin-card-header h3 {
        margin: 0;
        font-size: 1.1rem;
        color: #1e293b;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .kin-card-header .count-badge {
        background: #f1f5f9;
        color: #475569;
        font-weight: bold;
        font-size: 0.8rem;
        padding: 2px 9px;
        border-radius: 12px;
    }
    .relatives-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
        flex: 1;
    }
    .relative-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 14px;
        background: #f8fafc;
        border-radius: 12px;
        border: 1px solid #edf2f7;
        text-decoration: none;
        color: inherit;
        transition: all 0.2s;
    }
    .relative-item:hover {
        background: #f1f5f9;
        border-color: #cbd5e1;
        transform: translateX(-3px);
    }
    .relative-name {
        font-weight: bold;
        color: #1e293b;
        font-size: 0.95rem;
    }
    .relative-sub {
        font-size: 0.78rem;
        color: #64748b;
        margin-top: 2px;
    }
    .empty-notice {
        text-align: center;
        padding: 20px;
        color: #94a3b8;
        font-size: 0.9rem;
    }

    /* Ancestors Timeline */
    .ancestors-timeline-box {
        background: #fff;
        border-radius: 20px;
        padding: 25px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 15px rgba(0,0,0,0.04);
        margin-bottom: 30px;
    }
    .timeline-chain {
        display: flex;
        align-items: center;
        gap: 8px;
        overflow-x: auto;
        padding: 15px 5px;
        scrollbar-width: thin;
    }
    .ancestor-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: #f8fafc;
        border: 1px solid #cbd5e1;
        padding: 8px 16px;
        border-radius: 25px;
        font-weight: bold;
        font-size: 0.88rem;
        color: #1e293b;
        white-space: nowrap;
        text-decoration: none;
        transition: all 0.2s;
    }
    .ancestor-pill:hover {
        background: #e2e8f0;
        transform: translateY(-2px);
    }
    .ancestor-pill.shaho {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: #fff;
        border-color: #d97706;
        box-shadow: 0 4px 12px rgba(217, 119, 6, 0.3);
    }
    .ancestor-pill.prophetic {
        background: #f0fdf4;
        color: #166534;
        border-color: #bbf7d0;
        cursor: default;
    }
    .ancestor-pill.prophetic:hover {
        background: #dcfce7;
        border-color: #86efac;
    }
    .ancestor-pill.prophet {
        background: linear-gradient(135deg, #059669 0%, #047857 100%);
        color: #ffffff;
        border-color: #047857;
        box-shadow: 0 4px 14px rgba(5, 150, 105, 0.35);
        cursor: default;
    }
    .ancestor-pill.prophet:hover {
        background: linear-gradient(135deg, #047857 0%, #065f46 100%);
        transform: translateY(-2px);
    }
</style>

<div style="padding: 0 20px;">
    <!-- Search Hero -->
    <div class="kin-search-hero">
        <h1 style="margin: 0 0 10px 0; font-size: 1.8rem; font-weight: bold;">🔍 ناسینەوەی کەسی و تۆڕی خزمایەتی</h1>
        <p style="margin: 0; opacity: 0.9; font-size: 1rem;">ناوی هەر ئەندامێک بنووسە بۆ دۆزینەوەی تەواوی تۆڕی کەسوکاری (برا، مام، منداڵ، ئامۆزا و هێڵی باوباپیران).</p>
        
        <div class="search-input-box">
            <i class="fas fa-search search-icon"></i>
            <input type="text" id="kinshipSearchInput" 
                   autocomplete="off"
                   placeholder="ناوی کەسەکە لێرە بنووسە (بۆ نموونە: سامان، ئاوات، مەلا شەریف، ڕەفیق...)" 
                   value="<?php echo htmlspecialchars($kinshipData['person']['full_name'] ?? ''); ?>">
            
            <div id="searchDropdown" class="autocomplete-dropdown"></div>
        </div>
    </div>

    <?php if ($kinshipData): 
        $p = $kinshipData['person'];
        $father = $kinshipData['father'];
        $grandfather = $kinshipData['grandfather'];
        $siblings = $kinshipData['siblings'];
        $uncles = $kinshipData['uncles'];
        $children = $kinshipData['children'];
        $cousins = $kinshipData['cousins'];
        $ancestors = $kinshipData['ancestors_path'];
        $shahoGen = $kinshipData['generations_to_shaho'];
        $prophetGen = $kinshipData['generations_to_prophet'] ?? 0;
        $propheticAncestors = $kinshipData['prophetic_ancestors'] ?? [];
        $isActive = ($p['member_status'] ?? '') === 'active';
    ?>

    <!-- Main Person Card -->
    <div class="profile-hero-card">
        <div class="profile-main-info">
            <div class="profile-main-avatar">
                <?php echo mb_substr($p['full_name'], 0, 1); ?>
            </div>
            <div>
                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <h2 style="margin: 0; color: #1e293b; font-size: 1.45rem; font-weight: bold;"><?php echo htmlspecialchars($p['full_name']); ?></h2>
                    <?php if($isActive): ?>
                        <span class="status-tag active">🟢 لە ژیاندا ماو / چالاک</span>
                    <?php else: ?>
                        <span class="status-tag ancestor">🕊️ کۆچکردوو</span>
                    <?php endif; ?>
                    <?php if(!empty($p['is_family_head'])): ?>
                        <span style="background: #fef3c7; color: #b45309; border: 1px solid #fde68a; padding: 3px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: bold;">⭐ سەرۆک خێزان</span>
                    <?php endif; ?>
                </div>

                <div style="color: #64748b; font-size: 0.9rem; margin-top: 8px; display: flex; gap: 14px; flex-wrap: wrap; align-items: center;">
                    <span>🏛️ بنەماڵە: <strong style="color: #1e293b;"><?php echo htmlspecialchars($p['family_name'] ?: 'ساداتی پیرخدری شاهۆ'); ?></strong></span>
                    <span>📍 ناوچە: <strong><?php echo htmlspecialchars($p['address'] ?: ($p['origin_area'] ?: '-')); ?></strong></span>
                    <span>🔢 کۆد: <strong><?php echo htmlspecialchars($p['member_code'] ?: $p['id']); ?></strong></span>
                    <?php if($shahoGen > 0): ?>
                        <span style="color: #b45309; font-weight: bold; background: #fef3c7; padding: 3px 12px; border-radius: 12px; border: 1px solid #fde68a;">👑 بە <?php echo $shahoGen; ?> پشت دەگاتەوە بە پیرخدری شاهۆ</span>
                    <?php endif; ?>
                    <?php if($prophetGen > 0): ?>
                        <span style="color: #047857; font-weight: bold; background: #ecfdf5; padding: 3px 12px; border-radius: 12px; border: 1px solid #a7f3d0;">💚 بە <?php echo $prophetGen; ?> پشت دەگاتەوە بە پێغەمبەری ئیسلام (ﷺ)</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <?php if($isActive): ?>
                <a href="kinship_search.php?toggle_status=<?php echo $p['id']; ?>&new_status=inactive" class="lib-btn" style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; font-size: 0.88rem;">
                    📜 گۆڕین بۆ باوباپیران
                </a>
            <?php else: ?>
                <a href="kinship_search.php?toggle_status=<?php echo $p['id']; ?>&new_status=active" class="lib-btn" style="background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; font-size: 0.88rem;">
                    🟢 گۆڕین بۆ لە ژیاندا ماوان
                </a>
            <?php endif; ?>

            <a href="lineage_tree.php" class="lib-btn lib-btn-gold" style="font-size: 0.88rem;">
                🌳 بینین لە درەختی بنەماڵە
            </a>

            <a href="descendants_tree.php?id=<?php echo $p['id']; ?>" target="_blank" class="lib-btn" style="background: linear-gradient(135deg, #15803d, #166534); color: #fff; font-size: 0.88rem; box-shadow: 0 4px 12px rgba(22,101,52,0.25);" title="داری نەوەکان (شەجەرەی پۆستەر هاوشێوەی وێنەی سروشتی)">
                <i class="fas fa-tree"></i> 🌿 داری نەوەکان (شەجەرە)
            </a>

            <a href="lineage_reports.php?cert_id=<?php echo $p['id']; ?>&type=certificate" target="_blank" class="lib-btn" style="background: #720000; color: #fff; font-size: 0.88rem;">
                <i class="fas fa-certificate"></i> شەهادەی نەسەب
            </a>

            <a href="lineage_reports.php?cert_id=<?php echo $p['id']; ?>&type=committee" target="_blank" class="lib-btn" style="background: #0b5e28; color: #fff; font-size: 0.88rem;">
                <i class="fas fa-file-contract"></i> کڵێشەی فەرمی
            </a>
        </div>
    </div>

    <!-- Ancestors Path Timeline -->
    <?php if(!empty($ancestors)): ?>
    <div class="ancestors-timeline-box">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; flex-wrap: wrap; gap: 10px;">
            <h3 style="margin: 0; color: #1e293b; font-size: 1.1rem; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-route" style="color: var(--maroon);"></i> زنجیرەی باوباپیران تاوەکو پیرخدری شاهۆ و پێغەمبەری ئیسلام (ﷺ)
            </h3>
            <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                <?php if($shahoGen > 0): ?>
                    <span style="background: #fef3c7; color: #92400e; font-weight: bold; font-size: 0.85rem; padding: 5px 14px; border-radius: 20px; border: 1px solid #fde68a; display: inline-flex; align-items: center; gap: 6px;">
                        👑 بەستراوە بە پیرخدری شاهۆ بە <?php echo $shahoGen; ?> پشت
                    </span>
                <?php endif; ?>
                <?php if($prophetGen > 0): ?>
                    <span style="background: #ecfdf5; color: #065f46; font-weight: bold; font-size: 0.85rem; padding: 5px 14px; border-radius: 20px; border: 1px solid #a7f3d0; display: inline-flex; align-items: center; gap: 6px;">
                        💚 بەستراوە بە پێغەمبەری ئیسلام (ﷺ) بە <?php echo $prophetGen; ?> پشت
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <div class="timeline-chain">
            <span class="ancestor-pill" style="background: #1a5653; color: #fff; border-color: #1a5653;">
                👤 <?php echo htmlspecialchars($p['full_name']); ?>
            </span>
            <span style="color: #94a3b8; font-weight: bold;">⬅️</span>

            <?php foreach ($ancestors as $idx => $a): 
                $isShaho = ($a['id'] == 1 || isShahoName($a['full_name']));
                $arrow = ($idx < count($ancestors) - 1 || ($isShaho && $prophetGen > 0)) ? '<span style="color: #94a3b8; font-weight: bold;">⬅️</span>' : '';
            ?>
                <a href="kinship_search.php?id=<?php echo $a['id']; ?>" class="ancestor-pill <?php echo $isShaho ? 'shaho' : ''; ?>" title="کلیک بکە بۆ ناسینەوەی ئەم کەسە">
                    <span><?php echo $isShaho ? '👑' : '📍'; ?></span>
                    <span><?php echo htmlspecialchars($a['full_name']); ?></span>
                </a>
                <?php echo $arrow; ?>
            <?php endforeach; ?>

            <?php if($shahoGen > 0 && !empty($propheticAncestors)): ?>
                <?php foreach ($propheticAncestors as $pIdx => $pAnc): 
                    $isLastProphet = ($pIdx === count($propheticAncestors) - 1);
                    $pArrow = (!$isLastProphet) ? '<span style="color: #94a3b8; font-weight: bold;">⬅️</span>' : '';
                ?>
                    <span class="ancestor-pill <?php echo $isLastProphet ? 'prophet' : 'prophetic'; ?>" title="<?php echo htmlspecialchars($pAnc['title']); ?>">
                        <span><?php echo $isLastProphet ? '💚' : '✨'; ?></span>
                        <span><?php echo htmlspecialchars($pAnc['name']); ?></span>
                    </span>
                    <?php echo $pArrow; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Kinship Grid (Father, Grandfather, Children, Siblings, Uncles, Cousins) -->
    <div class="kin-grid">
        <!-- 1. Father & Grandfather -->
        <div class="kin-card">
            <div class="kin-card-header">
                <h3>🧔 باوک و باپیر</h3>
            </div>
            <div class="relatives-list">
                <?php if($father): ?>
                    <a href="kinship_search.php?id=<?php echo $father['id']; ?>" class="relative-item">
                        <div>
                            <div class="relative-name">باوک: <?php echo htmlspecialchars($father['full_name']); ?></div>
                            <div class="relative-sub">کۆد: <?php echo htmlspecialchars($father['member_code'] ?: $father['id']); ?></div>
                        </div>
                        <span style="font-size: 0.78rem; background: #e0f2fe; color: #0369a1; padding: 3px 8px; border-radius: 6px; font-weight: bold;">باوک</span>
                    </a>
                <?php else: ?>
                    <div class="empty-notice">ناوی باوک تۆمار نەکراوە.</div>
                <?php endif; ?>

                <?php if($grandfather): ?>
                    <a href="kinship_search.php?id=<?php echo $grandfather['id']; ?>" class="relative-item">
                        <div>
                            <div class="relative-name">باپیر: <?php echo htmlspecialchars($grandfather['full_name']); ?></div>
                            <div class="relative-sub">کۆد: <?php echo htmlspecialchars($grandfather['member_code'] ?: $grandfather['id']); ?></div>
                        </div>
                        <span style="font-size: 0.78rem; background: #fef3c7; color: #92400e; padding: 3px 8px; border-radius: 6px; font-weight: bold;">باپیر</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- 2. Children -->
        <div class="kin-card">
            <div class="kin-card-header">
                <h3>👶 منداڵەکان (کوڕ و کچ)</h3>
                <span class="count-badge"><?php echo count($children); ?></span>
            </div>
            <div class="relatives-list">
                <?php if(!empty($children)): ?>
                    <?php foreach ($children as $c): ?>
                        <a href="kinship_search.php?id=<?php echo $c['id']; ?>" class="relative-item">
                            <div>
                                <div class="relative-name"><?php echo htmlspecialchars($c['full_name']); ?></div>
                                <div class="relative-sub">کۆد: <?php echo htmlspecialchars($c['member_code'] ?: $c['id']); ?></div>
                            </div>
                            <span style="font-size: 0.78rem; background: #ecfdf5; color: #047857; padding: 3px 8px; border-radius: 6px; font-weight: bold;">منداڵ</span>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-notice">هیچ منداڵێکی ئەم کەسە تۆمار نەکراوە.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 3. Siblings -->
        <div class="kin-card">
            <div class="kin-card-header">
                <h3>👥 براکان و خوشکەکان</h3>
                <span class="count-badge"><?php echo count($siblings); ?></span>
            </div>
            <div class="relatives-list">
                <?php if(!empty($siblings)): ?>
                    <?php foreach ($siblings as $s): ?>
                        <a href="kinship_search.php?id=<?php echo $s['id']; ?>" class="relative-item">
                            <div>
                                <div class="relative-name"><?php echo htmlspecialchars($s['full_name']); ?></div>
                                <div class="relative-sub">کۆد: <?php echo htmlspecialchars($s['member_code'] ?: $s['id']); ?></div>
                            </div>
                            <span style="font-size: 0.78rem; background: #ede9fe; color: #6d28d9; padding: 3px 8px; border-radius: 6px; font-weight: bold;">برا/خوشک</span>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-notice">هیچ برایەکی ئەم کەسە تۆمار نەکراوە.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 4. Uncles -->
        <div class="kin-card">
            <div class="kin-card-header">
                <h3>👤 مامەکان (براکانی باوک)</h3>
                <span class="count-badge"><?php echo count($uncles); ?></span>
            </div>
            <div class="relatives-list">
                <?php if(!empty($uncles)): ?>
                    <?php foreach ($uncles as $u): ?>
                        <a href="kinship_search.php?id=<?php echo $u['id']; ?>" class="relative-item">
                            <div>
                                <div class="relative-name"><?php echo htmlspecialchars($u['full_name']); ?></div>
                                <div class="relative-sub">مامی کەسەکە | کۆد: <?php echo htmlspecialchars($u['member_code'] ?: $u['id']); ?></div>
                            </div>
                            <span style="font-size: 0.78rem; background: #e0f2fe; color: #0369a1; padding: 3px 8px; border-radius: 6px; font-weight: bold;">مام</span>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-notice">هیچ مامیك لە داتابەیسدا نەدۆزرایەوە.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 5. Cousins -->
        <div class="kin-card" style="grid-column: 1 / -1;">
            <div class="kin-card-header">
                <h3>🤝 ئامۆزاکان (منداڵەکانی مامەکان)</h3>
                <span class="count-badge"><?php echo count($cousins); ?></span>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 12px;">
                <?php if(!empty($cousins)): ?>
                    <?php foreach ($cousins as $cou): ?>
                        <a href="kinship_search.php?id=<?php echo $cou['id']; ?>" class="relative-item">
                            <div>
                                <div class="relative-name"><?php echo htmlspecialchars($cou['full_name']); ?></div>
                                <div class="relative-sub">باوک (مام): <?php echo htmlspecialchars($cou['uncle_name']); ?></div>
                            </div>
                            <span style="font-size: 0.78rem; background: #fef3c7; color: #92400e; padding: 3px 8px; border-radius: 6px; font-weight: bold;">ئامۆزا</span>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-notice" style="grid-column: 1 / -1;">هیچ ئامۆزایەکی ئەم کەسە لە داتابەیسدا تۆمار نەکراوە.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php elseif ($selectedId > 0): ?>
        <div style="text-align: center; padding: 60px 20px; background: #fff; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
            <div style="font-size: 3rem; color: #dc2626; margin-bottom: 15px;">⚠️</div>
            <h2 style="color: #1e293b;">ئەم کەسە نەدۆزرایەوە</h2>
            <p style="color: #64748b;">تکایە ناوی کەسێکی تر لە بۆکسی گەڕانی سەرەوە بنووسە.</p>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 80px 20px; background: #fff; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.04); border: 2px dashed #e2e8f0;">
            <div style="font-size: 4rem; margin-bottom: 15px;">🔎</div>
            <h2 style="color: #1e293b; margin-bottom: 10px;">ناوی کەسەکە لە سەرەوە بنووسە بۆ ناسینەوە</h2>
            <p style="color: #64748b; max-width: 500px; margin: 0 auto;">دەتوانیت ناوی هەر ئەندامێکی تۆمارکراوی بنەماڵە بنووسیت بۆ بینینی باوک، باپیر، برا، مام، منداڵ و زنجیرەی پشتاوپشتی نەسەبەکەی.</p>
        </div>
    <?php endif; ?>
</div>

<script>
(function() {
    let kinSearchTimeout = null;
    const kinSearchInput = document.getElementById('kinshipSearchInput');
    const kinDropdown = document.getElementById('searchDropdown');

    if (!kinSearchInput || !kinDropdown) return;

    kinSearchInput.addEventListener('input', function() {
        clearTimeout(kinSearchTimeout);
        const val = this.value.trim();
        if (val.length < 1) {
            kinDropdown.style.display = 'none';
            return;
        }

        kinSearchTimeout = setTimeout(async () => {
            try {
                const res = await fetch(`kinship_search.php?action=search_ajax&q=${encodeURIComponent(val)}`);
                const data = await res.json();
                if (data.length > 0) {
                    let html = '';
                    data.forEach(m => {
                        const statusBadge = m.status === 'active' 
                            ? '<span style="display: inline-flex; align-items: center; gap: 4px; background: #ecfdf5; color: #047857; font-size: 0.75rem; font-weight: bold; padding: 3px 10px; border-radius: 20px; border: 1px solid #a7f3d0;"><span style="width: 7px; height: 7px; border-radius: 50%; background: #10b981;"></span> لە ژیاندا ماو</span>' 
                            : '<span style="display: inline-flex; align-items: center; gap: 4px; background: #f8fafc; color: #64748b; font-size: 0.75rem; padding: 3px 10px; border-radius: 20px; border: 1px solid #e2e8f0;">🕊️ کۆچکردوو</span>';
                        
                        const displayName = m.triplet_name || m.full_name;

                        html += `
                            <a href="kinship_search.php?id=${m.id}" class="autocomplete-item">
                                <div class="autocomplete-avatar">${m.full_name.charAt(0)}</div>
                                <div style="flex: 1; min-width: 0;">
                                    <div style="font-weight: 700; color: #0f172a; font-size: 0.98rem; line-height: 1.4; word-break: break-word;">${displayName}</div>
                                    <div style="font-size: 0.8rem; color: #64748b; margin-top: 4px; display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
                                        <span>🏛️ بنەماڵە: <strong style="color: #334155;">${m.family_name}</strong></span>
                                        <span>🔢 کۆد: <strong>#${m.member_code}</strong></span>
                                    </div>
                                </div>
                                <div style="flex-shrink: 0;">
                                    ${statusBadge}
                                </div>
                            </a>
                        `;
                    });
                    kinDropdown.innerHTML = html;
                    kinDropdown.style.display = 'block';
                } else {
                    kinDropdown.innerHTML = '<div style="padding: 15px; text-align: center; color: #94a3b8;">هیچ ناوێک نەدۆزرایەوە</div>';
                    kinDropdown.style.display = 'block';
                }
            } catch (e) {
                console.error(e);
            }
        }, 200);
    });

    // Support keyboard Enter to pick first result
    kinSearchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            const firstLink = kinDropdown.querySelector('.autocomplete-item');
            if (firstLink) {
                window.location.href = firstLink.getAttribute('href');
            }
        }
    });

    document.addEventListener('click', function(e) {
        if (!kinSearchInput.contains(e.target) && !kinDropdown.contains(e.target)) {
            kinDropdown.style.display = 'none';
        }
    });
})();
</script>

<?php require_once 'includes/layout_end.php'; ?>
