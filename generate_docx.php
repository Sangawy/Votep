<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Forbidden');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

require_once 'config.php';
$conn = getDB();

$templatePath = __DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'template.docx';
if (!is_file($templatePath)) {
    http_response_code(404);
    die("Template file not found at: $templatePath");
}

$values = [
    'NUMBER' => trim($_POST['doc_num'] ?? ''),
    'DATE' => trim($_POST['doc_date'] ?? ''),
    'CORRESPONDENT' => trim($_POST['doc_to'] ?? ''),
    'SUBJECT' => trim($_POST['doc_subject'] ?? ''),
    'CONTENT' => trim($_POST['doc_content'] ?? ''),
];

// Handle Committee Table
$committeeHeaders = $_POST['committee_headers'] ?? [];
$committeeCells = $_POST['committee_cells'] ?? [];
$committeeXml = '';
$committeeDataJson = null;

if (!empty($committeeHeaders)) {
    // Chunk cells into rows
    $colCount = count($committeeHeaders);
    $committeeRows = array_chunk($committeeCells, $colCount);
    $committeeXml = buildCommitteeTableXml($committeeHeaders, $committeeRows);
    
    // Prepare for DB
    $committeeDataJson = json_encode([
        'headers' => $committeeHeaders,
        'cells' => $committeeCells
    ], JSON_UNESCAPED_UNICODE);
}
$docType = trim($_POST['doc_type'] ?? 'دەرچوو');

foreach (['DATE', 'CORRESPONDENT', 'SUBJECT'] as $field) {
    if ($values[$field] === '') {
        http_response_code(422);
        exit('Required field is missing.');
    }
}

$settings = ['archive_base_dir' => 'D:/Archive/'];
$loginUser = [
    'id' => (int)($_SESSION['user_id'] ?? 0),
    'name' => $_SESSION['username'] ?? 'بەکارهێنەر',
];
$docId = isset($_POST['doc_id']) ? (int)$_POST['doc_id'] : 0;

// Fetch User's Unit and Signatories
$unitName = '';
$managerName = '';
$managerRank = '';
$deputyName = '';
$deputyRank = '';

if ($conn) {
    $userId = (int)$loginUser['id'];
    $userRes = $conn->query("SELECT u.unit_id, un.unit_name FROM users u LEFT JOIN staff_units un ON u.unit_id = un.id WHERE u.id = $userId LIMIT 1");
    if ($userRes && $userRow = $userRes->fetch_assoc()) {
        $uId = (int)($userRow['unit_id'] ?? 0);
        if ($uId <= 0) $uId = 1; // Default to main board/manager unit if user has no unit
        
        $unitName = $userRow['unit_name'] ?? '';
        
        $sigRes = $conn->query("SELECT 
                                m1.full_name as m_name, m1.member_rank as m_rank,
                                m2.full_name as d_name, m2.member_rank as d_rank,
                                un.unit_name as actual_unit_name
                              FROM staff_units un
                              LEFT JOIN members m1 ON un.manager_id = m1.id
                              LEFT JOIN members m2 ON un.deputy_id = m2.id
                              WHERE un.id = $uId");
        if ($sigRes && $sigRow = $sigRes->fetch_assoc()) {
            $managerName = $sigRow['m_name'] ?? '';
            $managerRank = $sigRow['m_rank'] ?? '';
            $deputyName = $sigRow['d_name'] ?? '';
            $deputyRank = $sigRow['d_rank'] ?? '';
            if (empty($unitName)) $unitName = $sigRow['actual_unit_name'] ?? '';
        }
    }
}

if ($conn) {
    $res = $conn->query("SELECT archive_base_dir, system_name FROM settings WHERE id = 1");
    if ($res && $row = $res->fetch_assoc()) {
        if (!empty($row['archive_base_dir'])) $settings['archive_base_dir'] = $row['archive_base_dir'];
        $settings['system_name'] = $row['system_name'] ?? 'ڕێکخراو';
    }
}

$signBy = isset($_POST['sign_by_organization']) ? 'organization' : 'director';
if ($signBy === 'organization') {
    $managerName = $settings['system_name'] ?? 'ڕێکخراو';
    $managerRank = '';
    $unitName = '';
    $deputyName = '';
    $deputyRank = '';
}

// Add these to values for replacement
$values['SIGNATORY'] = $managerName;
$values['SIGNATORY_RANK'] = $managerRank;
$values['UNIT_NAME'] = $unitName;
$values['DEPUTY'] = $deputyName;
$values['DEPUTY_RANK'] = $deputyRank;

if ($conn) {
    $userId = (int)$loginUser['id'];
    $userRes = $conn->query("SELECT username, full_name FROM users WHERE id = $userId LIMIT 1");
    if ($userRes && $userRow = $userRes->fetch_assoc()) {
        $loginUser['name'] = $userRow['full_name'] ?: ($userRow['username'] ?: $loginUser['name']);
    }

    // Only generate sequential number if it's new and empty
    if ($docId <= 0 && $values['NUMBER'] === '') {
        $values['NUMBER'] = (string)getNextArchiveDocNumberByType($conn, $docType);
    }
}

$year = date('Y', strtotime($values['DATE']) ?: time());
$typeFolder = getArchiveTypeFolder($docType);
$baseDir = rtrim(str_replace('\\', '/', $settings['archive_base_dir']), '/');
$targetDir = $baseDir . '/' . $year . '/' . $typeFolder;

if (!is_dir($targetDir) && !mkdir($targetDir, 0777, true)) {
    http_response_code(500);
    exit('Could not create archive directory.');
}

$safeNumber = preg_replace('/[^\p{L}\p{N}_\-]+/u', '_', $values['NUMBER']);
$outputName = ($safeNumber ?: date('Ymd_His')) . '.docx';
$targetPath = $targetDir . '/' . $outputName;

if (file_exists($targetPath)) {
    $targetPath = $targetDir . '/' . ($safeNumber ?: date('Ymd_His')) . '_' . date('His') . '.docx';
}

if (!copy($templatePath, $targetPath)) {
    http_response_code(500);
    exit('Could not prepare template.');
}

$manualAttachmentsListText = trim($_POST['attachments_list_text'] ?? '');
$manualAttachmentsPreviewText = trim($_POST['attachments_preview_text'] ?? '');

$attachmentItems = collectArchiveDocAttachmentItems($conn, $docId);
$attachmentsListXml = $manualAttachmentsListText !== ''
    ? buildAttachmentLinesXml(preg_split('/\r\n|\r|\n/u', $manualAttachmentsListText) ?: [], 'هاوپێچ :')
    : buildAttachmentsListXml($attachmentItems, 'هاوپێچ :');
$attachmentsPreviewXml = $manualAttachmentsPreviewText !== ''
    ? buildAttachmentLinesXml(preg_split('/\r\n|\r|\n/u', $manualAttachmentsPreviewText) ?: [], 'وێنەیەک بۆ:')
    : '';

$xmlEntries = [
    'word/document.xml',
    'word/header1.xml', 'word/header2.xml', 'word/header3.xml',
    'word/footer1.xml', 'word/footer2.xml', 'word/footer3.xml'
];

foreach ($xmlEntries as $entry) {
    $xml = readDocxEntry($targetPath, $entry);
    if ($xml === null) continue;

    foreach ($values as $key => $value) {
        $placeholder = '[[' . $key . ']]';
        $altPlaceholder = '{{' . $key . '}}';
        
        if ($key === 'CONTENT') {
            $complexValue = buildContentReplacementXml((string)$value, $committeeXml);
            $xml = replaceDocxPlaceholder($xml, $placeholder, $complexValue, true);
            $xml = replaceDocxPlaceholder($xml, $altPlaceholder, $complexValue, true);
        } else {
            $xml = replaceDocxPlaceholder($xml, $placeholder, $value);
            $xml = replaceDocxPlaceholder($xml, $altPlaceholder, $value);
        }
    }

    // Explicit Table Tag Replacement
    if (!empty($committeeXml)) {
        $xml = replaceDocxPlaceholder($xml, '[[COMMITTEE_TABLE]]', '</w:t></w:r></w:p>' . $committeeXml . '<w:p><w:r><w:t>', true);
        $xml = replaceDocxPlaceholder($xml, '{{COMMITTEE_TABLE}}', '</w:t></w:r></w:p>' . $committeeXml . '<w:p><w:r><w:t>', true);
    }

    if ($entry === 'word/document.xml') {
        $xml = replaceDocxBlockPlaceholder($xml, 'ATTACHMENTS_LIST', $attachmentsListXml);
        $xml = replaceDocxBlockPlaceholder($xml, 'ATTACHMENTS_PREVIEW', $attachmentsPreviewXml);
    }
    
    writeDocxEntry($targetPath, $entry, $xml);
}

$userYearCount = 1;

if ($conn) {
    ensureArchiveDocsCreatedBy($conn);

    $userId = (int)$loginUser['id'];
    $countRes = $conn->query("SELECT COUNT(*) AS cnt FROM archive_docs WHERE created_by = $userId AND YEAR(doc_date) = " . (int)$year);
    if ($countRes && $countRow = $countRes->fetch_assoc()) {
        $userYearCount = ((int)($countRow['cnt'] ?? 0)) + 1;
    }

    $footerXml = readDocxEntry($targetPath, 'word/footer1.xml');
    if ($footerXml !== null) {
        $footerText = $loginUser['name'] . ' - ڕێکەوت: ' . $values['DATE'] . ' - ساڵی ' . $year . ' نوسراوی ژمارە ' . $userYearCount;
        writeDocxEntry($targetPath, 'word/footer1.xml', buildFooterXml($footerText));
    }

    $docNumber = $conn->real_escape_string($values['NUMBER']);
    $docDate = $conn->real_escape_string($values['DATE']);
    $subject = $conn->real_escape_string($values['SUBJECT']);
    $correspondent = $conn->real_escape_string($values['CORRESPONDENT']);
    $dbDocType = $conn->real_escape_string(normalizeArchiveDocType($docType));
    $dbWord = $conn->real_escape_string($values['CONTENT']);
    $dbAttachmentsList = $manualAttachmentsListText !== '' ? "'" . $conn->real_escape_string($manualAttachmentsListText) . "'" : "NULL";
    $dbAttachmentsPreview = $manualAttachmentsPreviewText !== '' ? "'" . $conn->real_escape_string($manualAttachmentsPreviewText) . "'" : "NULL";
    $filePath = $conn->real_escape_string(str_replace('/', DIRECTORY_SEPARATOR, $targetPath));
    $createdBy = (int)$loginUser['id'];
    $relatedDocId = !empty($_POST['related_doc_id']) ? (int)$_POST['related_doc_id'] : 'NULL';

    $dbCommittee = $committeeDataJson ? "'" . $conn->real_escape_string($committeeDataJson) . "'" : "NULL";
    $dbSignBy = $conn->real_escape_string($signBy);

    if ($docId > 0) {
        $sql = "UPDATE archive_docs SET 
                doc_number = '$docNumber', 
                subject = '$subject', 
                doc_type = '$dbDocType', 
                correspondent = '$correspondent', 
                doc_date = '$docDate', 
                word_content = '$dbWord',
                file_path = '$filePath',
                related_doc_id = $relatedDocId,
                committee_data = $dbCommittee,
                attachments_list_text = $dbAttachmentsList,
                attachments_preview_text = $dbAttachmentsPreview,
                sign_by = '$dbSignBy'
                WHERE id = $docId";
        $conn->query($sql);
    } else {
        $sql = "INSERT INTO archive_docs (doc_number, subject, doc_type, correspondent, doc_date, word_content, file_path, created_by, related_doc_id, committee_data, attachments_list_text, attachments_preview_text, sign_by) 
                VALUES ('$docNumber', '$subject', '$dbDocType', '$correspondent', '$docDate', '$dbWord', '$filePath', $createdBy, $relatedDocId, $dbCommittee, $dbAttachmentsList, $dbAttachmentsPreview, '$dbSignBy')";
        $conn->query($sql);
    }
}

openLocalWordFile($targetPath);
header('Location: docs.php?msg=created');
exit;

function getArchiveTypeFolder(string $type): string
{
    $normalized = normalizeArchiveDocType($type);
    if ($normalized === 'Inbound') return 'Inbound';
    if ($normalized === 'Outbound') return 'Outbound';
    return preg_replace('/[^\p{L}\p{N}_\-]+/u', '_', $type) ?: 'Other';
}

function normalizeArchiveDocType(string $type): string
{
    $type = trim($type);
    if (in_array($type, ['هاتوو', 'Inbound'], true)) return 'Inbound';
    if (in_array($type, ['دەرچوو', 'دەرچو', 'Outbound'], true)) return 'Outbound';
    return $type ?: 'Outbound';
}

function getNextArchiveDocNumberByType(mysqli $conn, string $type): int
{
    $normalized = normalizeArchiveDocType($type);
    $typeEsc = $conn->real_escape_string($normalized);
    $legacyType = $normalized === 'Inbound' ? 'هاتوو' : 'دەرچوو';
    $legacyEsc = $conn->real_escape_string($legacyType);
    $res = $conn->query("SELECT MAX(CAST(doc_number AS UNSIGNED)) AS max_num
                         FROM archive_docs
                         WHERE doc_number REGEXP '^[0-9]+$'
                           AND (doc_type = '$typeEsc' OR doc_type = '$legacyEsc')");
    $max = 0;
    if ($res && $row = $res->fetch_assoc()) {
        $max = (int)($row['max_num'] ?? 0);
    }
    return $max + 1;
}

function openLocalWordFile(string $path): void
{
    $winPath = str_replace('/', '\\', $path);
    $cmd = 'cmd /c start "" "' . str_replace('"', '\"', $winPath) . '"';
    @pclose(@popen($cmd, 'r'));
}

function ensureArchiveDocsCreatedBy(mysqli $conn): void
{
    $createdByCol = $conn->query("SHOW COLUMNS FROM archive_docs LIKE 'created_by'");
    if (!$createdByCol || $createdByCol->num_rows === 0) {
        $conn->query("ALTER TABLE archive_docs ADD COLUMN created_by INT NULL AFTER file_path");
    }
}

function buildFooterXml(string $text): string
{
    $safeText = htmlspecialchars($text, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        . '<w:p><w:pPr><w:jc w:val="center"/><w:rPr><w:rFonts w:ascii="Rabar_022" w:hAnsi="Rabar_022" w:cs="Rabar_022"/><w:sz w:val="20"/><w:szCs w:val="20"/><w:rtl/></w:rPr></w:pPr>'
        . '<w:r><w:rPr><w:rFonts w:ascii="Rabar_022" w:hAnsi="Rabar_022" w:cs="Rabar_022"/><w:sz w:val="20"/><w:szCs w:val="20"/><w:rtl/></w:rPr><w:t>' . $safeText . '</w:t></w:r>'
        . '</w:p></w:ftr>';
}

function buildContentReplacementXml(string $value, string $committeeXml = ''): string
{
    $escaped = htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    $escaped = str_replace("\n", '<w:br/>', $escaped);
    return $escaped . '</w:t></w:r></w:p>' . $committeeXml . '<w:p><w:r><w:t>';
}

function replaceDocxBlockPlaceholder(string $xml, string $placeholderName, string $blockXml): string
{
    $replacement = $blockXml === '' ? '' : '</w:t></w:r></w:p>' . $blockXml . '<w:p><w:r><w:t>';
    $xml = replaceDocxPlaceholder($xml, '[[' . $placeholderName . ']]', $replacement, true);
    return replaceDocxPlaceholder($xml, '{{' . $placeholderName . '}}', $replacement, true);
}

function replaceDocxPlaceholder(string $xml, string $placeholder, string $value, bool $isRaw = false): string
{
    if ($isRaw) {
        $escapedValue = $value;
    } else {
        $escapedValue = htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
        $escapedValue = str_replace("\n", '<w:br/>', $escapedValue);
    }
    
    $chars = preg_split('//u', $placeholder, -1, PREG_SPLIT_NO_EMPTY);
    if (!$chars) return $xml;

    $pattern = '';
    $lastIndex = count($chars) - 1;
    foreach ($chars as $index => $char) {
        $pattern .= preg_quote($char, '/');
        if ($index !== $lastIndex) {
            $pattern .= '(?:<[^>]+>)*';
        }
    }

    return preg_replace('/' . $pattern . '/u', $escapedValue, $xml) ?? $xml;
}

function collectArchiveDocAttachmentItems(?mysqli $conn, int $docId): array
{
    $items = [];
    $seen = [];

    if (isset($_FILES['scanned_file']) && (int)($_FILES['scanned_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        appendAttachmentItem($items, $seen, (string)$_FILES['scanned_file']['tmp_name'], (string)($_FILES['scanned_file']['name'] ?? ''));
    }

    if (!$conn || $docId <= 0) {
        return $items;
    }

    $res = $conn->query("SELECT file_path FROM archive_docs WHERE id = $docId LIMIT 1");
    if ($res && $row = $res->fetch_assoc()) {
        $path = (string)($row['file_path'] ?? '');
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($path !== '' && $ext !== 'docx' && is_file($path)) {
            appendAttachmentItem($items, $seen, $path, basename($path));
        }
    }

    if (tableExists($conn, 'document_attachments')) {
        $attRes = $conn->query("SELECT file_path, file_name FROM document_attachments WHERE doc_id = $docId ORDER BY id ASC");
        if ($attRes) {
            while ($att = $attRes->fetch_assoc()) {
                appendAttachmentItem(
                    $items,
                    $seen,
                    (string)($att['file_path'] ?? ''),
                    (string)($att['file_name'] ?? '')
                );
            }
        }
    }

    return $items;
}

function appendAttachmentItem(array &$items, array &$seen, string $path, string $name = ''): void
{
    $path = trim($path);
    if ($path === '' || !is_file($path)) {
        return;
    }

    $key = strtolower(str_replace('\\', '/', $path));
    if (isset($seen[$key])) {
        return;
    }

    $seen[$key] = true;
    $items[] = [
        'path' => $path,
        'name' => $name !== '' ? $name : basename($path),
    ];
}

function tableExists(mysqli $conn, string $tableName): bool
{
    $safe = $conn->real_escape_string($tableName);
    $res = $conn->query("SHOW TABLES LIKE '$safe'");
    return (bool)($res && $res->num_rows > 0);
}

function buildAttachmentsListXml(array $items, string $title = ''): string
{
    if ($items === []) {
        return '';
    }

    $lines = [];
    foreach ($items as $item) {
        $lines[] = (string)($item['name'] ?? basename((string)$item['path']));
    }

    return buildAttachmentLinesXml($lines, $title);
}

function buildAttachmentLinesXml(array $lines, string $title = ''): string
{
    $linesXml = '';
    $hasRealData = false;
    foreach ($lines as $line) {
        $line = trim((string)$line);
        if ($line === '') {
            continue;
        }
        $hasRealData = true;
        $name = htmlspecialchars($line, ENT_XML1 | ENT_COMPAT, 'UTF-8');
        $linesXml .= '<w:p><w:pPr><w:bidi/><w:jc w:val="left"/></w:pPr>'
             . '<w:r><w:rPr><w:rtl w:val="1"/><w:sz w:val="26"/><w:szCs w:val="26"/><w:rFonts w:ascii="Rabar_005" w:hAnsi="Rabar_005" w:cs="Rabar_005"/><w:lang w:bidi="ku-IQ"/></w:rPr><w:t>'
             . $name
             . '</w:t></w:r></w:p>';
    }
    
    if (!$hasRealData) {
        return '';
    }

    $titleXml = '';
    if ($title !== '') {
        $safeTitle = htmlspecialchars($title, ENT_XML1 | ENT_COMPAT, 'UTF-8');
        $titleXml = '<w:p><w:pPr><w:bidi/><w:jc w:val="left"/></w:pPr>'
             . '<w:r><w:rPr><w:b/><w:rtl w:val="1"/><w:sz w:val="26"/><w:szCs w:val="26"/><w:rFonts w:ascii="Rabar_005" w:hAnsi="Rabar_005" w:cs="Rabar_005"/><w:lang w:bidi="ku-IQ"/></w:rPr><w:t>'
             . $safeTitle
             . '</w:t></w:r></w:p>';
    }

    return $titleXml . $linesXml;
}

function buildCommitteeTableXml(array $headers, array $rows): string
{
    // Check if there is ANY actual data in the rows
    $hasData = false;
    foreach ($rows as $row) {
        foreach ($row as $cell) {
            if (trim($cell) !== '') {
                $hasData = true;
                break 2;
            }
        }
    }

    if (!$hasData) {
        return '';
    }

    $fullHeaders = array_merge(['ژ'], array_values($headers));
    $dataColsCount = max(1, count($headers));
    $tableWidthDxa = 9000;
    $numColWidthDxa = 600;
    $dataColWidthDxa = (int)floor(($tableWidthDxa - $numColWidthDxa) / $dataColsCount);
    if ($dataColWidthDxa < 1) $dataColWidthDxa = 1;

    $xml = '<w:tbl>';
    // Table Properties: Borders, Width, Alignment, RTL
    $xml .= '<w:tblPr>
                <w:tblStyle w:val="TableGrid"/>
                <w:tblW w:w="' . $tableWidthDxa . '" w:type="dxa"/>
                <w:jc w:val="right"/>
                <w:bidiVisual/>
                <w:tblLayout w:type="fixed"/>
                <w:tblLook w:val="04A0" w:firstRow="1" w:lastRow="0" w:firstColumn="1" w:lastColumn="0" w:noHBand="0" w:noVBand="1"/>
                <w:tblBorders>
                    <w:top w:val="single" w:sz="4" w:space="0" w:color="auto"/>
                    <w:left w:val="single" w:sz="4" w:space="0" w:color="auto"/>
                    <w:bottom w:val="single" w:sz="4" w:space="0" w:color="auto"/>
                    <w:right w:val="single" w:sz="4" w:space="0" w:color="auto"/>
                    <w:insideH w:val="single" w:sz="4" w:space="0" w:color="auto"/>
                    <w:insideV w:val="single" w:sz="4" w:space="0" w:color="auto"/>
                </w:tblBorders>
            </w:tblPr>';
    $xml .= '<w:tblGrid>';
    foreach ($fullHeaders as $headerText) {
        $gridWidth = ((string)$headerText === 'ژ') ? $numColWidthDxa : $dataColWidthDxa;
        $xml .= '<w:gridCol w:w="' . $gridWidth . '"/>';
    }
    $xml .= '</w:tblGrid>';
    
    // Header Row
    $xml .= '<w:tr>
                <w:trPr><w:trHeight w:val="450" w:hRule="atLeast"/></w:trPr>';

    foreach ($fullHeaders as $headerText) {
        $safeHeader = htmlspecialchars((string)$headerText, ENT_XML1 | ENT_COMPAT, 'UTF-8');
        $isNumHeader = ((string)$headerText === 'ژ');
        $tcWVal = $isNumHeader ? $numColWidthDxa : $dataColWidthDxa;
        $tcW = '<w:tcW w:w="' . $tcWVal . '" w:type="dxa"/>';
        $xml .= '<w:tc>
                    <w:tcPr>' . $tcW . '<w:tcMar><w:top w:w="30" w:type="dxa"/><w:right w:w="60" w:type="dxa"/><w:bottom w:w="30" w:type="dxa"/><w:left w:w="60" w:type="dxa"/></w:tcMar><w:shd w:val="clear" w:color="auto" w:fill="E7E6E6"/><w:vAlign w:val="center"/></w:tcPr>
                    <w:p><w:pPr><w:bidi/><w:jc w:val="left"/></w:pPr><w:r><w:rPr><w:b/><w:rtl w:val="1"/><w:sz w:val="28"/><w:szCs w:val="28"/><w:rFonts w:ascii="Rabar_021" w:hAnsi="Rabar_021" w:cs="Rabar_021" w:eastAsia="Rabar_021" w:hint="cs"/><w:lang w:bidi="ku-IQ"/></w:rPr><w:t>' . $safeHeader . '</w:t></w:r></w:p>
                </w:tc>';
    }
    $xml .= '</w:tr>';
    
    // Data Rows
    foreach ($rows as $idx => $row) {
        // Skip if entire row is empty
        $isEmpty = true;
        foreach($row as $c) { if(trim($c) !== '') { $isEmpty = false; break; } }
        if ($isEmpty) continue;

        $numStr = convertToKurdishDigits($idx + 1);
        $cells = array_merge([['type' => 'num', 'text' => $numStr]], array_map(static function ($v) {
            return ['type' => 'data', 'text' => $v];
        }, array_values($row)));
        $xml .= '<w:tr>
                    <w:trPr><w:trHeight w:val="350" w:hRule="atLeast"/></w:trPr>
                    ';

        foreach ($cells as $cell) {
            $cellText = $cell['type'] === 'num' ? (string)$cell['text'] : trim((string)$cell['text']);
            $safeCell = htmlspecialchars($cellText, ENT_XML1 | ENT_COMPAT, 'UTF-8');
            $tcWVal = $cell['type'] === 'num' ? $numColWidthDxa : $dataColWidthDxa;
            $tcW = '<w:tcW w:w="' . $tcWVal . '" w:type="dxa"/>';
            $xml .= '<w:tc><w:tcPr>' . $tcW . '<w:tcMar><w:top w:w="30" w:type="dxa"/><w:right w:w="60" w:type="dxa"/><w:bottom w:w="30" w:type="dxa"/><w:left w:w="60" w:type="dxa"/></w:tcMar><w:vAlign w:val="center"/></w:tcPr><w:p><w:pPr><w:bidi/><w:jc w:val="left"/></w:pPr><w:r><w:rPr><w:rtl w:val="1"/><w:sz w:val="28"/><w:szCs w:val="28"/><w:rFonts w:ascii="Rabar_005" w:hAnsi="Rabar_005" w:cs="Rabar_005"/><w:lang w:bidi="ku-IQ"/></w:rPr><w:t>' . $safeCell . '</w:t></w:r></w:p></w:tc>';
        }
        $xml .= '</w:tr>';
    }
    
    $xml .= '</w:tbl>';
    return $xml;
}

function convertToKurdishDigits($num): string
{
    $arabic = ['0','1','2','3','4','5','6','7','8','9'];
    $kurdish = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
    return str_replace($arabic, $kurdish, (string)$num);
}

function readDocxEntry(string $docxPath, string $entryName): ?string
{
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($docxPath) !== true) return null;
        $content = $zip->getFromName($entryName);
        $zip->close();
        if ($content !== false) return $content;
    }

    $docxPath = str_replace('/', DIRECTORY_SEPARATOR, $docxPath);

    $tempDir = __DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'docs';
    if (!is_dir($tempDir)) @mkdir($tempDir, 0777, true);
    
    $xmlPath = $tempDir . DIRECTORY_SEPARATOR . 'temp_' . uniqid() . '.xml';
    $script = "
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
\$zip = [IO.Compression.ZipFile]::OpenRead('" . psQuote($docxPath) . "')
\$entry = \$zip.GetEntry('" . psQuote($entryName) . "')
if (\$null -eq \$entry) { \$zip.Dispose(); exit 2 }
\$inputStream = \$entry.Open()
\$outputStream = [IO.File]::Open('" . psQuote($xmlPath) . "', [IO.FileMode]::Create, [IO.FileAccess]::Write)
\$inputStream.CopyTo(\$outputStream)
\$outputStream.Close(); \$inputStream.Close(); \$zip.Dispose()
";

    $ok = runPowerShell($script);
    $content = ($ok && is_file($xmlPath)) ? file_get_contents($xmlPath) : null;
    @unlink($xmlPath);
    return $content === false ? null : $content;
}

function writeDocxEntry(string $docxPath, string $entryName, string $content): bool
{
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($docxPath) !== true) return false;
        $ok = $zip->addFromString($entryName, $content) !== false;
        $zip->close();
        if ($ok) return true;
    }

    $docxPath = str_replace('/', DIRECTORY_SEPARATOR, $docxPath);
    $tempDir = __DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'docs';
    if (!is_dir($tempDir)) @mkdir($tempDir, 0777, true);

    $xmlPath = $tempDir . DIRECTORY_SEPARATOR . 'temp_' . uniqid() . '.xml';
    file_put_contents($xmlPath, $content);
    $script = "
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
\$zip = [IO.Compression.ZipFile]::Open('" . psQuote($docxPath) . "', [IO.Compression.ZipArchiveMode]::Update)
\$entry = \$zip.GetEntry('" . psQuote($entryName) . "')
if (\$null -ne \$entry) { \$entry.Delete() }
\$newEntry = \$zip.CreateEntry('" . psQuote($entryName) . "')
\$inputStream = [IO.File]::OpenRead('" . psQuote($xmlPath) . "')
\$outputStream = \$newEntry.Open()
\$inputStream.CopyTo(\$outputStream)
\$outputStream.Close(); \$inputStream.Close(); \$zip.Dispose()
";

    $ok = runPowerShell($script);
    @unlink($xmlPath);
    return $ok;
}

function runPowerShell(string $script): bool
{
    $tempDir = __DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'docs';
    if (!is_dir($tempDir)) @mkdir($tempDir, 0777, true);
    
    $scriptPath = $tempDir . DIRECTORY_SEPARATOR . 'ps_' . uniqid() . '.ps1';
    file_put_contents($scriptPath, $script);
    $cmd = 'C:\Windows\System32\WindowsPowerShell\v1.0\powershell.exe -NoProfile -ExecutionPolicy Bypass -File ' . escapeshellarg($scriptPath);
    exec($cmd, $output, $code);
    @unlink($scriptPath);
    return $code === 0;
}

function psQuote(string $value): string
{
    return str_replace("'", "''", $value);
}
