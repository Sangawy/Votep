<?php
// config.php
$host = '3.78.47.142';
$port = '3306';
$dbname = 'lsshaho_ls_db';
$username = 'lsshaho_Sangawy';
$password = 'Sabate  12@12';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("هەڵە لە پەیوەندی داتابەیس: " . $e->getMessage());
}
?>