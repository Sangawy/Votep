<?php
session_start();
if (!isset($_SESSION['user_id'])) die("Access Denied");

require_once 'config.php';
$conn = getDB();

$csvFile = 'f:/laragon\www/pirxdry/File/لیستی ناوی ئەندامان داتا بەیس.csv';

// Handle AJAX Update
if (isset($_POST['ajax_update'])) {
    $m_id = (int)$_POST['member_id'];
    $new_phone = trim($_POST['phone']);
    $stmt = $conn->prepare("UPDATE members SET phone = ? WHERE id = ?");
    $stmt->bind_param("si", $new_phone, $m_id);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error']);
    }
    exit;
}

// Super Normalization (No spaces, consistent characters)
function superNormalize($str) {
    $map = ['ێ'=>'ی','ي'=>'ی','ى'=>'ی','ك'=>'ک','ة'=>'ه','ە'=>'ه','ڕ'=>'ر','ڵ'=>'ل','ۆ'=>'و','أ'=>'ا','إ'=>'ا','آ'=>'ا',' '=>''];
    $titles = ['السيد', 'السید', 'سید', 'ملا', 'مەلا', 'شێخ', 'شیخ'];
    $str = str_replace($titles, '', $str);
    $str = str_replace(array_keys($map), array_values($map), $str);
    return trim($str);
}

$page_title = 'بەراوردکردنی ژمارەی مۆبایلەکان';
require_once 'includes/layout.php';
?>

<style>
    .sync-card {
        background: #fff;
        border-radius: 20px;
        box-shadow: 0 15px 50px rgba(0,0,0,0.1);
        padding: 30px;
        margin-top: 20px;
    }
    .sync-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0 12px;
    }
    .sync-table th {
        background: #f8f9fa;
        padding: 15px;
        text-align: right;
        color: #1a5653;
        font-weight: bold;
        border-bottom: 2px solid #eee;
    }
    .sync-row {
        background: #fff;
        transition: all 0.3s;
        box-shadow: 0 2px 10px rgba(0,0,0,0.02);
    }
    .sync-row:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        background: #fdfcf8;
    }
    .sync-row td {
        padding: 15px;
        border-top: 1px solid #f1f1f1;
        border-bottom: 1px solid #f1f1f1;
    }
    .sync-row td:first-child { border-right: 1px solid #f1f1f1; border-top-right-radius: 12px; border-bottom-right-radius: 12px; }
    .sync-row td:last-child { border-left: 1px solid #f1f1f1; border-top-left-radius: 12px; border-bottom-left-radius: 12px; }
    
    .status-badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: bold;
    }
    .status-match { background: #e6f4ea; color: #1e7e34; }
    .status-mismatch { background: #fff3cd; color: #856404; }
    .status-new { background: #f0f4f7; color: #0c5460; }

    .name-diff {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .csv-label { font-size: 0.75rem; color: #888; margin-bottom: -2px; }
    .db-val { font-weight: bold; color: #1a5653; }
    .csv-val { color: #d63384; font-style: italic; }
    
    .phone-input {
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 8px;
        width: 140px;
        font-family: inherit;
        text-align: center;
    }
    .update-btn {
        background: var(--gold);
        color: #fff;
        border: none;
        padding: 8px 20px;
        border-radius: 10px;
        cursor: pointer;
        font-weight: bold;
        transition: 0.3s;
    }
    .update-btn:hover { background: #d4af37; transform: scale(1.05); }
    .update-btn.done { background: #28a745; pointer-events: none; }
</style>

<div class="sync-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <div>
            <h2 style="margin:0; color: #1a5653;">🔍 بەراوردکردنی زانیارییەکان</h2>
            <p style="color: #666; margin-top: 5px;">بەراوردکردنی ناوی ناو ئێکسڵ (CSV) لەگەڵ داتابەیس بۆ دڵنیابوونەوە لە ژمارە مۆبایلەکان.</p>
        </div>
        <div style="text-align: left; display: flex; gap: 10px;">
            <button id="bulkUpdateBtn" class="lib-btn lib-btn-primary" onclick="bulkUpdateAll()" style="background: #28a745; display: none;">
                <i class="fas fa-check-double"></i> نوێکردنەوەی هەموو دۆزراوەکان
            </button>
            <a href="members_list.php" class="lib-btn" style="background: #eee; color: #333;">بگەڕێوە</a>
        </div>
    </div>
    
    <div id="bulkProgress" style="display: none; margin-bottom: 20px; padding: 15px; background: #e8f5e9; border-radius: 12px; border-right: 5px solid #2e7d32;">
        <span id="progressText" style="font-weight: bold; color: #1b5e20;">خەریکی نوێکردنەوەی هەمووان... (0/0)</span>
    </div>

    <table class="sync-table">
        <thead>
            <tr>
                <th>ناوی ئەندام (بەراورد)</th>
                <th>بنەماڵە</th>
                <th>مۆبایلی ئێستا</th>
                <th>مۆبایلی نوێ (CSV)</th>
                <th style="text-align: center;">کردار</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $handle = fopen($csvFile, "r");
            $rowCount = 0;
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $rowCount++;
                
                $csv_name = trim($data[1] ?? '');
                $csv_family = trim($data[4] ?? '');
                $csv_phone = trim($data[5] ?? '');
                
                if (empty($csv_name)) continue;

                // Format phone
                if (!empty($csv_phone) && $csv_phone[0] !== '0' && strlen($csv_phone) >= 10) {
                    $csv_phone = '0' . $csv_phone;
                }

                $norm_name = superNormalize($csv_name);
                
                // 1. Broad Search: Match by normalized full name (no spaces)
                $stmt = $conn->prepare("SELECT m.id, m.full_name, m.phone as db_phone, f.family_name as db_family 
                                        FROM members m 
                                        LEFT JOIN families f ON m.family_id = f.id 
                                        WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(m.normalized_name, 'ێ', 'ی'), 'ي', 'ی'), 'ى', 'ی'), 'ك', 'ک'), 'ة', 'ه'), 'ە', 'ه'), 'ڕ', 'ر'), 'ڵ', 'ل'), 'ۆ', 'و'), 'أ', 'ا'), 'إ', 'ا'), ' ', '') 
                                        LIKE CONCAT('%', ?, '%') LIMIT 1");
                $stmt->bind_param("s", $norm_name);
                $stmt->execute();
                $res = $stmt->get_result();
                $db = $res->fetch_assoc();
                $stmt->close();

                // 2. Fallback: Match by first 3 parts if broad search fails
                if (!$db) {
                    $parts = preg_split('/\s+/', trim($csv_name), -1, PREG_SPLIT_NO_EMPTY);
                    if (count($parts) >= 2) {
                        $f2 = superNormalize($parts[0] . $parts[1]);
                        $stmt = $conn->prepare("SELECT m.id, m.full_name, m.phone as db_phone, f.family_name as db_family 
                                                FROM members m 
                                                LEFT JOIN families f ON m.family_id = f.id 
                                                WHERE REPLACE(CONCAT(m.first_name, m.father_name), ' ', '') LIKE CONCAT('%', ?, '%') LIMIT 1");
                        $stmt->bind_param("s", $f2);
                        $stmt->execute();
                        $res = $stmt->get_result();
                        $db = $res->fetch_assoc();
                        $stmt->close();
                    }
                }

                $row_class = $db ? 'status-match' : 'status-new';
                ?>
                <tr class="sync-row" id="row-<?php echo $rowCount; ?>">
                    <td>
                        <div class="name-diff">
                            <span class="db-val"><?php echo $db ? htmlspecialchars($db['full_name']) : '<span style="color:#dc3545">نەدۆزرایەوە</span>'; ?></span>
                            <?php if ($db && superNormalize($db['full_name']) !== $norm_name): ?>
                                <span class="csv-val"><?php echo htmlspecialchars($csv_name); ?> (CSV)</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <div class="name-diff">
                            <span class="db-val"><?php echo $db ? htmlspecialchars($db['db_family'] ?: '-') : '-'; ?></span>
                            <?php if ($db && superNormalize($db['db_family']) !== superNormalize($csv_family)): ?>
                                <span class="csv-val"><?php echo htmlspecialchars($csv_family); ?> (CSV)</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td style="text-align: center; color: #888;">
                        <?php echo $db ? htmlspecialchars($db['db_phone'] ?: '-') : '-'; ?>
                    </td>
                    <td>
                        <input type="text" value="<?php echo htmlspecialchars($csv_phone); ?>" class="phone-input" id="phone-<?php echo $rowCount; ?>">
                    </td>
                    <td style="text-align: center;">
                        <?php if ($db): ?>
                            <button class="update-btn" onclick="updatePhone(<?php echo $db['id']; ?>, 'phone-<?php echo $rowCount; ?>', this)">
                                <i class="fas fa-sync"></i> نوێکردنەوە
                            </button>
                        <?php else: ?>
                            <span class="status-badge status-new">ئەندامی نوێ</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php
            }
            fclose($handle);
            ?>
        </tbody>
    </table>
</div>

<script>
function updatePhone(memberId, inputId, btn) {
    const phone = document.getElementById(inputId).value;
    
    // Simple AJAX
    const formData = new FormData();
    formData.append('ajax_update', '1');
    formData.append('member_id', memberId);
    formData.append('phone', phone);

    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    
    fetch('phone_sync_ui.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            btn.innerHTML = '✓ سەرکەوتوو';
            btn.classList.add('done');
            btn.closest('tr').style.opacity = '0.6';
            btn.closest('tr').style.background = '#f0fff4';
        } else {
            alert('هەڵەیەک ڕوویدا');
            btn.innerHTML = '<i class="fas fa-sync"></i> نوێکردنەوە';
        }
    });
}

function bulkUpdateAll() {
    const rows = document.querySelectorAll('.sync-row');
    const updateButtons = [];
    
    rows.forEach(row => {
        const btn = row.querySelector('.update-btn:not(.done)');
        if (btn) {
            const memberId = btn.getAttribute('onclick').match(/updatePhone\((\d+)/)[1];
            const inputId = btn.getAttribute('onclick').match(/'([^']+)'/)[1];
            updateButtons.push({ memberId, inputId, btn });
        }
    });

    if (updateButtons.length === 0) {
        alert('هیچ ئەندامێکی نوێ بۆ نوێکردنەوە نەدۆزرایەوە.');
        return;
    }

    if (!confirm(`ئایا دڵنیای لە نوێکردنەوەی ${updateButtons.length} ئەندام بە یەکجار؟`)) return;

    const progressDiv = document.getElementById('bulkProgress');
    const progressText = document.getElementById('progressText');
    const bulkBtn = document.getElementById('bulkUpdateBtn');
    
    progressDiv.style.display = 'block';
    bulkBtn.disabled = true;
    bulkBtn.style.opacity = '0.5';

    let completed = 0;
    const total = updateButtons.length;

    function processNext() {
        if (completed < total) {
            const item = updateButtons[completed];
            const phone = document.getElementById(item.inputId).value;
            
            const formData = new FormData();
            formData.append('ajax_update', '1');
            formData.append('member_id', item.member_id);
            formData.append('phone', phone);

            item.btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            fetch('phone_sync_ui.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    item.btn.innerHTML = '✓';
                    item.btn.classList.add('done');
                    item.btn.closest('tr').style.opacity = '0.6';
                    item.btn.closest('tr').style.background = '#f0fff4';
                }
                completed++;
                progressText.innerText = `خەریکی نوێکردنەوەی هەمووان... (${completed}/${total})`;
                processNext();
            });
        } else {
            progressText.innerText = `✅ هەموو ${total} ئەندامەکە بە سەرکەوتوویی نوێکرانەوە!`;
            setTimeout(() => { progressDiv.style.display = 'none'; }, 5000);
        }
    }

    processNext();
}

document.addEventListener('DOMContentLoaded', function() {
    const hasUpdates = document.querySelector('.update-btn');
    if (hasUpdates) {
        document.getElementById('bulkUpdateBtn').style.display = 'block';
    }
});
</script>
