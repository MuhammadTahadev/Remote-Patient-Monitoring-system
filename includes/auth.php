<?php
// includes/auth.php
require_once '../config/config.php';


// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role_name']);
}

// Check if user has specific role
function hasRole($role_name) {
    if (!isLoggedIn()) return false;
    return $_SESSION['role_name'] == $role_name;
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: ../auth/login.php");
        exit();
    }
}

// Redirect if doesn't have role
function requireRole($role_name) {
    requireLogin();
    if (!hasRole($role_name)) {
        header("Location: ../auth/unauthorized.php");
        exit();
    }
}

// Password hashing
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

// Verify password
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}
?>