<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
$conn = getDB();

$categories = [];
$authors = [];
$stats = ['books' => 0, 'categories' => 0, 'authors' => 0, 'shelves' => 0];

if ($conn !== null) {
    // Get Categories
    $res = $conn->query("SELECT id, name FROM categories ORDER BY name");
    while ($row = $res->fetch_assoc()) $categories[] = $row;
    
    // Get Authors
    $res = $conn->query("SELECT id, name FROM authors ORDER BY name");
    while ($row = $res->fetch_assoc()) $authors[] = $row;

    // Get Stats
    $stats = [
        'books' => $conn->query("SELECT SUM(quantity) as total FROM books")->fetch_assoc()['total'] ?? 0,
        'categories' => $conn->query("SELECT COUNT(*) as total FROM categories")->fetch_assoc()['total'] ?? 0,
        'authors' => $conn->query("SELECT COUNT(*) as total FROM authors")->fetch_assoc()['total'] ?? 0,
        'shelves' => $conn->query("SELECT COUNT(DISTINCT shelf_code) as total FROM books WHERE shelf_code != ''")->fetch_assoc()['total'] ?? 0
    ];
}

// Layout Config
$page_title = 'ڕاپۆرتەکان';
$active_nav = 'reports';
require_once 'includes/layout.php';
?>

<style>
    .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 24px; margin-bottom: 30px; }
    .stat-card-small { background: #fff; padding: 20px; border-radius: 16px; border: 1px solid var(--border); display: flex; align-items: center; gap: 15px; box-shadow: var(--shadow-sm); }
    .stat-card-small .icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
    .stat-card-small.books .icon { background: rgba(90, 2, 2, 0.1); color: var(--maroon); }
    .stat-card-small.cats .icon { background: rgba(200, 169, 81, 0.1); color: var(--gold); }
    .stat-card-small.authors .icon { background: rgba(0, 150, 136, 0.1); color: #009688; }
    .stat-card-small.shelves .icon { background: rgba(33, 150, 243, 0.1); color: #2196f3; }
    .stat-card-small .info h4 { font-size: 1.5rem; color: #333; line-height: 1; margin-bottom: 5px; }
    .stat-card-small .info p { font-size: 0.85rem; color: var(--text-muted); font-weight: 700; }

    .filters-section { background: #fdfdfd; border-radius: 16px; padding: 25px; border: 1px solid var(--border); margin-bottom: 30px; }
    
    .results-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    .results-table th { background: #f8f8f8; color: var(--maroon); padding: 12px 10px; text-align: right; border: 1px solid #eee; font-weight: 700; font-size: 0.9rem; }
    .results-table td { padding: 12px 10px; border: 1px solid #eee; font-size: 0.9rem; word-break: break-all; }
    
    .print-only { display: none; }

    @media print {
        .lib-sidebar, .lib-topbar, .filters-section, .lib-btn { display: none !important; }
        .lib-main { margin: 0; width: 100%; max-width: 100%; }
        .lib-page { padding: 0; }
        .lib-card { border: none; box-shadow: none; }
        .print-only { display: block; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 3px solid var(--maroon); }
        .results-table th { background: #eee !important; color: #000 !important; }
        body { background: #fff; }
    }
</style>

<div class="stats-row no-print">
    <div class="stat-card-small books">
        <div class="icon">📚</div>
        <div class="info">
            <h4><?php echo number_format($stats['books']); ?></h4>
            <p>کۆی گشتی کتێب</p>
        </div>
    </div>
    <div class="stat-card-small cats">
        <div class="icon">📂</div>
        <div class="info">
            <h4><?php echo number_format($stats['categories']); ?></h4>
            <p>بەشەکان</p>
        </div>
    </div>
    <div class="stat-card-small authors">
        <div class="icon">✍️</div>
        <div class="info">
            <h4><?php echo number_format($stats['authors']); ?></h4>
            <p>نوسەرەکان</p>
        </div>
    </div>
    <div class="stat-card-small shelves">
        <div class="icon">🪜</div>
        <div class="info">
            <h4><?php echo number_format($stats['shelves']); ?></h4>
            <p>ڕەفەکان</p>
        </div>
    </div>
</div>

<div class="lib-card">
    <div class="print-only">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <img src="<?php echo htmlspecialchars($settings['logo']); ?>" style="width: 80px; height: 80px; object-fit: contain;">
            </div>
            <div style="text-align: center;">
                <h2 style="color: var(--maroon);"><?php echo htmlspecialchars($settings['system_name']); ?></h2>
                <h3 style="margin-top: 5px; color: #444;">ڕاپۆرتی گشتی لیستی کتێبەکان</h3>
            </div>
            <div style="text-align: left; font-size: 0.85rem; color: #666;">
                <p>ڕێکەوت: <?php echo date('Y-m-d'); ?></p>
                <p>کات: <?php echo date('H:i'); ?></p>
            </div>
        </div>
    </div>

    <div class="filters-section no-print">
        <h3 style="margin-bottom: 20px; color: var(--maroon); font-size: 1.1rem; font-weight: 700;">🔍 فلتەرکردنی ڕاپۆرت</h3>
        <div class="lib-form-grid">
            <div class="lib-field">
                <label>بەش (Category)</label>
                <select id="rep_category">
                    <option value="">هەموو بەشەکان</option>
                    <?php foreach($categories as $c): ?>
                    <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="lib-field">
                <label>نوسەر (Author)</label>
                <select id="rep_author">
                    <option value="">هەموو نوسەرەکان</option>
                    <?php foreach($authors as $a): ?>
                    <option value="<?php echo $a['id']; ?>"><?php echo htmlspecialchars($a['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="lib-field">
                <label>گەڕان بەدوای ناو یان کۆد</label>
                <input type="text" id="rep_search" placeholder="بنووسە بۆ گەڕان...">
            </div>
        </div>
        <div style="display: flex; gap: 15px; justify-content: flex-end; margin-top: 25px;">
            <button class="lib-btn lib-btn-primary" onclick="generateReport()" style="padding: 12px 30px;">نیشاندانی ئەنجامەکان</button>
            <button class="lib-btn lib-btn-gold" onclick="window.print()" style="padding: 12px 30px;">چاپی ڕاپۆرت</button>
        </div>
    </div>

    <div class="lib-table-wrapper">
        <table class="results-table">
            <thead>
                <tr>
                    <th style="width: 40px; text-align: center;">#</th>
                    <th style="width: 100px;">کۆد</th>
                    <th>ناوی کتێب</th>
                    <th>نوسەر</th>
                    <th>وەرگێڕ</th>
                    <th>بەش</th>
                    <th style="width: 100px; text-align: center;">ڕەفە</th>
                    <th style="width: 60px; text-align: center;">دانە</th>
                </tr>
            </thead>
            <tbody id="reportResults">
                <tr>
                    <td colspan="8" style="text-align:center; padding: 60px; color: #999; font-size: 1.1rem;">تکایە فلتەرەکان دیاری بکە و دوگمەی "نیشاندانی ئەنجامەکان" دابگرە</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="print-only" style="margin-top: 60px; border-top: none;">
        <div style="display: flex; justify-content: space-around; text-align: center;">
            <div>
                <p><strong>ئامادەکاری ڕاپۆرت</strong></p>
                <div style="margin-top: 40px; border-top: 1px dashed #000; width: 180px; padding-top: 5px;"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
            </div>
            <div>
                <p><strong>بەڕێوەبەری کتێبخانە</strong></p>
                <div style="margin-top: 40px; border-top: 1px dashed #000; width: 180px; padding-top: 5px;">مۆر و واژوو</div>
            </div>
        </div>
    </div>
</div>

<script>
    async function generateReport() {
        const tbody = document.getElementById('reportResults');
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding: 60px; font-size: 1.1rem; color: var(--maroon);">لە گەڕاندایە...</td></tr>';
        
        const params = new URLSearchParams({
            action: 'filter_books',
            category_id: document.getElementById('rep_category').value,
            author_id: document.getElementById('rep_author').value,
            search: document.getElementById('rep_search').value,
            limit: 5000 // Large limit for reports
        });

        try {
            const res = await fetch(`fetch_data.php?${params}`).then(r=>r.json());
            const books = res.books || [];
            
            if (books.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding: 60px; color: #888;">هیچ کتێبێک بەم فلتەرانە نەدۆزرایەوە</td></tr>';
                return;
            }

            tbody.innerHTML = books.map((b, i) => `
                <tr>
                    <td style="text-align: center;">${i + 1}</td>
                    <td style="font-family: monospace; font-weight: 700; font-size: 0.85rem; color: var(--maroon);">${b.book_code || '-'}</td>
                    <td style="font-weight: 700;">${b.title}</td>
                    <td>${b.author_name || '-'}</td>
                    <td>${b.translator_name || '-'}</td>
                    <td>${b.category_name || '-'}</td>
                    <td style="text-align: center; font-size: 0.85rem;">${b.shelf_code || '-'}</td>
                    <td style="text-align: center; font-weight: 700;">${b.quantity}</td>
                </tr>
            `).join('');
        } catch (e) {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding: 60px; color:red;">هەڵەیەک ڕوویدا</td></tr>';
        }
    }
</script>

<?php require_once 'includes/layout_end.php'; ?>
