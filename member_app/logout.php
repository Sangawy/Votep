<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
unset($_SESSION['member_portal_id']);
header('Location: login.php');
exit;
