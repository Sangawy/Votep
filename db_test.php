<?php
// تاقیکردنەوەی داتابەیس
header('Content-Type: text/html; charset=utf-8');
echo '<html dir="rtl"><head><title>تاقیکردنەوەی داتابەیس</title>';
echo '<style>body{font-family:Tahoma,Arial,sans-serif;background:#f5f5f5;margin:20px;line-height:1.6;color:#333}';
echo 'pre{background:#fff;border:1px solid #ddd;padding:15px;border-radius:5px;direction:rtl;text-align:right}</style></head>';
echo '<body><h2>تاقیکردنەوەی داتابەیس</h2><pre>';

require_once 'config.php'; // دەستکاری مەکە؛ $pdo لێرە دروست دەکرێت

function line($text) {
    echo $text . "\n";
}

function tableExists(PDO $pdo, string $table): bool {
    $sql = "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

line('—— تاقیکردنەوەی داتابەیس ——');

try {
    // پەیوەندی
    $serverVersion = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
    line('پەیوەندی بە داتابەیس: سەرکەوتوو');
    line('وەشانی MySQL: ' . $serverVersion);

    // تاقیکردنەوەی سادە
    $stmt = $pdo->query('SELECT 1');
    $val = (int)$stmt->fetchColumn();
    line('SELECT 1 بەخێرایی کارکرد: ' . ($val === 1 ? 'باشە' : 'هەڵە'));

    // پشکنینی خشتەکان
    $tables = ['observers', 'voters'];
    foreach ($tables as $t) {
        $exists = tableExists($pdo, $t);
        line("خشتە '$t': " . ($exists ? 'هه‌یه‌' : 'بوونی نییە'));
    }

    // ژمێری خاڵەکان لە خشتەی voters
    if (tableExists($pdo, 'voters')) {
        $count = (int)$pdo->query('SELECT COUNT(*) FROM voters')->fetchColumn();
        line('ژمارەی ڕیزەکان لە خشتەی voters: ' . $count);
    }

    line('— تەواو —');
} catch (Throwable $e) {
    line('هەڵە لە تاقیکردنەوە: ' . $e->getMessage());
}
echo '</pre></body></html>';