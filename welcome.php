<?php
/**
 * Welcome & Setup Success Page
 * سیستەمی دیجیتاڵی کارمەندانی پێشمەرگە
 */
?>
<!DOCTYPE html>
<html lang="ku" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="assets/logo.ico" type="image/x-icon">
    <title>بەخێرهاتن | سیستەمی پێشمەرگە</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&family=Noto+Sans+Arabic:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #5a0202;
            --gold: #c5a059;
            --success: #2e7d32;
            --bg: #f4f7f6;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Noto Sans Arabic', 'Inter', sans-serif;
            background: var(--bg);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            overflow: hidden;
        }
        .welcome-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 50px;
            width: 100%;
            max-width: 600px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.1);
            text-align: center;
            position: relative;
            border: 1px solid rgba(255,255,255,0.3);
            animation: slideUp 0.8s ease-out;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .logo {
            width: 120px;
            margin-bottom: 25px;
            filter: drop-shadow(0 5px 15px rgba(0,0,0,0.1));
        }
        h1 { color: var(--primary); margin-bottom: 10px; font-size: 1.8rem; }
        p { color: #666; margin-bottom: 30px; line-height: 1.6; }
        
        .status-list {
            text-align: right;
            background: #fff;
            padding: 20px;
            border-radius: 16px;
            margin-bottom: 35px;
            border: 1px solid #eee;
        }
        .status-item {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
            font-weight: 700;
            color: #444;
        }
        .status-item:last-child { margin-bottom: 0; }
        .status-icon {
            width: 24px;
            height: 24px;
            background: var(--success);
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
        }

        .btn-start {
            display: inline-block;
            background: var(--primary);
            color: #fff;
            padding: 15px 40px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 700;
            font-size: 1.1rem;
            transition: 0.3s;
            box-shadow: 0 10px 20px rgba(90, 2, 2, 0.2);
        }
        .btn-start:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(90, 2, 2, 0.3);
            background: #7a0303;
        }

        .confetti {
            position: absolute;
            top: -50px;
            left: 50%;
            transform: translateX(-50%);
            width: 100%;
            pointer-events: none;
        }
    </style>
</head>
<body>

<div class="welcome-card">
    <img src="assets/logo.ico" alt="Peshmerga Logo" class="logo">
    <h1>پیرۆزە! دامەزراندن کۆتایی هات</h1>
    <p>سیستەمی دیجیتاڵی کارمەندانی پێشمەرگە بە سەرکەوتوویی لەسەر کۆمپیوتەرەکەت جێگیر کرا.</p>

    <div class="status-list">
        <div class="status-item">
            <div class="status-icon">✓</div>
            <span>سێرڤەری لۆکاڵ (Laragon) چالاک کرا</span>
        </div>
        <div class="status-item">
            <div class="status-icon">✓</div>
            <span>داتابەیسی Peshmerga ئامادە کرا</span>
        </div>
        <div class="status-item">
            <div class="status-icon">✓</div>
            <span>فایلەکانی سیستم جێگیر کران</span>
        </div>
        <div class="status-item">
            <div class="status-icon">✓</div>
            <span>ئایکۆنی سەر دێسکتۆپ دروست کرا</span>
        </div>
    </div>

    <a href="login.php" class="btn-start">چوونە ناو سیستم</a>

    <div style="margin-top: 25px; font-size: 0.8rem; color: #aaa;">
        وەشانی ١.٠.٠ | وەزارەتی پێشمەرگە
    </div>
</div>

</body>
</html>
