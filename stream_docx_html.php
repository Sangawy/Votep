<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Content-Type: application/json");
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!isset($_GET['f'])) {
    header("Content-Type: application/json");
    echo json_encode(['error' => 'No file path provided']);
    exit;
}

// Decode the path (same encoding as JS: TextEncoder -> base64)
$file = base64_decode($_GET['f']);
$file = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $file);

if (strpos($file, '..') !== false || !file_exists($file)) {
    header("Content-Type: application/json");
    echo json_encode(['error' => 'File not found: ' . $file]);
    exit;
}

$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
if ($ext !== 'docx') {
    header("Content-Type: application/json");
    echo json_encode(['error' => 'Not a docx file']);
    exit;
}

if (!class_exists('ZipArchive')) {
    header("Content-Type: application/json");
    echo json_encode(['error' => 'ZipArchive not available']);
    exit;
}

$zip = new ZipArchive();
if ($zip->open($file) !== true) {
    header("Content-Type: application/json");
    echo json_encode(['error' => 'Cannot open docx file']);
    exit;
}

$xml = $zip->getFromName('word/document.xml');
$zip->close();

if ($xml === false) {
    header("Content-Type: application/json");
    echo json_encode(['error' => 'Cannot read document.xml from docx']);
    exit;
}

// Convert OOXML to simple HTML
$html = convertDocxXmlToHtml($xml);

header("Content-Type: application/json");
echo json_encode(['html' => $html]);
exit;

function convertDocxXmlToHtml(string $xml): string {
    // Register namespaces
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadXML($xml);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

    $html = '';
    $body = $xpath->query('//w:body');
    if (!$body || $body->length === 0) {
        return '<p>هیچ ناوەڕۆکێک نەدۆزرایەوە.</p>';
    }

    // Process paragraphs and tables
    $children = $body->item(0)->childNodes;
    foreach ($children as $child) {
        $localName = $child->localName;

        if ($localName === 'p') {
            $html .= processParagraph($xpath, $child);
        } elseif ($localName === 'tbl') {
            $html .= processTable($xpath, $child);
        }
    }

    return $html ?: '<p>هیچ ناوەڕۆکێک نەدۆزرایەوە.</p>';
}

function processParagraph(DOMXPath $xpath, DOMNode $para): string {
    // Check alignment
    $jc = $xpath->query('.//w:pPr/w:jc/@w:val', $para);
    $align = 'right'; // default RTL
    if ($jc && $jc->length > 0) {
        $val = $jc->item(0)->nodeValue;
        if ($val === 'center') $align = 'center';
        elseif ($val === 'left') $align = 'left';
        elseif ($val === 'both') $align = 'justify';
    }

    // Check if bold heading (numPr or large font)
    $numPr = $xpath->query('.//w:pPr/w:numPr', $para);
    $isList = $numPr && $numPr->length > 0;

    $text = '';
    $runs = $xpath->query('.//w:r', $para);
    foreach ($runs as $run) {
        // Check run properties
        $bold = $xpath->query('.//w:rPr/w:b', $run);
        $isBold = $bold && $bold->length > 0;

        $tNodes = $xpath->query('.//w:t', $run);
        foreach ($tNodes as $t) {
            $content = htmlspecialchars($t->nodeValue, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $text .= $isBold ? '<strong>' . $content . '</strong>' : $content;
        }

        // Line breaks
        $brs = $xpath->query('.//w:br', $run);
        foreach ($brs as $br) {
            $text .= '<br>';
        }
    }

    if (trim(strip_tags($text)) === '') {
        return '<p style="margin:4px 0;">&nbsp;</p>';
    }

    $prefix = $isList ? '• ' : '';
    return '<p style="margin:6px 0; text-align:' . $align . '; direction:rtl;">' . $prefix . $text . '</p>';
}

function processTable(DOMXPath $xpath, DOMNode $tbl): string {
    $html = '<table style="width:100%; border-collapse:collapse; margin:12px 0; direction:rtl;">';

    $rows = $xpath->query('.//w:tr', $tbl);
    if (!$rows) return '';

    foreach ($rows as $row) {
        $html .= '<tr>';
        $cells = $xpath->query('.//w:tc', $row);
        foreach ($cells as $cell) {
            $cellText = '';
            $paras = $xpath->query('.//w:p', $cell);
            foreach ($paras as $p) {
                $runs = $xpath->query('.//w:t', $p);
                foreach ($runs as $t) {
                    $cellText .= htmlspecialchars($t->nodeValue, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
            }
            $html .= '<td style="border:1px solid #ccc; padding:6px 10px; text-align:right;">' . ($cellText ?: '&nbsp;') . '</td>';
        }
        $html .= '</tr>';
    }

    $html .= '</table>';
    return $html;
}
