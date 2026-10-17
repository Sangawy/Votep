<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
$conn = getDB();

// Fetch active election
$election = $conn->query("SELECT * FROM elections WHERE status = 'active' ORDER BY id DESC LIMIT 1")->fetch_assoc();

if (!$election) {
    header('Location: elections_dashboard.php');
    exit;
}

// Fetch positions for the dropdown
$check_p = $conn->query("SELECT id FROM election_positions WHERE election_id = {$election['id']}");
if ($check_p->num_rows == 0) {
    $standard_positions = [
        'سەرۆکی دەستەی کارگیڕی',
        'دەستەی کارگێڕی',
        'ئەنجومەنی ڕاوێژکاری',
        'دەستەی کارگێڕی (یەدەگ)'
    ];
    foreach ($standard_positions as $sp) {
        $stmt = $conn->prepare("INSERT INTO election_positions (election_id, position_name) VALUES (?, ?)");
        $stmt->bind_param("is", $election['id'], $sp);
        $stmt->execute();
    }
}

$positions = [];
$res_p = $conn->query("SELECT * FROM election_positions WHERE election_id = {$election['id']} ORDER BY id ASC");
while ($row = $res_p->fetch_assoc()) $positions[] = $row;

// Fetch current candidates
$candidates_list = [];
$query_c = "SELECT c.*, m.full_name, m.member_code, m.profile_image, p.position_name, f.family_name
            FROM candidates c
            JOIN members m ON c.member_id = m.id
            JOIN election_positions p ON c.position_id = p.id
            LEFT JOIN families f ON m.family_id = f.id
            WHERE c.election_id = {$election['id']}
            ORDER BY p.id ASC, c.created_at DESC";
$res_c = $conn->query($query_c);
while ($row = $res_c->fetch_assoc()) $candidates_list[] = $row;

$page_title = 'بەربژێرەکان';
$active_nav = 'elections';
$sys_module = 'elections';
require_once 'includes/layout.php';
?>

<div class="lib-container">
    <div class="lib-header-row">
        <div class="lib-title-group">
            <h1 class="lib-page-title">🪪 بەڕێوەبردنی بەربژێرەکان</h1>
            <p class="lib-subtitle">گەڕان بۆ ئەندامان و دەستنیشانکردنیان وەک بەربژێر</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="elections_ballot.php" target="_blank" class="lib-btn lib-btn-gold">🖨️ چاپی فۆڕمی دەنگدان</a>
            <a href="elections_dashboard.php" class="lib-btn lib-btn-secondary">➡️ گەڕانەوە بۆ داشبۆرد</a>
        </div>
    </div>

    <!-- Nomination Search Section -->
    <div class="lib-card" style="padding: 40px; margin-bottom: 30px; background: linear-gradient(135deg, #fff 0%, #f9f9f9 100%); overflow: visible;">
        <div style="max-width: 800px; margin: 0 auto;">
            <h3 style="text-align: center; margin-bottom: 25px;">🔍 گەڕان بۆ دەستنیشانکردنی بەربژێری نوێ</h3>
            
            <div class="search-box-wrapper">
                <input type="text" id="nominationMemberSearch" placeholder="ناو، کۆد، یان مۆبایلی ئەندام بنوسە..." autocomplete="off">
                <div class="search-icon">🔍</div>
                
                <!-- Search Results Dropdown -->
                <div id="nominationSearchResults" class="search-results-dropdown">
                    <!-- Results will be injected here via JS -->
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 15px; color: #888; font-size: 0.9rem;">
                تێبینی: هەر کەسێکی دۆزیەوە، لە ڕێگەی دوگمەی "بەربژێرکردن" پۆستەکەی بۆ دیاری بکە.
            </div>
        </div>
    </div>

    <!-- Candidates List -->
    <div class="lib-card" style="padding: 25px;">
        <h3 style="margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 15px;">
            👥 لیستی بەربژێرەکان (<?php echo count($candidates_list); ?>)
        </h3>
        
        <div class="lib-table-wrapper">
            <table class="lib-table">
                <thead>
                    <tr>
                        <th style="width: 80px;">وێنە</th>
                        <th>ناوی تەواو</th>
                        <th>کۆدی ئەندام</th>
                        <th>بنەماڵە</th>
                        <th>پۆستی بەربژێر</th>
                        <th style="width: 100px;">کردارەکان</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($candidates_list)): ?>
                        <tr><td colspan="6" style="padding: 40px; text-align: center; color: #999;">تا ئێستا هیچ بەربژێرێک تۆمار نەکراوە</td></tr>
                    <?php else: ?>
                        <?php foreach ($candidates_list as $c): ?>
                            <tr>
                                <td>
                                    <div style="width: 45px; height: 45px; border-radius: 50%; overflow: hidden; margin: 0 auto; border: 2px solid var(--gold);">
                                        <img src="stream_image.php?path=<?php echo urlencode($c['profile_image'] ?: 'assets/imgae/user.png'); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                    </div>
                                </td>
                                <td style="font-weight: bold; text-align: right;"><?php echo htmlspecialchars($c['full_name']); ?></td>
                                <td><span class="lib-badge lib-badge-primary"><?php echo htmlspecialchars($c['member_code']); ?></span></td>
                                <td><?php echo htmlspecialchars($c['family_name'] ?: '-'); ?></td>
                                <td><span class="lib-badge lib-badge-gold" style="font-size: 0.9rem; padding: 5px 15px;"><?php echo htmlspecialchars($c['position_name']); ?></span></td>
                                <td>
                                    <button onclick="removeCandidate(<?php echo $c['id']; ?>)" class="lib-btn lib-btn-danger" style="padding: 5px 10px;" title="سڕینەوە">🗑️</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Nomination Modal -->
<div id="electionNominateModal" class="nomination-modal-overlay">
    <div class="nomination-modal-content">
        <div class="nomination-modal-header">
            <h3>👤 بەربژێرکردنی ئەندام</h3>
            <span class="close-modal" onclick="closeModal()">&times;</span>
        </div>
        <div class="nomination-modal-body">
            <form id="nominateForm" action="elections_save.php" method="POST">
                <input type="hidden" name="action" value="add_candidate">
                <input type="hidden" name="election_id" value="<?php echo $election['id']; ?>">
                <input type="hidden" id="modalMemberId" name="member_id" value="">
                
                <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 25px; background: #f8f9fa; padding: 15px; border-radius: 12px; border-right: 4px solid var(--gold);">
                    <img id="modalMemberImg" src="assets/imgae/user.png" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 2px solid #fff; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                    <div>
                        <h4 id="modalMemberName" style="margin: 0;">ناوی ئەندام</h4>
                        <p id="modalMemberCode" style="margin: 5px 0 0 0; color: #666; font-size: 0.9rem;">کۆد: #123</p>
                    </div>
                </div>
                
                <div class="lib-field">
                    <label style="display: block; margin-bottom: 10px; font-weight: bold;">پۆستی بەربژێر هەڵبژێرە:</label>
                    <select name="position_id" required style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #ddd; font-family: 'Rabar022';">
                        <option value="" disabled selected>-- پۆستەکە دیاری بکە --</option>
                        <?php foreach ($positions as $p): ?>
                            <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['position_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="margin-top: 25px; display: flex; gap: 10px;">
                    <button type="submit" class="lib-btn lib-btn-primary" style="flex: 1;">تۆمارکردن</button>
                    <button type="button" onclick="closeModal()" class="lib-btn lib-btn-secondary" style="flex: 1;">پاشگەزبوونەوە</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .search-box-wrapper {
        position: relative;
        width: 100%;
    }
    #nominationMemberSearch {
        width: 100%;
        padding: 18px 60px 18px 25px;
        font-size: 1.2rem;
        border: 2px solid #eee;
        border-radius: 50px;
        font-family: 'Rabar022';
        box-shadow: 0 10px 25px rgba(0,0,0,0.05);
        transition: 0.3s;
    }
    #nominationMemberSearch:focus {
        border-color: var(--gold);
        outline: none;
        box-shadow: 0 10px 30px rgba(200, 169, 81, 0.15);
    }
    .search-icon {
        position: absolute;
        right: 25px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 1.5rem;
        color: #ccc;
    }
    .search-results-dropdown {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: #fff;
        border-radius: 15px;
        box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        margin-top: 10px;
        z-index: 1000;
        max-height: 400px;
        overflow-y: auto;
        display: none;
        border: 1px solid #eee;
    }
    .search-result-item {
        padding: 15px 25px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 1px solid #f5f5f5;
        cursor: pointer;
        transition: 0.2s;
    }
    .search-result-item:hover {
        background: #fdfaf0;
    }
    .search-result-item .member-info {
        display: flex;
        align-items: center;
        gap: 15px;
    }
    .search-result-item img {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        object-fit: cover;
    }
    
    /* Nomination Modal Specific Styles */
    .nomination-modal-overlay {
        display: none;
        position: fixed;
        z-index: 999999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.7);
        backdrop-filter: blur(8px);
    }
    .nomination-modal-content {
        background: #fff;
        padding: 0;
        border-radius: 20px;
        width: 100%;
        max-width: 500px;
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        overflow: hidden;
        box-shadow: 0 25px 50px rgba(0,0,0,0.4);
        z-index: 1000000;
    }
    .nomination-modal-header {
        padding: 20px 25px;
        background: var(--maroon);
        color: #fff;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .nomination-modal-body {
        padding: 30px 25px;
    }
    .close-modal {
        font-size: 2rem;
        cursor: pointer;
    }
</style>

<script>
// Global variables for search timeout
let nominationTimeout;

// Global functions for modal and actions (must be outside DOMContentLoaded for onclick)
function openNomination(id, name, code, img) {
    console.log("Opening nomination for:", id, name);
    const modal = document.getElementById('electionNominateModal');
    if (!modal) {
        console.error("Modal element 'electionNominateModal' not found!");
        return;
    }
    
    document.getElementById('modalMemberId').value = id;
    document.getElementById('modalMemberName').innerText = name;
    document.getElementById('modalMemberCode').innerText = code;
    
    const modalImg = document.getElementById('modalMemberImg');
    if (modalImg) {
        modalImg.src = img ? `stream_image.php?path=${encodeURIComponent(img)}` : 'assets/imgae/user.png';
    }
    
    console.log("Setting modal display to block");
    modal.style.setProperty('display', 'block', 'important');
    modal.style.setProperty('visibility', 'visible', 'important');
    modal.style.setProperty('opacity', '1', 'important');
    
    // Hide search results
    const resBox = document.getElementById('nominationSearchResults');
    if (resBox) {
        console.log("Hiding search results box");
        resBox.style.display = 'none';
    }
}

function closeModal() {
    const modal = document.getElementById('electionNominateModal');
    if (modal) modal.style.display = 'none';
}

function removeCandidate(id) {
    if (confirm('ئایا دڵنیای لە سڕینەوەی ئەم بەربژێرە؟')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'elections_save.php';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'remove_candidate';
        form.appendChild(actionInput);
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'candidate_id';
        idInput.value = id;
        form.appendChild(idInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const nominationInput = document.getElementById('nominationMemberSearch');
    const nominationResults = document.getElementById('nominationSearchResults');

    if (!nominationInput || !nominationResults) return;

    nominationInput.addEventListener('input', function() {
        clearTimeout(nominationTimeout);
        const query = this.value.trim();
        
        if (query.length < 2) {
            nominationResults.style.display = 'none';
            return;
        }
        
        nominationTimeout = setTimeout(() => {
            fetch(`members_search_api.php?q=${encodeURIComponent(query)}&election_id=<?php echo $election['id']; ?>`)
                .then(res => res.json())
                .then(data => {
                    renderNominationResults(data);
                });
        }, 300);
    });

    function renderNominationResults(data) {
        nominationResults.innerHTML = '';
        if (data.length === 0) {
            nominationResults.innerHTML = '<div style="padding: 20px; text-align: center; color: #999;">هیچ ئەندامێک نەدۆزرایەوە</div>';
        } else {
            data.forEach(item => {
                const div = document.createElement('div');
                div.className = 'search-result-item';
                
                const imgPath = item.img ? `stream_image.php?path=${encodeURIComponent(item.img)}` : 'assets/imgae/user.png';
                
                const safeName = item.title.replace(/'/g, "\\'");
                const safeSub = item.sub.replace(/'/g, "\\'");
                const safeImg = (item.img || '').replace(/'/g, "\\'");
                
                div.innerHTML = `
                    <div class="member-info">
                        <img src="${imgPath}" onerror="this.src='assets/imgae/user.png'">
                        <div>
                            <div style="font-weight: bold; color: var(--maroon);">${item.title}</div>
                            <div style="font-size: 0.8rem; color: #666;">${item.sub}</div>
                        </div>
                    </div>
                    <button type="button" class="lib-btn lib-btn-gold" onclick="openNomination('${item.id}', '${safeName}', '${safeSub}', '${safeImg}')" style="padding: 5px 15px; font-size: 0.9rem;">
                        ➕ بەربژێرکردن
                    </button>
                `;
                nominationResults.appendChild(div);
            });
        }
        nominationResults.style.display = 'block';
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!nominationInput.contains(e.target) && !nominationResults.contains(e.target)) {
            nominationResults.style.display = 'none';
        }
    });
});
</script>

<?php require_once 'includes/layout_end.php'; ?>
