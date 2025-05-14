<?php
require_once '../includes/auth.php';
require_once '../config/config.php';
// require_once '../includes/header.php';

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: login.php");
exit();
?>