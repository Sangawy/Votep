<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['member_portal_id']) && (int)$_SESSION['member_portal_id'] > 0) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../config.php';
$conn = getDB();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $family_id = isset($_POST['family_id']) ? (int)$_POST['family_id'] : 0;
    $member_id = isset($_POST['member_id']) ? (int)$_POST['member_id'] : 0;
    $code_input = isset($_POST['code_input']) ? trim($_POST['code_input']) : '';

    if ($family_id <= 0 || $member_id <= 0 || empty($code_input)) {
        $error = 'تکایە سەرجەم خانەکان بە دروستی پڕبکەرەوە.';
    } else {
        $cleanCode = ltrim($code_input, '#');
        $stmt = $conn->prepare("
            SELECT id, full_name, family_id, member_code 
            FROM members 
            WHERE id = ? AND family_id = ?
        ");
        $stmt->bind_param("ii", $member_id, $family_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $found = $res->fetch_assoc();
        $stmt->close();

        if ($found) {
            // Verify code against ID or member_code
            $match = false;
            if ((string)$found['id'] === $cleanCode) {
                $match = true;
            } elseif (!empty($found['member_code']) && (string)$found['member_code'] === $cleanCode) {
                $match = true;
            } elseif (!empty($found['member_code']) && (string)$found['member_code'] === $code_input) {
                $match = true;
            }

            if ($match) {
                $_SESSION['member_portal_id'] = $found['id'];
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'ئایدی یان کۆدی داخڵکراو هەڵەیە! تکایە ئایدی (#ID) کەسی خۆت لە سیستەم بنووسە.';
            }
        } else {
            $error = 'ئەم ئەندامە لەم بنەماڵەیەدا نەدۆزرایەوە.';
        }
    }
}

// Fetch families for dropdown
$familiesRes = $conn->query("SELECT id, family_name FROM families ORDER BY family_name ASC");
$families = [];
if ($familiesRes) {
    while ($f = $familiesRes->fetch_assoc()) {
        $families[] = $f;
    }
}

// Fetch Branding
$branding = [
    'system_name' => 'مەڵبەندی ڕۆشنبیری و کۆمەڵایەتی',
    'system_subtitle' => 'ساداتی پیرخدری شاهۆ',
    'logo' => '../assets/logo.png'
];
$bRes = $conn->query("SELECT * FROM settings LIMIT 1");
if ($bRes && $bRow = $bRes->fetch_assoc()) {
    $branding = array_merge($branding, $bRow);
}
?>
<!DOCTYPE html>
<html lang="ku" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>پۆرتاڵی ئەندامان - چوونەژوورەوە</title>
    
    <!-- Fonts & Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Arabic:wght@300;400;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <style>
        :root {
            --app-primary: #1a5653;
            --app-primary-dark: #0f3533;
            --app-gold: #c5a059;
            --app-gold-light: #f7edd7;
            --app-bg: #f8fafc;
            --app-card: #ffffff;
            --app-text: #0f172a;
            --app-muted: #64748b;
            --app-border: #e2e8f0;
            --app-radius: 18px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: 'Noto Sans Arabic', Tahoma, sans-serif;
            background: linear-gradient(135deg, #0d3835 0%, #1a5653 40%, #0a2524 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            color: var(--app-text);
        }

        .login-wrapper {
            width: 100%;
            max-width: 440px;
            background: var(--app-card);
            border-radius: 28px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.35);
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.15);
            animation: slideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-header {
            background: linear-gradient(135deg, #1a5653 0%, #134240 100%);
            color: #fff;
            padding: 32px 24px 26px;
            text-align: center;
            position: relative;
        }

        .login-header::after {
            content: '';
            position: absolute;
            bottom: -15px;
            left: 0;
            right: 0;
            height: 30px;
            background: var(--app-card);
            border-radius: 24px 24px 0 0;
        }

        .logo-wrap {
            width: 76px;
            height: 76px;
            background: #ffffff;
            border-radius: 22px;
            margin: 0 auto 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 25px rgba(0,0,0,0.25);
            padding: 8px;
            border: 2px solid var(--app-gold);
        }

        .logo-wrap img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .login-header h1 {
            font-size: 1.25rem;
            font-weight: 800;
            margin-bottom: 4px;
            color: #ffffff;
        }

        .login-header p {
            font-size: 0.85rem;
            color: var(--app-gold-light);
            opacity: 0.95;
        }

        .badge-app {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(197, 160, 89, 0.22);
            color: #f7edd7;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.76rem;
            font-weight: 700;
            margin-top: 10px;
            border: 1px solid rgba(197, 160, 89, 0.4);
        }

        .login-body {
            padding: 24px 24px 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 0.86rem;
            font-weight: 700;
            color: #334155;
            margin-bottom: 8px;
        }

        .form-label i {
            color: var(--app-primary);
            margin-left: 6px;
        }

        .form-control {
            width: 100%;
            height: 48px;
            border: 1.5px solid var(--app-border);
            border-radius: 14px;
            padding: 0 16px;
            font-family: inherit;
            font-size: 0.95rem;
            color: var(--app-text);
            background: #f8fafc;
            transition: all 0.25s ease;
            outline: none;
        }

        .form-control:focus {
            border-color: var(--app-primary);
            background: #fff;
            box-shadow: 0 0 0 4px rgba(26, 86, 83, 0.12);
        }

        .select2-container--default .select2-selection--single {
            height: 48px;
            border: 1.5px solid var(--app-border);
            border-radius: 14px;
            background: #f8fafc;
            display: flex;
            align-items: center;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 48px;
            padding-right: 16px;
            padding-left: 36px;
            font-size: 0.95rem;
            color: #0f172a;
            font-family: 'Noto Sans Arabic', sans-serif;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 46px;
            left: 12px;
            right: auto;
        }

        .select2-dropdown {
            border-radius: 14px;
            border: 1.5px solid var(--app-primary);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            font-family: 'Noto Sans Arabic', sans-serif;
            overflow: hidden;
        }

        .hint-text {
            font-size: 0.78rem;
            color: var(--app-muted);
            margin-top: 6px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .btn-submit {
            width: 100%;
            height: 52px;
            background: linear-gradient(135deg, #1a5653 0%, #113c3a 100%);
            color: #fff;
            border: none;
            border-radius: 16px;
            font-family: inherit;
            font-size: 1.05rem;
            font-weight: 800;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 10px 25px rgba(26, 86, 83, 0.35);
            transition: all 0.25s ease;
            margin-top: 26px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 30px rgba(26, 86, 83, 0.45);
        }

        .btn-submit:active {
            transform: scale(0.98);
        }

        .alert-error {
            background: #fef2f2;
            border: 1px solid #fee2e2;
            color: #dc2626;
            padding: 12px 16px;
            border-radius: 14px;
            font-size: 0.86rem;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: var(--app-muted);
            text-decoration: none;
            font-size: 0.86rem;
            font-weight: 600;
            transition: color 0.2s;
        }

        .back-link:hover {
            color: var(--app-primary);
        }

        .loading-spinner {
            display: none;
            font-size: 0.82rem;
            color: var(--app-primary);
            margin-top: 5px;
        }
    </style>
</head>
<body>

    <div class="login-wrapper">
        <div class="login-header">
            <div class="logo-wrap">
                <img src="<?php echo htmlspecialchars($branding['logo'] ?: '../assets/logo.png'); ?>" alt="Logo">
            </div>
            <h1><?php echo htmlspecialchars($branding['system_name']); ?></h1>
            <p><?php echo htmlspecialchars($branding['system_subtitle']); ?></p>
            <div class="badge-app">
                <i class="fas fa-mobile-alt"></i> پۆرتاڵی ئەندامان و بنەماڵەکان
            </div>
        </div>

        <div class="login-body">
            <?php if (!empty($error)): ?>
                <div class="alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" id="loginForm">
                
                <!-- 1. SELECT FAMILY -->
                <div class="form-group">
                    <label class="form-label" for="family_select">
                        <i class="fas fa-landmark"></i> ١. بنەماڵەکەت هەڵبژێرە:
                    </label>
                    <select name="family_id" id="family_select" class="form-control" required style="width: 100%;">
                        <option value="">-- بنەماڵەکەت هەڵبژێرە --</option>
                        <?php foreach($families as $f): ?>
                            <option value="<?php echo $f['id']; ?>">
                                <?php echo htmlspecialchars($f['family_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 2. SELECT MEMBER NAME OR FATHER'S NAME -->
                <div class="form-group">
                    <label class="form-label" for="member_select">
                        <i class="fas fa-user"></i> ٢. ناوی خۆت یان باوکت:
                    </label>
                    <select name="member_id" id="member_select" class="form-control" required style="width: 100%;" disabled>
                        <option value="">-- سەرەتا بنەماڵە هەڵبژێرە --</option>
                    </select>
                    <div id="loadingMembers" class="loading-spinner">
                        <i class="fas fa-spinner fa-spin"></i> چاوەڕوانبە، ناوی ئەندامان باردەکرێت...
                    </div>
                </div>

                <!-- 3. MEMBER ID / CODE INSTEAD OF PASSWORD -->
                <div class="form-group">
                    <label class="form-label" for="code_input">
                        <i class="fas fa-id-card"></i> ٣. ئایدی / کۆدی کەسی لە سیستەم:
                    </label>
                    <input type="text" name="code_input" id="code_input" class="form-control" placeholder="بۆ نموونە: 249 یان 515" required autocomplete="off">
                    <div class="hint-text">
                        <i class="fas fa-info-circle" style="color: var(--app-gold);"></i>
                        لە جیاتی وشەی نهێنی، ئایدی کەسی خۆت (#ID) لە سیستەم بنووسە.
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-sign-in-alt"></i> چوونەژوورەوە بۆ ئەپ
                </button>

                <a href="../public_portal/index.php" class="back-link">
                    <i class="fas fa-arrow-right"></i> گەڕانەوە بۆ ماڵپەڕی سەرەکی
                </a>

            </form>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#family_select').select2({
                dir: "rtl",
                placeholder: "-- بنەماڵەکەت هەڵبژێرە --"
            });

            $('#member_select').select2({
                dir: "rtl",
                placeholder: "-- ناوی خۆت یان باوکت هەڵبژێرە --"
            });

            // When family changes, load members dynamically
            $('#family_select').on('change', function() {
                const familyId = $(this).val();
                const memberSelect = $('#member_select');
                const loading = $('#loadingMembers');

                if (!familyId) {
                    memberSelect.html('<option value="">-- سەرەتا بنەماڵە هەڵبژێرە --</option>').prop('disabled', true);
                    return;
                }

                loading.show();
                memberSelect.prop('disabled', true);

                fetch('api.php?action=get_family_members&family_id=' + familyId)
                    .then(res => res.json())
                    .then(data => {
                        loading.hide();
                        memberSelect.html('<option value="">-- ناوی خۆت یان باوکت هەڵبژێرە --</option>');
                        if (data.success && data.members && data.members.length > 0) {
                            data.members.forEach(m => {
                                const fatherText = m.father_name ? (' (باوکی: ' + m.father_name + ')') : '';
                                const codeText = m.code ? (' #' + m.code) : (' #' + m.id);
                                const option = new Option(m.name + fatherText + codeText, m.id);
                                memberSelect.append(option);
                            });
                            memberSelect.prop('disabled', false);
                            memberSelect.trigger('change');
                        } else {
                            memberSelect.html('<option value="">هیچ ئەندامێک لەم بنەماڵەیەدا نییە</option>');
                        }
                    })
                    .catch(err => {
                        loading.hide();
                        console.error('Error fetching members:', err);
                        alert('هەڵەیەک لە بارکردنی ناوی ئەندامان ڕوویدا');
                    });
            });
        });
    </script>
</body>
</html>
