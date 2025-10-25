<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'auth.php';
require_once 'config.php';

// Require authentication with code 0330
requireAuth();

// Set default values for observer session if not set
if (!isset($_SESSION['observer_id'])) {
    $_SESSION['observer_id'] = 'admin';
    $_SESSION['observer_name'] = 'بەڕێوەبەر';
    $_SESSION['center_name'] = 'هەموو ناوەندەکان';
}

// Cache configuration
$cache_duration = 30; // 30 seconds cache
$cache_file = 'cache/stats_' . md5($center_name) . '.json';
$cache_dir = 'cache';

// Create cache directory if it doesn't exist
if (!is_dir($cache_dir)) {
    mkdir($cache_dir, 0755, true);
}

// Function to get cached stats
function getCachedStats($cache_file, $cache_duration) {
    if (file_exists($cache_file)) {
        $cache_time = filemtime($cache_file);
        if (time() - $cache_time < $cache_duration) {
            return json_decode(file_get_contents($cache_file), true);
        }
    }
    return null;
}

// Function to save stats to cache
function saveStatsToCache($cache_file, $stats) {
    file_put_contents($cache_file, json_encode($stats));
}

// وەرگرتنی ئاماری بنکە لە کاش یان دیتابەیس
$cached_stats = getCachedStats($cache_file, $cache_duration);

if ($cached_stats && !isset($_POST['scan'])) {
    // Use cached data if available and not processing a scan
    $total_voters = $cached_stats['total_voters'];
    $scanned_voters = $cached_stats['scanned_voters'];
    $percentage = $cached_stats['percentage'];
} else {
    // Fetch fresh data from database
    try {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_voters,
                SUM(CASE WHEN status = 'voted' THEN 1 ELSE 0 END) as scanned_voters
            FROM voters 
            WHERE voting_center_name = ?
        ");
        $stmt->execute([$center_name]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        $total_voters = $stats['total_voters'] ?? 0;
        $scanned_voters = $stats['scanned_voters'] ?? 0;
        $percentage = $total_voters > 0 ? round(($scanned_voters / $total_voters) * 100) : 0;
        
        // Save to cache
        saveStatsToCache($cache_file, [
            'total_voters' => $total_voters,
            'scanned_voters' => $scanned_voters,
            'percentage' => $percentage
        ]);
    } catch(PDOException $e) {
        $error = "هەڵە لە خوێندنەوەی داتا: " . $e->getMessage();
        $total_voters = 0;
        $scanned_voters = 0;
        $percentage = 0;
    }
}

// پرۆسێسی سکانکردن
$scan_result = null;
if (isset($_POST['scan'])) {
    $voter_number = trim($_POST['voter_number']);
    
    if (empty($voter_number)) {
        $scan_result = array(
            'type' => 'error',
            'message' => 'تکایە ژمارەی دەنگدەر بنووسە!'
        );
    } else {
        try {
            // پشکنینی کارت لە داتابەیس - تەنها گەڕان بە ژمارەی دەنگدەر
            $stmt = $pdo->prepare("
                SELECT * FROM voters 
                WHERE (voter_number = ? OR voter_number LIKE ?) 
                AND voting_center_name = ?
            ");
            $stmt->execute([$voter_number, "%$voter_number", $center_name]);
            $voter = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($voter) {
                if ($voter['status'] == 'voted') {
                    $scanned_time = $voter['scanned_at'] ? date('H:i', strtotime($voter['scanned_at'])) : 'پێشوو';
                    $scan_result = array(
                        'type' => 'duplicate',
                        'message' => "ئەم کارتە پێشووتر سکان کراوە لە کاتژمێر {$scanned_time} - دووبارەیە!"
                    );
                } else {
                    // نوێکردنەوەی ستاتوسی دەنگدەر لە خشتەی voters
                    $updateStmt = $pdo->prepare("UPDATE voters SET status = 'voted', scanned_at = NOW(), scanned_by = ? WHERE id = ?");
                    $updateStmt->execute([$observer_id, $voter['id']]);
                    
                    // Clear cache after successful scan
                    if (file_exists($cache_file)) {
                        unlink($cache_file);
                    }
                    if (file_exists($recent_cache_file)) {
                        unlink($recent_cache_file);
                    }
                    
                    // نوێکردنەوەی ئامارەکان
                    $scanned_voters++;
                    $percentage = round(($scanned_voters / $total_voters) * 100);
                    
                    $scan_result = array(
                        'type' => 'success',
                        'message' => "کارتی دەنگدەر {$voter['full_name']} بە سەرکەوتوویی تۆمارکرا!"
                    );
                }
            } else {
                // پشکنین ئەگەر کارتەکە لە بنکەیەکی ترە
                $stmt_other_center = $pdo->prepare("
                    SELECT voting_center_name FROM voters 
                    WHERE (voter_number = ? OR voter_number LIKE ?) 
                    AND voting_center_name != ?
                    LIMIT 1
                ");
                $stmt_other_center->execute([$voter_number, "%$voter_number", $center_name]);
                $other_center = $stmt_other_center->fetch(PDO::FETCH_ASSOC);
                
                if ($other_center) {
                    $scan_result = array(
                        'type' => 'error', 
                        'message' => "کارت نەدۆزرایەوە لە بنکەدا!\n\nهۆکار: ئەم کارتە سەر بە بنکەی دەنگدانی '{$other_center['voting_center_name']}' ە، نەک ئەم بنکەیە."
                    );
                } else {
                    $scan_result = array(
                        'type' => 'error', 
                        'message' => "کارت نەدۆزرایەوە لە بنکەدا!\n\nهۆکار: ئەم ژمارە دەنگدەرە لە هیچ بنکەیەکدا تۆمار نەکراوە یان ژمارەکە هەڵەیە."
                    );
                }
            }
        } catch(PDOException $e) {
            $scan_result = array(
                'type' => 'error',
                'message' => 'هەڵە لە پرۆسێسی سکانکردن: ' . $e->getMessage()
            );
        }
    }
}

// وەرگرتنی سکانە دوایەکان لە کاش یان دیتابەیس
$recent_cache_file = 'cache/recent_' . md5($center_name) . '.json';
$recent_cache_duration = 60; // 1 minute cache for recent scans

$cached_recent = getCachedStats($recent_cache_file, $recent_cache_duration);

if ($cached_recent && !isset($_POST['scan'])) {
    $recent_scans = $cached_recent;
} else {
    try {
        $stmt = $pdo->prepare("
            SELECT full_name, voter_number, scanned_at 
            FROM voters 
            WHERE voting_center_name = ? AND status = 'voted' 
            ORDER BY scanned_at DESC 
            LIMIT 10
        ");
        $stmt->execute([$center_name]);
        $recent_scans = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Save to cache
        file_put_contents($recent_cache_file, json_encode($recent_scans));
    } catch(PDOException $e) {
        $recent_scans = [];
        $error = "هەڵە لە وەرگرتنی مێژووی سکان: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="ku">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سیستەمی سکانکردن - چاودێر</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            direction: rtl;
            min-height: 100vh;
            padding: 20px;
        }
        .scanner-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
        }
        .header {
            background: linear-gradient(135deg, #2c3e50, #4a6491);
            color: white;
            padding: 20px;
            text-align: center;
        }
        .form-container {
            padding: 30px;
        }
        .btn-scan {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            color: white;
            padding: 12px;
            font-size: 1.1rem;
            border-radius: 8px;
        }
        .btn-manual {
            background: linear-gradient(135deg, #007bff, #0056b3);
            border: none;
            color: white;
            padding: 12px;
            font-size: 1.1rem;
            border-radius: 8px;
        }
        .stats-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin: 10px 0;
            border-left: 4px solid #007bff;
        }
        .scan-result {
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
        }
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }
        .duplicate {
            background: #f8d7da;
            border: 2px solid #dc3545;
            color: #721c24;
            font-weight: bold;
            animation: shake 0.5s ease-in-out;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        .voter-card {
            background: linear-gradient(135deg, #ffffff, #f8f9fa);
            border: 2px solid #28a745;
            border-radius: 15px;
            margin: 15px 0;
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.15);
            overflow: hidden;
            animation: slideIn 0.5s ease-out;
        }
        .voter-card-header {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 15px 20px;
            text-align: center;
            font-weight: bold;
        }
        .voter-card-body {
            padding: 20px;
        }
        .voter-info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .voter-info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: bold;
            color: #495057;
            flex: 0 0 40%;
        }
        .info-value {
            color: #212529;
            font-weight: 500;
            flex: 1;
            text-align: left;
            background: #f8f9fa;
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid #dee2e6;
        }
        .voter-card-footer {
            background: #f8f9fa;
            padding: 15px 20px;
            text-align: center;
            border-top: 1px solid #dee2e6;
        }
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .camera-container {
            width: 100%;
            height: 300px;
            background: #000;
            border-radius: 8px;
            margin: 15px 0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
        .input-group {
            direction: ltr;
        }
        .input-group-text {
            background: #e9ecef;
            border: 1px solid #ced4da;
        }
        .recent-scans {
            max-height: 200px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <div class="scanner-container">
        <div class="header">
            <h5 class="mb-1">بەخێربێی <?php echo htmlspecialchars($observer_name); ?>!</h5>
            <p class="mb-0 small">بنکە: <?php echo htmlspecialchars($center_name); ?></p>
        </div>
        <div class="form-container">
            <!-- Statistics -->
            <div class="stats-card">
                <div class="row text-center">
                    <div class="col-6">
                        <h6 class="text-primary mb-1">کۆی دەنگدەران</h6>
                        <h4 class="mb-0" id="totalVoters"><?php echo $total_voters; ?></h4>
                    </div>
                    <div class="col-6">
                        <h6 class="text-success mb-1">سکانکراو</h6>
                        <h4 class="mb-0" id="scannedVoters"><?php echo $scanned_voters; ?></h4>
                    </div>
                </div>
                <div class="progress mt-2">
                    <div class="progress-bar bg-success" id="progressBar" style="width: <?php echo $percentage; ?>%"></div>
                </div>
                <div class="text-center mt-1">
                    <small class="text-muted" id="percentageText"><?php echo $percentage; ?>% بەشداربوون</small>
                </div>
            </div>

            <!-- Scanner Options -->
            <div class="mb-4">
                <h6 class="text-center mb-3">هەڵبژاردنی شێوازی سکانکردن</h6>
                <div class="row g-2">
                    <div class="col-6">
                        <button class="btn btn-scan w-100" onclick="showQRScanner()">
                            <span>📷</span><br>سکانی کیوئاڕ
                        </button>
                    </div>
                    <div class="col-6">
                        <button class="btn btn-manual w-100" onclick="showManualInput()">
                            <span>⌨️</span><br>نوسینی ژمارە
                        </button>
                    </div>
                </div>
            </div>

            <!-- QR Scanner -->
            <div id="qrScanner" style="display: none;">
                <div class="camera-container">
                    <div class="text-center">
                        <span style="font-size: 3rem;">📷</span>
                        <p>کامێرای کیوئاڕ سکانەر</p>
                        <small class="text-muted">کیوئاڕ کۆدی کارت ڕوو بکە بۆ کامێرا</small>
                    </div>
                </div>
                <button class="btn btn-secondary w-100 mt-2" onclick="hideQRScanner()">گەڕانەوە</button>
            </div>

            <!-- Manual Input -->
            <div id="manualInput">
                <form method="POST" id="scanForm">
                    <div class="mb-3">
                        <label class="form-label">ژمارەی دەنگدەر</label>
                        <div class="input-group">
                            <input type="text" class="form-control text-center" name="voter_number" 
                                   placeholder="ژمارەی دەنگدەر (وەک: V001001)" 
                                   style="font-size: 1.1rem;" required
                                   id="voterInput">
                            <span class="input-group-text">📋</span>
                        </div>
                        <small class="text-muted">دەتوانیت ژمارەی دەنگدەر بنووسیت (وەک: V001001)</small>
                    </div>
                    <button type="submit" name="scan" class="btn btn-success w-100 mb-2 btn-scan">
                        <span>📱</span> سکانکردن
                    </button>
                </form>
            </div>

            <!-- Scan Result / Voter Card -->
            <?php if ($scan_result): ?>
                <?php if ($scan_result['type'] == 'success' && isset($voter)): ?>
                    <!-- Voter Information Card -->
                    <div class="voter-card" id="voterCard">
                        <div class="voter-card-header">
                            <h6 class="mb-0">✅ دەنگدەر دۆزرایەوە</h6>
                        </div>
                        <div class="voter-card-body">
                            <div class="voter-info-row">
                                <span class="info-label">ناوی سیانی:</span>
                                <span class="info-value"><?php echo htmlspecialchars($voter['full_name']); ?></span>
                            </div>
                            <div class="voter-info-row">
                                <span class="info-label">ژمارەی دەنگدەر:</span>
                                <span class="info-value"><?php echo htmlspecialchars($voter['voter_number']); ?></span>
                            </div>
                            <div class="voter-info-row">
                                <span class="info-label">ژمارەی موبایل:</span>
                                <span class="info-value"><?php echo htmlspecialchars($voter['phone'] ?? 'تۆمار نەکراوە'); ?></span>
                            </div>
                            <div class="voter-info-row">
                                <span class="info-label">یەکە:</span>
                                <span class="info-value"><?php echo htmlspecialchars($voter['unit'] ?? 'تۆمار نەکراوە'); ?></span>
                            </div>
                            <div class="voter-info-row">
                                <span class="info-label">بنکەی دەنگدان:</span>
                                <span class="info-value"><?php echo htmlspecialchars($voter['voting_center_name']); ?></span>
                            </div>
                        </div>
                        <div class="voter-card-footer">
                            <small class="text-success">✅ بە سەرکەوتوویی تۆمارکرا!</small>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Error/Warning Message -->
                    <div class="scan-result <?php echo $scan_result['type']; ?>" id="scanResult">
                        <?php echo $scan_result['message']; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Recent Scans Button -->
            <div class="mt-4 text-center">
                <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#recentScansModal">
                    <i class="bi bi-list-ul"></i> بینینی سکانە دوایەکان
                    <span class="badge bg-primary ms-2"><?php echo count($recent_scans); ?></span>
                </button>
            </div>

            <!-- Recent Scans Modal -->
            <div class="modal fade" id="recentScansModal" tabindex="-1" aria-labelledby="recentScansModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title" id="recentScansModalLabel">
                                <i class="bi bi-list-ul me-2"></i>سکانە دوایەکان
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <!-- Search Section -->
                            <div class="mb-3">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-search"></i>
                                    </span>
                                    <input type="text" class="form-control" id="searchInput" placeholder="گەڕان بە ناو یان ژمارەی دەنگدەر...">
                                    <button class="btn btn-outline-secondary" type="button" id="clearSearch">
                                        <i class="bi bi-x-circle"></i>
                                    </button>
                                </div>
                                <small class="text-muted">دەتوانیت بە ناوی تەواو یان ژمارەی دەنگدەر بگەڕیت</small>
                            </div>
                            
                            <div class="recent-scans-modal" id="recentScansModal">
                                <?php if (empty($recent_scans)): ?>
                                    <div class="text-center text-muted py-5">
                                        <i class="bi bi-inbox" style="font-size: 3rem; opacity: 0.3;"></i>
                                        <p class="mt-3">هیچ سکانێک تۆمار نەکراوە</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>ناوی تەواو</th>
                                                    <th>ژمارەی دەنگدەر</th>
                                                    <th>کاتی سکان</th>
                                                </tr>
                                            </thead>
                                            <tbody id="scansTableBody">
                                                <?php foreach($recent_scans as $scan): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="fw-bold text-primary">
                                                                <?php echo htmlspecialchars($scan['full_name']); ?>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <code class="bg-light px-2 py-1 rounded">
                                                                <?php echo htmlspecialchars($scan['voter_number']); ?>
                                                            </code>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-success">
                                                                <?php echo $scan['scanned_at'] ? date('H:i', strtotime($scan['scanned_at'])) : '---'; ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">داخستن</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Logout Button -->
            <div class="text-center mt-4">
                <a href="logout.php" class="btn btn-outline-danger btn-sm">
                    <span>🚪</span> چوونەدەرەوە
                </a>
            </div>
        </div>
    </div>

    <script>
        // AJAX Configuration
        let isProcessing = false;
        
        // Show QR Scanner
        function showQRScanner() {
            document.getElementById('qrScanner').style.display = 'block';
            document.getElementById('manualInput').style.display = 'none';
        }

        // Hide QR Scanner
        function hideQRScanner() {
            document.getElementById('qrScanner').style.display = 'none';
            document.getElementById('manualInput').style.display = 'block';
        }

        // Show Manual Input
        function showManualInput() {
            document.getElementById('manualInput').style.display = 'block';
            document.getElementById('qrScanner').style.display = 'none';
            document.getElementById('voterInput').focus();
        }

        // AJAX Scan Function
        function performScan(voterNumber) {
            if (isProcessing) return;
            isProcessing = true;
            
            // Show loading state
            showLoadingState();
            
            const formData = new FormData();
            formData.append('voter_number', voterNumber);
            formData.append('scan', '1'); // Add scan parameter
            
            fetch('observer_scanner.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                // Parse the response to extract the scan result
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                
                // Update scan result area
                const newResult = doc.querySelector('#voterCard, #scanResult');
                const resultContainer = document.querySelector('.form-container');
                const oldResult = document.querySelector('#voterCard, #scanResult');
                
                if (oldResult) {
                    oldResult.remove();
                }
                
                if (newResult) {
                    const statsCard = document.querySelector('.stats-card');
                    statsCard.parentNode.insertBefore(newResult, statsCard.nextSibling);
                    
                    // Check if it's a duplicate scan and show alert
                    if (newResult.classList.contains('duplicate')) {
                        alert('⚠️ دووبارەبوونەوە!\n\n' + newResult.textContent.trim());
                    }
                }
                
                // Update statistics
                const newStats = doc.querySelector('.stats-card');
                if (newStats) {
                    document.querySelector('.stats-card').innerHTML = newStats.innerHTML;
                }
                
                // Update recent scans in modal
                const newRecentScansModal = doc.querySelector('.recent-scans-modal');
                if (newRecentScansModal) {
                    const currentModal = document.querySelector('.recent-scans-modal');
                    if (currentModal) {
                        currentModal.innerHTML = newRecentScansModal.innerHTML;
                    }
                }
                
                // Update button badge count
                const recentScansCount = doc.querySelectorAll('.recent-scans-modal tbody tr').length;
                const badgeElement = document.querySelector('button[data-bs-target="#recentScansModal"] .badge');
                if (badgeElement) {
                    badgeElement.textContent = recentScansCount;
                }
                
                // Clear input
                document.getElementById('voterInput').value = '';
                
                // Auto-hide result after 8 seconds
                setTimeout(hideResult, 8000);
            })
            .catch(error => {
                console.error('Error:', error);
                showError('هەڵە لە پرۆسێسی سکانکردن');
            })
            .finally(() => {
                hideLoadingState();
                isProcessing = false;
            });
        }
        
        // Show loading state
        function showLoadingState() {
            const button = document.querySelector('.btn-scan');
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>سکانکردن...';
            button.disabled = true;
        }
        
        // Hide loading state
        function hideLoadingState() {
            const button = document.querySelector('.btn-scan');
            button.innerHTML = '<span>📱</span> سکانکردن';
            button.disabled = false;
        }
        
        // Show error message
        function showError(message) {
            const errorDiv = document.createElement('div');
            errorDiv.className = 'scan-result error';
            errorDiv.innerHTML = message;
            
            const statsCard = document.querySelector('.stats-card');
            statsCard.parentNode.insertBefore(errorDiv, statsCard.nextSibling);
            
            setTimeout(() => errorDiv.remove(), 5000);
        }
        
        // Hide result
        function hideResult() {
            const result = document.querySelector('#voterCard, #scanResult');
            if (result) {
                result.style.opacity = '0';
                setTimeout(() => result.remove(), 500);
            }
        }

        // Handle form submission
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const voterInput = document.getElementById('voterInput');
            
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const voterNumber = voterInput.value.trim();
                    if (voterNumber) {
                        performScan(voterNumber);
                    }
                });
            }
            
            // Focus on input
            if (voterInput) {
                voterInput.focus();
                
                // Handle Enter key
                voterInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        const voterNumber = this.value.trim();
                        if (voterNumber) {
                            performScan(voterNumber);
                        }
                    }
                });
            }
        });

        // Search functionality for recent scans modal
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const clearSearchBtn = document.getElementById('clearSearch');
            
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                     const searchTerm = this.value.toLowerCase().trim();
                     const tableRows = document.querySelectorAll('#scansTableBody tr');
                     
                     tableRows.forEach(row => {
                         const name = row.querySelector('td:first-child').textContent.toLowerCase();
                         const voterNumber = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
                         
                         if (name.includes(searchTerm) || voterNumber.includes(searchTerm)) {
                             row.style.display = '';
                         } else {
                             row.style.display = 'none';
                         }
                     });
                     
                     // Show/hide "no results" message
                     const visibleRows = Array.from(tableRows).filter(row => row.style.display !== 'none');
                     const noResultsMsg = document.getElementById('noSearchResults');
                     
                     if (visibleRows.length === 0 && searchTerm !== '') {
                         if (!noResultsMsg) {
                             const tbody = document.querySelector('#scansTableBody');
                             const noResultsRow = document.createElement('tr');
                             noResultsRow.id = 'noSearchResults';
                             noResultsRow.innerHTML = `
                                 <td colspan="3" class="text-center text-muted py-4">
                                     <i class="bi bi-search" style="font-size: 2rem; opacity: 0.3;"></i>
                                     <p class="mt-2 mb-0">هیچ ئەنجامێک نەدۆزرایەوە بۆ "${searchTerm}"</p>
                                 </td>
                             `;
                             tbody.appendChild(noResultsRow);
                         }
                     } else if (noResultsMsg) {
                         noResultsMsg.remove();
                     }
                 });
                
                // Clear search functionality
                if (clearSearchBtn) {
                    clearSearchBtn.addEventListener('click', function() {
                        searchInput.value = '';
                        const tableRows = document.querySelectorAll('#recentScansModal tbody tr');
                        tableRows.forEach(row => {
                            row.style.display = '';
                        });
                        const noResultsMsg = document.getElementById('noSearchResults');
                        if (noResultsMsg) {
                            noResultsMsg.remove();
                        }
                        searchInput.focus();
                    });
                }
            }
        });

        // Auto-hide existing scan result after 5 seconds
        <?php if ($scan_result): ?>
            setTimeout(function() {
                const resultElement = document.querySelector('#voterCard, #scanResult');
                if (resultElement) {
                    resultElement.style.opacity = '0';
                    setTimeout(() => resultElement.remove(), 500);
                }
            }, 8000);
        <?php endif; ?>
    </script>
    
    <!-- Bootstrap JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>