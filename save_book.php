<?php
require_once 'config.php';
require_once 'sync_utils.php';
session_start();


header('Content-Type: application/json');

// Disable HTML error display for AJAX requests to prevent corrupted JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

$conn = getDB();
if ($conn === null) {
    echo json_encode(['success' => false, 'message' => 'پەیوەندی بە داتابەیسەوە سەرنەکەوت']);
    exit;
}

// --- SAFE AUTO UPDATE SCHEMA ---
function addColumnIfNotExists($conn, $table, $column, $type) {
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if ($result && $result->num_rows == 0) {
        $conn->query("ALTER TABLE `$table` ADD `$column` $type");
    }
}

addColumnIfNotExists($conn, 'books', 'pdf_file', 'VARCHAR(255)');
addColumnIfNotExists($conn, 'books', 'pdf_link', 'TEXT');
addColumnIfNotExists($conn, 'books', 'cover_image', 'VARCHAR(255)');
addColumnIfNotExists($conn, 'books', 'description', 'TEXT');
addColumnIfNotExists($conn, 'books', 'language', 'VARCHAR(100)');
addColumnIfNotExists($conn, 'books', 'shelf_code', 'VARCHAR(50)');
addColumnIfNotExists($conn, 'books', 'book_code', 'VARCHAR(50)');
addColumnIfNotExists($conn, 'books', 'translator_id', 'INT');
addColumnIfNotExists($conn, 'books', 'author_id', 'INT');

$action = $_POST['action'] ?? '';

if ($action === 'save') {
    try {
        $user_id = $_SESSION['user_id'] ?? null;
        $id = $_POST['id'] ?? null;
        if ($id === '' || $id === 'null' || $id == 0) $id = null;
        else $id = intval($id);

        $title = trim($_POST['title'] ?? '');
        $author_id = $_POST['author_id'] ?? null;
        $translator_id = $_POST['translator_id'] ?? null;
        $category_id = $_POST['category_id'] ?? null;
        $quantity = intval($_POST['quantity'] ?? 1);
        $shelf_code = trim($_POST['shelf_code'] ?? '');
        $book_code = trim($_POST['book_code'] ?? '');
        $publish_year = $_POST['publish_year'] ?? null;
        $language = trim($_POST['language'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $sync_pub = $_POST['sync_pub'] ?? '0';
        $pdf_link = trim($_POST['pdf_link'] ?? '');
        $delete_cover = ($_POST['delete_cover'] ?? '0') === '1';

        $author_name = trim($_POST['author_name'] ?? '');
        $translator_name = trim($_POST['translator_name'] ?? '');
        $category_name = trim($_POST['category_name'] ?? '');

        // Normalize
        $author_id = (empty($author_id)) ? null : intval($author_id);
        $translator_id = (empty($translator_id)) ? null : intval($translator_id);
        $category_id = (empty($category_id)) ? null : intval($category_id);
        $publish_year = (empty($publish_year)) ? null : strval($publish_year);
        $user_id = (empty($user_id)) ? null : intval($user_id);

        // Handle new insertions or existing by name
        if (empty($author_id) && !empty($author_name)) {
            $check = $conn->prepare("SELECT id FROM authors WHERE name = ?");
            $check->bind_param("s", $author_name); $check->execute();
            $res = $check->get_result();
            if ($res->num_rows > 0) {
                $author_id = $res->fetch_assoc()['id'];
            } else {
                $stmt = $conn->prepare("INSERT INTO authors (name) VALUES (?)");
                $stmt->bind_param("s", $author_name);
                $stmt->execute();
                $author_id = $conn->insert_id;
            }
        }

        if (empty($translator_id) && !empty($translator_name)) {
            $check = $conn->prepare("SELECT id FROM translators WHERE name = ?");
            $check->bind_param("s", $translator_name); $check->execute();
            $res = $check->get_result();
            if ($res->num_rows > 0) {
                $translator_id = $res->fetch_assoc()['id'];
            } else {
                $stmt = $conn->prepare("INSERT INTO translators (name) VALUES (?)");
                $stmt->bind_param("s", $translator_name);
                $stmt->execute();
                $translator_id = $conn->insert_id;
            }
        }

        if (empty($category_id) && !empty($category_name)) {
            $check = $conn->prepare("SELECT id FROM categories WHERE name = ?");
            $check->bind_param("s", $category_name); $check->execute();
            $res = $check->get_result();
            if ($res->num_rows > 0) {
                $category_id = $res->fetch_assoc()['id'];
            } else {
                $stmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
                $stmt->bind_param("s", $category_name);
                $stmt->execute();
                $category_id = $conn->insert_id;
            }
        }

        if (empty($title)) throw new Exception('ناوی کتێب پێویستە');
        if (empty($book_code)) throw new Exception('کۆدی کتێب پێویستە');

        // Folders
        $upload_dir = 'uploads/books/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        if (!is_dir($upload_dir . 'covers/')) mkdir($upload_dir . 'covers/', 0777, true);
        if (!is_dir($upload_dir . 'pdfs/')) mkdir($upload_dir . 'pdfs/', 0777, true);

        $cover_path = null;
        $pdf_path = null;

        if ($id) {
            $ex_stmt = $conn->prepare("SELECT cover_image, pdf_file FROM books WHERE id = ?");
            if ($ex_stmt) {
                $ex_stmt->bind_param("i", $id); $ex_stmt->execute();
                $ex = $ex_stmt->get_result()->fetch_assoc();
                if ($ex) { $cover_path = $ex['cover_image']; $pdf_path = $ex['pdf_file']; }
            }
        }

        // Handle cover deletion flag
        if ($delete_cover && $id) {
            if ($cover_path && file_exists($cover_path)) @unlink($cover_path);
            $cover_path = null;
        }

        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION);
            $new_name = 'cover_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            $dest = $upload_dir . 'covers/' . $new_name;
            if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $dest)) {
                if ($id && $cover_path && file_exists($cover_path)) @unlink($cover_path);
                $cover_path = $dest;
            }
        }

        if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['pdf_file']['name'], PATHINFO_EXTENSION);
            $new_name = 'book_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            $dest = $upload_dir . 'pdfs/' . $new_name;
            if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $dest)) {
                if ($id && $pdf_path && file_exists($pdf_path)) @unlink($pdf_path);
                $pdf_path = $dest;
            }
        }

        if ($id) {
            $check = $conn->prepare("SELECT id FROM books WHERE book_code = ? AND category_id = ? AND id != ?");
            $check->bind_param("sii", $book_code, $category_id, $id); $check->execute();
            if ($check->get_result()->num_rows > 0) throw new Exception('کۆدی کتێب لەم بەشەدا پێشتر تۆمارکراوە');

            $sql = "UPDATE books SET title = ?, author_id = ?, translator_id = ?, category_id = ?, quantity = ?, shelf_code = ?, book_code = ?, publish_year = ?, language = ?, user_id = ?, pdf_link = ?, cover_image = ?, pdf_file = ?, description = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            if (!$stmt) throw new Exception("Update Prepare Failed: " . $conn->error);
            
            // 15 parameters: s i i i i s s s s i s s s s i
            $types = "siiiissssissssi";
            $stmt->bind_param($types, $title, $author_id, $translator_id, $category_id, $quantity, $shelf_code, $book_code, $publish_year, $language, $user_id, $pdf_link, $cover_path, $pdf_path, $description, $id);
        } else {
            $check = $conn->prepare("SELECT id FROM books WHERE book_code = ? AND category_id = ?");
            $check->bind_param("si", $book_code, $category_id); $check->execute();
            if ($check->get_result()->num_rows > 0) throw new Exception('کۆدی کتێب لەم بەشەدا پێشتر تۆمارکراوە');

            $sql = "INSERT INTO books (title, author_id, translator_id, category_id, quantity, shelf_code, book_code, publish_year, language, user_id, available, pdf_link, cover_image, pdf_file, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if (!$stmt) throw new Exception("Insert Prepare Failed: " . $conn->error);
            
            // 15 parameters: s i i i i s s s s i i s s s s
            $types = "siiiissssiissss";
            $stmt->bind_param($types, $title, $author_id, $translator_id, $category_id, $quantity, $shelf_code, $book_code, $publish_year, $language, $user_id, $quantity, $pdf_link, $cover_path, $pdf_path, $description);
        }

        if (!$stmt->execute()) throw new Exception('هەڵە لە پاشەکەوتکردن: ' . $stmt->error);
        
        // --- SYNC TO PUBLIC SITE ---
        $saved_id = $id ? $id : $conn->insert_id;
        syncBookToPublic($saved_id, 'save');

        echo json_encode(['success' => true, 'message' => $id ? 'کتێبەکە نوێکرایەوە' : 'کتێبەکە زیادکرا']);


    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} elseif ($action === 'delete') {
    // ... same as before
    $id = intval($_POST['id'] ?? 0);
    if ($id) {
        $ex_stmt = $conn->prepare("SELECT cover_image, pdf_file FROM books WHERE id = ?");
        $ex_stmt->bind_param("i", $id); $ex_stmt->execute();
        $res = $ex_stmt->get_result()->fetch_assoc();
        if ($res) {
            if ($res['cover_image'] && file_exists($res['cover_image'])) @unlink($res['cover_image']);
            if ($res['pdf_file'] && file_exists($res['pdf_file'])) @unlink($res['pdf_file']);
        }
        $stmt = $conn->prepare("DELETE FROM books WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            syncBookToPublic(['id' => $id], 'delete');
            echo json_encode(['success' => true, 'message' => 'کتێبەکە سڕایەوە']);
        }

        else echo json_encode(['success' => false, 'message' => 'هەڵە لە سڕینەوە']);
    }
}
$conn->close();
?>
