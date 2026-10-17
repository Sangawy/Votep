<?php
$sys_module = 'lineage';
require_once 'config.php';
require_once 'lineage_core.php';
$conn = getDB();

// --- AJAX HANDLER FOR QUICK ADD ---
if (isset($_POST['action']) && $_POST['action'] == 'quick_add_members') {
    $parent_id = (int)$_POST['parent_id'];
    $family_id = (int)$_POST['family_id'];
    $names_raw = $_POST['names'];
    
    $titles = ['ملا', 'شێخ', 'شیخ', 'مولا', 'مەلا', 'السید', 'السيد', 'سید', 'سەید', 'د.', 'دکتۆر', 'الشیخ', 'الشيخ'];
    
    function getRealFirstName($fullName, $titles) {
        $parts = explode(' ', trim($fullName));
        foreach ($parts as $p) {
            if (!in_array($p, $titles) && !empty($p)) return $p;
        }
        return '';
    }

    // Build the ancestors array
    $ancestor_names = [];
    $curr_id = $parent_id;
    $last_full_name = '';

    for ($i = 0; $i < 3; $i++) { // We need 3 more names to make it 4
        $p_stmt = $conn->prepare("SELECT full_name, parent_id FROM members WHERE id = ?");
        $p_stmt->bind_param("i", $curr_id);
        $p_stmt->execute();
        $res = $p_stmt->get_result()->fetch_assoc();
        
        if ($res) {
            $last_full_name = $res['full_name'];
            $fn = getRealFirstName($res['full_name'], $titles);
            if ($fn) $ancestor_names[] = $fn;
            $curr_id = (int)$res['parent_id'];
            if ($curr_id == 0) break;
        } else break;
    }

    // If we have fewer than 3 ancestors, try to extract from the last full name string
    if (count($ancestor_names) < 3 && !empty($last_full_name)) {
        $parts = explode(' ', trim($last_full_name));
        $extracted = [];
        foreach ($parts as $p) {
            if (!in_array($p, $titles) && !empty($p)) $extracted[] = $p;
        }
        // Skip the first one as it's already in ancestor_names[0]
        for ($j = 1; $j < count($extracted); $j++) {
            if (count($ancestor_names) < 3) {
                $ancestor_names[] = $extracted[$j];
            }
        }
    }

    // Split input names by comma, dash, or "و" / "والسيد"
    $raw_split = preg_replace('/\s*(?:والسید|والسيد)\s+/u', ' ، السيد ', $names_raw);
    $raw_split = preg_replace('/\s+(?:و|وە)\s+/u', ' ، ', $raw_split);
    $names = preg_split('/[,،\-]/u', $raw_split, -1, PREG_SPLIT_NO_EMPTY);
    $added_count = 0;

    foreach ($names as $name) {
        $name = trim($name);
        if (empty($name)) continue;
        
        $hasTitle = false;
        foreach ($titles as $t) {
            if (mb_strpos($name, $t) === 0) { $hasTitle = true; break; }
        }
        $full_name_final = $hasTitle ? $name : "السيد " . $name;
        $norm = normalizeName($full_name_final);

        $stmt = $conn->prepare("INSERT INTO members (full_name, normalized_name, parent_id, family_id, member_rank, member_status, is_alive, is_official_member, created_at) VALUES (?, ?, ?, ?, NULL, 'active', 1, 0, NOW())");
        $stmt->bind_param("ssii", $full_name_final, $norm, $parent_id, $family_id);
        if ($stmt->execute()) {
            $newId = $conn->insert_id;
            updateMemberNameFields($conn, $newId);
            $added_count++;
        }
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'count' => $added_count]);
    exit;
}

// --- AJAX HANDLER FOR TOGGLE FAMILY HEAD ---
if (isset($_POST['action']) && $_POST['action'] == 'toggle_family_head') {
    $member_id = (int)($_POST['member_id'] ?? 0);
    $is_head = (int)($_POST['is_head'] ?? 0);
    if ($member_id > 0) {
        $stmt = $conn->prepare("UPDATE members SET is_family_head = ? WHERE id = ?");
        $stmt->bind_param("ii", $is_head, $member_id);
        $stmt->execute();
        $stmt->close();
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'is_head' => $is_head, 'member_id' => $member_id]);
        exit;
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid member ID']);
    exit;
}

// --- AJAX HANDLER FOR EDIT MEMBER & LINK TO FATHER ---
if (isset($_POST['action']) && $_POST['action'] == 'edit_member_lineage') {
    $member_id = (int)($_POST['member_id'] ?? 0);
    $parent_id = (int)($_POST['parent_id'] ?? 0);
    $full_name = trim($_POST['full_name'] ?? '');

    if ($member_id <= 0 || empty($full_name)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'زانیارییەکان تەواو نین!']);
        exit;
    }

    if ($member_id === 1) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'پیرخدری شاهۆ باوە گەورەی ساداتە و باوکی دەستکاری ناکرێت.']);
        exit;
    }

    if ($parent_id > 0 && wouldCreateCycle($conn, $member_id, $parent_id)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'ناتوانرێت ئەم باوکە دیاری بکرێت چون بازنەیەک (Cycle) لە ڕەچەڵەکدا دروست دەکات!']);
        exit;
    }

    $family_id_post = (int)($_POST['family_id'] ?? 0);
    $norm = normalizeName($full_name);
    if ($family_id_post > 0) {
        $stmt = $conn->prepare("UPDATE members SET full_name = ?, normalized_name = ?, parent_id = ?, family_id = ? WHERE id = ?");
        $stmt->bind_param("ssiii", $full_name, $norm, $parent_id, $family_id_post, $member_id);
    } else {
        $stmt = $conn->prepare("UPDATE members SET full_name = ?, normalized_name = ?, parent_id = ? WHERE id = ?");
        $stmt->bind_param("ssii", $full_name, $norm, $parent_id, $member_id);
    }
    if ($stmt->execute()) {
        $stmt->close();
        updateMemberNameFields($conn, $member_id);

        // Update immediate children
        $chRes = $conn->query("SELECT id FROM members WHERE parent_id = $member_id");
        while ($ch = $chRes->fetch_assoc()) {
            updateMemberNameFields($conn, (int)$ch['id']);
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'زانیارییەکان و بەستنەوە بە باوک بە سەرکەوتوویی پاشەکەوت کران']);
        exit;
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'هەڵەیەک لە نوێکردنەوە ڕوویدا: ' . $conn->error]);
        exit;
    }
}

// --- AJAX HANDLER FOR SAFE DELETE OF LEAF MEMBER ---
if (isset($_POST['action']) && $_POST['action'] == 'delete_lineage_member') {
    $member_id = (int)($_POST['member_id'] ?? 0);
    if ($member_id <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'کەسی نادیار!']);
        exit;
    }

    if ($member_id === 1) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'پیرخدری شاهۆ باوە گەورەی هەموو ساداتە و ناسڕدرێتەوە!']);
        exit;
    }

    // CHECK IF MEMBER HAS CHILDREN
    $cntRes = $conn->query("SELECT COUNT(*) as cnt FROM members WHERE parent_id = $member_id")->fetch_assoc();
    $childCount = (int)($cntRes['cnt'] ?? 0);
    if ($childCount > 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => "ئەم کەسە ناکرێت بسڕدرێتەوە چونکە ($childCount) منداڵی هەیە! سەرەتا دەبێت منداڵەکانی بسڕدرێنەوە."]);
        exit;
    }

    // Safe deletion of childless leaf member
    $conn->query("DELETE FROM congress_members WHERE member_id = $member_id");
    $conn->query("DELETE FROM candidates WHERE member_id = $member_id");
    $conn->query("DELETE FROM member_documents WHERE member_id = $member_id");
    $conn->query("DELETE FROM members WHERE id = $member_id");

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'کەسەکە بە سەرکەوتوویی سڕایەوە']);
    exit;
}


require_once 'includes/layout.php';

// Dynamically get the first family if no ID is provided, instead of hardcoding 26
$family_id = isset($_GET['family_id']) ? (int)$_GET['family_id'] : 0;
if ($family_id === 0) {
    $first_f = $conn->query("SELECT id FROM families ORDER BY id ASC LIMIT 1");
    if ($f_row = $first_f->fetch_assoc()) {
        $family_id = (int)$f_row['id'];
    } else {
        $family_id = 1; // absolute fallback
    }
}

// Fetch current family name
$current_family_name = 'سادات';
$f_check = $conn->query("SELECT family_name FROM families WHERE id = $family_id");
if ($f_row = $f_check->fetch_assoc()) {
    $current_family_name = $f_row['family_name'];
}

// 1. Fetch all members belonging to this family
$membersById = [];
$res = $conn->query("
    SELECT m.id, m.full_name, m.parent_id, m.member_code, m.member_status, m.is_official_member, m.is_family_head, m.profile_image, 
           m.father_name as direct_father_name, m.grandfather_name as direct_grandfather_name, m.great_grandfather_name as direct_great_grandfather_name,
           fat.full_name as father_name, fat.id as father_id, 
           gfat.full_name as grandfather_name, gfat.id as grandfather_id 
    FROM members m 
    LEFT JOIN members fat ON m.parent_id = fat.id 
    LEFT JOIN members gfat ON fat.parent_id = gfat.id 
    WHERE m.family_id = $family_id
");
while($row = $res->fetch_assoc()) {
    $membersById[(int)$row['id']] = $row;
}

// 2. Trace upwards for any ancestor chain outside this family until Shaho (ID 1)
$ancestorIdsToFetch = [];
foreach ($membersById as $id => $row) {
    $currP = (int)$row['parent_id'];
    $visited = [];
    while ($currP > 0 && !isset($membersById[$currP]) && !isset($visited[$currP])) {
        $visited[$currP] = true;
        $ancestorIdsToFetch[$currP] = true;
        $pRow = $conn->query("SELECT id, parent_id FROM members WHERE id = $currP")->fetch_assoc();
        if ($pRow) {
            if ($currP == 1) break; // Reached Pirkhidri Shaho
            $currP = (int)$pRow['parent_id'];
        } else {
            break;
        }
    }
}

// Fetch ancestor details
if (!empty($ancestorIdsToFetch)) {
    $idsStr = implode(',', array_keys($ancestorIdsToFetch));
    $aRes = $conn->query("
        SELECT m.id, m.full_name, m.parent_id, m.member_code, m.member_status, m.is_official_member, m.is_family_head, m.profile_image, 
               m.father_name as direct_father_name, m.grandfather_name as direct_grandfather_name, m.great_grandfather_name as direct_great_grandfather_name,
               fat.full_name as father_name, fat.id as father_id, 
               gfat.full_name as grandfather_name, gfat.id as grandfather_id 
        FROM members m 
        LEFT JOIN members fat ON m.parent_id = fat.id 
        LEFT JOIN members gfat ON fat.parent_id = gfat.id 
        WHERE m.id IN ($idsStr)
    ");
    while ($row = $aRes->fetch_assoc()) {
        $membersById[(int)$row['id']] = $row;
    }
}

// 3. Always include Pirkhidri Shaho (ID 1) as the universal root of all families
if (!isset($membersById[1])) {
    $shahoRes = $conn->query("
        SELECT m.id, m.full_name, 0 as parent_id, m.member_code, m.member_status, m.is_official_member, 0 as is_family_head, m.profile_image, 
               '' as direct_father_name, '' as direct_grandfather_name, '' as direct_great_grandfather_name,
               '' as father_name, null as father_id, 
               '' as grandfather_name, null as grandfather_id 
        FROM members m 
        WHERE m.id = 1
    ");
    if ($shahoRow = $shahoRes->fetch_assoc()) {
        $membersById[1] = $shahoRow;
    }
}

// 4. Find the Shaho ID (either 1 or name containing Pirkhidri Shaho)
$shahoId = 1;
foreach ($membersById as $id => $row) {
    $norm = normalizeName($row['full_name']);
    if (mb_strpos($norm, 'بیرخضرالشاهو') !== false || mb_strpos($norm, 'پیرخضرالشاهو') !== false) {
        $shahoId = $id;
        break;
    }
}

// 5. Build membersData - ONLY Shaho is root!
$membersData = [];

// Pre-index single and father names for all members in dataset
$parsedNames = [];
foreach ($membersById as $id => $row) {
    $isShaho = ($id === $shahoId);
    $parsedNames[$id] = extractSingleAndFatherName($row['full_name'], $isShaho);
}

// Pre-assign parents with candidate smart matching
$assignedParents = [];
foreach ($membersById as $id => $row) {
    if ($id === $shahoId) {
        $assignedParents[$id] = 0;
        continue;
    }
    $pId = (int)$row['parent_id'];
    if ($pId === 0 || !isset($membersById[$pId])) {
        $parsed = smartTokenizeName($row['full_name']);
        $fName = $parsed['chain'][0] ?? ($row['father_name'] ?? '');
        $gName = $parsed['chain'][1] ?? ($row['grandfather_name'] ?? '');
        
        if (!empty($fName) && $fName !== 'نادیار') {
            $fNorm = normalizeName($fName);
            $gNorm = !empty($gName) ? normalizeName($gName) : '';
            
            $matches = [];
            foreach ($membersById as $candId => $candRow) {
                if ($candId === $id || $candId === $shahoId) continue;
                $candSingle = $parsedNames[$candId]['single'];
                $candSingleNorm = normalizeName($candSingle);
                
                if ($candSingleNorm === $fNorm) {
                    $score = 1;
                    $candFNorm = normalizeName($parsedNames[$candId]['father']);
                    if (!empty($gNorm) && !empty($candFNorm) && $candFNorm === $gNorm) {
                        $score = 2; // Exact grandfather match
                    }
                    $matches[] = ['id' => $candId, 'score' => $score];
                }
            }
            if (!empty($matches)) {
                usort($matches, function($a, $b) { return $b['score'] - $a['score']; });
                $pId = $matches[0]['id'];
            }
        }
    }
    $assignedParents[$id] = $pId;
}

// Function to check if a node traces back to Pirkhidri Shaho
if (!function_exists('treeNodeReachesShaho')) {
    function treeNodeReachesShaho($startId, $shahoId, $assignedParents) {
        $curr = $startId;
        $visited = [];
        while ($curr > 0 && !isset($visited[$curr])) {
            $visited[$curr] = true;
            if ($curr === $shahoId) return true;
            $curr = isset($assignedParents[$curr]) ? (int)$assignedParents[$curr] : 0;
        }
        return false;
    }
}

// Resolve final parent IDs: if a branch does not connect to Shaho, connect its root to 'unlinked_gap'
$hasUnlinkedGap = false;
$finalParentMap = [];
foreach ($membersById as $id => $row) {
    if ($id === $shahoId) {
        $finalParentMap[$id] = null;
        continue;
    }
    $pId = $assignedParents[$id];
    if ($pId > 0 && isset($membersById[$pId]) && treeNodeReachesShaho($pId, $shahoId, $assignedParents)) {
        $finalParentMap[$id] = (string)$pId;
    } else {
        // If parent exists in the local unlinked branch, keep local hierarchy
        if ($pId > 0 && isset($membersById[$pId])) {
            $finalParentMap[$id] = (string)$pId;
        } else {
            // Root of unlinked branch connects to intermediate 'unlinked_gap' card
            $finalParentMap[$id] = 'unlinked_gap';
            $hasUnlinkedGap = true;
        }
    }
}

// Build membersData
foreach ($membersById as $id => $row) {
    $isShaho = ($id === $shahoId);
    $parentId = $finalParentMap[$id];
    $pIdInt = ($parentId !== null && $parentId !== 'unlinked_gap') ? (int)$parentId : 0;

    $displayName = $parsedNames[$id]['single'] ?: $row['full_name'];

    // Father name for display: prefer parent's core name in tree
    $displayFather = '';
    if ($pIdInt > 0 && isset($membersById[$pIdInt]) && $pIdInt !== $shahoId) {
        $displayFather = $parsedNames[$pIdInt]['core'] ?: $parsedNames[$pIdInt]['single'];
    } elseif (!empty($parsedNames[$id]['father'])) {
        $displayFather = $parsedNames[$id]['father'];
    } elseif (!empty($row['father_name']) && $row['father_name'] !== 'نادیار') {
        $fExt = extractSingleAndFatherName($row['father_name'], false);
        $displayFather = $fExt['core'] ?: $fExt['single'];
    }

    $membersData[] = [
        'id' => (string)$id,
        'parentId' => $parentId,
        'name' => $row['full_name'], // Full name for sidebar and search
        'display_name' => $displayName, // Single name for card
        'display_father' => $displayFather, // Father for card
        'code' => $row['member_code'] ?: (string)$id,
        'status' => $row['member_status'] ?: 'inactive',
        'is_official_member' => (int)($row['is_official_member'] ?? 0),
        'is_family_head' => (int)($row['is_family_head'] ?? 0),
        'father_name' => !empty($row['direct_father_name']) ? $row['direct_father_name'] : ($displayFather ?: ($row['father_name'] ?: 'نادیار')),
        'father_id' => $row['father_id'] ? (string)$row['father_id'] : null,
        'grandfather_name' => !empty($row['direct_grandfather_name']) ? $row['direct_grandfather_name'] : ($row['grandfather_name'] ?: 'نادیار'),
        'grandfather_id' => $row['grandfather_id'] ? (string)$row['grandfather_id'] : null,
        'great_grandfather_name' => $row['direct_great_grandfather_name'] ?? '',
        'parent_row_name' => $row['father_name'] ?? '',
        'grand_row_name' => $row['grandfather_name'] ?? '',
        'image' => $row['profile_image'] ?: '',
        'is_root' => $isShaho,
        'is_shaho' => $isShaho,
        'is_unlinked_gap' => false
    ];
}

// If there are unlinked members, inject the intermediate 'unlinked_gap' card between Shaho and them
if ($hasUnlinkedGap) {
    $membersData[] = [
        'id' => 'unlinked_gap',
        'parentId' => (string)$shahoId,
        'name' => 'زنجیرەی نادیار',
        'display_name' => 'زنجیرەی نادیار',
        'display_father' => 'پیرخضر الشاهو',
        'code' => 'نادیار',
        'status' => 'gap',
        'is_official_member' => 0,
        'is_family_head' => 0,
        'father_name' => 'السيد محمد (پیرخضر الشاهو)',
        'father_id' => (string)$shahoId,
        'grandfather_name' => 'بنچینەی سادات',
        'grandfather_id' => null,
        'great_grandfather_name' => '',
        'parent_row_name' => '',
        'grand_row_name' => '',
        'image' => '',
        'is_root' => false,
        'is_shaho' => false,
        'is_unlinked_gap' => true,
        'gap_message' => 'زنجیرەی ئەم بنەماڵەیە لە داتابەیسدا تۆمار نەکراوە'
    ];
}

$families = [];
$fres = $conn->query("SELECT id, family_name FROM families");
while($f = $fres->fetch_assoc()) $families[] = $f;

$page_title = 'داری نەسەبی پێشکەوتوو';
    $active_nav = 'lineage_tree';
    ?>
    <!-- Dependencies -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    
    <!-- FontAwesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <style>
        /* Custom Select2 Styling to match theme */
        .select2-container--default .select2-selection--single {
            border-radius: 50px;
            height: 45px;
            border: 1px solid #ddd;
            padding-top: 8px;
            padding-right: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 43px;
            left: 10px;
            right: auto;
        }
        .select2-container--open {
            z-index: 99999 !important;
        }
        .select2-dropdown {
            z-index: 99999 !important;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
            margin-top: 6px;
            overflow: hidden;
            padding: 8px;
            font-family: 'Rabar005', 'Rabar022', sans-serif;
            background: #fff;
        }
        .select2-search--dropdown {
            padding: 4px;
        }
        .select2-search--dropdown .select2-search__field {
            border-radius: 10px;
            padding: 10px 14px;
            border: 1.5px solid #cbd5e1;
            font-family: 'Rabar005', 'Rabar022', sans-serif;
            font-size: 0.92rem;
            outline: none;
            direction: rtl;
            width: 100%;
            box-sizing: border-box;
        }
        .select2-search--dropdown .select2-search__field:focus {
            border-color: var(--tree-maroon);
            box-shadow: 0 0 0 3px rgba(114, 0, 0, 0.1);
        }
        .select2-results__option {
            padding: 10px 14px;
            border-radius: 8px;
            margin-bottom: 2px;
            font-family: 'Rabar005', 'Rabar022', sans-serif;
            font-size: 0.92rem;
            direction: rtl;
            text-align: right;
        }
        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: var(--tree-maroon) !important;
            color: #fff !important;
        }

        /* Modal specific select2 single container */
        .edit-parent-select-wrapper .select2-container--default .select2-selection--single {
            border-radius: 12px !important;
            height: 48px !important;
            border: 1.5px solid #cbd5e1 !important;
            padding-top: 10px !important;
            padding-right: 15px !important;
            box-shadow: none !important;
            background: #fff !important;
        }
        .edit-parent-select-wrapper .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: #1e293b !important;
            font-weight: 600 !important;
            font-size: 0.95rem !important;
            line-height: 26px !important;
            font-family: 'Rabar005', 'Rabar022', sans-serif !important;
            text-align: right !important;
        }
        .edit-parent-select-wrapper .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 46px !important;
            left: 12px !important;
            right: auto !important;
        }
        .edit-parent-select-wrapper .select2-container--default.select2-container--focus .select2-selection--single {
            border-color: var(--tree-maroon) !important;
            box-shadow: 0 0 0 3px rgba(114, 0, 0, 0.1) !important;
        }
    </style>

<!-- Load D3 and Org Chart Core -->
<script src="https://d3js.org/d3.v7.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/d3-org-chart@3.1.1"></script>
<script src="https://cdn.jsdelivr.net/npm/d3-flextree@2.1.2/build/d3-flextree.js"></script>

<style>
    :root {
        --tree-maroon: var(--maroon, #720000);
        --tree-gold: #c5a059;
    }

    .main-wrapper {
        display: flex;
        flex-direction: column;
        height: calc(100vh - 140px);
        background: #fbfbfb;
        border-radius: 20px;
        overflow: hidden;
        border: 1px solid #ddd;
        position: relative;
    }

    /* TREE TOP HEADER BAR (HORIZONTAL ACTIONS) */
    .tree-header-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 8px 18px;
        background: #ffffff;
        border-bottom: 1px solid #e2e8f0;
        box-shadow: 0 2px 10px rgba(0,0,0,0.03);
        z-index: 150;
        flex-wrap: wrap;
        min-height: 56px;
    }

    .tree-header-right {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .tree-header-left {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .header-action-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 12px;
        font-size: 0.82rem;
        font-weight: 700;
        border-radius: 10px;
        text-decoration: none;
        cursor: pointer;
        transition: all 0.2s ease;
        white-space: nowrap;
        border: 1px solid transparent;
        line-height: 1.2;
    }
    .header-action-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 10px rgba(0,0,0,0.12);
    }

    .tree-workspace {
        display: flex;
        flex: 1;
        min-height: 0;
        position: relative;
        overflow: hidden;
    }

    /* SIDEBAR STYLING */
    .info-sidebar {
        width: 380px;
        background: #fff;
        border-left: 1px solid #eee;
        display: flex;
        flex-direction: column;
        z-index: 100;
        box-shadow: -5px 0 25px rgba(0,0,0,0.02);
        transition: transform 0.3s;
    }

    .sidebar-header {
        padding: 40px 20px 20px;
        background: linear-gradient(135deg, var(--tree-maroon), #4a0000);
        color: #fff;
        text-align: center;
        position: relative;
    }

    .p-icon {
        width: 80px; height: 80px;
        border-radius: 50%;
        background: rgba(255,255,255,0.2);
        margin: 0 auto 15px;
        display: flex; align-items: center; justify-content: center;
        font-size: 30px;
        border: 2px solid var(--tree-gold);
    }

    /* TABS STYLING */
    .sidebar-tabs {
        display: flex;
        background: #f8f9fa;
        border-bottom: 1px solid #eee;
    }

    .tab-btn {
        flex: 1;
        padding: 15px 5px;
        border: none;
        background: none;
        font-family: 'Rabar005', 'Rabar022', sans-serif;
        font-size: 0.85rem;
        color: #666;
        cursor: pointer;
        transition: 0.3s;
        border-bottom: 3px solid transparent;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 5px;
    }

    .tab-btn.active {
        color: var(--tree-maroon);
        border-bottom-color: var(--tree-gold);
        background: #fff;
    }

    .tab-btn .count {
        font-size: 0.75rem;
        opacity: 0.7;
    }

    .side-content {
        flex: 1;
        overflow-y: auto;
        padding: 20px;
    }

    .tab-panel {
        display: none;
    }

    .tab-panel.active {
        display: block;
    }

    .m-card {
        padding: 12px 15px;
        background: #fff;
        border: 1px solid #f0f0f0;
        border-radius: 12px;
        margin-bottom: 10px;
        font-size: 0.9rem;
        cursor: pointer;
        transition: 0.3s;
        display: flex; align-items: center; justify-content: space-between;
        box-shadow: 0 2px 5px rgba(0,0,0,0.02);
    }

    .m-card:hover {
        border-color: var(--tree-maroon);
        background: #fffafa;
        transform: scale(1.02);
    }

    .m-card .sub-text {
        font-size: 0.7rem;
        color: #999;
    }

    /* CHART CANVAS */
    .chart-container {
        flex: 1;
        height: 100%;
        cursor: grab;
        position: relative;
    }

    /* NODE CARD STYLING */
    .node-wrapper {
        box-sizing: border-box;
        border-radius: 16px;
        position: relative;
        cursor: pointer;
        transition: all 0.22s cubic-bezier(0.4, 0, 0.2, 1);
        user-select: none;
    }
    .node-wrapper:hover {
        transform: translateY(-4px);
    }
    .root-node {
        background: linear-gradient(135deg, #2d0505 0%, #5a0202 55%, #380303 100%) !important;
        border: 2.5px solid #d4af37 !important;
        box-shadow: 0 10px 30px rgba(90, 2, 2, 0.4) !important;
    }
    .root-node:hover {
        border-color: #ffd700 !important;
        box-shadow: 0 14px 35px rgba(212, 175, 55, 0.5) !important;
    }
    .standard-node {
        background: #ffffff !important;
        border: 1.8px solid #cbd5e1 !important;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05) !important;
    }
    .standard-node:hover {
        border-color: var(--tree-gold) !important;
        box-shadow: 0 10px 25px rgba(197, 160, 89, 0.25) !important;
    }
    .unlinked-gap-node {
        background: linear-gradient(135deg, #fffdf5 0%, #fef3c7 100%) !important;
        border: 2px dashed #f59e0b !important;
        box-shadow: 0 6px 18px rgba(245, 158, 11, 0.15) !important;
    }
    .unlinked-gap-node:hover {
        border-color: #d97706 !important;
        box-shadow: 0 10px 25px rgba(217, 119, 6, 0.25) !important;
    }

    /* FLOATING CONTROLS TOOLBAR */
    .tree-controls-bar {
        position: absolute;
        bottom: 25px;
        right: 25px;
        z-index: 100;
        background: rgba(255, 255, 255, 0.96);
        backdrop-filter: blur(12px);
        border: 1.5px solid #e2e8f0;
        padding: 6px 12px;
        border-radius: 40px;
        display: flex;
        align-items: center;
        gap: 6px;
        box-shadow: 0 10px 35px rgba(0,0,0,0.15);
    }
    .tree-ctrl-btn {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        border: none;
        background: transparent;
        color: #334155;
        font-size: 0.95rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
    }
    .tree-ctrl-btn:hover {
        background: #f1f5f9;
        color: var(--tree-maroon);
        transform: translateY(-2px);
    }
    .tree-ctrl-divider {
        width: 1px;
        height: 22px;
        background: #e2e8f0;
        margin: 0 4px;
    }
</style>

<div class="main-wrapper">
    <!-- TREE TOP HEADER BAR (HORIZONTAL ACTIONS & SELECTORS) -->
    <header class="tree-header-bar">
        <!-- RIGHT: FAMILY SELECTOR & MEMBER SEARCH (RTL) -->
        <div class="tree-header-right">
            <!-- Family Selector -->
            <div style="display: flex; align-items: center; gap: 8px; background: #f8fafc; padding: 3px 12px; border-radius: 20px; border: 1px solid #e2e8f0;">
                <span style="font-size: 0.85rem; color: #64748b; font-weight: bold;">🏛️ بنەماڵە:</span>
                <select class="family-select" style="width: 220px;" onchange="window.location.href='?family_id=' + this.value">
                    <?php foreach($families as $f): ?>
                        <option value="<?php echo $f['id']; ?>" <?php echo $family_id == $f['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($f['family_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Quick Jump Search -->
            <div style="position: relative; display: flex; align-items: center; background: #f8fafc; padding: 3px 14px; border-radius: 20px; border: 1px solid #e2e8f0;">
                <i class="fas fa-search" style="color: #64748b; font-size: 0.85rem; margin-left: 8px;"></i>
                <input type="text" id="treeSearchInput" placeholder="گەڕان بۆ سەر کەسێک..." style="border: none; outline: none; background: transparent; padding: 5px 4px; font-family: 'Rabar005', 'Rabar022', sans-serif; font-size: 0.85rem; width: 170px;" oninput="onTreeSearch(this.value)">
                <div id="treeSearchResults" style="display: none; position: absolute; top: 42px; right: 0; width: 270px; max-height: 280px; overflow-y: auto; background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); z-index: 300;"></div>
            </div>
        </div>

        <!-- LEFT: HORIZONTAL ACTION BUTTONS FOR SELECTED MEMBER -->
        <div class="tree-header-left" id="header-member-actions">
            <!-- Selected Member Tag -->
            <div id="header-selected-tag" style="display: none; align-items: center; gap: 6px; background: #fffdf5; border: 1.5px solid var(--tree-gold); padding: 5px 12px; border-radius: 10px; font-weight: bold; color: var(--tree-maroon); font-size: 0.85rem;">
                <i class="fas fa-user-check" style="color: var(--tree-gold);"></i>
                <span id="header-selected-name">ناوی کەسەکە</span>
            </div>

            <div id="header-no-selection" style="display: flex; align-items: center; gap: 6px; color: #94a3b8; font-size: 0.83rem;">
                <i class="fas fa-mouse-pointer" style="color: var(--tree-gold);"></i>
                <span>کلیک لەسەر هەر کەسێک بکە لە دارەکە بۆ پیشاندانی کردارەکان</span>
            </div>

            <button id="toggle-head-btn" class="header-action-btn" style="background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff; display: none;" onclick="toggleFamilyHead()">
                <i class="fas fa-crown"></i> <span id="toggle-head-text">باوکی گەورە</span>
            </button>
            <a id="make-official-btn" href="#" class="header-action-btn" style="background: #eff6ff; border: 1.5px solid #3b82f6; color: #1d4ed8; display: none;">
                <i class="fas fa-id-card"></i> <span>ئەندامی فەرمی</span>
            </a>
            <a id="kinship-member-btn" href="#" class="header-action-btn" style="background: #1a5653; color: #fff; display: none;">
                <i class="fas fa-network-wired"></i> <span>خزمایەتی</span>
            </a>
            <a id="descendants-art-tree-btn" href="#" target="_blank" class="header-action-btn" style="background: linear-gradient(135deg, #15803d, #166534); color: #fff; display: none;">
                <i class="fas fa-tree"></i> <span>🌿 شەجەرە</span>
            </a>
            <a id="cert-cert-btn" href="#" target="_blank" class="header-action-btn" style="background: #720000; color: #fff; display: none;">
                <i class="fas fa-certificate"></i> <span>شەهادەی نەسەب</span>
            </a>
            <a id="cert-comm-btn" href="#" target="_blank" class="header-action-btn" style="background: #0b5e28; color: #fff; display: none;">
                <i class="fas fa-file-contract"></i> <span>کڵێشەی فەرمی</span>
            </a>
            <button id="edit-member-btn" class="header-action-btn" style="background: #fff; border: 1.5px solid #cbd5e1; color: #334155; display: none;" onclick="openEditLineageModal()">
                <i class="fas fa-user-edit" style="color: #0284c7;"></i> <span>دەستکاری و باوک</span>
            </button>
            <button id="add-son-btn" class="header-action-btn" style="background: var(--tree-gold); color: #fff; display: none;" onclick="openQuickAdd()">
                <i class="fas fa-user-plus"></i> <span>زیادکردنی کوڕ</span>
            </button>
            <button id="delete-member-btn" class="header-action-btn" style="background: #fff1f2; border: 1.5px solid #fecdd3; color: #e11d48; display: none;" onclick="deleteCurrentMember()">
                <i class="fas fa-trash-alt"></i> <span>سڕینەوە</span>
            </button>
        </div>
    </header>

    <!-- WORKSPACE BODY (SIDEBAR + TREE CANVAS) -->
    <div class="tree-workspace">
        <!-- INFO SIDEBAR (CLEAN PROFILE & RELATIVES) -->
        <div class="info-sidebar">
            <div class="sidebar-header">
                <div class="p-icon" id="s-icon"><i class="fas fa-user-shield"></i></div>
                <h4 id="s-name" style="margin: 0; font-size: 1.15rem; font-weight: 800;">بۆ بینینی زانیاری کلیک بکە</h4>
                <div id="s-badge-container" style="margin-top: 6px;">
                    <span id="s-status-badge" style="display: inline-flex; align-items: center; gap: 4px; background: rgba(255,255,255,0.2); color: #fff; font-size: 0.72rem; padding: 2px 10px; border-radius: 20px;">📜 دۆخ</span>
                </div>
                <p id="s-desc" style="font-size: 0.78rem; opacity: 0.85; margin-top: 5px;">ئەندامی بنەماڵەی <?php echo htmlspecialchars($current_family_name); ?></p>
            </div>

            <!-- LINEAGE SUMMARY BOX -->
            <div id="s-lineage-box" style="padding: 14px 18px; background: #fffdf7; border-bottom: 1px solid #faecc5; font-size: 0.85rem; display: none;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <span style="color: #64748b;">👤 باوک:</span>
                    <span id="s-father-name" style="font-weight: bold; color: #1e293b;"></span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <span style="color: #64748b;">👴 باپیری:</span>
                    <span id="s-grand-name" style="font-weight: bold; color: #1e293b;"></span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="color: #64748b;">🔢 کۆدی کەسی:</span>
                    <span id="s-member-code" style="font-weight: bold; color: var(--tree-maroon);"></span>
                </div>
            </div>

            <div class="sidebar-tabs">
                <button class="tab-btn active" onclick="switchTab('children')">
                    <span>کوڕەکان</span>
                    <span class="count" id="count-children">(0)</span>
                </button>
                <button class="tab-btn" onclick="switchTab('grand-children')">
                    <span>نەوەکان</span>
                    <span class="count" id="count-grand">(0)</span>
                </button>
                <button class="tab-btn" onclick="switchTab('siblings')">
                    <span>براکان</span>
                    <span class="count" id="count-siblings">(0)</span>
                </button>
            </div>

            <div class="side-content">
                <div id="panel-children" class="tab-panel active"></div>
                <div id="panel-grand-children" class="tab-panel"></div>
                <div id="panel-siblings" class="tab-panel"></div>
            </div>
        </div>

        <!-- MAIN CHART AREA -->
        <div class="chart-container" id="chart-area">
            <!-- FLOATING CONTROLS TOOLBAR -->
            <div class="tree-controls-bar">
                <button class="tree-ctrl-btn" onclick="chart.zoomIn()" title="گەورەکردن (Zoom In)">
                    <i class="fas fa-plus"></i>
                </button>
                <button class="tree-ctrl-btn" onclick="chart.zoomOut()" title="بچووککردن (Zoom Out)">
                    <i class="fas fa-minus"></i>
                </button>
                <button class="tree-ctrl-btn" onclick="resetZoom(0.85)" title="قەبارەی بنەڕەتی ١٠٠٪ (Reset Zoom)" style="font-weight: 800; font-size: 0.72rem; color: var(--tree-maroon);">
                    100%
                </button>
                <div class="tree-ctrl-divider"></div>
                <button class="tree-ctrl-btn" id="compactToggleBtn" onclick="toggleCompactMode()" title="گۆڕینی دۆخی چڕ / فراوان (Compact Mode)">
                    <i class="fas fa-layer-group"></i>
                </button>
                <button class="tree-ctrl-btn" onclick="fitTreeSmart()" title="هاوسەنگکردن (Smart Fit)">
                    <i class="fas fa-expand"></i>
                </button>
                <button class="tree-ctrl-btn" onclick="goToRoot()" title="چوونە سەر باوکی گەورە (ڕەگ)">
                    <i class="fas fa-crown" style="color: #d4af37;"></i>
                </button>
                <button class="tree-ctrl-btn" onclick="goToLeaves()" title="چوونە سەر کۆتا نەوەکان">
                    <i class="fas fa-leaf" style="color: #10b981;"></i>
                </button>
                <div class="tree-ctrl-divider"></div>
                <button class="tree-ctrl-btn" onclick="chart.expandAll(); fitTreeSmart();" title="کردنەوەی هەموو لقەکان">
                    <i class="fas fa-folder-open"></i>
                </button>
                <button class="tree-ctrl-btn" onclick="chart.collapseAll().render(); resetZoom(0.85);" title="داخستنی هەموو لقەکان">
                    <i class="fas fa-compress-alt"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- QUICK ADD MODAL -->
<div id="quickAddModal" style="display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); align-items: center; justify-content: center; backdrop-filter: blur(5px);">
    <div style="background: #fff; width: 500px; border-radius: 25px; overflow: hidden; box-shadow: 0 25px 60px rgba(0,0,0,0.3); animation: modalIn 0.4s cubic-bezier(0.4, 0, 0.2, 1);">
        <div style="padding: 25px; background: var(--tree-maroon); color: #fff; display: flex; justify-content: space-between; align-items: center;">
            <h4 style="margin: 0; font-family: 'Rabar013', serif;"><i class="fas fa-user-plus"></i> تۆمارکردنی منداڵی نوێ</h4>
            <i class="fas fa-times" style="cursor: pointer; font-size: 20px;" onclick="closeQuickAdd()"></i>
        </div>
        <div style="padding: 30px;">
            <p style="font-size: 0.9rem; color: #666; margin-bottom: 15px; font-weight: bold;">
                باوک: <span id="modal-parent-name" style="color: var(--tree-maroon);"></span>
            </p>
            <div style="background: #fff9e6; border-right: 4px solid var(--tree-gold); padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 0.85rem; color: #856404;">
                ناوەکان بنووسە و بە (،) یان (,) یان (-) جیایان بکەرەوە.
            </div>
            <textarea id="quick-names" class="lib-input w-100" style="height: 120px; border-radius: 15px; padding: 15px; font-size: 1.1rem; border: 1px solid #ddd;" placeholder="بۆ نموونە: سامان ، هێمن ، عەلی"></textarea>
            
            <div style="margin-top: 30px; display: flex; gap: 15px;">
                <button class="lib-btn w-100" style="background: var(--tree-maroon); color: #fff; border-radius: 12px; padding: 15px; border: none; font-weight: bold;" onclick="submitQuickAdd()">
                    <i class="fas fa-save"></i> پاشەکەوتکردنی هەموویان
                </button>
                <button class="lib-btn w-100" style="background: #f0f0f0; color: #555; border-radius: 12px; padding: 15px; border: none;" onclick="closeQuickAdd()">
                    پاشگەزبوونەوە
                </button>
            </div>
        </div>
    </div>
</div>

<!-- EDIT LINEAGE & PARENT MODAL -->
<div id="editLineageModal" style="display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.65); align-items: center; justify-content: center; backdrop-filter: blur(6px);">
    <div style="background: #fff; width: 520px; max-width: 95vw; border-radius: 25px; overflow: visible; box-shadow: 0 25px 60px rgba(0,0,0,0.35); animation: modalIn 0.35s cubic-bezier(0.4, 0, 0.2, 1); position: relative;">
        <div style="padding: 22px 25px; background: linear-gradient(135deg, #1e293b, #0f172a); color: #fff; display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--tree-gold); border-top-left-radius: 25px; border-top-right-radius: 25px;">
            <h4 style="margin: 0; font-family: 'Rabar013', serif; font-size: 1.15rem; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-user-edit" style="color: var(--tree-gold);"></i> دەستکاری و بەستنەوە بە باوک
            </h4>
            <i class="fas fa-times" style="cursor: pointer; font-size: 20px; color: #94a3b8; transition: 0.2s;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#94a3b8'" onclick="closeEditLineageModal()"></i>
        </div>
        <div style="padding: 25px 30px;">
            <input type="hidden" id="edit-member-id" value="">
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; font-weight: bold; color: #334155; margin-bottom: 8px; font-size: 0.9rem;">
                    👤 ناوی کەسەکە:
                </label>
                <input type="text" id="edit-member-name" class="lib-input w-100" style="padding: 12px 15px; border-radius: 12px; border: 1.5px solid #cbd5e1; font-size: 1rem; width: 100%; box-sizing: border-box;" placeholder="ناوی تەواوی کەسەکە بنووسە...">
            </div>

            <div style="margin-bottom: 25px;">
                <label style="display: block; font-weight: bold; color: #334155; margin-bottom: 6px; font-size: 0.9rem;">
                    🔗 باوکی کەسەکە دیاری بکە:
                </label>
                <div style="font-size: 0.8rem; color: #64748b; margin-bottom: 8px;">
                    دەتوانیت لەناو سێرچ بۆکسەکە بگەڕێیت و هەر باوکێک لەم بنەماڵەیە یان باوەگەورە (پیرخدری شاهۆ) دیاری بکەیت:
                </div>
                <div class="edit-parent-select-wrapper" style="position: relative;">
                    <select id="edit-parent-select" style="width: 100%;">
                        <!-- Populated dynamically via JS -->
                    </select>
                </div>
            </div>

            <div style="display: flex; gap: 12px;">
                <button type="button" class="lib-btn w-100" style="background: var(--tree-maroon); color: #fff; border-radius: 12px; padding: 13px; border: none; font-weight: bold; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 4px 12px rgba(114,0,0,0.25);" onclick="submitEditLineage()">
                    <i class="fas fa-save"></i> پاشەکەوتکردن
                </button>
                <button type="button" class="lib-btn" style="background: #f1f5f9; color: #475569; border-radius: 12px; padding: 13px 20px; border: none; font-weight: bold; cursor: pointer;" onclick="closeEditLineageModal()">
                    پاشگەزبوونەوە
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    @keyframes modalIn {
        from { transform: translateY(30px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
</style>

<script>
    let chart;
    let selectedId = null;
    let isCompactMode = true;
    const membersData = <?php echo json_encode($membersData); ?>;
    const familyId = <?php echo $family_id; ?>;

    // Robust Kurdish & Arabic text normalizer for search
    function normalizeKurdishArabic(str) {
        if (!str) return '';
        return str
            .toString()
            .toLowerCase()
            // Remove Arabic diacritics / Harakat / Tatweel
            .replace(/[\u064B-\u065F\u0640\u0670]/g, '')
            // Normalize Alef variants
            .replace(/[آأإٱ]/g, 'ا')
            // Normalize Yeh / Alif Maqsura / Kurdish Ye variants
            .replace(/[يىیێئ]/g, 'ی')
            // Normalize Heh / Teh Marbuta / Kurdish Ae variants
            .replace(/[هةھە]/g, 'ه')
            // Normalize Kaf
            .replace(/[ك]/g, 'ک')
            // Normalize Vav variants
            .replace(/[ۆۇۉ]/g, 'و')
            // Normalize Re variants
            .replace(/[ڕ]/g, 'ر')
            // Normalize Lam variants
            .replace(/[ڵ]/g, 'ل')
            // Replace punctuation/brackets with space
            .replace(/[()[\]{}.,_\-–—/\\:;#]/g, ' ')
            // Normalize whitespace
            .replace(/\s+/g, ' ')
            .trim();
    }

    function cleanHonorific(name) {
        if (!name) return '';
        return name
            .replace(/^(?:السید|السيد|سید|سەید|ملا|مەلا|شێخ|شیخ|الحاج|حاجی|حاج|العلامة|د\.|ئەندام)\s+/u, '')
            .trim();
    }

    // Constructs full lineage chain (e.g., 'السيد عبدالله مصطفى نوري محمد')
    function buildMemberLineageChain(m) {
        if (!m) return '';
        if (m.is_unlinked_gap) return 'زنجیرەی نادیار';
        let tokens = [];

        // Primary title/name
        let primary = (m.display_name || m.name || '').trim();
        tokens.push(primary);

        // Father resolution
        let father = '';
        if (m.parentId && m.parentId !== '1' && m.parentId !== 'unlinked_gap') {
            const pNode = membersData.find(x => x.id.toString() === m.parentId.toString() && !x.is_unlinked_gap);
            if (pNode) {
                father = (pNode.display_name || pNode.name || '').trim();
            }
        }
        if (!father && m.father_name && m.father_name !== 'نادیار') {
            father = m.father_name.trim();
        }
        let cleanF = cleanHonorific(father);
        if (cleanF && !primary.includes(cleanF)) {
            tokens.push(cleanF);
        }

        // Grandfather resolution
        let grand = '';
        if (m.parentId && m.parentId !== '1' && m.parentId !== 'unlinked_gap') {
            const pNode = membersData.find(x => x.id.toString() === m.parentId.toString() && !x.is_unlinked_gap);
            if (pNode && pNode.parentId && pNode.parentId !== '1' && pNode.parentId !== 'unlinked_gap') {
                const gNode = membersData.find(x => x.id.toString() === pNode.parentId.toString() && !x.is_unlinked_gap);
                if (gNode) {
                    grand = (gNode.display_name || gNode.name || '').trim();
                }
            }
        }
        if (!grand && m.grandfather_name && m.grandfather_name !== 'نادیار') {
            grand = m.grandfather_name.trim();
        }
        let cleanG = cleanHonorific(grand);
        if (cleanG && !tokens.join(' ').includes(cleanG)) {
            tokens.push(cleanG);
        }

        // Great-grandfather resolution
        let cleanGG = cleanHonorific(m.great_grandfather_name || '');
        if (cleanGG && !tokens.join(' ').includes(cleanGG)) {
            tokens.push(cleanGG);
        }

        return tokens.filter(Boolean).join(' ');
    }

    // Multi-term Kurdish/Arabic matcher for Select2
    function select2KurdishMatcher(params, data) {
        if (!params.term || $.trim(params.term) === '') {
            return data;
        }
        if (!data.text) {
            return null;
        }

        const extraSearch = data.element ? (data.element.dataset.search || '') : '';
        const fullText = normalizeKurdishArabic(data.text + ' ' + extraSearch);
        const searchTerms = normalizeKurdishArabic(params.term).split(/\s+/).filter(Boolean);

        if (searchTerms.length === 0) {
            return data;
        }

        const allMatch = searchTerms.every(term => fullText.includes(term));
        return allMatch ? data : null;
    }

    // Ensure Select2 input receives focus when dropdown opens
    if (typeof jQuery !== 'undefined') {
        $(document).on('select2:open', () => {
            setTimeout(() => {
                const searchInput = document.querySelector('.select2-container--open .select2-search__field');
                if (searchInput) {
                    searchInput.focus();
                }
            }, 50);
        });
    }

    function renderChart(membersData) {
        if (!membersData || membersData.length === 0) return;

        console.log("[TREE DBG]: Total Nodes =", membersData.length);
        
        // Root Detection & Fallback
        let roots = membersData.filter(m => m.parentId === null);
        console.log("[TREE DBG]: Detected Roots =", roots.length);

        if (roots.length === 0 && membersData.length > 0) {
            // FALLBACK: Pick member with minimum ID as root
            let fallback = membersData.reduce((prev, curr) => (parseInt(prev.id) < parseInt(curr.id) ? prev : curr));
            fallback.parentId = null;
            roots = [fallback];
            console.warn("[TREE DBG]: No root found! Fallback to ID:", fallback.id);
        }

        // Children count logging
        membersData.forEach(p => {
            let children = membersData.filter(c => c.parentId == p.id);
            if (children.length > 0) {
                console.log(`[TREE DBG]: Node ${p.id} (${p.name}) has ${children.length} children`);
            }
        });

        let finalData = [];

        // 4. Consolidate roots: Pirkhidri Shaho is the ONLY root of the tree
        let mainRoot = membersData.find(r => r.is_shaho || r.id === '1') || roots[0];
        let gapNode = membersData.find(m => m.is_unlinked_gap);
        if (mainRoot) {
            mainRoot.parentId = null;
            mainRoot.is_root = true;
            mainRoot.is_shaho = true;
            roots.forEach(r => { 
                if (r.id !== mainRoot.id) {
                    if (gapNode && r.id !== gapNode.id) {
                        r.parentId = gapNode.id;
                    } else {
                        r.parentId = mainRoot.id;
                    }
                    r.is_root = false;
                }
            });
        }

        // 5. BFS to ensure NO cycles and single hierarchy
        const queue = mainRoot ? [mainRoot] : [];
        const addedIds = new Set();

        while (queue.length > 0) {
            const current = queue.shift();
            if (addedIds.has(current.id)) continue;
            
            addedIds.add(current.id);
            let node = membersData.find(m => m.id === current.id);
            if (node) finalData.push(node);

            if (!node) continue;

            // Find children
            membersData.filter(m => m.parentId === current.id).forEach(c => {
                if (!addedIds.has(c.id)) queue.push(c);
            });
        }

        console.log("Tree Data:", finalData); // For debugging

        chart = new d3.OrgChart()
            .container('#chart-area')
            .data(finalData)
            .nodeWidth(d => 280)
            .nodeHeight(d => 110)
            .siblingsMargin(d => 30)
            .childrenMargin(d => 50)
            .neighbourMargin(d => 50)
            .compact(isCompactMode)
            .layout('bottom') 
            .onNodeClick(d => {
                const nodeId = (typeof d === 'object' && d.data) ? d.data.id : d;
                focusOnMember(nodeId, 0.85);
            })
            .linkUpdate(function (d, i, arr) {
                d3.select(this).attr("stroke", "#c5a059").attr("stroke-width", 2);
            })
            .nodeContent(function (d, i, arr, state) {
                const isShaho = d.data.is_shaho || d.data.id === '1';
                const isUnlinkedGap = !!d.data.is_unlinked_gap;
                const isFamilyHead = parseInt(d.data.is_family_head) === 1;
                const currentFamilyName = <?php echo json_encode($current_family_name); ?>;
                const isSelected = selectedId && (selectedId.toString() === d.data.id.toString());
                const borderHighlight = isSelected ? 'border: 3px solid #f59e0b !important; box-shadow: 0 0 20px rgba(245, 158, 11, 0.6) !important;' : '';

                // ONLY PIRKHIDRI SHAHO GETS THE ROOT CARD AND "باوە گەورە" TITLE
                if (isShaho) {
                    return `
                    <div class="node-wrapper root-node" style="width: 280px; height: 110px; box-sizing: border-box; display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; color: #fff; padding: 10px 14px; ${borderHighlight}">
                        <div style="display: inline-flex; align-items: center; gap: 6px; background: rgba(212, 175, 55, 0.25); border: 1px solid #d4af37; border-radius: 20px; padding: 2px 10px; font-size: 0.72rem; color: #ffd700; font-weight: bold; margin-bottom: 6px;">
                            <i class="fas fa-crown"></i> باپیرە گەورەی سادات
                        </div>
                        <div style="font-family: 'Rabar013', 'Rabar022', serif; font-size: 1.02rem; font-weight: 800; color: #ffffff; line-height: 1.35; max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; text-shadow: 0 2px 4px rgba(0,0,0,0.6);">
                            ${d.data.name}
                        </div>
                        <div style="font-size: 0.72rem; color: #f1c40f; margin-top: 5px; opacity: 0.95;">
                            بنچینە و سەرچاوەی سادات
                        </div>
                    </div>
                    `;
                }

                // UNLINKED GAP CARD
                if (isUnlinkedGap) {
                    return `
                    <div class="node-wrapper unlinked-gap-node" style="width: 280px; height: 110px; box-sizing: border-box; display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; padding: 10px 14px; ${borderHighlight}">
                        <div style="display: inline-flex; align-items: center; gap: 5px; background: #fde68a; border: 1px dashed #d97706; border-radius: 20px; padding: 2px 10px; font-size: 0.68rem; color: #92400e; font-weight: bold; margin-bottom: 5px;">
                            <i class="fas fa-link-slash"></i> زنجیرەی نادیار
                        </div>
                        <div style="font-family: 'Rabar013', 'Rabar022', serif; font-size: 1.08rem; font-weight: 800; color: #78350f; line-height: 1.3; max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            ${d.data.name}
                        </div>
                        <div style="font-size: 0.72rem; color: #b45309; font-weight: 600; margin-top: 4px; line-height: 1.2;">
                            <i class="fas fa-info-circle"></i> زنجیرەی ئەم بنەماڵەیە لە داتابەیسدا تۆمار نەکراوە
                        </div>
                    </div>
                    `;
                }

                // ALL OTHER MEMBERS ARE STANDARD NODES
                const isActive = d.data.status === 'active';
                const statusBadge = isActive 
                    ? '<span style="display: inline-flex; align-items: center; gap: 4px; background: #ecfdf5; color: #047857; font-size: 0.68rem; font-weight: bold; padding: 2px 8px; border-radius: 12px; border: 1px solid #a7f3d0;"><span style="width: 5px; height: 5px; border-radius: 50%; background: #10b981;"></span>لە ژیاندا</span>' 
                    : '<span style="display: inline-flex; align-items: center; gap: 4px; background: #f8fafc; color: #64748b; font-size: 0.68rem; padding: 2px 8px; border-radius: 12px; border: 1px solid #e2e8f0;">کۆچکردوو</span>';

                const isOfficial = parseInt(d.data.is_official_member) === 1;
                const officialBadge = isOfficial 
                    ? '<span style="display: inline-flex; align-items: center; gap: 3px; background: #eff6ff; color: #1d4ed8; font-size: 0.65rem; font-weight: bold; padding: 1px 6px; border-radius: 6px; border: 1px solid #bfdbfe;"><i class="fas fa-id-card"></i> ئەندام</span>' 
                    : '';

                const headBadge = isFamilyHead 
                    ? '<span style="display: inline-flex; align-items: center; gap: 3px; background: #fefce8; color: #b45309; font-size: 0.65rem; font-weight: bold; padding: 1px 6px; border-radius: 6px; border: 1px solid #fde047;"><i class="fas fa-crown"></i> باوکی گەورەی بنەماڵە</span>' 
                    : '';

                const headBorder = isFamilyHead ? 'border: 2px solid #d4af37 !important; box-shadow: 0 4px 15px rgba(212, 175, 55, 0.25) !important;' : '';

                const cardName = d.data.display_name || d.data.name;
                const fatherVal = d.data.display_father || (d.data.father_name !== 'نادیار' ? d.data.father_name : '');
                const fatherTxt = fatherVal ? `باوک: ${fatherVal}` : currentFamilyName;

                return `
                <div class="node-wrapper standard-node" style="width: 280px; height: 110px; box-sizing: border-box; display: flex; flex-direction: column; justify-content: space-between; padding: 12px 14px; text-align: right; ${headBorder} ${borderHighlight}">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <span style="font-size: 0.72rem; color: #475569; font-weight: bold; background: #f1f5f9; padding: 2px 8px; border-radius: 8px; border: 1px solid #e2e8f0;">#${d.data.code}</span>
                        <div style="display: flex; gap: 4px; align-items: center;">
                            ${headBadge}
                            ${officialBadge}
                            ${statusBadge}
                        </div>
                    </div>
                    <div style="font-family: 'Rabar013', 'Rabar022', serif; font-size: 1.05rem; font-weight: 700; color: #0f172a; line-height: 1.35; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="${d.data.name}">
                        ${cardName}
                    </div>
                    <div style="font-size: 0.75rem; color: #64748b; font-family: 'Rabar005', 'Rabar022', sans-serif; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; border-top: 1px solid #f1f5f9; padding-top: 4px;">
                        ${fatherTxt}
                    </div>
                </div>
                `;
            })
            .render()
            .expandAll();

        // SMART INITIAL LOAD: Focus on the latest leaf or root with readable 0.85 scale
        setTimeout(() => {
            const leaves = finalData.filter(m => !finalData.some(c => c.parentId == m.id));
            const focusTarget = (leaves.length > 0) ? leaves[leaves.length - 1] : (mainRoot || finalData[0]);
            if (focusTarget) {
                focusOnMember(focusTarget.id, 0.85);
            }
        }, 300);
    }

    function focusOnMember(nodeId, targetScale = 0.85) {
        if (!chart) return;
        selectedId = nodeId;
        const member = membersData.find(m => m.id === nodeId);
        if (!member) return;

        chart.setCentered(nodeId).render();

        const state = chart.getChartState();
        if (state && state.root && state.svg && state.zoomBehavior) {
            const descendants = state.root.descendants();
            const node = descendants.find(d => state.nodeId(d.data).toString() === nodeId.toString());
            if (node) {
                const w = state.svgWidth;
                const h = state.svgHeight;
                const targetY = node.y - 55;

                const identity = d3.zoomIdentity
                    .translate(w / 2, h / 2)
                    .scale(targetScale)
                    .translate(-node.x, -targetY);

                state.svg.transition().duration(600).call(state.zoomBehavior.transform, identity);
            }
        }

        updateSidebarContent(member);
    }

    function fitTreeSmart() {
        if (!chart) return;
        const state = chart.getChartState();
        if (!state || !state.root || !state.svg || !state.zoomBehavior) {
            chart.fit();
            return;
        }

        const descendants = state.root.descendants();
        if (descendants.length === 0) return;

        const attrs = state;
        const minX = d3.min(descendants, d => d.x + attrs.layoutBindings[attrs.layout].nodeLeftX(d));
        const maxX = d3.max(descendants, d => d.x + attrs.layoutBindings[attrs.layout].nodeRightX(d));
        const minY = d3.min(descendants, d => d.y + attrs.layoutBindings[attrs.layout].nodeTopY(d));
        const maxY = d3.max(descendants, d => d.y + attrs.layoutBindings[attrs.layout].nodeBottomY(d));

        const w = state.svgWidth;
        const h = state.svgHeight;
        const chartW = maxX - minX + 100;
        const chartH = maxY - minY + 100;

        let scaleVal = Math.min(1.0, 0.9 / Math.max(chartW / w, chartH / h));
        // CLAMP: Never allow fit to shrink past readable threshold!
        if (scaleVal < 0.4) {
            scaleVal = 0.55;
        }

        const centerX = (minX + maxX) / 2;
        const centerY = (minY + maxY) / 2;

        const identity = d3.zoomIdentity
            .translate(w / 2, h / 2)
            .scale(scaleVal)
            .translate(-centerX, -centerY);

        state.svg.transition().duration(600).call(state.zoomBehavior.transform, identity);
    }

    function resetZoom(scale = 0.85) {
        const targetId = selectedId || (membersData.find(m => m.is_shaho || m.id === '1')?.id) || membersData[0]?.id;
        if (targetId) {
            focusOnMember(targetId, scale);
        }
    }

    function toggleCompactMode() {
        if (!chart) return;
        isCompactMode = !isCompactMode;
        chart.compact(isCompactMode).render();
        
        const btn = document.getElementById('compactToggleBtn');
        if (btn) {
            btn.style.color = isCompactMode ? 'var(--tree-maroon)' : '#334155';
            btn.style.background = isCompactMode ? '#fee2e2' : 'transparent';
        }

        const targetId = selectedId || membersData[0]?.id;
        if (targetId) {
            focusOnMember(targetId, 0.85);
        }
    }

    function goToRoot() {
        if (!membersData || membersData.length === 0) return;
        let root = membersData.find(m => m.is_shaho || m.id === '1') || membersData[0];
        focusOnMember(root.id, 0.85);
    }

    function goToLeaves() {
        if (!membersData || membersData.length === 0) return;
        const parentIds = new Set(membersData.map(m => m.parentId).filter(p => p !== null));
        const leaves = membersData.filter(m => !parentIds.has(m.id));
        const target = leaves.length > 0 ? leaves[leaves.length - 1] : membersData[membersData.length - 1];
        focusOnMember(target.id, 0.85);
    }

    function onTreeSearch(val) {
        val = (val || '').trim();
        const resultsBox = document.getElementById('treeSearchResults');
        if (!resultsBox) return;

        if (val.length < 2) {
            resultsBox.style.display = 'none';
            return;
        }

        const normVal = normalizeKurdishArabic(val);
        const searchTerms = normVal.split(/\s+/).filter(Boolean);

        const matches = membersData.filter(m => {
            const chain = buildMemberLineageChain(m);
            const hay = normalizeKurdishArabic(`${chain} ${m.name} ${m.display_name} ${m.father_name || ''} ${m.grandfather_name || ''} ${m.code} ${m.id}`);
            return searchTerms.every(term => hay.includes(term));
        }).slice(0, 15);

        if (matches.length === 0) {
            resultsBox.innerHTML = '<div style="padding: 10px 14px; color: #94a3b8; font-size: 0.85rem;">هیچ ئەندامێک نەدۆزرایەوە</div>';
        } else {
            resultsBox.innerHTML = matches.map(m => {
                const chain = buildMemberLineageChain(m);
                return `
                <div class="tree-search-item" onclick="selectSearchMember('${m.id}')" style="padding: 8px 14px; border-bottom: 1px solid #f1f5f9; cursor: pointer; transition: 0.2s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                    <div style="font-weight: bold; font-size: 0.88rem; color: #1e293b;">${chain}</div>
                    <div style="font-size: 0.75rem; color: #64748b;">کۆد: #${m.code} | باوک: ${m.father_name || 'نادیار'}</div>
                </div>
            `;}).join('');
        }
        resultsBox.style.display = 'block';
    }

    function selectSearchMember(id) {
        const resultsBox = document.getElementById('treeSearchResults');
        if (resultsBox) resultsBox.style.display = 'none';
        const input = document.getElementById('treeSearchInput');
        if (input) input.value = '';
        
        focusOnMember(id, 0.95);
    }

    // Close search dropdown when clicking outside
    document.addEventListener('click', function(e) {
        const box = document.getElementById('treeSearchResults');
        const input = document.getElementById('treeSearchInput');
        if (box && input && !box.contains(e.target) && e.target !== input) {
            box.style.display = 'none';
        }
    });

    function updateSidebar(nodeId) {
        focusOnMember(nodeId, 0.85);
    }

    function updateSidebarContent(member) {
        document.getElementById('s-name').innerText = member.name;
        
        const isFamilyHead = parseInt(member.is_family_head) === 1;
        const isShaho = member.is_shaho;
        const isUnlinkedGap = !!member.is_unlinked_gap;

        // Icon
        const sIcon = document.getElementById('s-icon');
        if (isUnlinkedGap) {
            sIcon.innerHTML = '<i class="fas fa-link-slash" style="color: #f59e0b;"></i>';
            sIcon.style.borderColor = '#f59e0b';
            sIcon.style.background = 'rgba(245, 158, 11, 0.2)';
        } else if (isFamilyHead || isShaho) {
            sIcon.innerHTML = '<i class="fas fa-crown" style="color: #ffd700;"></i>';
            sIcon.style.borderColor = '#ffd700';
            sIcon.style.background = 'rgba(212, 175, 55, 0.3)';
        } else {
            sIcon.innerHTML = '<i class="fas fa-user"></i>';
            sIcon.style.borderColor = 'var(--tree-gold)';
            sIcon.style.background = 'rgba(255,255,255,0.2)';
        }

        // Status badge
        const badgeContainer = document.getElementById('s-badge-container');
        const isActive = member.status === 'active';
        let headBadgeHtml = '';
        if (isUnlinkedGap) {
            headBadgeHtml = '<span style="display: inline-flex; align-items: center; gap: 4px; background: #fef3c7; color: #92400e; font-size: 0.75rem; font-weight: bold; padding: 3px 12px; border-radius: 20px; border: 1px dashed #f59e0b;"><i class="fas fa-link-slash"></i> زنجیرەی نادیار</span>';
        } else if (isFamilyHead) {
            headBadgeHtml = '<span style="display: inline-flex; align-items: center; gap: 4px; background: #fef3c7; color: #92400e; font-size: 0.75rem; font-weight: bold; padding: 3px 12px; border-radius: 20px; border: 1px solid #fcd34d;"><i class="fas fa-crown"></i> باوکی گەورەی بنەماڵە</span>';
        } else if (isShaho) {
            headBadgeHtml = '<span style="display: inline-flex; align-items: center; gap: 4px; background: rgba(212, 175, 55, 0.2); color: #ffd700; font-size: 0.75rem; font-weight: bold; padding: 3px 12px; border-radius: 20px; border: 1px solid #d4af37;"><i class="fas fa-crown"></i> باپیرە گەورەی سادات</span>';
        }

        if (isUnlinkedGap) {
            badgeContainer.innerHTML = headBadgeHtml;
        } else {
            badgeContainer.innerHTML = (isActive
                ? '<span style="display: inline-flex; align-items: center; gap: 5px; background: #10b981; color: #fff; font-size: 0.75rem; font-weight: bold; padding: 3px 12px; border-radius: 20px;"><span style="width: 6px; height: 6px; border-radius: 50%; background: #fff;"></span> لە ژیاندا ماو</span>'
                : '<span style="display: inline-flex; align-items: center; gap: 5px; background: rgba(255,255,255,0.25); color: #fff; font-size: 0.75rem; padding: 3px 12px; border-radius: 20px;">🕊️ کۆچکردوو</span>') + headBadgeHtml;
        }

        // Lineage summary box
        const lineageBox = document.getElementById('s-lineage-box');
        lineageBox.style.display = 'block';
        document.getElementById('s-father-name').innerText = member.father_name || 'نادیار';
        document.getElementById('s-grand-name').innerText = member.grandfather_name || 'نادیار';
        document.getElementById('s-member-code').innerText = '#' + member.code;

        // Update Header Selected Member Tag
        const headerSelectedName = document.getElementById('header-selected-name');
        if (headerSelectedName) {
            headerSelectedName.innerText = member.display_name || member.name;
        }
        const headerSelectedTag = document.getElementById('header-selected-tag');
        if (headerSelectedTag) headerSelectedTag.style.display = 'inline-flex';
        const headerNoSelection = document.getElementById('header-no-selection');
        if (headerNoSelection) headerNoSelection.style.display = 'none';

        // Toggle Family Head Action Button
        const toggleHeadBtn = document.getElementById('toggle-head-btn');
        if (toggleHeadBtn) {
            if (isUnlinkedGap) {
                toggleHeadBtn.style.display = 'none';
            } else {
                if (isFamilyHead) {
                    toggleHeadBtn.innerHTML = '<i class="fas fa-times-circle"></i> لابردنی باوکی گەورە';
                    toggleHeadBtn.style.background = '#fff1f2';
                    toggleHeadBtn.style.color = '#e11d48';
                    toggleHeadBtn.style.border = '1.5px solid #fecdd3';
                } else {
                    toggleHeadBtn.innerHTML = '<i class="fas fa-crown"></i> باوکی گەورەی بنەماڵە';
                    toggleHeadBtn.style.background = 'linear-gradient(135deg, #f59e0b, #d97706)';
                    toggleHeadBtn.style.color = '#fff';
                    toggleHeadBtn.style.border = 'none';
                }
                toggleHeadBtn.style.display = 'inline-flex';
            }
        }

        // Action Buttons
        const makeOfficialBtn = document.getElementById('make-official-btn');
        if (makeOfficialBtn) {
            if (isUnlinkedGap) {
                makeOfficialBtn.style.display = 'none';
            } else {
                if (parseInt(member.is_official_member) === 1) {
                    makeOfficialBtn.innerHTML = '<i class="fas fa-check-double"></i> ئەندامی فەرمی کارگێڕییە';
                    makeOfficialBtn.href = `members_profile.php?id=${member.id}`;
                    makeOfficialBtn.style.background = '#dcfce7';
                    makeOfficialBtn.style.color = '#15803d';
                    makeOfficialBtn.style.borderColor = '#86efac';
                } else {
                    makeOfficialBtn.innerHTML = '<i class="fas fa-id-card"></i> ئەندامی فەرمی ئیدارە';
                    makeOfficialBtn.href = `members_new.php?link_lineage_id=${member.id}`;
                    makeOfficialBtn.style.background = '#eff6ff';
                    makeOfficialBtn.style.color = '#1d4ed8';
                    makeOfficialBtn.style.borderColor = '#93c5fd';
                }
                makeOfficialBtn.style.display = 'inline-flex';
            }
        }

        const kinBtn = document.getElementById('kinship-member-btn');
        if (kinBtn) {
            if (isUnlinkedGap) {
                kinBtn.style.display = 'none';
            } else {
                kinBtn.href = `kinship_search.php?id=${member.id}`;
                kinBtn.style.display = 'inline-flex';
            }
        }

        const artTreeBtn = document.getElementById('descendants-art-tree-btn');
        if (artTreeBtn) {
            if (isUnlinkedGap) {
                artTreeBtn.style.display = 'none';
            } else {
                artTreeBtn.href = `descendants_tree.php?id=${member.id}`;
                artTreeBtn.style.display = 'inline-flex';
            }
        }

        const certCertBtn = document.getElementById('cert-cert-btn');
        if (certCertBtn) {
            if (isUnlinkedGap) {
                certCertBtn.style.display = 'none';
            } else {
                certCertBtn.href = `lineage_reports.php?cert_id=${member.id}&type=certificate`;
                certCertBtn.style.display = 'inline-flex';
            }
        }

        const certCommBtn = document.getElementById('cert-comm-btn');
        if (certCommBtn) {
            if (isUnlinkedGap) {
                certCommBtn.style.display = 'none';
            } else {
                certCommBtn.href = `lineage_reports.php?cert_id=${member.id}&type=committee`;
                certCommBtn.style.display = 'inline-flex';
            }
        }

        const editBtn = document.getElementById('edit-member-btn');
        if (editBtn) {
            if (member.is_shaho || member.id === '1' || isUnlinkedGap) {
                editBtn.style.display = 'none';
            } else {
                editBtn.style.display = 'inline-flex';
            }
        }

        const deleteBtn = document.getElementById('delete-member-btn');
        if (deleteBtn) {
            if (member.is_shaho || member.id === '1' || isUnlinkedGap) {
                deleteBtn.style.display = 'none';
            } else {
                deleteBtn.style.display = 'inline-flex';
            }
        }

        const addBtn = document.getElementById('add-son-btn');
        if (addBtn) {
            if (isUnlinkedGap) {
                addBtn.style.display = 'none';
            } else {
                addBtn.style.display = 'inline-flex';
                document.getElementById('modal-parent-name').innerText = member.name;
            }
        }

        // 1. CHILDREN
        const children = membersData.filter(m => m.parentId === member.id);
        document.getElementById('count-children').innerText = `(${children.length})`;
        renderList('panel-children', children);

        // 2. GRANDCHILDREN
        const grandChildren = [];
        children.forEach(child => {
            const kids = membersData.filter(m => m.parentId === child.id);
            kids.forEach(k => {
                k.fatherName = child.name;
                grandChildren.push(k);
            });
        });
        document.getElementById('count-grand').innerText = `(${grandChildren.length})`;
        renderList('panel-grand-children', grandChildren, true);

        // 3. SIBLINGS
        const siblings = membersData.filter(m => {
            if (m.id === member.id || m.is_unlinked_gap) return false;
            if (member.parentId === 'unlinked_gap') {
                return m.parentId === 'unlinked_gap' && m.father_name && member.father_name && m.father_name === member.father_name;
            }
            return m.parentId === member.parentId;
        });
        document.getElementById('count-siblings').innerText = `(${siblings.length})`;
        renderList('panel-siblings', siblings);
    }

    function toggleFamilyHead() {
        if (!selectedId || selectedId === 'unlinked_gap') return;
        const member = membersData.find(m => m.id.toString() === selectedId.toString());
        if (!member || member.is_unlinked_gap) return;
        const currentHead = parseInt(member.is_family_head) === 1 ? 1 : 0;
        const newHead = currentHead === 1 ? 0 : 1;

        const formData = new FormData();
        formData.append('action', 'toggle_family_head');
        formData.append('member_id', member.id);
        formData.append('is_head', newHead);

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                member.is_family_head = newHead;
                updateSidebarContent(member);
                chart.render();
                focusOnMember(member.id, 0.85);
            }
        })
        .catch(err => {
            console.error('Error toggling family head:', err);
        });
    }

    function openQuickAdd() {
        if (!selectedId || selectedId === 'unlinked_gap') return;
        document.getElementById('quickAddModal').style.display = 'flex';
        document.getElementById('quick-names').value = '';
        document.getElementById('quick-names').focus();
    }

    function closeQuickAdd() {
        document.getElementById('quickAddModal').style.display = 'none';
    }

    function submitQuickAdd() {
        if (!selectedId || selectedId === 'unlinked_gap') return;
        const names = document.getElementById('quick-names').value;
        if (!names.trim()) return;

        const formData = new FormData();
        formData.append('action', 'quick_add_members');
        formData.append('parent_id', selectedId);
        formData.append('family_id', familyId);
        formData.append('names', names);

        fetch(window.location.href, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                closeQuickAdd();
                window.location.reload(); 
            }
        });
    }

    function openEditLineageModal() {
        if (!selectedId || selectedId === 'unlinked_gap') return;
        const member = membersData.find(m => m.id.toString() === selectedId.toString());
        if (!member || member.is_unlinked_gap) return;

        if (member.is_shaho || member.id === '1') {
            alert('پیرخدری شاهۆ باپیرە گەورەی هەمووانە و باوکی دەستکاری ناکرێت.');
            return;
        }

        document.getElementById('edit-member-id').value = member.id;
        document.getElementById('edit-member-name').value = member.name;

        // Build list of all descendants of this member to prevent circular lineage
        const descendantIds = new Set();
        function collectDescendants(id) {
            membersData.filter(m => m.parentId && m.parentId.toString() === id.toString()).forEach(child => {
                descendantIds.add(child.id.toString());
                collectDescendants(child.id.toString());
            });
        }
        collectDescendants(member.id.toString());

        // Destroy previous select2 instance if exists
        if (typeof jQuery !== 'undefined' && $('#edit-parent-select').hasClass("select2-hidden-accessible")) {
            $('#edit-parent-select').select2('destroy');
        }

        // Populate parent select dropdown
        const select = document.getElementById('edit-parent-select');
        select.innerHTML = '';

        // Pirkhidri Shaho option
        const shahoOpt = document.createElement('option');
        shahoOpt.value = '1';
        shahoOpt.textContent = 'السید پیرخضر الپیرخدری الشاهو (باپیرە گەورە)';
        shahoOpt.dataset.search = 'السید پیرخضر الپیرخدری الشاهو باپیرە گەورە پیرخدری شاهۆ 1';
        select.appendChild(shahoOpt);

        // Sort candidates alphabetically by their full lineage chain
        const candidates = membersData.filter(m => 
            m.id.toString() !== '1' && 
            m.id.toString() !== 'unlinked_gap' &&
            !m.is_unlinked_gap &&
            m.id.toString() !== member.id.toString() && 
            !descendantIds.has(m.id.toString())
        );
        candidates.sort((a, b) => {
            const nameA = buildMemberLineageChain(a);
            const nameB = buildMemberLineageChain(b);
            return nameA.localeCompare(nameB, 'ckb');
        });

        candidates.forEach(m => {
            const opt = document.createElement('option');
            opt.value = m.id;
            const lineageChain = buildMemberLineageChain(m);
            opt.textContent = `${lineageChain} (کۆد: #${m.code})`;
            opt.dataset.search = `${lineageChain} ${m.name} ${m.display_name} ${m.father_name || ''} ${m.grandfather_name || ''} ${m.great_grandfather_name || ''} ${m.code} ${m.id}`;
            select.appendChild(opt);
        });

        // Determine current parent
        let currentParent = member.parentId || member.father_id || '1';
        select.value = currentParent.toString();
        if (!select.value) {
            select.value = '1';
        }

        document.getElementById('editLineageModal').style.display = 'flex';

        // Initialize Select2 with live search and custom Kurdish matcher
        if (typeof jQuery !== 'undefined' && typeof jQuery.fn.select2 !== 'undefined') {
            $('#edit-parent-select').select2({
                dir: "rtl",
                width: '100%',
                dropdownParent: $('#editLineageModal'),
                placeholder: "گەڕان بەدوای ناوی باوکدا...",
                matcher: select2KurdishMatcher,
                language: {
                    noResults: function() { return "هیچ ئەنجامێک نەدۆزرایەوە"; },
                    searching: function() { return "گەڕان..."; }
                }
            });
            $('#edit-parent-select').val(currentParent.toString()).trigger('change');
        }

        document.getElementById('edit-member-name').focus();
    }

    function closeEditLineageModal() {
        if (typeof jQuery !== 'undefined' && $('#edit-parent-select').hasClass("select2-hidden-accessible")) {
            $('#edit-parent-select').select2('close');
        }
        document.getElementById('editLineageModal').style.display = 'none';
    }

    function submitEditLineage() {
        const memberId = document.getElementById('edit-member-id').value;
        const fullName = document.getElementById('edit-member-name').value.trim();
        const parentId = (typeof jQuery !== 'undefined' && $('#edit-parent-select').length) 
            ? $('#edit-parent-select').val() 
            : document.getElementById('edit-parent-select').value;

        if (!memberId || !fullName) {
            alert('تکایە ناوی کەسەکە بە تەواوی بنووسە!');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'edit_member_lineage');
        formData.append('member_id', memberId);
        formData.append('parent_id', parentId);
        formData.append('family_id', familyId);
        formData.append('full_name', fullName);

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                closeEditLineageModal();
                window.location.reload();
            } else {
                alert(data.message || 'هەڵەیەک لە نوێکردنەوەدا ڕوویدا');
            }
        })
        .catch(err => {
            console.error('Error updating member:', err);
            alert('هەڵەیەک لە پەیوەندی بە سێرڤەر ڕوویدا');
        });
    }

    function deleteCurrentMember() {
        if (!selectedId || selectedId === 'unlinked_gap') return;
        const member = membersData.find(m => m.id.toString() === selectedId.toString());
        if (!member || member.is_unlinked_gap) return;

        if (member.is_shaho || member.id === '1') {
            alert('پیرخدری شاهۆ باپیرە گەورەی هەمووانە و ناسڕدرێتەوە!');
            return;
        }

        // STRICT LEAF CHECK:
        const children = membersData.filter(m => m.parentId && m.parentId.toString() === member.id.toString());
        if (children.length > 0) {
            alert(`ئەم کەسە ناکرێت بسڕدرێتەوە چونکە (${children.length}) منداڵی هەیە!\n\nهەر باوکێک منداڵی هەبێت نەسڕێتەوە تا سەرەتا منداڵەکانی دەسڕێنەوە یان دەبەسترێنەوە بە باوکێکی تر.`);
            return;
        }

        if (!confirm(`ئایا دڵنیایت لە سڕینەوەی ئەم کەسە: (${member.name})؟\n\nئاگاداربە: ئەم تۆمارە لە بنکەدراوە بە یەکجاری دەسڕدرێتەوە.`)) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'delete_lineage_member');
        formData.append('member_id', member.id);

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                window.location.reload();
            } else {
                alert(data.message || 'هەڵەیەک لە سڕینەوەدا ڕوویدا');
            }
        })
        .catch(err => {
            console.error('Error deleting member:', err);
            alert('هەڵەیەک لە پەیوەندی بە سێرڤەر ڕوویدا');
        });
    }

    function renderList(panelId, items, isGrand = false) {
        const panel = document.getElementById(panelId);
        if (items.length === 0) {
            panel.innerHTML = '<div style="color:#ccc; text-align:center; padding:50px;">هیچ تۆمارێک نییە</div>';
            return;
        }
        panel.innerHTML = items.map(it => `
            <div class="m-card" onclick="updateSidebar('${it.id}')">
                <div>
                    <div style="font-weight:bold;">${it.name}</div>
                    ${isGrand ? `<div class="sub-text">وەچەی (${it.fatherName})</div>` : ''}
                </div>
                <i class="fas fa-chevron-left" style="color:var(--tree-gold); font-size:0.8rem;"></i>
            </div>
        `).join('');
    }

    function switchTab(tab) {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        event.currentTarget.classList.add('active');
        document.getElementById('panel-' + tab).classList.add('active');
    }

    // Safe initialization for Select2
    function initSelect2() {
        if (typeof jQuery !== 'undefined' && typeof jQuery.fn.select2 !== 'undefined') {
            $('.family-select').select2({
                dir: "rtl",
                width: '280px',
                language: {
                    noResults: function() { return "هیچ ئەنجامێک نەدۆزرایەوە"; }
                }
            });
        } else {
            setTimeout(initSelect2, 100);
        }
    }
    initSelect2();

    document.addEventListener('DOMContentLoaded', () => {
        renderChart(membersData);
    });
</script>

<?php require_once 'includes/layout_end.php'; ?>
