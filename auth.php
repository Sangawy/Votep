<?php
session_start();

// Authentication function
function requireAuth() {
    // Check if user is already authenticated
    if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
        // If this is a POST request with the auth code
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['auth_code'])) {
            $entered_code = $_POST['auth_code'];
            
            // Check if the code is correct
            if ($entered_code === '0330') {
                $_SESSION['authenticated'] = true;
                $_SESSION['auth_time'] = time();
                return true;
            } else {
                $error = "کۆدەکە هەڵەیە! تکایە دووبارە هەوڵ بدەوە.";
                showAuthForm($error);
                exit();
            }
        } else {
            // Show authentication form
            showAuthForm();
            exit();
        }
    }
    
    // Check if session has expired (optional - 24 hours)
    if (isset($_SESSION['auth_time']) && (time() - $_SESSION['auth_time']) > 86400) {
        session_destroy();
        session_start();
        showAuthForm("کاتی چوونەژوورەوەکەت تەواو بووە. تکایە دووبارە کۆدەکە بنووسە.");
        exit();
    }
    
    return true;
}

// Function to show authentication form
function showAuthForm($error = '') {
    $current_page = basename($_SERVER['PHP_SELF']);
    $page_title = getPageTitle($current_page);
    
    ?>
    <!DOCTYPE html>
    <html lang="ku">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>چوونەژوورەوە - <?php echo $page_title; ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
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
            
            .auth-container {
                background: rgba(255, 255, 255, 0.95);
                border-radius: 20px;
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
                padding: 40px;
                text-align: center;
                max-width: 450px;
                width: 100%;
            }
            
            .auth-icon {
                width: 80px;
                height: 80px;
                margin: 0 auto 20px;
                background: linear-gradient(135deg, #3498db, #2980b9);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-size: 2rem;
            }
            
            .auth-title {
                color: #2c3e50;
                font-size: 1.8rem;
                font-weight: bold;
                margin-bottom: 10px;
            }
            
            .auth-subtitle {
                color: #7f8c8d;
                margin-bottom: 30px;
            }
            
            .form-control {
                border-radius: 10px;
                border: 2px solid #e9ecef;
                padding: 15px;
                font-size: 1.1rem;
                text-align: center;
                letter-spacing: 2px;
                font-weight: bold;
            }
            
            .form-control:focus {
                border-color: #3498db;
                box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
            }
            
            .btn-auth {
                background: linear-gradient(135deg, #3498db, #2980b9);
                border: none;
                border-radius: 10px;
                padding: 15px 30px;
                color: white;
                font-size: 1.1rem;
                font-weight: bold;
                width: 100%;
                margin-top: 20px;
                transition: all 0.3s ease;
            }
            
            .btn-auth:hover {
                transform: translateY(-2px);
                box-shadow: 0 10px 25px rgba(52, 152, 219, 0.3);
                color: white;
            }
            
            .error-message {
                background: #e74c3c;
                color: white;
                padding: 15px;
                border-radius: 10px;
                margin-bottom: 20px;
                font-weight: bold;
            }
            
            .back-btn {
                position: absolute;
                top: 20px;
                right: 20px;
                background: rgba(255, 255, 255, 0.2);
                border: none;
                border-radius: 50%;
                width: 50px;
                height: 50px;
                color: white;
                font-size: 1.2rem;
                transition: all 0.3s ease;
            }
            
            .back-btn:hover {
                background: rgba(255, 255, 255, 0.3);
                color: white;
            }
        </style>
    </head>
    <body>
        <a href="index.php" class="back-btn">
            <i class="bi bi-arrow-right"></i>
        </a>
        
        <div class="auth-container">
            <div class="auth-icon">
                <i class="bi bi-shield-lock"></i>
            </div>
            
            <h2 class="auth-title">چوونەژوورەوە</h2>
            <p class="auth-subtitle">بۆ دەستپێگەیشتن بە <?php echo $page_title; ?></p>
            
            <?php if ($error): ?>
                <div class="error-message">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="auth_code" class="form-label">کۆدی چوونەژوورەوە</label>
                    <input type="password" class="form-control" id="auth_code" name="auth_code" 
                           placeholder="کۆدەکە بنووسە" maxlength="4" required autofocus>
                </div>
                
                <button type="submit" class="btn btn-auth">
                    <i class="bi bi-unlock me-2"></i>
                    چوونەژوورەوە
                </button>
            </form>
        </div>
        
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            // Auto-focus on the input field
            document.getElementById('auth_code').focus();
            
            // Auto-submit when 4 digits are entered
            document.getElementById('auth_code').addEventListener('input', function(e) {
                if (e.target.value.length === 4) {
                    // Small delay to show the 4th digit
                    setTimeout(() => {
                        e.target.form.submit();
                    }, 300);
                }
            });
        </script>
    </body>
    </html>
    <?php
}

// Function to get page title based on filename
function getPageTitle($filename) {
    $titles = [
        'observer_dashboard.php' => 'داش بۆرد',
        'member_manager.php' => 'بەڕێوەبەری تۆمارەکان',
        'observer_manager.php' => 'بەڕێوەبەری چاودێرەکان',
        'observer_scanner.php' => 'سکان کردن'
    ];
    
    return $titles[$filename] ?? 'سیستەم';
}

// Function to logout
function logout() {
    session_destroy();
    header("Location: index.php");
    exit();
}

// Handle logout request
if (isset($_GET['logout'])) {
    logout();
}
?>