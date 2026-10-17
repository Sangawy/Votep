<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
$conn = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_family'])) {
    $name = trim($_POST['family_name']);
    $area = trim($_POST['origin_area']);
    $ancestor = trim($_POST['ancestor_name']);
    $history = trim($_POST['history']);
    $parent = $_POST['parent_family_id'] ?: null;
    $id = isset($_POST['family_id']) ? (int)$_POST['family_id'] : 0;

    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE families SET family_name=?, origin_area=?, ancestor_name=?, history=?, parent_family_id=? WHERE id=?");
        $stmt->bind_param("ssssii", $name, $area, $ancestor, $history, $parent, $id);
    } else {
        $stmt = $conn->prepare("INSERT INTO families (family_name, origin_area, ancestor_name, history, parent_family_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", $name, $area, $ancestor, $history, $parent);
    }
    $stmt->execute();
    header("Location: families_manage.php");
    exit;
}

if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];

    // Check if family has members
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM members WHERE family_id = ?");
    $stmt->bind_param("i", $del_id);
    $stmt->execute();
    $check_res = $stmt->get_result()->fetch_assoc();
    $members_cnt = (int)($check_res['cnt'] ?? 0);
    $stmt->close();

    if ($members_cnt > 0) {
        $_SESSION['flash_error'] = "ناتوانرێت ئەم بنەماڵەیە بسڕدرێتەوە چونکە ($members_cnt) ئەندامی تێدایە. سەرەتا پێویستە سەرجەم ئەندامەکانی بسڕدرێنەوە.";
        header("Location: families_manage.php");
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM families WHERE id = ?");
    $stmt->bind_param("i", $del_id);
    $stmt->execute();
    $stmt->close();
    $_SESSION['flash_success'] = "بنەماڵەکە بە سەرکەوتوویی سڕایەوە.";
    header("Location: families_manage.php");
    exit;
}

$edit_family = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $res = $conn->query("SELECT * FROM families WHERE id = $edit_id");
    $edit_family = $res->fetch_assoc();
}

$families = [];
$res = $conn->query("SELECT f.*, p.family_name as parent_name, 
                    (SELECT COUNT(*) FROM members WHERE family_id = f.id) as members_count 
                    FROM families f 
                    LEFT JOIN families p ON f.parent_family_id = p.id 
                    ORDER BY members_count DESC, f.family_name ASC");
while ($row = $res->fetch_assoc()) $families[] = $row;

$page_title = 'بەشی بنەماڵەکان';
$active_nav = 'families_manage';
$sys_module = 'lineage';
require_once 'includes/layout.php';
?>

<style>
    .folder-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 25px;
        padding: 20px;
    }
    .family-folder {
        background: #fff;
        border-radius: 15px;
        padding: 30px 15px;
        text-align: center;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        transition: all 0.3s ease;
        cursor: pointer;
        border: 1px solid #eee;
        position: relative;
        text-decoration: none;
        display: block;
    }
    .family-folder:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        border-color: var(--gold);
    }
    .folder-icon {
        font-size: 3.5rem;
        color: #f1c40f;
        margin-bottom: 15px;
        display: block;
    }
    .folder-name {
        font-family: 'Rabar022', sans-serif;
        font-weight: bold;
        color: #1a5653;
        font-size: 1.1rem;
        margin-bottom: 5px;
        display: block;
    }
    .folder-meta {
        font-size: 0.8rem;
        color: #666;
        display: block;
    }
    .member-badge {
        position: absolute;
        top: 15px;
        right: 15px;
        background: var(--gold);
        color: #fff;
        font-size: 0.75rem;
        font-weight: bold;
        padding: 4px 10px;
        border-radius: 20px;
        box-shadow: 0 2px 8px rgba(212, 175, 55, 0.4);
    }
    .folder-actions {
        position: absolute;
        top: 15px;
        left: 15px;
        display: flex;
        gap: 5px;
        opacity: 0;
        transition: opacity 0.3s;
    }
    .family-folder:hover .folder-actions {
        opacity: 1;
    }
    .action-btn {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255,255,255,0.95);
        border: 1px solid #ddd;
        font-size: 0.75rem;
        color: #333;
        transition: all 0.2s;
    }
    .action-btn:hover {
        background: #dc3545;
        color: #fff;
        border-color: #dc3545;
    }
    .action-btn.edit:hover {
        background: #2980b9;
        border-color: #2980b9;
    }
    .action-btn.tree:hover {
        background: #27ae60;
        border-color: #27ae60;
    }

    .search-box-wrapper {
        position: relative;
    }
    .search-box-wrapper input {
        width: 100%;
        padding: 11px 45px 11px 38px;
        border-radius: 25px;
        border: 1.5px solid #cbd5e1;
        background: #fff;
        outline: none;
        font-family: inherit;
        font-size: 0.92rem;
        transition: all 0.2s ease;
        box-shadow: 0 2px 6px rgba(0,0,0,0.03);
    }
    .search-box-wrapper input:focus {
        border-color: #1a5653;
        box-shadow: 0 0 0 3px rgba(26, 86, 83, 0.15);
    }
    .search-box-wrapper i.search-icon {
        position: absolute;
        right: 16px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 1rem;
        pointer-events: none;
    }
    .search-clear-btn {
        display: none;
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: #94a3b8;
        cursor: pointer;
        font-size: 1rem;
        padding: 0;
    }
    .search-clear-btn:hover {
        color: #ef4444;
    }
    
    /* Modal Styles */
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        display: none;
        justify-content: center;
        align-items: center;
        z-index: 10000;
        backdrop-filter: blur(5px);
    }
    .modal-content {
        background: #fff;
        padding: 30px;
        border-radius: 20px;
        width: 100%;
        max-width: 600px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    }
</style>

<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px; margin-bottom: 25px; padding: 0 20px;">
    <div style="display: flex; align-items: center; gap: 15px;">
        <h2 style="margin: 0; font-size: 1.8rem; color: #1a5653; display: flex; align-items: center; gap: 10px;">
            بەشەکانی بنەماڵە
        </h2>
        <span style="background: #e6f4ea; color: #137333; font-weight: bold; font-size: 0.85rem; padding: 4px 12px; border-radius: 20px; border: 1px solid #ceead6;">
            <span id="visibleFamiliesCount"><?php echo count($families); ?></span> بنەماڵە
        </span>
    </div>

    <!-- Search Box for Families -->
    <div class="search-box-wrapper" style="flex: 1; max-width: 380px; min-width: 250px;">
        <svg class="search-icon" style="position: absolute; right: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; pointer-events: none;" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
        <input type="text" id="familySearchInput" oninput="filterFamilies(this.value)" placeholder="گەڕان بەدوای ناوی بنەماڵە..." autocomplete="off">
        <button type="button" id="clearSearchBtn" class="search-clear-btn" onclick="clearFamilySearch()" title="سڕینەوەی دەق">&times;</button>
    </div>

    <button onclick="openFamilyModal()" class="lib-btn lib-btn-primary" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; font-weight: bold; font-size: 0.95rem; border-radius: 10px;">
        <span>➕</span> زیادکردنی بنەماڵە
    </button>
</div>

<div class="folder-grid">
    <?php foreach ($families as $f): ?>
    <a href="family_view.php?id=<?php echo $f['id']; ?>" 
       class="family-folder"
       data-name="<?php echo htmlspecialchars($f['family_name'], ENT_QUOTES); ?>"
       data-area="<?php echo htmlspecialchars($f['origin_area'] ?? '', ENT_QUOTES); ?>">
        <div class="member-badge">
            👥 <?php echo number_format($f['members_count']); ?>
        </div>
        <div class="folder-actions">
            <button type="button" onclick="event.preventDefault(); event.stopPropagation(); window.location.href='lineage_tree.php?family_id=<?php echo $f['id']; ?>';" class="action-btn tree" title="داری خێزانی ئەم بنەماڵەیە">🌳</button>
            <button type="button" onclick="event.preventDefault(); event.stopPropagation(); openFamilyModal(<?php echo htmlspecialchars(json_encode($f)); ?>)" class="action-btn edit" title="دەستکاری">✏️</button>
            <button type="button" onclick="event.preventDefault(); event.stopPropagation(); confirmDelete(<?php echo $f['id']; ?>, <?php echo (int)$f['members_count']; ?>, '<?php echo addslashes(htmlspecialchars($f['family_name'], ENT_QUOTES)); ?>')" class="action-btn" title="سڕینەوە">🗑️</button>
        </div>
        <i class="fas fa-folder folder-icon"></i>
        <span class="folder-name"><?php echo htmlspecialchars($f['family_name']); ?></span>
        <span class="folder-meta"><?php echo htmlspecialchars($f['origin_area'] ?: 'ناوچەی نادیار'); ?></span>
    </a>
    <?php endforeach; ?>

    <div id="noFamilyMatches" style="display: none; grid-column: 1 / -1; padding: 50px 20px; text-align: center; color: #888; background: #fff; border-radius: 15px; border: 2px dashed #e2e8f0;">
        <svg style="margin: 0 auto 12px; display: block; color: #cbd5e1;" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
        <p style="font-size: 1.1rem; margin: 0; font-weight: bold; color: #64748b;">هیچ بنەماڵەیەک بەم ناوە نەدۆزرایەوە</p>
    </div>
</div>

<!-- Family Modal -->
<div id="familyModal" class="modal-overlay">
    <div class="modal-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 id="modalTitle" style="margin: 0;">➕ زیادکردنی بنەماڵەی نوێ</h3>
            <button onclick="closeFamilyModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="family_id" id="f_id" value="0">
            <div class="lib-form-grid">
                <div class="lib-field">
                    <label>ناوی بنەماڵە:</label>
                    <input type="text" name="family_name" id="f_name" required placeholder="بۆ نموونە: بنەماڵەی شێخانی بازیان">
                </div>
                <div class="lib-field">
                    <label>ناوچە (شوێن):</label>
                    <input type="text" name="origin_area" id="f_area" placeholder="بازیان، سلێمانی ...">
                </div>
                <div class="lib-field">
                    <label>باپیرە گەورە (Ancestor):</label>
                    <input type="text" name="ancestor_name" id="f_ancestor">
                </div>
                <div class="lib-field">
                    <label>بنەماڵەی سەرەکی:</label>
                    <select name="parent_family_id" id="f_parent">
                        <option value="">-- هیچ --</option>
                        <?php foreach ($families as $f): ?>
                            <option value="<?php echo $f['id']; ?>"><?php echo htmlspecialchars($f['family_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="lib-field" style="grid-column: 1 / -1;">
                    <label>مێژوو و کورتەی بنەماڵە:</label>
                    <textarea name="history" id="f_history" rows="3"></textarea>
                </div>
            </div>
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" name="save_family" class="lib-btn lib-btn-primary">💾 پاراستنی زانیارییەکان</button>
                <button type="button" onclick="closeFamilyModal()" class="lib-btn" style="background: #eee; color: #333;">پاشگەزبوونەوە</button>
            </div>
        </form>
    </div>
</div>

<script>
function openFamilyModal(family = null) {
    const modal = document.getElementById('familyModal');
    const title = document.getElementById('modalTitle');
    
    if (family) {
        title.innerText = '✏️ دەستکاریکردنی بنەماڵە';
        document.getElementById('f_id').value = family.id;
        document.getElementById('f_name').value = family.family_name;
        document.getElementById('f_area').value = family.origin_area;
        document.getElementById('f_ancestor').value = family.ancestor_name;
        document.getElementById('f_history').value = family.history;
        document.getElementById('f_parent').value = family.parent_family_id || '';
    } else {
        title.innerText = '➕ زیادکردنی بنەماڵەی نوێ';
        document.getElementById('f_id').value = '0';
        document.getElementById('f_name').value = '';
        document.getElementById('f_area').value = '';
        document.getElementById('f_ancestor').value = '';
        document.getElementById('f_history').value = '';
        document.getElementById('f_parent').value = '';
    }
    modal.style.display = 'flex';
}

function closeFamilyModal() {
    document.getElementById('familyModal').style.display = 'none';
}

async function confirmDelete(id, count = 0, name = '') {
    if (count > 0) {
        showAlert(
            'ناتوانرێت بسڕدرێتەوە!',
            `بنەماڵەی (${name}) خاوەنی ${count} ئەندامە.\nبۆ پاراستنی زانیارییەکان، ڕێگەپێدراو نییە بسڕدرێتەوە تاوەکو سەرەتا سەرجەم ئەندامەکانی نەسڕدرێنەوە.`,
            'error'
        );
        return;
    }

    const confirmed = await showConfirm(
        'ئایا دڵنیای؟',
        `ئایا دڵنیای لە سڕینەوەی بنەماڵەی (${name})؟ هیچ ئەندامێکی لەسەر تۆمار نەکراوە.`,
        'warning'
    );
    if (confirmed) {
        window.location.href = 'families_manage.php?delete=' + id;
    }
}

// Show edit modal if coming from edit link (compatibility)
<?php if($edit_family): ?>
window.onload = () => openFamilyModal(<?php echo json_encode($edit_family); ?>);
<?php endif; ?>

<?php if(isset($_SESSION['flash_error'])): ?>
showAlert('ڕێگری لە سڕینەوە!', <?php echo json_encode($_SESSION['flash_error']); ?>, 'error');
<?php unset($_SESSION['flash_error']); endif; ?>

<?php if(isset($_SESSION['flash_success'])): ?>
Toast.fire({ icon: 'success', title: <?php echo json_encode($_SESSION['flash_success']); ?> });
<?php unset($_SESSION['flash_success']); endif; ?>

function normalizeKurdish(str) {
    if (!str) return '';
    return str
        .toString()
        .toLowerCase()
        .replace(/[يى]/g, 'ی')
        .replace(/[ك]/g, 'ک')
        .replace(/[ة]/g, 'ە')
        .replace(/[\u064B-\u065F\u0670]/g, '')
        .replace(/[آأإ]/g, 'ا')
        .replace(/\s+/g, ' ')
        .trim();
}

function filterFamilies(val) {
    const q = normalizeKurdish(val);
    const folders = document.querySelectorAll('.family-folder');
    let visibleCount = 0;

    folders.forEach(card => {
        const name = normalizeKurdish(card.getAttribute('data-name') || '');
        const area = normalizeKurdish(card.getAttribute('data-area') || '');

        if (!q || name.includes(q) || area.includes(q)) {
            card.style.display = '';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });

    const countEl = document.getElementById('visibleFamiliesCount');
    if (countEl) {
        countEl.textContent = visibleCount;
    }

    const clearBtn = document.getElementById('clearSearchBtn');
    if (clearBtn) {
        clearBtn.style.display = val.trim() ? 'block' : 'none';
    }

    const noMatches = document.getElementById('noFamilyMatches');
    if (noMatches) {
        noMatches.style.display = (visibleCount === 0 && folders.length > 0) ? 'block' : 'none';
    }
}

function clearFamilySearch() {
    const input = document.getElementById('familySearchInput');
    if (input) {
        input.value = '';
        filterFamilies('');
        input.focus();
    }
}
</script>

<?php require_once 'includes/layout_end.php'; ?>
