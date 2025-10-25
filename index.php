<!DOCTYPE html>
<html lang="ku">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سیستەمی تۆماری دەنگدەرانی تایبەت</title>
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
        
        .main-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 40px;
            text-align: center;
            max-width: 800px;
            width: 100%;
        }
        
        .logo-container {
            margin-bottom: 30px;
        }
        
        .logo {
            width: 120px;
            height: 120px;
            margin: 0 auto 20px;
            background: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .logo img {
            width: 80px;
            height: 80px;
            object-fit: contain;
        }
        
        .main-title {
            color: #2c3e50;
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .subtitle {
            color: #7f8c8d;
            font-size: 1.2rem;
            margin-bottom: 40px;
        }
        
        .nav-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 40px;
        }
        
        .nav-btn {
            background: linear-gradient(135deg, #3498db, #2980b9);
            border: none;
            border-radius: 15px;
            padding: 25px 20px;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(52, 152, 219, 0.3);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 120px;
        }
        
        .nav-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(52, 152, 219, 0.4);
            color: white;
        }
        
        .nav-btn.dashboard {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            box-shadow: 0 8px 25px rgba(231, 76, 60, 0.3);
        }
        
        .nav-btn.dashboard:hover {
            box-shadow: 0 15px 35px rgba(231, 76, 60, 0.4);
        }
        
        .nav-btn.members {
            background: linear-gradient(135deg, #27ae60, #229954);
            box-shadow: 0 8px 25px rgba(39, 174, 96, 0.3);
        }
        
        .nav-btn.members:hover {
            box-shadow: 0 15px 35px rgba(39, 174, 96, 0.4);
        }
        
        .nav-btn.observers {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            box-shadow: 0 8px 25px rgba(243, 156, 18, 0.3);
        }
        
        .nav-btn.observers:hover {
            box-shadow: 0 15px 35px rgba(243, 156, 18, 0.4);
        }
        
        .nav-btn.scanner {
            background: linear-gradient(135deg, #9b59b6, #8e44ad);
            box-shadow: 0 8px 25px rgba(155, 89, 182, 0.3);
        }
        
        .nav-btn.scanner:hover {
            box-shadow: 0 15px 35px rgba(155, 89, 182, 0.4);
        }
        
        .nav-btn i {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .nav-btn .btn-title {
            font-size: 1.1rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .nav-btn .btn-desc {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        @media (max-width: 768px) {
            .main-title {
                font-size: 2rem;
            }
            
            .nav-buttons {
                grid-template-columns: 1fr;
            }
            
            .main-container {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Logo Section -->
        <div class="logo-container">
            <div class="logo">
                <img src="image/logo.png" alt="لۆگۆ" onerror="this.style.display='none'; this.parentNode.innerHTML='<i class=\'bi bi-shield-check\' style=\'font-size: 3rem; color: #3498db;\'></i>'">
            </div>
        </div>
        
        <!-- Title Section -->
        <h1 class="main-title">سیستەمی تۆماری دەنگدەرانی تایبەت</h1>
        <p class="subtitle">وەزارەتی پێشمەرگە</p>
        
        <!-- Navigation Buttons -->
        <div class="nav-buttons">
            <a href="observer_dashboard.php" class="nav-btn dashboard">
                <i class="bi bi-speedometer2"></i>
                <div class="btn-title">داش بۆرد</div>
                <div class="btn-desc">بینینی ئاماری گشتی</div>
            </a>
            
            <a href="member_manager.php" class="nav-btn members">
                <i class="bi bi-people-fill"></i>
                <div class="btn-title">بەڕێوەبەری تۆمارەکان</div>
                <div class="btn-desc">بەڕێوەبردنی دەنگدەران</div>
            </a>
            
            <a href="observer_manager.php" class="nav-btn observers">
                <i class="bi bi-person-badge-fill"></i>
                <div class="btn-title">بەڕێوەبەری چاودێرەکان</div>
                <div class="btn-desc">بەڕێوەبردنی چاودێرەکان</div>
            </a>
            
            <a href="observer_scanner.php" class="nav-btn scanner">
                <i class="bi bi-qr-code-scan"></i>
                <div class="btn-title">سکان کردن</div>
                <div class="btn-desc">سکانی کارتی دەنگدەران</div>
            </a>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>