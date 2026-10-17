<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
$conn = getDB();

$head_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$family_id = isset($_GET['family_id']) ? (int)$_GET['family_id'] : 0;

if ($head_id <= 0) {
    header('Location: families_manage.php');
    exit;
}

// Fetch Head Details
$res = $conn->query("SELECT * FROM members WHERE id = $head_id");
$head = $res->fetch_assoc();
if (!$head) {
    header('Location: families_manage.php');
    exit;
}

// Fetch all members of this family to build the local tree
$all_members = [];
$res = $conn->query("SELECT id, full_name, parent_id, nickname, member_rank FROM members WHERE family_id = $family_id");
while ($row = $res->fetch_assoc()) {
    $all_members[] = $row;
}

// Filter to only include descendants of this head
function getDescendants($parentId, $members) {
    $descendants = [];
    foreach ($members as $m) {
        if ($m['parent_id'] == $parentId) {
            $descendants[] = $m;
            $descendants = array_merge($descendants, getDescendants($m['id'], $members));
        }
    }
    return $descendants;
}

$tree_members = getDescendants($head_id, $all_members);
// Add the head as the root
array_unshift($tree_members, $head);

// Format for D3 Org Chart
$chart_data = [];
foreach ($tree_members as $m) {
    $chart_data[] = [
        'id' => (string)$m['id'],
        'parentId' => ($m['id'] == $head_id) ? null : (string)$m['parent_id'],
        'name' => $m['full_name'],
        'nickname' => $m['nickname'],
        'rank' => $m['member_rank'] ?: 'ئەندام'
    ];
}

$page_title = "خێزانی " . $head['full_name'];
$active_nav = 'families_manage';
$sys_module = 'lineage';
require_once 'includes/layout.php';
?>

<!-- Load D3 and Org Chart Core -->
<script src="https://d3js.org/d3.v7.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/d3-org-chart@3.1.1"></script>
<script src="https://cdn.jsdelivr.net/npm/d3-flextree@2.1.2/build/d3-flextree.js"></script>

<style>
    :root {
        --tree-maroon: var(--maroon, #720000);
        --tree-gold: #c5a059;
    }

    .tree-wrapper {
        height: calc(100vh - 200px);
        background: #fff;
        border-radius: 20px;
        overflow: hidden;
        border: 1px solid #eee;
        position: relative;
        box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    }

    .chart-container {
        width: 100%;
        height: 100%;
        cursor: grab;
    }

    /* NODE CARD STYLING - Matching the main tree */
    .node-wrapper {
        background: #fff;
        border: 2px solid var(--tree-maroon);
        border-radius: 15px;
        padding: 15px;
        text-align: center;
        width: 220px;
        box-shadow: 0 8px 25px rgba(114, 0, 0, 0.1);
        position: relative;
        cursor: pointer;
        transition: 0.3s;
    }

    .node-wrapper:hover {
        transform: translateY(-5px) scale(1.05);
        border-color: var(--tree-gold);
        box-shadow: 0 12px 30px rgba(114, 0, 0, 0.2);
    }

    .node-wrapper::after {
        content: '';
        position: absolute; top: -10px; left: 50%; transform: translateX(-50%);
        width: 10px; height: 10px; border-radius: 50%; background: var(--tree-gold);
    }

    .node-name {
        color: var(--tree-maroon);
        font-family: 'Rabar022';
        font-weight: bold;
        font-size: 1.05rem;
        margin-top: 10px;
    }

    .node-meta {
        font-size: 0.75rem;
        color: #999;
        margin-top: 5px;
    }

    .controls {
        position: absolute; bottom: 30px; right: 30px; display: flex; flex-direction: column; gap: 12px;
    }

    .btn-circle {
        width: 50px; height: 50px; border-radius: 50%; background: #fff; border: none;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1); color: var(--tree-maroon); font-size: 20px; cursor: pointer; transition: 0.3s;
    }
    .btn-circle:hover { background: var(--tree-maroon); color: #fff; transform: scale(1.1); }
</style>

<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; padding: 0 20px;">
    <div style="display: flex; align-items: center; gap: 10px;">
        <a href="family_view.php?id=<?php echo $family_id; ?>" style="color: #666; text-decoration: none;"><i class="fas fa-arrow-right"></i> گەڕانەوە</a>
        <span style="color: #ccc;">/</span>
        <h2 style="margin: 0; color: var(--maroon);">شەجەرەی خێزانی (<?php echo htmlspecialchars($head['full_name']); ?>)</h2>
    </div>
    
    <div style="display: flex; gap: 10px;">
        <button class="lib-btn" onclick="chart.fit()" style="background: #f8f9fa; border: 1px solid #ddd; color: #666;">
            <i class="fas fa-expand-arrows-alt"></i> پڕکردنەوەی شاشە
        </button>
        <button class="lib-btn lib-btn-gold" onclick="chart.exportImg()">
            <i class="fas fa-download"></i> داگرتنی وێنە
        </button>
    </div>
</div>

<div style="padding: 0 20px;">
    <div class="tree-wrapper">
        <div id="chart-area" class="chart-container"></div>
        
        <!-- ZOOM CONTROLS -->
        <div class="controls">
            <button class="btn-circle" onclick="chart.zoomIn()" title="نزیککردنەوە">
                <i class="fas fa-plus" style="color: var(--tree-maroon);"></i>
            </button>
            <button class="btn-circle" onclick="chart.zoomOut()" title="دوورخستنەوە">
                <i class="fas fa-minus" style="color: var(--tree-maroon);"></i>
            </button>
        </div>
    </div>
</div>

<script>
    let chart;
    const membersData = <?php echo json_encode($chart_data); ?>;

    function renderChart() {
        if (membersData.length === 0) return;

        chart = new d3.OrgChart()
            .container('#chart-area')
            .data(membersData)
            .nodeWidth(d => 250)
            .nodeHeight(d => 140)
            .compact(false)
            .layout('bottom') 
            .onNodeClick(d => {
                const nodeId = (typeof d === 'object' && d.data) ? d.data.id : d;
                window.location.href = 'members_profile.php?id=' + nodeId;
            })
            .linkUpdate(function (d, i, arr) {
                d3.select(this).attr("stroke", "#c5a059").attr("stroke-width", 2);
            })
            .nodeContent(function (d, i, arr, state) {
                const fullName = d.data.name;
                const nickname = d.data.nickname ? `(${d.data.nickname})` : '';
                const rank = d.data.rank;
                
                // Root styling
                const isRoot = d.data.id == '<?php echo $head_id; ?>';
                const borderColor = isRoot ? 'var(--tree-gold)' : 'var(--tree-maroon)';
                const iconColor = isRoot ? 'var(--tree-gold)' : '#c5a059';

                return `
                <div class="node-wrapper" style="border-color: ${borderColor}">
                    <div style="font-size:1.5rem; color:${iconColor};">
                        <i class="fas ${isRoot ? 'fa-crown' : 'fa-user-circle'}"></i>
                    </div>
                    <div class="node-name">${fullName}</div>
                    <div style="font-size:0.8rem; color:#888;">${nickname}</div>
                    <div class="node-meta">${rank}</div>
                </div>
                `;
            })
            .render()
            .expandAll()
            .fit();
    }

    document.addEventListener('DOMContentLoaded', renderChart);
</script>

<?php require_once 'includes/layout_end.php'; ?>
