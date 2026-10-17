<?php
session_start();
if (!isset($_SESSION['user_id'])) die("Access Denied");

require_once 'config.php';
$conn = getDB();

// Delete all members, documents, and families to start fresh
$conn->query("SET FOREIGN_KEY_CHECKS = 0");
$conn->query("TRUNCATE TABLE member_documents");
$conn->query("TRUNCATE TABLE members");
$conn->query("TRUNCATE TABLE families");
$conn->query("SET FOREIGN_KEY_CHECKS = 1");

echo "هەموو ئەندامەکان و نەسەبەکان بە سەرکەوتوویی سڕانەوە. ئێستا دەتوانیت هاوردەکردن ئەنجام بدەیت.";
?>
