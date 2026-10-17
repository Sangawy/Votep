<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
$conn = getDB();

$flash_msg = '';
$flash_type = 'success';

// -------------------------------------------------------------
// ACTION: APPROVE REQUEST
// -------------------------------------------------------------
if (isset($_GET['approve']) && (int)$_GET['approve'] > 0) {
    $req_id = (int)$_GET['approve'];
    
    // Fetch request
    $rStmt = $conn->prepare("SELECT * FROM member_requests WHERE id = ? AND status = 'pending'");
    $rStmt->bind_param("i", $req_id);
    $rStmt->execute();
    $request = $rStmt->get_result()->fetch_assoc();
    $rStmt->close();

    if ($request) {
        $member_id = (int)$request['member_id'];
        
        // Fetch parent details
        $pStmt = $conn->prepare("SELECT * FROM members WHERE id = ?");
        $pStmt->bind_param("i", $member_id);
        $pStmt->execute();
        $parent = $pStmt->get_result()->fetch_assoc();
        $pStmt->close();

        if ($parent) {
            $child_name = trim($request['child_name']);
            // Add title if male and doesn't have it
            $gender = $request['gender'] === 'مێ' ? 'مێ' : 'نێر';
            $formatted_name = $child_name;
            if ($gender === 'نێر' && !preg_match('/^(سید|سەید|السيد|السید)\s+/u', $formatted_name)) {
                $formatted_name = 'السيد ' . $formatted_name;
            }

            $family_id = (int)($parent['family_id'] ?? 0);
            $bnamala = $parent['bnamala'] ?? '';
            $village = $parent['village'] ?? '';
            $birth_date = !empty($request['birth_date']) ? $request['birth_date'] : null;
            $birth_year = !empty($request['birth_date']) ? date('Y', strtotime($request['birth_date'])) : null;
            $notes = trim($request['notes'] ?? '');

            // Insert new child into members table
            $insStmt = $conn->prepare("
                INSERT INTO members (
                    full_name, parent_id, family_id, gender, bnamala, village, 
                    birth_date, birth_year, member_status, is_alive, is_official_member, 
                    notes, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', 1, 0, ?, NOW(), NOW())
            ");
            $insStmt->bind_param("siissssss", $formatted_name, $member_id, $family_id, $gender, $bnamala, $village, $birth_date, $birth_year, $notes);

            if ($insStmt->execute()) {
                $new_member_id = $insStmt->insert_id;
                $insStmt->close();

                // Update request status to approved
                $uStmt = $conn->prepare("UPDATE member_requests SET status = 'approved', admin_notes = 'پەسەندکرا لە درەختی نەسەبدا' WHERE id = ?");
                $uStmt->bind_param("i", $req_id);
                $uStmt->execute();
                $uStmt->close();

                $_SESSION['flash_msg'] = "منداڵەکە («{$formatted_name}») بە سەرکەوتوویی پەسەندکرا و خرایە ناو درەختی نەسەب و بنەماڵە!";
                $_SESSION['flash_type'] = "success";
            } else {
                $_SESSION['flash_msg'] = "هەڵەیەک لە زیادکردنی منداڵەکە ڕوویدا: " . $insStmt->error;
                $_SESSION['flash_type'] = "error";
            }
        } else {
            $_SESSION['flash_msg'] = "باوکی ئەم منداڵە لە داتابەیسدا نەدۆزرایەوە.";
            $_SESSION['flash_type'] = "error";
        }
    } else {
        $_SESSION['flash_msg'] = "داواکارییەکە پێشتر کاری لەسەر کراوە یاخود بوونی نییە.";
        $_SESSION['flash_type'] = "error";
    }

    header('Location: member_requests_confirm.php');
    exit;
}

// -------------------------------------------------------------
// ACTION: REJECT REQUEST
// -------------------------------------------------------------
if (isset($_GET['reject']) && (int)$_GET['reject'] > 0) {
    $req_id = (int)$_GET['reject'];
    $uStmt = $conn->prepare("UPDATE member_requests SET status = 'rejected', admin_notes = 'ڕەتکرایەوە لەلایەن بەڕێوەبەر' WHERE id = ?");
    $uStmt->bind_param("i", $req_id);
    $uStmt->execute();
    $uStmt->close();

    $_SESSION['flash_msg'] = "داواکارییەکە بە سەرکەوتوویی ڕەتکرایەوە.";
    $_SESSION['flash_type'] = "warning";
    header('Location: member_requests_confirm.php');
    exit;
}

// -------------------------------------------------------------
// ACTION: DELETE RECORD
// -------------------------------------------------------------
if (isset($_GET['delete']) && (int)$_GET['delete'] > 0) {
    $req_id = (int)$_GET['delete'];
    $dStmt = $conn->prepare("DELETE FROM member_requests WHERE id = ?");
    $dStmt->bind_param("i", $req_id);
    $dStmt->execute();
    $dStmt->close();

    $_SESSION['flash_msg'] = "تۆماری داواکارییەکە بە سەرکەوتوویی سڕایەوە.";
    $_SESSION['flash_type'] = "success";
    header('Location: member_requests_confirm.php');
    exit;
}

// Flash messages from session
if (isset($_SESSION['flash_msg'])) {
    $flash_msg = $_SESSION['flash_msg'];
    $flash_type = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_msg'], $_SESSION['flash_type']);
}

// Filter status
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : 'pending';

// Stats
$cntPending = $conn->query("SELECT COUNT(*) as c FROM member_requests WHERE status = 'pending'")->fetch_assoc()['c'] ?? 0;
$cntApproved = $conn->query("SELECT COUNT(*) as c FROM member_requests WHERE status = 'approved'")->fetch_assoc()['c'] ?? 0;
$cntRejected = $conn->query("SELECT COUNT(*) as c FROM member_requests WHERE status = 'rejected'")->fetch_assoc()['c'] ?? 0;
$cntTotal = $conn->query("SELECT COUNT(*) as c FROM member_requests")->fetch_assoc()['c'] ?? 0;

// Build Query
$whereClause = "1=1";
if ($filter_status === 'pending') {
    $whereClause .= " AND r.status = 'pending'";
} elseif ($filter_status === 'approved') {
    $whereClause .= " AND r.status = 'approved'";
} elseif ($filter_status === 'rejected') {
    $whereClause .= " AND r.status = 'rejected'";
}

$query = "
    SELECT r.*, 
           m.full_name as parent_name, m.member_code as parent_code, m.phone as parent_phone, m.profile_image,
           f.family_name
    FROM member_requests r
    JOIN members m ON r.member_id = m.id
    LEFT JOIN families f ON m.family_id = f.id
    WHERE $whereClause
    ORDER BY (r.status = 'pending') DESC, r.id DESC
";
$requestsList = $conn->query($query)->fetch_all(MYSQLI_ASSOC);

$page_title = 'پەسەندکردنی داواکارییەکانی ئەندامان';
$active_nav = 'member_requests_confirm';
$sys_module = 'lineage';
require_once 'includes/layout.php';
?>

<style>
    .confirm-header-card {
        background: linear-gradient(135deg, #1a5653 0%, #0f3533 100%);
        color: #fff;
        border-radius: 20px;
        padding: 24px 28px;
        margin-bottom: 24px;
        box-shadow: 0 10px 30px rgba(26, 86, 83, 0.15);
    }

    .confirm-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }

    .stat-box {
        background: #fff;
        border-radius: 18px;
        padding: 18px 20px;
        display: flex;
        align-items: center;
        gap: 16px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.04);
        border: 1px solid #e2e8f0;
        text-decoration: none;
        color: inherit;
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .stat-box:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.08);
    }

    .stat-box.active {
        border-color: #1a5653;
        background: #f8fafc;
        border-width: 2px;
    }

    .stat-box-icon {
        width: 52px;
        height: 52px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        flex-shrink: 0;
    }

    .table-container {
        background: #fff;
        border-radius: 20px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        border: 1px solid #e2e8f0;
        overflow: hidden;
    }

    .confirm-table {
        width: 100%;
        border-collapse: collapse;
        text-align: right;
    }

    .confirm-table th {
        background: #f8fafc;
        color: #475569;
        font-weight: 800;
        font-size: 0.86rem;
        padding: 16px 20px;
        border-bottom: 1.5px solid #e2e8f0;
    }

    .confirm-table td {
        padding: 16px 20px;
        border-bottom: 1px solid #f1f5f9;
        font-size: 0.92rem;
        vertical-align: middle;
    }

    .confirm-table tr:hover {
        background: #fafafa;
    }

    .parent-pill {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .parent-avatar {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #c5a059;
        background: #f1f5f9;
    }

    .badge-status {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.78rem;
        font-weight: 800;
    }

    .status-pending { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
    .status-approved { background: #d1fae5; color: #047857; border: 1px solid #a7f3d0; }
    .status-rejected { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }

    .action-btn-group {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .btn-action {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        border-radius: 10px;
        font-size: 0.82rem;
        font-weight: 700;
        text-decoration: none;
        border: none;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-approve {
        background: #10b981;
        color: #fff;
    }
    .btn-approve:hover {
        background: #059669;
        transform: translateY(-2px);
    }

    .btn-reject {
        background: #fee2e2;
        color: #b91c1c;
    }
    .btn-reject:hover {
        background: #fecaca;
    }

    .btn-del {
        background: #f1f5f9;
        color: #64748b;
    }
    .btn-del:hover {
        background: #e2e8f0;
        color: #0f172a;
    }
</style>

<!-- Header -->
<div class="confirm-header-card">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
        <div>
            <h2 style="margin: 0; font-size: 1.5rem; font-weight: 900; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-check-double" style="color: #f1c40f;"></i> پەسەندکردنی داواکارییەکانی ئەندامان
            </h2>
            <p style="margin: 6px 0 0; opacity: 0.9; font-size: 0.92rem;">
                هەموو ئەو منداڵانەی لەلایەن ئەندامانەوە لە ئەپی مۆبایل داوادەکرێن، لێرەدا دەردەکەون تا بەڕێوەبەر پەسەندیان بکات و بیانخاتە سەر درەختی نەسەب.
            </p>
        </div>
        <a href="member_app/login.php" target="_blank" style="background: rgba(255,255,255,0.18); color: #fff; text-decoration: none; padding: 10px 18px; border-radius: 12px; font-weight: 800; font-size: 0.88rem; display: inline-flex; align-items: center; gap: 8px; border: 1px solid rgba(255,255,255,0.25);">
            <i class="fas fa-mobile-alt"></i> کردنەوەی ئەپی مۆبایل
        </a>
    </div>
</div>

<?php if (!empty($flash_msg)): ?>
    <div style="background: <?php echo $flash_type === 'success' ? '#ecfdf5' : ($flash_type === 'warning' ? '#fffbeb' : '#fef2f2'); ?>; 
                border: 1px solid <?php echo $flash_type === 'success' ? '#a7f3d0' : ($flash_type === 'warning' ? '#fde68a' : '#fecaca'); ?>; 
                color: <?php echo $flash_type === 'success' ? '#065f46' : ($flash_type === 'warning' ? '#92400e' : '#991b1b'); ?>; 
                padding: 14px 20px; border-radius: 14px; margin-bottom: 20px; font-weight: 700; font-size: 0.95rem; display: flex; align-items: center; gap: 10px;">
        <i class="fas <?php echo $flash_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i>
        <span><?php echo htmlspecialchars($flash_msg); ?></span>
    </div>
<?php endif; ?>

<!-- Stats Counters / Filters -->
<div class="confirm-stats-grid">
    <a href="?status=pending" class="stat-box <?php echo $filter_status === 'pending' ? 'active' : ''; ?>">
        <div class="stat-box-icon" style="background: #fef3c7; color: #d97706;">
            <i class="fas fa-hourglass-half"></i>
        </div>
        <div>
            <div style="font-size: 0.82rem; color: #64748b; font-weight: 700;">لە چاوەڕوانیدا</div>
            <div style="font-size: 1.5rem; font-weight: 900; color: #b45309;"><?php echo $cntPending; ?></div>
        </div>
    </a>

    <a href="?status=approved" class="stat-box <?php echo $filter_status === 'approved' ? 'active' : ''; ?>">
        <div class="stat-box-icon" style="background: #ecfdf5; color: #059669;">
            <i class="fas fa-check-circle"></i>
        </div>
        <div>
            <div style="font-size: 0.82rem; color: #64748b; font-weight: 700;">پەسەندکراو</div>
            <div style="font-size: 1.5rem; font-weight: 900; color: #047857;"><?php echo $cntApproved; ?></div>
        </div>
    </a>

    <a href="?status=rejected" class="stat-box <?php echo $filter_status === 'rejected' ? 'active' : ''; ?>">
        <div class="stat-box-icon" style="background: #fef2f2; color: #dc2626;">
            <i class="fas fa-times-circle"></i>
        </div>
        <div>
            <div style="font-size: 0.82rem; color: #64748b; font-weight: 700;">ڕەتکراوە</div>
            <div style="font-size: 1.5rem; font-weight: 900; color: #b91c1c;"><?php echo $cntRejected; ?></div>
        </div>
    </a>

    <a href="?status=all" class="stat-box <?php echo $filter_status === 'all' ? 'active' : ''; ?>">
        <div class="stat-box-icon" style="background: #f1f5f9; color: #475569;">
            <i class="fas fa-list"></i>
        </div>
        <div>
            <div style="font-size: 0.82rem; color: #64748b; font-weight: 700;">کۆی گشتی</div>
            <div style="font-size: 1.5rem; font-weight: 900; color: #1e293b;"><?php echo $cntTotal; ?></div>
        </div>
    </a>
</div>

<!-- Table Card -->
<div class="table-container">
    <div style="padding: 16px 24px; background: #fafafa; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
        <strong style="color: #334155;">
            لیستی داواکارییەکان (<?php echo count($requestsList); ?> داواکاری)
        </strong>
    </div>

    <div style="overflow-x: auto;">
        <table class="confirm-table">
            <thead>
                <tr>
                    <th style="width: 70px;">کۆد</th>
                    <th>داواکار (باوک)</th>
                    <th>بنەماڵە</th>
                    <th>ناوی منداڵ</th>
                    <th>ڕەگەز</th>
                    <th>لەدایکبوون</th>
                    <th>تێبینی ئەندام</th>
                    <th>بەرواری ناردن</th>
                    <th>دۆخ</th>
                    <th style="text-align: center; width: 220px;">کردارەکان</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($requestsList)): ?>
                    <tr>
                        <td colspan="10" style="text-align: center; padding: 50px; color: #94a3b8;">
                            <i class="fas fa-inbox" style="font-size: 2.2rem; color: #cbd5e1; margin-bottom: 10px;"></i>
                            <p style="font-weight: 700;">هیچ داواکارییەک بەم دۆخە بوونی نییە.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach($requestsList as $req): ?>
                        <tr>
                            <td>
                                <span style="font-weight: 800; color: #64748b; background: #f1f5f9; padding: 2px 7px; border-radius: 6px;">
                                    #<?php echo $req['id']; ?>
                                </span>
                            </td>
                            <td>
                                <div class="parent-pill">
                                    <img src="<?php echo htmlspecialchars(!empty($req['profile_image']) ? $req['profile_image'] : 'assets/image/user.png'); ?>" 
                                         alt="Avatar" class="parent-avatar" onerror="this.src='assets/image/user.png'">
                                    <div>
                                        <strong style="color: #0f172a; font-size: 0.95rem;"><?php echo htmlspecialchars($req['parent_name']); ?></strong>
                                        <div style="font-size: 0.74rem; color: #64748b;">
                                            کۆد: #<?php echo htmlspecialchars($req['parent_code'] ?: $req['member_id']); ?>
                                            <?php if (!empty($req['parent_phone'])): ?>
                                                | مۆبایل: <?php echo htmlspecialchars($req['parent_phone']); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span style="color: #1a5653; font-weight: 800;">
                                    <?php echo htmlspecialchars($req['family_name'] ?: 'نادیار'); ?>
                                </span>
                            </td>
                            <td>
                                <strong style="color: #1e293b; font-size: 1rem;"><?php echo htmlspecialchars($req['child_name']); ?></strong>
                            </td>
                            <td>
                                <?php if ($req['gender'] === 'مێ'): ?>
                                    <span style="color: #db2777; font-weight: 700; background: #fdf2f8; padding: 2px 8px; border-radius: 8px;">
                                        👧 مێ (کچ)
                                    </span>
                                <?php else: ?>
                                    <span style="color: #0284c7; font-weight: 700; background: #f0f9ff; padding: 2px 8px; border-radius: 8px;">
                                        👦 نێر (کوڕ)
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="color: #64748b; font-size: 0.86rem;">
                                    <?php echo !empty($req['birth_date']) ? htmlspecialchars($req['birth_date']) : '—'; ?>
                                </span>
                            </td>
                            <td>
                                <span style="color: #475569; font-size: 0.84rem;">
                                    <?php echo !empty($req['notes']) ? htmlspecialchars($req['notes']) : '—'; ?>
                                </span>
                            </td>
                            <td>
                                <span style="color: #94a3b8; font-size: 0.78rem;">
                                    <?php echo date('Y/m/d H:i', strtotime($req['created_at'])); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($req['status'] === 'pending'): ?>
                                    <span class="badge-status status-pending">
                                        <i class="fas fa-hourglass-half"></i> چاوەڕوانکراو
                                    </span>
                                <?php elseif ($req['status'] === 'approved'): ?>
                                    <span class="badge-status status-approved">
                                        <i class="fas fa-check-circle"></i> پەسەندکراو
                                    </span>
                                <?php else: ?>
                                    <span class="badge-status status-rejected">
                                        <i class="fas fa-times-circle"></i> ڕەتکراوە
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <div class="action-btn-group">
                                    <?php if ($req['status'] === 'pending'): ?>
                                        <a href="?approve=<?php echo $req['id']; ?>" class="btn-action btn-approve" 
                                           onclick="return confirm('ئایا دڵنیایت لە پەسەندکردنی ئەم منداڵە («<?php echo addslashes($req['child_name']); ?>»)\nو خستنەسەری بۆ ناو درەختی نەسەب؟')">
                                            <i class="fas fa-check"></i> پەسەندکردن
                                        </a>
                                        <a href="?reject=<?php echo $req['id']; ?>" class="btn-action btn-reject"
                                           onclick="return confirm('ئایا دڵنیایت لە ڕەتکردنەوەی ئەم داواکارییە؟')">
                                            <i class="fas fa-times"></i> ڕەتکردنەوە
                                        </a>
                                    <?php else: ?>
                                        <a href="?delete=<?php echo $req['id']; ?>" class="btn-action btn-del"
                                           onclick="return confirm('ئایا دڵنیایت لە سڕینەوەی ئەم تۆمارە؟')">
                                            <i class="fas fa-trash-alt"></i> سڕینەوە
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/layout_end.php'; ?>
