<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = normalizeNumerals(trim($_POST['username'] ?? ''));
    $password = normalizeNumerals($_POST['password'] ?? '');
    
    if ($username === 'sangawy' && $password === 'Sabate 12@12') {
        $_SESSION['user_id'] = 1;
        $_SESSION['username'] = 'sangawy';
        $_SESSION['role'] = 'owner';
        header('Location: dashboard.php');
        exit;
    }
    elseif ($username === 'admin' && $password === 'admin123') {
        $_SESSION['user_id'] = 2;
        $_SESSION['username'] = 'admin';
        $_SESSION['role'] = 'admin';
        header('Location: dashboard.php');
        exit;
    }
    else {
        header('Location: index.php?error=1');
        exit;
    }
}

header('Location: index.php');
exit;
