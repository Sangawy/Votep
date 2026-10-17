<?php
session_start();
if (!isset($_SESSION['public_admin'])) {
    header("Location: login.php");
    exit;
}
require_once '../db_config.php';
$conn = getPublicDB();

// ----------------------------------------------------
// HANDLE DELETE ACTION
// ----------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM public_books WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }
    header("Location: books.php");
    exit;
}

// ----------------------------------------------------
// HANDLE ADD OR EDIT ACTIONS
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id              = (int)($_POST['id'] ?? 0);
    $title           = $_POST['title'] ?? '';
    $author_name     = $_POST['author_name'] ?? '';
    $translator_name = $_POST['translator_name'] ?? '';
    $category_name   = $_POST['category_name'] ?? '';
    $publish_year    = $_POST['publish_year'] ?? '';
    $shelf_code      = $_POST['shelf_code'] ?? '';
    $book_code       = $_POST['book_code'] ?? '';
    $quantity        = (int)($_POST['quantity'] ?? 1);
    $available       = (int)($_POST['available'] ?? 1);
    $cover_image     = $_POST['cover_image'] ?? '';
    $description     = $_POST['description'] ?? '';
    $creator_name    = $_POST['creator_name'] ?? '';

    if (empty($title)) {
        die("Title is required!");
    }

    // ----------------------------------------------------
    // PROCESS UPLOADED COVER FILE (Camera or Gallery)
    // ----------------------------------------------------
    if (isset($_FILES['cover_file']) && $_FILES['cover_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['cover_file']['tmp_name'];
        $fileName = $_FILES['cover_file']['name'];
        $fileSize = $_FILES['cover_file']['size'];
        $fileType = $_FILES['cover_file']['type'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($fileExtension, $allowedExtensions)) {
            $uploadFileDir = '../uploads/covers/';
            if (!file_exists($uploadFileDir)) {
                mkdir($uploadFileDir, 0777, true);
            }
            // Create unique safe filename
            $newFileName = 'cover_' . time() . '_' . rand(1000, 9999) . '.' . $fileExtension;
            $dest_path = $uploadFileDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                // Save the relative path starting from public root (i.e. 'uploads/covers/...')
                $cover_image = 'uploads/covers/' . $newFileName;
            }
        }
    }

    if ($id > 0) {
        // UPDATE EXISTING BOOK
        // If no new cover image was provided, retain the old cover image path
        if (empty($cover_image)) {
            $old_q = $conn->prepare("SELECT cover_image FROM public_books WHERE id = ?");
            $old_q->bind_param("i", $id);
            $old_q->execute();
            $old_res = $old_q->get_result()->fetch_assoc();
            if ($old_res) {
                $cover_image = $old_res['cover_image'];
            }
        }

        $sql = "UPDATE public_books SET 
                title = ?, 
                author_name = ?, 
                translator_name = ?, 
                category_name = ?, 
                publish_year = ?, 
                shelf_code = ?, 
                book_code = ?, 
                quantity = ?, 
                available = ?, 
                cover_image = ?, 
                description = ?, 
                creator_name = ? 
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssssiisssi", 
            $title, 
            $author_name, 
            $translator_name, 
            $category_name, 
            $publish_year, 
            $shelf_code, 
            $book_code, 
            $quantity, 
            $available, 
            $cover_image, 
            $description, 
            $creator_name, 
            $id
        );
        $stmt->execute();
    } else {
        // CREATE NEW BOOK WITH UNIQUE LOCAL ID (SAFE FROM OVERWRITING BY SYNCS)
        $local_id = 900000000 + (time() % 100000000) + rand(1, 999);
        
        $sql = "INSERT INTO public_books 
                (local_id, title, author_name, translator_name, category_name, publish_year, shelf_code, book_code, quantity, available, cover_image, description, creator_name) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isssssssiisss", 
            $local_id,
            $title, 
            $author_name, 
            $translator_name, 
            $category_name, 
            $publish_year, 
            $shelf_code, 
            $book_code, 
            $quantity, 
            $available, 
            $cover_image, 
            $description, 
            $creator_name
        );
        $stmt->execute();
    }
    
    header("Location: books.php");
    exit;
}

header("Location: books.php");
exit;
