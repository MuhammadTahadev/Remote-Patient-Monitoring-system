<?php
// config.php

// Start the session
if (session_status() == PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 86400,
        'read_and_close' => false,
    ]);
}

// Your other configuration settings here
?>
