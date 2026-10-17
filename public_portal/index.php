<?php
require_once 'db_config.php';
$conn = getPublicDB();
$settings_res = $conn->query("SELECT * FROM public_settings WHERE id = 1");
$site = $settings_res->fetch_assoc();

// Fallback values
$site_name = $site['system_name'] ?? 'پیرخدر دۆت ئۆرگ';
$site_subtitle = $site['system_subtitle'] ?? 'مەڵبەندی ڕۆشنبیری و کۆمەڵایەتی پیرخدر';
$site_color = $site['primary_color'] ?? '#5a0202';
$site_desc = $site['description'] ?? 'مەڵبەندی پیرخدر کار دەکات بۆ بڵاوکردنەوەی زانست و خزمەتکردنی کۆمەڵگا.';
?>
<!DOCTYPE html>

<html lang="ku" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($site_name); ?> - <?php echo htmlspecialchars($site_subtitle); ?></title>
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="<?php echo htmlspecialchars($site_desc); ?>">

    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;700;900&family=Noto+Sans+Arabic:wght@300;400;700;900&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: <?php echo $site_color; ?>;
            --primary-light: <?php echo $site_color; ?>cc;
            --gold: #d4af37;

            --gold-light: #f1c40f;
            --dark: #1a1a1a;
            --light: #f9f9f9;
            --glass: rgba(255, 255, 255, 0.85);
            --shadow: 0 10px 30px rgba(0,0,0,0.1);
            --bg-logo: url('<?php 
                $bg_logo = $site['logo_path'] ?? '';
                if (!empty($bg_logo) && !file_exists($bg_logo) && file_exists('../' . $bg_logo)) $bg_logo = '../' . $bg_logo;
                echo $bg_logo;
            ?>');
        }


    /* Rabar Kurdish Fonts */
    @font-face {
        font-family: 'Rabar';
        src: url('assets/font/Rabar_022.ttf') format('truetype');
        font-weight: normal;
        font-style: normal;
    }
    @font-face {
        font-family: 'RabarBold';
        src: url('assets/font/Rabar_005.ttf') format('truetype');
        font-weight: bold;
        font-style: normal;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { 
        font-family: 'Rabar', 'Noto Sans Arabic', sans-serif; 
        background-color: #fff; 
        color: var(--dark);
        line-height: 1.6;
        overflow-x: hidden;
    }


        /* --- Header & Navigation --- */
        header {
            background: var(--glass);
            backdrop-filter: blur(10px);
            position: fixed;
            top: 0; width: 100%; z-index: 1000;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            padding: 15px 5%;
            display: flex; justify-content: space-between; align-items: center;
        }
        .logo { display: flex; align-items: center; gap: 15px; text-decoration: none; }
        .logo-box { width: 45px; height: 45px; background: var(--primary); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 900; font-size: 1.5rem; box-shadow: 0 4px 10px rgba(90,2,2,0.3); }
        .logo-text h1 { font-size: 1.4rem; color: var(--primary); font-family: 'RabarBold'; margin: 0; }
        .logo-text p { font-size: 0.95rem; color: #666; margin-top: 2px; font-weight: 400; }


        nav ul { display: flex; gap: 30px; list-style: none; }
        nav a { text-decoration: none; color: var(--dark); font-weight: 700; font-size: 0.95rem; transition: 0.3s; }
        nav a:hover { color: var(--primary); }
        .btn-portal { background: var(--primary); color: #fff; padding: 10px 25px; border-radius: 30px; font-weight: 700; box-shadow: 0 5px 15px rgba(90,2,2,0.2); }
        .btn-portal:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(90,2,2,0.3); color: #fff; }

        /* --- Hero Section --- */
        .hero {
            height: 90vh;
            background: linear-gradient(135deg, rgba(90,2,2,0.02) 0%, rgba(255,255,255,1) 100%);
            display: flex; align-items: center; padding: 0 5%;
            position: relative; overflow: hidden;
        }
        .hero::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 600px;
            height: 600px;
            background-image: var(--bg-logo);
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
            opacity: 0.05;
            z-index: 1;
        }
        .hero-content { max-width: 700px; z-index: 10; position: relative; animation: fadeInUp 1s ease; }

        .hero-tag { background: #fdf2f2; color: var(--primary); padding: 6px 15px; border-radius: 50px; font-size: 0.85rem; font-weight: 900; margin-bottom: 20px; display: inline-block; border: 1px solid rgba(90,2,2,0.1); }
        .hero h2 { font-size: 3.5rem; line-height: 1.2; font-weight: 900; color: var(--primary); margin-bottom: 25px; }
        .hero p { font-size: 1.2rem; color: #555; margin-bottom: 40px; }
        .hero-btns { display: flex; gap: 20px; }
        .btn-main { background: var(--primary); color: #fff; padding: 15px 40px; border-radius: 50px; text-decoration: none; font-weight: 700; font-size: 1.1rem; box-shadow: 0 10px 30px rgba(90,2,2,0.3); transition: 0.3s; }
        .btn-main:hover { transform: translateY(-5px); box-shadow: 0 15px 40px rgba(90,2,2,0.4); }
        .btn-secondary { background: #fff; color: var(--primary); padding: 15px 40px; border-radius: 50px; text-decoration: none; font-weight: 700; font-size: 1.1rem; border: 2px solid var(--primary); transition: 0.3s; }
        .btn-secondary:hover { background: #fdf2f2; }

        .hero-image { position: absolute; left: 5%; top: 55%; transform: translateY(-50%); width: 450px; z-index: 5; opacity: 0.8; }
        
        /* --- Hero Slider --- */
        .hero { 
            height: auto; padding: 120px 5% 40px; 
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }
        .slider-frame {
            position: relative; width: 100%; height: 65vh;
            border-radius: 30px; overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            border: 12px solid #fff;
            background: #fff;
        }
        .hero-slider {
            position: absolute; inset: 0; width: 100%; height: 100%; z-index: 0;
        }
        .slide {
            position: absolute; inset: 0;
            opacity: 0; transition: opacity 1.5s ease-in-out;
            overflow: hidden;
        }
        .slide-blur-bg {
            position: absolute; inset: -20px;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            filter: blur(20px) brightness(0.85);
            opacity: 0.7;
            transform: scale(1);
            transition: transform 6.5s cubic-bezier(0.16, 1, 0.3, 1);
            z-index: 0;
        }
        .slide.active .slide-blur-bg {
            transform: scale(1.06);
        }
        .slide-bg {
            position: absolute; inset: 0;
            background-size: contain;
            background-position: center;
            background-repeat: no-repeat;
            transform: scale(1);
            transition: transform 6.5s cubic-bezier(0.16, 1, 0.3, 1);
            z-index: 1;
        }
        .slide.active { opacity: 1; } 
        .slide.active .slide-bg {
            transform: scale(1.05);
        }
        @font-face { font-family: 'RabarTitle'; src: url('assets/font/Rabar_013.ttf') format('truetype'); }
        .slide-caption {
            position: absolute; bottom: 40px; right: 40px;
            padding: 15px 30px;
            background: #0c7179;
            color: #fff; font-family: 'RabarTitle', sans-serif; font-size: 1.5rem;
            z-index: 2; transform: translateY(20px); opacity: 0;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            transition: all 0.8s cubic-bezier(0.4, 0, 0.2, 1);
            max-width: 80%;
        }
        .slide.active .slide-caption { transform: translateY(0); opacity: 1; }
        
        /* Mobile Adjustments */
        @media (max-width: 768px) {
            .hero { padding: 100px 15px 30px; }
            .slider-frame { height: 300px; border-width: 6px; border-radius: 20px; }
            .slide-caption { 
                bottom: 20px; right: 20px; 
                font-size: 1.1rem; padding: 10px 20px;
                max-width: calc(100% - 40px);
            }
        }
        
        .hero-overlay {
            position: absolute; inset: 0; background: rgba(0,0,0,0.05);
            z-index: 1;
        }
        
        /* --- Activities Section --- */
        section { padding: 100px 5%; }
        .section-header { text-align: center; margin-bottom: 60px; }
        .section-header h3 { font-size: 2.2rem; font-weight: 900; color: var(--primary); margin-bottom: 10px; }
        .section-header p { color: #888; font-size: 1rem; }

        .activities-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 40px; }
        .activity-card { 
            background: #fff; border-radius: 24px; overflow: hidden; box-shadow: var(--shadow); 
            transition: 0.4s; border: 1px solid rgba(0,0,0,0.03); 
            display: flex; flex-direction: column;
        }
        .activity-card:hover { transform: translateY(-15px); box-shadow: 0 30px 60px rgba(0,0,0,0.15); }
        .activity-img { height: 240px; background-size: cover; background-position: center; position: relative; }
        .activity-date { position: absolute; bottom: 20px; right: 20px; background: rgba(255,255,255,0.9); padding: 5px 15px; border-radius: 50px; font-size: 0.8rem; font-weight: 700; color: var(--primary); }
        .activity-body { padding: 30px; flex-grow: 1; display: flex; flex-direction: column; }
        .activity-body h4 { font-size: 1.3rem; font-weight: 900; margin-bottom: 15px; color: var(--dark); }
        .activity-body p { font-size: 0.95rem; color: #666; margin-bottom: 25px; line-height: 1.7; flex-grow: 1; }
        .read-more { color: var(--primary); font-weight: 900; text-decoration: none; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; }
        .read-more:hover { gap: 12px; }

        /* --- Library Preview --- */
        .library-preview { background: #fdf2f2; }
        .book-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 30px; }
        .book-item { text-align: center; cursor: pointer; }
        .book-cover-wrap {
            width: 100%; aspect-ratio: 2/3;
            border-radius: 14px; margin-bottom: 15px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
            transition: 0.3s; overflow: hidden;
            border: 1px solid rgba(0,0,0,0.05);
            position: relative;
        }
        .book-cover-wrap:hover { transform: scale(1.05) rotate(1.5deg); box-shadow: 0 15px 35px rgba(0,0,0,0.2); }
        /* Real cover = bg image */
        .book-cover-wrap.has-cover {
            background-size: cover; background-position: center;
        }
        /* No cover = fallback book image */
        .book-cover-wrap.no-cover {
            background-image: url('https://images.unsplash.com/photo-1543002588-bfa74002ed7e?q=80&w=300&auto=format&fit=crop');
            background-size: cover; background-position: center;
        }
        .book-item h5 { font-size: 0.95rem; font-weight: 900; color: var(--dark); margin-bottom: 5px; line-height:1.3; }
        .book-item p { font-size: 0.8rem; color: #888; }

        /* New Badge & Flashing */
        .new-badge {
            position: absolute; top: 10px; left: 10px;
            background: #ff3b30; color: #fff;
            font-size: 0.68rem; font-family: 'RabarBold';
            padding: 3px 9px; border-radius: 20px;
            z-index: 5; box-shadow: 0 2px 10px rgba(255,59,48,0.4);
            animation: badgeFlash 2s infinite;
            pointer-events: none;
        }
        @keyframes badgeFlash {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.6; transform: scale(0.92); }
        }

        /* ===== BOOK DETAIL MODAL (Sync with library.php) ===== */
        .modal-backdrop {
            display:none; position:fixed; inset:0;
            background:rgba(0,0,0,0.55); backdrop-filter:blur(6px);
            z-index:5000; align-items:center; justify-content:center; padding:16px;
        }
        .modal-backdrop.open { display:flex; }
        .modal-box {
            background:#fff; border-radius:22px;
            width:100%; max-width:520px;
            max-height:92vh; overflow-y:auto;
            animation:modalIn 0.28s cubic-bezier(.34,1.56,.64,1);
            box-shadow:0 30px 90px rgba(0,0,0,0.28);
            text-align: right;
        }
        @keyframes modalIn { from{opacity:0;transform:scale(0.88) translateY(30px)} to{opacity:1;transform:scale(1) translateY(0)} }
        .modal-header {
            background:var(--primary); color:#fff;
            padding:18px 22px; border-radius:22px 22px 0 0;
            display:flex; justify-content:space-between; align-items:center;
        }
        .modal-header h3 { font-family:'RabarBold'; font-size:1rem; }
        .modal-close {
            background:rgba(255,255,255,0.18); border:none; color:#fff;
            width:30px; height:30px; border-radius:50%; cursor:pointer;
            font-size:1rem; display:flex; align-items:center; justify-content:center;
        }
        .modal-cover-hero { width:100%; height:200px; background:#ddd; background-size:cover; background-position:center; position:relative; }
        .modal-cover-hero::after { content:''; position:absolute; inset:0; background:linear-gradient(to top, rgba(0,0,0,0.55) 0%, transparent 60%); }
        .modal-cover-hero .modal-cat-badge {
            position:absolute; bottom:14px; right:16px; z-index:2;
            background:var(--gold); color:#1a1a1a;
            padding:4px 14px; border-radius:20px; font-size:0.78rem; font-family:'RabarBold';
        }
        .modal-cover-hero.no-img {
            background:linear-gradient(135deg,var(--primary) 0%,#0a3d40 100%);
            display:flex; align-items:center; justify-content:center;
        }
        .modal-fallback-icon { font-size:4rem; opacity:0.5; }
        .modal-content { padding:22px; }
        .modal-title { font-family:'RabarBold'; font-size:1.3rem; color:var(--dark); margin-bottom:18px; }
        .modal-persons { display:flex; flex-wrap:wrap; gap:10px; margin-bottom:14px; }
        .modal-person {
            display:flex; align-items:center; gap:8px;
            background:#f4f4f6; border-radius:12px; padding:10px 16px;
            cursor:pointer; transition:0.2s;
        }
        .modal-person:hover { background:var(--primary-light); }
        .modal-person-info small { display:block; font-size:0.68rem; color:#aaa; }
        .modal-person-info strong { font-family:'RabarBold'; color:var(--dark); font-size:0.9rem; }
        .modal-meta { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:16px; }
        .modal-meta-chip { background:#f4f4f6; border-radius:10px; padding:7px 14px; }
        .modal-meta-chip small { display:block; font-size:0.68rem; color:#aaa; }
        .modal-meta-chip strong { font-family:'RabarBold'; color:var(--dark); font-size:0.85rem; }
        .modal-desc-block { background:#f8f9fb; border-radius:12px; padding:14px 16px; margin-bottom:14px; border-right:3px solid var(--primary); }
        .modal-desc-label { font-size:0.72rem; color:var(--primary); font-family:'RabarBold'; margin-bottom:6px; }
        .modal-desc-text { font-size:0.88rem; color:#555; line-height:1.75; }
        .modal-footer { padding:0 22px 18px; }
        .modal-close-btn { background:#f0f0f0; border:none; padding:10px 28px; border-radius:10px; cursor:pointer; font-family:'Rabar'; color:#555; }


        /* --- Footer --- */
        footer { background: var(--dark); color: #fff; padding: 80px 5% 40px; }
        .footer-grid { display: grid; grid-template-columns: 2fr 1fr 1.5fr; gap: 80px; margin-bottom: 60px; }
        .footer-about h4 { color: var(--gold); font-size: 1.5rem; margin-bottom: 20px; }
        .footer-about p { color: #aaa; font-size: 1rem; margin-bottom: 25px; }
        .social-links { display: flex; gap: 15px; }
        .social-btn { width: 40px; height: 40px; background: rgba(255,255,255,0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; transition: 0.3s; }
        .social-btn:hover { background: var(--primary); transform: translateY(-5px); }

        .footer-links h5 { font-size: 1.1rem; margin-bottom: 25px; position: relative; }
        .footer-links h5::after { content: ''; position: absolute; right: 0; bottom: -8px; width: 40px; height: 3px; background: var(--gold); }
        .footer-links ul { list-style: none; }
        .footer-links li { margin-bottom: 12px; }
        .footer-links a { color: #aaa; text-decoration: none; transition: 0.3s; }
        .footer-links a:hover { color: var(--gold); padding-right: 8px; }

        .footer-bottom { border-top: 1px solid rgba(255,255,255,0.1); padding-top: 30px; text-align: center; color: #666; font-size: 0.9rem; }

        @keyframes fadeInUp { from { opacity: 0; transform: translateY(50px); } to { opacity: 1; transform: translateY(0); } }

        /* --- Mobile App Mode --- */
        @media (max-width: 768px) {
            header { padding: 10px 5%; }
            .logo-text h1 { font-size: 1.1rem; }
            .logo-text p { font-size: 0.8rem; }
            nav { display: none; } /* Hide top nav on mobile */
            
            .mobile-nav {
                display: flex; position: fixed; bottom: 0; left: 0; width: 100%;
                background: #fff; border-top: 1px solid #eee; z-index: 2000;
                justify-content: space-around; padding: 10px 0;
                box-shadow: 0 -5px 20px rgba(0,0,0,0.05);
            }
            .mobile-nav a {
                text-decoration: none; color: #777; font-size: 0.75rem;
                display: flex; flex-direction: column; align-items: center; gap: 4px;
                font-family: 'Rabar';
            }
            .mobile-nav a.active { color: var(--primary); font-weight: 700; }
            .mobile-nav-icon { font-size: 1.4rem; }

            .hero { height: auto; padding: 100px 5% 40px; text-align: center; }
            .hero h2 { font-size: 1.8rem; margin-bottom: 15px; }
            .hero-image { display: none !important; }
            .hero::before { width: 250px; height: 250px; opacity: 0.1; }
            .hero-btns { flex-direction: column; gap: 12px; margin-top: 30px; }
            .btn-main, .btn-secondary { width: 100%; text-align: center; padding: 12px 20px; font-size: 1rem; }
            
            .activities-grid { grid-template-columns: 1fr; }
            .book-grid { grid-template-columns: 1fr 1fr; gap: 12px; }
            .footer-grid { grid-template-columns: 1fr; gap: 30px; }
            section { padding: 50px 5%; }

        }

        @media (min-width: 769px) {
            .mobile-nav { display: none; }
        }

    </style>
</head>
<body>

    <header>
        <a href="index.php" class="logo">
            <?php 
            $display_logo = $site['logo_path'] ?? '';
            // If we are in public_portal/ and logo is in assets/, we need to go up one level
            if (!empty($display_logo) && !file_exists($display_logo) && file_exists('../' . $display_logo)) {
                $display_logo = '../' . $display_logo;
            }
            
            if (!empty($display_logo)): ?>
                <img src="<?php echo htmlspecialchars($display_logo); ?>" alt="Logo" style="height: 50px; width: auto; border-radius: 8px;">
            <?php else: ?>
                <div class="logo-box"><?php echo mb_substr($site_name, 0, 1, 'UTF-8'); ?></div>
            <?php endif; ?>
            <div class="logo-text">
                <h1><?php echo htmlspecialchars($site_name); ?></h1>
                <p><?php echo htmlspecialchars($site_subtitle); ?></p>
            </div>
        </a>


        <nav>
            <ul>
                <li><a href="index.php">سەرەکی</a></li>
                <li><a href="#activities">چالاکییەکان</a></li>
                <li><a href="library.php">کتێبخانە</a></li>
                <li><a href="#about">دەربارە</a></li>
                <li><a href="../member_app/login.php" class="btn-portal" style="background: linear-gradient(135deg, #c5a059 0%, #a68138 100%);"><i class="fas fa-mobile-alt"></i> ئەپی ئەندامان</a></li>
                <li><a href="admin/login.php" class="btn-portal">چوونەژوورەوە</a></li>
            </ul>
        </nav>
    </header>


    <main>
        <!-- Hero Section (Framed Slideshow) -->
        <section class="hero">
            <div class="slider-frame">
                <!-- Hero Slider Background -->
                <div class="hero-slider" id="heroSlider">
                    <?php
                    $slider_res = $conn->query("
                        SELECT i.image_path, a.title 
                        FROM public_activity_images i
                        JOIN public_activities a ON i.activity_id = a.id
                        WHERE a.is_published = 1
                        ORDER BY RAND()
                        LIMIT 10
                    ");
                    $slides = [];
                    if ($slider_res && $slider_res->num_rows > 0) {
                        while($s = $slider_res->fetch_assoc()) $slides[] = $s;
                    } else {
                        $slides[] = ['image_path' => 'https://images.unsplash.com/photo-1507842217343-583bb7270b66?q=80&w=1000&auto=format&fit=crop', 'title' => 'مەڵبەندی پیرخدر'];
                    }
                    
                    foreach($slides as $index => $slide):
                        $active = ($index === 0) ? 'active' : '';
                    ?>
                        <div class="slide <?php echo $active; ?>">
                            <div class="slide-blur-bg" style="background-image: url('<?php echo htmlspecialchars($slide['image_path']); ?>');"></div>
                            <div class="slide-bg" style="background-image: url('<?php echo htmlspecialchars($slide['image_path']); ?>');"></div>
                            <div class="slide-caption">
                                <?php echo htmlspecialchars($slide['title']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="hero-overlay"></div>
                </div>
            </div>
        </section>

        <script>
            // Hero Slider Logic
            const slides = document.querySelectorAll('#heroSlider .slide');
            let currentSlide = 0;
            if (slides.length > 1) {
                setInterval(() => {
                    slides[currentSlide].classList.remove('active');
                    currentSlide = (currentSlide + 1) % slides.length;
                    slides[currentSlide].classList.add('active');
                }, 5000);
            }
        </script>

        <!-- Activities Section -->
        <section id="activities">
            <div class="section-header">
                <h3>دوایین چالاکییەکان</h3>
                <p>ئاگاداری دوایین کار و پڕۆژە و سیمینارەکانی مەڵبەند بە</p>
            </div>
            
            <div class="activities-grid">
                <?php
                $act_res = $conn->query("SELECT * FROM public_activities WHERE is_published = 1 ORDER BY activity_date DESC, id DESC LIMIT 6");
                if ($act_res && $act_res->num_rows > 0):
                    while($act = $act_res->fetch_assoc()):
                        $a_img = $act['image_path'] ?: 'https://images.unsplash.com/photo-1517048676732-d65bc937f952?q=80&w=800&auto=format&fit=crop';
                ?>
                <div class="activity-card">
                    <div class="activity-img" style="background-image: url('<?php echo htmlspecialchars($a_img); ?>');">
                        <div class="activity-date">
                            <span><?php echo str_replace('-', '/', $act['activity_date']); ?></span>
                            <span style="margin-right: 10px; font-size: 0.8rem; opacity: 0.9;">👁️ <?php echo $act['view_count']; ?></span>
                        </div>
                    </div>
                    <div class="activity-body">
                        <h4><?php echo htmlspecialchars($act['title']); ?></h4>
                        <p><?php echo mb_substr(strip_tags($act['content']), 0, 120, 'UTF-8') . '...'; ?></p>
                        <a href="activity_detail.php?id=<?php echo $act['id']; ?>" class="read-more">درێژەی بابەت ←</a>
                    </div>
                </div>
                <?php endwhile; 
                else: ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 60px; color: #aaa;">
                    <span style="font-size: 3rem; display: block; margin-bottom: 20px;">📢</span>
                    <h4>هیچ چالاکییەک هێشتا بڵاونەکراوەتەوە</h4>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Library Section -->
        <section id="library" class="library-preview">
            <div class="section-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                <div>
                    <h3>کتێبخانەی ئۆنلاین</h3>
                    <p>گەڕان بکە لە نێوان هەزاران ناونیشانی زانستی و ئەدەبی</p>
                </div>
                <a href="library.php" style="background:var(--primary); color:#fff; padding:10px 24px; border-radius:30px; font-family:'RabarBold'; font-size:0.9rem; text-decoration:none; white-space:nowrap;">بینینی هەموو ←</a>
            </div>

            <div class="book-grid">
                <?php
                $books_res = $conn->query("SELECT * FROM public_books ORDER BY created_at DESC LIMIT 8");

                if ($books_res && $books_res->num_rows > 0):
                    while($bk = $books_res->fetch_assoc()):
                        $has_cover = !empty($bk['cover_image']);
                ?>
                <div class="book-item" 
                     onclick="handleCardClick(this)" 
                     ondblclick="openBook(this)"
                     data-book='<?php echo htmlspecialchars(json_encode([
                        "title"       => $bk["title"],
                        "author"      => $bk["author_name"]     ?? "",
                        "translator"  => $bk["translator_name"] ?? "",
                        "category"    => $bk["category_name"]   ?? "",
                        "code"        => ($bk["shelf_code"] ?? "" ) . "-" . ($bk["book_code"] ?? ""),
                        "year"        => $bk["publish_year"]     ?? "",
                        "desc"        => $bk["description"]      ?? "",
                        "cover"       => $bk["cover_image"] ?: "https://images.unsplash.com/photo-1543002588-bfa74002ed7e?q=80&w=300&auto=format&fit=crop"
                     ]), ENT_QUOTES); ?>'>
                    <?php if ($has_cover): ?>
                    <div class="book-cover-wrap has-cover"
                         style="background-image:url('<?php echo htmlspecialchars($bk['cover_image']); ?>');">
                         <span class="new-badge">نوێ</span>
                    </div>
                    <?php else: ?>
                    <div class="book-cover-wrap no-cover">
                         <span class="new-badge">نوێ</span>
                    </div>
                    <?php endif; ?>
                    <h5><?php echo htmlspecialchars($bk['title']); ?></h5>
                    <p><?php echo htmlspecialchars($bk['author_name'] ?: ''); ?></p>
                </div>

                <?php 
                    endwhile;
                else:
                ?>
                    <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #999;">هیچ کتێبێک هێشتا هاوکات نەکراوە.</div>
                <?php endif; ?>
            </div>


            <div style="text-align: center; margin-top: 50px;">
                <a href="library.php" class="btn-secondary">بینینی هەموو کتێبەکان</a>
            </div>
        </section>
    </main>

    <footer id="about">
        <div class="footer-grid">
            <div class="footer-about">
                <h4><?php echo htmlspecialchars($site_name); ?></h4>
                <p><?php echo htmlspecialchars($site_desc); ?></p>
                <div class="social-links">

                    <a href="#" class="social-btn">f</a>
                    <a href="#" class="social-btn">i</a>
                    <a href="#" class="social-btn">y</a>
                    <a href="#" class="social-btn">t</a>
                </div>
            </div>
            <div class="footer-links">
                <h5>بەستەرەکان</h5>
                <ul>
                    <li><a href="#">سەرەتا</a></li>
                    <li><a href="#activities">چالاکییەکان</a></li>
                    <li><a href="#library">کتێبخانە</a></li>
                    <li><a href="#">پەیوەندی</a></li>
                </ul>
            </div>
            <div class="footer-links">
                <h5>زانیاری پەیوەندی</h5>
                <ul>
                    <li style="color:#aaa;">📍 سلێمانی - گەڕەکی کوردسات</li>
                    <li style="color:#aaa;">📞 07701078999</li>
                    <li style="color:#aaa;">✉️ info@pirxdry.org</li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>سەرجەم مافەکان پارێزراوە بۆ مەڵبەندی پیرخدر &copy; ٢٠٢٦</p>
        </div>
    </footer>

    <div class="mobile-nav">
        <a href="index.php" class="active">
            <span class="mobile-nav-icon">🏠</span>
            <span>سەرەکی</span>
        </a>
        <a href="#activities">
            <span class="mobile-nav-icon">📢</span>
            <span>چالاکی</span>
        </a>
        <a href="#library">
            <span class="mobile-nav-icon">📚</span>
            <span>کتێبخانە</span>
        </a>
        <a href="admin/login.php">
            <span class="mobile-nav-icon">👤</span>
            <span>چوونەژوورە</span>
        </a>
    </div>

    <!-- Book Detail Modal -->
    <div class="modal-backdrop" id="bookModal" onclick="closeOnBackdrop(event)">
        <div class="modal-box">
            <div class="modal-header">
                <h3>زانیارییەکانی کتێب</h3>
                <button class="modal-close" onclick="closeModal()">&#x2715;</button>
            </div>
            <div class="modal-cover-hero" id="modal-cover-hero">
                <span class="modal-cat-badge" id="modal-cat"></span>
            </div>
            <div class="modal-content">
                <div class="modal-title" id="modal-title"></div>
                <div class="modal-persons">
                    <div class="modal-person" id="chip-author" onclick="filterByPerson('author')">
                        <span style="font-size:1.1rem;">✍️</span>
                        <div class="modal-person-info"><small>نووسەر</small><strong id="modal-author">—</strong></div>
                    </div>
                    <div class="modal-person" id="chip-translator" onclick="filterByPerson('translator')">
                        <span style="font-size:1.1rem;">🌍</span>
                        <div class="modal-person-info"><small>وەرگێڕ</small><strong id="modal-translator">—</strong></div>
                    </div>
                </div>
                <div class="modal-meta">
                    <div class="modal-meta-chip" id="chip-year"><small>ساڵی چاپ</small><strong id="modal-year">—</strong></div>
                    <div class="modal-meta-chip"><small>کۆدی کتێب</small><strong id="modal-code">—</strong></div>
                </div>
                <div class="modal-desc-block" id="modal-desc-block">
                    <div class="modal-desc-label">📖 دەربارەی کتێب</div>
                    <div class="modal-desc-text" id="modal-desc"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="modal-close-btn" onclick="closeModal()">داخستن</button>
            </div>
        </div>
    </div>

    <script>
        let currentBook = {};
        function openBook(card) {
            const d = JSON.parse(card.getAttribute('data-book'));
            currentBook = d;
            document.getElementById('modal-title').textContent = d.title || '—';
            const hero = document.getElementById('modal-cover-hero');
            if (d.cover && d.cover.indexOf('unsplash') === -1) {
                hero.style.backgroundImage = `url('${d.cover}')`;
                hero.classList.remove('no-img');
                const fi = hero.querySelector('.modal-fallback-icon'); if (fi) fi.remove();
            } else {
                hero.style.backgroundImage = ''; hero.classList.add('no-img');
                if (!hero.querySelector('.modal-fallback-icon')) {
                    const ic = document.createElement('span'); ic.className = 'modal-fallback-icon'; ic.textContent = '📖';
                    hero.appendChild(ic);
                }
            }
            const cat = document.getElementById('modal-cat');
            cat.textContent = d.category || ''; cat.style.display = d.category ? 'inline-block' : 'none';
            document.getElementById('modal-author').textContent = d.author || '—';
            const chipTrans = document.getElementById('chip-translator');
            if (d.translator) { document.getElementById('modal-translator').textContent = d.translator; chipTrans.style.display = 'flex'; } 
            else { chipTrans.style.display = 'none'; }
            const chipYear = document.getElementById('chip-year');
            if (d.year) { document.getElementById('modal-year').textContent = d.year; chipYear.style.display = 'block'; } 
            else { chipYear.style.display = 'none'; }
            document.getElementById('modal-code').textContent = d.code || '—';
            const descBlock = document.getElementById('modal-desc-block');
            const descText  = document.getElementById('modal-desc');
            if (d.desc && d.desc.trim()) { descText.textContent = d.desc; descBlock.style.display = 'block'; } 
            else { descBlock.style.display = 'none'; }
            document.getElementById('bookModal').classList.add('open');
            document.body.style.overflow = 'hidden';
        }

        let lastTap = 0;
        function handleCardClick(card) {
            const now = Date.now();
            if (now - lastTap < 300 && now - lastTap > 0) { openBook(card); lastTap = 0; } 
            else { lastTap = now; }
        }

        function filterByPerson(type) {
            const name = type === 'author' ? currentBook.author : currentBook.translator;
            if (!name) return;
            window.location.href = 'library.php?q=' + encodeURIComponent(name);
        }
        function closeModal() { document.getElementById('bookModal').classList.remove('open'); document.body.style.overflow = ''; }
        function closeOnBackdrop(e) { if (e.target === document.getElementById('bookModal')) closeModal(); }
        document.addEventListener('keydown', e => { if(e.key === 'Escape') closeModal(); });
    </script>
</body>
</html>

