<?php
$sys_module = 'lineage';
$active_nav = 'lineage_reports';
$page_title = 'ڕاپۆرت و چاپکردنی فەرمی نەسەب';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
require_once 'lineage_core.php';
$conn = getDB();

// Fetch Branding Settings
$branding = [
    'system_name' => 'مەڵبەندی ڕۆشنبیری و کۆمەڵایەتی',
    'system_subtitle' => 'ساداتی پیرخدری شاهۆ',
    'logo' => 'assets/logo.ico'
];
$bRes = $conn->query("SELECT * FROM settings LIMIT 1");
if ($bRes && $bRow = $bRes->fetch_assoc()) {
    $branding = array_merge($branding, $bRow);
}

// -------------------------------------------------------------
// HELPER: Trace Lineage Chain to Pirkhidri Shaho
// -------------------------------------------------------------
if (!function_exists('getAncestorsChain')) {
    function getAncestorsChain($conn, $startParentId) {
        $currId = (int)$startParentId;
        $chain = [];
        $visited = [];
        while ($currId > 0 && !isset($visited[$currId])) {
            $visited[$currId] = true;
            $stmt = $conn->prepare("SELECT id, full_name, parent_id, member_code FROM members WHERE id = ?");
            $stmt->bind_param("i", $currId);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $chain[] = $row;
                if ($row['id'] == 1 || isShahoName($row['full_name'])) {
                    break;
                }
                $currId = (int)$row['parent_id'];
            } else {
                break;
            }
        }
        return $chain;
    }
}

// -------------------------------------------------------------
// -------------------------------------------------------------
// DEDICATED PRINT VIEWS: 1. Committee Layout  2. Luxury Certificate
// -------------------------------------------------------------
$certMemberId = isset($_GET['cert_id']) ? (int)$_GET['cert_id'] : 0;
if ($certMemberId > 0) {
    if (!function_exists('formatArabicName')) {
        function formatArabicName($name) {
            $name = trim($name);
            $name = preg_replace('/\s*\(قدس سره\)/u', '', $name);
            if (preg_match('/^(سید|سەید|السيد|السید)\s+/u', $name)) {
                $name = preg_replace('/^(سید|سەید|السيد|السید)\s+/u', 'السيد ', $name);
            } else {
                $name = 'السيد ' . $name;
            }
            return trim($name);
        }
    }

    if (!function_exists('getCleanFatherTitle')) {
        function getCleanFatherTitle($fullName) {
            $name = trim(preg_replace('/\s*\(قدس سره\)/u', '', $fullName));
            $name = preg_replace('/^(السيد|السید|سید|سەید)\s+/u', '', $name);
            $name = preg_replace('/^(ملا|الشيخ|شيخ)\s+/u', '', $name);
            return 'السيد ' . trim($name);
        }
    }

    $cStmt = $conn->prepare("
        SELECT m.*, f.family_name, 
               fat.full_name as father_name, 
               gfat.full_name as grandfather_name 
        FROM members m 
        LEFT JOIN families f ON m.family_id = f.id 
        LEFT JOIN members fat ON m.parent_id = fat.id 
        LEFT JOIN members gfat ON fat.parent_id = gfat.id 
        WHERE m.id = ?
    ");
    $cStmt->bind_param("i", $certMemberId);
    $cStmt->execute();
    $member = $cStmt->get_result()->fetch_assoc();
    $cStmt->close();

    if ($member) {
        $certType = isset($_GET['type']) && $_GET['type'] === 'certificate' ? 'certificate' : 'committee';
        $includeBrothers = isset($_GET['include_brothers']) ? (bool)$_GET['include_brothers'] : false;
        $parentId = (int)$member['parent_id'];

        // Get ancestors list
        $ancestorsObjects = getAncestorsChain($conn, $member['parent_id']);
        $genCount = count($ancestorsObjects);
        $reachShaho = false;
        if ($genCount > 0) {
            $lastAnc = end($ancestorsObjects);
            if ($lastAnc['id'] == 1 || isShahoName($lastAnc['full_name'])) {
                $reachShaho = true;
            }
        }

        // Fetch Director from Settings -> Positions (staff_units)
        $directorName = '';
        $dirRes = $conn->query("
            SELECT m.full_name 
            FROM staff_units u 
            JOIN members m ON u.manager_id = m.id 
            WHERE u.id = 1 OR u.unit_name LIKE '%بەڕێوەبەری مەڵبەند%' OR u.unit_name LIKE '%کارگێڕی%'
            LIMIT 1
        ");
        if ($dirRes && $dirRow = $dirRes->fetch_assoc()) {
            $directorName = trim($dirRow['full_name']);
        }
        if (empty($directorName)) {
            $directorName = 'مظفر سید حسن البیرخضري';
        }

        // --------------------------------------------------
        // SHARED PREPARATION FOR COMMITTEE VIEW
        // --------------------------------------------------
        $sonsText = "";
        if ($includeBrothers && $parentId > 0) {
            $bStmt = $conn->prepare("SELECT full_name, gender FROM members WHERE parent_id = ? ORDER BY id ASC");
            $bStmt->bind_param("i", $parentId);
            $bStmt->execute();
            $bRes = $bStmt->get_result();
            $brothers = [];
            while ($bRow = $bRes->fetch_assoc()) {
                if (isset($bRow['gender']) && $bRow['gender'] === 'female') continue;
                $brothers[] = $bRow;
            }
            $bStmt->close();
            if (count($brothers) > 1) {
                $names = [];
                foreach ($brothers as $b) {
                    $names[] = formatArabicName($b['full_name']);
                }
                $sonsText = implode(' و ', $names) . ' ابناء ';
            }
        }
        if (empty($sonsText)) {
            $sonsText = formatArabicName($member['full_name']) . ' بن ';
        }

        // Intermediate ancestors text
        $ancestorNames = [];
        foreach ($ancestorsObjects as $anc) {
            if ($anc['id'] == 1 || isShahoName($anc['full_name'])) break;
            $ancestorNames[] = formatArabicName($anc['full_name']);
        }
        $intermediateText = !empty($ancestorNames) ? implode(' بن ', $ancestorNames) . ' بن ' : '';

        $shahoGreenHtml = 'السيدمحمد الزاهد الملقب بـ <span style="color: #008520; font-weight: bold;">( بيرخضرـ شاهو )</span>';

        $shahoPropheticTail = "بن السيد محمود المدني بن السيد جعفر بن السيد حسين بن السيد محمود الينبوعي العاصم بن السيد رحمة الله المعروف بالعارف بالله بن السيد موسى الداعي الى الله بن السيد حسين الصامت بن السيد هلال بن السيد محمد الملقب بالخلف بن السيد منهال بن السيد ماجد بن السيد عبدالرحمن بن السيد قاسم بن السيد ادريس ابن السلطان جعفر بن السيد الامام علي النقي بن السيد الامام محمد الجواد التقي بن السيد الامام علي الرضا بن السيد الامام موسى الكاظم بن السيد الامام جعفر الصادق بن السيد الامام محمد الباقر بن السيد الامام زين العابدين بن السيد الامام حسين الشهيد بن الامام علي بن ابي طالب زوج فاطمة الزهراء بنت محمد رسول الله ابن عبدالله . صلى الله عليه و سلم .";

        $fullLineageHtml = '<span style="color: #e00000; font-weight: bold;">' . $sonsText . '</span>'
                         . $intermediateText
                         . $shahoGreenHtml . ' '
                         . $shahoPropheticTail;

        // Family Name Clean
        $familyRaw = isset($_GET['family_name']) && trim($_GET['family_name']) !== '' 
            ? trim($_GET['family_name']) 
            : (!empty($member['family_name']) ? $member['family_name'] : (!empty($member['bnamala']) ? $member['bnamala'] : 'البيرخضرية'));
        $cleanFamilyName = trim(preg_replace('/^(ساداتی|سادات|سادتی)\s+/u', '', $familyRaw));

        // Dates
        $hijriYearMonthDay = "1445/4/5";
        $gregYearMonthDay = "2023/11/18";
        if (class_exists('IntlDateFormatter')) {
            try {
                $fmt = new IntlDateFormatter('en_US@calendar=islamic-umalqura', IntlDateFormatter::FULL, IntlDateFormatter::NONE, 'Asia/Baghdad', IntlDateFormatter::TRADITIONAL, 'yyyy/M/d');
                $hijriYearMonthDay = $fmt->format(time());
                $gregYearMonthDay = date('Y/m/d');
            } catch (Exception $e) {}
        }
        $docHijri = isset($_GET['hijri']) ? trim($_GET['hijri']) : $hijriYearMonthDay;
        $docGreg = isset($_GET['greg']) ? trim($_GET['greg']) : $gregYearMonthDay;
        ?>
        <!DOCTYPE html>
        <html lang="ku" dir="rtl">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo $certType === 'certificate' ? 'شەهادەی فەرمی نەسەب' : 'کڵێشەی فەرمی شەجەرە'; ?> - <?php echo htmlspecialchars($member['full_name']); ?></title>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Amiri:ital,wght@0,400;0,700;1,400&family=Noto+Naskh+Arabic:wght@400;600;700&display=swap" rel="stylesheet">
            <style>
                @font-face {
                    font-family: 'Rabar013';
                    src: url('assets/font/Rabar_013.ttf') format('truetype');
                    font-weight: normal;
                    font-style: normal;
                }
                @font-face {
                    font-family: 'Rabar013';
                    src: url('assets/font/Rabar_013.ttf') format('truetype');
                    font-weight: bold;
                    font-style: normal;
                }
                @font-face {
                    font-family: 'Rabar005';
                    src: url('assets/font/Rabar_005.ttf') format('truetype');
                    font-weight: normal;
                    font-style: normal;
                }
                @font-face {
                    font-family: 'Rabar005';
                    src: url('assets/font/Rabar_005.ttf') format('truetype');
                    font-weight: bold;
                    font-style: normal;
                }
                @font-face {
                    font-family: 'Rabar022';
                    src: url('assets/font/Rabar_022.ttf') format('truetype');
                }
                * {
                    box-sizing: border-box;
                    margin: 0;
                    padding: 0;
                }
                body {
                    background-color: #525659;
                    font-family: 'Rabar005', 'Rabar022', Tahoma, Arial, sans-serif;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    padding: 20px 0;
                    color: #000;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }

                /* Control Toolbar */
                .no-print.cert-toolbar {
                    width: 210mm;
                    background: #ffffff;
                    padding: 10px 18px;
                    border-radius: 12px;
                    margin-bottom: 16px;
                    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
                    display: flex;
                    flex-wrap: wrap;
                    align-items: center;
                    justify-content: space-between;
                    gap: 12px;
                    font-family: 'Rabar013', 'Rabar022', sans-serif;
                }
                .toolbar-btn {
                    padding: 8px 16px;
                    border-radius: 8px;
                    font-weight: bold;
                    font-size: 0.9rem;
                    cursor: pointer;
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                    border: none;
                    text-decoration: none;
                    transition: 0.2s;
                    font-family: 'Rabar013', 'Rabar022', sans-serif;
                }
                .btn-print-green { background: #0b5e28; color: #fff; }
                .btn-print-green:hover { background: #08491f; }
                .btn-print-maroon { background: #720000; color: #fff; }
                .btn-print-maroon:hover { background: #550000; }
                .btn-back { background: #f1f5f9; color: #334155; }
                .btn-back:hover { background: #e2e8f0; }

                /* View Switcher */
                .view-switcher {
                    display: flex;
                    background: #f1f5f9;
                    border-radius: 10px;
                    padding: 3px;
                    gap: 4px;
                    border: 1px solid #e2e8f0;
                }
                .switch-tab {
                    padding: 6px 14px;
                    border-radius: 8px;
                    font-size: 0.88rem;
                    font-weight: bold;
                    text-decoration: none;
                    color: #64748b;
                    transition: all 0.2s;
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    font-family: 'Rabar013', 'Rabar022', sans-serif;
                }
                .switch-tab:hover {
                    color: #1e293b;
                }
                .switch-tab.active-committee {
                    background: #0b5e28;
                    color: #fff;
                    box-shadow: 0 2px 6px rgba(11, 94, 40, 0.25);
                }
                .switch-tab.active-cert {
                    background: #720000;
                    color: #fff;
                    box-shadow: 0 2px 6px rgba(114, 0, 0, 0.25);
                }

                .toolbar-options {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    font-size: 0.88rem;
                    font-family: 'Rabar013', 'Rabar022', sans-serif;
                }
                .toggle-control {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    cursor: pointer;
                    background: #f8fafc;
                    padding: 6px 12px;
                    border-radius: 8px;
                    border: 1px solid #cbd5e1;
                    user-select: none;
                }
                .toggle-control input {
                    cursor: pointer;
                    width: 16px;
                    height: 16px;
                }

                /* ============================================================== */
                /* 1. COMMITTEE VIEW STYLES                                       */
                /* ============================================================== */
                .sheet-wrapper {
                    width: 210mm;
                    height: 297mm;
                    background: #ffffff;
                    padding: 5mm 6mm;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                    box-sizing: border-box;
                    display: flex;
                    flex-direction: column;
                }
                .document-frame {
                    border: 1.5px solid #000000;
                    padding: 10px 14px 8px 14px;
                    height: 100%;
                    box-sizing: border-box;
                    display: flex;
                    flex-direction: column;
                    justify-content: space-between;
                }
                .header-top-bismillah {
                    text-align: center;
                    font-size: 13.5px;
                    font-weight: bold;
                    color: #333;
                    margin-bottom: 2px;
                    letter-spacing: 0.2px;
                    font-family: 'Rabar013', 'Rabar022', Tahoma, sans-serif;
                }
                .header-grid {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    margin-bottom: 4px;
                }
                .header-kurdish, .header-arabic {
                    text-align: center;
                    font-weight: bold;
                    font-size: 14.5px;
                    line-height: 1.45;
                    color: #111;
                    width: 38%;
                    font-family: 'Rabar013', 'Rabar022', Tahoma, sans-serif;
                }
                .header-logo-box { width: 24%; text-align: center; }
                .header-logo { width: 74px; height: 74px; object-fit: contain; }
                .header-divider {
                    height: 4px;
                    background: linear-gradient(to bottom, #83976f 0%, #d4e0ca 50%, #9cb088 100%);
                    border-top: 1px solid #5a6e46;
                    border-bottom: 1px solid #5a6e46;
                    margin: 4px 0 8px 0;
                }
                .bismillah-title {
                    text-align: center;
                    font-size: 21px;
                    font-weight: bold;
                    color: #1b73b5;
                    margin-bottom: 3px;
                    letter-spacing: 0.5px;
                    font-family: 'Rabar013', 'Rabar022', Tahoma, serif;
                }
                .ayah-title {
                    text-align: center;
                    font-size: 16.5px;
                    font-weight: bold;
                    color: #111;
                    margin-bottom: 8px;
                    line-height: 1.45;
                    font-family: 'Rabar013', 'Rabar022', Tahoma, serif;
                }
                .ayah-ref {
                    color: #e00000;
                    font-weight: bold;
                    margin-right: 5px;
                    font-size: 15.5px;
                    font-family: 'Rabar013', 'Rabar022', Tahoma, sans-serif;
                }
                .badge-box { text-align: center; margin-bottom: 10px; }
                .green-title-badge {
                    background-color: #0b5e28;
                    color: #ffffff;
                    font-size: 22px;
                    font-weight: bold;
                    padding: 3px 24px;
                    display: inline-block;
                    letter-spacing: 0.3px;
                    font-family: 'Rabar013', 'Rabar022', Tahoma, sans-serif;
                }
                .green-title-badge u {
                    text-decoration: underline;
                    text-underline-offset: 4px;
                }
                .badge-underline {
                    width: 130px;
                    height: 2px;
                    background: #000;
                    margin: 5px auto 0 auto;
                }
                .intro-statement {
                    text-align: justify;
                    text-justify: inter-word;
                    font-size: 16.5px;
                    font-weight: bold;
                    line-height: 1.85;
                    color: #000;
                    margin-bottom: 14px;
                }
                .lineage-chain-box {
                    text-align: justify;
                    text-justify: inter-word;
                    font-size: 18px;
                    font-weight: bold;
                    line-height: 2.35;
                    color: #000;
                    margin-bottom: 14px;
                    flex-grow: 1;
                }
                .date-line {
                    font-size: 15px;
                    font-weight: bold;
                    text-align: right;
                    color: #000;
                    margin-bottom: 8px;
                    font-family: 'Rabar013', 'Rabar022', Tahoma, sans-serif;
                }
                .committee-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 14px;
                    border: 1.5px solid #000;
                }
                .committee-table th {
                    width: 33.333%;
                    background-color: #e2ebd9;
                    border: 1.5px solid #000;
                    padding: 4px;
                    font-size: 14.5px;
                    font-weight: bold;
                    color: #000;
                    text-align: center;
                    font-family: 'Rabar013', 'Rabar022', Tahoma, sans-serif;
                }
                .committee-table td {
                    border: 1.5px solid #000;
                    padding: 8px 4px;
                    font-size: 14.5px;
                    font-weight: bold;
                    line-height: 1.35;
                    color: #000;
                    text-align: center;
                    font-family: 'Rabar005', 'Rabar022', Tahoma, sans-serif;
                }
                .signatures-row {
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-end;
                    margin-bottom: 10px;
                    padding: 0 4px;
                }
                .endorsement-block {
                    font-size: 14px;
                    font-weight: bold;
                    color: #1b5e20;
                    line-height: 1.5;
                    text-align: right;
                    white-space: nowrap;
                    padding-bottom: 8px;
                    font-family: 'Rabar013', 'Rabar022', Tahoma, sans-serif;
                }
                .director-block {
                    text-align: center;
                    min-width: 220px;
                }
                .director-name {
                    font-size: 17px;
                    font-weight: bold;
                    color: #000;
                    margin-bottom: 4px;
                    font-family: 'Rabar013', 'Rabar022', Tahoma, sans-serif;
                }
                .director-divider {
                    width: 190px;
                    border-bottom: 1.5px solid #000;
                    margin: 0 auto 5px auto;
                }
                .director-role {
                    font-size: 14.5px;
                    font-weight: bold;
                    color: #000;
                    line-height: 1.35;
                    font-family: 'Rabar013', 'Rabar022', Tahoma, sans-serif;
                }
                .committee-footer {
                    background-color: #97a884;
                    color: #ffffff;
                    font-size: 14px;
                    font-weight: bold;
                    text-align: center;
                    padding: 5px 10px;
                    border-radius: 3px;
                    letter-spacing: 0.3px;
                    font-family: 'Rabar013', 'Rabar022', Tahoma, sans-serif;
                }

                /* ============================================================== */
                /* 2. LUXURY CERTIFICATE VIEW STYLES                              */
                /* ============================================================== */
                .certificate-sheet {
                    width: 210mm;
                    height: 297mm;
                    background: #ffffff;
                    padding: 5mm 6mm;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                    box-sizing: border-box;
                    display: flex;
                    flex-direction: column;
                }
                .cert-border-outer {
                    border: 3px solid #720000;
                    padding: 4px;
                    height: 100%;
                    box-sizing: border-box;
                }
                .cert-border-inner {
                    border: 1.5px solid #c5a059;
                    padding: 16px 20px 10px 20px;
                    position: relative;
                    height: 100%;
                    box-sizing: border-box;
                    display: flex;
                    flex-direction: column;
                    justify-content: space-between;
                }
                .cert-corner {
                    position: absolute;
                    width: 28px;
                    height: 28px;
                    border-color: #c5a059;
                    border-style: solid;
                }
                .c-top-left { top: -2px; left: -2px; border-width: 3px 0 0 3px; }
                .c-top-right { top: -2px; right: -2px; border-width: 3px 3px 0 0; }
                .c-bot-left { bottom: -2px; left: -2px; border-width: 0 0 3px 3px; }
                .c-bot-right { bottom: -2px; right: -2px; border-width: 0 3px 3px 0; }

                /* Global Titles Styling with Rabar_013 */
                h1, h2, h3, h4, h5, h6,
                .header-top-bismillah, .header-kurdish, .header-arabic, 
                .bismillah-title, .ayah-title, .ayah-ref, .green-title-badge, 
                .committee-table th, .director-name, .director-role, .endorsement-block,
                .committee-footer, .date-line,
                .cert-title, .cert-subtitle, .cert-bismillah, .cert-person-name,
                .cert-lineage-title, .sig-title, .sig-name, .sig-role-kurdish, .sig-role-line {
                    font-family: 'Rabar013', 'Rabar022', Tahoma, serif !important;
                }

                /* Global Content / Body Text Styling with Rabar_005 */
                .intro-statement,
                .lineage-chain-box,
                .committee-table td,
                .cert-intro-text,
                .cert-meta-strip,
                .cert-chain-flow,
                .chain-item,
                .chain-sep,
                .cert-body p {
                    font-family: 'Rabar005', 'Rabar022', Tahoma, sans-serif !important;
                }

                .cert-header { text-align: center; margin-bottom: 6px; }
                .cert-top-bar {
                    display: grid;
                    grid-template-columns: 1fr auto 1fr;
                    align-items: center;
                    margin-bottom: 6px;
                    border-bottom: 1.5px solid #faecc5;
                    padding-bottom: 6px;
                }
                .cert-org-name {
                    text-align: center;
                    line-height: 1.35;
                    justify-self: start;
                }
                .cert-org-main {
                    font-size: 1.18rem;
                    font-weight: bold;
                    color: #0f172a;
                    font-family: 'Rabar013', 'Rabar022', Tahoma, serif !important;
                }
                .cert-org-sub {
                    font-size: 1.08rem;
                    font-weight: bold;
                    color: #720000;
                    font-family: 'Rabar013', 'Rabar022', Tahoma, serif !important;
                }
                .cert-logo-wrap {
                    display: flex;
                    justify-content: center;
                    align-items: center;
                }
                .cert-logo-img {
                    height: 80px;
                    max-height: 82px;
                    object-fit: contain;
                    filter: drop-shadow(0 2px 4px rgba(0,0,0,0.08));
                }
                .cert-top-info {
                    text-align: left;
                    font-size: 0.88rem;
                    color: #475569;
                    line-height: 1.6;
                    font-family: 'Rabar005', 'Rabar022', Tahoma, sans-serif !important;
                }
                .cert-bismillah {
                    font-size: 1.35rem;
                    color: #720000;
                    margin: 2px 0 2px 0;
                    font-family: 'Rabar013', serif;
                }
                .cert-title {
                    font-size: 1.65rem;
                    color: #720000;
                    font-weight: bold;
                    margin: 0 0 2px 0;
                    letter-spacing: 0.5px;
                    font-family: 'Rabar013', 'Rabar022', Tahoma, serif;
                }
                .cert-subtitle {
                    font-size: 0.92rem;
                    color: #c5a059;
                    font-weight: bold;
                    font-family: 'Rabar013', 'Rabar022', Tahoma, serif;
                    margin-bottom: 4px;
                }
                .cert-body { text-align: center; margin: 6px 0; }
                .cert-intro-text {
                    font-size: 1.04rem;
                    line-height: 1.85;
                    color: #334155;
                    margin-bottom: 8px;
                }
                .cert-person-name {
                    font-size: 1.48rem;
                    font-weight: 800;
                    color: #720000;
                    background: #fdfbf7;
                    border: 1.5px dashed #c5a059;
                    padding: 6px 24px;
                    display: inline-block;
                    border-radius: 12px;
                    margin: 4px 0 10px;
                    font-family: 'Rabar013', 'Rabar022', Tahoma, serif;
                }
                .cert-meta-strip {
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    gap: 16px;
                    background: #fdfbf7;
                    border: 1px solid #faecc5;
                    padding: 8px 18px;
                    border-radius: 12px;
                    font-size: 0.95rem;
                    color: #334155;
                    margin-bottom: 14px;
                }
                .cert-meta-strip span {
                    display: inline-flex;
                    align-items: center;
                    gap: 4px;
                }
                .cert-meta-strip span:not(:last-child)::after {
                    content: "•";
                    margin-right: 14px;
                    color: #c5a059;
                    font-weight: bold;
                }
                .cert-lineage-title {
                    font-size: 1.08rem;
                    color: #720000;
                    font-weight: bold;
                    margin-bottom: 8px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 8px;
                    font-family: 'Rabar013', 'Rabar022', Tahoma, serif;
                }
                .cert-lineage-title::before,
                .cert-lineage-title::after {
                    content: "";
                    height: 1.5px;
                    width: 40px;
                    background: #c5a059;
                }
                .cert-chain-flow {
                    display: flex;
                    flex-wrap: wrap;
                    align-items: center;
                    justify-content: center;
                    gap: 6px;
                    line-height: 2.25;
                    background: #fafaf9;
                    border: 1px solid #f1f5f9;
                    border-radius: 12px;
                    padding: 12px 16px;
                    margin-bottom: 14px;
                }
                .chain-item {
                    font-weight: 700;
                    color: #1e293b;
                    font-size: 0.96rem;
                }
                .chain-item.shaho-target {
                    color: #ffffff !important;
                    background: #056a43 !important;
                    padding: 4px 14px;
                    border-radius: 8px;
                    border: 1px solid #045234;
                    font-size: 1.02rem;
                    font-family: 'Rabar013', 'Rabar022', Tahoma, serif !important;
                    font-weight: bold;
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    box-shadow: 0 2px 6px rgba(5, 106, 67, 0.25);
                }
                .chain-item.shaho-target i {
                    color: #ffffff !important;
                }
                .chain-sep {
                    color: #c5a059;
                    font-size: 0.85rem;
                    font-weight: bold;
                }

                .cert-footer {
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-start;
                    padding: 12px 20px 4px;
                    border-top: 1.5px solid #faecc5;
                }
                .sig-box {
                    text-align: center;
                    min-width: 220px;
                }
                .sig-name {
                    font-size: 1.15rem;
                    font-weight: bold;
                    color: #1e293b;
                    font-family: 'Rabar013', 'Rabar022', Tahoma, serif !important;
                    margin-bottom: 3px;
                }
                .sig-divider {
                    width: 140px;
                    border-bottom: 1.5px solid #c5a059;
                    margin: 0 auto 5px auto;
                }
                .sig-role-line {
                    font-size: 0.95rem;
                    font-weight: bold;
                    color: #720000;
                    line-height: 1.45;
                    font-family: 'Rabar013', 'Rabar022', Tahoma, serif !important;
                }
                .sig-role-kurdish {
                    font-size: 1.12rem;
                    font-weight: bold;
                    color: #1e293b;
                    font-family: 'Rabar013', 'Rabar022', Tahoma, serif !important;
                    margin-bottom: 3px;
                }
                .sig-role-arabic {
                    font-size: 1.02rem;
                    font-weight: bold;
                    color: #720000;
                    font-family: 'Amiri', 'Rabar013', serif !important;
                    line-height: 1.45;
                }
                .sig-line {
                    border-bottom: 1px dashed #94a3b8;
                    width: 180px;
                    margin: 25px auto 0 auto;
                }

                /* ============================================================== */
                /* PRINT OPTIMIZATION - STRICT 1 PAGE ONLY FOR BOTH               */
                /* ============================================================== */
                @page {
                    size: A4 portrait;
                    margin: 0;
                }
                @media print {
                    html, body {
                        width: 210mm !important;
                        height: 297mm !important;
                        margin: 0 !important;
                        padding: 0 !important;
                        background: #fff !important;
                        overflow: hidden !important;
                    }
                    .no-print {
                        display: none !important;
                    }
                    .sheet-wrapper, .certificate-sheet {
                        box-shadow: none !important;
                        border: none !important;
                        width: 210mm !important;
                        height: 297mm !important;
                        max-height: 297mm !important;
                        min-height: 297mm !important;
                        padding: 5mm 6mm !important;
                        margin: 0 !important;
                        box-sizing: border-box !important;
                        page-break-inside: avoid !important;
                        page-break-after: avoid !important;
                        page-break-before: avoid !important;
                    }
                    .document-frame {
                        height: calc(297mm - 10mm) !important;
                        max-height: calc(297mm - 10mm) !important;
                        min-height: calc(297mm - 10mm) !important;
                        padding: 8px 14px 6px 14px !important;
                        box-sizing: border-box !important;
                        page-break-inside: avoid !important;
                        page-break-after: avoid !important;
                        display: flex !important;
                        flex-direction: column !important;
                        justify-content: space-between !important;
                    }
                    .cert-border-outer { height: 100% !important; }
                    .cert-border-inner { height: 100% !important; }
                }
            </style>
        </head>
        <body>

        <!-- Interactive Control Bar (Hidden on Print) -->
        <div class="no-print cert-toolbar">
            <div style="display: flex; gap: 10px; align-items: center;">
                <a href="lineage_reports.php" class="toolbar-btn btn-back">
                    <i class="fas fa-arrow-right"></i> گەڕانەوە بۆ ڕاپۆرتەکان
                </a>
                <button onclick="window.print()" class="toolbar-btn <?php echo $certType === 'certificate' ? 'btn-print-maroon' : 'btn-print-green'; ?>">
                    <i class="fas fa-print"></i> چاپکردنی فەرمی (Print A4)
                </button>
            </div>

            <!-- View Switcher (1-Click Toggle) -->
            <div class="view-switcher">
                <a href="?cert_id=<?php echo $certMemberId; ?>&type=committee<?php echo $includeBrothers ? '&include_brothers=1' : ''; ?>" class="switch-tab <?php echo $certType === 'committee' ? 'active-committee' : ''; ?>">
                    <i class="fas fa-file-contract"></i> کڵێشەی فەرمی لیژنە
                </a>
                <a href="?cert_id=<?php echo $certMemberId; ?>&type=certificate" class="switch-tab <?php echo $certType === 'certificate' ? 'active-cert' : ''; ?>">
                    <i class="fas fa-certificate"></i> شەهادەی نەسەب
                </a>
            </div>

            <?php if ($certType === 'committee'): ?>
            <div class="toolbar-options">
                <label class="toggle-control" title="ئەگەر چالاک بێت هەموو براکانی کەسەکە وەک سەرەتای شەجەرە دادەنرێن">
                    <input type="checkbox" id="chkBrothers" <?php echo $includeBrothers ? 'checked' : ''; ?> onchange="reloadWithOptions()">
                    <span>پیشاندانی براکان لەگەڵ (ابناء)</span>
                </label>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($certType === 'certificate'): ?>
        <!-- ============================================================== -->
        <!-- VIEW 1: LUXURY CERTIFICATE OF LINEAGE                          -->
        <!-- ============================================================== -->
        <div class="certificate-sheet">
            <div class="cert-border-outer">
                <div class="cert-border-inner">
                    <div class="cert-corner c-top-left"></div>
                    <div class="cert-corner c-top-right"></div>
                    <div class="cert-corner c-bot-left"></div>
                    <div class="cert-corner c-bot-right"></div>

                    <!-- Header -->
                    <div class="cert-header">
                        <div class="cert-top-bar">
                            <div class="cert-org-name">
                                <div class="cert-org-main"><?php echo htmlspecialchars($branding['system_name']); ?></div>
                                <div class="cert-org-sub"><?php echo htmlspecialchars($branding['system_subtitle']); ?></div>
                            </div>
                            <div class="cert-logo-wrap">
                                <img src="<?php echo htmlspecialchars($branding['logo']); ?>" alt="Logo" class="cert-logo-img">
                            </div>
                            <div class="cert-top-info">
                                <div>کۆدی کەسی: <strong>#<?php echo htmlspecialchars($member['member_code'] ?: $member['id']); ?></strong></div>
                                <div>بەروار: <strong><?php echo date('Y/m/d'); ?></strong></div>
                            </div>
                        </div>

                        <!-- Bismillah positioned directly over Title -->
                        <div class="cert-bismillah">بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ</div>
                        <h1 class="cert-title">بڕوانامەی فەرمی نەسەب و شەجەرە</h1>
                        <div class="cert-subtitle">شجرة النَّسَب الشريف لسادات بيرخضر الشاهو</div>
                    </div>

                    <!-- Body Content -->
                    <div class="cert-body">
                        <p class="cert-intro-text">
                            مەڵبەندی ڕۆشنبیری و کۆمەڵایەتی ساداتی پیرخدری شاهۆ<br>
                            بەپێی بەڵگەنامە و دەستنووسە باوەڕپێکراوەکانی ئەنساب پشتڕاستی دەکاتەوە کە بەڕێز:
                        </p>

                        <?php 
                        $dispPerson = formatArabicName($member['full_name']);
                        $dispFather = !empty($member['father_name']) ? getCleanFatherTitle($member['father_name']) : '';
                        $dispBnamala = !empty($cleanFamilyName) && $cleanFamilyName !== 'البيرخضرية' 
                            ? $cleanFamilyName 
                            : (!empty($member['bnamala']) ? trim($member['bnamala']) : '');
                        $certDisplayName = trim($dispPerson . ' ' . $dispFather . ' ' . $dispBnamala);
                        ?>
                        <div class="cert-person-name">
                            <?php echo htmlspecialchars($certDisplayName); ?>
                        </div>

                        <?php $prophetGenCount = ($genCount > 0) ? ($genCount + 25) : 0; ?>
                        <div class="cert-meta-strip">
                            <span>بنەماڵە: <strong><?php echo htmlspecialchars($member['family_name'] ?: 'ساداتی پیرخدری شاهۆ'); ?></strong></span>
                            <?php if ($genCount > 0): ?>
                                <span>بە <strong><?php echo $genCount; ?></strong> پشت دەگاتەوە بە پیرخدری شاهۆ</span>
                                <span>بە <strong><?php echo $prophetGenCount; ?></strong> پشت دەگاتەوە بە پێغەمبەری خودا (ﷺ)</span>
                            <?php endif; ?>
                        </div>

                        <div class="cert-lineage-title">
                            <i class="fas fa-link"></i> زنجیرەی پیرۆزی ڕەچەڵەک بەرەو باوباپیران
                        </div>

                        <div class="cert-chain-flow">
                            <span class="chain-item" style="color: #720000; font-size: 1.05rem;"><?php echo htmlspecialchars($member['full_name']); ?></span>
                            <?php if(!empty($ancestorsObjects)): ?>
                                <?php foreach($ancestorsObjects as $idx => $anc): ?>
                                    <span class="chain-sep">بن</span>
                                    <?php 
                                    $isFinalShaho = ($anc['id'] == 1 || isShahoName($anc['full_name']));
                                    ?>
                                    <span class="chain-item <?php echo $isFinalShaho ? 'shaho-target' : ''; ?>">
                                        <?php if($isFinalShaho): ?><i class="fas fa-crown"></i> <?php endif; ?>
                                        <?php echo htmlspecialchars($anc['full_name']); ?>
                                    </span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="chain-sep">بن</span>
                                <span class="chain-item"><?php echo htmlspecialchars($member['father_name'] ?: 'نادیار'); ?></span>
                            <?php endif; ?>
                        </div>

                        <p style="font-size: 0.84rem; color: #64748b; line-height: 1.6; max-width: 580px; margin: 0 auto;">
                            ئەم بڕوانامەیە وەک بەڵگەنامەیەکی فەرمی ئەنساب بە شێوەی باوەڕپێکراو تۆمار کراوە و هەموو مافێکی پارێزراوە.
                        </p>
                    </div>

                    <!-- Signatures (Without Stamp) -->
                    <div class="cert-footer">
                        <!-- Right Signature: Lineage Committee -->
                        <div class="sig-box">
                            <div class="sig-role-kurdish">لیژنەی باڵای ڕاستاندنی ڕەچەڵەک</div>
                            <div class="sig-role-arabic">لجنة تصديق النسب</div>
                            <div class="sig-line" style="margin-top: 38px;"></div>
                        </div>

                        <!-- Left Signature: Director from Settings -> Positions -->
                        <div class="sig-box">
                            <div class="sig-name"><?php echo htmlspecialchars($directorName); ?></div>
                            <div class="sig-divider"></div>
                            <div class="sig-role-line">سەرۆکی دەستەی کارگێڕی</div>
                            <div class="sig-role-line">بەڕێوەبەری مەڵبەند</div>
                            <div class="sig-line"></div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <?php else: ?>
        <!-- ============================================================== -->
        <!-- VIEW 2: OFFICIAL COMMITTEE LINEAGE DOCUMENT (A4)               -->
        <!-- ============================================================== -->
        <div class="sheet-wrapper">
            <div class="document-frame">
                
                <!-- Top Section -->
                <div>
                    <!-- Bismillah Kurdish Header Top -->
                    <div class="header-top-bismillah">به ناوی خوای به خشنده و میهره بان</div>

                    <!-- 3-Column Header -->
                    <div class="header-grid">
                        <div class="header-arabic">
                            <div>المركز الثقافي و الاجتماعي</div>
                            <div>للسادة البيرخضرية الشاهوئية</div>
                            <div>قسم الأنساب</div>
                        </div>
                        <div class="header-logo-box">
                            <img src="assets/logo.png" alt="Logo" class="header-logo">
                        </div>
                        <div class="header-kurdish">
                            <div>مەڵبەندی ڕۆشنبیری و کۆمەڵایەتی</div>
                            <div>ساداتی پیرخدری شاهۆ</div>
                            <div>بەشی نە سەب و ئاینی</div>
                        </div>
                    </div>

                    <!-- Divider Bar -->
                    <div class="header-divider"></div>

                    <!-- Bismillah & Quranic Verse -->
                    <div class="bismillah-title">]بسم الله الرحمن الرحيم[</div>
                    <div class="ayah-title">
                        (إِنَّمَا يُرِيدُ اللَّهُ لِيُذْهِبَ عَنكُمُ الرِّجْسَ أَهْلَ الْبَيْتِ وَيُطَهِّرَكُمْ تَطْهِيرًا)<span class="ayah-ref">الاحزاب33</span>
                    </div>

                    <!-- Green Title Box -->
                    <div class="badge-box">
                        <div class="green-title-badge">
                            شجرة سادة ( <u><?php echo htmlspecialchars($cleanFamilyName); ?></u> )البيرخضرية
                        </div>
                        <div class="badge-underline"></div>
                    </div>

                    <!-- Committee Intro Text -->
                    <div class="intro-statement">
                        بعد عرض هذه الشجرة أمام أنظار لجنة تصديق الانساب في مركزنا الثقافي قمنا بتدقيق سلسلة النسب فيها و مطابقتها مع بقية الشجرات المتوفرة بين أيدينا و بعد الفراغ من التحقيق ستكون سلسلة نسبهم كما يلي :
                    </div>

                    <!-- Lineage Chain -->
                    <div class="lineage-chain-box">
                        <?php echo $fullLineageHtml; ?>
                    </div>
                </div>

                <!-- Bottom Section -->
                <div>
                    <!-- Date -->
                    <div class="date-line">
                        بتاريخ <?php echo htmlspecialchars($docHijri); ?> هـ الموافق <?php echo htmlspecialchars($docGreg); ?> ز
                    </div>

                    <!-- Committee Table -->
                    <table class="committee-table">
                        <thead>
                            <tr>
                                <th>عضو</th>
                                <th>عضو</th>
                                <th>رئيس اللجنة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    ملا الشيخ عبدالله سيدعبيد<br>حوتاشى
                                </td>
                                <td>
                                    الملا سيدعبدالله سيد مصطفى<br>تاوگۆزي
                                </td>
                                <td>
                                    ملا الشيخ علاءالدين رضا محمد<br>بانيموردى
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Signatures & Endorsement -->
                    <div class="signatures-row">
                        <div class="endorsement-block">
                            نؤيد تصديق هذه الشجرة المباركة المصدق من قبل لجنة تصديق الأنساب التابعة لمركزنا .
                        </div>
                        <div class="director-block">
                            <div class="director-name">مظفر سید حسن البیرخضري</div>
                            <div class="director-divider"></div>
                            <div class="director-role">
                                رئيس الهيئة الأدارية<br>
                                مدير المركز
                            </div>
                        </div>
                    </div>

                    <!-- Bottom Olive-Green Footer -->
                    <div class="committee-footer">
                        ( دیزاین : سید سامان م.شیخ علاءالدین بانیمۆرد &nbsp;&nbsp;&nbsp;-&nbsp;&nbsp;&nbsp; کۆمپیوتەر : سید ئەندام سیدابراهیم دەرەشیخان )
                    </div>
                </div>

            </div>
        </div>
        <?php endif; ?>

        <script>
        function reloadWithOptions() {
            const chkBrothers = document.getElementById('chkBrothers') ? (document.getElementById('chkBrothers').checked ? 1 : 0) : 0;
            const url = new URL(window.location.href);
            url.searchParams.set('include_brothers', chkBrothers);
            window.location.href = url.toString();
        }
        </script>
        </body>
        </html>
        <?php
        exit;
    }
}

// -------------------------------------------------------------
// MAIN TABULAR LINEAGE REPORTS PAGE
// -------------------------------------------------------------
$filter_family = isset($_GET['family_id']) ? (int)$_GET['family_id'] : 0;
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : '';
$search_q = isset($_GET['q']) ? trim($_GET['q']) : '';

// Stats
$totalMembers = $conn->query("SELECT COUNT(*) as cnt FROM members")->fetch_assoc()['cnt'] ?? 0;
$activeCount = $conn->query("SELECT COUNT(*) as cnt FROM members WHERE member_status = 'active'")->fetch_assoc()['cnt'] ?? 0;
$deceasedCount = $conn->query("SELECT COUNT(*) as cnt FROM members WHERE member_status != 'active' OR member_status IS NULL")->fetch_assoc()['cnt'] ?? 0;
$familiesCount = $conn->query("SELECT COUNT(*) as cnt FROM families")->fetch_assoc()['cnt'] ?? 0;

// Fetch Families for filter
$families = $conn->query("SELECT id, family_name FROM families ORDER BY family_name ASC")->fetch_all(MYSQLI_ASSOC);

// Build Query
$where = ["1=1"];
$params = [];
$types = "";

if ($filter_family > 0) {
    $where[] = "m.family_id = ?";
    $params[] = $filter_family;
    $types .= "i";
}

if ($filter_status === 'active') {
    $where[] = "m.member_status = 'active'";
} elseif ($filter_status === 'deceased') {
    $where[] = "(m.member_status != 'active' OR m.member_status IS NULL)";
}

if (!empty($search_q)) {
    $where[] = "(m.full_name LIKE ? OR m.member_code LIKE ? OR fat.full_name LIKE ?)";
    $like = "%$search_q%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "sss";
}

$whereSql = implode(" AND ", $where);
$query = "
    SELECT m.id, m.full_name, m.member_code, m.member_status, m.parent_id,
           f.family_name,
           fat.full_name as father_name,
           gfat.full_name as grandfather_name
    FROM members m 
    LEFT JOIN families f ON m.family_id = f.id 
    LEFT JOIN members fat ON m.parent_id = fat.id 
    LEFT JOIN members gfat ON fat.parent_id = gfat.id 
    WHERE $whereSql 
    ORDER BY (m.member_status = 'active') DESC, m.id DESC 
    LIMIT 200
";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$membersList = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

require_once 'includes/layout.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />

<style>
    .reports-header-card h2,
    .print-only-header h2,
    .print-only-header h4,
    .rep-table th,
    .filter-panel h4 {
        font-family: 'Rabar013', 'Rabar022', Tahoma, serif !important;
    }
    .rep-table td,
    .filter-panel,
    .stat-card,
    .reports-header-card p {
        font-family: 'Rabar005', 'Rabar022', Tahoma, sans-serif !important;
    }
    .reports-header-card {
        background: linear-gradient(135deg, #1a5653 0%, #0d3432 100%);
        color: #fff;
        padding: 35px 30px;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(26, 86, 83, 0.2);
        margin-bottom: 25px;
        position: relative;
        overflow: hidden;
    }
    .reports-header-card::after {
        content: "🖨️";
        position: absolute;
        left: 30px;
        bottom: 5px;
        font-size: 100px;
        opacity: 0.08;
        pointer-events: none;
    }
    .reports-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        margin-bottom: 25px;
    }
    .stat-card {
        background: #fff;
        border-radius: 16px;
        padding: 20px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        display: flex;
        align-items: center;
        gap: 16px;
        transition: transform 0.2s;
    }
    .stat-card:hover { transform: translateY(-3px); }
    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }
    .filter-panel {
        background: #fff;
        border-radius: 16px;
        padding: 20px 25px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        margin-bottom: 25px;
    }
    .reports-table-card {
        background: #fff;
        border-radius: 18px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 6px 20px rgba(0,0,0,0.04);
        overflow: hidden;
    }
    .rep-table {
        width: 100%;
        border-collapse: collapse;
        text-align: right;
        font-size: 0.92rem;
    }
    .rep-table th {
        background: #f8fafc;
        color: #475569;
        font-weight: bold;
        padding: 14px 18px;
        border-bottom: 1.5px solid #e2e8f0;
    }
    .rep-table td {
        padding: 13px 18px;
        border-bottom: 1px solid #f1f5f9;
        color: #1e293b;
    }
    .rep-table tr:hover td {
        background: #fdfbf7;
    }
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 0.75rem;
        font-weight: bold;
        padding: 3px 10px;
        border-radius: 20px;
    }
    .status-badge.active {
        background: #ecfdf5;
        color: #047857;
        border: 1px solid #a7f3d0;
    }
    .status-badge.deceased {
        background: #f8fafc;
        color: #64748b;
        border: 1px solid #e2e8f0;
    }
    .action-cert-btn {
        background: linear-gradient(135deg, #1a5653, #134240);
        color: #fff;
        border: none;
        padding: 6px 14px;
        border-radius: 10px;
        font-size: 0.82rem;
        font-weight: bold;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        text-decoration: none;
        transition: all 0.2s;
        box-shadow: 0 3px 10px rgba(26, 86, 83, 0.15);
    }
    .action-cert-btn:hover {
        background: #0f3533;
        transform: translateY(-2px);
        color: #fff;
    }
    @media print {
        .reports-header-card, .filter-panel, .reports-stats-grid, .topbar, .sidebar, .action-cell {
            display: none !important;
        }
        .main-wrapper, body {
            background: #fff !important;
            padding: 0 !important;
        }
        .reports-table-card {
            border: none !important;
            box-shadow: none !important;
        }
        .rep-table th, .rep-table td {
            border: 1px solid #ccc !important;
            font-size: 11pt !important;
        }
        .print-only-header {
            display: block !important;
            text-align: center;
            margin-bottom: 20px;
        }
    }
    .print-only-header { display: none; }
</style>

<div class="print-only-header">
    <h2 style="margin: 0; color: #1a5653;"><?php echo htmlspecialchars($branding['system_name']); ?></h2>
    <h4 style="margin: 5px 0 15px; color: #c5a059;"><?php echo htmlspecialchars($branding['system_subtitle']); ?> - لیستی ئەندامان</h4>
    <div style="font-size: 0.85rem; color: #666;">بەرواری دەرهێنان: <?php echo date('Y/m/d H:i'); ?></div>
</div>

<!-- Header Card -->
<div class="reports-header-card">
    <h2 style="margin: 0; font-size: 1.6rem; font-weight: bold;">
        <i class="fas fa-print" style="color: #f1c40f;"></i> ڕاپۆرت و بڕوانامەی فەرمی نەسەب
    </h2>
    <p style="margin: 8px 0 0; opacity: 0.9; font-size: 0.95rem;">
        دەرهێنانی لیستی ئەندامان، ڕاپۆرتی گشتی بنەماڵەکان، و چاپکردنی بڕوانامەی ڕەسەنی نەسەب بۆ ئەندامان بە کوالیتی بەرز.
    </p>
</div>

<!-- Stats Row -->
<div class="reports-stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: #e0f2fe; color: #0284c7;">
            <i class="fas fa-users"></i>
        </div>
        <div>
            <div style="font-size: 0.8rem; color: #64748b;">کۆی ئەندامان</div>
            <div style="font-size: 1.4rem; font-weight: bold; color: #0f172a;"><?php echo number_format($totalMembers); ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: #ecfdf5; color: #059669;">
            <i class="fas fa-heartbeat"></i>
        </div>
        <div>
            <div style="font-size: 0.8rem; color: #64748b;">لە ژیاندا ماو</div>
            <div style="font-size: 1.4rem; font-weight: bold; color: #059669;"><?php echo number_format($activeCount); ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: #f1f5f9; color: #475569;">
            <i class="fas fa-monument"></i>
        </div>
        <div>
            <div style="font-size: 0.8rem; color: #64748b;">کۆچکردوو</div>
            <div style="font-size: 1.4rem; font-weight: bold; color: #475569;"><?php echo number_format($deceasedCount); ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: #fef3c7; color: #d97706;">
            <i class="fas fa-landmark"></i>
        </div>
        <div>
            <div style="font-size: 0.8rem; color: #64748b;">بنەماڵەکان</div>
            <div style="font-size: 1.4rem; font-weight: bold; color: #d97706;"><?php echo number_format($familiesCount); ?></div>
        </div>
    </div>
</div>

<!-- Filters Panel -->
<div class="filter-panel">
    <form method="GET" action="lineage_reports.php" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
        <div style="flex: 2; min-width: 200px;">
            <label style="display: block; font-size: 0.82rem; font-weight: bold; color: #475569; margin-bottom: 6px;">گەڕان بە ناو یان کۆد:</label>
            <input type="text" name="q" value="<?php echo htmlspecialchars($search_q); ?>" placeholder="ناوی کەسەکە، باوکی، یان کۆد..." class="lib-input" style="width: 100%; height: 42px; border-radius: 10px;">
        </div>

        <div style="flex: 1.5; min-width: 180px;">
            <label style="display: block; font-size: 0.82rem; font-weight: bold; color: #475569; margin-bottom: 6px;">بنەماڵە:</label>
            <select name="family_id" class="lib-input" style="width: 100%; height: 42px; border-radius: 10px;">
                <option value="0">-- هەموو بنەماڵەکان --</option>
                <?php foreach($families as $fam): ?>
                    <option value="<?php echo $fam['id']; ?>" <?php echo $filter_family == $fam['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($fam['family_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="flex: 1.2; min-width: 150px;">
            <label style="display: block; font-size: 0.82rem; font-weight: bold; color: #475569; margin-bottom: 6px;">دۆخی ژیان:</label>
            <select name="status" class="lib-input" style="width: 100%; height: 42px; border-radius: 10px;">
                <option value="">هەموو</option>
                <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>🟢 لە ژیاندا ماو</option>
                <option value="deceased" <?php echo $filter_status === 'deceased' ? 'selected' : ''; ?>>🕊️ کۆچکردوو</option>
            </select>
        </div>

        <div style="display: flex; gap: 8px;">
            <button type="submit" class="lib-btn" style="height: 42px; background: #1a5653; color: #fff; border-radius: 10px; padding: 0 20px;">
                <i class="fas fa-filter"></i> فلتەرکردن
            </button>
            <a href="lineage_reports.php" class="lib-btn" style="height: 42px; background: #f1f5f9; color: #475569; border-radius: 10px; padding: 0 15px; display: flex; align-items: center; justify-content: center; text-decoration: none;">
                پاککردنەوە
            </a>
            <button type="button" onclick="window.print()" class="lib-btn" style="height: 42px; background: #c5a059; color: #fff; border-radius: 10px; padding: 0 18px;">
                <i class="fas fa-print"></i> چاپی لیست
            </button>
        </div>
    </form>
</div>

<!-- Table Card -->
<div class="reports-table-card">
    <div style="padding: 16px 20px; background: #fafafa; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
        <div style="font-weight: bold; color: #334155;">
            لیستی تۆمارکراوان (<?php echo count($membersList); ?> ئەندام)
        </div>
        <div style="font-size: 0.82rem; color: #64748b;">
            کلیک لەسەر «بڕوانامە» بکە بۆ دروستکردنی بڕوانامەی ڕەسمی نەسەب
        </div>
    </div>

    <div style="overflow-x: auto;">
        <table class="rep-table">
            <thead>
                <tr>
                    <th style="width: 80px;">کۆد</th>
                    <th>ناوی تەواو</th>
                    <th>ناوی باوک و باپیر</th>
                    <th>بنەماڵە</th>
                    <th>دۆخ</th>
                    <th class="action-cell" style="text-align: center; width: 190px;">کردارەکان</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($membersList)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px; color: #94a3b8;">
                            هیچ ئەندامێک بەم مەرجانە نەدۆزرایەوە
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach($membersList as $m): ?>
                        <tr>
                            <td>
                                <span style="font-weight: bold; color: #475569; background: #f1f5f9; padding: 2px 7px; border-radius: 6px;">
                                    #<?php echo htmlspecialchars($m['member_code'] ?: $m['id']); ?>
                                </span>
                            </td>
                            <td>
                                <strong style="color: #0f172a; font-size: 0.96rem;"><?php echo htmlspecialchars($m['full_name']); ?></strong>
                            </td>
                            <td style="color: #64748b; font-size: 0.88rem;">
                                <?php 
                                $fPart = $m['father_name'] ?: 'نادیار';
                                $gPart = $m['grandfather_name'] ?: '';
                                echo htmlspecialchars($fPart . ($gPart ? " بن $gPart" : ''));
                                ?>
                            </td>
                            <td>
                                <span style="color: #1a5653; font-weight: bold;"><?php echo htmlspecialchars($m['family_name'] ?: 'سادات'); ?></span>
                            </td>
                            <td>
                                <?php if($m['member_status'] === 'active'): ?>
                                    <span class="status-badge active"><i class="fas fa-circle" style="font-size: 6px;"></i> لە ژیاندا</span>
                                <?php else: ?>
                                    <span class="status-badge deceased">🕊️ کۆچکردوو</span>
                                <?php endif; ?>
                            </td>
                            <td class="action-cell" style="text-align: center;">
                                <div style="display: inline-flex; gap: 5px; align-items: center;">
                                    <a href="lineage_reports.php?cert_id=<?php echo $m['id']; ?>&type=certificate" target="_blank" class="action-cert-btn" style="background: #720000; color: #fff; text-decoration: none;" title="شەهادەی نەسەب">
                                        <i class="fas fa-certificate"></i> شەهادە
                                    </a>
                                    <a href="lineage_reports.php?cert_id=<?php echo $m['id']; ?>&type=committee" target="_blank" class="action-cert-btn" style="background: #0b5e28; color: #fff; text-decoration: none;" title="کڵێشەی فەرمی لیژنە">
                                        <i class="fas fa-file-contract"></i> کڵێشەی فەرمی
                                    </a>
                                    <a href="kinship_search.php?id=<?php echo $m['id']; ?>" style="color: #64748b; font-size: 1.1rem; padding: 5px 8px; text-decoration: none;" title="تۆڕی خزمایەتی">
                                        <i class="fas fa-network-wired"></i>
                                    </a>
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
