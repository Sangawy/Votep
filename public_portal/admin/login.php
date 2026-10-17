<?php
session_start();
require_once '../db_config.php';
$conn = getPublicDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT * FROM public_users WHERE username = ?");
    $stmt->bind_param("s", $user);
    $stmt->execute();
    $res = $stmt->get_result();
    $u = $res->fetch_assoc();

    if ($u && ($pass === $u['password'] || password_verify($pass, $u['password']))) {
        $_SESSION['public_admin'] = $u['id'];
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "ناوی بەکارهێنەر یان وشەی تێپەڕ هەڵەیە";
    }
}
?>
<!DOCTYPE html>
<html lang="ku" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>چوونەژوورەوەی ئەدمین</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Arabic:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Noto Sans Arabic', sans-serif; background: #f4f7f6; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-box { background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        h2 { text-align: center; color: #5a0202; margin-bottom: 30px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; color: #666; }
        input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; outline: none; }
        button { width: 100%; padding: 12px; background: #5a0202; color: #fff; border: none; border-radius: 8px; cursor: pointer; font-size: 1rem; font-weight: bold; }
        .error { color: #d32f2f; background: #fdeaea; padding: 10px; border-radius: 6px; margin-bottom: 20px; text-align: center; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>چوونەژوورەوە</h2>
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label>ناوی بەکارهێنەر</label>
                <input type="text" name="username" required autofocus>
            </div>
            <div class="form-group">
                <label>وشەی تێپەڕ</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit">چوونەژوورەوە</button>
        </form>
    </div>
</body>
</html>
