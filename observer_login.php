<?php
session_start();
require_once 'config.php';

if (isset($_POST['login'])) {
    $mobile = $_POST['mobile'];
    $password = $_POST['password'];
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM observers WHERE mobile = ? AND password = ?");
        $stmt->execute([$mobile, $password]);
        $observer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($observer) {
            $_SESSION['observer_id'] = $observer['id'];
            $_SESSION['observer_name'] = $observer['full_name'];
            $_SESSION['center_name'] = $observer['voting_center_name'];
            $_SESSION['mobile'] = $observer['mobile'];
            
            header("Location: observer_scanner.php");
            exit();
        } else {
            $error = "ژمارەی مۆبایل یان پاسۆرد هەڵەیە!";
        }
    } catch(PDOException $e) {
        $error = "هەڵە لە پەیوەندی داتابەیس: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="ku">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>چوونەژورەوەی چاودێرەکان</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            direction: rtl;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
            width: 100%;
            max-width: 400px;
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
    </style>
</head>
<body>
    <div class="login-container">
        <div class="header">
            <h4 class="mb-0">سیستەمی سکانکردنی دەنگدەران</h4>
            <p class="mb-0 mt-1">چوونەژورەوەی چاودێرەکان</p>
        </div>
        <div class="form-container">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-3">
                    <label for="mobile" class="form-label">ژمارەی مۆبایل</label>
                    <input type="text" class="form-control" id="mobile" name="mobile" placeholder="07501234567" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">پاسۆرد</label>
                    <input type="password" class="form-control" id="password" name="password" placeholder="••••" required>
                </div>
                <button type="submit" name="login" class="btn btn-primary w-100 py-2">چوونەژورەوە</button>
            </form>
        </div>
    </div>
</body>
</html>