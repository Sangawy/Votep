<?php
require_once 'auth.php';
require_once 'config.php';

// Require authentication with code 0330
requireAuth();

// Set default values for observer session if not set
if (!isset($_SESSION['observer_id'])) {
    $_SESSION['observer_id'] = 'admin';
    $_SESSION['observer_name'] = 'بەڕێوەبەر';
}

// Get overall voting statistics for all centers
try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_voters,
            SUM(CASE WHEN status = 'voted' THEN 1 ELSE 0 END) as voted_count,
            SUM(CASE WHEN status = 'not_voted' OR status = '' OR status IS NULL THEN 1 ELSE 0 END) as not_voted_count
        FROM voters
    ");
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    $total_voters = $stats['total_voters'] ?? 0;
    $voted_count = $stats['voted_count'] ?? 0;
    $not_voted_count = $stats['not_voted_count'] ?? 0;
    $participation_rate = $total_voters > 0 ? round(($voted_count / $total_voters) * 100, 1) : 0;
} catch(PDOException $e) {
    $error = "هەڵە لە خوێندنەوەی ئامارەکان: " . $e->getMessage();
    $total_voters = $voted_count = $not_voted_count = 0;
    $participation_rate = 0;
}

// Get hourly voting statistics for all centers
try {
    $stmt = $pdo->prepare("
        SELECT 
            HOUR(scanned_at) as hour,
            COUNT(*) as votes_count
        FROM voters 
        WHERE status = 'voted' AND DATE(scanned_at) = CURDATE()
        GROUP BY HOUR(scanned_at)
        ORDER BY hour
    ");
    $stmt->execute();
    $hourly_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $hourly_stats = [];
}

// Get recent voting activity from all centers
try {
    $stmt = $pdo->prepare("
        SELECT full_name, voter_number, scanned_at, voting_center_name 
        FROM voters 
        WHERE status = 'voted' 
        ORDER BY scanned_at DESC 
        LIMIT 20
    ");
    $stmt->execute();
    $recent_votes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $recent_votes = [];
}

// Get all voting centers dynamically from database
try {
    // Get lowest voting centers
    $stmt = $pdo->query("
        SELECT 
            voting_center_name,
            COUNT(*) as total_voters,
            SUM(CASE WHEN status = 'voted' THEN 1 ELSE 0 END) as voted_count,
            ROUND((SUM(CASE WHEN status = 'voted' THEN 1 ELSE 0 END) * 100.0 / COUNT(*)), 2) as participation_rate
        FROM voters
        GROUP BY voting_center_name
        ORDER BY participation_rate ASC, voted_count ASC
    ");
    $lowest_centers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->query("
        SELECT 
            voting_center_name,
            COUNT(*) as total_voters,
            SUM(CASE WHEN status = 'voted' THEN 1 ELSE 0 END) as voted_count
        FROM voters
        GROUP BY voting_center_name
        ORDER BY voting_center_name
    ");
    $all_centers_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $all_centers_stats = [];
}
?>

<!DOCTYPE html>
<html lang="ku">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>داشبۆردی چاودێر</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            direction: rtl;
            min-height: 100vh;
            padding: 20px;
        }
        .dashboard-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #2c3e50, #4a6491);
            color: white;
            padding: 20px;
            text-align: center;
        }
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-left: 4px solid;
        }
        .stats-card.primary { border-left-color: #007bff; }
        .stats-card.success { border-left-color: #28a745; }
        .stats-card.warning { border-left-color: #ffc107; }
        .stats-card.info { border-left-color: #17a2b8; }
        
        .stats-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin: 0;
        }
        .stats-label {
            color: #6c757d;
            font-size: 0.9rem;
            margin-top: 5px;
        }
        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .activity-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .activity-item:last-child {
            border-bottom: none;
        }
        .time-badge {
            background: #e9ecef;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
        }
        .navbar-custom {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
        }
        .voting-center-card {
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            height: 100%;
        }
        .voting-center-card .card-header {
            padding: 10px 15px;
        }
        .voting-center-card .card-header h5 {
            font-size: 0.9rem;
            margin: 0;
        }
        .voting-center-card .card-body {
            padding: 15px;
        }
        .voting-center-card .card-footer {
            padding: 10px 15px;
        }
        .voting-center-card .progress-circle {
            margin-bottom: 10px;
        }
        .voting-center-card .progress-ring {
            width: 60px;
            height: 60px;
        }
        .voting-center-card .progress-text {
            font-size: 1rem;
        }
        .voting-center-card .stat-number {
            font-size: 1.2rem;
            font-weight: bold;
        }
        .voting-center-card .stat-label {
            font-size: 0.7rem;
            margin-top: 2px;
        }
        .voting-center-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border-color: #007bff;
        }
        .voting-center-card.current-center {
            border-color: #28a745;
            background: linear-gradient(135deg, #f8fff9, #ffffff);
        }
        .progress-circle {
            position: relative;
            display: inline-block;
        }
        .progress-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 1.2rem;
            font-weight: bold;
            color: #28a745;
        }
        .progress-ring circle {
            fill: transparent;
            stroke-width: 6;
            stroke-linecap: round;
        }
        .progress-ring .background {
            stroke: #e9ecef;
        }
        .progress-ring .progress {
            stroke: #28a745;
            stroke-dasharray: 188;
            stroke-dashoffset: 188;
            transform: rotate(-90deg);
            transform-origin: 50% 50%;
            transition: stroke-dashoffset 1s ease-in-out;
        }
        .stat-item {
            padding: 5px;
        }
        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            margin: 0;
        }
        .stat-label {
            font-size: 0.75rem;
            color: #6c757d;
            margin-top: 2px;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-custom fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="bi bi-speedometer2"></i> داش بۆردی چاودێری دەنگدانی تایبەت / پانێڵی وەزارەتی پێشمەرگە
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="observer_scanner.php">
                    <i class="bi bi-qr-code-scan"></i> سکانەر
                </a>
                <a class="nav-link" href="logout.php">
                    <i class="bi bi-box-arrow-right"></i> دەرچوون
                </a>
            </div>
        </div>
    </nav>

    <div style="margin-top: 80px;">
        <div class="dashboard-container">
            <div class="header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-1">داش بۆردی چاودێری دەنگدانی تایبەت</h4>
                        <p class="mb-0">پانێڵی وەزارەتی پێشمەرگە - ئاماری گشتی دەنگدان</p>
                    </div>
                    <div>
                        <button type="button" class="btn btn-danger btn-lg" onclick="openLowestCentersModal()">
                            <i class="bi bi-exclamation-triangle"></i> کەمترین بنکەی دەنگدان (5)
                        </button>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row">
                <div class="col-md-3">
                    <div class="stats-card primary">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="stats-number text-primary"><?php echo $total_voters; ?></h3>
                                <p class="stats-label">کۆی دەنگدەران</p>
                            </div>
                            <i class="bi bi-people-fill text-primary" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card success">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="stats-number text-success"><?php echo $voted_count; ?></h3>
                                <p class="stats-label">دەنگیداوە</p>
                            </div>
                            <i class="bi bi-check-circle-fill text-success" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card warning">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="stats-number text-warning"><?php echo $not_voted_count; ?></h3>
                                <p class="stats-label">دەنگینەداوە</p>
                            </div>
                            <i class="bi bi-clock-fill text-warning" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card info">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="stats-number text-info"><?php echo $participation_rate; ?>%</h3>
                                <p class="stats-label">ڕێژەی بەشداری</p>
                            </div>
                            <div class="text-center">
                                <svg class="progress-ring" width="60" height="60">
                                    <circle class="background" cx="30" cy="30" r="25"></circle>
                                    <circle class="progress" cx="30" cy="30" r="25" 
                                            style="stroke-dashoffset: <?php echo 283 - (283 * $participation_rate / 100); ?>"></circle>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Voting Centers Cards -->
            <div class="row">
                <?php foreach ($all_centers_stats as $center): ?>
                    <?php 
                    $center_rate = $center['total_voters'] > 0 ? round(($center['voted_count'] / $center['total_voters']) * 100, 1) : 0;
                    $not_voted = $center['total_voters'] - $center['voted_count'];
                    ?>
                    <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-4">
                        <div class="card voting-center-card" 
                             data-center="<?php echo htmlspecialchars($center['voting_center_name']); ?>"
                             onclick="openCenterModal('<?php echo htmlspecialchars($center['voting_center_name']); ?>', '<?php echo htmlspecialchars($center['voting_center_name']); ?>')">
                            <div class="card-header text-center">
                                <h5 class="mb-0">
                                    <i class="bi bi-building"></i>
                                    <?php echo htmlspecialchars($center['voting_center_name']); ?>
                                </h5>
                            </div>
                            <div class="card-body text-center">
                                <div class="progress-circle mb-3">
                                    <svg class="progress-ring" width="60" height="60">
                                        <circle class="background" cx="30" cy="30" r="25"></circle>
                                        <circle class="progress" cx="30" cy="30" r="25" 
                                                style="stroke-dashoffset: <?php echo 157 - (157 * $center_rate / 100); ?>"></circle>
                                    </svg>
                                    <div class="progress-text"><?php echo $center_rate; ?>%</div>
                                </div>
                            </div>
                            <div class="card-footer">
                                <div class="row text-center">
                                    <div class="col-4">
                                        <div class="stat-item">
                                            <div class="stat-number text-primary"><?php echo $center['total_voters']; ?></div>
                                            <div class="stat-label">کۆی دەنگدەران</div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="stat-item">
                                            <div class="stat-number text-success"><?php echo $center['voted_count']; ?></div>
                                            <div class="stat-label">دەنگیداوە</div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="stat-item">
                                            <div class="stat-number text-warning"><?php echo $not_voted; ?></div>
                                            <div class="stat-label">دەنگینەداوە</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Center Details Modal -->
    <div class="modal fade" id="centerModal" tabindex="-1" aria-labelledby="centerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="centerModalLabel">
                        <i class="bi bi-building"></i>
                        <span id="modalCenterName">تفصیلی بنکە</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="btn-group" role="group">
                                    <input type="radio" class="btn-check" name="voterType" id="votedList" autocomplete="off" checked>
                                    <label class="btn btn-outline-success" for="votedList">
                                        <i class="bi bi-check-circle"></i> دەنگیداوە (<span id="votedCount">0</span>)
                                    </label>
                                    
                                    <input type="radio" class="btn-check" name="voterType" id="notVotedList" autocomplete="off">
                                    <label class="btn btn-outline-warning" for="notVotedList">
                                        <i class="bi bi-clock"></i> دەنگینەداوە (<span id="notVotedCount">0</span>)
                                    </label>
                                </div>
                                <button type="button" class="btn btn-primary" onclick="printList()">
                                    <i class="bi bi-printer"></i> پرینت
                                </button>
                            </div>
                            
                            <!-- Search Box -->
                            <div class="mb-3">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-search"></i>
                                    </span>
                                    <input type="text" class="form-control" id="modalSearchInput" placeholder="گەڕان بە ناو، ژمارەی دەنگدەر، موبایل یان یەکە...">
                                    <button class="btn btn-outline-secondary" type="button" id="clearModalSearch">
                                        <i class="bi bi-x"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>ناوی تەواو</th>
                                    <th>ژمارەی دەنگدەر</th>
                                    <th>ژمارەی موبایل</th>
                                    <th>یەکە</th>
                                    <th>بنکەی دەنگدان</th>
                                    <th>کاتی سکان</th>
                                </tr>
                            </thead>
                            <tbody id="modalTableBody">
                                <!-- Data will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                    
                    <div id="modalNoResults" class="text-center text-muted py-4" style="display: none;">
                        <i class="bi bi-search" style="font-size: 2rem; opacity: 0.3;"></i>
                        <p class="mt-2 mb-0">هیچ ئەنجامێک نەدۆزرایەوە</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">داخستن</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Lowest Centers Modal -->
    <div class="modal fade" id="lowestCentersModal" tabindex="-1" aria-labelledby="lowestCentersModalLabel" aria-hidden="true" style="z-index: 1060;">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="lowestCentersModalLabel">
                        <i class="bi bi-exclamation-triangle"></i> کەمترین بنکەی دەنگدان
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-outline-primary" id="prevBtn" onclick="navigateLowestCenters(-1)">
                                <i class="bi bi-chevron-left"></i> پێشوو
                            </button>
                            <button type="button" class="btn btn-outline-primary" id="nextBtn" onclick="navigateLowestCenters(1)">
                                دواتر <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>
                        <div class="text-muted">
                            <span id="currentPage">1</span> لە <span id="totalPages">1</span>
                        </div>
                    </div>
                    
                    <div class="row" id="lowestCentersContainer">
                        <!-- Centers will be loaded here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">داخستن</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentCenterData = {
            voted: [],
            notVoted: []
        };

        function openCenterModal(centerId, centerName) {
            document.getElementById('modalCenterName').textContent = centerName;
            
            // Fetch data for this center
            fetch('get_center_details.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    center_id: centerId
                })
            })
            .then(response => response.json())
            .then(data => {
                console.log('Received data:', data); // Debug log
                
                // Check if data structure is correct
                if (!data || typeof data !== 'object') {
                    throw new Error('Invalid data structure received');
                }
                
                // Ensure voted and notVoted arrays exist
                if (!data.voted) data.voted = [];
                if (!data.notVoted) data.notVoted = [];
                
                currentCenterData = data;
                
                // Update counts
                document.getElementById('votedCount').textContent = data.voted.length;
                document.getElementById('notVotedCount').textContent = data.notVoted.length;
                
                // Show voted list by default
                document.getElementById('votedList').checked = true;
                showVoterList('voted');
                
                // Show modal
                const modal = new bootstrap.Modal(document.getElementById('centerModal'));
                modal.show();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('هەڵەیەک ڕوویدا لە هێنانی زانیاریەکان');
            });
        }

        function showVoterList(type) {
            const tableBody = document.getElementById('modalTableBody');
            
            // Check if currentCenterData exists and has the required type
            if (!currentCenterData || !currentCenterData[type]) {
                console.error('Data not available for type:', type);
                tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">هیچ زانیاریەک نییە</td></tr>';
                return;
            }
            
            const data = currentCenterData[type];
            tableBody.innerHTML = '';
            
            if (!Array.isArray(data) || data.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">هیچ زانیاریەک نییە</td></tr>';
                return;
            }
            
            data.forEach(voter => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${voter.full_name || 'نەزانراو'}</td>
                    <td>${voter.voter_number || 'نەزانراو'}</td>
                    <td>${voter.mobile_number || 'تۆمار نەکراوە'}</td>
                    <td>${voter.unit || 'تۆمار نەکراوە'}</td>
                    <td>${voter.voting_center_name || 'نەزانراو'}</td>
                    <td>${voter.scan_time || 'دەنگینەداوە'}</td>
                `;
                tableBody.appendChild(row);
            });
        }

        // Event listeners for radio buttons
        document.getElementById('votedList').addEventListener('change', function() {
            if (this.checked) {
                showVoterList('voted');
            }
        });

        document.getElementById('notVotedList').addEventListener('change', function() {
            if (this.checked) {
                showVoterList('notVoted');
            }
        });

        // Search functionality
        document.getElementById('modalSearchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#modalTableBody tr');
            let visibleRows = 0;
            
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length > 1) {
                    const name = cells[0].textContent.toLowerCase();
                    const voterNumber = cells[1].textContent.toLowerCase();
                    const mobile = cells[2].textContent.toLowerCase();
                    const unit = cells[3].textContent.toLowerCase();
                    
                    if (name.includes(searchTerm) || voterNumber.includes(searchTerm) || 
                        mobile.includes(searchTerm) || unit.includes(searchTerm)) {
                        row.style.display = '';
                        visibleRows++;
                    } else {
                        row.style.display = 'none';
                    }
                }
            });
            
            // Show/hide no results message
            const noResults = document.getElementById('modalNoResults');
            if (visibleRows === 0 && searchTerm !== '') {
                noResults.style.display = 'block';
            } else {
                noResults.style.display = 'none';
            }
        });

        // Clear search
        document.getElementById('clearModalSearch').addEventListener('click', function() {
            document.getElementById('modalSearchInput').value = '';
            document.getElementById('modalSearchInput').dispatchEvent(new Event('input'));
        });

        // Print functionality
        function printList() {
            const activeTab = document.querySelector('input[name="voterType"]:checked').id;
            const listType = activeTab === 'votedList' ? 'voted' : 'notVoted';
            const centerName = document.getElementById('modalCenterName').textContent;
            const data = currentCenterData[listType];
            
            if (data.length === 0) {
                alert('هیچ زانیاریەک نییە بۆ پرینت کردن');
                return;
            }
            
            // Create print window
            const printWindow = window.open('', '_blank');
            const listTitle = listType === 'voted' ? 'لیستی ناوی دەنگدەران' : 'لیستی ناوی دەنگدەران';
            
            let printContent = `
                <!DOCTYPE html>
                <html dir="rtl">
                <head>
                    <meta charset="UTF-8">
                    <title>${listTitle} - ${centerName}</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 10px; }
                        .center-name { font-size: 24px; font-weight: bold; margin-bottom: 10px; }
                        .list-type { font-size: 18px; color: #666; }
                        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                        th, td { border: 1px solid #ddd; padding: 8px; text-align: right; }
                        th { background-color: #f2f2f2; font-weight: bold; }
                        .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
                        @media print { body { margin: 0; } }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <div class="center-name">${centerName}</div>
                        <div class="list-type">${listTitle}</div>
                        <div style="font-size: 14px; margin-top: 10px;">کۆی گشتی: ${data.length} کەس</div>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>ژ.</th>
                                <th>ناوی تەواو</th>
                                <th>ژمارەی دەنگدەر</th>
                                <th>ژمارەی موبایل</th>
                                <th>یەکە</th>
                                <th>بنکەی دەنگدان</th>
                                ${listType === 'voted' ? '<th>کاتی سکان</th>' : ''}
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            data.forEach((voter, index) => {
                printContent += `
                    <tr>
                        <td>${index + 1}</td>
                        <td>${voter.full_name}</td>
                        <td>${voter.voter_number}</td>
                        <td>${voter.mobile_number || 'تۆمار نەکراوە'}</td>
                        <td>${voter.unit || 'تۆمار نەکراوە'}</td>
                        <td>${voter.voting_center_name}</td>
                        ${listType === 'voted' ? `<td>${voter.scan_time || ''}</td>` : ''}
                    </tr>
                `;
            });
            
            printContent += `
                        </tbody>
                    </table>
                    <div class="footer">
                        چاپکراو لە: ${new Date().toLocaleDateString('ku')} - ${new Date().toLocaleTimeString('ku')}
                    </div>
                </body>
                </html>
            `;
            
            printWindow.document.write(printContent);
            printWindow.document.close();
            printWindow.focus();
            printWindow.print();
        }

        // Lowest Centers Modal Functions
        let lowestCentersData = <?php echo json_encode($lowest_centers); ?>;
        let currentLowestPage = 0;
        const centersPerPage = 5;

        function openLowestCentersModal() {
            currentLowestPage = 0;
            displayLowestCenters();
            const modal = new bootstrap.Modal(document.getElementById('lowestCentersModal'));
            modal.show();
        }

        function displayLowestCenters() {
            const container = document.getElementById('lowestCentersContainer');
            const startIndex = currentLowestPage * centersPerPage;
            const endIndex = Math.min(startIndex + centersPerPage, lowestCentersData.length);
            const totalPages = Math.ceil(lowestCentersData.length / centersPerPage);
            
            // Update pagination info
            document.getElementById('currentPage').textContent = currentLowestPage + 1;
            document.getElementById('totalPages').textContent = totalPages;
            
            // Update navigation buttons
            document.getElementById('prevBtn').disabled = currentLowestPage === 0;
            document.getElementById('nextBtn').disabled = currentLowestPage >= totalPages - 1;
            
            // Clear container
            container.innerHTML = '';
            
            // Display centers for current page
            for (let i = startIndex; i < endIndex; i++) {
                const center = lowestCentersData[i];
                const centerCard = `
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="card border-danger">
                            <div class="card-header bg-danger text-white text-center">
                                <h6 class="mb-0">
                                    <i class="bi bi-building"></i>
                                    ${center.voting_center_name}
                                </h6>
                            </div>
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <div class="display-6 text-danger">${center.participation_rate}%</div>
                                    <small class="text-muted">ڕێژەی بەشداری</small>
                                </div>
                                <div class="row text-center">
                                    <div class="col-6">
                                        <div class="text-primary fw-bold">${center.total_voters}</div>
                                        <small class="text-muted">کۆی دەنگدەران</small>
                                    </div>
                                    <div class="col-6">
                                        <div class="text-success fw-bold">${center.voted_count}</div>
                                        <small class="text-muted">دەنگیداوە</small>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer text-center">
                                <button class="btn btn-sm btn-outline-primary" onclick="openCenterModalFromLowest('${center.voting_center_name}', '${center.voting_center_name}')">
                                    <i class="bi bi-eye"></i> بینینی وردەکاری
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                container.innerHTML += centerCard;
            }
        }

        function navigateLowestCenters(direction) {
            const totalPages = Math.ceil(lowestCentersData.length / centersPerPage);
            currentLowestPage += direction;
            
            if (currentLowestPage < 0) currentLowestPage = 0;
            if (currentLowestPage >= totalPages) currentLowestPage = totalPages - 1;
            
            displayLowestCenters();
        }

        // Function to open center modal from lowest centers modal
        function openCenterModalFromLowest(centerId, centerName) {
            // First hide the lowest centers modal
            const lowestModal = bootstrap.Modal.getInstance(document.getElementById('lowestCentersModal'));
            if (lowestModal) {
                lowestModal.hide();
            }
            
            // Wait for the modal to be hidden, then open the center modal
            setTimeout(() => {
                openCenterModal(centerId, centerName);
            }, 300);
        }

        // Auto-refresh functionality
        function refreshDashboard() {
            location.reload();
        }

        // Auto-refresh every 30 seconds
        setInterval(refreshDashboard, 30000);
    </script>
</body>
</html>