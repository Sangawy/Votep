<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'sangawy';
    $_SESSION['role'] = 'admin';
}

require_once 'config.php';
require_once 'lineage_core.php';
$conn = getDB();

// ── AJAX Search ──
if (isset($_GET['action']) && $_GET['action'] === 'search_ajax') {
    header('Content-Type: application/json; charset=utf-8');
    $q = trim($_GET['q'] ?? '');
    if (mb_strlen($q) < 1) { echo json_encode([]); exit; }
    $like = "%".normalizeName($q)."%";
    $likeR = "%$q%";
    $st = $conn->prepare("SELECT id,full_name,member_code,gender,member_status FROM members WHERE normalized_name LIKE ? OR full_name LIKE ? OR member_code LIKE ? ORDER BY id LIMIT 10");
    $st->bind_param("sss",$like,$likeR,$likeR);
    $st->execute();
    $rows = [];
    while ($r = $st->get_result()->fetch_assoc()) $rows[] = $r;
    echo json_encode($rows); exit;
}

// ── Target member ──
$id = max(1, (int)($_GET['id'] ?? 0));

$st = $conn->prepare("SELECT m.*, f.family_name, fat.full_name as father_name
                      FROM members m
                      LEFT JOIN families f ON m.family_id=f.id
                      LEFT JOIN members fat ON m.parent_id=fat.id
                      WHERE m.id=?");
$st->bind_param("i",$id);
$st->execute();
$person = $st->get_result()->fetch_assoc();

if (!$person) {
    $fb = $conn->query("SELECT id FROM members ORDER BY id LIMIT 1")->fetch_assoc();
    if ($fb) { header("Location: descendants_tree.php?id={$fb['id']}"); exit; }
    die("هیچ کەسێک نەدۆزرایەوە.");
}

// ── Ancestors chain (for trunk & full base lineage up to the Prophet) ──
$dbAncestors = [];
$cur = (int)$person['parent_id'];
$vis = [$id => true];
while ($cur > 0 && !isset($vis[$cur])) {
    $vis[$cur] = true;
    $row = $conn->query("SELECT id,full_name,first_name,parent_id FROM members WHERE id=$cur")->fetch_assoc();
    if (!$row) break;
    $dbAncestors[] = $row;
    $cur = (int)$row['parent_id'];
}

// Ancestors for breadcrumb bar (up to 12 oldest-first)
$ancestors = [];
$recentAncestors = array_slice($dbAncestors, 0, 12);
foreach ($recentAncestors as $row) {
    $ancestors[] = [
        'id' => (int)$row['id'],
        'full_name' => $row['full_name'],
        'short' => $row['first_name'] ?: preg_split('/\s+/u', trim($row['full_name']))[0]
    ];
}
$ancestors = array_reverse($ancestors); // oldest first

// Full textual lineage connecting from Root Person's father all the way up to Prophet Muhammad
$rawBase = '';
$baseRes = $conn->query("SELECT base_lineage FROM settings WHERE id = 1");
if ($baseRes && $bRow = $baseRes->fetch_assoc()) {
    $rawBase = trim($bRow['base_lineage'] ?? '');
}

$afterShaho = '';
if (preg_match('/(?:بيرخضر|پیرخضر|بيرخدر|پیرخدر)[^)]*\)\s*(?:بن|ابن)\s*(.*)$/u', $rawBase, $m)) {
    $afterShaho = trim($m[1]);
}

$fullLineageArray = [];
foreach ($dbAncestors as $a) {
    $cleanName = trim($a['full_name']);
    if (!empty($cleanName)) $fullLineageArray[] = $cleanName;
}

$fullLineageText = implode(' بن ', $fullLineageArray);
if (!empty($afterShaho)) {
    // If Pir Khidr Shaho was already the last in $dbAncestors, append the rest from base_lineage
    $lastAncestorName = end($fullLineageArray) ?: '';
    if (isShahoName($lastAncestorName)) {
        $fullLineageText .= ' بن ' . $afterShaho;
    } else {
        // If the DB chain didn't reach Shaho yet, or stopped earlier, append Shaho then base
        if (strpos($fullLineageText, 'بيرخضر') === false && strpos($fullLineageText, 'پیرخدر') === false) {
            $fullLineageText .= ' بن ' . $rawBase;
        } else {
            $fullLineageText .= ' بن ' . $afterShaho;
        }
    }

// Compute exact generation counts to Prophet Muhammad ﷺ and to Pir Khidr Shaho
$ancestorsToProphet = 0;
$ancestorsToShaho = 0;

$allLineageNames = preg_split('/\s+(?:بن|ابن)\s+/u', $fullLineageText);
$ancestorsToProphet = count(array_filter($allLineageNames));

foreach ($allLineageNames as $idx => $name) {
    if ($ancestorsToShaho === 0 && (mb_strpos($name, 'بيرخضر') !== false || mb_strpos($name, 'پیرخدر') !== false || mb_strpos($name, 'پیرخضر') !== false)) {
        $ancestorsToShaho = $idx + 1; // 1-based generation
    }
}
$isRootShaho = (int)$id === 1 || isShahoName($person['full_name']);

}

// ── All members ──
$res = $conn->query("SELECT id,full_name,first_name,gender,member_status,member_code,parent_id,birth_year,is_family_head FROM members");
$all = []; $byP = [];
while ($r = $res->fetch_assoc()) {
    $all[(int)$r['id']] = $r;
    if ($r['parent_id'] > 0) $byP[(int)$r['parent_id']][] = (int)$r['id'];
}

function shortName($full,$first){ 
    if (!empty($first)) return $first;
    $p = preg_split('/\s+/u',trim($full));
    $skip=['السيد','السید','سید','سەید','ملا','مەلا','الشيخ','شێخ','د.','دکتۆر'];
    foreach($p as $w) if(!in_array($w,$skip)&&$w!=='') return $w;
    return $p[0]??$full;
}

$vd=[]; 
$build = function($mid,$depth) use(&$build,&$all,&$byP,&$vd){
    if(isset($vd[$mid])) return null;
    $vd[$mid]=true;
    $m=$all[$mid];
    $node=['id'=>(int)$m['id'],'name'=>$m['full_name'],
           'short'=>shortName($m['full_name'],$m['first_name']),
           'gender'=>$m['gender']??'نێر','status'=>$m['member_status']??'active',
           'code'=>$m['member_code']?:(string)$m['id'],
           'birth'=>$m['birth_year']??'','is_head'=>(int)($m['is_family_head']??0),
           'depth'=>$depth,'children'=>[]];
    foreach(($byP[$mid]??[]) as $cid){
        $c=$build($cid,$depth+1);
        if($c) $node['children'][]=$c;
    }
    return $node;
};
$tree = $build($id,0);

// Stats
$tot=0;$males=0;$fem=0;$maxD=0;
$stats=function($n)use(&$stats,&$tot,&$males,&$fem,&$maxD){
    foreach($n['children']as$c){
        $tot++;
        if(strpos($c['gender'],'مێ')!==false||strtolower($c['gender'])==='female') $fem++; else $males++;
        if($c['depth']>$maxD) $maxD=$c['depth'];
        $stats($c);
    }
};
$stats($tree);

$rootPerson = ['id'=>(int)$person['id'],'full_name'=>$person['full_name'],
               'short'=>shortName($person['full_name'],$person['first_name']??'')];
$page_title = 'داری نەوەکان - '.htmlspecialchars($person['full_name']);
?>
<!DOCTYPE html>
<html lang="ku" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $page_title ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Amiri:ital,wght@0,400;0,700;1,400;1,700&family=Vazirmatn:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<script src="assets/js/d3.v7.min.js"></script>
<script>
if (typeof d3 === 'undefined') {
  document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/d3/7.8.5/d3.min.js"><\/script>');
}
</script>
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{
  --wood-dark: #2a1205;
  --wood-mid: #5c2c10;
  --wood-light: #8c451e;
  --gold-glow: #f59e0b;
  --leaf-emerald: #059669;
  --leaf-deep: #064e3b;
  --parchment: #fdfbf7;
}

body{
  font-family:'Vazirmatn',sans-serif;
  background-color: #f6f1e5;
  background-image: 
    radial-gradient(ellipse at 50% 25%, #fffdf8 0%, #f5efe2 60%, #e8dcbe 100%),
    radial-gradient(#c7b89f 0.75px, transparent 0.75px);
  background-size: 100% 100%, 28px 28px;
  overflow:hidden;
  width:100vw;
  height:100vh;
  user-select:none;
}

/* Antique Botanical Border Vignette */
.vintage-vignette {
  position: fixed;
  inset: 0;
  pointer-events: none;
  box-shadow: inset 0 0 100px rgba(92, 44, 16, 0.09), inset 0 0 180px rgba(61, 26, 8, 0.07);
  z-index: 10;
}

/* Top Floating Royal Toolbar */
.toolbar{
  position:fixed;top:14px;left:18px;right:18px;z-index:500;
  display:flex;align-items:center;justify-content:space-between;
  padding:9px 20px;
  background:rgba(255, 253, 248, 0.94);
  backdrop-filter:blur(18px);
  border-radius:50px;
  box-shadow:0 10px 32px rgba(61, 26, 8, 0.12), 0 2px 6px rgba(0,0,0,0.04);
  border:1px solid rgba(217, 119, 6, 0.35);
  gap:12px;
}
.tg{display:flex;align-items:center;gap:10px}

.btn{
  display:inline-flex;align-items:center;gap:7px;
  background:#fffdfa;color:#3b1e08;border:1px solid #d5c4af;
  padding:7px 16px;border-radius:30px;font-size:.84rem;font-weight:700;
  cursor:pointer;text-decoration:none;font-family:inherit;
  transition:all .22s cubic-bezier(.4,0,.2,1);
  box-shadow:0 2px 4px rgba(61, 26, 8, 0.05);
}
.btn:hover{
  background:#fff;border-color:#b45309;
  transform:translateY(-1.5px);
  box-shadow:0 6px 16px rgba(180, 83, 9, 0.16);
  color:#92400e;
}
.toggle-sons-chk {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: #fffdfa;
  color: #3b1e08;
  border: 1.5px solid #d5c4af;
  padding: 6px 14px;
  border-radius: 30px;
  font-size: 0.83rem;
  font-weight: 700;
  cursor: pointer;
  user-select: none;
  transition: all .22s cubic-bezier(.4, 0, .2, 1);
  box-shadow: 0 2px 5px rgba(61, 26, 8, 0.05);
}
.toggle-sons-chk:hover {
  border-color: #059669;
  background: #f0fdf4;
  color: #065f46;
  transform: translateY(-1.5px);
  box-shadow: 0 6px 16px rgba(5, 150, 105, 0.16);
}
.toggle-sons-chk input {
  display: none;
}
.toggle-sons-chk .chk-box {
  width: 18px;
  height: 18px;
  border-radius: 5px;
  border: 2px solid #a89a8a;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all .2s;
}
.toggle-sons-chk .chk-mark {
  font-size: 10px;
  color: #fff;
  opacity: 0;
  transform: scale(0.4);
  transition: all .2s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.toggle-sons-chk.active {
  background: linear-gradient(135deg, #065f46, #047857);
  color: #ffffff;
  border-color: #059669;
  box-shadow: 0 4px 16px rgba(4, 120, 87, 0.32);
}
.toggle-sons-chk.active .chk-box {
  background: #10b981;
  border-color: #fef08a;
}
.toggle-sons-chk.active .chk-mark {
  opacity: 1;
  transform: scale(1);
}
.toggle-sons-chk.active .chk-text i {
  color: #fde047 !important;
}

.btn.gold{
  background:linear-gradient(135deg, #b45309, #d97706);
  color:#fff;border:none;
  box-shadow:0 4px 14px rgba(180, 83, 9, 0.3);
}
.btn.gold:hover{
  background:linear-gradient(135deg, #92400e, #b45309);
  color:#fff;
}
.btn.leaf{
  background:linear-gradient(135deg, #065f46, #059669);
  color:#fff;border:none;
  box-shadow:0 4px 14px rgba(5, 150, 105, 0.3);
}
.btn.leaf:hover{
  background:linear-gradient(135deg, #047857, #064e3b);
  color:#fff;
}

/* Tree Title Crest in Toolbar */
.tree-title-crest {
  display:flex;align-items:center;gap:10px;
  font-family:'Amiri', serif;
  font-size:1.18rem;font-weight:700;
  color:#3b1e08;
  text-shadow:0 1px 1px rgba(255,255,255,0.8);
}
.tree-title-crest i {
  color:#b45309;font-size:1.35rem;
}

/* Search Dropdown */
.srch{position:relative;width:240px}
.srch input{
  width:100%;padding:7px 34px 7px 14px;border-radius:30px;
  border:1px solid #d5c4af;font-family:inherit;font-size:.83rem;
  outline:none;background:#fffdfa;transition:all .2s;color:#3b1e08;
}
.srch input:focus{
  border-color:#b45309;background:#fff;
  box-shadow:0 0 0 3px rgba(180, 83, 9, 0.15);
}
.srch .ico{
  position:absolute;right:12px;top:50%;transform:translateY(-50%);
  color:#9c8570;pointer-events:none;font-size:.82rem;
}
.drop{
  position:absolute;top:calc(100% + 8px);right:0;left:0;
  background:#fffdfa;border-radius:16px;
  box-shadow:0 14px 40px rgba(61, 26, 8, 0.2);
  border:1px solid #e7d8c5;max-height:280px;overflow-y:auto;display:none;z-index:600;
}
.drop a{
  display:flex;align-items:center;justify-content:space-between;
  padding:10px 14px;border-bottom:1px solid #f5ede2;
  cursor:pointer;text-decoration:none;color:#3b1e08;font-size:.84rem;
  transition:background .15s;
}
.drop a:hover{background:#fef3c7;color:#92400e}

/* Ancestor Lineage Breadcrumb Bar */
.lineage-bar{
  position:fixed;top:74px;left:18px;right:18px;z-index:450;
  display:flex;align-items:center;gap:7px;overflow-x:auto;
  padding:6px 16px;background:rgba(255, 253, 248, 0.92);
  backdrop-filter:blur(12px);border-radius:30px;
  border:1px solid rgba(217, 119, 6, 0.25);
  box-shadow:0 4px 16px rgba(61, 26, 8, 0.06);font-size:.78rem;color:#5c3a21;
}
.lineage-bar::-webkit-scrollbar{height:4px}
.lineage-bar::-webkit-scrollbar-thumb{background:#d5c4af;border-radius:4px}
.l-item{
  display:inline-flex;align-items:center;gap:4px;white-space:nowrap;
  color:#3b1e08;font-weight:700;text-decoration:none;padding:3px 10px;
  border-radius:20px;transition:all .15s;background:#fff;border:1px solid #e7d8c5;
}
.l-item:hover{background:#fef3c7;color:#92400e;border-color:#f59e0b}
.l-sep{color:#b45309;font-size:.72rem}

/* Viewport Canvas */
#vp{
  position:absolute;top:0;left:0;width:100vw;height:100vh;
  cursor:grab;overflow:hidden;
}
#vp:active{cursor:grabbing}
#tree-svg{width:100%;height:100%;display:block}

/* Floating Zoom & Compass Controls */
.zooms{
  position:fixed;bottom:22px;right:22px;z-index:400;
  display:flex;flex-direction:column;gap:8px;
}
.zbtn{
  width:44px;height:44px;border-radius:50%;
  background:rgba(255, 253, 248, 0.95);
  border:1px solid rgba(217, 119, 6, 0.35);
  color:#5c2c10;font-size:1rem;cursor:pointer;
  box-shadow:0 6px 18px rgba(61, 26, 8, 0.14);
  display:flex;align-items:center;justify-content:center;
  transition:all .2s cubic-bezier(.4,0,.2,1);
}
.zbtn:hover{
  background:#fff;color:#b45309;transform:scale(1.08);
  border-color:#b45309;box-shadow:0 8px 24px rgba(180, 83, 9, 0.24);
}

/* Stats Pill at Bottom Left */
.stats{
  position:fixed;bottom:22px;left:22px;z-index:400;
  background:rgba(255, 253, 248, 0.95);
  backdrop-filter:blur(14px);
  padding:9px 20px;border-radius:30px;
  box-shadow:0 8px 26px rgba(61, 26, 8, 0.12);
  border:1px solid rgba(217, 119, 6, 0.3);
  font-size:.82rem;color:#451a03;display:flex;align-items:center;gap:16px;
}
.stats strong{color:#92400e;font-size:.94rem}

/* Botanical Node Elements - Smooth & Stable Hover (No Jitter) */
.botanical-node {
  cursor: pointer;
}
.botanical-node:hover .node-halo {
  opacity: 0.95;
  stroke: #f59e0b;
  stroke-width: 3.5;
}
.botanical-node:hover .medallion-plate {
  filter: drop-shadow(0 10px 22px rgba(180, 83, 9, 0.55));
}

/* Wood Limb Paths */
.wood-limb {
  fill-rule: evenodd;
  transition: filter 0.25s;
}
.wood-limb:hover {
  filter: brightness(1.15);
}

/* Modal Dialog */
.modal{
  position:fixed;inset:0;background:rgba(26, 11, 4, 0.6);
  backdrop-filter:blur(8px);
  z-index:900;display:none;align-items:center;justify-content:center;
}
.mcard{
  background:radial-gradient(circle at 50% 20%, #fffefb 0%, #fdf8ed 100%);
  border-radius:24px;padding:26px;width:370px;
  box-shadow:0 24px 60px rgba(42, 18, 5, 0.35);
  border:2px solid #d97706;text-align:center;position:relative;
  animation:popModal 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
}
@keyframes popModal {
  0%{transform:scale(0.85);opacity:0}
  100%{transform:scale(1);opacity:1}
}
.mcl{
  position:absolute;top:14px;left:14px;background:#fef3c7;border:none;
  width:32px;height:32px;border-radius:50%;color:#92400e;font-size:1rem;
  cursor:pointer;display:flex;align-items:center;justify-content:center;
  transition:all .15s;
}
.mcl:hover{background:#fde68a;transform:rotate(90deg)}

/* Print Layout (Optimized for A3 Portrait & Landscape) */
@media print {
  *, *::before, *::after {
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }
  .toolbar, .stats, .zooms, .modal, .lineage-bar, .vintage-vignette { 
    display: none !important; 
  }
  html, body { 
    margin: 0 !important; 
    padding: 0 !important; 
    background: #fdfbf7 !important; 
    overflow: hidden !important; 
  }
  body.print-landscape, body.print-landscape html { 
    width: 420mm !important; 
    height: 297mm !important; 
  }
  body.print-landscape #vp, body.print-landscape #tree-svg { 
    width: 420mm !important; 
    height: 297mm !important; 
    display: block !important; 
  }
  body.print-portrait, body.print-portrait html { 
    width: 297mm !important; 
    height: 420mm !important; 
  }
  body.print-portrait #vp, body.print-portrait #tree-svg { 
    width: 297mm !important; 
    height: 420mm !important; 
    display: block !important; 
  }
  #vp { 
    position: absolute !important; 
    inset: 0 !important; 
    overflow: hidden !important; 
    background: transparent !important;
  }
}
</style>
</head>
<body>

<div class="vintage-vignette"></div>

<!-- Top Toolbar -->
<div class="toolbar">
  <div class="tg">
    <a href="kinship_search.php?id=<?= $id ?>" class="btn"><i class="fas fa-arrow-right"></i> پەڕەی کەسی</a>
    <a href="lineage_tree.php" class="btn"><i class="fas fa-network-wired"></i> داری گشتی</a>
    <div class="srch">
      <i class="fas fa-search ico"></i>
      <input type="text" id="si" placeholder="گەڕان بەدوای کەسێک..." autocomplete="off">
      <div class="drop" id="sd"></div>
    </div>
  </div>

  <div class="tree-title-crest">
    <i class="fas fa-tree"></i>
    <span>داری نەوەکانی: <?= htmlspecialchars($person['full_name']) ?></span>
  </div>

  <div class="tg">
    <!-- Only Direct Sons Toggle Checkbox -->
    <label class="toggle-sons-chk" id="lblOnlySons" title="تەنها پیشاندانی کوڕە ڕاستەوخۆکانی ڕەگی سەرەکی">
      <input type="checkbox" id="chkOnlySons">
      <span class="chk-box">
        <i class="fas fa-check chk-mark"></i>
      </span>
      <span class="chk-text"><i class="fas fa-user-friends"></i> تەنها کوڕەکان</span>
    </label>

    <button class="btn gold" id="fitBtn"><i class="fas fa-expand-arrows-alt"></i> تەواوی دارەکە</button>

    <!-- Print Dropdown (Portrait or Landscape) -->
    <div style="position:relative">
      <button class="btn" id="prBtn"><i class="fas fa-print"></i> چاپی A3 <i class="fas fa-chevron-down" style="font-size:.7rem"></i></button>
      <div class="drop" id="prDrop" style="width:230px;right:0">
        <a onclick="triggerA3Print('portrait')"><i class="fas fa-file-alt" style="color:#b45309"></i> چاپی A3 پۆترەیت (بۆ دارە درێژەکان)</a>
        <a onclick="triggerA3Print('landscape')"><i class="fas fa-file-invoice" style="color:#059669"></i> چاپی A3 پانی (بۆ دارە پانەکان ٣٠+ کەس)</a>
      </div>
    </div>

    <!-- Download Dropdown -->
    <div style="position:relative">
      <button class="btn leaf" id="dlBtn"><i class="fas fa-download"></i> داگرتن <i class="fas fa-chevron-down" style="font-size:.7rem"></i></button>
      <div class="drop" id="dlDrop" style="width:200px;right:0">
        <a id="dlPng"><i class="fas fa-file-image" style="color:#059669"></i> وێنەی بەرز (PNG)</a>
        <a id="dlSvg"><i class="fas fa-bezier-curve" style="color:#b45309"></i> فایلی ڤێکتۆر (SVG)</a>
      </div>
    </div>
  </div>
</div>

<!-- Ancestor Lineage Breadcrumb Bar -->
<?php if (!empty($ancestors)): ?>
<div class="lineage-bar">
  <span style="font-weight:800;color:#b45309"><i class="fas fa-history"></i> زنجیرەی باوان:</span>
  <?php foreach ($ancestors as $i => $anc): ?>
    <a href="descendants_tree.php?id=<?= $anc['id'] ?>" class="l-item" title="<?= htmlspecialchars($anc['full_name']) ?>">
      <?= htmlspecialchars($anc['short']) ?>
    </a>
    <span class="l-sep">←</span>
  <?php endforeach; ?>
  <span class="l-item" style="background:#fef3c7;border-color:#f59e0b;color:#92400e;font-weight:900">
    👑 <?= htmlspecialchars($rootPerson['short']) ?>
  </span>
</div>
<?php endif; ?>

<!-- Stats Badge -->
<div class="stats">
  <div id="statTot">🌿 کۆی نەوەکان: <strong><?= $tot ?> کەس</strong></div>
  <div id="statMales" style="color:#0369a1">👦 کوڕ: <strong><?= $males ?></strong></div>
  <div id="statFem" style="color:#be185d">👧 کچ: <strong><?= $fem ?></strong></div>
  <div id="statGen" style="color:#b45309">👑 پشتەکان: <strong><?= $maxD ?> پشت</strong></div>
</div>

<!-- Floating Zoom Controls -->
<div class="zooms">
  <button class="zbtn" id="zi" title="گەورەکردن"><i class="fas fa-plus"></i></button>
  <button class="zbtn" id="zo" title="بچووککردن"><i class="fas fa-minus"></i></button>
  <button class="zbtn" id="zf" title="گەڕانەوە بۆ تەواوی دارەکە"><i class="fas fa-crosshairs"></i></button>
</div>

<!-- Viewport & SVG Container -->
<div id="vp">
  <svg id="tree-svg">
    <defs>
      <!-- Shadow Filter for Medallions -->
      <filter id="medallion-shadow" x="-30%" y="-30%" width="160%" height="170%">
        <feDropShadow dx="0" dy="7" stdDeviation="8" flood-color="#2a1205" flood-opacity="0.24" />
      </filter>

      <!-- Branch Shadow Filter -->
      <filter id="branch-shadow" x="-20%" y="-20%" width="140%" height="140%">
        <feDropShadow dx="0" dy="7" stdDeviation="8" flood-color="#1f0c02" flood-opacity="0.28" />
      </filter>

      <!-- Golden Halo Glow -->
      <filter id="gold-glow" x="-40%" y="-40%" width="180%" height="180%">
        <feGaussianBlur stdDeviation="6" result="blur" />
        <feComponentTransfer in="blur" result="glow">
          <feFuncA type="linear" slope="0.8"/>
        </feComponentTransfer>
        <feMerge>
          <feMergeNode in="glow"/>
          <feMergeNode in="SourceGraphic"/>
        </feMerge>
      </filter>

      <!-- Wood Trunk Gradients -->
      <linearGradient id="trunk-wood" x1="0%" y1="0%" x2="100%" y2="0%">
        <stop offset="0%" stop-color="#1c0a02" />
        <stop offset="20%" stop-color="#3d1b09" />
        <stop offset="48%" stop-color="#6e3512" />
        <stop offset="65%" stop-color="#8c471d" />
        <stop offset="82%" stop-color="#4a220b" />
        <stop offset="100%" stop-color="#180902" />
      </linearGradient>

      <!-- Wood Branch Gradients (Default Trunk & 8 Royal Clan Palettes) -->
      <linearGradient id="branch-grad" x1="0%" y1="0%" x2="0%" y2="100%">
        <stop offset="0%" stop-color="#6e3512" />
        <stop offset="50%" stop-color="#4a220b" />
        <stop offset="100%" stop-color="#2c1204" />
      </linearGradient>

      <!-- Branch Knot / Joint Collar Radial Gradient -->
      <radialGradient id="branch-knot" cx="45%" cy="40%" r="55%">
        <stop offset="0%" stop-color="#8c451e" />
        <stop offset="60%" stop-color="#4a220b" />
        <stop offset="100%" stop-color="#240e03" />
      </radialGradient>

      <!-- Clan 0: Royal Emerald Forest (زمردی شاهانە) -->
      <linearGradient id="branch-grad-0" x1="0%" y1="0%" x2="0%" y2="100%">
        <stop offset="0%" stop-color="#059669" />
        <stop offset="45%" stop-color="#047857" />
        <stop offset="100%" stop-color="#022c22" />
      </linearGradient>
      <radialGradient id="branch-knot-0" cx="45%" cy="40%" r="55%">
        <stop offset="0%" stop-color="#10b981" />
        <stop offset="60%" stop-color="#064e3b" />
        <stop offset="100%" stop-color="#022c22" />
      </radialGradient>

      <!-- Clan 1: Royal Sapphire / Lapis (شینی یاقوتی) -->
      <linearGradient id="branch-grad-1" x1="0%" y1="0%" x2="0%" y2="100%">
        <stop offset="0%" stop-color="#2563eb" />
        <stop offset="45%" stop-color="#1d4ed8" />
        <stop offset="100%" stop-color="#0f172a" />
      </linearGradient>
      <radialGradient id="branch-knot-1" cx="45%" cy="40%" r="55%">
        <stop offset="0%" stop-color="#38bdf8" />
        <stop offset="60%" stop-color="#1e3a8a" />
        <stop offset="100%" stop-color="#0f172a" />
      </radialGradient>

      <!-- Clan 2: Royal Amber Gold / Warm Bronze (ئاڵتوونی و برۆنز) -->
      <linearGradient id="branch-grad-2" x1="0%" y1="0%" x2="0%" y2="100%">
        <stop offset="0%" stop-color="#d97706" />
        <stop offset="45%" stop-color="#b45309" />
        <stop offset="100%" stop-color="#451a03" />
      </linearGradient>
      <radialGradient id="branch-knot-2" cx="45%" cy="40%" r="55%">
        <stop offset="0%" stop-color="#fbbf24" />
        <stop offset="60%" stop-color="#78350f" />
        <stop offset="100%" stop-color="#451a03" />
      </radialGradient>

      <!-- Clan 3: Imperial Ruby Crimson (یاقوتی سوور) -->
      <linearGradient id="branch-grad-3" x1="0%" y1="0%" x2="0%" y2="100%">
        <stop offset="0%" stop-color="#e11d48" />
        <stop offset="45%" stop-color="#be123c" />
        <stop offset="100%" stop-color="#4c0519" />
      </linearGradient>
      <radialGradient id="branch-knot-3" cx="45%" cy="40%" r="55%">
        <stop offset="0%" stop-color="#fb7185" />
        <stop offset="60%" stop-color="#881337" />
        <stop offset="100%" stop-color="#4c0519" />
      </radialGradient>

      <!-- Clan 4: Royal Amethyst Purple (مۆری پاشایەتی) -->
      <linearGradient id="branch-grad-4" x1="0%" y1="0%" x2="0%" y2="100%">
        <stop offset="0%" stop-color="#9333ea" />
        <stop offset="45%" stop-color="#7e22ce" />
        <stop offset="100%" stop-color="#3b0764" />
      </linearGradient>
      <radialGradient id="branch-knot-4" cx="45%" cy="40%" r="55%">
        <stop offset="0%" stop-color="#c084fc" />
        <stop offset="60%" stop-color="#581c87" />
        <stop offset="100%" stop-color="#3b0764" />
      </radialGradient>

      <!-- Clan 5: Persian Turquoise Teal (پیرۆزەیی) -->
      <linearGradient id="branch-grad-5" x1="0%" y1="0%" x2="0%" y2="100%">
        <stop offset="0%" stop-color="#0d9488" />
        <stop offset="45%" stop-color="#0f766e" />
        <stop offset="100%" stop-color="#042f2e" />
      </linearGradient>
      <radialGradient id="branch-knot-5" cx="45%" cy="40%" r="55%">
        <stop offset="0%" stop-color="#2dd4bf" />
        <stop offset="60%" stop-color="#134e4a" />
        <stop offset="100%" stop-color="#042f2e" />
      </radialGradient>

      <!-- Clan 6: Terracotta Rust / Sienna (دارچینی و ئاگرین) -->
      <linearGradient id="branch-grad-6" x1="0%" y1="0%" x2="0%" y2="100%">
        <stop offset="0%" stop-color="#ea580c" />
        <stop offset="45%" stop-color="#c2410c" />
        <stop offset="100%" stop-color="#431407" />
      </linearGradient>
      <radialGradient id="branch-knot-6" cx="45%" cy="40%" r="55%">
        <stop offset="0%" stop-color="#fb923c" />
        <stop offset="60%" stop-color="#7c2d12" />
        <stop offset="100%" stop-color="#431407" />
      </radialGradient>

      <!-- Clan 7: Deep Indigo Cobalt (نیلی تاریک) -->
      <linearGradient id="branch-grad-7" x1="0%" y1="0%" x2="0%" y2="100%">
        <stop offset="0%" stop-color="#4f46e5" />
        <stop offset="45%" stop-color="#3730a3" />
        <stop offset="100%" stop-color="#1e1b4b" />
      </linearGradient>
      <radialGradient id="branch-knot-7" cx="45%" cy="40%" r="55%">
        <stop offset="0%" stop-color="#818cf8" />
        <stop offset="60%" stop-color="#312e81" />
        <stop offset="100%" stop-color="#1e1b4b" />
      </radialGradient>

      <!-- Lush Green Botanical Leaves Gradient -->
      <linearGradient id="leaf-grad" x1="0%" y1="0%" x2="100%" y2="100%">
        <stop offset="0%" stop-color="#4ade80" />
        <stop offset="35%" stop-color="#16a34a" />
        <stop offset="80%" stop-color="#047857" />
        <stop offset="100%" stop-color="#064e3b" />
      </linearGradient>

      <!-- Olive Golden Laurel Leaves Gradient (For Root Patriarch) -->
      <linearGradient id="leaf-gold-grad" x1="0%" y1="0%" x2="100%" y2="100%">
        <stop offset="0%" stop-color="#fef08a" />
        <stop offset="40%" stop-color="#eab308" />
        <stop offset="75%" stop-color="#b45309" />
        <stop offset="100%" stop-color="#78350f" />
      </linearGradient>

      <!-- Medallion Gold Border Gradient -->
      <linearGradient id="gold-grad" x1="0%" y1="0%" x2="100%" y2="100%">
        <stop offset="0%" stop-color="#b45309" />
        <stop offset="25%" stop-color="#d97706" />
        <stop offset="50%" stop-color="#f59e0b" />
        <stop offset="75%" stop-color="#d97706" />
        <stop offset="100%" stop-color="#92400e" />
      </linearGradient>
      <linearGradient id="gold-rim" x1="0%" y1="0%" x2="100%" y2="100%">
        <stop offset="0%" stop-color="#b45309" />
        <stop offset="30%" stop-color="#fbbf24" />
        <stop offset="55%" stop-color="#fef08a" />
        <stop offset="80%" stop-color="#d97706" />
        <stop offset="100%" stop-color="#92400e" />
      </linearGradient>

      <!-- Father Imperial Gold-Parchment Plate Gradient (Rich Gold for Fathers) -->
      <linearGradient id="plate-father" x1="0%" y1="0%" x2="0%" y2="100%">
        <stop offset="0%" stop-color="#fffdf0" />
        <stop offset="35%" stop-color="#fef3c7" />
        <stop offset="75%" stop-color="#fde68a" />
        <stop offset="100%" stop-color="#fcd34d" />
      </linearGradient>

      <!-- Grandchild Luminous Pearl Plate Gradient (Pure White Pearl for Descendants) -->
      <linearGradient id="plate-grandchild" x1="0%" y1="0%" x2="0%" y2="100%">
        <stop offset="0%" stop-color="#ffffff" />
        <stop offset="70%" stop-color="#f8fafc" />
        <stop offset="100%" stop-color="#f1f5f9" />
      </linearGradient>

      <!-- Male Parchment Plate Gradient (Fallback) -->
      <linearGradient id="plate-male" x1="0%" y1="0%" x2="0%" y2="100%">
        <stop offset="0%" stop-color="#ffffff" />
        <stop offset="85%" stop-color="#f8fafc" />
        <stop offset="100%" stop-color="#f1f5f9" />
      </linearGradient>

      <!-- Female Parchment Plate Gradient -->
      <linearGradient id="plate-female" x1="0%" y1="0%" x2="0%" y2="100%">
        <stop offset="0%" stop-color="#ffffff" />
        <stop offset="80%" stop-color="#fdf2f8" />
        <stop offset="100%" stop-color="#ffe4e6" />
      </linearGradient>

      <!-- Root Patriarch Imperial Parchment Plate Gradient -->
      <linearGradient id="plate-root" x1="0%" y1="0%" x2="0%" y2="100%">
        <stop offset="0%" stop-color="#fffbeb" />
        <stop offset="60%" stop-color="#fef3c7" />
        <stop offset="100%" stop-color="#fde68a" />
      </linearGradient>

      <!-- Earth / Grass Mound Gradient -->
      <linearGradient id="earth-grad" x1="0%" y1="0%" x2="0%" y2="100%">
        <stop offset="0%" stop-color="#2d3f1a" />
        <stop offset="40%" stop-color="#1f2c11" />
        <stop offset="100%" stop-color="#161f0c" />
      </linearGradient>
    </defs>

    <!-- Main Zoomable Group -->
    <g id="g-main">
      <!-- Ground Earth & Mound Base -->
      <g id="g-earth"></g>
      <!-- Base Lineage Royal Scroll (Nestled directly under and embraced by roots) -->
      <g id="g-scroll"></g>
      <!-- Trunk and Roots (Roots grip gracefully over the top of the scroll) -->
      <g id="g-trunk"></g>
      <!-- Wood Branches (Limbs & Twigs) -->
      <g id="g-branches"></g>
      <!-- Stats Cards Flanking the Trunk (Descendants Left, Ancestors Right) -->
      <g id="g-stats-cards"></g>
      <!-- Leaves Sprays Behind Medallions -->
      <g id="g-leaves"></g>
      <!-- Botanical Medallion Nodes -->
      <g id="g-medallions"></g>
    </g>
  </svg>
</div>

<!-- Person Detail Modal -->
<div class="modal" id="modal">
  <div class="mcard">
    <button class="mcl" onclick="closeModal()"><i class="fas fa-times"></i></button>
    <div id="mav" style="width:72px;height:72px;border-radius:50%;margin:0 auto 12px;background:#fef3c7;display:flex;align-items:center;justify-content:center;font-size:2rem;color:#b45309;border:3px solid #f59e0b">🌿</div>
    <h3 id="mname" style="font-family:'Amiri',serif;font-size:1.45rem;font-weight:700;color:#3b1e08;margin-bottom:4px"></h3>
    <div id="mcode" style="font-size:.82rem;color:#78350f;margin-bottom:14px"></div>
    <div style="background:#fffdf7;border-radius:16px;padding:14px 18px;font-size:.84rem;color:#5c3a21;margin-bottom:18px;line-height:2.1;text-align:right;border:1px solid #ebd9c5">
      <div>📌 دۆخ: <strong id="mst">-</strong></div>
      <div>📅 ساڵی لەدایکبوون: <strong id="mby">-</strong></div>
      <div>👑 نەوەی: <strong id="mdp">-</strong></div>
      <div>🌿 ژمارەی منداڵەکان: <strong id="mch">-</strong></div>
    </div>
    <div style="display:flex;gap:10px;justify-content:center">
      <a id="mta" href="#" class="btn gold" style="flex:1;justify-content:center"><i class="fas fa-tree"></i> داری نەوەکانی ئەم کەسە</a>
      <a id="mpa" href="#" target="_blank" class="btn" style="justify-content:center"><i class="fas fa-id-card"></i> پەڕەی کەسی</a>
    </div>
  </div>
</div>

<script>
// ═══════════════════════════════════════════════════════
//  DATA & BOTANICAL CONFIGURATION
// ═══════════════════════════════════════════════════════
const RAW_TREE          = <?= json_encode($tree,            JSON_UNESCAPED_UNICODE) ?>;
const ROOT_INFO         = <?= json_encode($rootPerson,      JSON_UNESCAPED_UNICODE) ?>;
const FULL_LINEAGE_TEXT = <?= json_encode($fullLineageText, JSON_UNESCAPED_UNICODE) ?>;
const ANCESTORS_TO_PROPHET = <?= (int)$ancestorsToProphet ?>;
const ANCESTORS_TO_SHAHO   = <?= (int)$ancestorsToShaho ?>;
const IS_ROOT_SHAHO        = <?= $isRootShaho ? 'true' : 'false' ?>;

// Global Botanical Configuration & Dimensions
let layoutMode = 'portrait'; // 'portrait' (Adaptive Crown) or 'wide' (Classic Horizontal)
let onlySonsMode = false;
const originalStats = {
  tot: <?= (int)$tot ?>,
  males: <?= (int)$males ?>,
  fem: <?= (int)$fem ?>,
  gen: <?= (int)$maxD ?>
};

let curMedallionRx = 80;
let curMedallionRy = 26;
const ROOT_RX      = 116;
const ROOT_RY      = 37;

// Compact Upward Growth Spacing Constants
const HORIZ_SLOT_W = 182; // Leaf slot width
const LEVEL_HEIGHT = 310; // Tall upward growth between generations

// D3 Selectors
const svg         = d3.select('#tree-svg');
const gMain       = d3.select('#g-main');
const gEarth      = d3.select('#g-earth');
const gTrunk      = d3.select('#g-trunk');
const gBranches   = d3.select('#g-branches');
const gLeaves     = d3.select('#g-leaves');
const gMedallions = d3.select('#g-medallions');
const gScroll     = d3.select('#g-scroll');
const gStatsCards = d3.select('#g-stats-cards');

// Global D3 Zoom Behavior
const zoom = d3.zoom()
  .scaleExtent([0.04, 3.5])
  .on('zoom', (e) => gMain.attr('transform', e.transform));

svg.call(zoom);

// Global State
let treeDataRoot = null;
let allNodesList = [];
let treeBounds   = { minX: 0, maxX: 0, minY: 0, maxY: 0 };

// ═══════════════════════════════════════════════════════
//  BOTANICAL SUBTREE CLUSTERING & ADAPTIVE CANOPY ENGINE
// ═══════════════════════════════════════════════════════
// ═══════════════════════════════════════════════════════
//  ROYAL BOTANICAL CLAN COLOR PALETTES
// ═══════════════════════════════════════════════════════
const CLAN_THEMES = [
  { name: 'زمردی',    accent: '#059669', rim: '#10b981', badge: '#047857', dark: '#064e3b', mid: '#059669', halo: '#34d399' },
  { name: 'یاقوتی شین',accent: '#2563eb', rim: '#38bdf8', badge: '#1d4ed8', dark: '#1e3a8a', mid: '#2563eb', halo: '#60a5fa' },
  { name: 'ئاڵتوونی',  accent: '#d97706', rim: '#fbbf24', badge: '#b45309', dark: '#78350f', mid: '#d97706', halo: '#f59e0b' },
  { name: 'یاقوتی سوور',accent: '#e11d48', rim: '#fb7185', badge: '#be123c', dark: '#881337', mid: '#e11d48', halo: '#f43f5e' },
  { name: 'مۆری شاهانە',accent: '#9333ea', rim: '#c084fc', badge: '#7e22ce', dark: '#581c87', mid: '#9333ea', halo: '#a855f7' },
  { name: 'پیرۆزەیی', accent: '#0d9488', rim: '#2dd4bf', badge: '#0f766e', dark: '#134e4a', mid: '#0d9488', halo: '#14b8a6' },
  { name: 'دارچینی',   accent: '#ea580c', rim: '#fb923c', badge: '#c2410c', dark: '#7c2d12', mid: '#ea580c', halo: '#f97316' },
  { name: 'نیلی',      accent: '#4f46e5', rim: '#818cf8', badge: '#3730a3', dark: '#312e81', mid: '#4f46e5', halo: '#6366f1' }
];

// ═══════════════════════════════════════════════════════
//  KURDISH NUMBERING & HIERARCHICAL IDENTIFICATION SYSTEM
// ═══════════════════════════════════════════════════════
function toKurdishNumber(str) {
  const digits = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
  return String(str).replace(/[0-9]/g, d => digits[parseInt(d, 10)]);
}

// Hierarchical identification codes:
// Main sons (Level 1): 1, 2, 3, 4, 5... (Ahmad is 4, Saleh is 5, Mulla Ali is 3, Haj Saeed is 1)
// Sons of fathers (Level 2): 4-1 Abdullah, 4-2 Hiwa, 4-3 Kamil, 4-4 Fatih
// Grandchildren (Level 3): for Abdullah (who is 4-1, local index 1): 1-1 Mohammad, 1-2...
function assignHierarchyCodes(rootNode) {
  rootNode.treeCode = '';

  const sons = rootNode.children || [];
  const maroofBranchNums = {
    142: 1, // Haj Sheikh Saeed
    131: 3, // Mulla Sheikh Ali
    126: 4, // Ahmad (explicitly 4 as requested by user)
    111: 5  // Saleh
  };

  sons.forEach((son, i) => {
    const bNum = (rootNode.id === 110 && maroofBranchNums[son.id]) ? maroofBranchNums[son.id] : (i + 1);
    son.treeCode = String(bNum);
    son.localIndex = bNum;

    // For Ahmad (126), ensure user's exact requested order:
    // 4-1 Abdullah (127), 4-2 Hiwa (130), 4-3 Kamil (129), 4-4 Fatih (128)
    if (son.id === 126 && son.children && son.children.length === 4) {
      const desiredOrder = [127, 130, 129, 128];
      son.children.sort((a, b) => desiredOrder.indexOf(a.id) - desiredOrder.indexOf(b.id));
    }

    (son.children || []).forEach((child, ci) => {
      const childIndex = ci + 1;
      child.localIndex = childIndex;
      child.treeCode = `${son.treeCode}-${childIndex}`;

      // Grandchildren (Level 3):
      // User explicit rule:
      // "بۆ نمونە عبدالله کە خۆی ٤-١ بوە ببێت بە ١-١ محمد ئاوا بۆ ئەوەی بناسرێنەوە"
      (child.children || []).forEach((grandchild, gci) => {
        const gcIndex = gci + 1;
        grandchild.localIndex = gcIndex;
        grandchild.treeCode = `${child.localIndex}-${gcIndex}`;

        (grandchild.children || []).forEach((greatGc, ggci) => {
          greatGc.localIndex = ggci + 1;
          greatGc.treeCode = `${grandchild.localIndex}-${ggci + 1}`;
        });
      });
    });
  });
}

// Color assignment: Each primary branch and each multi-family brother gets a distinct theme!
function assignUniqueFamilyColors(root) {
  root.clanIndex = 2; // Patriarch amber
  const PALETTE_ORDER = [0, 5, 2, 3, 4, 6, 7, 1]; // Emerald, Turquoise, Amber, Ruby, Amethyst, Terracotta, Indigo, Sapphire
  let poolIdx = 0;

  (root.children || []).forEach(son => {
    const sonsWithSubfamilies = (son.children || []).filter(c => c.children && c.children.length > 0);
    if (sonsWithSubfamilies.length > 1) {
      // Grandfather bough (like Saleh):
      son.clanIndex = 0; // Emerald for Saleh himself
      // EACH of his sons (the fathers/uncles) gets his OWN DISTINCT COLOR!
      son.children.forEach((ch, ci) => {
        const col = PALETTE_ORDER[ci % PALETTE_ORDER.length];
        function setSubtreeColor(node, c) {
          node.clanIndex = c;
          (node.children || []).forEach(k => setSubtreeColor(k, c));
        }
        setSubtreeColor(ch, col);
      });
    } else {
      const col = [1, 2, 4, 6, 7][poolIdx % 5];
      poolIdx++;
      function setSubtreeColor(node, c) {
        node.clanIndex = c;
        (node.children || []).forEach(k => setSubtreeColor(k, c));
      }
      setSubtreeColor(son, col);
    }
  });
}

// ═══════════════════════════════════════════════════════
//  RADIAL CIRCULAR BOTANICAL CANOPY ENGINE (Well-Spaced)
// ═══════════════════════════════════════════════════════
function layoutBotanicalTree(rootNode, rootX, rootY) {
  allNodesList = [];
  assignHierarchyCodes(rootNode);
  assignUniqueFamilyColors(rootNode);

  function countTotal(n) {
    let cnt = 1;
    (n.children || []).forEach(c => cnt += countTotal(c));
    return cnt;
  }
  const totalCount = countTotal(rootNode);

  // Dynamic Medallion dimensions (Generous width to display hierarchical names)
  if (totalCount > 25) {
    curMedallionRx = 78;
    curMedallionRy = 24;
  } else if (totalCount <= 8) {
    curMedallionRx = 88;
    curMedallionRy = 28;
  } else {
    curMedallionRx = 82;
    curMedallionRy = 25;
  }

  rootNode.x = rootX;
  rootNode.y = rootY;
  rootNode.depth = 0;
  rootNode.isFather = true;
  allNodesList.push(rootNode);

  // 1. Compute leaf counts and generational depth (Bottom-Up)
  function computeSubtreeMetrics(node, depth = 0) {
    node.depth = depth;
    node.isFather = Boolean(node.children && node.children.length > 0);
    if (!node.children || node.children.length === 0) {
      node.leafWeight = 1;
      node.maxDepth = depth;
      return;
    }
    let sumWeight = 0;
    let maxD = depth;
    node.children.forEach(child => {
      child.parent = node;
      computeSubtreeMetrics(child, depth + 1);
      sumWeight += child.leafWeight;
      if (child.maxDepth > maxD) maxD = child.maxDepth;
    });
    node.leafWeight = sumWeight;
    node.maxDepth = maxD;
  }
  computeSubtreeMetrics(rootNode, 0);

  const totalLeaves = rootNode.leafWeight;
  const maxTreeDepth = rootNode.maxDepth;
  const sons = rootNode.children || [];
  const numSons = sons.length;
  if (numSons === 0) return;

  // Dynamic Medallion dimensions
  if (totalLeaves > 22) {
    curMedallionRx = 76;
    curMedallionRy = 23;
  } else if (totalLeaves <= 8) {
    curMedallionRx = 86;
    curMedallionRy = 26;
  } else {
    curMedallionRx = 80;
    curMedallionRy = 24;
  }

  // 2. Radial canopy: every branch owns an angular sector proportional to its subtree weight.
  // A father with children always has at least a minimal weight so he never gets squeezed
  // between busier brothers. Rules are generic - no names or ids are used anywhere.
  function weightOf(n) {
    return n.isFather ? Math.max(n.leafWeight, 1.7) : 1;
  }
  const totalSpan = totalLeaves <= 12 ? 130 : (totalLeaves <= 24 ? 150 : 170);
  const startAngle = -totalSpan / 2.0;

  function assignSectors(node, sStart, sEnd) {
    node.sectorStart = sStart;
    node.sectorEnd = sEnd;
    if (!node.children || node.children.length === 0) {
      node.angle = (sStart + sEnd) / 2.0;
      return;
    }
    const total = node.children.reduce((s, c) => s + weightOf(c), 0);
    const span = sEnd - sStart;
    let cur = sStart;
    node.children.forEach(c => {
      const w = span * (weightOf(c) / total);
      assignSectors(c, cur, cur + w);
      cur += w;
    });
    // Father sits exactly in the middle of his children
    node.angle = (node.children[0].angle + node.children[node.children.length - 1].angle) / 2.0;
  }

  const sonsTotal = sons.reduce((s, c) => s + weightOf(c), 0);
  let curA = startAngle;
  sons.forEach(son => {
    const w = totalSpan * (weightOf(son) / sonsTotal);
    assignSectors(son, curA, curA + w);
    curA += w;
  });

  // 3. Radius per generation. The canopy grows with the number of medallions so every leaf
  // owns enough arc length (leaves alternate in/out rows). Because of that, children never
  // have to be dragged across another family: they simply fan out beyond their own father.
  const R1 = 330;
  const SLOT = 2 * curMedallionRx + 44;
  const neededLeafR = sonsTotal * SLOT * 0.36 / (totalSpan * Math.PI / 180);
  const minLeafR = R1 + Math.max(0, maxTreeDepth - 1) * 160;
  const leafR = Math.min(3400, Math.max(minLeafR, neededLeafR));
  const STEP = maxTreeDepth > 1 ? (leafR - R1) / (maxTreeDepth - 1) : 0;
  const leafParity = {};
  function positionNodes(node) {
    if (node !== rootNode) {
      let r = R1 + (node.depth - 1) * STEP;
      // Medallions without children alternate between an inner and an outer row
      if (!node.isFather) {
        const k = leafParity[node.depth] = (leafParity[node.depth] === undefined ? 0 : leafParity[node.depth] + 1);
        r += (k % 3) * 64;
      }
      node.radius = r;
      const rad = (node.angle * Math.PI) / 180.0;
      node.x = rootX + r * Math.sin(rad);
      node.y = rootY - r * Math.cos(rad);
      allNodesList.push(node);
    }
    (node.children || []).forEach(positionNodes);
  }
  positionNodes(rootNode);

  // 4. Relaxation: only resolves overlaps, never leaves the node's own angular sector
  const minRx = curMedallionRx + 10;
  const minRy = curMedallionRy + 8;
  for (let iter = 0; iter < 40; iter++) {
    for (let i = 0; i < allNodesList.length; i++) {
      for (let j = i + 1; j < allNodesList.length; j++) {
        const a = allNodesList[i];
        const b = allNodesList[j];
        if (a === rootNode || b === rootNode) continue;
        const normX = (b.x - a.x) / (2 * minRx);
        const normY = (b.y - a.y) / (2 * minRy);
        const d2 = normX * normX + normY * normY;
        if (d2 < 1.0 && d2 > 0.0001) {
          const d = Math.sqrt(d2);
          const ov = 1.0 - d;
          const pushX = (normX / d) * ov * minRx * 0.25;
          const pushY = (normY / d) * ov * minRy * 0.25;
          if (a.depth > 1) { a.x -= pushX; a.y -= pushY; }
          if (b.depth > 1) { b.x += pushX; b.y += pushY; }
          [a, b].forEach(n => {
            const ang = Math.atan2(n.x - rootX, rootY - n.y) * 180 / Math.PI;
            if (ang < n.sectorStart || ang > n.sectorEnd) {
              const cl = Math.max(n.sectorStart, Math.min(n.sectorEnd, ang));
              const rr = Math.hypot(n.x - rootX, rootY - n.y);
              n.x = rootX + rr * Math.sin(cl * Math.PI / 180);
              n.y = rootY - rr * Math.cos(cl * Math.PI / 180);
            }
          });
        }
      }
    }
  }
}

function createTaperedBranchPath(parent, child) {
  const isParentRoot = (parent.depth === 0);
  const x0 = parent.x;
  const y0 = parent.y - (isParentRoot ? ROOT_RY * 0.72 : curMedallionRy * 0.70);
  const x1 = child.x;
  const y1 = child.y + curMedallionRy * 0.82;

  let wStart, wEnd;
  if (isParentRoot) {
    wStart = 34; wEnd = 15;
  } else if (parent.depth === 1) {
    wStart = 15; wEnd = 8.5;
  } else if (parent.depth === 2) {
    wStart = 8.5; wEnd = 5.0;
  } else {
    wStart = 5.0; wEnd = 3.2;
  }

  const dy = y1 - y0;
  const dx = x1 - x0;

  let cp1x, cp1y, cp2x, cp2y;
  if (isParentRoot) {
    cp1x = x0 + dx * 0.12;
    cp1y = y0 + dy * 0.58;
    cp2x = x1 - dx * 0.14;
    cp2y = y1 - dy * 0.38;
  } else {
    cp1x = x0 + dx * 0.28;
    cp1y = y0 + dy * 0.45;
    cp2x = x1 - dx * 0.22;
    cp2y = y1 - dy * 0.35;
  }

  const sway = (dx > 0 ? 1 : (dx < 0 ? -1 : 0)) * Math.min(10, Math.abs(dx) * 0.03);
  const finalCp1x = cp1x + sway;
  const finalCp2x = cp2x + sway * 0.4;

  const hW0 = wStart / 2;
  const hW1 = wEnd / 2;
  const wMid = (wStart + wEnd) / 2;
  const hWm = wMid / 2;

  return `
    M ${x0 - hW0},${y0}
    C ${finalCp1x - hWm},${cp1y} ${finalCp2x - hW1},${cp2y} ${x1 - hW1},${y1}
    L ${x1 + hW1},${y1}
    C ${finalCp2x + hW1},${cp2y} ${finalCp1x + hWm},${cp1y} ${x0 + hW0},${y0}
    Z
  `;
}

// ═══════════════════════════════════════════════════════
//  BOTANICAL LAUREL WREATH LEAF SPRAY GENERATOR
// ═══════════════════════════════════════════════════════
function renderLeafSprays(container, node) {
  const g = container.append('g')
    .attr('transform', `translate(${node.x}, ${node.y})`);

  const isRoot = node.depth === 0;
  const leafFill = isRoot ? 'url(#leaf-gold-grad)' : 'url(#leaf-grad)';
  const rx = isRoot ? ROOT_RX : curMedallionRx;
  const ry = isRoot ? ROOT_RY : curMedallionRy;

  const totalLeaves = treeDataRoot ? treeDataRoot.leafCount : 10;
  const isDense = (layoutMode === 'portrait') && (totalLeaves > 10);
  const isVeryDense = (layoutMode === 'portrait') && (totalLeaves > 20);
  const scale = isRoot ? 1.25 : (isVeryDense ? 0.80 : (isDense ? 0.90 : 1.0));

  const leftLeaves = [
    { angle: -140, dx: -rx * 0.55, dy: -12, scale: 0.85 },
    { angle: -100, dx: -rx * 0.85, dy: 0,   scale: 0.95 },
    { angle: -60,  dx: -rx * 0.55, dy: 12,  scale: 0.85 }
  ];

  const rightLeaves = [
    { angle: 140, dx: rx * 0.55, dy: -12, scale: 0.85 },
    { angle: 100, dx: rx * 0.85, dy: 0,   scale: 0.95 },
    { angle: 60,  dx: rx * 0.55, dy: 12,  scale: 0.85 }
  ];

  [...leftLeaves, ...rightLeaves].forEach(leaf => {
    const lg = g.append('g')
      .attr('transform', `translate(${leaf.dx}, ${leaf.dy}) rotate(${leaf.angle}) scale(${scale * leaf.scale})`);

    lg.append('path')
      .attr('d', 'M 0,0 C -12,-18 -18,-42 0,-58 C 18,-42 12,-18 0,0 Z')
      .attr('transform', 'translate(0, -14)')
      .attr('fill', leafFill)
      .attr('stroke', isRoot ? '#78350f' : '#064e3b')
      .attr('stroke-width', '0.75')
      .attr('opacity', '0.94');

    lg.append('line')
      .attr('x1', 0).attr('y1', -14)
      .attr('x2', 0).attr('y2', -70)
      .attr('stroke', isRoot ? '#fef08a' : '#86efac')
      .attr('stroke-width', '0.85')
      .attr('opacity', '0.8');
  });

  const stemFill = isRoot ? 'url(#branch-grad)' : `url(#branch-grad-${node.clanIndex !== undefined ? node.clanIndex : 0})`;
  g.append('rect')
    .attr('x', -4)
    .attr('y', isRoot ? ROOT_RY * 0.72 : curMedallionRy * 0.72)
    .attr('width', 8)
    .attr('height', 16)
    .attr('rx', 3)
    .attr('fill', stemFill);
}

// ═══════════════════════════════════════════════════════
//  BOTANICAL MEDALLION GENERATOR (Fathers Gold, Children Pearl)
// ═══════════════════════════════════════════════════════
function renderMedallion(container, node) {
  const isRoot = node.depth === 0;
  const isFather = Boolean(((node.children && node.children.length > 0) || (node.rawChildrenCount && node.rawChildrenCount > 0)) && !isRoot);
  const clan = (node.clanIndex !== undefined && CLAN_THEMES[node.clanIndex]) ? CLAN_THEMES[node.clanIndex] : CLAN_THEMES[0];
  const isMale = !(node.gender && (node.gender.indexOf('مێ') !== -1 || node.gender.toLowerCase() === 'female'));

  // Dimensions: Fathers slightly more prominent, grandchildren compact
  const rx = isRoot ? ROOT_RX : (isFather ? curMedallionRx + 4 : curMedallionRx - 2);
  const ry = isRoot ? ROOT_RY : (isFather ? curMedallionRy + 2 : curMedallionRy - 1);

  const g = container.append('g')
    .attr('class', 'botanical-node' + (isFather ? ' father-node' : ''))
    .attr('id', `node-${node.id}`)
    .attr('transform', `translate(${node.x}, ${node.y})`)
    .on('click', (e) => {
      e.stopPropagation();
      openModal(node);
    });

  // Glowing Halo (illuminates on hover)
  g.append('ellipse')
    .attr('class', 'node-halo')
    .attr('rx', rx + 8)
    .attr('ry', ry + 8)
    .attr('fill', 'none')
    .attr('stroke', isRoot ? '#f59e0b' : (isFather ? '#d97706' : clan.accent))
    .attr('stroke-width', 2.5)
    .attr('opacity', 0)
    .attr('filter', 'url(#gold-glow)');

  // "وابکە باوکەکان رەنگێک بن و نەوەکان ڕەنگێک"
  // FATHERS get Rich Royal Gold Parchment Plate (plate-father) with Gold Rim!
  // GRANDCHILDREN / LEAVES get Pure Luminous Pearl Plate (plate-grandchild) with Clan Rim!
  let plateFill;
  if (isRoot) {
    plateFill = 'url(#plate-root)';
  } else if (isFather) {
    plateFill = 'url(#plate-father)'; // Distinct Gold Plate for all Fathers
  } else if (!isMale) {
    plateFill = 'url(#plate-female)';
  } else {
    plateFill = 'url(#plate-grandchild)'; // Distinct Pearl Plate for Grandchildren
  }

  // Border: Fathers get royal gold rim; grandchildren get clan rim
  const strokeBorder = (isRoot || isFather) ? 'url(#gold-rim)' : clan.rim;
  const strokeW = isRoot ? 3.5 : (isFather ? 3.4 : 2.8);

  g.append('ellipse')
    .attr('class', 'medallion-plate')
    .attr('rx', rx)
    .attr('ry', ry)
    .attr('fill', plateFill)
    .attr('stroke', strokeBorder)
    .attr('stroke-width', strokeW)
    .attr('filter', 'url(#medallion-shadow)');

  // For grandchildren: subtle clan color wash
  if (!isRoot && !isFather) {
    g.append('ellipse')
      .attr('rx', rx)
      .attr('ry', ry)
      .attr('fill', clan.halo)
      .attr('opacity', 0.08)
      .attr('stroke', 'none');
  }

  // Inner Ornate Beaded Rim
  g.append('ellipse')
    .attr('rx', rx - 5)
    .attr('ry', ry - 4.5)
    .attr('fill', 'none')
    .attr('stroke', isRoot ? '#b45309' : (isFather ? '#b45309' : clan.accent))
    .attr('stroke-width', isFather ? 1.2 : 0.8)
    .attr('stroke-dasharray', isFather ? '4, 2' : '3, 2.5')
    .attr('opacity', 0.85);

  // Left & Right Heraldic Crest Dots
  g.append('circle')
    .attr('cx', -rx + 11)
    .attr('cy', 0)
    .attr('r', isFather ? 3.2 : 2.5)
    .attr('fill', isRoot ? '#b45309' : (isFather ? '#b45309' : clan.accent));

  g.append('circle')
    .attr('cx', rx - 11)
    .attr('cy', 0)
    .attr('r', isFather ? 3.2 : 2.5)
    .attr('fill', isRoot ? '#b45309' : (isFather ? '#b45309' : clan.accent));

  // Display Name with Hierarchical Number Prefix:
  // e.g. "٤ السید احمد", "٤-١ السید حاجى عبدالله", "١-١ السید پێشەوا"
  const rawName = node.name || node.full_name || node.short || '';
  const nameY = (node.birth || isRoot || isFather || node.depth === 1) ? -3 : 2;
  const nameFontSize = isRoot ? '17px' : (isFather ? '13.5px' : '12.5px');
  const subFontSize  = isRoot ? '10.5px' : '9px';

  const nameText = g.append('text')
    .attr('x', 0)
    .attr('y', nameY)
    .attr('text-anchor', 'middle')
    .attr('dominant-baseline', 'middle')
    .attr('font-family', "'Amiri', 'Vazirmatn', serif")
    .attr('font-size', nameFontSize)
    .attr('font-weight', '700')
    .attr('fill', '#261205')
    .attr('letter-spacing', '0.2px');

  if (node.treeCode && !isRoot) {
    const kurdishCode = toKurdishNumber(node.treeCode);
    // Explicit LTR tspan ensures 4-1 is NEVER reversed by RTL document direction
    nameText.append('tspan')
      .attr('direction', 'ltr')
      .attr('unicode-bidi', 'bidi-override')
      .text(kurdishCode + ' ');
  }
  nameText.append('tspan')
    .text(rawName);

  // Subtitle: Fathers show child count; descendants show their code
  let subText = null;
  if (isRoot) {
    subText = '👑 بنچینەی دارەکە (باوانی یەکەم)';
  } else if (isFather) {
    const chCount = (node.children && node.children.length > 0) ? node.children.length : (node.rawChildrenCount || 0);
    subText = chCount > 0 ? `👑 باوک (${toKurdishNumber(chCount)} منداڵ)` : `🌿 لقی ${clan.name}`;
  } else if (node.depth === 1) {
    subText = `🌿 لقی ${clan.name}`;
  } else if (node.birth) {
    subText = `📅 ${toKurdishNumber(node.birth)} کۆچی`;
  } else if (node.code) {
    subText = `کۆد: ${toKurdishNumber(node.code)}`;
  }

  if (subText) {
    g.append('text')
      .attr('x', 0)
      .attr('y', isRoot ? 18 : 15)
      .attr('text-anchor', 'middle')
      .attr('dominant-baseline', 'middle')
      .attr('font-family', "'Vazirmatn', sans-serif")
      .attr('font-size', subFontSize)
      .attr('font-weight', isFather ? '800' : '700')
      .attr('fill', isRoot ? '#78350f' : (isFather ? '#92400e' : clan.accent))
      .text(subText);
  }

  // Top Identification Badge Seal:
  // Fathers get circular gold badge with father number (e.g. ٤, ٥, ٣, ١)
  // Descendants get sleek pill badge with code (e.g. ٤-١, ١-١, ٣-٢)
  if (node.treeCode && !isRoot) {
    const kurdishCode = toKurdishNumber(node.treeCode);
    const badgeG = g.append('g')
      .attr('transform', `translate(0, ${-ry + 1})`);

    const hasDash = node.treeCode.includes('-');
    if (hasDash) {
      badgeG.append('rect')
        .attr('x', -18)
        .attr('y', -8.5)
        .attr('width', 36)
        .attr('height', 17)
        .attr('rx', 8.5)
        .attr('fill', clan.badge)
        .attr('stroke', '#ffffff')
        .attr('stroke-width', 1.2)
        .attr('filter', 'url(#medallion-shadow)');

      badgeG.append('text')
        .attr('x', 0)
        .attr('y', 0.5)
        .attr('text-anchor', 'middle')
        .attr('dominant-baseline', 'central')
        .attr('direction', 'ltr')
        .attr('unicode-bidi', 'bidi-override')
        .attr('font-size', '9.5px')
        .attr('font-weight', '900')
        .attr('fill', '#ffffff')
        .text(kurdishCode);
    } else {
      badgeG.append('circle')
        .attr('r', 10.5)
        .attr('fill', '#b45309')
        .attr('stroke', '#ffffff')
        .attr('stroke-width', 1.4)
        .attr('filter', 'url(#medallion-shadow)');

      badgeG.append('text')
        .attr('x', 0)
        .attr('y', 0.5)
        .attr('text-anchor', 'middle')
        .attr('dominant-baseline', 'central')
        .attr('font-size', '10px')
        .attr('font-weight', '900')
        .attr('fill', '#ffffff')
        .text('\u202A' + kurdishCode + '\u202C');
    }
  }
}

function renderTrunkAndRoots(rootNode) {
  const rx = rootNode.x;
  const ry = rootNode.y;
  const scrollY = ry + 230;

  // 1. Lush Green Earth Mound - Cradles the top and shoulders of the parchment board
  gEarth.append('path')
    .attr('d', `
      M ${rx - 720},${scrollY + 85}
      C ${rx - 400},${scrollY - 80} ${rx + 400},${scrollY - 80} ${rx + 720},${scrollY + 85}
      L ${rx + 720},${scrollY + 145}
      L ${rx - 720},${scrollY + 145}
      Z
    `)
    .attr('fill', 'url(#earth-grad)')
    .attr('opacity', '0.96');

  // Decorative Golden Terraced Arc
  gEarth.append('path')
    .attr('d', `
      M ${rx - 660},${scrollY + 70}
      C ${rx - 360},${scrollY - 65} ${rx + 360},${scrollY - 65} ${rx + 660},${scrollY + 70}
    `)
    .attr('fill', 'none')
    .attr('stroke', '#d97706')
    .attr('stroke-width', '2')
    .attr('stroke-dasharray', '8, 6')
    .attr('opacity', '0.65');

  // Tufts of wild botanical grass along the earth crest
  const grassOffsets = [-520, -380, -250, 0, 250, 380, 520];
  grassOffsets.forEach(gx => {
    const gy = scrollY + 45 - Math.cos((gx / 660) * (Math.PI / 2)) * 95;
    const grassG = gEarth.append('g').attr('transform', `translate(${rx + gx}, ${gy})`);
    grassG.append('path')
      .attr('d', 'M 0,0 C -3,-14 -8,-26 -16,-34 C -6,-20 -2,-10 0,0 C 2,-10 6,-20 16,-34 C 8,-26 3,-14 0,0 Z')
      .attr('fill', '#4ade80')
      .attr('opacity', '0.8');
  });

  // 2. Sculpted Mighty Wood Trunk with Botanical Gateway Arch
  // Outer Left Buttress: Sweeps wide down over the left shoulder of the board
  gTrunk.append('path')
    .attr('d', `
      M ${rx - 36},${ry + 42}
      C ${rx - 75},${ry + 110} ${rx - 160},${ry + 180} ${rx - 340},${scrollY + 38}
      C ${rx - 295},${scrollY + 52} ${rx - 130},${ry + 195} ${rx - 22},${ry + 140}
      Z
    `)
    .attr('fill', 'url(#trunk-wood)')
    .attr('filter', 'url(#branch-shadow)');

  // Outer Right Buttress: Sweeps wide down over the right shoulder of the board
  gTrunk.append('path')
    .attr('d', `
      M ${rx + 36},${ry + 42}
      C ${rx + 75},${ry + 110} ${rx + 160},${ry + 180} ${rx + 340},${scrollY + 38}
      C ${rx + 295},${scrollY + 52} ${rx + 130},${ry + 195} ${rx + 22},${ry + 140}
      Z
    `)
    .attr('fill', 'url(#trunk-wood)')
    .attr('filter', 'url(#branch-shadow)');

  // Inner Left Root Limb - Grips gracefully to the left of the golden cartouche
  gTrunk.append('path')
    .attr('d', `
      M ${rx - 20},${ry + 100}
      C ${rx - 50},${ry + 155} ${rx - 95},${scrollY - 10} ${rx - 155},${scrollY + 32}
      C ${rx - 130},${scrollY + 40} ${rx - 45},${ry + 175} ${rx - 8},${ry + 120}
      Z
    `)
    .attr('fill', 'url(#trunk-wood)')
    .attr('opacity', '0.95')
    .attr('filter', 'url(#branch-shadow)');

  // Inner Right Root Limb - Grips gracefully to the right of the golden cartouche
  gTrunk.append('path')
    .attr('d', `
      M ${rx + 20},${ry + 100}
      C ${rx + 50},${ry + 155} ${rx + 95},${scrollY - 10} ${rx + 155},${scrollY + 32}
      C ${rx + 130},${scrollY + 40} ${rx + 45},${ry + 175} ${rx + 8},${ry + 120}
      Z
    `)
    .attr('fill', 'url(#trunk-wood)')
    .attr('opacity', '0.95')
    .attr('filter', 'url(#branch-shadow)');

  // Central Trunk Pillar with Raised Arch Gateway (Leaves Golden Cartouche completely clear!)
  gTrunk.append('path')
    .attr('d', `
      M ${rx - 42},${ry + ROOT_RY * 0.8}
      C ${rx - 52},${ry + 85} ${rx - 75},${ry + 150} ${rx - 85},${scrollY + 10}
      C ${rx - 40},${scrollY - 22} ${rx + 40},${scrollY - 22} ${rx + 85},${scrollY + 10}
      C ${rx + 75},${ry + 150} ${rx + 52},${ry + 85} ${rx + 42},${ry + ROOT_RY * 0.8}
      Z
    `)
    .attr('fill', 'url(#trunk-wood)')
    .attr('filter', 'url(#branch-shadow)');

  // Bark Vertical Fiber Details
  const fiberOffsets = [-24, -12, 0, 12, 24];
  fiberOffsets.forEach(off => {
    gTrunk.append('path')
      .attr('d', `
        M ${rx + off * 0.7},${ry + 45}
        C ${rx + off * 1.05},${ry + 90} ${rx + off * 1.3},${ry + 150} ${rx + off * 1.7},${scrollY - 5}
      `)
      .attr('fill', 'none')
      .attr('stroke', off === 0 ? '#8c451e' : '#220f04')
      .attr('stroke-width', off === 0 ? '2' : '1.2')
      .attr('opacity', '0.65');
  });

  // Royal Golden Root Clasps & Fresh Green Leaf Sprouts where roots embrace the scroll board
  const claspPositions = [-335, -150, 150, 335];
  claspPositions.forEach(cx => {
    const cg = gTrunk.append('g').attr('transform', `translate(${rx + cx}, ${scrollY + 30})`);
    // Shadow
    cg.append('ellipse').attr('rx', 9).attr('ry', 4).attr('cy', 3).attr('fill', 'rgba(0,0,0,0.25)');
    // Golden Ring / Fastener Bracket
    cg.append('circle').attr('r', 6.5).attr('fill', '#d97706').attr('stroke', '#fef3c7').attr('stroke-width', 1.5);
    cg.append('circle').attr('r', 3).attr('fill', '#78350f');
    // Fresh Green Botanical Sprout Leaves (Pair of leaves)
    cg.append('path')
      .attr('d', 'M 0,0 C -6,-10 -8,-20 0,-28 C 8,-20 6,-10 0,0 Z')
      .attr('transform', `rotate(${cx > 0 ? 42 : -42}) scale(0.75)`)
      .attr('fill', 'url(#leaf-grad)');
    cg.append('path')
      .attr('d', 'M 0,0 C -5,-8 -6,-16 0,-22 C 6,-16 5,-8 0,0 Z')
      .attr('transform', `rotate(${cx > 0 ? 15 : -15}) scale(0.55)`)
      .attr('fill', 'url(#leaf-grad)');
  });
}

function renderArtisticBotanicalTree() {
  // Clear previous graphics
  gEarth.selectAll('*').remove();
  gTrunk.selectAll('*').remove();
  gBranches.selectAll('*').remove();
  gLeaves.selectAll('*').remove();
  gMedallions.selectAll('*').remove();
  gScroll.selectAll('*').remove();

  allNodesList = [];
  currentLeafIdx = 0;

  // Clone data hierarchy
  treeDataRoot = JSON.parse(JSON.stringify(RAW_TREE));

  if (onlySonsMode && treeDataRoot.children) {
    treeDataRoot.children.forEach(c => {
      c.rawChildrenCount = (c.children || []).length;
      c.children = [];
    });
  }

  // 1. Execute Botanical Upward Canopy & Curved Limbs Engine
  const initialRootX = window.innerWidth / 2;
  const initialRootY = window.innerHeight * 0.88;
  layoutBotanicalTree(treeDataRoot, initialRootX, initialRootY);

  // Compute bounding box
  treeBounds.minX = d3.min(allNodesList, d => d.x) - curMedallionRx - 70;
  treeBounds.maxX = d3.max(allNodesList, d => d.x) + curMedallionRx + 70;
  treeBounds.minY = d3.min(allNodesList, d => d.y) - curMedallionRy - 70;
  treeBounds.maxY = treeDataRoot.y + 360;

  // 4. Render Trunk and Earth Base
  renderTrunkAndRoots(treeDataRoot);

  // 5. Render Wood Limbs (Connecting parents to children)
  allNodesList.forEach(parent => {
    if (parent.children && parent.children.length > 0) {
      // Branch Split Joint Collar (Wooden knot)
      const jointRadius = parent.depth === 0 ? 17 : 11;
      const knotFill = parent.depth === 0 ? 'url(#branch-knot)' : `url(#branch-knot-${parent.clanIndex !== undefined ? parent.clanIndex : 0})`;
      gBranches.append('circle')
        .attr('cx', parent.x)
        .attr('cy', parent.y - (parent.depth === 0 ? ROOT_RY * 0.75 : curMedallionRy * 0.75))
        .attr('r', jointRadius)
        .attr('fill', knotFill)
        .attr('filter', 'url(#branch-shadow)');

      // Leaf buds at the joint
      const knotLeaves = gBranches.append('g')
        .attr('transform', `translate(${parent.x}, ${parent.y - (parent.depth === 0 ? ROOT_RY * 0.75 : curMedallionRy * 0.75)})`);

      knotLeaves.append('path')
        .attr('d', 'M 0,0 C -6,-10 -8,-20 0,-28 C 8,-20 6,-10 0,0 Z')
        .attr('transform', 'rotate(-45) scale(0.6)')
        .attr('fill', 'url(#leaf-grad)');

      knotLeaves.append('path')
        .attr('d', 'M 0,0 C -6,-10 -8,-20 0,-28 C 8,-20 6,-10 0,0 Z')
        .attr('transform', 'rotate(45) scale(0.6)')
        .attr('fill', 'url(#leaf-grad)');

      parent.children.forEach(child => {
        // Tapered 3D Wood Limb Path with smooth cubic bezier curve
        const limbPath = createTaperedBranchPath(parent, child);
        const clanIdx = child.clanIndex !== undefined ? child.clanIndex : 0;
        const clanTheme = CLAN_THEMES[clanIdx] || CLAN_THEMES[0];

        gBranches.append('path')
          .attr('class', 'wood-limb')
          .attr('d', limbPath)
          .attr('fill', `url(#branch-grad-${clanIdx})`)
          .attr('filter', 'url(#branch-shadow)');

        // Small leaf sprout along long branches
        const dx = child.x - parent.x;
        const dy = parent.y - child.y;
        if (Math.abs(dx) > 100 || dy > 160) {
          const mx = parent.x + dx * 0.42;
          const my = parent.y - dy * 0.45;
          const sproutG = gBranches.append('g')
            .attr('transform', `translate(${mx}, ${my}) rotate(${dx > 0 ? -38 : 38})`);

          sproutG.append('line')
            .attr('x1', 0).attr('y1', 0).attr('x2', 0).attr('y2', -14)
            .attr('stroke', clanTheme.dark).attr('stroke-width', 2);

          sproutG.append('path')
            .attr('d', 'M 0,-14 C -6,-22 -8,-32 0,-40 C 8,-32 6,-22 0,-14 Z')
            .attr('transform', 'rotate(-25, 0, -14) scale(0.55)')
            .attr('fill', 'url(#leaf-grad)');

          sproutG.append('path')
            .attr('d', 'M 0,-14 C -6,-22 -8,-32 0,-40 C 8,-32 6,-22 0,-14 Z')
            .attr('transform', 'rotate(25, 0, -14) scale(0.55)')
            .attr('fill', 'url(#leaf-grad)');
        }
      });
    }
  });

  // 6. Render Leaf Sprays & Medallions
  allNodesList.forEach(node => {
    renderLeafSprays(gLeaves, node);
    renderMedallion(gMedallions, node);
  });

  // 7. Render Royal Lineage Scroll under tree (Up to Prophet Muhammad)
  renderBaseLineageScroll(treeDataRoot);
  renderPrintStatsCards(treeDataRoot);
}


// ═══════════════════════════════════════════════════════
//  PRINT & POSTER STATS CARDS (Descendants Left, Ancestors Right)
// ═══════════════════════════════════════════════════════
function renderPrintStatsCards(rootNode) {
  gStatsCards.selectAll('*').remove();

  const rx = rootNode.x;
  const ry = rootNode.y;

  const cardW = 330;
  const cardH = 145;
  const cardY = ry + 120;
  const leftX = rx - 355;
  const rightX = rx + 355;

  const curTot = onlySonsMode ? (RAW_TREE.children || []).length : originalStats.tot;
  const curMales = onlySonsMode ? (RAW_TREE.children || []).length : originalStats.males;
  const curFem = onlySonsMode ? 0 : originalStats.fem;
  const curGen = onlySonsMode ? 1 : originalStats.gen;

  // ════════════════════════════════════════════════════════
  // 1. LEFT CARD: Descendants Stats (ئاماری نەوەکانی دارەکە)
  // ════════════════════════════════════════════════════════
  const gLeft = gStatsCards.append('g')
    .attr('class', 'tree-stat-card card-descendants')
    .attr('transform', `translate(${leftX}, ${cardY})`);

  // Outer parchment card
  gLeft.append('rect')
    .attr('x', -cardW / 2)
    .attr('y', -cardH / 2)
    .attr('width', cardW)
    .attr('height', cardH)
    .attr('rx', 18)
    .attr('fill', '#fffdf9')
    .attr('stroke', '#d97706')
    .attr('stroke-width', 2.4)
    .attr('filter', 'url(#medallion-shadow)');

  // Inner dashed border
  gLeft.append('rect')
    .attr('x', -cardW / 2 + 8)
    .attr('y', -cardH / 2 + 8)
    .attr('width', cardW - 16)
    .attr('height', cardH - 16)
    .attr('rx', 12)
    .attr('fill', 'none')
    .attr('stroke', '#b45309')
    .attr('stroke-width', 1.1)
    .attr('stroke-dasharray', '6, 4')
    .attr('opacity', 0.65);

  // 4 Corner Arabesque Flairs
  const flairs = [
    [-cardW / 2 + 14, -cardH / 2 + 14],
    [cardW / 2 - 14, -cardH / 2 + 14],
    [-cardW / 2 + 14, cardH / 2 - 14],
    [cardW / 2 - 14, cardH / 2 - 14]
  ];
  flairs.forEach(([cx, cy]) => {
    gLeft.append('circle').attr('cx', cx).attr('cy', cy).attr('r', 3).attr('fill', '#d97706');
  });

  // Top Title Ribbon
  const leftRibbon = gLeft.append('g').attr('transform', `translate(0, ${-cardH / 2})`);
  leftRibbon.append('rect')
    .attr('x', -105)
    .attr('y', -14)
    .attr('width', 210)
    .attr('height', 28)
    .attr('rx', 14)
    .attr('fill', 'url(#gold-grad)')
    .attr('stroke', '#fef3c7')
    .attr('stroke-width', 1.4)
    .attr('filter', 'url(#medallion-shadow)');

  leftRibbon.append('text')
    .attr('x', 0)
    .attr('y', 0.5)
    .attr('text-anchor', 'middle')
    .attr('dominant-baseline', 'central')
    .attr('font-family', "'Amiri', serif")
    .attr('font-size', '13.5px')
    .attr('font-weight', '700')
    .attr('fill', '#ffffff')
    .text(onlySonsMode ? '🌿 ئاماری کوڕەکان (نەوەی ١)' : '🌿 ئاماری نەوەکانی دارەکە');

  // Stat Lines inside Left Card
  // Line 1: Total Descendants
  const tLeft1 = gLeft.append('text')
    .attr('x', 0)
    .attr('y', -12)
    .attr('text-anchor', 'middle')
    .attr('dominant-baseline', 'central')
    .attr('font-family', "'Amiri', serif")
    .attr('font-size', '14.5px')
    .attr('fill', '#261205');
  tLeft1.append('tspan').text(onlySonsMode ? '👥 تەنها کوڕەکان: ' : '👥 کۆی گشتی نەوەکان: ');
  tLeft1.append('tspan')
    .attr('font-weight', '900')
    .attr('font-size', '16.5px')
    .attr('fill', '#047857')
    .text(`${toKurdishNumber(curTot)} کەس`);

  // Line 2: Sons & Daughters
  const tLeft2 = gLeft.append('text')
    .attr('x', 0)
    .attr('y', 16)
    .attr('text-anchor', 'middle')
    .attr('dominant-baseline', 'central')
    .attr('font-family', "'Amiri', serif")
    .attr('font-size', '13.5px')
    .attr('fill', '#451a03');
  tLeft2.append('tspan').text('👦 کوڕ: ');
  tLeft2.append('tspan').attr('font-weight', '800').attr('fill', '#0284c7').text(toKurdishNumber(curMales));
  tLeft2.append('tspan').text('   |   👧 کچ: ');
  tLeft2.append('tspan').attr('font-weight', '800').attr('fill', '#db2777').text(toKurdishNumber(curFem));

  // Line 3: Generations
  const tLeft3 = gLeft.append('text')
    .attr('x', 0)
    .attr('y', 42)
    .attr('text-anchor', 'middle')
    .attr('dominant-baseline', 'central')
    .attr('font-family', "'Amiri', serif")
    .attr('font-size', '13.5px')
    .attr('fill', '#451a03');
  tLeft3.append('tspan').text('👑 ژمارەی پشتەکان: ');
  tLeft3.append('tspan')
    .attr('font-weight', '900')
    .attr('font-size', '15px')
    .attr('fill', '#b45309')
    .text(`${toKurdishNumber(curGen)} پشت`);


  // ════════════════════════════════════════════════════════
  // 2. RIGHT CARD: Ancestral Lineage Stats (ئاماری باوانی پیرۆز)
  // ════════════════════════════════════════════════════════
  const gRight = gStatsCards.append('g')
    .attr('class', 'tree-stat-card card-ancestors')
    .attr('transform', `translate(${rightX}, ${cardY})`);

  // Outer parchment card
  gRight.append('rect')
    .attr('x', -cardW / 2)
    .attr('y', -cardH / 2)
    .attr('width', cardW)
    .attr('height', cardH)
    .attr('rx', 18)
    .attr('fill', '#fffdf9')
    .attr('stroke', '#d97706')
    .attr('stroke-width', 2.4)
    .attr('filter', 'url(#medallion-shadow)');

  // Inner dashed border
  gRight.append('rect')
    .attr('x', -cardW / 2 + 8)
    .attr('y', -cardH / 2 + 8)
    .attr('width', cardW - 16)
    .attr('height', cardH - 16)
    .attr('rx', 12)
    .attr('fill', 'none')
    .attr('stroke', '#b45309')
    .attr('stroke-width', 1.1)
    .attr('stroke-dasharray', '6, 4')
    .attr('opacity', 0.65);

  flairs.forEach(([cx, cy]) => {
    gRight.append('circle').attr('cx', cx).attr('cy', cy).attr('r', 3).attr('fill', '#d97706');
  });

  // Top Title Ribbon
  const rightRibbon = gRight.append('g').attr('transform', `translate(0, ${-cardH / 2})`);
  rightRibbon.append('rect')
    .attr('x', -110)
    .attr('y', -14)
    .attr('width', 220)
    .attr('height', 28)
    .attr('rx', 14)
    .attr('fill', 'url(#gold-grad)')
    .attr('stroke', '#fef3c7')
    .attr('stroke-width', 1.4)
    .attr('filter', 'url(#medallion-shadow)');

  rightRibbon.append('text')
    .attr('x', 0)
    .attr('y', 0.5)
    .attr('text-anchor', 'middle')
    .attr('dominant-baseline', 'central')
    .attr('font-family', "'Amiri', serif")
    .attr('font-size', '13.5px')
    .attr('font-weight', '700')
    .attr('fill', '#ffffff')
    .text('📜 زنجیرەی پیرۆزی باوان');

  // Stat Lines inside Right Card
  // Line 1: Lineage to Prophet Muhammad
  const tRight1 = gRight.append('text')
    .attr('x', 0)
    .attr('y', -10)
    .attr('text-anchor', 'middle')
    .attr('dominant-baseline', 'central')
    .attr('font-family', "'Amiri', serif")
    .attr('font-size', '14px')
    .attr('fill', '#261205');
  tRight1.append('tspan').text('🕌 ڕەچەڵەک تا پێغەمبەر ﷺ: ');
  tRight1.append('tspan')
    .attr('font-weight', '900')
    .attr('font-size', '16.5px')
    .attr('fill', '#047857')
    .text(`${toKurdishNumber(ANCESTORS_TO_PROPHET)} پشت`);

  // Line 2: Lineage to Pir Khidr Shaho
  const tRight2 = gRight.append('text')
    .attr('x', 0)
    .attr('y', 24)
    .attr('text-anchor', 'middle')
    .attr('dominant-baseline', 'central')
    .attr('font-family', "'Amiri', serif")
    .attr('font-size', '14px')
    .attr('fill', '#261205');
  tRight2.append('tspan').text('👑 ڕەچەڵەک تا پیرخدری شاهۆ: ');
  if (IS_ROOT_SHAHO) {
    tRight2.append('tspan')
      .attr('font-weight', '800')
      .attr('font-size', '13.5px')
      .attr('fill', '#047857')
      .text('خۆی باوانی بنچینەیە');
  } else {
    tRight2.append('tspan')
      .attr('font-weight', '900')
      .attr('font-size', '16px')
      .attr('fill', '#b45309')
      .text(`${toKurdishNumber(ANCESTORS_TO_SHAHO)} پشت`);
  }

  // Ensure treeBounds encompasses stats cards comfortably
  treeBounds.minX = Math.min(treeBounds.minX, leftX - cardW / 2 - 35);
  treeBounds.maxX = Math.max(treeBounds.maxX, rightX + cardW / 2 + 35);
}

// ═══════════════════════════════════════════════════════
//  ROYAL ANCESTRAL LINEAGE SCROLL (To Prophet Muhammad)
// ═══════════════════════════════════════════════════════
function renderBaseLineageScroll(rootNode) {
  gScroll.selectAll('*').remove();
  if (!FULL_LINEAGE_TEXT || FULL_LINEAGE_TEXT.trim() === '') return;

  const rx = rootNode.x;
  const ry = rootNode.y;
  // Positioned nestled directly under the trunk roots (blended seamlessly!)
  const scrollY = ry + 230;

  const scrollG = gScroll.append('g')
    .attr('class', 'royal-lineage-scroll')
    .attr('transform', `translate(${rx}, ${scrollY})`);

  // Split ancestral names cleanly by 'بن' or 'ابن'
  const rawNames = FULL_LINEAGE_TEXT.split(/\s+(?:بن|ابن)\s+/u).map(s => s.trim()).filter(Boolean);

  // Equalized Balanced Line Partitioning Algorithm:
  // Balances ancestors into 5 majestic, equal-length calligraphic rows
  function partitionBalancedLineage(names) {
    const totalChars = names.reduce((acc, n) => acc + n.length + 4, 0);
    const targetChars = totalChars / 4.8;

    const linesArr = [];
    let cur = [];
    let curLen = 0;

    for (let i = 0; i < names.length; i++) {
      const n = names[i];
      const nLen = n.length + 4;
      const linesLeft = 5 - linesArr.length;
      const itemsLeft = names.length - i;

      if (linesLeft > 1 && (curLen + nLen > targetChars * 1.05 || itemsLeft <= linesLeft - 1) && cur.length > 0) {
        linesArr.push(cur.join(' بن ') + ' بن');
        cur = [n];
        curLen = nLen;
      } else {
        cur.push(n);
        curLen += nLen;
      }
    }
    if (cur.length > 0) {
      linesArr.push(cur.join(' بن '));
    }
    return linesArr;
  }

  const lines = partitionBalancedLineage(rawNames);

  const startY = 186;
  const lineHeight = 44; // Comfortable, stately vertical spacing for enlarged 17.5px font
  const textBlockH = lines.length * lineHeight;
  const scrollH = startY + textBlockH + 42;

  // Background rect placeholders (will be sized snuggly after text width measurement)
  const outerRect = scrollG.append('rect')
    .attr('y', 0)
    .attr('height', scrollH)
    .attr('rx', 24)
    .attr('fill', '#fffdf9')
    .attr('stroke', '#d97706')
    .attr('stroke-width', 2.8)
    .attr('filter', 'url(#medallion-shadow)');

  const innerRect = scrollG.append('rect')
    .attr('y', 12)
    .attr('height', scrollH - 24)
    .attr('rx', 18)
    .attr('fill', 'none')
    .attr('stroke', '#b45309')
    .attr('stroke-width', 1.3)
    .attr('stroke-dasharray', '8, 5')
    .attr('opacity', '0.65');

  // Header Title Plaque (Golden Cartouche) - Proudly visible in the central root arch
  // User explicit request: "پاشانی مەنوسە ڕەچەلەکی باوانی دارەکە بنوسە ڕەچەڵەکی باوانی بنەماڵەکە"
  const headerG = scrollG.append('g').attr('transform', `translate(0, 62)`);
  headerG.append('rect')
    .attr('x', -300)
    .attr('y', -21)
    .attr('width', 600)
    .attr('height', 42)
    .attr('rx', 21)
    .attr('fill', 'url(#gold-grad)')
    .attr('stroke', '#fef3c7')
    .attr('stroke-width', 1.6)
    .attr('filter', 'url(#medallion-shadow)');

  // Inner gold filigree line
  headerG.append('rect')
    .attr('x', -293)
    .attr('y', -16)
    .attr('width', 586)
    .attr('height', 32)
    .attr('rx', 16)
    .attr('fill', 'none')
    .attr('stroke', '#fbbf24')
    .attr('stroke-width', 0.9)
    .attr('stroke-dasharray', '5, 3')
    .attr('opacity', 0.85);

  headerG.append('text')
    .attr('x', 0)
    .attr('y', 1)
    .attr('text-anchor', 'middle')
    .attr('dominant-baseline', 'central')
    .attr('font-family', "'Amiri', serif")
    .attr('font-size', '17.5px')
    .attr('font-weight', '700')
    .attr('fill', '#ffffff')
    .attr('letter-spacing', '0.3px')
    .text(`📜 ڕەچەڵەکی باوانی بنەماڵەکە تا پێغەمبەری خودا ﷺ`);

  // Root Person Callout Plaque (Sub-banner)
  // User explicit request: "لە خوارتریش بنوسە باوانی بنچینەی بنەماڵەی : السید رضا"
  const plaqueW = Math.max(430, Math.min(560, (ROOT_INFO.full_name.length + 26) * 11));
  const rootPlaqueG = scrollG.append('g').attr('transform', `translate(0, 110)`);
  rootPlaqueG.append('rect')
    .attr('x', -plaqueW / 2)
    .attr('y', -16)
    .attr('width', plaqueW)
    .attr('height', 32)
    .attr('rx', 16)
    .attr('fill', '#fef3c7')
    .attr('stroke', '#f59e0b')
    .attr('stroke-width', 1.3);

  rootPlaqueG.append('text')
    .attr('x', 0)
    .attr('y', 0.5)
    .attr('text-anchor', 'middle')
    .attr('dominant-baseline', 'central')
    .attr('font-family', "'Amiri', serif")
    .attr('font-size', '14.5px')
    .attr('font-weight', '700')
    .attr('fill', '#92400e')
    .text(`👑 باوانی بنچینەی بنەماڵەی: ${ROOT_INFO.full_name}`);

  // Divider Line Group
  const divG = scrollG.append('g').attr('transform', `translate(0, 146)`);
  const divLine = divG.append('line')
    .attr('y1', 0).attr('y2', 0)
    .attr('stroke', '#d97706')
    .attr('stroke-width', 1.2)
    .attr('stroke-dasharray', '8, 5')
    .attr('opacity', 0.7);
  divG.append('circle').attr('cx', 0).attr('cy', 0).attr('r', 4.8).attr('fill', '#b45309');
  divG.append('circle').attr('cx', -30).attr('cy', 0).attr('r', 3).attr('fill', '#d97706');
  divG.append('circle').attr('cx', 30).attr('cy', 0).attr('r', 3).attr('fill', '#d97706');

  // Regex to detect Pir Khidr Shaho and Prophet Muhammad
  const shahoRegex = /(السيد\s+محمد\s+الملقب\s+ب\s*\([^)]*(?:بيرخضر|پیرخضر|پیرخدر|بيرخدر)[^)]*\)|\([^)]*(?:بيرخضر|پیرخضر|پیرخدر|بيرخدر)[^)]*\))/u;
  const prophetRegex = /(محمد\s+رسول\s+الله.*?صلى\s+الله\s+عليه\s+و\s*سلم.*?)(?:$|ـ|\))/u;

  // Render Lineage Lines
  const textG = scrollG.append('g').attr('transform', `translate(0, ${startY})`);

  lines.forEach((lineText, idx) => {
    const isLast = (idx === lines.length - 1);
    const lineY = idx * lineHeight;

    const t = textG.append('text')
      .attr('x', 0)
      .attr('y', lineY)
      .attr('text-anchor', 'middle')
      .attr('dominant-baseline', 'central')
      .attr('font-family', "'Amiri', 'Traditional Arabic', serif")
      .attr('letter-spacing', '0.25px');

    // Break line into segments to highlight Shaho & Prophet in Bold Emerald Green
    let segments = [{ text: lineText, type: 'normal' }];

    // Match Shaho
    let seg1 = [];
    segments.forEach(seg => {
      if (seg.type === 'normal' && shahoRegex.test(seg.text)) {
        const parts = seg.text.split(shahoRegex);
        parts.forEach(p => {
          if (!p) return;
          if (shahoRegex.test(p)) {
            seg1.push({ text: p, type: 'shaho' });
          } else {
            seg1.push({ text: p, type: 'normal' });
          }
        });
      } else {
        seg1.push(seg);
      }
    });

    // Match Prophet
    let seg2 = [];
    seg1.forEach(seg => {
      if (seg.type === 'normal' && prophetRegex.test(seg.text)) {
        const parts = seg.text.split(prophetRegex);
        parts.forEach(p => {
          if (!p) return;
          if (prophetRegex.test(p)) {
            seg2.push({ text: p, type: 'prophet' });
          } else {
            seg2.push({ text: p, type: 'normal' });
          }
        });
      } else {
        seg2.push(seg);
      }
    });

    // Append tspans
    seg2.forEach(s => {
      if (s.type === 'shaho') {
        t.append('tspan')
          .attr('fill', '#047857')
          .attr('font-weight', '900')
          .attr('font-size', '19.5px')
          .text(s.text);
      } else if (s.type === 'prophet') {
        t.append('tspan')
          .attr('fill', '#047857')
          .attr('font-weight', '900')
          .attr('font-size', '20px')
          .text(s.text);
      } else {
        t.append('tspan')
          .attr('fill', isLast ? '#065f46' : '#2e1503')
          .attr('font-weight', isLast ? '700' : '600')
          .attr('font-size', isLast ? '18px' : '17.5px')
          .text(s.text);
      }
    });
  });

  // Dynamic Snug Width Calculation:
  // Measure rendered text width so margins are snug and never excessive!
  let maxMeasuredW = 0;
  textG.selectAll('text').each(function() {
    if (this.getComputedTextLength) {
      const len = this.getComputedTextLength();
      if (len > maxMeasuredW) maxMeasuredW = len;
    }
  });

  const maxChars = Math.max(...lines.map(l => l.length));
  const fallbackW = Math.round(maxChars * 5.4);
  const textWidth = (maxMeasuredW > 500) ? maxMeasuredW : fallbackW;

  // Snug border margin: 42px on each side of the text inside the border
  const scrollW = Math.max(1220, Math.round(textWidth + 84));

  // Apply snug dimensions to outer and inner borders
  outerRect.attr('x', -scrollW / 2).attr('width', scrollW);
  innerRect.attr('x', -scrollW / 2 + 12).attr('width', scrollW - 24);
  divLine.attr('x1', -scrollW * 0.40).attr('x2', scrollW * 0.40);

  // 4 Corner Arabesque Flairs
  const cornerFlairs = [
    [-scrollW / 2 + 20, 20],
    [scrollW / 2 - 20, 20],
    [-scrollW / 2 + 20, scrollH - 20],
    [scrollW / 2 - 20, scrollH - 20]
  ];
  cornerFlairs.forEach(([cx, cy]) => {
    scrollG.append('circle').attr('cx', cx).attr('cy', cy).attr('r', 3.8).attr('fill', '#d97706');
    scrollG.append('circle').attr('cx', cx).attr('cy', cy).attr('r', 7.5).attr('fill', 'none').attr('stroke', '#b45309').attr('stroke-width', 0.9).attr('opacity', 0.65);
  });

  // Expand treeBounds so fitToScreen, export, and A3 printing frame the scroll completely
  treeBounds.minX = Math.min(treeBounds.minX, rx - scrollW / 2 - 50);
  treeBounds.maxX = Math.max(treeBounds.maxX, rx + scrollW / 2 + 50);
  treeBounds.maxY = Math.max(treeBounds.maxY, scrollY + scrollH + 80);
}

function fitToScreen(immediate = false) {
  if (!treeDataRoot) return;

  const vpW = window.innerWidth;
  const vpH = window.innerHeight;

  const treeW = Math.max(100, treeBounds.maxX - treeBounds.minX);
  const treeH = Math.max(100, treeBounds.maxY - treeBounds.minY);

  const padX = 140;
  const padY = 195;

  const scale = Math.min((vpW - padX) / treeW, (vpH - padY) / treeH, 1.25);
  const midX  = (treeBounds.minX + treeBounds.maxX) / 2;
  const midY  = (treeBounds.minY + treeBounds.maxY) / 2;

  const tx = vpW / 2 - midX * scale;
  const ty = vpH / 2 - (midY - 25) * scale;

  if (immediate) {
    svg.call(zoom.transform, d3.zoomIdentity.translate(tx, ty).scale(scale));
  } else {
    svg.transition().duration(850).ease(d3.easeCubicOut)
      .call(zoom.transform, d3.zoomIdentity.translate(tx, ty).scale(scale));
  }
}

// ═══════════════════════════════════════════════════════
//  A3 DUAL-MODE PRINT ENGINE (Portrait or Landscape)
// ═══════════════════════════════════════════════════════
let originalSvgViewBox    = null;
let originalSvgWidth      = null;
let originalSvgHeight     = null;
let originalMainTransform = null;
let activePrintMode       = 'portrait';

function prepareSvgForPrint(mode = 'portrait') {
  activePrintMode = mode;
  const svgEl = document.getElementById('tree-svg');
  originalSvgViewBox = svgEl.getAttribute('viewBox');
  originalSvgWidth   = svgEl.getAttribute('width');
  originalSvgHeight  = svgEl.getAttribute('height');
  
  const gMainEl = document.getElementById('g-main');
  originalMainTransform = gMainEl.getAttribute('transform');

  // Strip temporary screen zoom transform so geometry is pure
  gMainEl.setAttribute('transform', '');

  // Dynamic @page orientation rule
  let printStyle = document.getElementById('print-page-style');
  if (!printStyle) {
    printStyle = document.createElement('style');
    printStyle.id = 'print-page-style';
    document.head.appendChild(printStyle);
  }

  // Aspect ratio:
  // A3 Portrait: 297mm / 420mm ≈ 0.7071
  // A3 Landscape: 420mm / 297mm ≈ 1.4142
  let targetRatio;
  if (mode === 'landscape') {
    printStyle.textContent = '@page { size: A3 landscape; margin: 0; }';
    document.body.classList.add('print-landscape');
    document.body.classList.remove('print-portrait');
    targetRatio = 420 / 297;
  } else {
    printStyle.textContent = '@page { size: A3 portrait; margin: 0; }';
    document.body.classList.add('print-portrait');
    document.body.classList.remove('print-landscape');
    targetRatio = 297 / 420;
  }

  const pad = 65; // Balanced framing margin for poster
  let treeW = (treeBounds.maxX - treeBounds.minX) + pad * 2;
  let treeH = (treeBounds.maxY - treeBounds.minY) + pad * 2;
  const centerX = (treeBounds.minX + treeBounds.maxX) / 2;
  const centerY = (treeBounds.minY + treeBounds.maxY) / 2;

  if (treeW / treeH > targetRatio) {
    treeH = treeW / targetRatio;
  } else {
    treeW = treeH * targetRatio;
  }

  const vx = centerX - treeW / 2;
  const vy = centerY - treeH / 2;

  svgEl.setAttribute('viewBox', `${vx} ${vy} ${treeW} ${treeH}`);
}

function restoreSvgAfterPrint() {
  const svgEl = document.getElementById('tree-svg');
  if (originalSvgViewBox !== null) {
    svgEl.setAttribute('viewBox', originalSvgViewBox);
  } else {
    svgEl.removeAttribute('viewBox');
  }
  if (originalSvgWidth !== null) svgEl.setAttribute('width', originalSvgWidth);
  else svgEl.removeAttribute('width');

  if (originalSvgHeight !== null) svgEl.setAttribute('height', originalSvgHeight);
  else svgEl.removeAttribute('height');

  const gMainEl = document.getElementById('g-main');
  if (originalMainTransform !== null) {
    gMainEl.setAttribute('transform', originalMainTransform);
  }
  document.body.classList.remove('print-portrait', 'print-landscape');
}

window.addEventListener('beforeprint', () => prepareSvgForPrint(activePrintMode));
window.addEventListener('afterprint', restoreSvgAfterPrint);

function triggerA3Print(mode = 'portrait') {
  const prDrop = document.getElementById('prDrop');
  if (prDrop) prDrop.style.display = 'none';
  prepareSvgForPrint(mode);
  setTimeout(() => {
    window.print();
    setTimeout(restoreSvgAfterPrint, 1000);
  }, 100);
}

// Zoom directly to a specific person's medallion with golden beacon highlight
function focusNodeById(id) {
  const target = allNodesList.find(n => String(n.id) === String(id));
  if (!target) return;

  const vpW = window.innerWidth;
  const vpH = window.innerHeight;
  const targetScale = 1.35;

  const tx = vpW / 2 - target.x * targetScale;
  const ty = vpH / 2 - target.y * targetScale;

  svg.transition().duration(900).ease(d3.easeCubicOut)
    .call(zoom.transform, d3.zoomIdentity.translate(tx, ty).scale(targetScale));

  // Pulse highlight
  const el = d3.select(`#node-${target.id}`);
  if (!el.empty()) {
    el.select('.node-halo')
      .transition().duration(250).attr('opacity', 1).attr('stroke-width', 5)
      .transition().duration(1200).attr('opacity', 0).attr('stroke-width', 2.5);
  }
}

// ═══════════════════════════════════════════════════════
//  ZOOM CONTROLS & EXPORT (PNG / SVG)
// ═══════════════════════════════════════════════════════
document.getElementById('zi').onclick = () => svg.transition().duration(300).call(zoom.scaleBy, 1.3);
document.getElementById('zo').onclick = () => svg.transition().duration(300).call(zoom.scaleBy, 0.77);
document.getElementById('zf').onclick = fitToScreen;

function updateStatsBadge() {
  const elTot = document.getElementById('statTot');
  const elMales = document.getElementById('statMales');
  const elFem = document.getElementById('statFem');
  const elGen = document.getElementById('statGen');

  if (onlySonsMode) {
    const sonsCount = (RAW_TREE.children || []).length;
    if (elTot) elTot.innerHTML = `🌿 تەنها کوڕەکان: <strong>${toKurdishNumber(sonsCount)} کەس</strong>`;
    if (elMales) elMales.innerHTML = `👦 کوڕ: <strong>${toKurdishNumber(sonsCount)}</strong>`;
    if (elFem) elFem.innerHTML = `👧 کچ: <strong>٠</strong>`;
    if (elGen) elGen.innerHTML = `👑 پشتەکان: <strong>١ پشت</strong>`;
  } else {
    if (elTot) elTot.innerHTML = `🌿 کۆی نەوەکان: <strong>${toKurdishNumber(originalStats.tot)} کەس</strong>`;
    if (elMales) elMales.innerHTML = `👦 کوڕ: <strong>${toKurdishNumber(originalStats.males)}</strong>`;
    if (elFem) elFem.innerHTML = `👧 کچ: <strong>${toKurdishNumber(originalStats.fem)}</strong>`;
    if (elGen) elGen.innerHTML = `👑 پشتەکان: <strong>${toKurdishNumber(originalStats.gen)} پشت</strong>`;
  }
}

const chkOnlySons = document.getElementById('chkOnlySons');
const lblOnlySons = document.getElementById('lblOnlySons');
if (chkOnlySons) {
  chkOnlySons.addEventListener('change', () => {
    onlySonsMode = chkOnlySons.checked;
    if (onlySonsMode) {
      lblOnlySons.classList.add('active');
    } else {
      lblOnlySons.classList.remove('active');
    }
    renderArtisticBotanicalTree();
    updateStatsBadge();
    setTimeout(fitToScreen, 120);
  });
}
document.getElementById('fitBtn').onclick = fitToScreen;

// Print Dropdown Toggle
const prBtn  = document.getElementById('prBtn');
const prDrop = document.getElementById('prDrop');
if (prBtn && prDrop) {
  prBtn.addEventListener('click', e => {
    e.stopPropagation();
    if (dlDrop) dlDrop.style.display = 'none';
    prDrop.style.display = prDrop.style.display === 'block' ? 'none' : 'block';
  });
}

// Download Dropdown Toggle
const dlBtn  = document.getElementById('dlBtn');
const dlDrop = document.getElementById('dlDrop');
if (dlBtn && dlDrop) {
  dlBtn.addEventListener('click', e => {
    e.stopPropagation();
    if (prDrop) prDrop.style.display = 'none';
    dlDrop.style.display = dlDrop.style.display === 'block' ? 'none' : 'block';
  });
}
document.addEventListener('click', () => { 
  if (prDrop) prDrop.style.display = 'none'; 
  if (dlDrop) dlDrop.style.display = 'none'; 
});

// 1. Export as Infinite-Resolution Vector SVG
document.getElementById('dlSvg').onclick = () => {
  dlDrop.style.display = 'none';
  const svgEl = document.getElementById('tree-svg');
  const clone = svgEl.cloneNode(true);
  
  // Clean zoom transform for clean export
  clone.querySelector('#g-main').setAttribute('transform', '');
  const treeW = treeBounds.maxX - treeBounds.minX + 200;
  const treeH = treeBounds.maxY - treeBounds.minY + 200;
  clone.setAttribute('viewBox', `${treeBounds.minX - 100} ${treeBounds.minY - 100} ${treeW} ${treeH}`);
  clone.setAttribute('width', treeW);
  clone.setAttribute('height', treeH);

  const serializer = new XMLSerializer();
  const svgString = '<?xml version="1.0" standalone="no"?>\r\n' + serializer.serializeToString(clone);
  const blob = new Blob([svgString], { type: 'image/svg+xml;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `داری_نەوەکانی_${ROOT_INFO.full_name.replace(/\s+/g,'_')}.svg`;
  document.body.appendChild(a); a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
};

// 2. Export as High-Resolution 300-DPI Poster PNG
document.getElementById('dlPng').onclick = () => {
  dlDrop.style.display = 'none';
  dlBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> داگرتن...';
  dlBtn.style.pointerEvents = 'none';

  const svgEl = document.getElementById('tree-svg');
  const clone = svgEl.cloneNode(true);
  clone.querySelector('#g-main').setAttribute('transform', '');

  const treeW = treeBounds.maxX - treeBounds.minX + 240;
  const treeH = treeBounds.maxY - treeBounds.minY + 240;
  clone.setAttribute('viewBox', `${treeBounds.minX - 120} ${treeBounds.minY - 120} ${treeW} ${treeH}`);
  clone.setAttribute('width', treeW);
  clone.setAttribute('height', treeH);

  const serializer = new XMLSerializer();
  const svgString = '<?xml version="1.0" standalone="no"?>\r\n' + serializer.serializeToString(clone);
  const svgBlob = new Blob([svgString], { type: 'image/svg+xml;charset=utf-8' });
  const url = URL.createObjectURL(svgBlob);

  const img = new Image();
  img.onload = () => {
    // 2.5x Hi-Res Canvas
    const scaleFactor = 2.2;
    const canvas = document.createElement('canvas');
    canvas.width  = treeW * scaleFactor;
    canvas.height = treeH * scaleFactor;
    const ctx = canvas.getContext('2d');

    // Luxurious Antique Background
    const bgGrad = ctx.createRadialGradient(
      canvas.width / 2, canvas.height * 0.4, 100,
      canvas.width / 2, canvas.height * 0.5, canvas.width * 0.7
    );
    bgGrad.addColorStop(0, '#fffdf9');
    bgGrad.addColorStop(0.7, '#f7f1e4');
    bgGrad.addColorStop(1, '#ebe0cc');
    ctx.fillStyle = bgGrad;
    ctx.fillRect(0, 0, canvas.width, canvas.height);

    // Draw SVG Tree
    ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

    canvas.toBlob(pngBlob => {
      const pngUrl = URL.createObjectURL(pngBlob);
      const a = document.createElement('a');
      a.href = pngUrl;
      a.download = `داری_نەوەکانی_${ROOT_INFO.full_name.replace(/\s+/g,'_')}.png`;
      document.body.appendChild(a); a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(pngUrl);
      URL.revokeObjectURL(url);
      dlBtn.innerHTML = '<i class="fas fa-check"></i> داگیرا!';
      setTimeout(() => { 
        dlBtn.innerHTML = '<i class="fas fa-download"></i> داگرتن <i class="fas fa-chevron-down" style="font-size:.7rem"></i>'; 
        dlBtn.style.pointerEvents = 'auto'; 
      }, 2000);
    }, 'image/png');
  };
  img.src = url;
};

// ═══════════════════════════════════════════════════════
//  MODAL DIALOG DETAILS
// ═══════════════════════════════════════════════════════
function openModal(node) {
  document.getElementById('mname').innerText = node.name || node.full_name || node.short;
  document.getElementById('mcode').innerText = `کۆدی ئەندام: ${node.code || node.member_code || node.id}`;
  document.getElementById('mst').innerText   = node.status === 'active' ? '🟢 لە ژیاندا ماوە' : '🕊️ کۆچکردوو';
  document.getElementById('mby').innerText   = node.birth ? `${node.birth} کۆچی` : 'تۆمار نەکراوە';
  document.getElementById('mdp').innerText   = `پشتی ${node.depth}`;
  
  const chCount = (node.children || []).length;
  document.getElementById('mch').innerText   = `${chCount} منداڵ`;
  document.getElementById('mta').href        = `descendants_tree.php?id=${node.id}`;
  document.getElementById('mpa').href        = `kinship_search.php?id=${node.id}`;
  document.getElementById('modal').style.display = 'flex';
}
function closeModal() { document.getElementById('modal').style.display = 'none'; }
document.getElementById('modal').onclick = e => { if (e.target.id === 'modal') closeModal(); };

// ═══════════════════════════════════════════════════════
//  AUTOCOMPLETE SEARCH
// ═══════════════════════════════════════════════════════
const si = document.getElementById('si');
const sd = document.getElementById('sd');
let searchTimer;
si.addEventListener('input', () => {
  clearTimeout(searchTimer);
  const v = si.value.trim();
  if (v.length < 1) { sd.style.display = 'none'; return; }
  searchTimer = setTimeout(async () => {
    try {
      const res = await fetch(`descendants_tree.php?action=search_ajax&q=${encodeURIComponent(v)}`);
      const d = await res.json();
      sd.innerHTML = '';
      if (!d.length) {
        sd.innerHTML = '<div style="padding:10px;color:#94a3b8;font-size:.8rem;text-align:center">هیچ کەسێک نەدۆزرایەوە</div>';
      } else {
        d.forEach(m => {
          const a = document.createElement('a');
          a.href = `descendants_tree.php?id=${m.id}`;
          a.innerHTML = `<div><div style="font-weight:700">${m.full_name}</div><div style="font-size:.72rem;color:#78350f">کۆد: ${m.member_code || m.id}</div></div><span style="font-size:.7rem;padding:2px 8px;border-radius:10px;background:#fef3c7;color:#92400e;font-weight:700">داری نەوەکان</span>`;
          sd.appendChild(a);
        });
      }
      sd.style.display = 'block';
    } catch(err) { console.error(err); }
  }, 250);
});
document.addEventListener('click', e => {
  if (!si.contains(e.target) && !sd.contains(e.target)) sd.style.display = 'none';
});

// ═══════════════════════════════════════════════════════
//  INITIALIZATION
// ═══════════════════════════════════════════════════════
window.addEventListener('resize', fitToScreen);

document.fonts.ready.then(() => {
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get('sons') === '1' || window.location.hash === '#sons') {
    chkOnlySons.checked = true;
    onlySonsMode = true;
    lblOnlySons.classList.add('active');
    updateStatsBadge();
  }
  renderArtisticBotanicalTree();
  if (window.location.hash === '#print') {
    prepareSvgForPrint('portrait');
  } else if (window.location.hash === '#lineage') {
    const rx = treeDataRoot.x;
    const ry = treeDataRoot.y;
    const targetScale = 1.0;
    const tx = window.innerWidth / 2 - rx * targetScale;
    const ty = window.innerHeight / 2 - (ry + 450) * targetScale;
    svg.call(zoom.transform, d3.zoomIdentity.translate(tx, ty).scale(targetScale));
  } else {
    fitToScreen(true);
    setTimeout(() => fitToScreen(false), 200);
  }
});
</script>
</body>
</html>
