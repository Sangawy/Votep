<?php
require_once 'config.php';
$conn = getDB();

// 1. Create the table
$sql = "CREATE TABLE IF NOT EXISTS staff_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT,
    doc_title VARCHAR(255),
    file_path VARCHAR(255),
    file_type VARCHAR(50),
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff_members(id) ON DELETE CASCADE
)";
$conn->query($sql);

// 2. Migration: Scan existing archive folders and populate the table
// This ensures old files are searchable
$res = $conn->query("SELECT id, archive_folder FROM staff_members WHERE archive_folder != ''");
while ($staff = $res->fetch_assoc()) {
    $folder = $staff['archive_folder'];
    if (is_dir($folder)) {
        $files = glob($folder . DIRECTORY_SEPARATOR . "*");
        foreach ($files as $file) {
            if (is_file($file)) {
                $filename = basename($file);
                // Skip profile images if they are the primary one
                if (strpos($filename, 'profile_') === 0) continue;

                $parts = explode('_', $filename);
                $title = $parts[0];
                if (count($parts) < 2) $title = "بەڵگەنامە";

                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                
                // Check if already exists in DB to avoid duplicates
                $check = $conn->prepare("SELECT id FROM staff_documents WHERE file_path = ?");
                $check->bind_param("s", $file);
                $check->execute();
                if ($check->get_result()->num_rows == 0) {
                    $ins = $conn->prepare("INSERT INTO staff_documents (staff_id, doc_title, file_path, file_type) VALUES (?, ?, ?, ?)");
                    $ins->bind_param("isss", $staff['id'], $title, $file, $ext);
                    $ins->execute();
                }
            }
        }
    }
}

echo "Database updated and existing files migrated to 'staff_documents' table.";
?>
