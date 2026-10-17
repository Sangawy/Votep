<?php
/**
 * Pirxdry Express Advanced Live Deployment & Backup Panel - PRO MASTER EDITION
 * دیزاینی پریمیۆم و کارکردنی جێگیر بۆ هاوکاتکردنی خێرا و پشتیوانیکردنی تەواوی پڕۆژە
 */

// Start session to support admin verification
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Safety Check: Allow only Localhost or Authenticated Admin
$isCli = (php_sapi_name() === 'cli');
$isLocalhost = $isCli || 
               (isset($_SERVER['REMOTE_ADDR']) && in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1', 'localhost'])) || 
               (isset($_SERVER['HTTP_HOST']) && (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '.test') !== false));

$isAdmin = isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['admin', 'super_admin', 'director']);

if (!$isLocalhost && !$isAdmin) {
    header("HTTP/1.1 403 Forbidden");
    die("<div style='font-family:sans-serif; text-align:center; padding:50px;'><h2>Forbidden (403)</h2><p>This developer utility is only accessible locally or by authenticated system administrators.</p></div>");
}

// Ensure backups directory exists
$backupsDir = __DIR__ . DIRECTORY_SEPARATOR . 'backups';
if (!file_exists($backupsDir)) {
    mkdir($backupsDir, 0777, true);
}

// Helper: Synchronize with GitHub Repository (Sangawy/pirxdry)
function deployer_git_push($commitMessage = 'Auto-Deploy & Sync', ?callable $logger = null) {
    $repoDir = __DIR__;
    $log = function($msg, $type = 'info') use ($logger) {
        if ($logger) {
            $logger($msg, $type);
        }
    };

    $log("\n🐙 [GITHUB] دەستپێکردنی هاوتاکردن لەگەڵ کۆگای GitHub (Sangawy/pirxdry)...", "status");

    // Check if git is available
    exec("git --version 2>&1", $verOut, $verCode);
    if ($verCode !== 0) {
        $log("⚠️ [GITHUB] فەرمانی Git نەدۆزرایەوە لە سیستەمدا.", "error");
        return false;
    }

    putenv("GIT_TERMINAL_PROMPT=0");

    // 1. Auto-dump latest Database Structure to database/pirxdry_db_schema.sql
    try {
        $dbDir = $repoDir . DIRECTORY_SEPARATOR . 'database';
        if (!file_exists($dbDir)) {
            @mkdir($dbDir, 0777, true);
        }
        $schemaFile = $dbDir . DIRECTORY_SEPARATOR . 'pirxdry_db_schema.sql';
        exec("mysqldump --no-data -u root pirxdry_db > " . escapeshellarg($schemaFile) . " 2>&1", $dumpOut, $dumpCode);
        if ($dumpCode === 0 && file_exists($schemaFile) && filesize($schemaFile) > 0) {
            $log("🗄️ [GITHUB] ستراکچەری داتابەیس نوێکرایەوە لە database/pirxdry_db_schema.sql", "info");
        }
    } catch (Throwable $e) {
        // Continue if mysqldump fails
    }

    // 2. Stage all changes
    $log("📦 [GITHUB] قۆناغبەندیکردنی فایلە نوێ و گۆڕدراوەکان (git add -A)...", "info");
    exec("git -C " . escapeshellarg($repoDir) . " add -A 2>&1", $addOut, $addCode);

    // 3. Check status
    exec("git -C " . escapeshellarg($repoDir) . " status --porcelain 2>&1", $statusOut, $statusCode);
    if (empty($statusOut)) {
        $log("ℹ️ [GITHUB] هیچ فایلێکی نوێ یان گۆڕاو نییە بۆ ناردن (کۆگای گیت‌هەب بەتەواوی هاوتایە).", "info");
        return true;
    }

    // 4. Commit
    $log("💾 [GITHUB] پاشەکەوتکردنی کۆمیت: \"{$commitMessage}\"...", "info");
    $commitCmd = "git -C " . escapeshellarg($repoDir) . " commit -m " . escapeshellarg($commitMessage) . " 2>&1";
    exec($commitCmd, $commitOut, $commitCode);
    if ($commitCode !== 0) {
        $log("⚠️ [GITHUB] کۆمیتکردن سەرکەوتوو نەبوو: " . implode(" ", $commitOut), "error");
        return false;
    }

    // 5. Push
    $log("🚀 [GITHUB] ناردنی فایلەکان بۆ گیت‌هەب (git push origin main)...", "status");
    $pushCmd = "git -C " . escapeshellarg($repoDir) . " push origin main 2>&1";
    $process = proc_open($pushCmd, [
        1 => ["pipe", "w"],
        2 => ["pipe", "w"]
    ], $pipes);

    $pushLog = "";
    $sanitize = function($text) {
        return preg_replace('/:[a-zA-Z0-9_\-\.]+@github\.com/', '@github.com', $text);
    };

    if (is_resource($process)) {
        while ($line = fgets($pipes[1])) {
            $pushLog .= $sanitize($line);
            $lineTrim = trim($sanitize($line));
            if ($lineTrim !== '') {
                $log("   " . $lineTrim, "output");
            }
        }
        while ($line = fgets($pipes[2])) {
            $pushLog .= $sanitize($line);
            $lineTrim = trim($sanitize($line));
            if ($lineTrim !== '') {
                $log("   " . $lineTrim, "output");
            }
        }
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode === 0) {
            $log("✅ [GITHUB] سەرکەوتوو بوو! هەموو فایلەکان بە سەرکەوتوویی گەیشتنە سەر GitHub (Sangawy/pirxdry) 🚀", "success");
            return true;
        } else {
            $log("❌ [GITHUB] کێشە لە Push بۆ سەر گیت‌هەب: " . trim($pushLog), "error");
            return false;
        }
    }

    return false;
}

// 2. Handle Actions
if (isset($_GET['action'])) {
    // Prevent execution timeout & memory exhaustion
    set_time_limit(0); // Unlimited execution time
    ini_set('memory_limit', '2048M');

    $action = $_GET['action'];

    if ($action === 'sync_stream') {
        // Stream output in real-time using SSE (Server-Sent Events)
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        
        while (ob_get_level()) {
            ob_end_clean();
        }

        function send_log($message, $type = 'output') {
            echo "data: " . json_encode(['type' => $type, 'text' => $message], JSON_UNESCAPED_UNICODE) . "\n\n";
            flush();
        }

        $target = isset($_GET['target']) ? trim($_GET['target']) : '';
        $isMemberOnly = ($target === 'member_app');

        if ($isMemberOnly) {
            send_log("🔔 دەستپێکردنی هاوتاکردنی ئەپی ئەندامان (member_app) بۆ سەر سێرڤەر...\n", "status");
            
            // Deploy Web Disk Target member_app
            send_log("☁️ گواستنەوەی فایلەکانی ئەپی ئەندامان بۆ Web Disk (/public_html/member_app)...", "info");
            $forceFullParam = (isset($_GET['force_full']) && $_GET['force_full'] === '1') ? ' -ForceFull' : '';
            $deployCmd = "powershell.exe -ExecutionPolicy Bypass -File \"" . __DIR__ . "/Deploy_WebDisk.ps1\"$forceFullParam -TargetFolder member_app 2>&1";

            $process = proc_open($deployCmd, [
                1 => ["pipe", "w"],
                2 => ["pipe", "w"]
            ], $pipes);

            if (is_resource($process)) {
                while ($line = fgets($pipes[1])) {
                    send_log($line, "output");
                }
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);
            } else {
                send_log("❌ Failed to initiate Deploy_WebDisk.ps1", "error");
            }

            // Auto-Push to GitHub
            $pushGit = !isset($_GET['push_github']) || $_GET['push_github'] === '1';
            if ($pushGit) {
                deployer_git_push("Auto-Deploy & Sync [Member App] - " . date('Y-m-d H:i:s'), 'send_log');
            }

            send_log("\n✅ هاوتاکردنی ئەپی ئەندامان بە سەرکەوتوویی تەواو بوو! لەم بەستەرە بەردەستە: www.pirxdry.org/member_app", "success");
            exit;
        }

        send_log("🔔 Starting live sync deployment process (Web App & Database)...\n", "status");
        
        // Deploy Web Disk Root
        send_log("☁️ گواستنەوەی فایلە نوێ و دەستکاریکراوەکان بۆ cPanel Web Disk (WebDAV)...", "info");
        $forceFullParam = (isset($_GET['force_full']) && $_GET['force_full'] === '1') ? ' -ForceFull' : '';
        $deployCmd = "powershell.exe -ExecutionPolicy Bypass -File \"" . __DIR__ . "/Deploy_WebDisk.ps1\"$forceFullParam 2>&1";
        
        $process = proc_open($deployCmd, [
            1 => ["pipe", "w"],
            2 => ["pipe", "w"]
        ], $pipes);

        if (is_resource($process)) {
            while ($line = fgets($pipes[1])) {
                send_log($line, "output");
            }
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
        } else {
            send_log("❌ Failed to initiate Deploy_WebDisk.ps1", "error");
        }

        // Auto-Push to GitHub
        $pushGit = !isset($_GET['push_github']) || $_GET['push_github'] === '1';
        if ($pushGit) {
            deployer_git_push("Auto-Deploy & Sync [Web App & DB] - " . date('Y-m-d H:i:s'), 'send_log');
        }

        send_log("\n✅ هاوتاکردنی پڕۆژە بە سەرکەوتوویی تەواو بوو! سێرڤەری هۆست و GitHub تەواو نوێن.", "success");
        exit;
    }

    if ($action === 'get_web_status') {
        header('Content-Type: application/json; charset=utf-8');
        $source = __DIR__;
        $lastSyncFile = __DIR__ . '/config/last_sync.txt';
        $lastSyncTime = null;
        if (file_exists($lastSyncFile)) {
            $lastSyncRaw = trim(file_get_contents($lastSyncFile));
            $lastSyncRaw = preg_replace('/^\xEF\xBB\xBF/', '', $lastSyncRaw);
            $parsed = strtotime($lastSyncRaw);
            if ($parsed !== false) {
                $lastSyncTime = $parsed;
            }
        }
        
        $changedFiles = [];
        $totalFiles = 0;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $subPath = substr($item->getPathname(), strlen($source) + 1);
                $relNorm = str_replace('\\', '/', $subPath);

                // Exclude system and irrelevant files
                if (preg_match('/^(uploads|backups|updates|setup_files|\.git|\.vscode|\.agents|node_modules|scratch)/i', $relNorm)) {
                    continue;
                }
                if (preg_match('/(\.zip|\.zip\..*|\.tar\.gz|\.tmp|\.log)$/i', $relNorm)) {
                    continue;
                }

                $totalFiles++;
                if ($lastSyncTime === null || $item->getMTime() > $lastSyncTime) {
                    $changedFiles[] = [
                        'path' => $relNorm,
                        'size' => round($item->getSize() / 1024, 1) . ' KB',
                        'time' => date('Y-m-d H:i:s', $item->getMTime())
                    ];
                }
            }
        }

        echo json_encode([
            'success' => true,
            'source' => $source,
            'last_sync' => $lastSyncTime ? date('Y-m-d H:i:s', $lastSyncTime) : 'Never / هێشتا نەکراوە',
            'total_files' => $totalFiles,
            'changed_count' => count($changedFiles),
            'files' => array_slice($changedFiles, 0, 100)
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'get_member_status') {
        header('Content-Type: application/json; charset=utf-8');
        $memberSource = __DIR__ . '/member_app';
        $lastSyncFile = __DIR__ . '/config/last_sync_member_app.txt';
        $lastSyncTime = null;
        if (file_exists($lastSyncFile)) {
            $lastSyncRaw = trim(file_get_contents($lastSyncFile));
            $lastSyncRaw = preg_replace('/^\xEF\xBB\xBF/', '', $lastSyncRaw);
            $parsed = strtotime($lastSyncRaw);
            if ($parsed !== false) {
                $lastSyncTime = $parsed;
            }
        }
        
        $changedFiles = [];
        $totalFiles = 0;
        if (file_exists($memberSource)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($memberSource, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $item) {
                if ($item->isFile()) {
                    $subPath = substr($item->getPathname(), strlen($memberSource) + 1);
                    if (preg_match('/^(uploads|tests|\.git|\.vscode|node_modules|scratch)/i', $subPath)) {
                        continue;
                    }
                    if (preg_match('/(\.zip|\.tar\.gz|\.tmp|\.log)$/i', $subPath)) {
                        continue;
                    }
                    $totalFiles++;
                    if ($lastSyncTime === null || $item->getMTime() > $lastSyncTime) {
                        $changedFiles[] = [
                            'path' => str_replace('\\', '/', $subPath),
                            'size' => round($item->getSize() / 1024, 1) . ' KB',
                            'time' => date('Y-m-d H:i:s', $item->getMTime())
                        ];
                    }
                }
            }
        }
        echo json_encode([
            'success' => true,
            'source' => $memberSource,
            'exists' => file_exists($memberSource),
            'last_sync' => $lastSyncTime ? date('Y-m-d H:i:s', $lastSyncTime) : 'Never / هێشتا نەکراوە',
            'total_files' => $totalFiles,
            'changed_count' => count($changedFiles),
            'files' => array_slice($changedFiles, 0, 50)
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'git_push_stream') {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');

        while (ob_get_level()) {
            ob_end_clean();
        }

        function send_log($message, $type = 'output') {
            echo "data: " . json_encode(['type' => $type, 'text' => $message], JSON_UNESCAPED_UNICODE) . "\n\n";
            flush();
        }

        send_log("🔔 دەستپێکردنی ڕەوانەکردنی ڕاستەوخۆ بۆ سەر کۆگای GitHub (Sangawy/pirxdry)...\n", "status");
        $pushRes = deployer_git_push("Manual Push via Deployer - " . date('Y-m-d H:i:s'), 'send_log');
        if ($pushRes) {
            send_log("\n🏁 پڕۆسەی گیت‌هەب بە سەرکەوتوویی تەواو بوو! فایلی نوێ لەسەر گیت‌هەب تۆمارکرا.", "success");
        } else {
            send_log("\n⚠️ پڕۆسەی گیت‌هەب تەواو بوو.", "status");
        }
        exit;
    }

    if ($action === 'backup_stream') {
        // Stream database and file backup progress recursively with SSE
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        
        while (ob_get_level()) {
            ob_end_clean();
        }

        function send_progress($percent, $message, $type = 'progress') {
            echo "data: " . json_encode(['type' => $type, 'percent' => $percent, 'text' => $message], JSON_UNESCAPED_UNICODE) . "\n\n";
            flush();
        }

        send_progress(5, "🔔 Starting full consolidated backup...", "status");

        // Step 1: Export Database Structure & Data to SQL
        send_progress(10, "🗄️ [1/3] پەیوەستبوون بە داتابەیس و هەناردەکردنی خشتەکان...", "info");
        try {
            $host = '127.0.0.1';
            $port = '3306';
            $dbname = 'pirxdry_db';
            $username = 'root';
            $password = '';

            try {
                $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
                $pdo = new PDO($dsn, $username, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]);
            } catch (PDOException $e) {
                // If 127.0.0.1 failed, attempt standard localhost as fallback
                $host = 'localhost';
                $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
                $pdo = new PDO($dsn, $username, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]);
            }

            $dateStamp = date('Y-m-d_H-i-s');
            $sqlFilename = "db_backup_{$dateStamp}.sql";
            $sqlFilePath = $backupsDir . DIRECTORY_SEPARATOR . $sqlFilename;
            
            $zipFilename = "Pirxdry_Full_Backup_{$dateStamp}.zip";
            $zipFilePath = $backupsDir . DIRECTORY_SEPARATOR . $zipFilename;

            $sql = "-- Pirxdry Express Database Backup\n";
            $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
            $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $totalTables = count($tables);

            if ($totalTables === 0) {
                throw new Exception("هیچ خشتەیەک لە داتابەیسدا نەدۆزرایەوە.");
            }

            foreach ($tables as $index => $table) {
                $dbPercent = 10 + round(($index / $totalTables) * 15);
                send_progress($dbPercent, "هەناردەکردنی خشتەی: `{$table}`...");

                $sql .= "-- Table: `{$table}`\n";
                $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
                
                $createStmt = $pdo->query("SHOW CREATE TABLE `{$table}`");
                $createRow = $createStmt->fetch(PDO::FETCH_NUM);
                $sql .= $createRow[1] . ";\n\n";

                $dataStmt = $pdo->query("SELECT * FROM `{$table}`");
                $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

                if (count($rows) > 0) {
                    $sql .= "INSERT INTO `{$table}` VALUES \n";
                    $inserts = [];
                    foreach ($rows as $row) {
                        $values = [];
                        foreach ($row as $key => $val) {
                            if ($val === null) {
                                $values[] = "NULL";
                            } else {
                                $values[] = $pdo->quote($val);
                            }
                        }
                        $inserts[] = "(" . implode(", ", $values) . ")";
                    }
                    $sql .= implode(",\n", $inserts) . ";\n\n";
                }
            }
            $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
            file_put_contents($sqlFilePath, $sql);
            send_progress(25, "🗄️ داتابەیس بە سەرکەوتوویی پاشەکەوت کرا!", "info");
        } catch (Throwable $e) {
            send_progress(25, "❌ داتابەیس باکئەپ نەکرا: " . $e->getMessage(), "error");
            exit;
        }

        // Step 2: Scan and Filter Project Files
        send_progress(30, "🔍 [2/3] خاوێنکردنەوە و فلتەرکردنی فایلەکان و لابردنی فۆڵدەرە کاتییەکان...", "info");
        
        $sourcePath = __DIR__;
        $fileList = [];
        
        $excludedDirNames = [
            '.git', '.vscode', '.agents', '.gemini', 'backups', 'uploads', 'updates', 'setup_files',
            'node_modules', 'temp', 'tmp', 'scratch'
        ];

        $excludedExtensions = [
            'zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'iso',
            'sql', // Fresh DB dump will be added to root of zip
            'log', 'tmp', 'bak', 'backup'
        ];

        $excludedFilenames = [
            '.ds_store', 'thumbs.db', 'desktop.ini'
        ];

        $dirIterator = new RecursiveDirectoryIterator(
            $sourcePath,
            RecursiveDirectoryIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS
        );

        $filterIterator = new RecursiveCallbackFilterIterator($dirIterator, function ($current) use ($excludedDirNames, $excludedExtensions, $excludedFilenames) {
            $filename = $current->getFilename();
            $lowerFilename = strtolower($filename);

            if ($current->isDir()) {
                if (in_array($lowerFilename, $excludedDirNames, true)) {
                    return false;
                }
                if (strpos($lowerFilename, '.tmp') === 0 || strpos($lowerFilename, 'upload') !== false) {
                    return false;
                }
                return true;
            }

            if (in_array($lowerFilename, $excludedFilenames, true)) {
                return false;
            }

            if (strpos($filename, '~') === 0 || strpos($lowerFilename, '.tmp') === 0) {
                return false;
            }

            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (in_array($ext, $excludedExtensions, true)) {
                return false;
            }

            return true;
        });

        $files = new RecursiveIteratorIterator($filterIterator, RecursiveIteratorIterator::LEAVES_ONLY);

        foreach ($files as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($sourcePath) + 1);
                $relativePath = str_replace('\\', '/', $relativePath);

                $fileList[] = [
                    'full' => $filePath,
                    'relative' => $relativePath
                ];
            }
        }

        $totalFiles = count($fileList);
        send_progress(35, "🔍 ژمارەی فایلە شیاوەکان بۆ باکئەپی ZIP: {$totalFiles} فایل", "info");

        // Step 3: Fast Single-Pass ZIP Compression
        try {
            $zip = new ZipArchive();
            if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new Exception("Unable to create ZIP archive at: " . $zipFilename);
            }

            // Add database SQL first
            if (file_exists($sqlFilePath)) {
                $zip->addFile($sqlFilePath, $sqlFilename);
            }

            $chunkSize = max(10, min(50, intval($totalFiles / 30)));

            for ($i = 0; $i < $totalFiles; $i++) {
                $f = $fileList[$i];
                $zip->addFile($f['full'], "ProjectFiles/" . $f['relative']);
                
                if ($i % $chunkSize === 0 || $i === $totalFiles - 1) {
                    $zipPercent = 35 + round(($i / $totalFiles) * 59);
                    $fileNum = $i + 1;
                    send_progress($zipPercent, "پەستاندنی فایلی {$fileNum} / {$totalFiles}: " . basename($f['relative']));
                }
            }

            send_progress(95, "💾 پاشەکەوتکردنی فایلی ZIP لەسەر دیسک...", "info");
            $closeResult = $zip->close();

            if (!$closeResult) {
                throw new Exception("ZipArchive failed to close and finalize on disk.");
            }

            // Save database SQL file into database/pirxdry_db_backup.sql for Git version control
            if (file_exists($sqlFilePath)) {
                $dbDir = __DIR__ . DIRECTORY_SEPARATOR . 'database';
                if (!file_exists($dbDir)) {
                    @mkdir($dbDir, 0777, true);
                }
                @copy($sqlFilePath, $dbDir . DIRECTORY_SEPARATOR . 'pirxdry_db_backup.sql');
                unlink($sqlFilePath);
            }

            if (!file_exists($zipFilePath)) {
                throw new Exception("ZIP archive file not found after closing.");
            }

            $finalSize = round(filesize($zipFilePath) / (1024 * 1024), 2) . ' MB';
            send_progress(96, "📦 باکئەپی لۆکاڵ تەواو بوو ({$finalSize}). ئێستا دەنێردرێت بۆ سەر GitHub...", "info");

            // Push to GitHub
            deployer_git_push("Consolidated Database & Code Backup - " . date('Y-m-d H:i:s'), function($msg, $type) {
                send_progress(98, $msg, $type);
            });

            send_progress(100, "🎉 باکئەپ بە سەرکەوتوویی دروستکرا و لەسەر GitHub (Sangawy/pirxdry) پاشەکەوت کرا!", "success");
            exit;

        } catch (Throwable $e) {
            send_progress(95, "❌ دروستکردنی ZIP شکستی هێنا: " . $e->getMessage(), "error");
            if (isset($sqlFilePath) && file_exists($sqlFilePath)) {
                @unlink($sqlFilePath);
            }
            exit;
        }
    }

    if ($action === 'list_backups') {
        header('Content-Type: application/json');
        $backups = [];
        $files = glob($backupsDir . DIRECTORY_SEPARATOR . '*.zip');
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        foreach ($files as $file) {
            $backups[] = [
                'name' => basename($file),
                'size' => round(filesize($file) / (1024 * 1024), 2) . ' MB',
                'date' => date('Y-m-d H:i:s', filemtime($file))
            ];
        }

        echo json_encode(['success' => true, 'backups' => $backups]);
        exit;
    }

    if ($action === 'download' && isset($_GET['file'])) {
        $filename = basename($_GET['file']);
        $filePath = $backupsDir . DIRECTORY_SEPARATOR . $filename;
        if (file_exists($filePath)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($filePath));
            readfile($filePath);
            exit;
        } else {
            die("File not found.");
        }
    }

    if ($action === 'delete' && isset($_GET['file'])) {
        header('Content-Type: application/json');
        $filename = basename($_GET['file']);
        $filePath = $backupsDir . DIRECTORY_SEPARATOR . $filename;
        if (file_exists($filePath) && unlink($filePath)) {
            echo json_encode(['success' => true, 'message' => 'فایلی باکئەپ بە سەرکەوتوویی سڕایەوە.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'فایلەکە نەدۆزرایەوە یان سڕینەوەی سەرکەوتوو نەبوو.']);
        }
        exit;
    }

    if ($action === 'save_webdisk_pass' && isset($_POST['password'])) {
        @ini_set('display_errors', '0');
        error_reporting(0);
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json; charset=utf-8');
        $pass = trim($_POST['password']);
        $configFile = __DIR__ . '/config/webdisk_config.json';
        
        $cfg = [];
        if (file_exists($configFile)) {
            $cfg = json_decode(file_get_contents($configFile), true);
            if (!is_array($cfg)) {
                $cfg = [];
            }
        }
        
        $url  = isset($_POST['url']) ? trim($_POST['url']) : ($cfg['webdisk_url'] ?? 'https://185.181.111.127:2078');
        $user = isset($_POST['username']) ? trim($_POST['username']) : ($cfg['webdisk_user'] ?? 'pirxdry@pirxdry.org');
        $path = isset($_POST['path']) ? trim($_POST['path'], '/ ') : ($cfg['remote_path'] ?? '');
        
        // Test connection with credentials before saving
        $urlTrimmed = rtrim($url, '/');
        $testUrl = ($path !== '') ? $urlTrimmed . '/' . $path : $urlTrimmed . '/';
        
        $connOk = false;
        $connMsg = '';
        
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $testUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PROPFIND');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Basic ' . base64_encode($user . ':' . $pass),
                'Depth: 0'
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            if (PHP_VERSION_ID < 80500 && function_exists('curl_close')) {
                @curl_close($ch);
            }
            
            if ($error) {
                $connMsg = 'شکست لە کۆنێکت بوون بە سێرڤەر (cURL): ' . $error;
            } elseif ($httpCode === 401) {
                $connMsg = 'پاسوورد یان یوزەرنەیمەکە هەڵەیە! (401 Unauthorized)';
            } elseif ($httpCode === 403) {
                $connMsg = 'ڕێگەپێنەدراو! (403 Forbidden)';
            } elseif (($httpCode >= 200 && $httpCode < 400) || $httpCode === 405 || $httpCode === 404) {
                $connOk = true;
            } else {
                $connMsg = 'سێرڤەر وەڵامی دایەوە بە کۆدی هەڵەی: ' . $httpCode;
            }
        } else {
            $auth = base64_encode($user . ':' . $pass);
            $context = stream_context_create([
                'http' => [
                    'method' => 'PROPFIND',
                    'header' => [
                        "Authorization: Basic $auth",
                        "Depth: 0"
                    ],
                    'timeout' => 8,
                    'ignore_errors' => true
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ]);
            
            $response = @file_get_contents($testUrl, false, $context);
            if ($response === false) {
                $error = error_get_last();
                $connMsg = 'پەیوەندی شکستی هێنا: ' . ($error['message'] ?? 'Unknown stream error');
            } else {
                $respHeaders = function_exists('http_get_last_response_headers') ? http_get_last_response_headers() : ($GLOBALS['http_response_header'] ?? []);
                if (!empty($respHeaders)) {
                    preg_match('{HTTP\/\S*\s(\d+)}', $respHeaders[0], $matches);
                    $status = isset($matches[1]) ? (int)$matches[1] : 0;
                    if ($status === 401) {
                        $connMsg = 'پاسوورد یان یوزەرنەیمەکە هەڵەیە! (401 Unauthorized)';
                    } elseif ($status === 403) {
                        $connMsg = 'ڕێگەپێنەدراو! (403 Forbidden)';
                    } elseif (($status >= 200 && $status < 400) || $status === 405 || $status === 404) {
                        $connOk = true;
                    } else {
                        $connMsg = 'سێرڤەر وەڵامی دایەوە بە کۆدی هەڵەی: ' . $status;
                    }
                } else {
                    $connOk = true;
                }
            }
        }
        
        if (!$connOk) {
            echo json_encode(['success' => false, 'message' => 'کۆنێکت نەبوو! ' . $connMsg], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // Save
        if (file_exists($configFile) || is_writable(dirname($configFile))) {
            $cfg['webdisk_url'] = $url;
            $cfg['webdisk_user'] = $user;
            $cfg['webdisk_pass'] = $pass;
            $cfg['remote_path'] = $path;
            if (file_put_contents($configFile, json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false) {
                echo json_encode(['success' => true, 'message' => 'پەیوەندی بە سەرکەوتوویی تاقیکرایەوە و زانیارییەکان پاشەکەوت کران!'], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['success' => false, 'message' => 'شکست لە پاشەکەوتکردنی فایلی config.json (نووسین ڕێگەپێنەدراوە)'], JSON_UNESCAPED_UNICODE);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'فایلی config نەدۆزرایەوە یان دەستکاری ناکرێت'], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ckb" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pirxdry Express Developer Panel (WebDAV Sync & Backup)</title>
    
    <!-- Premium Google Fonts & CSS Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --glass-bg: rgba(255, 255, 255, 0.85);
            --primary: #4f46e5;
            --primary-glow: rgba(79, 70, 229, 0.35);
            --secondary: #8b5cf6;
            --secondary-glow: rgba(139, 92, 246, 0.35);
            --success: #10b981;
            --dark: #0f172a;
            --terminal-bg: #090d16;
            --terminal-border: #1e293b;
        }

        body {
            background: linear-gradient(135deg, #f3f4f6 0%, #e2e8f0 100%);
            font-family: 'Outfit', 'Segoe UI', Tahoma, sans-serif;
            min-height: 100vh;
            color: #1e293b;
            padding: 30px 15px;
        }

        .panel-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Glassmorphic Premium Header */
        .glass-header {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            border-radius: 24px;
            padding: 35px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.6);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-title h1 {
            font-weight: 700;
            font-size: 28px;
            margin-bottom: 8px;
            background: linear-gradient(to right, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .header-title p {
            color: #64748b;
            font-size: 14px;
            margin-bottom: 0;
        }

        .status-badge {
            background: #fff;
            padding: 8px 16px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            border: 1px solid #e2e8f0;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            background-color: var(--success);
            border-radius: 50%;
            display: inline-block;
            box-shadow: 0 0 10px var(--success);
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(0.9); opacity: 0.7; }
            50% { transform: scale(1.15); opacity: 1; }
            100% { transform: scale(0.9); opacity: 0.7; }
        }

        /* Action Cards Grid */
        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .action-card {
            background: #ffffff;
            border-radius: 24px;
            padding: 35px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
            border: 1px solid #f1f5f9;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .action-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
        }

        .action-card.sync {
            border-bottom: 5px solid var(--primary);
        }

        .action-card.backup {
            border-bottom: 5px solid var(--secondary);
        }

        .icon-box {
            width: 65px;
            height: 65px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 25px;
        }

        .sync .icon-box {
            background: rgba(79, 70, 229, 0.1);
            color: var(--primary);
        }

        .backup .icon-box {
            background: rgba(139, 92, 246, 0.1);
            color: var(--secondary);
        }

        .action-card h3 {
            font-weight: 700;
            font-size: 20px;
            margin-bottom: 12px;
            color: #0f172a;
        }

        .action-card p {
            font-size: 14px;
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 30px;
        }

        .btn-premium {
            border-radius: 14px;
            padding: 12px 24px;
            font-weight: 600;
            font-size: 15px;
            border: none;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
        }

        .btn-sync {
            background: var(--primary);
            color: #fff;
            box-shadow: 0 4px 15px var(--primary-glow);
        }

        .btn-sync:hover {
            background: #4338ca;
            box-shadow: 0 8px 25px var(--primary-glow);
            transform: scale(1.02);
            color: #fff;
        }

        .btn-backup {
            background: var(--secondary);
            color: #fff;
            box-shadow: 0 4px 15px var(--secondary-glow);
        }

        .btn-backup:hover {
            background: #7c3aed;
            box-shadow: 0 8px 25px var(--secondary-glow);
            transform: scale(1.02);
            color: #fff;
        }

        /* Terminal Console */
        .terminal-container {
            background: var(--terminal-bg);
            border-radius: 24px;
            border: 1px solid var(--terminal-border);
            padding: 25px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
            margin-bottom: 30px;
            display: none;
        }

        .terminal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 15px;
            border-bottom: 1px solid #1e293b;
            margin-bottom: 15px;
        }

        .terminal-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
        }

        .terminal-body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 13px;
            color: #a7f3d0;
            height: 350px;
            overflow-y: auto;
            white-space: pre-wrap;
            scroll-behavior: smooth;
        }

        .terminal-body::-webkit-scrollbar {
            width: 8px;
        }

        .terminal-body::-webkit-scrollbar-track {
            background: #0b0f19;
        }

        .terminal-body::-webkit-scrollbar-thumb {
            background: #1e293b;
            border-radius: 4px;
        }

        /* History & Changed Cards */
        .history-card {
            background: #ffffff;
            border-radius: 24px;
            padding: 35px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
            border: 1px solid #f1f5f9;
        }

        .history-card h3 {
            font-weight: 700;
            font-size: 18px;
            color: #0f172a;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .backup-item {
            padding: 16px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .backup-item:last-child {
            border-bottom: none;
        }

        .backup-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .backup-icon {
            font-size: 26px;
            color: var(--secondary);
        }

        .backup-name {
            font-weight: 600;
            font-size: 14px;
            color: #334155;
        }

        .backup-meta {
            font-size: 12px;
            color: #94a3b8;
            margin-top: 3px;
        }

        .btn-circle {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            border: none;
        }

        .btn-dl {
            background: #f1f5f9;
            color: #475569;
        }

        .btn-dl:hover {
            background: var(--primary);
            color: #fff;
        }

        .btn-del {
            background: #fee2e2;
            color: #ef4444;
        }

        .btn-del:hover {
            background: #ef4444;
            color: #fff;
        }

        /* Progress Bar */
        .progress-container {
            background: var(--glass-bg);
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.03);
            border: 1px solid rgba(255,255,255,0.6);
            margin-bottom: 30px;
            display: none;
        }
        
        .progress-bar-custom {
            height: 22px;
            border-radius: 12px;
            background: #e2e8f0;
            overflow: hidden;
            position: relative;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.05);
        }

        .progress-fill {
            height: 100%;
            width: 0%;
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            transition: width 0.3s ease;
            border-radius: 12px;
        }
        
        /* Navigation Tabs Styling */
        .nav-pills-deployer {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            border-radius: 20px;
            padding: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.03);
            border: 1px solid rgba(255,255,255,0.7);
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
        }

        .nav-pills-deployer .nav-link {
            flex: 1;
            padding: 14px 20px;
            border-radius: 14px;
            color: #475569;
            font-weight: 700;
            font-size: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            border: 1.5px solid transparent;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            background: transparent;
        }

        .nav-pills-deployer .nav-link:hover {
            color: var(--primary);
            background: rgba(79, 70, 229, 0.05);
        }

        .nav-pills-deployer .nav-link.active {
            color: #fff !important;
            background: linear-gradient(135deg, var(--primary), var(--secondary)) !important;
            box-shadow: 0 4px 15px var(--primary-glow);
        }

        .nav-pills-deployer #tab-mobile-btn.active {
            background: linear-gradient(135deg, #06b6d4, #0891b2) !important;
            box-shadow: 0 4px 15px rgba(8, 145, 178, 0.35);
        }
    </style>
</head>
<body>

    <div class="panel-container">
        <!-- Premium Header -->
        <div class="glass-header">
            <div class="header-title">
                <h1>💎 پانێڵی پێشخستن و ئەپلۆدی Pirxdry Express</h1>
                <p>سیستەمی پریمیۆمی هاوکاتکردنی ڕاستەوخۆی فایلەکان و پاراستنی پڕۆژە</p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <a href="https://github.com/Sangawy/pirxdry" target="_blank" class="badge bg-dark text-white border text-decoration-none px-3 py-2 d-inline-flex align-items-center gap-2" style="border-radius: 12px; font-size: 13px; font-family: monospace;" title="کردنەوەی کۆگای گیت‌هەب">
                    <i class="bi bi-github text-warning fs-6"></i>
                    <span>Sangawy/pirxdry</span>
                    <span class="badge bg-success" style="font-size: 10px;">Connected</span>
                </a>
                <div class="status-badge">
                    <span class="status-dot"></span>
                    <span>سیستەم ئامادەیە</span>
                </div>
            </div>
        </div>

        <!-- Real-time Progress Bar -->
        <div class="progress-container" id="progressCard">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="h6 fw-bold mb-0" id="progressTitle">⏳ لە کاردایە... چاوەڕوان بن</h4>
                <span class="badge bg-primary rounded-pill fw-bold" id="progressPercentBadge">0%</span>
            </div>
            <div class="progress-bar-custom mb-3">
                <div class="progress-fill" id="progressFillBar"></div>
            </div>
            <div class="small text-muted font-monospace" id="progressStatusMessage">ئامادەکاری...</div>
        </div>

        <!-- Real-time Terminal Console Box -->
        <div class="terminal-container" id="terminalBox">
            <div class="terminal-header">
                <div class="d-flex align-items-center gap-2">
                    <span class="terminal-dot" style="background: #ef4444;"></span>
                    <span class="terminal-dot" style="background: #f59e0b;"></span>
                    <span class="terminal-dot" style="background: #10b981;"></span>
                    <span class="text-white small fw-bold ms-2 font-monospace">🚀 Real-time Deployment & Git Console</span>
                </div>
                <button class="btn btn-sm btn-outline-secondary text-white-50" onclick="document.getElementById('terminalBox').style.display='none'" style="font-size: 11px; border-radius: 8px;">داخستن</button>
            </div>
            <div class="terminal-body" id="terminalConsole"></div>
        </div>

        <!-- WebDAV Credentials Setup Card -->
        <?php
        $cfgPath = __DIR__ . '/config/webdisk_config.json';
        $cfg = file_exists($cfgPath) ? json_decode(file_get_contents($cfgPath), true) : [];
        $cfgUrl  = $cfg['webdisk_url']  ?? 'https://185.181.111.127:2078';
        $cfgUser = $cfg['webdisk_user'] ?? 'pirxdry@pirxdry.org';
        $cfgPass = $cfg['webdisk_pass'] ?? '';
        $cfgPath2= $cfg['remote_path']  ?? '';
        $passOk  = ($cfgPass !== '' && $cfgPass !== 'YOUR_PASSWORD_HERE');
        ?>
        <div style="background:white; border-radius:20px; padding:28px 32px; margin-bottom:24px; box-shadow:0 4px 20px rgba(0,0,0,0.05); border:2px solid <?= $passOk ? '#d1fae5' : '#fee2e2' ?>;">
            <div style="display:flex; align-items:center; gap:14px; margin-bottom:18px;">
                <div style="width:48px; height:48px; border-radius:14px; background:<?= $passOk ? 'rgba(16,185,129,0.1)' : 'rgba(239,68,68,0.1)' ?>; display:flex; align-items:center; justify-content:center; font-size:22px;">
                    <?= $passOk ? '🔓' : '🔐' ?>
                </div>
                <div>
                    <h3 style="font-size:16px; font-weight:700; margin:0; color:#0f172a;">ئەکاونتی WebDAV / cPanel</h3>
                    <p style="margin:2px 0 0; font-size:12px; color:<?= $passOk ? '#059669' : '#dc2626' ?>; font-weight:600;">
                        <?= $passOk ? '✅ پاسووردی هۆست داننراوە — هاوتاکردن ئامادەیە' : '⚠️ پاسووردی هۆست داننراوە نیە — sync کارناکات!' ?>
                    </p>
                </div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr 1.2fr 1fr auto; gap:12px; align-items:end;">
                <div>
                    <label style="font-size:11px; font-weight:600; color:#64748b; display:block; margin-bottom:5px;">🌐 WebDisk URL</label>
                    <input type="text" id="cfgUrl" value="<?= htmlspecialchars($cfgUrl) ?>" 
                           style="width:100%; border:1.5px solid #e2e8f0; border-radius:10px; padding:9px 12px; font-size:13px; font-family:monospace; background:#fff; color:#374151; outline:none;">
                </div>
                <div>
                    <label style="font-size:11px; font-weight:600; color:#64748b; display:block; margin-bottom:5px;">👤 یوزەرنەیم (cPanel)</label>
                    <input type="text" id="cfgUser" value="<?= htmlspecialchars($cfgUser) ?>" 
                           style="width:100%; border:1.5px solid #e2e8f0; border-radius:10px; padding:9px 12px; font-size:13px; font-family:monospace; background:#fff; color:#374151; outline:none;">
                </div>
                <div>
                    <label style="font-size:11px; font-weight:600; color:#64748b; display:block; margin-bottom:5px;">🔑 پاسووردی cPanel <span style="color:#dc2626;">*</span></label>
                    <input type="password" id="cfgPass" placeholder="پاسووردی cPanel بنووسە..." 
                           style="width:100%; border:1.5px solid <?= $passOk ? '#a7f3d0' : '#fca5a5' ?>; border-radius:10px; padding:9px 12px; font-size:13px; outline:none;">
                </div>
                <div>
                    <label style="font-size:11px; font-weight:600; color:#64748b; display:block; margin-bottom:5px;">📁 ڕێڕەوی هۆست (Remote Path)</label>
                    <input type="text" id="cfgPath" value="<?= htmlspecialchars($cfgPath2) ?>" placeholder="خاڵی جێبهێڵە ئەگەر پێویست نەبوو"
                           style="width:100%; border:1.5px solid #e2e8f0; border-radius:10px; padding:9px 12px; font-size:13px; font-family:monospace; background:#fff; color:#374151; outline:none;">
                </div>
                <div>
                    <button onclick="saveWebDiskPass()" 
                            style="background:linear-gradient(135deg,#10b981,#059669); color:white; border:none; border-radius:12px; padding:10px 22px; font-size:14px; font-weight:700; cursor:pointer; white-space:nowrap; box-shadow:0 4px 12px rgba(16,185,129,0.3);">
                        💾 پاشەکەوت
                    </button>
                </div>
            </div>
            <div id="cfgSaveMsg" style="margin-top:10px; font-size:13px; font-weight:600; display:none;"></div>
        </div>

        <!-- Navigation Tabs -->
        <ul class="nav nav-pills-deployer" id="deployerNavTabs" role="tablist">
            <li class="nav-item flex-fill" role="presentation">
                <button class="nav-link active w-100" id="tab-web-btn" data-bs-toggle="pill" data-bs-target="#tab-web" type="button" role="tab" onclick="loadWebStatus()">
                    <i class="bi bi-laptop"></i>
                    <span>دیپلۆمێنتی سەرەکی وێب ئەپ و داتابەیس (pirxdry)</span>
                    <span class="badge rounded-pill ms-2" id="webPendingBadge" style="background: rgba(99, 102, 241, 0.2); color: #4f46e5; font-size: 11px;">پشکنین...</span>
                </button>
            </li>
            <li class="nav-item flex-fill" role="presentation">
                <button class="nav-link w-100" id="tab-mobile-btn" data-bs-toggle="pill" data-bs-target="#tab-mobile" type="button" role="tab" onclick="loadMemberStatus()">
                    <i class="bi bi-phone"></i>
                    <span>دیپلۆمێنتی ئەپی ئەندامان (member_app)</span>
                    <span class="badge rounded-pill" id="memberPendingBadge" style="background: rgba(6, 182, 212, 0.2); color: #0891b2; font-size: 11px;">/member_app</span>
                </button>
            </li>
        </ul>

        <div class="tab-content" id="deployerTabContent">
            <!-- TAB 1: Main Web App & Database -->
            <div class="tab-pane fade show active" id="tab-web" role="tabpanel">
                <div class="action-grid" id="actionCardsGrid">
                    <!-- Card 1: Sync changes -->
                    <div class="action-card sync">
                        <div>
                            <div class="icon-box"><i class="bi bi-cloud-arrow-up"></i></div>
                            <h3>هاوتاکردنی گۆڕانکارییەکان (Sync)</h3>
                            <p>ئەم دوگمەیە خۆکارانە فایلی نوێی دێپڵۆیمێنت دروستدەکات، و هەموو فایلە گۆڕدراوە نوێیەکان ڕاستەوخۆ دەگوازێتەوە سەر سێرڤەرەکەت و پاشەکەوتی دەکاتە ناو GitHub.</p>
                            
                            <!-- Git Auto Push Option -->
                            <div class="mb-2 d-flex align-items-center justify-content-between p-3 rounded" style="background: #eff6ff; border: 1.5px solid #bfdbfe; direction: rtl;">
                                <div>
                                    <span class="small fw-bold text-dark d-block"><i class="bi bi-github text-primary me-1"></i> هاوکات ناردن بۆ گیت‌هەب (Auto-Push to GitHub)</span>
                                    <span class="text-muted" style="font-size: 11px;">هەموو گۆڕانکارییەکان ڕاستەوخۆ دەگەنە ناو کۆگای Sangawy/pirxdry</span>
                                </div>
                                <div class="form-check form-switch m-0 p-0" style="display: flex; align-items: center;">
                                    <input class="form-check-input" type="checkbox" id="autoPushGitWeb" checked style="cursor: pointer; margin: 0; float: none;">
                                </div>
                            </div>

                            <!-- Force Full Sync Option -->
                            <div class="mb-4 d-flex align-items-center justify-content-between p-3 rounded" style="background: #f8fafc; border: 1.5px solid #e2e8f0; direction: rtl;">
                                <span class="small text-muted fw-bold">⚠️ ئەپلۆدکردنەوەی هەموو فایلەکان (Force Full Sync)</span>
                                <div class="form-check form-switch m-0 p-0" style="display: flex; align-items: center;">
                                    <input class="form-check-input" type="checkbox" id="forceFullSync" style="cursor: pointer; margin: 0; float: none;">
                                </div>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-premium btn-sync flex-grow-1" id="btnSync" onclick="startSyncStream()">
                                <i class="bi bi-play-circle"></i> هاوتاکردن دەست پێبکە
                            </button>
                            <button class="btn btn-outline-dark d-inline-flex align-items-center gap-2" id="btnGitPushWeb" onclick="startGitPushStream()" title="تەنها ناردن بۆ گیت‌هەب بەبێ سێرڤەر" style="border-radius: 14px; font-weight: 600; padding: 0 16px;">
                                <i class="bi bi-github fs-5"></i> <span class="d-none d-md-inline">Git Push</span>
                            </button>
                            <button class="btn btn-outline-secondary" onclick="loadWebStatus()" title="پشکنینەوەی فایلە گۆڕدراوەکان" style="border-radius: 14px; padding: 0 16px;">
                                <i class="bi bi-arrow-clockwise fs-5"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Card 2: Full Backup -->
                    <div class="action-card backup">
                        <div>
                            <div class="icon-box"><i class="bi bi-folder-symlink"></i></div>
                            <h3>باکئەپ کردنی پڕۆژە و داتابەیس</h3>
                            <p>ئەم دوگمەیە باکئەپی تەواوی داتابەیسەکە دەکات، هەموو فایلەکانی پڕۆژەکە لە فایلی ZIP هەڵدەگرێت، و هاوکات باکئەپی داتابەیس و کۆدەکە ڕاستەوخۆ دەگەیەنێتە سەر GitHub بۆ پاراستنی هەمیشەیی.</p>
                            <div class="p-2 px-3 rounded-3 mb-3 d-flex align-items-center gap-2" style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.25);">
                                <i class="bi bi-cloud-check-fill text-success fs-5"></i>
                                <span class="small text-dark fw-bold">باکئەپی داتابەیس ڕاستەوخۆ لەسەر GitHub (Sangawy/pirxdry) پاشەکەوت دەبێت.</span>
                            </div>
                        </div>
                        <button class="btn btn-premium btn-backup" id="btnBackup" onclick="startBackupStream()">
                            <i class="bi bi-shield-check"></i> دروستکردنی باکئەپی ئێستا
                        </button>
                    </div>
                </div>

                <!-- Pending Changed Files Table for Web App -->
                <div class="history-card mb-4" id="webChangedFilesBox">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 class="m-0" style="font-size: 17px;">
                            <i class="bi bi-file-earmark-diff text-primary"></i> ئەو فایلانەی لە وێب ئەپ گۆڕاون و هاوتا دەکرێن (Sync Pending):
                            <span class="badge bg-primary text-white rounded-pill ms-2" id="webChangedCountBadge">0</span>
                        </h3>
                        <div class="text-muted small">
                            دوایین هاوتاکردن: <span class="fw-bold text-success" id="webLastSyncText">پشکنین...</span>
                        </div>
                    </div>
                    <div class="table-responsive rounded-3 border" style="max-height: 280px; overflow-y: auto;">
                        <table class="table table-sm table-hover m-0" style="font-size: 13px;">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>ناوی فایل (File Path)</th>
                                    <th style="width: 120px;">قەبارە</th>
                                    <th style="width: 170px;">کاتی دەستکاریکردن</th>
                                </tr>
                            </thead>
                            <tbody id="webChangedFilesBody">
                                <tr>
                                    <td colspan="3" class="text-center py-3 text-muted">پشکنینی فایلەکان...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- History Section -->
                <div class="history-card mb-4">
                    <h3><i class="bi bi-clock-history"></i> مێژووی فایلەکانی باکئەپ لەسەر سەرڤەر</h3>
                    <div id="backupsListContainer">
                        <div class="text-center py-4 text-muted">تکایە چاوەڕوان بن...</div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: Member App Deployment -->
            <div class="tab-pane fade" id="tab-mobile" role="tabpanel">
                <div class="action-card mb-4" style="border-bottom: 5px solid #0891b2; max-width: 100%;">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
                        <div class="d-flex align-items-center gap-3">
                            <div class="icon-box m-0" style="background: rgba(8, 145, 178, 0.1); color: #0891b2;">
                                <i class="bi bi-phone"></i>
                            </div>
                            <div>
                                <h3 class="m-0" style="font-size: 22px;">هاوتاکردنی ئەپی ئەندامان (member_app)</h3>
                                <p class="m-0 text-muted small mt-1">ئەم بەشە تەنها فایلە گۆڕاوەکانی ناو فۆڵدەری <code>member_app</code> بەراورد دەکات و دەینێرێتە <code>/public_html/member_app/</code> لەسەر هۆست و GitHub.</p>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="http://localhost/pirxdry/member_app/login.php" target="_blank" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" style="border-radius: 12px; font-weight: 600; font-size: 13px;">
                                <i class="bi bi-display"></i> کردنەوە لە لۆکاڵ
                            </a>
                            <a href="https://pirxdry.org/member_app/login.php" target="_blank" class="btn btn-outline-info d-inline-flex align-items-center gap-2" style="border-radius: 12px; font-weight: 600; font-size: 13px;">
                                <i class="bi bi-globe2"></i> کردنەوە لەسەر سێرڤەر
                            </a>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <div class="p-3 rounded-3" style="background: #f8fafc; border: 1.5px solid #e2e8f0;">
                                <div class="text-muted small">📁 سەرچاوەی لۆکاڵ (Local Source):</div>
                                <div class="fw-bold text-dark font-monospace small mt-1">pirxdry/member_app</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 rounded-3" style="background: #f8fafc; border: 1.5px solid #e2e8f0;">
                                <div class="text-muted small">☁️ ڕێڕەوی سێرڤەر (Remote Path):</div>
                                <div class="fw-bold text-info font-monospace small mt-1">/public_html/member_app/</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 rounded-3" style="background: #f8fafc; border: 1.5px solid #e2e8f0;">
                                <div class="text-muted small">🕒 دوایین کاتی هاوتاکردن (Last Sync):</div>
                                <div class="fw-bold text-success small mt-1" id="memberLastSyncText">پشکنین...</div>
                            </div>
                        </div>
                    </div>

                    <!-- Git Auto Push Option Member App -->
                    <div class="mb-3 d-flex align-items-center justify-content-between p-3 rounded" style="background: #eff6ff; border: 1.5px solid #bfdbfe; direction: rtl;">
                        <div>
                            <span class="small fw-bold text-dark d-block"><i class="bi bi-github text-primary me-1"></i> هاوکات ناردن بۆ گیت‌هەب (Auto-Push to GitHub)</span>
                            <span class="text-muted" style="font-size: 11px;">فایلە گۆڕدراوەکانی ئەپی ئەندامان لەسەر GitHub (Sangawy/pirxdry) هاوتا دەکرێن</span>
                        </div>
                        <div class="form-check form-switch m-0 p-0" style="display: flex; align-items: center;">
                            <input class="form-check-input" type="checkbox" id="autoPushGitMember" checked style="cursor: pointer; margin: 0; float: none;">
                        </div>
                    </div>

                    <div class="mb-4 d-flex align-items-center justify-content-between p-3 rounded" style="background: #f0fdf4; border: 1.5px solid #bbf7d0; direction: rtl;">
                        <div>
                            <span class="fw-bold text-dark d-block">⚠️ ئەپلۆدکردنەوەی تەواوی فایلەکان (Force Full Sync)</span>
                            <span class="small text-muted">ئەگەر کارا بێت، هەموو فایلەکان بەبێ جیاوازی سەرلەنوێ ئەپلۆد دەکرێنەوە.</span>
                        </div>
                        <div class="form-check form-switch m-0 p-0" style="display: flex; align-items: center;">
                            <input class="form-check-input" type="checkbox" id="forceFullSyncMember" style="cursor: pointer; margin: 0; float: none;">
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button class="btn btn-premium flex-grow-1" id="btnMemberSync" onclick="startMemberSyncStream()" style="background: linear-gradient(135deg, #06b6d4, #0891b2); color: white; box-shadow: 0 4px 15px rgba(8, 145, 178, 0.35); font-size: 16px; padding: 14px 28px;">
                            <i class="bi bi-phone-flip"></i> هاوتاکردنی ئەپی ئەندامان دەست پێبکە (Sync Member App)
                        </button>
                        <button class="btn btn-outline-dark d-inline-flex align-items-center gap-2" onclick="startGitPushStream()" title="ناردنی فایلەکان بۆ گیت‌هەب بە تەنها" style="border-radius: 14px; font-weight: 600; padding: 0 16px;">
                            <i class="bi bi-github fs-5"></i> <span class="d-none d-md-inline">Git Push</span>
                        </button>
                        <button class="btn btn-outline-secondary" onclick="loadMemberStatus()" title="پشکنینەوەی فایلە گۆڕدراوەکان" style="border-radius: 14px; padding: 0 20px;">
                            <i class="bi bi-arrow-clockwise fs-5"></i>
                        </button>
                    </div>

                    <!-- Pending Changed Files Table -->
                    <div class="mt-4 pt-3 border-top" id="memberChangedFilesBox">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="m-0 fw-bold fs-6 text-dark">
                                <i class="bi bi-file-earmark-diff text-info"></i> ئەو فایلانەی لە ئەپی ئەندامان گۆڕاون و چاوەڕوانی دیپلۆین:
                                <span class="badge bg-info text-white rounded-pill ms-2" id="memberChangedCountBadge">0</span>
                            </h5>
                        </div>
                        <div class="table-responsive rounded-3 border" style="max-height: 280px; overflow-y: auto;">
                            <table class="table table-sm table-hover m-0" style="font-size: 13px;">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th>ناوی فایل (File Path)</th>
                                        <th style="width: 120px;">قەبارە</th>
                                        <th style="width: 170px;">کاتی دەستکاریکردن</th>
                                    </tr>
                                </thead>
                                <tbody id="memberChangedFilesBody">
                                    <tr>
                                        <td colspan="3" class="text-center py-3 text-muted">پشکنینی فایلەکان...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const terminalBox = document.getElementById('terminalBox');
        const terminalConsole = document.getElementById('terminalConsole');
        const btnSync = document.getElementById('btnSync');
        const btnMemberSync = document.getElementById('btnMemberSync');
        const btnBackup = document.getElementById('btnBackup');
        const progressCard = document.getElementById('progressCard');
        const progressTitle = document.getElementById('progressTitle');
        const progressPercentBadge = document.getElementById('progressPercentBadge');
        const progressFillBar = document.getElementById('progressFillBar');
        const progressStatusMessage = document.getElementById('progressStatusMessage');

        let isBackupSuccess = false;
        let isSyncSuccess = false;

        function appendToConsole(text, color = '#a7f3d0') {
            if (!terminalConsole) return;
            const span = document.createElement('span');
            span.style.color = color;
            span.textContent = text;
            terminalConsole.appendChild(span);
            terminalConsole.scrollTop = terminalConsole.scrollHeight;
        }

        function updateProgressBar(percent, text) {
            if (progressPercentBadge) progressPercentBadge.textContent = percent + '%';
            if (progressFillBar) progressFillBar.style.width = percent + '%';
            if (progressStatusMessage) progressStatusMessage.textContent = text;
        }

        // Live Server-Sent Events (SSE) Sync Stream
        function startSyncStream(target = '') {
            if (terminalBox) terminalBox.style.display = 'block';
            if (terminalConsole) terminalConsole.textContent = '';
            if (btnSync) btnSync.disabled = true;
            if (btnMemberSync) btnMemberSync.disabled = true;
            if (btnBackup) btnBackup.disabled = true;
            isSyncSuccess = false;
            
            const isMember = target === 'member_app';
            const forceFull = isMember 
                ? (document.getElementById('forceFullSyncMember')?.checked || false)
                : (document.getElementById('forceFullSync')?.checked || false);
            const pushGit = isMember
                ? (document.getElementById('autoPushGitMember')?.checked ?? true)
                : (document.getElementById('autoPushGitWeb')?.checked ?? true);
                
            let streamUrl = 'deployer.php?action=sync_stream';
            if (isMember) {
                streamUrl += '&target=member_app';
            }
            if (forceFull) {
                streamUrl += '&force_full=1';
            }
            if (pushGit) {
                streamUrl += '&push_github=1';
            } else {
                streamUrl += '&push_github=0';
            }
            
            const eventSource = new EventSource(streamUrl);
            
            eventSource.onmessage = function(event) {
                const data = JSON.parse(event.data);
                
                if (data.type === 'status') {
                    appendToConsole(data.text, '#38bdf8');
                } else if (data.type === 'info') {
                    appendToConsole(data.text, '#e2e8f0');
                } else if (data.type === 'error') {
                    appendToConsole(data.text, '#f87171');
                    isSyncSuccess = false;
                    eventSource.close();
                } else if (data.type === 'success') {
                    appendToConsole(data.text, '#34d399');
                    isSyncSuccess = true;
                    if (btnSync) btnSync.disabled = false;
                    if (btnMemberSync) btnMemberSync.disabled = false;
                    if (btnBackup) btnBackup.disabled = false;
                    eventSource.close();
                    if (isMember) {
                        loadMemberStatus();
                    } else {
                        loadWebStatus();
                    }
                } else {
                    appendToConsole(data.text, '#a7f3d0');
                }
            };

            eventSource.onerror = function() {
                if (isSyncSuccess) {
                    appendToConsole("\n[INFO] هاوتاکردن بە سەرکەوتوویی تەواو بوو.\n", "#34d399");
                } else {
                    appendToConsole("\n[WARNING] پەیوەندی هاوتاکردن پچڕا یان تەواو بوو.\n", "#fbbf24");
                }
                if (btnSync) btnSync.disabled = false;
                if (btnMemberSync) btnMemberSync.disabled = false;
                if (btnBackup) btnBackup.disabled = false;
                eventSource.close();
                if (isMember) {
                    loadMemberStatus();
                } else {
                    loadWebStatus();
                }
            };
        }

        function startMemberSyncStream() {
            startSyncStream('member_app');
        }

        // Check & Load Main Web App Status & Changed Files
        async function loadWebStatus() {
            const lastSyncEl = document.getElementById('webLastSyncText');
            const badgeEl = document.getElementById('webPendingBadge');
            const countBadge = document.getElementById('webChangedCountBadge');
            const tbody = document.getElementById('webChangedFilesBody');

            try {
                const res = await fetch('deployer.php?action=get_web_status');
                const data = await res.json();
                
                if (data.success) {
                    if (lastSyncEl) lastSyncEl.textContent = data.last_sync || 'تا ئێستا نەکراوە';
                    const count = data.changed_count !== undefined ? data.changed_count : (data.files ? data.files.length : 0);
                    if (badgeEl) badgeEl.textContent = count + ' فایلی گۆڕاو';
                    if (countBadge) countBadge.textContent = count;

                    if (tbody) {
                        if (count === 0) {
                            tbody.innerHTML = '<tr><td colspan="3" class="text-center py-3 text-success fw-bold"><i class="bi bi-check-circle me-1"></i> هەموو فایلەکان هاوتان و هیچ فایلێکی دەستکاریکراو نییە بۆ ئەپلۆد!</td></tr>';
                        } else {
                            tbody.innerHTML = (data.files || []).map(f => `
                                <tr>
                                    <td class="font-monospace text-dark text-start" style="direction: ltr;"><i class="bi bi-file-earmark-code text-primary me-1"></i>${f.path}</td>
                                    <td class="font-monospace text-muted">${f.size}</td>
                                    <td class="text-muted small">${f.time}</td>
                                </tr>
                            `).join('');
                        }
                    }
                } else {
                    if (tbody) tbody.innerHTML = `<tr><td colspan="3" class="text-center py-3 text-danger">⚠️ ${data.message || 'هەڵە لە پشکنینی فایلەکان'}</td></tr>`;
                }
            } catch(e) {
                if (tbody) tbody.innerHTML = '<tr><td colspan="3" class="text-center py-3 text-danger">هەڵەی پەیوەندی لە بارکردنی دۆخی وێب ئەپ.</td></tr>';
            }
        }

        // Direct Git Push Stream
        function startGitPushStream() {
            if (terminalBox) terminalBox.style.display = 'block';
            if (terminalConsole) terminalConsole.textContent = '';
            if (btnSync) btnSync.disabled = true;
            if (btnMemberSync) btnMemberSync.disabled = true;
            if (btnBackup) btnBackup.disabled = true;
            
            appendToConsole("🐙 دەستپێکردنی پەیوەندی لەگەڵ GitHub (Sangawy/pirxdry)...\n", "#38bdf8");

            const eventSource = new EventSource('deployer.php?action=git_push_stream');
            
            eventSource.onmessage = function(event) {
                const data = JSON.parse(event.data);
                if (data.type === 'status') {
                    appendToConsole(data.text + "\n", '#38bdf8');
                } else if (data.type === 'info') {
                    appendToConsole(data.text + "\n", '#e2e8f0');
                } else if (data.type === 'error') {
                    appendToConsole(data.text + "\n", '#f87171');
                } else if (data.type === 'success') {
                    appendToConsole(data.text + "\n", '#34d399');
                    btnSync.disabled = false;
                    if (btnMemberSync) btnMemberSync.disabled = false;
                    btnBackup.disabled = false;
                    eventSource.close();
                    loadMemberStatus();
                    loadWebStatus();
                } else {
                    appendToConsole(data.text + "\n", '#a7f3d0');
                }
            };

            eventSource.onerror = function() {
                appendToConsole("\n[INFO] پڕۆسەی گیت‌هەب تەواو بوو.\n", "#34d399");
                btnSync.disabled = false;
                if (btnMemberSync) btnMemberSync.disabled = false;
                btnBackup.disabled = false;
                eventSource.close();
                loadMemberStatus();
                loadWebStatus();
            };
        }

        // Check & Load Member App Status & Changed Files
        async function loadMemberStatus() {
            const lastSyncEl = document.getElementById('memberLastSyncText');
            const badgeEl = document.getElementById('memberPendingBadge');
            const countBadge = document.getElementById('memberChangedCountBadge');
            const tbody = document.getElementById('memberChangedFilesBody');

            try {
                const res = await fetch('deployer.php?action=get_member_status');
                const data = await res.json();
                
                if (data.success) {
                    if (lastSyncEl) lastSyncEl.textContent = data.last_sync || 'تا ئێستا نەکراوە';
                    const count = data.changed_count !== undefined ? data.changed_count : (data.files ? data.files.length : 0);
                    if (badgeEl) badgeEl.textContent = count + ' فایلی گۆڕاو';
                    if (countBadge) countBadge.textContent = count;

                    if (tbody) {
                        if (count === 0) {
                            tbody.innerHTML = '<tr><td colspan="3" class="text-center py-3 text-success fw-bold"><i class="bi bi-check-circle me-1"></i> هەموو فایلەکانی ئەپی ئەندامان هاوتان و هیچ فایلێکی گۆڕاو نییە!</td></tr>';
                        } else {
                            tbody.innerHTML = (data.files || []).map(f => `
                                <tr>
                                    <td class="font-monospace text-dark text-start" style="direction: ltr;"><i class="bi bi-file-earmark-code text-info me-1"></i>${f.path}</td>
                                    <td class="font-monospace text-muted">${f.size}</td>
                                    <td class="text-muted small">${f.time}</td>
                                </tr>
                            `).join('');
                        }
                    }
                } else {
                    if (tbody) tbody.innerHTML = `<tr><td colspan="3" class="text-center py-3 text-danger">⚠️ ${data.message || 'هەڵە لە پشکنینی فایلەکان'}</td></tr>`;
                }
            } catch(e) {
                if (tbody) tbody.innerHTML = '<tr><td colspan="3" class="text-center py-3 text-danger">هەڵەی پەیوەندی لە بارکردنی دۆخی ئەپی ئەندامان.</td></tr>';
            }
        }

        // Real-time Backup Progress Stream
        function startBackupStream() {
            if (!confirm('دەتەوێت باکئەپکردنی مۆدێرنی پڕۆژە و داتابەیسەکە دەستپێبکات؟')) return;

            if (terminalBox) terminalBox.style.display = 'block';
            if (terminalConsole) terminalConsole.textContent = '';
            if (progressCard) progressCard.style.display = 'block';
            
            if (btnSync) btnSync.disabled = true;
            if (btnMemberSync) btnMemberSync.disabled = true;
            if (btnBackup) btnBackup.disabled = true;
            isBackupSuccess = false;
            
            if (progressTitle) progressTitle.textContent = "⚙️ دروستکردنی باکئەپی پڕۆژە و داتابەیس";
            updateProgressBar(0, "دەستپێکردنی ڕێکخستنی باکئەپ...");

            const eventSource = new EventSource('deployer.php?action=backup_stream');
            
            eventSource.onmessage = function(event) {
                const data = JSON.parse(event.data);
                
                if (data.type === 'status') {
                    updateProgressBar(data.percent, data.text);
                    appendToConsole(data.text + "\n", '#38bdf8');
                } else if (data.type === 'info') {
                    updateProgressBar(data.percent, data.text);
                    appendToConsole(data.text + "\n", '#e2e8f0');
                } else if (data.type === 'error') {
                    updateProgressBar(data.percent, data.text);
                    appendToConsole("\n❌ خەتا: " + data.text + "\n", '#f87171');
                    isBackupSuccess = false;
                    if (btnSync) btnSync.disabled = false;
                    if (btnMemberSync) btnMemberSync.disabled = false;
                    if (btnBackup) btnBackup.disabled = false;
                    eventSource.close();
                } else if (data.type === 'success') {
                    updateProgressBar(100, "تەواو بوو!");
                    appendToConsole("\n" + data.text + "\n", '#34d399');
                    alert("باکئەپ بە سەرکەوتوویی تەواو بوو!");
                    
                    isBackupSuccess = true;
                    if (btnSync) btnSync.disabled = false;
                    if (btnMemberSync) btnMemberSync.disabled = false;
                    if (btnBackup) btnBackup.disabled = false;
                    
                    setTimeout(() => {
                        if (progressCard) progressCard.style.display = 'none';
                    }, 5000);
                    
                    eventSource.close();
                    loadBackupsList();
                } else {
                    updateProgressBar(data.percent, data.text);
                    appendToConsole(data.text + "\n", '#a7f3d0');
                }
            };

            eventSource.onerror = function() {
                if (isBackupSuccess) {
                    appendToConsole("\n[SUCCESS] Backup operation completed successfully.\n", "#34d399");
                } else {
                    appendToConsole("\n[WARNING] Connection interrupted or ended prematurely.\n", "#f87171");
                }
                if (btnSync) btnSync.disabled = false;
                if (btnMemberSync) btnMemberSync.disabled = false;
                if (btnBackup) btnBackup.disabled = false;
                eventSource.close();
                loadBackupsList();
            };
        }

        // Load Backup History
        async function loadBackupsList() {
            const container = document.getElementById('backupsListContainer');
            try {
                const res = await fetch('deployer.php?action=list_backups');
                const data = await res.json();
                
                if (data.success && data.backups.length > 0) {
                    container.innerHTML = data.backups.map(b => `
                        <div class="backup-item">
                            <div class="backup-info">
                                <i class="bi bi-file-earmark-zip backup-icon"></i>
                                <div>
                                    <div class="backup-name">${b.name}</div>
                                    <div class="backup-meta">${b.size} • دروستکراوە لە ${b.date}</div>
                                </div>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="deployer.php?action=download&file=${encodeURIComponent(b.name)}" class="btn-circle btn-dl" title="داگرتنی فایل">
                                    <i class="bi bi-download"></i>
                                </a>
                                <button onclick="deleteBackup('${b.name}')" class="btn-circle btn-del" title="سڕینەوە">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    `).join('');
                } else {
                    container.innerHTML = '<div class="text-center py-4 text-muted">هیچ باکئەپێک نەدۆزرایەوە.</div>';
                }
            } catch (e) {
                container.innerHTML = '<div class="text-center py-4 text-danger">هەڵەیەک لە بارکردنی لیستەکە هەیە.</div>';
            }
        }

        async function deleteBackup(filename) {
            if (!confirm('دڵنیای لە سڕینەوەی ئەم باکئەپە؟')) return;
            try {
                const res = await fetch('deployer.php?action=delete&file=' + encodeURIComponent(filename));
                const data = await res.json();
                alert(data.message);
                loadBackupsList();
            } catch (e) {
                alert("سڕینەوە شکستی هێنا.");
            }
        }

        // Save WebDAV configurations with connection check
        async function saveWebDiskPass() {
            const url = document.getElementById('cfgUrl').value.trim();
            const user = document.getElementById('cfgUser').value.trim();
            const pass = document.getElementById('cfgPass').value.trim();
            const path = document.getElementById('cfgPath').value.trim();
            const msgEl = document.getElementById('cfgSaveMsg');
            const saveBtn = document.querySelector('button[onclick="saveWebDiskPass()"]');
            
            if (!url || !user || !pass) {
                msgEl.style.display = 'block';
                msgEl.style.color = '#dc2626';
                msgEl.textContent = '⚠️ تکایە هەموو خانەکان (URL، یوزەرنەیم، پاسوورد) پڕ بکەرەوە!';
                return;
            }
            
            if (saveBtn) {
                saveBtn.disabled = true;
                saveBtn.innerHTML = '⏳ لە تاقیکردنەوەدایە...';
                saveBtn.style.opacity = '0.7';
            }
            
            msgEl.style.display = 'block';
            msgEl.style.color = '#4b5563';
            msgEl.textContent = '🔄 لە کۆنێکت بووندایە، تکایە چاوەڕێ بکە...';
            
            try {
                const formData = new FormData();
                formData.append('url', url);
                formData.append('username', user);
                formData.append('password', pass);
                formData.append('path', path);
                
                const res = await fetch('deployer.php?action=save_webdisk_pass', { method: 'POST', body: formData });
                const data = await res.json();
                
                if (data.success) {
                    msgEl.style.color = '#059669';
                    msgEl.textContent = '✅ ' + data.message + ' — پەیجەکە دووبارە بارئەبێت...';
                    setTimeout(() => location.reload(), 1500);
                } else {
                    msgEl.style.color = '#dc2626';
                    msgEl.textContent = '❌ ' + data.message;
                    if (saveBtn) {
                        saveBtn.disabled = false;
                        saveBtn.innerHTML = '💾 پاشەکەوت';
                        saveBtn.style.opacity = '1';
                    }
                }
            } catch(e) {
                msgEl.style.color = '#dc2626';
                msgEl.textContent = '❌ هەڵەی پەیوەندی: ' + e.message;
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = '💾 پاشەکەوت';
                    saveBtn.style.opacity = '1';
                }
            }
        }

        // Initialize lists on page load
        document.addEventListener('DOMContentLoaded', () => {
            loadBackupsList();
            loadWebStatus();
            loadMemberStatus();
        });
    </script>
</body>
</html>
