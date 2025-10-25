<?php
require_once 'auth.php';
require_once 'config.php';

// Require authentication with code 0330
requireAuth();

// Handle CRUD operations
$message = '';
$message_type = '';

// Add new observer
if (isset($_POST['add_observer'])) {
    $full_name = trim($_POST['full_name']);
    $mobile = trim($_POST['mobile']);
    $password = trim($_POST['password']);
    $voting_center_name = trim($_POST['voting_center_name']);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO observers (full_name, mobile, password, voting_center_name) VALUES (?, ?, ?, ?)");
        $stmt->execute([$full_name, $mobile, $password, $voting_center_name]);
        $message = "چاودێری نوێ بە سەرکەوتوویی زیادکرا!";
        $message_type = "success";
    } catch(PDOException $e) {
        if ($e->getCode() == 23000) {
            $message = "ژمارەی مۆبایل پێشووتر بەکارهاتووە!";
        } else {
            $message = "هەڵە لە زیادکردنی چاودێر: " . $e->getMessage();
        }
        $message_type = "danger";
    }
}

// Update observer
if (isset($_POST['update_observer'])) {
    $id = $_POST['observer_id'];
    $full_name = trim($_POST['full_name']);
    $mobile = trim($_POST['mobile']);
    $password = trim($_POST['password']);
    $voting_center_name = trim($_POST['voting_center_name']);
    
    try {
        $stmt = $pdo->prepare("UPDATE observers SET full_name = ?, mobile = ?, password = ?, voting_center_name = ? WHERE id = ?");
        $stmt->execute([$full_name, $mobile, $password, $voting_center_name, $id]);
        $message = "زانیاری چاودێر بە سەرکەوتوویی نوێکرایەوە!";
        $message_type = "success";
    } catch(PDOException $e) {
        if ($e->getCode() == 23000) {
            $message = "ژمارەی مۆبایل پێشووتر بەکارهاتووە!";
        } else {
            $message = "هەڵە لە نوێکردنەوەی چاودێر: " . $e->getMessage();
        }
        $message_type = "danger";
    }
}

// Delete observer
if (isset($_POST['delete_observer'])) {
    $id = $_POST['observer_id'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM observers WHERE id = ?");
        $stmt->execute([$id]);
        $message = "چاودێر بە سەرکەوتوویی سڕایەوە!";
        $message_type = "success";
    } catch(PDOException $e) {
        $message = "هەڵە لە سڕینەوەی چاودێر: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Get all observers
try {
    $stmt = $pdo->query("SELECT * FROM observers ORDER BY created_at DESC");
    $observers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $observers = [];
    $message = "هەڵە لە خوێندنەوەی چاودێرەکان: " . $e->getMessage();
    $message_type = "danger";
}

// Get unique voting centers
try {
    $stmt = $pdo->query("SELECT DISTINCT voting_center_name FROM voters ORDER BY voting_center_name");
    $voting_centers = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch(PDOException $e) {
    $voting_centers = [];
}
?>

<!DOCTYPE html>
<html lang="ku">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بەڕێوەبردنی چاودێرەکان</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            direction: rtl;
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            padding: 30px;
            margin: 20px auto;
        }
        .header {
            background: linear-gradient(135deg, #2c3e50, #4a6491);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
        }
        .btn-primary {
            background: linear-gradient(135deg, #007bff, #0056b3);
            border: none;
        }
        .btn-success {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
        }
        .btn-danger {
            background: linear-gradient(135deg, #dc3545, #c82333);
            border: none;
        }
        .btn-warning {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            border: none;
        }
        .table th {
            background: #f8f9fa;
            border-top: none;
        }
        .modal-header {
            background: linear-gradient(135deg, #2c3e50, #4a6491);
            color: white;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2><i class="bi bi-people-fill"></i> بەڕێوەبردنی چاودێرەکان</h2>
            <p class="mb-0">زیادکردن، دەستکاریکردن و سڕینەوەی چاودێرەکان</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Add Observer Button -->
        <div class="mb-3">
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addObserverModal">
                <i class="bi bi-plus-circle"></i> زیادکردنی چاودێری نوێ
            </button>
        </div>

        <!-- Observers Table -->
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>ژ</th>
                        <th>ناوی تەواو</th>
                        <th>مۆبایل</th>
                        <th>بنکەی دەنگدان</th>
                        <th>بەرواری دروستکردن</th>
                        <th>کردارەکان</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($observers)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                <i class="bi bi-inbox display-4 d-block mb-2"></i>
                                هیچ چاودێرێک تۆمار نەکراوە
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($observers as $index => $observer): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo htmlspecialchars($observer['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($observer['mobile']); ?></td>
                                <td><?php echo htmlspecialchars($observer['voting_center_name']); ?></td>
                                <td><?php echo date('Y-m-d H:i', strtotime($observer['created_at'])); ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#viewObserverModal<?php echo $observer['id']; ?>">
                                        <i class="bi bi-eye"></i> بینین
                                    </button>
                                    <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editObserverModal<?php echo $observer['id']; ?>">
                                        <i class="bi bi-pencil"></i> دەستکاری
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteObserverModal<?php echo $observer['id']; ?>">
                                        <i class="bi bi-trash"></i> سڕینەوە
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Observer Modal -->
    <div class="modal fade" id="addObserverModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">زیادکردنی چاودێری نوێ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="full_name" class="form-label">ناوی تەواو</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="mobile" class="form-label">ژمارەی مۆبایل</label>
                            <input type="text" class="form-control" id="mobile" name="mobile" placeholder="07501234567" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">پاسۆرد</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label for="voting_center_name" class="form-label">بنکەی دەنگدان</label>
                            <select class="form-select" id="voting_center_name" name="voting_center_name" required>
                                <option value="">هەڵبژاردنی بنکە</option>
                                <?php foreach ($voting_centers as $center): ?>
                                    <option value="<?php echo htmlspecialchars($center); ?>"><?php echo htmlspecialchars($center); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">پاشگەزبوونەوە</button>
                        <button type="submit" name="add_observer" class="btn btn-success">زیادکردن</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php foreach ($observers as $observer): ?>
        <!-- View Observer Modal -->
        <div class="modal fade" id="viewObserverModal<?php echo $observer['id']; ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">زانیاری چاودێر</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>ناوی تەواو:</strong></td>
                                <td><?php echo htmlspecialchars($observer['full_name']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>ژمارەی مۆبایل:</strong></td>
                                <td><?php echo htmlspecialchars($observer['mobile']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>بنکەی دەنگدان:</strong></td>
                                <td><?php echo htmlspecialchars($observer['voting_center_name']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>بەرواری دروستکردن:</strong></td>
                                <td><?php echo date('Y-m-d H:i', strtotime($observer['created_at'])); ?></td>
                            </tr>
                            <tr>
                                <td><strong>دوایین نوێکردنەوە:</strong></td>
                                <td><?php echo date('Y-m-d H:i', strtotime($observer['updated_at'])); ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">داخستن</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Observer Modal -->
        <div class="modal fade" id="editObserverModal<?php echo $observer['id']; ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">دەستکاریکردنی چاودێر</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="observer_id" value="<?php echo $observer['id']; ?>">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="edit_full_name<?php echo $observer['id']; ?>" class="form-label">ناوی تەواو</label>
                                <input type="text" class="form-control" id="edit_full_name<?php echo $observer['id']; ?>" name="full_name" value="<?php echo htmlspecialchars($observer['full_name']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit_mobile<?php echo $observer['id']; ?>" class="form-label">ژمارەی مۆبایل</label>
                                <input type="text" class="form-control" id="edit_mobile<?php echo $observer['id']; ?>" name="mobile" value="<?php echo htmlspecialchars($observer['mobile']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit_password<?php echo $observer['id']; ?>" class="form-label">پاسۆردی نوێ</label>
                                <input type="password" class="form-control" id="edit_password<?php echo $observer['id']; ?>" name="password" value="<?php echo htmlspecialchars($observer['password']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit_voting_center_name<?php echo $observer['id']; ?>" class="form-label">بنکەی دەنگدان</label>
                                <select class="form-select" id="edit_voting_center_name<?php echo $observer['id']; ?>" name="voting_center_name" required>
                                    <?php foreach ($voting_centers as $center): ?>
                                        <option value="<?php echo htmlspecialchars($center); ?>" <?php echo $center == $observer['voting_center_name'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($center); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">پاشگەزبوونەوە</button>
                            <button type="submit" name="update_observer" class="btn btn-warning">نوێکردنەوە</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Delete Observer Modal -->
        <div class="modal fade" id="deleteObserverModal<?php echo $observer['id']; ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">سڕینەوەی چاودێر</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>دڵنیایت لە سڕینەوەی چاودێری <strong><?php echo htmlspecialchars($observer['full_name']); ?></strong>؟</p>
                        <p class="text-danger"><small>ئەم کردارە ناگەڕێتەوە!</small></p>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="observer_id" value="<?php echo $observer['id']; ?>">
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">پاشگەزبوونەوە</button>
                            <button type="submit" name="delete_observer" class="btn btn-danger">سڕینەوە</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>