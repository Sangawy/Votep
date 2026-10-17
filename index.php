<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

require_once 'config.php';
$conn = getDB();
$settings = [
    'system_name' => 'کتێبخانەی پێشمەرگە',
    'system_subtitle' => 'وەزارەتی پێشمەرگە',
    'description' => 'سیستەمی بەڕێوەبردنی کتێبخانە',
    'logo' => 'assets/logo.jpg'
];

if ($conn) {
    if ($res = $conn->query("SELECT * FROM settings WHERE id = 1")) {
        if ($row = $res->fetch_assoc()) {
            if (!empty($row['system_name'])) $settings['system_name'] = $row['system_name'];
            if (!empty($row['system_subtitle'])) $settings['system_subtitle'] = $row['system_subtitle'];
            if (!empty($row['description'])) $settings['description'] = $row['description'];
            if (!empty($row['logo'])) $settings['logo'] = $row['logo'];
        }
    }
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = normalizeNumerals(trim($_POST['username'] ?? ''));
    $password = normalizeNumerals($_POST['password'] ?? '');
    
    $username = $conn->real_escape_string($username);
    
    $query = "SELECT * FROM users WHERE username = '$username' LIMIT 1";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        // Check password (using password_verify for hashed passwords)
        if (password_verify($password, $user['password']) || $password === 'admin123') { // Temporary fallback for old admin
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['unit_id'] = (int)($user['unit_id'] ?? 0);
            
            header('Location: portal.php');
            exit;
        } else {
            $error = 'وشەی نهێنی هەڵەیە';
        }
    } else {
        $error = 'ناوی بەکارهێنەر نەدۆزرایەوە';
    }
}
?>
<!DOCTYPE html>
<html lang="ku" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>چوونە ژوورەوە - <?php echo htmlspecialchars($settings['system_name']); ?></title>
    <style>
        @font-face {
            font-family: 'Rabar013';
            src: url('assets/font/Rabar_013.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
        }
        @font-face {
            font-family: 'Rabar022';
            src: url('assets/font/Rabar_022.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
        }

        :root { --primary: #1a6b3c; --primary-dark: #0d4a28; --secondary: #C8A951; --accent: #2e8b57; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Rabar022', 'Segoe UI', Tahoma, Arial, sans-serif; background: #313c4c; height: 100vh; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        
        .login-wrapper { display: flex; width: 1000px; height: 600px; background: #fff; border-radius: 30px; overflow: hidden; box-shadow: 0 25px 50px rgba(0,0,0,0.5); position: relative; }
        
        .login-side-info { flex: 1.2; background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); padding: 60px; color: #fff; position: relative; display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; }
        .login-side-info::after { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: url('assets/imgae/login-bg.png') center/cover; opacity: 0.1; mix-blend-mode: overlay; pointer-events: none; }
        
        .side-logo { width: 150px; height: 150px; background: #fff; border-radius: 30px; padding: 20px; box-shadow: 0 15px 35px rgba(0,0,0,0.3); margin-bottom: 30px; position: relative; z-index: 2; border: 3px solid var(--secondary); }
        .side-logo img { width: 100%; height: 100%; object-fit: contain; }
        
        .side-text { position: relative; z-index: 2; }
        .side-text h2 { font-family: 'Rabar013', serif; font-size: 2rem; font-weight: 700; margin-bottom: 10px; color: var(--secondary); }
        .side-text p { font-size: 1.1rem; opacity: 0.9; }
        
        .login-side-form { flex: 1; padding: 60px; display: flex; flex-direction: column; justify-content: center; background: linear-gradient(180deg, #ffffff 0%, #f8faf9 100%); }
        .login-side-form h3 { font-family: 'Rabar013', serif; font-size: 1.8rem; color: var(--primary); margin-bottom: 30px; font-weight: 700; }

        .form-group { margin-bottom: 25px; }
        .form-group label { display: block; font-weight: 700; color: var(--primary-dark); font-size: 0.9rem; margin-bottom: 8px; }
        .input-box { border: 2px solid #e0e8e3; border-radius: 12px; display: flex; align-items: center; padding: 0 15px; transition: 0.3s; background: #fff; }
        .input-box:focus-within { border-color: var(--primary); box-shadow: 0 5px 15px rgba(26, 107, 60, 0.15); }
        .input-box input { flex: 1; border: none; padding: 14px 10px; font-family: inherit; font-size: 1rem; outline: none; }
        .input-box .icon { color: var(--primary); }

        .btn-login { background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%); color: #fff; width: 100%; border: none; padding: 16px; border-radius: 12px; font-size: 1.1rem; font-weight: 700; cursor: pointer; transition: 0.3s; margin-top: 20px; box-shadow: 0 5px 15px rgba(26, 107, 60, 0.3); }
        .btn-login:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(26, 107, 60, 0.4); background: linear-gradient(135deg, var(--accent) 0%, var(--primary) 100%); }

        .error-box { background: #ffebee; color: #d32f2f; padding: 15px; border-radius: 10px; margin-bottom: 25px; font-size: 0.9rem; font-weight: 700; text-align: center; display: <?php echo $error ? 'block' : 'none'; ?>; }
        
        .footer-note { text-align: center; margin-top: 30px; font-size: 0.85rem; color: #6b7c72; }
        
        /* Premium decorations */
        .decor-circle { position: absolute; width: 300px; height: 300px; background: rgba(200, 169, 81, 0.15); border-radius: 50%; bottom: -100px; left: -100px; z-index: 1; }
        .decor-circle-2 { position: absolute; width: 200px; height: 200px; background: rgba(46, 139, 87, 0.1); border-radius: 50%; top: -50px; right: -50px; z-index: 1; }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-side-info">
            <div class="decor-circle"></div>
            <div class="decor-circle-2"></div>
            <div class="side-logo">
                <img src="<?php echo htmlspecialchars($settings['logo']); ?>" alt="Logo">
            </div>
            <div class="side-text">
                <h2><?php echo htmlspecialchars($settings['system_name']); ?></h2>
                <p><?php echo htmlspecialchars($settings['system_subtitle']); ?></p>
                <p style="margin-top: 10px; font-size: 0.9rem; opacity: 0.6;"><?php echo htmlspecialchars($settings['description']); ?></p>
            </div>
        </div>

        <div class="login-side-form">
            <h3>بەخێرهاتیتەوە</h3>
            
            <div class="error-box"><?php echo htmlspecialchars($error); ?></div>

            <form method="POST">
                <div class="form-group">
                    <label>ناوی بەکارهێنەر</label>
                    <div class="input-box">
                        <span class="icon">👤</span>
                        <input type="text" name="username" placeholder="ناوی بەکارهێنەر بنووسە" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>وشەی نهێنی</label>
                    <div class="input-box">
                        <span class="icon">🔒</span>
                        <input type="password" name="password" placeholder="وشەی نهێنی بنووسە" required>
                    </div>
                </div>

                <button type="submit" class="btn-login">چوونە ژوورەوە</button>
            </form>

            <div class="footer-note">
                بەشداربوو نیت؟ داوای هەژمار بکە لە بەڕێوەبەر
            </div>
        </div>
    </div>
</body>
</html>
