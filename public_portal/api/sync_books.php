<?php
/**
 * PUBLIC API ENDPOINT: sync_books.php
 * Receives data from the local library computer.
 */

header('Content-Type: application/json');

// Security Key - MUST MATCH THE ONE IN LOCAL sync_utils.php
$SYNC_KEY = 'PIRX_SECURE_SYNC_2026';

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || ($data['sync_key'] ?? '') !== $SYNC_KEY) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized Access - Key: ' . ($data['sync_key'] ?? 'EMPTY')]);
    exit;
}

if (file_exists(__DIR__ . '/../db_config.php')) {
    require_once __DIR__ . '/../db_config.php';
} elseif (file_exists(__DIR__ . '/db_config.php')) {
    require_once __DIR__ . '/db_config.php';
} elseif (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
} else {
    require_once __DIR__ . '/config.php';
}

$conn = function_exists('getPublicDB') ? getPublicDB() : getDB();

// ──────────────────────────────────────────────
// AUTO CHARSET HEALING: Convert database and tables to utf8mb4
// ──────────────────────────────────────────────
$conn->query("ALTER DATABASE CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$conn->query("ALTER TABLE `public_books` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$conn->query("ALTER TABLE `public_activities` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$conn->query("ALTER TABLE `public_settings` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$conn->query("ALTER TABLE `public_activity_images` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$conn->query("ALTER TABLE `public_users` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

// ──────────────────────────────────────────────
// AUTO MIGRATION: Ensure all columns and lineage tables exist
// ──────────────────────────────────────────────
function addColumnIfNotExists($conn, $table, $column, $type) {
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if ($result && $result->num_rows == 0) {
        $conn->query("ALTER TABLE `$table` ADD `$column` $type");
    }
}
addColumnIfNotExists($conn, 'public_books', 'translator_name', 'VARCHAR(255)');
addColumnIfNotExists($conn, 'public_books', 'publish_year', 'VARCHAR(10)');
addColumnIfNotExists($conn, 'public_books', 'description', 'TEXT');
addColumnIfNotExists($conn, 'public_books', 'quantity', 'INT DEFAULT 1');
addColumnIfNotExists($conn, 'public_books', 'available', 'INT DEFAULT 1');
addColumnIfNotExists($conn, 'public_books', 'creator_name', 'VARCHAR(255)');

// Auto-create Lineage tables if missing
$conn->query("CREATE TABLE IF NOT EXISTS `families` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `family_name` varchar(255) NOT NULL,
  `origin_area` varchar(255) DEFAULT NULL,
  `ancestor_name` varchar(255) DEFAULT NULL,
  `fixed_chain` text DEFAULT NULL,
  `history` text DEFAULT NULL,
  `parent_family_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$conn->query("CREATE TABLE IF NOT EXISTS `lineage_settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$conn->query("CREATE TABLE IF NOT EXISTS `members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) DEFAULT NULL,
  `member_code` varchar(50) DEFAULT NULL,
  `rank_id` int(11) DEFAULT NULL,
  `full_name` varchar(255) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `father_name` varchar(100) DEFAULT NULL,
  `grandfather_name` varchar(100) DEFAULT NULL,
  `great_grandfather_name` varchar(100) DEFAULT NULL,
  `normalized_name` varchar(255) DEFAULT NULL,
  `nickname` varchar(255) DEFAULT NULL,
  `member_rank` varchar(255) DEFAULT NULL,
  `member_status` enum('active','inactive') DEFAULT 'active',
  `is_official_member` tinyint(1) NOT NULL DEFAULT 0,
  `member_post` varchar(255) DEFAULT NULL,
  `family_id` int(11) DEFAULT NULL,
  `gender` enum('نێر','مێ') DEFAULT 'نێر',
  `name1` varchar(100) DEFAULT NULL,
  `name2` varchar(100) DEFAULT NULL,
  `name3` varchar(100) DEFAULT NULL,
  `name4` varchar(100) DEFAULT NULL,
  `bnamala` varchar(255) DEFAULT NULL,
  `unit_id` int(11) DEFAULT NULL,
  `position` varchar(255) DEFAULT NULL,
  `job` varchar(255) DEFAULT NULL,
  `post` varchar(255) DEFAULT NULL,
  `birth_year` int(11) DEFAULT NULL,
  `birth_place` varchar(255) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `address` text DEFAULT NULL,
  `marital_status` varchar(50) DEFAULT NULL,
  `children_count` int(11) DEFAULT 0,
  `branch` varchar(255) DEFAULT NULL,
  `military_branch_sinf` varchar(100) DEFAULT NULL,
  `education` varchar(255) DEFAULT NULL,
  `education_level` varchar(255) DEFAULT NULL,
  `military_education` varchar(255) DEFAULT NULL,
  `languages` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `id_card_number` varchar(50) DEFAULT NULL,
  `security_code` varchar(50) DEFAULT NULL,
  `membership_type` varchar(100) DEFAULT NULL,
  `blood_group` varchar(10) DEFAULT NULL,
  `physical_fitness` varchar(100) DEFAULT NULL,
  `health_status` text DEFAULT NULL,
  `honors_medals` text DEFAULT NULL,
  `injuries` text DEFAULT NULL,
  `last_promotion_date` date DEFAULT NULL,
  `promotion_due_date` date DEFAULT NULL,
  `absence_penalty_days` int(11) DEFAULT 0,
  `promotion_note` text DEFAULT NULL,
  `id_number` varchar(100) DEFAULT NULL,
  `appointment_order` varchar(255) DEFAULT NULL,
  `appointment_date` date DEFAULT NULL,
  `membership_date` date DEFAULT NULL,
  `profession` varchar(255) DEFAULT NULL,
  `civil_id` varchar(100) DEFAULT NULL,
  `form_number` varchar(100) DEFAULT NULL,
  `voting_number` varchar(100) DEFAULT NULL,
  `biometric_number` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `town` varchar(100) DEFAULT NULL,
  `district` varchar(100) DEFAULT NULL,
  `village` varchar(100) DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `archive_folder` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `notes` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_alive` tinyint(1) DEFAULT 1,
  `is_family_head` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_family` (`family_id`),
  KEY `idx_parent` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$conn->query("CREATE TABLE IF NOT EXISTS `member_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `request_type` varchar(50) DEFAULT 'add_child',
  `child_name` varchar(255) NOT NULL,
  `gender` enum('نێر','مێ') DEFAULT 'نێر',
  `birth_date` date DEFAULT NULL,
  `birth_year` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_member` (`member_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$conn->query("CREATE TABLE IF NOT EXISTS `member_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) DEFAULT NULL,
  `doc_type` varchar(100) DEFAULT NULL,
  `doc_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_doc_member` (`member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Check newly added columns for members
addColumnIfNotExists($conn, 'members', 'id_card_number', 'VARCHAR(50)');
addColumnIfNotExists($conn, 'members', 'security_code', 'VARCHAR(50)');
addColumnIfNotExists($conn, 'members', 'birth_date', 'DATE');
addColumnIfNotExists($conn, 'members', 'profession', 'VARCHAR(255)');
addColumnIfNotExists($conn, 'members', 'is_alive', 'TINYINT(1) DEFAULT 1');
addColumnIfNotExists($conn, 'members', 'is_family_head', 'TINYINT(1) DEFAULT 0');

$action = $data['action'] ?? '';
$book   = $data['book'] ?? null;

// ──────────────────────────────────────────────
// BULK SYNC: All books at once
// ──────────────────────────────────────────────
if ($action === 'sync_all_books') {
    $books = $data['books'] ?? [];
    if (empty($books)) {
        echo json_encode(['success' => false, 'message' => 'No books provided']);
        exit;
    }

    $sql = "INSERT INTO public_books (local_id, title, author_name, translator_name, category_name, book_code, shelf_code, publish_year, description, quantity, available, creator_name, cover_image)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            title            = VALUES(title),
            author_name      = VALUES(author_name),
            translator_name  = VALUES(translator_name),
            category_name    = VALUES(category_name),
            book_code        = VALUES(book_code),
            shelf_code       = VALUES(shelf_code),
            publish_year     = VALUES(publish_year),
            description      = VALUES(description),
            quantity         = VALUES(quantity),
            available        = VALUES(available),
            creator_name     = VALUES(creator_name),
            cover_image      = VALUES(cover_image)";

    $stmt = $conn->prepare($sql);
    $synced = 0; $errors = 0;

    foreach ($books as $bk) {
        $id    = (int)($bk['id'] ?? 0);
        $title = $bk['title'] ?? '';
        $auth  = $bk['author_name'] ?? '';
        $trans = $bk['translator_name'] ?? '';
        $cat   = $bk['category_name'] ?? '';
        $bcode = $bk['book_code'] ?? '';
        $scode = $bk['shelf_code'] ?? '';
        $year  = $bk['publish_year'] ?: null;
        $desc  = $bk['description'] ?? '';
        $qty   = (int)($bk['quantity'] ?? 1);
        $avail = (int)($bk['available'] ?? 1);
        $creator = $bk['creator_name'] ?? '';
        $cover = $bk['cover_image'] ?? '';
        if (!$id || !$title) continue;
        
        // Decode and save cover image if base64 data is present
        if (!empty($bk['cover_image_base64']) && !empty($cover)) {
            $img_data = base64_decode($bk['cover_image_base64']);
            if ($img_data !== false) {
                $dest_dir = '../uploads/books/covers/';
                if (!is_dir($dest_dir)) {
                    mkdir($dest_dir, 0777, true);
                }
                $dest_file = $dest_dir . basename($cover);
                file_put_contents($dest_file, $img_data);
            }
        }
        
        $stmt->bind_param("isssssssssiis", $id, $title, $auth, $trans, $cat, $bcode, $scode, $year, $desc, $qty, $avail, $creator, $cover);

        if ($stmt->execute()) $synced++; else $errors++;
    }

    echo json_encode([
        'success' => true,
        'synced'  => $synced,
        'errors'  => $errors,
        'message' => "هاوکات کرا: $synced کتێب"
    ]);
    $conn->close();
    exit;
}

// ──────────────────────────────────────────────
// SINGLE BOOK SAVE
// ──────────────────────────────────────────────
if ($action === 'save') {
    if (!$book || !$book['id']) {
        echo json_encode(['success' => false, 'message' => 'Invalid Book Data']);
        exit;
    }

    $sql = "INSERT INTO public_books (local_id, title, author_name, translator_name, category_name, book_code, shelf_code, publish_year, description, quantity, available, creator_name, cover_image)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            title            = VALUES(title),
            author_name      = VALUES(author_name),
            translator_name  = VALUES(translator_name),
            category_name    = VALUES(category_name),
            book_code        = VALUES(book_code),
            shelf_code       = VALUES(shelf_code),
            publish_year     = VALUES(publish_year),
            description      = VALUES(description),
            quantity         = VALUES(quantity),
            available        = VALUES(available),
            creator_name     = VALUES(creator_name),
            cover_image      = VALUES(cover_image)";

    $stmt = $conn->prepare($sql);
    
    $id      = (int)$book['id'];
    $title   = $book['title'] ?? '';
    $auth    = $book['author_name'] ?? '';
    $trans   = $book['translator_name'] ?? '';
    $cat     = $book['category_name'] ?? '';
    $bcode   = $book['book_code'] ?? '';
    $scode   = $book['shelf_code'] ?? '';
    $year    = $book['publish_year'] ?? '';
    $desc    = $book['description'] ?? '';
    $qty     = (int)($book['quantity'] ?? 1);
    $avail   = (int)($book['available'] ?? 1);
    $creator = $book['creator_name'] ?? '';
    $cover   = $book['cover_image'] ?? '';

    // Decode and save cover image if base64 data is present
    if (!empty($book['cover_image_base64']) && !empty($cover)) {
        $img_data = base64_decode($book['cover_image_base64']);
        if ($img_data !== false) {
            $dest_dir = '../uploads/books/covers/';
            if (!is_dir($dest_dir)) {
                mkdir($dest_dir, 0777, true);
            }
            $dest_file = $dest_dir . basename($cover);
            file_put_contents($dest_file, $img_data);
        }
    }

    $stmt->bind_param("isssssssssiis", $id, $title, $auth, $trans, $cat, $bcode, $scode, $year, $desc, $qty, $avail, $creator, $cover);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Synced Successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'DB Error: ' . $conn->error]);
    }

// ──────────────────────────────────────────────
// SYNC SETTINGS
// ──────────────────────────────────────────────
} elseif ($action === 'sync_settings') {
    $settings = $data['settings'] ?? null;
    if ($settings) {
        $sql = "UPDATE public_settings SET
                system_name   = ?,
                system_subtitle = ?,
                primary_color = ?,
                description   = ?,
                logo_path     = ?
                WHERE id = 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssss", $settings['name'], $settings['subtitle'], $settings['color'], $settings['desc'], $settings['logo']);
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'Settings Synced']);
    }

// ──────────────────────────────────────────────
// SYNC ALL ACTIVITIES
// ──────────────────────────────────────────────
} elseif ($action === 'sync_all_activities') {
    $activities = $data['activities'] ?? [];
    $images     = $data['images'] ?? [];

    // Clear existing images mapping before syncing to avoid duplicates
    $conn->query("TRUNCATE TABLE public_activity_images");

    // Sync Activities
    if (!empty($activities)) {
        $sql = "INSERT INTO public_activities (id, title, content, image_path, activity_date, is_published, view_count)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                title         = VALUES(title),
                content       = VALUES(content),
                image_path    = VALUES(image_path),
                activity_date = VALUES(activity_date),
                is_published  = VALUES(is_published)";
        $stmt = $conn->prepare($sql);
        foreach ($activities as $act) {
            $id      = (int)$act['id'];
            $title   = $act['title'];
            $content = $act['content'];
            $img     = $act['image_path'];
            $date    = $act['activity_date'];
            $pub     = (int)$act['is_published'];
            $views   = (int)($act['view_count'] ?? 0);
            $stmt->bind_param("isssssi", $id, $title, $content, $img, $date, $pub, $views);
            $stmt->execute();
        }
    }

    // Sync Activity Images
    if (!empty($images)) {
        $sql = "INSERT INTO public_activity_images (id, activity_id, image_path) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        foreach ($images as $img) {
            $id     = (int)$img['id'];
            $act_id = (int)$img['activity_id'];
            $path   = $img['image_path'];
            $stmt->bind_param("iis", $id, $act_id, $path);
            $stmt->execute();
        }
    }

    echo json_encode(['success' => true, 'message' => 'Activities and Images Synced Successfully']);
    $conn->close();
    exit;

} elseif ($action === 'pull_all_books') {
    $res = $conn->query("SELECT * FROM public_books ORDER BY id ASC");
    $books = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $books[] = [
                'id'              => (int)($row['local_id'] ?: $row['id']),
                'title'           => $row['title'],
                'author_name'     => $row['author_name']     ?? '',
                'translator_name' => $row['translator_name'] ?? '',
                'category_name'   => $row['category_name']   ?? '',
                'book_code'       => $row['book_code']        ?? '',
                'shelf_code'      => $row['shelf_code']       ?? '',
                'publish_year'    => $row['publish_year']     ?? '',
                'description'     => $row['description']     ?? '',
                'quantity'        => (int)($row['quantity']   ?? 1),
                'available'       => (int)($row['available']  ?? 1),
                'creator_name'    => $row['creator_name']     ?? '',
                'cover_image'     => $row['cover_image']      ?? '',
            ];
        }
    }
    echo json_encode(['success' => true, 'books' => $books]);
    $conn->close();
    exit;

// ──────────────────────────────────────────────
// DELETE SINGLE BOOK
// ──────────────────────────────────────────────
} elseif ($action === 'delete') {
    $id = (int)($book['id'] ?? 0);
    // PROTECT ONLINE-ADDED BOOKS: Never delete books with local_id >= 900000000 from remote requests
    if ($id >= 900000000) {
        echo json_encode(['success' => true, 'message' => 'Online book protected from deletion']);
        exit;
    }
    $stmt = $conn->prepare("DELETE FROM public_books WHERE local_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    echo json_encode(['success' => true, 'message' => 'Deleted Successfully']);

// ──────────────────────────────────────────────
// SYNC ALL LINEAGE (FAMILIES, MEMBERS, REQUESTS, DOCUMENTS, SETTINGS)
// ──────────────────────────────────────────────
} elseif ($action === 'sync_all_lineage') {
    $families         = $data['families'] ?? [];
    $lineage_settings = $data['lineage_settings'] ?? [];
    $member_requests  = $data['member_requests'] ?? [];
    $member_documents = $data['member_documents'] ?? [];
    $members          = $data['members'] ?? [];

    $syncedFamilies = 0;
    $syncedMembers = 0;
    $syncedRequests = 0;
    $syncedDocs = 0;

    // 1. Sync Families
    if (!empty($families)) {
        $fStmt = $conn->prepare("
            INSERT INTO families (id, family_name, origin_area, ancestor_name, fixed_chain, history, parent_family_id)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            family_name = VALUES(family_name),
            origin_area = VALUES(origin_area),
            ancestor_name = VALUES(ancestor_name),
            fixed_chain = VALUES(fixed_chain),
            history = VALUES(history),
            parent_family_id = VALUES(parent_family_id)
        ");
        foreach ($families as $f) {
            $id = (int)$f['id'];
            $name = $f['family_name'];
            $area = $f['origin_area'] ?? '';
            $anc = $f['ancestor_name'] ?? '';
            $chain = $f['fixed_chain'] ?? '';
            $hist = $f['history'] ?? '';
            $pid = !empty($f['parent_family_id']) ? (int)$f['parent_family_id'] : null;
            $fStmt->bind_param("isssssi", $id, $name, $area, $anc, $chain, $hist, $pid);
            if ($fStmt->execute()) $syncedFamilies++;
        }
        $fStmt->close();
    }

    // 2. Sync Lineage Settings
    if (!empty($lineage_settings)) {
        $sStmt = $conn->prepare("
            INSERT INTO lineage_settings (setting_key, setting_value)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        foreach ($lineage_settings as $s) {
            $k = $s['setting_key'];
            $v = $s['setting_value'];
            $sStmt->bind_param("ss", $k, $v);
            $sStmt->execute();
        }
        $sStmt->close();
    }

    // 3. Sync Member Requests
    if (!empty($member_requests)) {
        $rStmt = $conn->prepare("
            INSERT INTO member_requests (id, member_id, request_type, child_name, gender, birth_date, birth_year, notes, status, admin_notes, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            child_name = VALUES(child_name),
            gender = VALUES(gender),
            birth_date = VALUES(birth_date),
            birth_year = VALUES(birth_year),
            notes = VALUES(notes),
            status = VALUES(status),
            admin_notes = VALUES(admin_notes)
        ");
        foreach ($member_requests as $rq) {
            $id = (int)$rq['id'];
            $mid = (int)$rq['member_id'];
            $rtype = $rq['request_type'] ?? 'add_child';
            $cname = $rq['child_name'];
            $gen = $rq['gender'] ?? 'نێر';
            $bdate = !empty($rq['birth_date']) ? $rq['birth_date'] : null;
            $byear = !empty($rq['birth_year']) ? (int)$rq['birth_year'] : null;
            $notes = $rq['notes'] ?? '';
            $status = $rq['status'] ?? 'pending';
            $anotes = $rq['admin_notes'] ?? '';
            $cat = $rq['created_at'] ?? date('Y-m-d H:i:s');
            $rStmt->bind_param("iissssissss", $id, $mid, $rtype, $cname, $gen, $bdate, $byear, $notes, $status, $anotes, $cat);
            if ($rStmt->execute()) $syncedRequests++;
        }
        $rStmt->close();
    }

    // 4. Sync Member Documents
    if (!empty($member_documents)) {
        $dStmt = $conn->prepare("
            INSERT INTO member_documents (id, member_id, doc_type, doc_path, created_at)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            doc_type = VALUES(doc_type),
            doc_path = VALUES(doc_path)
        ");
        foreach ($member_documents as $doc) {
            $id = (int)$doc['id'];
            $mid = (int)$doc['member_id'];
            $dtype = $doc['doc_type'];
            $dpath = $doc['doc_path'];
            $dcat = $doc['created_at'] ?? date('Y-m-d H:i:s');
            $dStmt->bind_param("iisss", $id, $mid, $dtype, $dpath, $dcat);
            if ($dStmt->execute()) $syncedDocs++;
        }
        $dStmt->close();
    }

    // 5. Sync Members
    if (!empty($members)) {
        $mStmt = $conn->prepare("
            INSERT INTO members (
                id, parent_id, member_code, full_name, first_name, father_name, grandfather_name, great_grandfather_name,
                nickname, member_status, is_official_member, family_id, gender, birth_year, birth_place, birth_date,
                address, marital_status, children_count, education, education_level, languages, phone, id_card_number,
                security_code, blood_group, appointment_date, membership_date, profession, job, profile_image, notes,
                sort_order, is_alive, is_family_head
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?
            )
            ON DUPLICATE KEY UPDATE
                parent_id = VALUES(parent_id),
                member_code = VALUES(member_code),
                full_name = VALUES(full_name),
                first_name = VALUES(first_name),
                father_name = VALUES(father_name),
                grandfather_name = VALUES(grandfather_name),
                great_grandfather_name = VALUES(great_grandfather_name),
                nickname = VALUES(nickname),
                member_status = VALUES(member_status),
                is_official_member = VALUES(is_official_member),
                family_id = VALUES(family_id),
                gender = VALUES(gender),
                birth_year = VALUES(birth_year),
                birth_place = VALUES(birth_place),
                birth_date = VALUES(birth_date),
                address = VALUES(address),
                marital_status = VALUES(marital_status),
                children_count = VALUES(children_count),
                education = VALUES(education),
                education_level = VALUES(education_level),
                languages = VALUES(languages),
                phone = VALUES(phone),
                id_card_number = VALUES(id_card_number),
                security_code = VALUES(security_code),
                blood_group = VALUES(blood_group),
                appointment_date = VALUES(appointment_date),
                membership_date = VALUES(membership_date),
                profession = VALUES(profession),
                job = VALUES(job),
                profile_image = CASE WHEN VALUES(profile_image) != '' AND VALUES(profile_image) IS NOT NULL THEN VALUES(profile_image) ELSE profile_image END,
                notes = VALUES(notes),
                sort_order = VALUES(sort_order),
                is_alive = VALUES(is_alive),
                is_family_head = VALUES(is_family_head)
        ");

        $baseUploadDir = file_exists(__DIR__ . '/../uploads') ? __DIR__ . '/../' : (file_exists(__DIR__ . '/uploads') ? __DIR__ . '/' : __DIR__ . '/../../');

        foreach ($members as $m) {
            $id = (int)$m['id'];
            $parentId = !empty($m['parent_id']) ? (int)$m['parent_id'] : null;
            $code = $m['member_code'] ?? '';
            $fullName = $m['full_name'];
            $firstName = $m['first_name'] ?? '';
            $fatherName = $m['father_name'] ?? '';
            $grandName = $m['grandfather_name'] ?? '';
            $greatGrandName = $m['great_grandfather_name'] ?? '';
            $nick = $m['nickname'] ?? '';
            $mStatus = $m['member_status'] ?? 'active';
            $isOfficial = (int)($m['is_official_member'] ?? 0);
            $familyId = !empty($m['family_id']) ? (int)$m['family_id'] : null;
            $gender = $m['gender'] ?? 'نێر';
            $birthYear = !empty($m['birth_year']) ? (int)$m['birth_year'] : null;
            $birthPlace = $m['birth_place'] ?? '';
            $birthDate = !empty($m['birth_date']) ? $m['birth_date'] : null;
            $address = $m['address'] ?? '';
            $marital = $m['marital_status'] ?? '';
            $childrenCount = (int)($m['children_count'] ?? 0);
            $edu = $m['education'] ?? '';
            $eduLevel = $m['education_level'] ?? '';
            $lang = $m['languages'] ?? '';
            $phone = $m['phone'] ?? '';
            $idCard = $m['id_card_number'] ?? '';
            $secCode = $m['security_code'] ?? '';
            $blood = $m['blood_group'] ?? '';
            $appDate = !empty($m['appointment_date']) ? $m['appointment_date'] : null;
            $memDate = !empty($m['membership_date']) ? $m['membership_date'] : null;
            $prof = $m['profession'] ?? '';
            $job = $m['job'] ?? '';
            $profImg = $m['profile_image'] ?? '';
            $notes = $m['notes'] ?? '';
            $sort = (int)($m['sort_order'] ?? 0);
            $isAlive = isset($m['is_alive']) ? (int)$m['is_alive'] : 1;
            $isHead = (int)($m['is_family_head'] ?? 0);

            // Decode and save member photo if base64 provided
            if (!empty($m['profile_image_base64']) && !empty($profImg)) {
                $imgData = base64_decode($m['profile_image_base64']);
                if ($imgData !== false) {
                    $targetPath = $baseUploadDir . ltrim($profImg, '/');
                    $targetDir = dirname($targetPath);
                    if (!is_dir($targetDir)) @mkdir($targetDir, 0777, true);
                    @file_put_contents($targetPath, $imgData);
                }
            }

            $mStmt->bind_param(
                "iissssssssiisissssissssssssssssiii",
                $id, $parentId, $code, $fullName, $firstName, $fatherName, $grandName, $greatGrandName,
                $nick, $mStatus, $isOfficial, $familyId, $gender, $birthYear, $birthPlace, $birthDate,
                $address, $marital, $childrenCount, $edu, $eduLevel, $lang, $phone, $idCard,
                $secCode, $blood, $appDate, $memDate, $prof, $job, $profImg, $notes,
                $sort, $isAlive, $isHead
            );
            if ($mStmt->execute()) $syncedMembers++;
        }
        $mStmt->close();
    }

    echo json_encode([
        'success'         => true,
        'message'         => 'Lineage synced successfully',
        'synced_families' => $syncedFamilies,
        'synced_members'  => $syncedMembers,
        'synced_requests' => $syncedRequests,
        'synced_docs'     => $syncedDocs
    ]);
    $conn->close();
    exit;

// ──────────────────────────────────────────────
// PULL ALL LINEAGE (DOWNLOAD FROM HOST)
// ──────────────────────────────────────────────
} elseif ($action === 'pull_all_lineage') {
    // 1. Fetch Families
    $families = [];
    $fRes = $conn->query("SELECT * FROM families ORDER BY id ASC");
    if ($fRes) {
        while ($f = $fRes->fetch_assoc()) $families[] = $f;
    }

    // 2. Fetch Lineage Settings
    $lineage_settings = [];
    $sRes = $conn->query("SELECT * FROM lineage_settings");
    if ($sRes) {
        while ($s = $sRes->fetch_assoc()) $lineage_settings[] = $s;
    }

    // 3. Fetch Member Requests
    $member_requests = [];
    $rRes = $conn->query("SELECT * FROM member_requests ORDER BY id ASC");
    if ($rRes) {
        while ($rq = $rRes->fetch_assoc()) $member_requests[] = $rq;
    }

    // 4. Fetch Member Documents
    $member_documents = [];
    $dRes = $conn->query("SELECT * FROM member_documents ORDER BY id ASC");
    if ($dRes) {
        while ($doc = $dRes->fetch_assoc()) $member_documents[] = $doc;
    }

    // 5. Fetch Members
    $members = [];
    $mRes = $conn->query("SELECT * FROM members ORDER BY id ASC");
    if ($mRes) {
        while ($m = $mRes->fetch_assoc()) $members[] = $m;
    }

    echo json_encode([
        'success'          => true,
        'families'         => $families,
        'lineage_settings' => $lineage_settings,
        'member_requests'  => $member_requests,
        'member_documents' => $member_documents,
        'members'          => $members
    ]);
    $conn->close();
    exit;
}

$conn->close();
?>
