<?php

require_once '../includes/auth.php';
require_once '../config/config.php';
require_once '../includes/functions.php';

// Redirect if already logged in
if (isLoggedIn() && isset($_SESSION['role_name'])) {
    // Redirect to the dashboard based on the user's role
    header("Location: ../" . strtolower($_SESSION['role_name']) . "/dashboard.php");
    exit();
}
?>
<head>
<link rel="stylesheet" href="../assets/css/main.css">
<style>
            input:invalid,
        select:invalid,
        textarea:invalid {
            border: 2px solid #BC6C25;
            outline: none;
        }

        /* Optional: give valid fields a greenish border (nice UI feedback) */
        input:valid,
        select:valid,
        textarea:valid {
            border: 2px solid #609966;
        }

        /* Disable button when any input in the form is invalid */
        form:invalid button[type="submit"] {
            pointer-events: none;
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Optional: Make button normal when form is valid */
        form:valid button[type="submit"] {
            pointer-events: auto;
            opacity: 1;
            cursor: pointer;
        }

        .error-message {
            display: none;
            color: #BC6C25;
            font-size: 0.85rem;
            margin-top: 5px;
        }

        /* Show the small tag only if the input is invalid */
        input:invalid + .error-message,
        textarea:invalid + .error-message,
        select:invalid + .error-message {
            display: block;
        }

</style>
</head>

<h1
style="text-align: center; margin-top: 20px; margin-bottom: 20px"
>WELCOME BACK</h1>
<div class="auth-container">
    <h2>Login to RPM System</h2>
    <?php displayAlert(); ?>
    <form action="process_login.php" method="post">
        <div class="form-group">
            <label for="email">Email:</label>
            <input type="text" id="email" name="email" required autocomplete="off">
            <small id="email_error" class="error-message">Please enter a valid email (e.g., user@domain.com).</small>
        </div>
        <div class="form-group">
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>
        </div>
        <button type="submit" class="btn">Login</button>
    </form>
    <div class="auth-links">
        <a href="register.php">Create an account</a> |
        <a href="../index.php">Back to Home</a> 
    </div>
</div>

