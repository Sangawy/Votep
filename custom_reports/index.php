<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

// Handle POST request using Post/Redirect/Get (PRG) pattern to avoid "Confirm Form Resubmission"
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['report_config'] = [
        'fields' => $_POST['fields'] ?? [],
        'checkbox_fields' => $_POST['checkbox_fields'] ?? [],
        'sort_by' => $_POST['sort_by'] ?? 'priority_desc',
    ];
    header('Location: index.php');
    exit;
}

require_once '../config.php';
$conn = getDB();

// 1. Define all possible fields and their labels
$ALL_FIELDS_MAP = [
    'rank_name' => 'پلە',
    'full_name' => 'ناوی تەواو',
    'position' => 'پایە',
    'birth_year' => 'لەدایکبوون',
    'education' => 'بڕوانامە',
    'phone' => 'مۆبایل',
    'unit_name' => 'یەکە',
    'birth_place' => 'شوێنی لەدایکبوون',
    'id_number' => 'ژمارەی ناسنامە',
    'city' => 'شار',
    'town' => 'قەزا',
    'district' => 'ناحیه',
    'village' => 'گوند',
    'branch' => 'ڕشتە',
    'last_promotion_date' => 'دوا پلە',
    'promotion_due_date' => 'شایستەی داهاتوو',
    'promotion_note' => 'تێبینی پلەبەرزکردنەوە',
    'absence_penalty_days' => 'ڕۆژەکانی سزای غیاب',
    'appointment_order' => 'ژ. فەرمانی دامەزاردن',
    'appointment_date' => 'بەرواری فەرمانی دامەزاردن',
    'civil_id' => 'ژمارەی ناسنامەی مەدەنی',
    'form_number' => 'ژمارەی فۆڕمی خۆراک',
    'voting_number' => 'ژمارەی دەنگدان',
    'biometric_number' => 'ژمارەی بایۆ مەتری',
    'marital_status' => 'باری خێزانی',
    'children_count' => 'ژمارەی منداڵەکان',
    'military_branch_sinf' => 'صنف (جۆری چەک)',
    'military_education' => 'بڕوانامەی سەربازی',
    'health_status' => 'باری تەندروستی',
    'physical_fitness' => 'لێهاتوویی جەستەیی',
    'blood_group' => 'گروپی خوێن',
    'languages' => 'زمانەکان',
    'injuries' => 'برینداربوون / کەمئەندامی',
    'honors_medals' => 'مەدالیا و سوپاسنامەکان',
    'notes' => 'تێبینی',
];

// 2. Dynamically add any other columns from the database
$EXCLUDE_FIELDS = ['id', 'rank_id', 'unit_id', 'profile_image', 'archive_folder', 'created_at', 'updated_at'];
$columns_result = $conn->query("DESCRIBE staff_members");
while ($col = $columns_result->fetch_assoc()) {
    $fieldName = $col['Field'];
    if (!in_array($fieldName, $EXCLUDE_FIELDS) && !isset($ALL_FIELDS_MAP[$fieldName])) {
        $ALL_FIELDS_MAP[$fieldName] = ucfirst(str_replace('_', ' ', $fieldName));
    }
}

// 3. Handle selection and order
// Prioritize session config (from PRG redirect), then fallback to defaults
$config = $_SESSION['report_config'] ?? [];
$selected = $config['fields'] ?? [];

if (!is_array($selected)) {
    $selected = [];
}

// Default selection and order if none chosen
if (empty($selected) && empty($config)) {
    $selected = ['rank_name', 'full_name', 'position', 'birth_year'];
}

// 4. Determine which fields to show in the table (Preview)
$visible_fields = [];
foreach ($selected as $field) {
    if (isset($ALL_FIELDS_MAP[$field])) {
        $visible_fields[$field] = $ALL_FIELDS_MAP[$field];
    }
}

// Fetch staff data sorted by rank priority
$sort_by = $config['sort_by'] ?? 'priority_desc';

switch ($sort_by) {
    case 'seniority_asc':
        $order_clause = "ORDER BY m.last_promotion_date ASC, m.full_name ASC";
        break;
    case 'age_asc':
        $order_clause = "ORDER BY m.birth_year ASC, m.full_name ASC";
        break;
    case 'education_asc':
        $order_clause = "ORDER BY m.education ASC, m.full_name ASC";
        break;
    case 'priority_desc':
    default:
        $order_clause = "ORDER BY r.priority DESC, m.full_name ASC";
        break;
}

$query = "SELECT m.*, r.rank_name, r.priority, u.unit_name 
          FROM staff_members m 
          JOIN staff_ranks r ON m.rank_id = r.id 
          JOIN staff_units u ON m.unit_id = u.id 
          $order_clause";
$result = $conn->query($query);
$staff_list = [];
while ($row = $result->fetch_assoc()) {
    $staff_list[] = $row;
}

$page_title = 'دروستکردنی ڕاپۆرتی داینامیکی';
$active_nav = 'custom_reports';
$sys_module = 'nusinga';
require_once '../includes/layout.php';
?>

<style>
    .sort-container {
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 15px;
        margin-top: 20px;
        display: none;
    }
    .sort-list {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        list-style: none;
        padding: 0;
        margin: 10px 0;
    }
    .sort-item {
        background: #f0f0f0;
        border: 1px solid #ccc;
        padding: 8px 15px;
        border-radius: 20px;
        cursor: grab;
        user-select: none;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
        transition: background 0.2s;
    }
    .sort-item:active { cursor: grabbing; }
    .sort-item.dragging { opacity: 0.5; background: #e0e0e0; }

    .collapsible-content {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease-out;
    }
    .collapsible-content.show {
        max-height: 1000px; /* Large enough for all checkboxes */
    }
    .toggle-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        cursor: pointer;
        background: #f0f0f0;
        padding: 10px 15px;
        border-radius: 8px;
        margin-bottom: 10px;
    }
    .toggle-header:hover { background: #e8e8e8; }
    .chevron-icon { transition: transform 0.3s; }
    .chevron-icon.rotate { transform: rotate(180deg); }
</style>

<div class="lib-card">
    <div class="card-header">
        <h3>📊 دروستکردنی ڕاپۆرتی داینامیکی</h3>
    </div>
    
    <div style="padding: 20px; background: #f9f9f9; border-bottom: 1px solid #eee;">
        <form method="POST" id="reportForm">
            <div style="margin-bottom: 20px;">
                <div class="toggle-header" onclick="toggleSection('columnSelectionArea', 'chevron1')">
                    <label style="font-weight: bold; margin: 0; cursor: pointer;">١. هەڵبژاردنی ستوونەکان:</label>
                    <span id="chevron1" class="chevron-icon">▼</span>
                </div>
                
                <div id="columnSelectionArea" class="collapsible-content">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; padding: 15px; background: #fff; border: 1px solid #eee; border-radius: 0 0 8px 8px;">
                <?php foreach ($ALL_FIELDS_MAP as $field => $label): ?>
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" class="field-checkbox" data-field="<?php echo $field; ?>" data-label="<?php echo $label; ?>" name="checkbox_fields[]" value="<?php echo $field; ?>" <?php echo in_array($field, $selected) ? 'checked' : ''; ?>>
                        <?php echo $label; ?>
                    </label>
                    <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div id="sortSection" class="sort-container">
                <label style="font-weight: bold; margin-bottom: 10px; display: block;">٢. ڕێکخستنی شوێنی ستوونەکان (بڕوانە و بجێبهەڵوێڵە):</label>
                <ul id="sortList" class="sort-list">
                    <!-- JS will populate this -->
                </ul>
                <!-- Hidden inputs to preserve order in POST -->
                <div id="hiddenInputs"></div>
            </div>

            <div style="margin-top: 20px; padding: 15px; background: #eee; border-radius: 8px; display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                <div>
                    <label style="font-weight: bold; display: block; margin-bottom: 10px;">٣. ڕێکخستنی سۆرتکردن:</label>
                    <select name="sort_by" id="sortBy" style="width: 100%; padding: 8px; border-radius: 5px;">
                        <option value="priority_desc" <?php echo ($sort_by === 'priority_desc') ? 'selected' : ''; ?>>سۆرت بەپێی پلە (بەرز بۆ نزم)</option>
                        <option value="seniority_asc" <?php echo ($sort_by === 'seniority_asc') ? 'selected' : ''; ?>>سۆرت بەپێی ئەقدەمییەت (کۆنترین بەرواری دوا پلە)</option>
                        <option value="age_asc" <?php echo ($sort_by === 'age_asc') ? 'selected' : ''; ?>>سۆرت بەپێی تەمەن (لە گەورەوە بۆ بچووک)</option>
                        <option value="education_asc" <?php echo ($sort_by === 'education_asc') ? 'selected' : ''; ?>>سۆرت بەپێی بڕوانامە</option>
                    </select>
                </div>
                <div>
                    <label style="font-weight: bold; display: block; margin-bottom: 10px;">٤. بژاردەکانی چاپ:</label>
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-bottom: 5px;">
                        <input type="checkbox" name="show_watermark" id="showWatermark"> نیشانەی ئاو (Watermark)
                    </label>
                    <div style="margin-top: 10px; font-size: 0.9em; font-weight: bold;">خانەی واژۆکان:</div>
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="sig_preparer" checked> ئامادەکار
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="sig_reviewer" checked> وردبینیکار
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="sig_manager" checked> بەڕێوەبەر
                    </label>
                </div>
            </div>
            
            <div style="display: flex; align-items: flex-end; gap: 10px; margin-top: 20px;">
                <button type="submit" class="lib-btn lib-btn-gold" style="flex: 1;">🔍 نوێکردنەوەی پێشنیاز</button>
                <button type="button" onclick="printReport()" class="lib-btn lib-btn-primary" style="flex: 1;">🖨️ چاپکردنی ڕاپۆرت</button>
            </div>
        </form>
    </div>

    <div class="lib-table-wrapper">
        <table class="lib-table">
            <thead>
                <tr>
                    <th>ڕیز</th>
                    <?php foreach ($visible_fields as $field => $label): ?>
                        <th><?php echo $label; ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($staff_list)): ?>
                    <tr><td colspan="<?php echo count($visible_fields) + 1; ?>" style="text-align:center; padding: 50px;">هیچ زانیارییەک نەدۆزرایەوە</td></tr>
                <?php else: ?>
                    <?php foreach ($staff_list as $index => $s): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <?php foreach ($visible_fields as $field => $label): ?>
                                <td><?php echo htmlspecialchars($s[$field] ?? ''); ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function toggleSection(id, chevronId) {
    const content = document.getElementById(id);
    const chevron = document.getElementById(chevronId);
    content.classList.toggle('show');
    chevron.classList.toggle('rotate');
}

document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('.field-checkbox');
    const sortList = document.getElementById('sortList');
    const sortSection = document.getElementById('sortSection');
    const hiddenInputsDiv = document.getElementById('hiddenInputs');

    function updateSortList() {
        sortList.innerHTML = '';
        hiddenInputsDiv.innerHTML = '';
        
        // The order should follow the current order of elements in the sortList if we are just updating checkboxes,
        // but since the page reloads, we rely on the PHP $selected order.
        // For the initial load, we use the order from PHP.
        
        const selected = Array.from(checkboxes)
            .filter(cb => cb.checked)
            .map(cb => ({ field: cb.getAttribute('data-field'), label: cb.getAttribute('data-label') }));

        if (selected.length === 0) {
            sortSection.style.display = 'none';
            return;
        }

        sortSection.style.display = 'block';

        // To prevent order reset on simple checkbox toggle, we should try to maintain 
        // the order from the current DOM if it exists.
        const currentOrder = Array.from(sortList.querySelectorAll('.sort-item')).map(el => el.dataset.field);
        
        // Re-order selected items based on current order, then append new ones
        const finalOrder = [];
        currentOrder.forEach(field => {
            if (selected.find(s => s.field === field)) {
                finalOrder.push(selected.find(s => s.field === field));
            }
        });
        selected.forEach(item => {
            if (!finalOrder.find(f => f.field === item.field)) {
                finalOrder.push(item);
            }
        });

        finalOrder.forEach(item => {
            const li = document.createElement('li');
            li.className = 'sort-item';
            li.draggable = true;
            li.dataset.field = item.field;
            li.innerHTML = `<span>☰</span> ${item.label}`;
            
            li.addEventListener('dragstart', () => li.classList.add('dragging'));
            li.addEventListener('dragend', () => li.classList.remove('dragging'));
            
            sortList.appendChild(li);
        });
        updateHiddenInputs();
    }

    sortList.addEventListener('dragover', e => {
        e.preventDefault();
        const afterElement = getDragAfterElement(sortList, e.clientX);
        const draggable = document.querySelector('.dragging');
        if (afterElement == null) {
            sortList.appendChild(draggable);
        } else {
            sortList.insertBefore(draggable, afterElement);
        }
        updateHiddenInputs();
    });

    function getDragAfterElement(container, x) {
        const draggableElements = [...container.querySelectorAll('.sort-item:not(.dragging)')];
        return draggableElements.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = x - box.left - box.width / 2;
            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: child };
            } else {
                return closest;
            }
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    }

    function updateHiddenInputs() {
        hiddenInputsDiv.innerHTML = '';
        const items = sortList.querySelectorAll('.sort-item');
        items.forEach(item => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'fields[]';
            input.value = item.dataset.field;
            hiddenInputsDiv.appendChild(input);
        });
    }

    checkboxes.forEach(cb => {
        cb.addEventListener('change', () => {
            updateSortList();
        });
    });

    // Initial setup: the order in the sortList should match PHP $selected order on first load
    // Because we use hidden inputs, PHP $selected is already ordered.
    // However, the checkboxes are in ALL_FIELDS_MAP order.
    // We need to force the initial sortList to match the PHP $selected order.
    
    // This is handled by the initial updateSortList call, but we need to make sure 
    // it doesn't just use checkbox order.
    
    // To fix the initial load order:
    const phpSelectedOrder = <?php echo json_encode($selected); ?>;
    if (phpSelectedOrder.length > 0) {
        sortList.innerHTML = '';
        phpSelectedOrder.forEach(field => {
            const cb = document.querySelector(`.field-checkbox[data-field="${field}"]`);
            if (cb) {
                const li = document.createElement('li');
                li.className = 'sort-item';
                li.draggable = true;
                li.dataset.field = field;
                li.innerHTML = `<span>☰</span> ${cb.getAttribute('data-label')}`;
                li.addEventListener('dragstart', () => li.classList.add('dragging'));
                li.addEventListener('dragend', () => li.classList.remove('dragging'));
                sortList.appendChild(li);
            }
        });
        sortSection.style.display = 'block';
        updateHiddenInputs();
    } else {
        updateSortList();
    }
});

function printReport() {
    const hiddenInputs = document.querySelectorAll('#hiddenInputs input');
    const selectedFields = Array.from(hiddenInputs).map(input => input.value);
    
    if (selectedFields.length === 0) {
        alert('تکایە لانی کەم یەک ستوون هەڵبژێرە بۆ چاپکردن');
        return;
    }
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'print.php';
    form.target = '_blank';
    
    selectedFields.forEach(field => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'fields[]';
        input.value = field;
        form.appendChild(input);
    });

    // Add print settings
    const settings = [
        { name: 'sort_by', id: 'sortBy' },
        { name: 'show_watermark', id: 'showWatermark', type: 'checkbox' },
        { name: 'sig_preparer', type: 'checkbox', selector: 'input[name="sig_preparer"]' },
        { name: 'sig_reviewer', type: 'checkbox', selector: 'input[name="sig_reviewer"]' },
        { name: 'sig_manager', type: 'checkbox', selector: 'input[name="sig_manager"]' },
    ];

    settings.forEach(s => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = s.name;
        
        let value = '';
        if (s.type === 'checkbox') {
            const el = s.id ? document.getElementById(s.id) : document.querySelector(s.selector);
            value = el && el.checked ? '1' : '0';
        } else {
            const el = document.getElementById(s.id);
            value = el ? el.value : '';
        }
        
        input.value = value;
        form.appendChild(input);
    });
    
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}
</script>

<?php require_once '../includes/layout_end.php'; ?>
