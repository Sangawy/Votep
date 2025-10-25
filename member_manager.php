<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'auth.php';
require_once 'config.php';

// Require authentication with code 0330
requireAuth();

// کردارەکانی CRUD
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$member_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';

// زیادکردنی ئەندامی نوێ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
    $full_name = $_POST['full_name'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $voting_center = $_POST['voting_center'] ?? '';
    $voter_number = $_POST['voter_number'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $birth_year = $_POST['birth_year'] ?? '';
    $province = $_POST['province'] ?? '';
    $district = $_POST['district'] ?? '';
    
    if (!empty($full_name) && !empty($voter_number)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO voters (full_name, mobile, voting_center_name, voter_number, gender, birth_year, province, district) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$full_name, $phone, $voting_center, $voter_number, $gender, $birth_year, $province, $district]);
            $message = "ئەندامی نوێ بە سەرکەوتوویی زیادکرا";
            $action = 'list'; // گەڕانەوە بۆ لیست
        } catch (PDOException $e) {
            $message = "هەڵە لە زیادکردن: " . $e->getMessage();
        }
    } else {
        $message = "تکایە هەموو خانەکان پڕ بکەوە";
    }
}

// نوێکردنەوەی زانیاری ئەندام
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'edit') {
    $full_name = $_POST['full_name'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $voting_center = $_POST['voting_center'] ?? '';
    $voter_number = $_POST['voter_number'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $birth_year = $_POST['birth_year'] ?? '';
    $province = $_POST['province'] ?? '';
    $district = $_POST['district'] ?? '';
    $member_id = $_POST['member_id'] ?? 0;
    
    if (!empty($full_name) && !empty($voter_number) && $member_id > 0) {
        try {
            $stmt = $pdo->prepare("UPDATE voters SET full_name = ?, mobile = ?, voting_center_name = ?, voter_number = ?, gender = ?, birth_year = ?, province = ?, district = ? WHERE id = ?");
            $stmt->execute([$full_name, $phone, $voting_center, $voter_number, $gender, $birth_year, $province, $district, $member_id]);
            $message = "زانیاری ئەندام بە سەرکەوتوویی نوێکرایەوە";
            $action = 'list'; // گەڕانەوە بۆ لیست
        } catch (PDOException $e) {
            $message = "هەڵە لە نوێکردنەوە: " . $e->getMessage();
        }
    } else {
        $message = "تکایە هەموو خانەکان پڕ بکەوە";
    }
}

// سڕینەوەی ئەندام
if ($action === 'delete' && $member_id > 0) {
    try {
        $stmt = $pdo->prepare("DELETE FROM voters WHERE id = ?");
        $stmt->execute([$member_id]);
        $message = "ئەندام بە سەرکەوتوویی سڕایەوە";
        $action = 'list'; // گەڕانەوە بۆ لیست
    } catch (PDOException $e) {
        $message = "هەڵە لە سڕینەوە: " . $e->getMessage();
    }
}

// وەرگرتنی زانیاری ئەندام بۆ دەستکاریکردن
$member_data = [];
if ($action === 'edit' && $member_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM voters WHERE id = ?");
        $stmt->execute([$member_id]);
        $member_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$member_data) {
            $message = "ئەندام نەدۆزرایەوە";
            $action = 'list';
        }
    } catch (PDOException $e) {
        $message = "هەڵە لە وەرگرتنی زانیاری: " . $e->getMessage();
    }
}

// وەرگرتنی لیستی ئەندامەکان
$members = [];
$search = isset($_GET['search']) ? $_GET['search'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

if ($action === 'list') {
    try {
        $query = "SELECT id, full_name, mobile as phone, voting_center_name, status, voter_number, gender, birth_year, province, district FROM voters";
        $countQuery = "SELECT COUNT(*) FROM voters";
        $params = [];
        
        if (!empty($search)) {
            $query .= " WHERE full_name LIKE ? OR voter_number LIKE ? OR mobile LIKE ?";
            $countQuery .= " WHERE full_name LIKE ? OR voter_number LIKE ? OR mobile LIKE ?";
            $searchParam = "%$search%";
            $params = [$searchParam, $searchParam, $searchParam];
        }
        
        $query .= " ORDER BY id DESC LIMIT $offset, $per_page";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $countStmt = $pdo->prepare($countQuery);
        $countStmt->execute($params);
        $total_members = $countStmt->fetchColumn();
        $total_pages = ceil($total_members / $per_page);
    } catch (PDOException $e) {
        $message = "هەڵە لە وەرگرتنی لیست: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="ku" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بەڕێوەبردنی ئەندامەکان</title>
    <link href="bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="bootstrap-icons.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            padding: 20px;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        .card-header {
            background-color: #6c757d;
            color: white;
            border-radius: 10px 10px 0 0 !important;
        }
        .btn-action {
            margin-right: 5px;
        }
        .search-box {
            margin-bottom: 20px;
        }
        .pagination {
            justify-content: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="text-center mb-4">بەڕێوەبردنی ئەندامەکان</h2>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($action === 'list'): ?>
            <!-- گەڕان و زیادکردن -->
            <div class="row search-box">
                <div class="col-md-8">
                    <form method="GET" class="d-flex">
                        <input type="text" name="search" class="form-control me-2" placeholder="گەڕان بەدوای ناو، ژمارەی کارت یان مۆبایل" value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn btn-primary">گەڕان</button>
                    </form>
                </div>
                <div class="col-md-4 text-end">
                    <a href="?action=add" class="btn btn-success">
                        <i class="bi bi-plus-circle"></i> زیادکردنی ئەندامی نوێ
                    </a>
                </div>
            </div>
            
            <!-- لیستی ئەندامەکان -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">لیستی ئەندامەکان</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th style="width: 5%;">#</th>
                                    <th style="width: 20%;">ناوی تەواو</th>
                                    <th style="width: 15%;">ژمارەی دەنگدەر</th>
                                    <th style="width: 12%;">مۆبایل</th>
                                    <th style="width: 20%;">بنکەی دەنگدان</th>
                                    <th style="width: 10%;">دۆخ</th>
                                    <th style="width: 18%;">کردارەکان</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($members)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center">هیچ ئەندامێک نەدۆزرایەوە</td>
                                    </tr>
                                <?php else: ?>
                                    <?php 
                                    echo "<!-- Debug: Found " . count($members) . " members -->";
                                    foreach ($members as $index => $member): 
                                    ?>
                                        <tr>
                                            <td><?php echo $offset + $index + 1; ?></td>
                                            <td><?php echo htmlspecialchars($member['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($member['voter_number'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($member['phone'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($member['voting_center_name'] ?? ''); ?></td>
                                            <td>
                                <?php if (isset($member['status']) && $member['status'] === 'voted'): ?>
                                    <span class="badge bg-success text-white">دەنگیداوە</span>
                                <?php else: ?>
                                    <span class="badge bg-danger text-white">دەنگینەداوە</span>
                                <?php endif; ?>
                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info btn-action" data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $member['id']; ?>">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <a href="?action=edit&id=<?php echo $member['id']; ?>" class="btn btn-sm btn-primary btn-action">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <a href="?action=delete&id=<?php echo $member['id']; ?>" class="btn btn-sm btn-danger btn-action" onclick="return confirm('دڵنیایت لە سڕینەوەی ئەم ئەندامە؟');">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- پەڕەبەندی -->
                    <?php if (isset($total_pages) && $total_pages > 1): ?>
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>">
                                            <span>&laquo;</span> پێشوو
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                if ($start_page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=1&search=<?php echo urlencode($search); ?>">1</a>
                                    </li>
                                    <?php if ($start_page > 2): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($end_page < $total_pages): ?>
                                    <?php if ($end_page < $total_pages - 1): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php endif; ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>"><?php echo $total_pages; ?></a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>">
                                            دواتر <span>&raquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
            
        <?php elseif ($action === 'add'): ?>
            <!-- فۆرمی زیادکردنی ئەندامی نوێ -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">زیادکردنی ئەندامی نوێ</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="?action=add">
                        <div class="mb-3">
                            <label for="full_name" class="form-label">ناوی تەواو</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="voter_number" class="form-label">ژمارەی دەنگدەر</label>
                            <input type="text" class="form-control" id="voter_number" name="voter_number" required>
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label">ژمارەی مۆبایل</label>
                            <input type="text" class="form-control" id="phone" name="phone">
                        </div>
                        <div class="mb-3">
                            <label for="voting_center" class="form-label">بنکەی دەنگدان</label>
                            <input type="text" class="form-control" id="voting_center" name="voting_center" required>
                        </div>
                        <div class="mb-3">
                            <label for="gender" class="form-label">ڕەگەز</label>
                            <input type="text" class="form-control" id="gender" name="gender">
                        </div>
                        <div class="mb-3">
                            <label for="birth_year" class="form-label">ساڵی لەدایکبوون</label>
                            <input type="number" class="form-control" id="birth_year" name="birth_year">
                        </div>
                        <div class="mb-3">
                            <label for="province" class="form-label">پارێزگا</label>
                            <input type="text" class="form-control" id="province" name="province">
                        </div>
                        <div class="mb-3">
                            <label for="district" class="form-label">قەزا</label>
                            <input type="text" class="form-control" id="district" name="district">
                        </div>
                        <div class="d-flex justify-content-between">
                            <a href="?action=list" class="btn btn-secondary">گەڕانەوە</a>
                            <button type="submit" class="btn btn-success">زیادکردن</button>
                        </div>
                    </form>
                </div>
            </div>
            
        <?php elseif ($action === 'edit' && !empty($member_data)): ?>
            <!-- فۆرمی دەستکاریکردنی ئەندام -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">دەستکاریکردنی ئەندام</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="?action=edit">
                        <input type="hidden" name="member_id" value="<?php echo $member_data['id']; ?>">
                        <div class="mb-3">
                            <label for="full_name" class="form-label">ناوی تەواو</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($member_data['full_name']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="voter_number" class="form-label">ژمارەی دەنگدەر</label>
                            <input type="text" class="form-control" id="voter_number" name="voter_number" value="<?php echo htmlspecialchars($member_data['voter_number'] ?? ''); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label">ژمارەی مۆبایل</label>
                            <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($member_data['mobile'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="voting_center" class="form-label">بنکەی دەنگدان</label>
                            <input type="text" class="form-control" id="voting_center" name="voting_center" value="<?php echo htmlspecialchars($member_data['voting_center_name']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="gender" class="form-label">ڕەگەز</label>
                            <input type="text" class="form-control" id="gender" name="gender" value="<?php echo htmlspecialchars($member_data['gender'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="birth_year" class="form-label">ساڵی لەدایکبوون</label>
                            <input type="number" class="form-control" id="birth_year" name="birth_year" value="<?php echo htmlspecialchars($member_data['birth_year'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="province" class="form-label">پارێزگا</label>
                            <input type="text" class="form-control" id="province" name="province" value="<?php echo htmlspecialchars($member_data['province'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="district" class="form-label">قەزا</label>
                            <input type="text" class="form-control" id="district" name="district" value="<?php echo htmlspecialchars($member_data['district'] ?? ''); ?>">
                        </div>
                        <div class="d-flex justify-content-between">
                            <a href="?action=list" class="btn btn-secondary">گەڕانەوە</a>
                            <button type="submit" class="btn btn-primary">نوێکردنەوە</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- مۆداڵەکانی پیشاندانی زانیاری -->
        <?php if ($action === 'list' && !empty($members)): ?>
            <?php foreach ($members as $member): ?>
                <div class="modal fade" id="viewModal<?php echo $member['id']; ?>" tabindex="-1" aria-labelledby="viewModalLabel<?php echo $member['id']; ?>" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="viewModalLabel<?php echo $member['id']; ?>">زانیاری تەواوی ئەندام</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>زانیاری سەرەکی</h6>
                                        <table class="table table-borderless">
                                            <tr>
                                                <td><strong>ناوی تەواو:</strong></td>
                                                <td><?php echo htmlspecialchars($member['full_name']); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>ژمارەی دەنگدەر:</strong></td>
                                                <td><?php echo htmlspecialchars($member['voter_number'] ?? 'نەدۆزرایەوە'); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>ژمارەی مۆبایل:</strong></td>
                                                <td><?php echo htmlspecialchars($member['phone'] ?? 'نەدۆزرایەوە'); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>بنکەی دەنگدان:</strong></td>
                                                <td><?php echo htmlspecialchars($member['voting_center_name'] ?? 'نەدۆزرایەوە'); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>دۆخ:</strong></td>
                                                <td>
                                                    <?php if (isset($member['status']) && $member['status'] === 'voted'): ?>
                                                        <span class="badge bg-success text-white">دەنگیداوە</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger text-white">دەنگینەداوە</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>زانیاری زیاتر</h6>
                                        <table class="table table-borderless">
                                            <tr>
                                                <td><strong>ژمارەی دەنگدەر:</strong></td>
                                                <td><?php echo htmlspecialchars($member['voter_number'] ?? 'نەدۆزرایەوە'); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>ڕەگەز:</strong></td>
                                                <td><?php echo htmlspecialchars($member['gender'] ?? 'نەدۆزرایەوە'); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>ساڵی لەدایکبوون:</strong></td>
                                                <td><?php echo htmlspecialchars($member['birth_year'] ?? 'نەدۆزرایەوە'); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>پارێزگا:</strong></td>
                                                <td><?php echo htmlspecialchars($member['province'] ?? 'نەدۆزرایەوە'); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>قەزا:</strong></td>
                                                <td><?php echo htmlspecialchars($member['district'] ?? 'نەدۆزرایەوە'); ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">داخستن</button>
                                <a href="?action=edit&id=<?php echo $member['id']; ?>" class="btn btn-primary">دەستکاریکردن</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <script src="bootstrap.bundle.min.js"></script>
</body>
</html>