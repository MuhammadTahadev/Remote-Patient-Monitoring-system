<?php
// includes/header.php


ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Remote Patient Monitoring System</title>
    <link rel="icon" href="../assets/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <header>
        <div class="container">
            <div class="logo">
                <h1><i class="fas fa-heartbeat"></i> RPM System</h1>
            </div>
            <!-- Navigation Menu -->
            <nav>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                    <a href="../auth/logout.php">Logout</a>
                    
                <?php else: ?>
                    <a href="../auth/login.php">Login</a>
                    <a href="../auth/register.php">Register</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    <main class="container">
