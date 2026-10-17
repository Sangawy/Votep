<?php
require_once 'config.php';
session_start();

header('Content-Type: application/json');

$conn = getDB();

if ($conn === null) {
    echo json_encode(['success' => false, 'message' => 'پەیوەندی بە داتابەیسەوە سەرنەکەوت']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'save') {
    $id = $_POST['id'] ?? null;
    $type = trim($_POST['type'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $issue_number = trim($_POST['issue_number'] ?? '');
    $publish_year = intval($_POST['publish_year'] ?? date('Y'));
    $total_quantity = intval($_POST['total_quantity'] ?? 0);
    
    if (empty($name) || empty($issue_number) || empty($type)) {
        echo json_encode(['success' => false, 'message' => 'ناو، ژمارە، و جۆر پێویستن']);
        exit;
    }

    if ($id) {
        // Update
        $sql = "UPDATE publications SET name = ?, issue_number = ?, publish_year = ?, total_quantity = ?, type = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssiisi", $name, $issue_number, $publish_year, $total_quantity, $type, $id);
        
        // Recalculate remaining_quantity: total_quantity - sum(distributed_quantity)
        $dist_sql = "SELECT SUM(distributed_quantity) as total_dist FROM publication_distributions WHERE publication_id = ?";
        $dist_stmt = $conn->prepare($dist_sql);
        $dist_stmt->bind_param("i", $id);
        $dist_stmt->execute();
        $total_dist = $dist_stmt->get_result()->fetch_assoc()['total_dist'] ?? 0;
        $remaining = $total_quantity - $total_dist;
        
        $update_rem = $conn->prepare("UPDATE publications SET remaining_quantity = ? WHERE id = ?");
        $update_rem->bind_param("ii", $remaining, $id);
        $update_rem->execute();
        
    } else {
        // Insert
        $sql = "INSERT INTO publications (name, issue_number, publish_year, total_quantity, remaining_quantity, type) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssiiis", $name, $issue_number, $publish_year, $total_quantity, $total_quantity, $type);
    }

    if ($stmt->execute()) {
        // --- SYNC LOGIC: Sync with Books table ---
        $pub_id_for_sync = $id ? $id : $conn->insert_id;
        $book_title = $name;
        $book_code = $issue_number;
        $book_year = strval($publish_year);
        $book_qty = $total_quantity;
        $cat_id = 471; // بڵاوکراوەکانی وەزارتی پێشمەرگە
        $auth_id = 1648; // وەزارەتی پێشمەرگە
        $user_id = $_SESSION['user_id'] ?? null;

        // Check if book exists with this code
        $check_bk = $conn->prepare("SELECT id FROM books WHERE book_code = ?");
        $check_bk->bind_param("s", $book_code);
        $check_bk->execute();
        $bk_res = $check_bk->get_result();

        if ($bk_res->num_rows > 0) {
            $bid = $bk_res->fetch_assoc()['id'];
            $upd_bk = $conn->prepare("UPDATE books SET title = ?, author_id = ?, category_id = ?, quantity = ?, available = (available + (? - quantity)), publish_year = ?, user_id = ? WHERE id = ?");
            $upd_bk->bind_param("siiiisii", $book_title, $auth_id, $cat_id, $book_qty, $book_qty, $book_year, $user_id, $bid);
            $upd_bk->execute();
        } else {
            $ins_bk = $conn->prepare("INSERT INTO books (title, author_id, category_id, quantity, available, book_code, publish_year, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $ins_bk->bind_param("siiiisss", $book_title, $auth_id, $cat_id, $book_qty, $book_qty, $book_code, $book_year, $user_id);
            $ins_bk->execute();
        }
        // ------------------------------------------

        echo json_encode(['success' => true, 'message' => $id ? 'نوێکرایەوە' : 'زیادکرا']);
    } else {
        echo json_encode(['success' => false, 'message' => 'هەڵە: ' . $stmt->error]);
    }

} elseif ($action === 'delete') {
    $id = $_POST['id'] ?? 0;
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'نەدۆزرایەوە']);
        exit;
    }
    $sql = "DELETE FROM publications WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'سڕایەوە']);
    } else {
        echo json_encode(['success' => false, 'message' => 'هەڵە لە سڕینەوە']);
    }

} elseif ($action === 'distribute') {
    $publication_id = intval($_POST['publication_id'] ?? 0);
    $location = trim($_POST['location'] ?? '');
    $quantity = intval($_POST['quantity'] ?? 0);
    $receiver_name = trim($_POST['receiver_name'] ?? '');
    $receiver_phone = trim($_POST['receiver_phone'] ?? '');

    if (!$publication_id || empty($location) || $quantity <= 0 || empty($receiver_name)) {
        echo json_encode(['success' => false, 'message' => 'زانیارییەکان تەواو نین']);
        exit;
    }

    // Check remaining quantity
    $check_q = $conn->prepare("SELECT remaining_quantity FROM publications WHERE id = ?");
    $check_q->bind_param("i", $publication_id);
    $check_q->execute();
    $rem = $check_q->get_result()->fetch_assoc()['remaining_quantity'] ?? 0;

    if ($quantity > $rem) {
        echo json_encode(['success' => false, 'message' => 'بڕی پێویست بەردەست نییە. تەنها ' . $rem . ' دانە ماوە.']);
        exit;
    }

    // Insert distribution
    $user_id = $_SESSION['user_id'] ?? null;
    $sql = "INSERT INTO publication_distributions (publication_id, location, distributed_quantity, receiver_name, receiver_phone, user_id) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isissi", $publication_id, $location, $quantity, $receiver_name, $receiver_phone, $user_id);

    if ($stmt->execute()) {
        // Update remaining quantity
        $conn->query("UPDATE publications SET remaining_quantity = remaining_quantity - $quantity WHERE id = $publication_id");
        echo json_encode(['success' => true, 'message' => 'دابەشکرا']);
    } else {
        echo json_encode(['success' => false, 'message' => 'هەڵە: ' . $stmt->error]);
    }
} elseif ($action === 'update_distribution') {
    $id = intval($_POST['id'] ?? 0);
    $location = trim($_POST['location'] ?? '');
    $quantity = intval($_POST['quantity'] ?? 0);
    $receiver_name = trim($_POST['receiver_name'] ?? '');
    $receiver_phone = trim($_POST['receiver_phone'] ?? '');

    if (!$id || empty($location) || $quantity <= 0 || empty($receiver_name)) {
        echo json_encode(['success' => false, 'message' => 'زانیارییەکان تەواو نین']);
        exit;
    }

    // Get old distribution details
    $get_old = $conn->prepare("SELECT publication_id, distributed_quantity FROM publication_distributions WHERE id = ?");
    $get_old->bind_param("i", $id);
    $get_old->execute();
    $old = $get_old->get_result()->fetch_assoc();

    if (!$old) {
        echo json_encode(['success' => false, 'message' => 'دابەشکردنەکە نەدۆزرایەوە']);
        exit;
    }

    $pub_id = $old['publication_id'];
    $old_qty = $old['distributed_quantity'];

    // Check if enough remaining quantity (accounting for the old quantity being returned)
    $check_q = $conn->prepare("SELECT remaining_quantity FROM publications WHERE id = ?");
    $check_q->bind_param("i", $pub_id);
    $check_q->execute();
    $rem = $check_q->get_result()->fetch_assoc()['remaining_quantity'] ?? 0;

    if ($quantity > ($rem + $old_qty)) {
        echo json_encode(['success' => false, 'message' => 'بڕی پێویست بەردەست نییە']);
        exit;
    }

    $sql = "UPDATE publication_distributions SET location = ?, distributed_quantity = ?, receiver_name = ?, receiver_phone = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sissi", $location, $quantity, $receiver_name, $receiver_phone, $id);

    if ($stmt->execute()) {
        $diff = $old_qty - $quantity;
        $conn->query("UPDATE publications SET remaining_quantity = remaining_quantity + $diff WHERE id = $pub_id");
        echo json_encode(['success' => true, 'message' => 'نوێکرایەوە']);
    } else {
        echo json_encode(['success' => false, 'message' => 'هەڵە: ' . $stmt->error]);
    }

} elseif ($action === 'delete_distribution') {
    $id = intval($_POST['id'] ?? 0);
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'نەدۆزرایەوە']);
        exit;
    }

    $get_old = $conn->prepare("SELECT publication_id, distributed_quantity FROM publication_distributions WHERE id = ?");
    $get_old->bind_param("i", $id);
    $get_old->execute();
    $old = $get_old->get_result()->fetch_assoc();

    if ($old) {
        $pub_id = $old['publication_id'];
        $qty = $old['distributed_quantity'];
        
        $sql = "DELETE FROM publication_distributions WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $conn->query("UPDATE publications SET remaining_quantity = remaining_quantity + $qty WHERE id = $pub_id");
            echo json_encode(['success' => true, 'message' => 'سڕایەوە']);
        } else {
            echo json_encode(['success' => false, 'message' => 'هەڵە لە سڕینەوە']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'داتا نەدۆزرایەوە']);
    }
}

$conn->close();
?>
