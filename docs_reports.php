<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
$conn = getDB();

$user_id = $_SESSION['user_id'];
$user_data = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();
$current_user = strtolower($user_data['username'] ?? '');
$is_super_admin = ($current_user === 'admin' || $current_user === 'sangawy' || ($user_data['role'] ?? '') === 'owner');

if (!$is_super_admin && !(isset($user_data['can_access_docs']) && $user_data['can_access_docs'])) {
    header('Location: portal.php');
    exit;
}

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$doc_type = $_GET['doc_type'] ?? 'All';

$where = "DATE(doc_date) >= '$start_date' AND DATE(doc_date) <= '$end_date'";
if ($doc_type !== 'All') {
    $safe_type = $conn->real_escape_string($doc_type);
    $where .= " AND doc_type = '$safe_type'";
}

$reports = [];
$res = $conn->query("SELECT * FROM archive_docs WHERE $where ORDER BY doc_date DESC, id DESC");
if ($res) {
    while($r = $res->fetch_assoc()) {
        $reports[] = $r;
    }
}

$sys_module = 'docs';
$page_title = 'ڕاپۆرتی نوسراوەکان';
$active_nav = 'docs_reports';

require_once 'includes/layout.php';
?>

<div class="lib-card" style="margin-bottom: 25px;">
    <div class="card-header">
        <h3>پاڵاوتنی ڕاپۆرتەکان</h3>
        <button onclick="window.print()" class="lib-btn lib-btn-gold">🖨️ چاپکردنی ڕاپۆرت</button>
    </div>
    <div style="padding: 20px;">
        <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
            <div class="lib-field" style="flex: 1; min-width: 200px;">
                <label>لە ڕێکەوتی</label>
                <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
            </div>
            <div class="lib-field" style="flex: 1; min-width: 200px;">
                <label>تا ڕێکەوتی</label>
                <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
            </div>
            <div class="lib-field" style="flex: 1; min-width: 200px;">
                <label>جۆری نوسراو</label>
                <select name="doc_type">
                    <option value="All" <?php echo $doc_type==='All'?'selected':''; ?>>هەموو جۆرەکان</option>
                    <option value="Inbound" <?php echo $doc_type==='Inbound'?'selected':''; ?>>هاتوو</option>
                    <option value="Outbound" <?php echo $doc_type==='Outbound'?'selected':''; ?>>دەرچوو</option>
                    <option value="Internal" <?php echo $doc_type==='Internal'?'selected':''; ?>>ناوخۆیی</option>
                    <option value="Secret" <?php echo $doc_type==='Secret'?'selected':''; ?>>نهێنی</option>
                </select>
            </div>
            <div style="margin-bottom: 5px;">
                <button type="submit" class="lib-btn lib-btn-primary" style="height: 44px; padding: 0 25px;">پیشاندان</button>
            </div>
        </form>
    </div>
</div>

<div class="lib-card" id="printableArea">
    <div class="card-header print-only-header" style="display:none; text-align:center;">
        <h3>ڕاپۆرتی نوسراوەکان (لە <?php echo $start_date; ?> تا <?php echo $end_date; ?>)</h3>
    </div>
    <div class="lib-table-wrapper">
        <table class="lib-table">
            <thead>
                <tr>
                    <th>ژمارە</th>
                    <th>ڕێکەوتی نوسراو</th>
                    <th>بابەت</th>
                    <th>جۆر</th>
                    <th>نێرەر/وەرگر</th>
                    <th>کات و ڕێکەوتی تۆمارکردن</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reports)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 30px;">هیچ داتایەک نەدۆزرایەوە</td>
                </tr>
                <?php else: ?>
                    <?php foreach($reports as $doc): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($doc['doc_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($doc['doc_date']); ?></td>
                        <td><?php echo htmlspecialchars($doc['subject']); ?></td>
                        <td><?php echo htmlspecialchars($doc['doc_type']); ?></td>
                        <td><?php echo htmlspecialchars($doc['correspondent']); ?></td>
                        <td dir="ltr" style="text-align:right; font-size:0.85rem; color:#666;"><?php echo htmlspecialchars($doc['created_at'] ?? $doc['doc_date']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
    @media print {
        body * { visibility: hidden; }
        #printableArea, #printableArea * { visibility: visible; }
        #printableArea { position: absolute; left: 0; top: 0; width: 100%; box-shadow: none; border: none; }
        .print-only-header { display: block !important; border-bottom: 2px solid #000; margin-bottom: 20px; }
        .lib-table th { background: transparent !important; color: #000 !important; border-bottom: 2px solid #000; }
        .lib-table th, .lib-table td { padding: 8px !important; border-color: #ddd !important; }
        a[href]:after { content: none !important; }
        .lib-badge { border: 1px solid #000; color: #000 !important; background: transparent !important; }
    }
</style>

<?php require_once 'includes/layout_end.php'; ?>
