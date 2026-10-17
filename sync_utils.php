<?php
/**
 * Synchronization Utility for Public Website
 * Handles sending book data to www.pirxdry.org
 * Fetches connection details from local settings database
 */

function syncBookToPublic($bookIdOrData, $action = 'save') {
    require_once 'config.php';
    $conn = getDB();
    
    // Fetch Remote Sync Settings from DB
    $res = $conn->query("SELECT remote_sync_host, remote_sync_db, remote_sync_user, remote_sync_pass, remote_sync_port, remote_sync_key FROM settings WHERE id = 1");
    $settings = $res->fetch_assoc();
    
    if (!$settings || empty($settings['remote_sync_host'])) {
        return ['success' => false, 'message' => 'Remote sync not configured'];
    }

    $bookData = is_array($bookIdOrData) ? $bookIdOrData : ['id' => $bookIdOrData];

    if ($action === 'save') {
        // Fetch FULL book details for sync
        $id = intval($bookData['id']);
        $bq = $conn->query("
            SELECT b.*, a.name AS author_name, t.name AS translator_name, c.name AS category_name, u.full_name AS creator_name
            FROM books b
            LEFT JOIN authors     a ON b.author_id     = a.id
            LEFT JOIN translators t ON b.translator_id = t.id
            LEFT JOIN categories  c ON b.category_id   = c.id
            LEFT JOIN users       u ON b.user_id       = u.id
            WHERE b.id = $id
        ");
        if ($bq && $bq->num_rows > 0) {
            $bookData = $bq->fetch_assoc();
        }
    }

    $r_host = $settings['remote_sync_host'];
    $r_db   = $settings['remote_sync_db'];
    $r_user = $settings['remote_sync_user'];
    $r_pass = $settings['remote_sync_pass'];
    $r_port = $settings['remote_sync_port'] ?: '3306';
    $r_key  = $settings['remote_sync_key'];

    // METHOD A: DIRECT DATABASE CONNECTION (Disabled due to 21-second TCP timeout on firewalled port 3306)
    // Directly use the API method which is faster and more reliable over HTTP/HTTPS
    return syncViaAPI($settings, $bookData, $action);
}

function syncAllBooksToPublic($syncSettings = null) {
    require_once 'config.php';
    $conn = getDB();

    if (!$syncSettings) {
        $res = $conn->query("SELECT remote_sync_host, remote_sync_key FROM settings WHERE id = 1");
        $syncSettings = $res->fetch_assoc();
    }

    if (!$syncSettings || empty($syncSettings['remote_sync_host'])) {
        return ['success' => false, 'message' => 'هۆستی دوور ڕێکنەخراوە'];
    }

    // Fetch ALL books with author, translator and category names
    $books_res = $conn->query("
        SELECT b.id, b.title, b.book_code, b.shelf_code, b.cover_image,
               b.publish_year, b.description, b.quantity, b.available,
               a.name  AS author_name,
               t.name  AS translator_name,
               c.name  AS category_name,
               u.full_name AS creator_name
        FROM books b
        LEFT JOIN authors     a ON b.author_id     = a.id
        LEFT JOIN translators t ON b.translator_id = t.id
        LEFT JOIN categories  c ON b.category_id   = c.id
        LEFT JOIN users       u ON b.user_id       = u.id
        ORDER BY b.id ASC
    ");

    if (!$books_res) {
        return ['success' => false, 'message' => 'هەڵە لە خوێندنەوەی کتێبەکان: ' . $conn->error];
    }

    $books = [];
    while ($bk = $books_res->fetch_assoc()) {
        $books[] = [
            'id'              => (int)$bk['id'],
            'title'           => $bk['title'],
            'author_name'     => $bk['author_name']     ?? '',
            'translator_name' => $bk['translator_name'] ?? '',
            'category_name'   => $bk['category_name']   ?? '',
            'book_code'       => $bk['book_code']        ?? '',
            'shelf_code'      => $bk['shelf_code']       ?? '',
            'publish_year'    => $bk['publish_year']     ?? '',
            'description'     => $bk['description']     ?? '',
            'quantity'        => (int)$bk['quantity'],
            'available'       => (int)$bk['available'],
            'creator_name'    => $bk['creator_name']     ?? '',
            'cover_image'     => $bk['cover_image']      ?? '',
        ];
    }

    if (empty($books)) {
        return ['success' => false, 'message' => 'هیچ کتێبێک لە داتابەیس نەدۆزرایەوە'];
    }

    // Build API URL
    $host = $syncSettings['remote_sync_host'];
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https://" : "http://";
    $public_api_url = (strpos($host, 'http') === 0)
        ? rtrim($host, '/') . '/api/sync_books.php'
        : $protocol . rtrim($host, '/') . '/api/sync_books.php';

    // Chunk books to prevent payload size issues and execution timeouts
    $batchSize = 25;
    $batches = array_chunk($books, $batchSize);
    
    $totalSynced = 0;
    $totalErrors = 0;
    $lastMessage = '';
    $allSuccess = true;
    $lastDecoded = null;

    foreach ($batches as $batchIndex => $batchBooks) {
        // Encode cover images to base64 for each book in the batch if they exist locally
        $processedBooks = [];
        foreach ($batchBooks as $bk) {
            $bk['cover_image_base64'] = '';
            if (!empty($bk['cover_image']) && file_exists($bk['cover_image'])) {
                $bk['cover_image_base64'] = base64_encode(file_get_contents($bk['cover_image']));
            }
            $processedBooks[] = $bk;
        }

        $payload = [
            'sync_key' => $syncSettings['remote_sync_key'],
            'action'   => 'sync_all_books',
            'books'    => $processedBooks
        ];

        $ch = curl_init($public_api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 40); // 40 seconds timeout per batch
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $result   = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            return [
                'success' => false, 
                'message' => "کێشەی CURL لە گرووپی " . ($batchIndex + 1) . ": " . $curlErr,
                'total_sent' => count($books)
            ];
        }

        $decoded = json_decode($result, true);
        if ($httpCode === 200 && ($decoded['success'] ?? false)) {
            $totalSynced += ($decoded['synced'] ?? count($batchBooks));
            $totalErrors += ($decoded['errors'] ?? 0);
            $lastMessage = $decoded['message'] ?? '';
            $lastDecoded = $decoded;
        } else {
            $allSuccess = false;
            $lastMessage = $decoded['message'] ?? "پۆرت ناڵ وەڵامی نەدایەوە بە کۆدی $httpCode";
            $lastDecoded = $decoded;
            break; // Stop on first error
        }
    }

    return [
        'success'      => $allSuccess,
        'message'      => $lastMessage ?: "هاوکات کرا: $totalSynced کتێب",
        'total_sent'   => count($books),
        'api_response' => $lastDecoded ?: ['success' => $allSuccess, 'message' => $lastMessage, 'synced' => $totalSynced, 'errors' => $totalErrors]
    ];
}

function syncSettingsToPublic($settingsData, $syncSettings = null) {
    require_once 'config.php';
    $conn = getDB();
    
    if (!$syncSettings) {
        $res = $conn->query("SELECT remote_sync_host, remote_sync_key FROM settings WHERE id = 1");
        $syncSettings = $res->fetch_assoc();
    }
    
    if (!$syncSettings || empty($syncSettings['remote_sync_host'])) return ['success' => false, 'message' => 'Remote host not configured'];

    $host = $syncSettings['remote_sync_host'];

    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https://" : "http://";
    
    if (strpos($host, 'http') === 0) {
        $public_api_url = rtrim($host, '/') . '/api/sync_books.php';
    } else {
        $public_api_url = $protocol . rtrim($host, '/') . '/api/sync_books.php';
    }

    $data = [
        'sync_key' => $syncSettings['remote_sync_key'],
        'action' => 'sync_settings',
        'settings' => $settingsData
    ];


    $ch = curl_init($public_api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return ['success' => false, 'message' => 'CURL Error: ' . $curlError];
    }
    
    return [
        'success' => ($httpCode === 200),
        'message' => ($httpCode === 200) ? 'Success' : "HTTP Error: $httpCode",
        'api_response' => $result
    ];
}


function syncViaAPI($settings, $bookData, $action) {
    $host = $settings['remote_sync_host'];
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https://" : "http://";
    
    if (strpos($host, 'http') === 0) {
        $public_api_url = rtrim($host, '/') . '/api/sync_books.php';
    } else {
        $public_api_url = $protocol . rtrim($host, '/') . '/api/sync_books.php';
    }
    
    if ($action === 'save') {
        $bookData['cover_image_base64'] = '';
        if (!empty($bookData['cover_image']) && file_exists($bookData['cover_image'])) {
            $bookData['cover_image_base64'] = base64_encode(file_get_contents($bookData['cover_image']));
        }
    }

    $data = [
        'sync_key' => $settings['remote_sync_key'],
        'action' => $action,
        'book' => $bookData
    ];

    $ch = curl_init($public_api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['success' => ($httpCode === 200), 'method' => 'api'];
}

function syncAllActivitiesToPublic($syncSettings = null) {
    require_once 'config.php';
    $conn = getDB();

    if (!$syncSettings) {
        $res = $conn->query("SELECT remote_sync_host, remote_sync_key FROM settings WHERE id = 1");
        $syncSettings = $res->fetch_assoc();
    }

    if (!$syncSettings || empty($syncSettings['remote_sync_host'])) {
        return ['success' => false, 'message' => 'هۆستی دوور ڕێکنەخراوە'];
    }

    // Fetch ALL public_activities from local database
    $act_res = $conn->query("SELECT * FROM public_activities ORDER BY id ASC");
    if (!$act_res) {
        return ['success' => false, 'message' => 'خشتەی public_activities لە داتابەیسی لۆکاڵ بوونی نییە یان هەڵەیە: ' . $conn->error];
    }

    $activities = [];
    while ($row = $act_res->fetch_assoc()) {
        $activities[] = $row;
    }

    // Fetch ALL public_activity_images from local database
    $img_res = $conn->query("SELECT * FROM public_activity_images ORDER BY id ASC");
    $images = [];
    if ($img_res) {
        while ($row = $img_res->fetch_assoc()) {
            $images[] = $row;
        }
    }

    if (empty($activities)) {
        return ['success' => false, 'message' => 'هیچ چالاکییەک لە داتابەیسی لۆکاڵ نەدۆزرایەوە'];
    }

    // Build API URL
    $host = $syncSettings['remote_sync_host'];
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https://" : "http://";
    $public_api_url = (strpos($host, 'http') === 0)
        ? rtrim($host, '/') . '/api/sync_books.php'
        : $protocol . rtrim($host, '/') . '/api/sync_books.php';

    $payload = [
        'sync_key'   => $syncSettings['remote_sync_key'],
        'action'     => 'sync_all_activities',
        'activities' => $activities,
        'images'     => $images
    ];

    $ch = curl_init($public_api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $result   = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    $close    = curl_close($ch);

    if ($curlErr) return ['success' => false, 'message' => 'CURL Error: ' . $curlErr];

    $decoded = json_decode($result, true);
    return [
        'success'      => ($httpCode === 200 && ($decoded['success'] ?? false)),
        'message'      => $decoded['message'] ?? "HTTP $httpCode",
        'total_sent'   => count($activities),
        'api_response' => $decoded
    ];
}

function pullAllBooksFromPublic($syncSettings = null) {
    @set_time_limit(300);
    @ini_set('max_execution_time', 300);

    require_once 'config.php';
    $conn = getDB();

    if (!$syncSettings) {
        $res = $conn->query("SELECT remote_sync_host, remote_sync_key FROM settings WHERE id = 1");
        $syncSettings = $res->fetch_assoc();
    }

    if (!$syncSettings || empty($syncSettings['remote_sync_host'])) {
        return ['success' => false, 'message' => 'هۆستی دوور ڕێکنەخراوە'];
    }

    // Build API URL
    $host = $syncSettings['remote_sync_host'];
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https://" : "http://";
    $public_api_url = (strpos($host, 'http') === 0)
        ? rtrim($host, '/') . '/api/sync_books.php'
        : $protocol . rtrim($host, '/') . '/api/sync_books.php';

    $payload = [
        'sync_key' => $syncSettings['remote_sync_key'],
        'action'   => 'pull_all_books'
    ];

    $ch = curl_init($public_api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // give it more time if there's a lot of books
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $result   = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    if (PHP_VERSION_ID < 80500) {
        @curl_close($ch);
    }

    if ($curlErr) {
        return ['success' => false, 'message' => 'CURL Error: ' . $curlErr];
    }

    $decoded = json_decode($result, true);
    if ($httpCode !== 200 || !($decoded['success'] ?? false)) {
        return ['success' => false, 'message' => $decoded['message'] ?? "وەڵامی سێرڤەر سەرکەوتوو نەبوو: HTTP $httpCode"];
    }

    $remoteBooks = $decoded['books'] ?? [];
    if (empty($remoteBooks)) {
        return ['success' => true, 'message' => 'هیچ کتێبێک لەسەر سێرڤەر نییە بۆ هاوردەکردن', 'pulled' => 0, 'updated' => 0, 'downloaded_covers' => 0];
    }

    // Cache categories, authors, translators to prevent thousands of redundant queries
    $cat_cache = [];
    $r = $conn->query("SELECT id, name FROM categories");
    if ($r) while ($row = $r->fetch_assoc()) $cat_cache[mb_strtolower(trim($row['name']))] = (int)$row['id'];

    $auth_cache = [];
    $r = $conn->query("SELECT id, name FROM authors");
    if ($r) while ($row = $r->fetch_assoc()) $auth_cache[mb_strtolower(trim($row['name']))] = (int)$row['id'];

    $trans_cache = [];
    $r = $conn->query("SELECT id, name FROM translators");
    if ($r) while ($row = $r->fetch_assoc()) $trans_cache[mb_strtolower(trim($row['name']))] = (int)$row['id'];

    $insertedCount = 0;
    $updatedCount = 0;
    $downloadedCovers = 0;

    foreach ($remoteBooks as $bk) {
        $remote_id        = (int)($bk['id'] ?? 0);
        $title            = trim($bk['title'] ?? '');
        $author_name      = trim($bk['author_name'] ?? '');
        $translator_name  = trim($bk['translator_name'] ?? '');
        $category_name    = trim($bk['category_name'] ?? '');
        $book_code        = trim($bk['book_code'] ?? '');
        $shelf_code       = trim($bk['shelf_code'] ?? '');
        $publish_year     = !empty($bk['publish_year']) ? (int)$bk['publish_year'] : null;
        $description      = $bk['description'] ?? '';
        $quantity         = (int)($bk['quantity'] ?? 1);
        $available        = (int)($bk['available'] ?? 1);
        $cover_image      = $bk['cover_image'] ?? '';

        if (empty($title)) continue;

        // 1. Resolve Category ID
        $category_id = null;
        if (!empty($category_name)) {
            $cat_key = mb_strtolower($category_name);
            if (isset($cat_cache[$cat_key])) {
                $category_id = $cat_cache[$cat_key];
            } else {
                $ins_stmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
                $ins_stmt->bind_param("s", $category_name);
                if ($ins_stmt->execute()) {
                    $category_id = $conn->insert_id;
                    $cat_cache[$cat_key] = $category_id;
                }
                $ins_stmt->close();
            }
        }

        // 2. Resolve Author ID
        $author_id = null;
        if (!empty($author_name)) {
            $auth_key = mb_strtolower($author_name);
            if (isset($auth_cache[$auth_key])) {
                $author_id = $auth_cache[$auth_key];
            } else {
                $ins_stmt = $conn->prepare("INSERT INTO authors (name) VALUES (?)");
                $ins_stmt->bind_param("s", $author_name);
                if ($ins_stmt->execute()) {
                    $author_id = $conn->insert_id;
                    $auth_cache[$auth_key] = $author_id;
                }
                $ins_stmt->close();
            }
        }

        // 3. Resolve Translator ID
        $translator_id = null;
        if (!empty($translator_name)) {
            $trans_key = mb_strtolower($translator_name);
            if (isset($trans_cache[$trans_key])) {
                $translator_id = $trans_cache[$trans_key];
            } else {
                $ins_stmt = $conn->prepare("INSERT INTO translators (name) VALUES (?)");
                $ins_stmt->bind_param("s", $translator_name);
                if ($ins_stmt->execute()) {
                    $translator_id = $conn->insert_id;
                    $trans_cache[$trans_key] = $translator_id;
                }
                $ins_stmt->close();
            }
        }

        // 4. Download Cover Image if missing locally
        $final_cover_path = $cover_image;
        if (!empty($cover_image) && strpos($cover_image, 'http') !== 0) {
            $local_path = $cover_image;
            $local_dir = dirname($local_path);
            
            if (!is_dir($local_dir)) {
                mkdir($local_dir, 0777, true);
            }

            if (!file_exists($local_path)) {
                // Download file from public server
                $img_url = rtrim($host, '/') . '/' . ltrim($cover_image, '/');
                $img_ch = curl_init($img_url);
                curl_setopt($img_ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($img_ch, CURLOPT_TIMEOUT, 5);
                curl_setopt($img_ch, CURLOPT_SSL_VERIFYPEER, false);
                $img_data = curl_exec($img_ch);
                $img_code = curl_getinfo($img_ch, CURLINFO_HTTP_CODE);
                if (PHP_VERSION_ID < 80500) {
                    @curl_close($img_ch);
                }

                if ($img_code === 200 && $img_data !== false) {
                    file_put_contents($local_path, $img_data);
                    $downloadedCovers++;
                }
            }
        }

        // 5. Match book by ID (unique identity from host)
        $local_book_id = null;
        $existing_cover = '';

        if ($remote_id > 0) {
            $check_stmt = $conn->prepare("SELECT id, cover_image FROM books WHERE id = ?");
            $check_stmt->bind_param("i", $remote_id);
            $check_stmt->execute();
            $check_res = $check_stmt->get_result();
            if ($check_res && $check_res->num_rows > 0) {
                $existing_bk = $check_res->fetch_assoc();
                $local_book_id = (int)$existing_bk['id'];
                $existing_cover = $existing_bk['cover_image'] ?? '';
            }
            $check_stmt->close();
        }

        if ($local_book_id) {
            $cover_to_save = !empty($final_cover_path) ? $final_cover_path : $existing_cover;

            // Update existing book
            $up_stmt = $conn->prepare("
                UPDATE books 
                SET title = ?, author_id = ?, translator_id = ?, category_id = ?, 
                    quantity = ?, available = ?, shelf_code = ?, book_code = ?, publish_year = ?, 
                    description = ?, cover_image = ?
                WHERE id = ?
            ");
            $up_stmt->bind_param("siiiissssssi", 
                $title, $author_id, $translator_id, $category_id, 
                $quantity, $available, $shelf_code, $book_code, $publish_year, 
                $description, $cover_to_save, $local_book_id
            );
            $up_stmt->execute();
            $up_stmt->close();
            $updatedCount++;
        } else {
            // Insert new book with remote_id if remote_id > 0
            if ($remote_id > 0) {
                $ins_stmt = $conn->prepare("
                    INSERT INTO books 
                    (id, title, author_id, translator_id, category_id, quantity, available, shelf_code, book_code, publish_year, description, cover_image) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $ins_stmt->bind_param("isiiiissssss", 
                    $remote_id, $title, $author_id, $translator_id, $category_id, 
                    $quantity, $available, $shelf_code, $book_code, 
                    $publish_year, $description, $final_cover_path
                );
            } else {
                $ins_stmt = $conn->prepare("
                    INSERT INTO books 
                    (title, author_id, translator_id, category_id, quantity, available, shelf_code, book_code, publish_year, description, cover_image) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $ins_stmt->bind_param("siiiissssss", 
                    $title, $author_id, $translator_id, $category_id, 
                    $quantity, $available, $shelf_code, $book_code, 
                    $publish_year, $description, $final_cover_path
                );
            }
            $ins_stmt->execute();
            $ins_stmt->close();
            $insertedCount++;
        }
    }

    return [
        'success'           => true,
        'message'           => "داگرتن و هاوبەشکردن کۆتایی هات! زیادکراو: {$insertedCount}، نوێکراوە: {$updatedCount}، کەڤەری دابەزێنراو: {$downloadedCovers} ✅",
        'inserted'          => $insertedCount,
        'updated'           => $updatedCount,
        'downloaded_covers' => $downloadedCovers
    ];
}

/**
 * Sync ALL Lineage (Families, Members, Requests, Documents, Settings) to Host
 */
function syncAllLineageToPublic($syncSettings = null) {
    require_once 'config.php';
    $conn = getDB();

    if (!$syncSettings) {
        $res = $conn->query("SELECT remote_sync_host, remote_sync_key FROM settings WHERE id = 1");
        $syncSettings = $res->fetch_assoc();
    }

    if (!$syncSettings || empty($syncSettings['remote_sync_host'])) {
        return ['success' => false, 'message' => 'هۆستی دوور ڕێکنەخراوە'];
    }

    $host = $syncSettings['remote_sync_host'];
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https://" : "http://";
    $public_api_url = (strpos($host, 'http') === 0)
        ? rtrim($host, '/') . '/api/sync_books.php'
        : $protocol . rtrim($host, '/') . '/api/sync_books.php';

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
        while ($m = $mRes->fetch_assoc()) {
            $imgBase64 = '';
            if (!empty($m['profile_image'])) {
                $localImg = ltrim($m['profile_image'], '/');
                if (file_exists($localImg) && filesize($localImg) < 2000000) {
                    $imgBase64 = base64_encode(file_get_contents($localImg));
                } elseif (file_exists(__DIR__ . '/' . $localImg) && filesize(__DIR__ . '/' . $localImg) < 2000000) {
                    $imgBase64 = base64_encode(file_get_contents(__DIR__ . '/' . $localImg));
                }
            }
            $m['profile_image_base64'] = $imgBase64;
            $members[] = $m;
        }
    }

    // Chunk members to batches of 80 to prevent memory or HTTP post limit issues
    $batchSize = 80;
    $memberBatches = !empty($members) ? array_chunk($members, $batchSize) : [[]];
    $totalSyncedMembers = 0;

    foreach ($memberBatches as $idx => $batch) {
        $payload = [
            'sync_key'         => $syncSettings['remote_sync_key'],
            'action'           => 'sync_all_lineage',
            'batch_index'      => $idx,
            'is_first_batch'   => ($idx === 0),
            'families'         => ($idx === 0) ? $families : [],
            'lineage_settings' => ($idx === 0) ? $lineage_settings : [],
            'member_requests'  => ($idx === 0) ? $member_requests : [],
            'member_documents' => ($idx === 0) ? $member_documents : [],
            'members'          => $batch
        ];

        $ch = curl_init($public_api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $result   = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            return [
                'success' => false,
                'message' => "کێشەی CURL لە ناردنی نەسەب (گرووپی " . ($idx + 1) . "): " . $curlErr
            ];
        }

        $decoded = json_decode($result, true);
        if (!$decoded || empty($decoded['success'])) {
            return [
                'success' => false,
                'message' => "هەڵە لە سێرڤەری هۆست: " . ($decoded['message'] ?? $result)
            ];
        }

        $totalSyncedMembers += count($batch);
    }

    return [
        'success'         => true,
        'message'         => "نەسەب و ئەندامەکان بە سەرکەوتوویی هاوکات کران بۆ هۆست: " . count($families) . " بنەماڵە، " . $totalSyncedMembers . " ئەندام، " . count($member_requests) . " داواکاری بەبێ سڕینەوەی هیچ داتایەک ✅",
        'synced_families' => count($families),
        'synced_members'  => $totalSyncedMembers
    ];
}

/**
 * Pull ALL Lineage (Families, Members, Requests, Documents, Settings) from Host
 */
function pullAllLineageFromPublic($syncSettings = null) {
    require_once 'config.php';
    $conn = getDB();

    if (!$syncSettings) {
        $res = $conn->query("SELECT remote_sync_host, remote_sync_key FROM settings WHERE id = 1");
        $syncSettings = $res->fetch_assoc();
    }

    if (!$syncSettings || empty($syncSettings['remote_sync_host'])) {
        return ['success' => false, 'message' => 'هۆستی دوور ڕێکنەخراوە'];
    }

    $host = $syncSettings['remote_sync_host'];
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https://" : "http://";
    $public_api_url = (strpos($host, 'http') === 0)
        ? rtrim($host, '/') . '/api/sync_books.php'
        : $protocol . rtrim($host, '/') . '/api/sync_books.php';

    $payload = [
        'sync_key' => $syncSettings['remote_sync_key'],
        'action'   => 'pull_all_lineage'
    ];

    $ch = curl_init($public_api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $result   = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return ['success' => false, 'message' => "کێشەی CURL لە داگرتنی نەسەب: " . $curlErr];
    }

    $data = json_decode($result, true);
    if (!$data || empty($data['success'])) {
        return ['success' => false, 'message' => "هەڵە لە وەڵامی سێرڤەر: " . ($data['message'] ?? $result)];
    }

    $familiesCount = 0;
    if (!empty($data['families'])) {
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
        foreach ($data['families'] as $f) {
            $id = (int)$f['id'];
            $name = $f['family_name'];
            $area = $f['origin_area'] ?? '';
            $anc = $f['ancestor_name'] ?? '';
            $chain = $f['fixed_chain'] ?? '';
            $hist = $f['history'] ?? '';
            $pid = !empty($f['parent_family_id']) ? (int)$f['parent_family_id'] : null;
            $fStmt->bind_param("isssssi", $id, $name, $area, $anc, $chain, $hist, $pid);
            if ($fStmt->execute()) $familiesCount++;
        }
        $fStmt->close();
    }

    // Lineage settings
    if (!empty($data['lineage_settings'])) {
        $sStmt = $conn->prepare("
            INSERT INTO lineage_settings (setting_key, setting_value)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        foreach ($data['lineage_settings'] as $s) {
            $k = $s['setting_key'];
            $v = $s['setting_value'];
            $sStmt->bind_param("ss", $k, $v);
            $sStmt->execute();
        }
        $sStmt->close();
    }

    // Member Requests
    $reqCount = 0;
    if (!empty($data['member_requests'])) {
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
        foreach ($data['member_requests'] as $rq) {
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
            if ($rStmt->execute()) $reqCount++;
        }
        $rStmt->close();
    }

    // Member Documents
    $docCount = 0;
    if (!empty($data['member_documents'])) {
        $dStmt = $conn->prepare("
            INSERT INTO member_documents (id, member_id, doc_type, doc_path, created_at)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            doc_type = VALUES(doc_type),
            doc_path = VALUES(doc_path)
        ");
        foreach ($data['member_documents'] as $doc) {
            $id = (int)$doc['id'];
            $mid = (int)$doc['member_id'];
            $dtype = $doc['doc_type'];
            $dpath = $doc['doc_path'];
            $dcat = $doc['created_at'] ?? date('Y-m-d H:i:s');
            $dStmt->bind_param("iisss", $id, $mid, $dtype, $dpath, $dcat);
            if ($dStmt->execute()) $docCount++;

            // Download document file if missing locally
            if (!empty($dpath) && strpos($dpath, 'http') !== 0) {
                $localDocFile = ltrim($dpath, '/');
                if (!file_exists($localDocFile)) {
                    $dDir = dirname($localDocFile);
                    if (!is_dir($dDir)) mkdir($dDir, 0777, true);
                    $fileUrl = rtrim($host, '/') . '/' . $localDocFile;
                    $fData = @file_get_contents($fileUrl);
                    if ($fData) file_put_contents($localDocFile, $fData);
                }
            }
        }
        $dStmt->close();
    }

    // Members
    $membersCount = 0;
    if (!empty($data['members'])) {
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

        foreach ($data['members'] as $m) {
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

            // Download photo if missing locally
            if (!empty($profImg) && strpos($profImg, 'http') !== 0) {
                $localImgPath = ltrim($profImg, '/');
                if (!file_exists($localImgPath)) {
                    $imgDir = dirname($localImgPath);
                    if (!is_dir($imgDir)) mkdir($imgDir, 0777, true);
                    $imgUrl = rtrim($host, '/') . '/' . $localImgPath;
                    $imgContent = @file_get_contents($imgUrl);
                    if ($imgContent) file_put_contents($localImgPath, $imgContent);
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
            if ($mStmt->execute()) $membersCount++;
        }
        $mStmt->close();
    }

    return [
        'success' => true,
        'message' => "نەسەب و ئەندامەکان بە سەرکەوتوویی لە ماڵپەڕەوە داگیران: {$familiesCount} بنەماڵە، {$membersCount} ئەندام، {$reqCount} داواکاری، {$docCount} بەڵگەنامە بەبێ سڕینەوەی هیچ داتایەک ✅"
    ];
}
?>
