<?php
require_once 'config.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

$conn = getDB();

if ($conn === null) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

switch ($action) {
    case 'get_book_titles':
        $search = $_GET['search'] ?? '';
        $sql = "SELECT DISTINCT title FROM books";
        if ($search) {
            $sql .= " WHERE title LIKE ? LIMIT 50";
            $stmt = $conn->prepare($sql);
            $search_param = "%$search%";
            $stmt->bind_param("s", $search_param);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $sql .= " ORDER BY title ASC LIMIT 100";
            $result = $conn->query($sql);
        }
        $titles = [];
        while ($row = $result->fetch_assoc()) { $titles[] = $row['title']; }
        echo json_encode($titles);
        break;

    case 'get_authors':
        $search = $_GET['search'] ?? '';
        $sql = "SELECT id, name FROM authors";
        if ($search) {
            $sql .= " WHERE name LIKE ?";
            $stmt = $conn->prepare($sql);
            $search_param = "%$search%";
            $stmt->bind_param("s", $search_param);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $conn->query($sql);
        }
        
        $authors = [];
        while ($row = $result->fetch_assoc()) {
            $authors[] = $row;
        }
        echo json_encode($authors);
        break;
        
    case 'get_translators':
        $search = $_GET['search'] ?? '';
        $sql = "SELECT id, name FROM translators";
        if ($search) {
            $sql .= " WHERE name LIKE ?";
            $stmt = $conn->prepare($sql);
            $search_param = "%$search%";
            $stmt->bind_param("s", $search_param);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $conn->query($sql);
        }
        
        $translators = [];
        while ($row = $result->fetch_assoc()) {
            $translators[] = $row;
        }
        echo json_encode($translators);
        break;
        
    case 'get_categories':
        $search = $_GET['search'] ?? '';
        $sql = "SELECT id, name FROM categories";
        if ($search) {
            $sql .= " WHERE name LIKE ? ORDER BY name ASC";
            $stmt = $conn->prepare($sql);
            $search_param = "%$search%";
            $stmt->bind_param("s", $search_param);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $sql .= " ORDER BY name ASC";
            $result = $conn->query($sql);
        }
        
        $categories = [];
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
        echo json_encode($categories);
        break;
        
    case 'add_author':
        $name = trim($_POST['name'] ?? '');
        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'ناو پێویستە']);
            exit;
        }
        
        $check_sql = "SELECT id FROM authors WHERE name = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            echo json_encode(['success' => true, 'id' => $row['id'], 'exists' => true]);
        } else {
            $insert_sql = "INSERT INTO authors (name) VALUES (?)";
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("s", $name);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'id' => $conn->insert_id, 'exists' => false]);
            } else {
                echo json_encode(['success' => false, 'message' => 'هەڵە لە زیادکردن']);
            }
        }
        break;
        
    case 'add_translator':
        $name = trim($_POST['name'] ?? '');
        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'ناو پێویستە']);
            exit;
        }
        
        $check_sql = "SELECT id FROM translators WHERE name = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            echo json_encode(['success' => true, 'id' => $row['id'], 'exists' => true]);
        } else {
            $insert_sql = "INSERT INTO translators (name) VALUES (?)";
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("s", $name);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'id' => $conn->insert_id, 'exists' => false]);
            } else {
                echo json_encode(['success' => false, 'message' => 'هەڵە لە زیادکردن']);
            }
        }
        break;
        
    case 'add_category':
        $name = trim($_POST['name'] ?? '');
        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'ناو پێویستە']);
            exit;
        }
        
        $check_sql = "SELECT id FROM categories WHERE name = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            echo json_encode(['success' => true, 'id' => $row['id'], 'exists' => true]);
        } else {
            $insert_sql = "INSERT INTO categories (name) VALUES (?)";
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("s", $name);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'id' => $conn->insert_id, 'exists' => false]);
            } else {
                echo json_encode(['success' => false, 'message' => 'هەڵە لە زیادکردن']);
            }
        }
        break;
        
    case 'update_category':
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if (!$id || empty($name)) {
            echo json_encode(['success' => false, 'error' => 'زانیارییەکان تەواو نین']);
            break;
        }
        $stmt = $conn->prepare("UPDATE categories SET name = ? WHERE id = ?");
        $stmt->bind_param("si", $name, $id);
        if ($stmt->execute()) echo json_encode(['success' => true]);
        else echo json_encode(['success' => false, 'error' => $stmt->error]);
        break;

    case 'delete_category':
        $id = intval($_POST['id'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'ID نەدۆزرایەوە']);
            break;
        }
        // Check if books exist in this category
        $check = $conn->query("SELECT id FROM books WHERE category_id = $id LIMIT 1");
        if ($check && $check->num_rows > 0) {
            echo json_encode(['success' => false, 'error' => 'ناتوانیت ئەم بەشە بسڕیتەوە چونکە کتێبی تێدایە']);
            break;
        }
        $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) echo json_encode(['success' => true]);
        else echo json_encode(['success' => false, 'error' => $stmt->error]);
        break;
        
    case 'filter_books':
        $category_id = $_GET['category_id'] ?? '';
        $author_id = $_GET['author_id'] ?? '';
        $translator_id = $_GET['translator_id'] ?? '';
        $year = $_GET['year'] ?? '';
        $search = $_GET['search'] ?? '';
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 30;
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $offset = ($page - 1) * $limit;
        
        $where = " WHERE 1=1";
        $params = [];
        $types = "";
        
        if ($category_id) {
            $where .= " AND b.category_id = ?";
            $params[] = $category_id;
            $types .= "i";
        }
        if ($author_id) {
            $where .= " AND b.author_id = ?";
            $params[] = $author_id;
            $types .= "i";
        }
        if ($translator_id) {
            $where .= " AND b.translator_id = ?";
            $params[] = $translator_id;
            $types .= "i";
        }
        if ($year) {
            $where .= " AND b.publish_year = ?";
            $params[] = $year;
            $types .= "s";
        }
        if ($search) {
            $cleanSearch = str_replace('-', '', $search);
            $search_param = "%$search%";
            $clean_param = "%$cleanSearch%";
            
            $where .= " AND (b.title LIKE ? OR b.book_code LIKE ? OR b.description LIKE ? OR b.shelf_code LIKE ? OR a.name LIKE ? OR t.name LIKE ? OR c.name LIKE ? OR CONCAT(b.shelf_code, '-', b.book_code) LIKE ? OR REPLACE(CONCAT(b.shelf_code, b.book_code), '-', '') LIKE ?)";
            
            $params[] = $search_param; // title
            $params[] = $search_param; // book_code
            $params[] = $search_param; // description
            $params[] = $search_param; // shelf_code
            $params[] = $search_param; // author
            $params[] = $search_param; // translator
            $params[] = $search_param; // category
            $params[] = $search_param; // concat with dash
            $params[] = $clean_param;  // concat without dash
            
            for($i=0; $i<9; $i++) { $types .= "s"; }
        }

        // Get total count for pagination (Added joins to support search in related tables)
        $count_sql = "SELECT COUNT(*) as total FROM books b 
                     LEFT JOIN authors a ON b.author_id = a.id 
                     LEFT JOIN translators t ON b.translator_id = t.id 
                     LEFT JOIN categories c ON b.category_id = c.id 
                     $where";
        $stmt_count = $conn->prepare($count_sql);
        if (!empty($params)) {
            $stmt_count->bind_param($types, ...$params);
        }
        $stmt_count->execute();
        $total_records = $stmt_count->get_result()->fetch_assoc()['total'];
        
        // Get paginated data
        $group_by = $_GET['group_by'] ?? '';
        $order_by = "b.id DESC";
        if ($group_by === 'category') {
            $order_by = "c.name ASC, b.id DESC";
        }

        $sql = "SELECT b.*, a.name as author_name, t.name as translator_name, c.name as category_name, u.full_name as creator_name 
                FROM books b 
                LEFT JOIN authors a ON b.author_id = a.id 
                LEFT JOIN translators t ON b.translator_id = t.id 
                LEFT JOIN categories c ON b.category_id = c.id 
                LEFT JOIN users u ON b.user_id = u.id 
                $where
                ORDER BY $order_by LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $books = [];
        while ($row = $result->fetch_assoc()) {
            $books[] = $row;
        }
        
        echo json_encode([
            'books' => $books,
            'total' => $total_records,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total_records / $limit)
        ]);
        break;
        
    case 'get_book':
        $id = $_GET['id'] ?? 0;
        $sql = "SELECT b.*, a.name as author_name, t.name as translator_name, c.name as category_name, u.full_name as creator_name 
                FROM books b 
                LEFT JOIN authors a ON b.author_id = a.id 
                LEFT JOIN translators t ON b.translator_id = t.id 
                LEFT JOIN categories c ON b.category_id = c.id 
                LEFT JOIN users u ON b.user_id = u.id 
                WHERE b.id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            echo json_encode($row);
        } else {
            echo json_encode(['error' => 'کتێب نەدۆزرایەوە']);
        }
        break;
        
    case 'search_user':
        $search = trim($_GET['search'] ?? '');
        
        $sql = "SELECT id, username, full_name, role FROM users WHERE username LIKE ? OR full_name LIKE ? ORDER BY username LIMIT 10";
        $stmt = $conn->prepare($sql);
        $search_param = "%$search%";
        $stmt->bind_param("ss", $search_param, $search_param);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        echo json_encode($users);
        break;
        
    case 'add_user':
        $username = trim($_POST['username'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $unit = trim($_POST['unit'] ?? '');
        
        if (empty($username)) {
            echo json_encode(['success' => false, 'message' => 'ناوی بەکارهێنەر پێویستە']);
            exit;
        }
        
        // Convert name to username (remove spaces, make lowercase)
        $username_clean = preg_replace('/\s+/', '', strtolower($username));
        $username_clean = preg_replace('/[^\w]/', '', $username_clean); // Remove special chars
        
        if (empty($username_clean)) {
            $username_clean = 'user' . time();
        }
        
        // Check if username or full_name exists
        $check_sql = "SELECT id FROM users WHERE username = ? OR full_name = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("ss", $username_clean, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            echo json_encode(['success' => true, 'id' => $row['id'], 'exists' => true]);
        } else {
            // Make username unique if exists
            $base_username = $username_clean;
            $counter = 1;
            while (true) {
                $check_exists = "SELECT id FROM users WHERE username = ?";
                $stmt2 = $conn->prepare($check_exists);
                $stmt2->bind_param("s", $username_clean);
                $stmt2->execute();
                $result2 = $stmt2->get_result();
                
                if ($result2->num_rows == 0) {
                    break;
                }
                
                $username_clean = $base_username . $counter;
                $counter++;
            }
            
            $default_password = password_hash('123456', PASSWORD_DEFAULT);
            $insert_sql = "INSERT INTO users (username, password, role, full_name, phone, unit) VALUES (?, ?, 'user', ?, ?, ?)";
            $stmt3 = $conn->prepare($insert_sql);
            $stmt3->bind_param("sssss", $username_clean, $default_password, $username, $phone, $unit);
            
            if ($stmt3->execute()) {
                echo json_encode([
                    'success' => true, 
                    'id' => $conn->insert_id, 
                    'exists' => false,
                    'username' => $username_clean,
                    'message' => 'بەکارهێنەری نوێ زیادکرا'
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'هەڵە لە زیادکردن: ' . $stmt3->error]);
            }
        }
        break;
        
    case 'get_borrow_records':
        $book_id = $_GET['book_id'] ?? 0;
        
        $sql = "SELECT br.*, u.username, u.full_name, u.phone, u.unit, b.title as book_title, b.book_code, b.shelf_code 
                FROM borrow_records br 
                JOIN users u ON br.user_id = u.id 
                JOIN books b ON br.book_id = b.id";
        
        if ($book_id > 0) {
            $sql .= " WHERE br.book_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $book_id);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $conn->query($sql);
        }
        
        $records = [];
        while ($row = $result->fetch_assoc()) {
            $records[] = $row;
        }
        echo json_encode($records);
        break;
        
    case 'borrow_book':
        $book_id = $_POST['book_id'] ?? 0;
        $user_id = $_POST['user_id'] ?? 0;
        $borrow_date = $_POST['borrow_date'] ?? date('Y-m-d');
        $due_date = $_POST['due_date'] ?? date('Y-m-d', strtotime('+14 days'));
        
        if (!$book_id || !$user_id) {
            echo json_encode(['success' => false, 'message' => 'زانیاری تەواو پێویستە']);
            exit;
        }
        
        // 1. Check availability
        $check_q = $conn->prepare("SELECT available FROM books WHERE id = ?");
        $check_q->bind_param("i", $book_id);
        $check_q->execute();
        $res_q = $check_q->get_result()->fetch_assoc();
        
        if (!$res_q || $res_q['available'] <= 0) {
            echo json_encode(['success' => false, 'message' => 'ئەم کتێبە لە ئێستادا بەردەست نییە (بڕەکەی سفرە)']);
            exit;
        }

        // 2. Insert record
        $sql = "INSERT INTO borrow_records (book_id, user_id, borrow_date, due_date, status) VALUES (?, ?, ?, ?, 'borrowed')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiss", $book_id, $user_id, $borrow_date, $due_date);
        
        if ($stmt->execute()) {
            // 3. Decrement available count
            $conn->query("UPDATE books SET available = available - 1 WHERE id = $book_id");
            echo json_encode(['success' => true, 'message' => 'کتێبەکە خواستراوە']);
        } else {
            echo json_encode(['success' => false, 'message' => 'هەڵە: ' . $stmt->error]);
        }
        break;
        
    case 'return_book':
        $record_id = $_POST['record_id'] ?? 0;
        
        if (!$record_id) {
            echo json_encode(['success' => false, 'message' => 'تۆمار نەدۆزرایەوە']);
            exit;
        }
        
        $return_date = date('Y-m-d');
        $sql = "UPDATE borrow_records SET status = 'returned', return_date = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $return_date, $record_id);
        
        if ($stmt->execute()) {
            // Get book_id to increment available count
            $res_b = $conn->query("SELECT book_id FROM borrow_records WHERE id = $record_id")->fetch_assoc();
            if ($res_b) {
                $book_id = $res_b['book_id'];
                $conn->query("UPDATE books SET available = available + 1 WHERE id = $book_id");
            }
            echo json_encode(['success' => true, 'message' => 'کتێبەکە گەرایەوە']);
        } else {
            echo json_encode(['success' => false, 'message' => 'هەڵە: ' . $stmt->error]);
        }
        break;
        
    case 'filter_publications':
        $search = $_GET['search'] ?? '';
        $type = $_GET['type'] ?? '';
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 30;
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $offset = ($page - 1) * $limit;
        
        $where = " WHERE 1=1";
        $params = [];
        $types = "";
        
        if ($search) {
            $where .= " AND (name LIKE ? OR issue_number LIKE ?)";
            $search_param = "%$search%";
            $params[] = $search_param;
            $params[] = $search_param;
            $types .= "ss";
        }

        if ($type) {
            $where .= " AND type LIKE ?";
            $params[] = $type;
            $types .= "s";
        }

        // Total count
        $count_sql = "SELECT COUNT(*) as total FROM publications $where";
        $stmt_count = $conn->prepare($count_sql);
        if (!empty($params)) {
            $stmt_count->bind_param($types, ...$params);
        }
        $stmt_count->execute();
        $total_records = $stmt_count->get_result()->fetch_assoc()['total'];
        
        // Paginated data
        $sql = "SELECT * FROM publications $where ORDER BY CAST(issue_number AS UNSIGNED) ASC, issue_number ASC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $publications = [];
        while ($row = $result->fetch_assoc()) {
            $publications[] = $row;
        }
        
        echo json_encode([
            'publications' => $publications,
            'total' => $total_records,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total_records / $limit)
        ]);
        break;

    case 'get_publication':
        $id = $_GET['id'] ?? 0;
        $sql = "SELECT * FROM publications WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            echo json_encode($row);
        } else {
            echo json_encode(['error' => 'بڵاوکراوە نەدۆزرایەوە']);
        }
        break;

    case 'get_publication_distributions':
        $id = $_GET['publication_id'] ?? 0;
        $search = $_GET['search'] ?? '';
        
        $where = " WHERE 1=1";
        $params = [];
        $types = "";
        
        if ($id > 0) {
            $where .= " AND d.publication_id = ?";
            $params[] = $id;
            $types .= "i";
        }
        
        if ($search) {
            $where .= " AND (d.location LIKE ? OR d.receiver_name LIKE ? OR p.name LIKE ?)";
            $search_param = "%$search%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            $types .= "sss";
        }
        
        $sql = "SELECT d.*, p.name as publication_name, p.issue_number, u.full_name as entry_user 
                FROM publication_distributions d 
                JOIN publications p ON d.publication_id = p.id 
                LEFT JOIN users u ON d.user_id = u.id 
                $where 
                ORDER BY d.distributed_at DESC";
        
        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $distributions = [];
        while ($row = $result->fetch_assoc()) {
            $distributions[] = $row;
        }
        echo json_encode($distributions);
        break;

    case 'get_unique_locations':
        $sql = "SELECT d1.location, d1.receiver_name, d1.receiver_phone, d1.distributed_quantity 
                FROM publication_distributions d1
                INNER JOIN (
                    SELECT location, receiver_name, MAX(distributed_at) as max_at
                    FROM publication_distributions
                    GROUP BY location, receiver_name
                ) d2 ON d1.location = d2.location 
                    AND d1.receiver_name = d2.receiver_name 
                    AND d1.distributed_at = d2.max_at
                ORDER BY d1.distributed_at DESC";
        $result = $conn->query($sql);
        $locations = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $locations[] = $row;
            }
        }
        echo json_encode($locations);
        break;

    case 'update_category_shelf':
        $category_id = intval($_POST['category_id'] ?? 0);
        $shelf_code  = trim($_POST['shelf_code'] ?? '');
        
        if ($category_id <= 0 || empty($shelf_code)) {
            echo json_encode(['success' => false, 'error' => 'زانیارییەکان تەواو نین']);
            break;
        }
        
        $stmt = $conn->prepare("UPDATE books SET shelf_code = ? WHERE category_id = ?");
        if (!$stmt) {
            echo json_encode(['success' => false, 'error' => 'Prepare failed: ' . $conn->error]);
            break;
        }
        $stmt->bind_param("si", $shelf_code, $category_id);
        
        if ($stmt->execute()) {
            $count = $conn->affected_rows;
            echo json_encode(['success' => true, 'message' => "کۆدی ڕەفە بۆ ($count) کتێب بە سەرکەوتوویی نوێکرایەوە"]);
        } else {
            echo json_encode(['success' => false, 'error' => 'هەڵە لە نوێکردنەوەی داتاکان: ' . $stmt->error]);
        }
        $stmt->close();
        break;

    case 'get_user_entry_stats':
        $sql = "SELECT u.full_name, COUNT(b.id) as total_titles, SUM(b.quantity) as total_qty 
                FROM users u
                LEFT JOIN books b ON u.id = b.user_id 
                GROUP BY u.id 
                ORDER BY total_titles DESC";
        $result = $conn->query($sql);
        $stats = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) $stats[] = $row;
        }
        echo json_encode($stats);
        break;

    case 'get_staff_details':
        $name = $_GET['name'] ?? '';
        $sql = "SELECT phone FROM staff_members WHERE full_name = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            echo json_encode($row);
        } else {
            echo json_encode(['phone' => '']);
        }
        break;

    case 'get_next_book_code':
        $category_id = intval($_GET['category_id'] ?? 0);
        if (!$category_id) {
            echo json_encode(['next_code' => '']);
            break;
        }
        $sql = "SELECT MAX(CAST(book_code AS UNSIGNED)) as max_code,
                (SELECT shelf_code FROM books WHERE category_id = ? AND shelf_code IS NOT NULL AND shelf_code != '' GROUP BY shelf_code ORDER BY COUNT(*) DESC LIMIT 1) as suggested_shelf
                FROM books WHERE category_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $category_id, $category_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        $next_code = ($row && $row['max_code'] !== null) ? intval($row['max_code']) + 1 : 1;
        echo json_encode([
            'next_code' => $next_code,
            'suggested_shelf' => $row['suggested_shelf'] ?? ''
        ]);
        break;

    case 'get_all_staff_names':
        $sql = "SELECT DISTINCT full_name FROM staff_members ORDER BY full_name ASC";
        $result = $conn->query($sql);
        $names = [];
        while ($row = $result->fetch_assoc()) $names[] = $row['full_name'];
        echo json_encode($names);
        break;

    case 'get_shelf_categories':
        $sql = "SELECT c.id, c.name, 
                (SELECT shelf_code FROM books b WHERE b.category_id = c.id AND shelf_code IS NOT NULL AND shelf_code != '' GROUP BY shelf_code ORDER BY COUNT(*) DESC LIMIT 1) as current_shelf
                FROM categories c ORDER BY c.name";
        $r = $conn->query($sql);
        $cats = [];
        while ($row = $r->fetch_assoc()) $cats[] = $row;
        echo json_encode($cats);
        break;

    default:
        echo json_encode(['error' => 'Invalid action']);
}

$conn->close();
