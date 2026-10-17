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
    echo "هیچ هەڵبژاردنێکی چالاک نییە.";
    exit;
}

// Fetch system settings for logo
$settings = $conn->query("SELECT * FROM settings WHERE id = 1")->fetch_assoc();
$logo_path = ($settings && !empty($settings['logo'])) ? $settings['logo'] : 'assets/imgae/election.png';

// Handle position filtering
$selected_position_id = isset($_GET['pos_id']) ? (int)$_GET['pos_id'] : 0;
$pos_filter = $selected_position_id > 0 ? " AND c.position_id = $selected_position_id" : "";

// Fetch positions for the filter buttons
$positions_list = [];
$res_p = $conn->query("SELECT * FROM election_positions WHERE election_id = {$election['id']} ORDER BY id ASC");
while ($row = $res_p->fetch_assoc()) $positions_list[] = $row;

// Fetch candidates grouped by position
$query = "SELECT c.*, m.full_name, m.nickname, m.member_code, m.profile_image, p.position_name, f.family_name
          FROM candidates c
          JOIN members m ON c.member_id = m.id
          JOIN election_positions p ON c.position_id = p.id
          LEFT JOIN families f ON m.family_id = f.id
          WHERE c.election_id = {$election['id']} $pos_filter
          ORDER BY p.id ASC, c.created_at ASC";

$result = $conn->query($query);
$grouped_candidates = [];
while ($row = $result->fetch_assoc()) {
    $grouped_candidates[$row['position_name']][] = $row;
}
?>
<!DOCTYPE html>
<html lang="ckb" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>فۆڕمی دەنگدان - <?php echo htmlspecialchars($election['title']); ?></title>
    <style>
        @font-face {
            font-family: 'Rabar022';
            src: url('assets/font/Rabar_022.ttf') format('truetype');
        }
        body {
            font-family: 'Rabar022', sans-serif;
            margin: 0;
            padding: 20px;
            background: #fff;
            color: #333;
        }
        .ballot-container {
            max-width: 900px;
            margin: 0 auto;
            border: 1px solid #ccc;
            padding: 15px;
            position: relative;
        }
        .header {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 30px;
            margin-bottom: 15px;
            border-bottom: 3px solid #1a5653;
            padding-bottom: 10px;
            position: relative;
        }
        .header-logo {
            width: 80px;
            height: auto;
        }
        .header-text {
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 1.5rem;
            color: #1a5653;
        }
        .header h2 {
            margin: 5px 0 0 0;
            font-size: 1rem;
            color: #666;
        }
        .date {
            position: absolute;
            top: 5px;
            right: 0;
            font-size: 0.8rem;
            color: #888;
        }
        .position-section {
            margin-bottom: 15px;
        }
        .position-title {
            background: #f4f8f7;
            border-right: 6px solid #1a5653;
            padding: 5px 12px;
            font-size: 1rem;
            font-weight: bold;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .candidates-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .candidate-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 5px 10px;
            display: flex;
            align-items: center;
            background: #fff;
            height: 65px; /* Further reduced height */
            position: relative;
            break-inside: avoid;
            page-break-inside: avoid;
        }
        .voting-box {
            width: 28px;
            height: 28px;
            border: 2px solid #999;
            border-radius: 4px;
            margin-left: 10px;
            flex-shrink: 0;
        }
        .candidate-img {
            width: 45px;
            height: 45px;
            border-radius: 6px;
            object-fit: cover;
            border: 1px solid #eee;
            margin-left: 10px;
        }
        .candidate-info {
            flex-grow: 1;
            line-height: 1.1;
            min-width: 0;
        }
        .candidate-name {
            font-weight: bold;
            font-size: 0.9rem;
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .candidate-details {
            font-size: 0.7rem;
            color: #777;
        }
        .candidate-number {
            width: 22px;
            height: 22px;
            background: #1a5653;
            color: #fff;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 0.75rem;
            font-weight: bold;
            margin-right: auto; /* Push to the far left in RTL */
            flex-shrink: 0;
        }
        .footer-note {
            text-align: center;
            margin-top: 15px;
            font-size: 0.8rem;
            color: #888;
            border-top: 1px dashed #ccc;
            padding-top: 10px;
        }
        @media print {
            body { padding: 0 !important; }
            .ballot-container { border: none !important; width: 100% !important; max-width: none !important; margin: 0 !important; padding: 5px !important; }
            .no-print { display: none !important; }
        }
        .print-btn-fixed {
            position: fixed;
            bottom: 30px;
            left: 30px;
            background: #1a5653;
            color: #fff;
            border: none;
            padding: 15px 30px;
            border-radius: 50px;
            font-family: 'Rabar022';
            cursor: pointer;
            box-shadow: 0 5px 20px rgba(0,0,0,0.3);
            z-index: 1000;
            font-size: 1.1rem;
        }
    </style>
</head>
<body>

    <div class="no-print" style="position: fixed; top: 20px; left: 20px; display: flex; flex-direction: column; gap: 10px; z-index: 1000;">
        <button onclick="window.print()" style="background: #1a5653; color: #fff; border: none; padding: 12px 20px; border-radius: 8px; cursor: pointer; font-family: 'Rabar022'; font-weight: bold;">🖨️ چاپکردنی ئەم لاپەڕەیە</button>
        
        <div style="background: #fff; padding: 15px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border: 1px solid #eee;">
            <p style="margin: 0 0 10px 0; font-weight: bold; font-size: 0.9rem; color: #666;">پاڵاوتن بەپێی پۆست:</p>
            <a href="elections_ballot.php" style="display: block; padding: 8px; text-decoration: none; color: <?php echo $selected_position_id == 0 ? '#1a5653' : '#666'; ?>; font-weight: <?php echo $selected_position_id == 0 ? 'bold' : 'normal'; ?>;">🔘 هەموو پۆستەکان</a>
            <?php foreach ($positions_list as $pos): ?>
                <a href="elections_ballot.php?pos_id=<?php echo $pos['id']; ?>" style="display: block; padding: 8px; text-decoration: none; color: <?php echo $selected_position_id == $pos['id'] ? '#1a5653' : '#666'; ?>; font-weight: <?php echo $selected_position_id == $pos['id'] ? 'bold' : 'normal'; ?>;">🔹 <?php echo htmlspecialchars($pos['position_name']); ?></a>
            <?php endforeach; ?>
        </div>
        
        <a href="elections_candidates.php" style="background: #666; color: #fff; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-family: 'Rabar022'; text-decoration: none; text-align: center; font-size: 0.9rem;">➡️ گەڕانەوە</a>
    </div>

    <div class="ballot-container">
        <div class="date">بەروار: <?php echo date('Y/m/d'); ?></div>
        
        <div class="header">
            <img src="<?php echo $logo_path; ?>" class="header-logo" alt="Logo">
            <div class="header-text">
                <h1>مەڵبەندی ڕۆشنبیری و کۆمەڵایەتی ساداتی پیرخدری شاهۆ</h1>
                <h2>فۆڕمی دەنگدان - <?php echo htmlspecialchars($election['title']); ?></h2>
            </div>
        </div>

        <?php foreach ($grouped_candidates as $position_name => $candidates): ?>
            <div class="position-section">
                <div class="position-title">
                    <span>کاندیدەکانی <?php echo htmlspecialchars($position_name); ?></span>
                </div>
                
                <div class="candidates-grid">
                    <?php 
                    $counter = 1;
                    foreach ($candidates as $c): 
                    ?>
                        <div class="candidate-card">
                            <div class="voting-box"></div>
                            <img class="candidate-img" src="stream_image.php?path=<?php echo urlencode($c['profile_image'] ?: 'assets/imgae/user.png'); ?>" onerror="this.src='assets/imgae/user.png'">
                            <div class="candidate-info">
                                <div class="candidate-name"><?php echo htmlspecialchars($c['full_name']); ?></div>
                                <div class="candidate-details">
                                    <?php 
                                        $nickname = (string)($c['nickname'] ?? '');
                                        $family = (string)($c['family_name'] ?? '');
                                        $displayName = !empty($nickname) ? $nickname . ' - ' . $family : $family;
                                        echo htmlspecialchars($displayName); 
                                    ?>
                                </div>
                            </div>
                            <div class="candidate-number"><?php echo $counter++; ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="footer-note">
            تکایە بە وردی فۆڕمەکە پڕ بکەرەوە و لە چوارچێوەی تایبەت نیشانەکەت دابنێ
        </div>
    </div>

</body>
</html>
