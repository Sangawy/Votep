<?php
session_start();
session_destroy();
header("Location: observer_login.php");
exit();
?>