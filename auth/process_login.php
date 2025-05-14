<?php
require_once '../includes/db.php';
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($conn, $_POST['email']);
    $password = $_POST['password'];

    // Get user by email
    $stmt = $conn->prepare("SELECT u.*, r.Role_Name FROM User u JOIN Role r ON u.Role_ID = r.Role_ID WHERE u.EMAIL = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        if (verifyPassword($password, $user['Password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['User_ID'];
            $_SESSION['full_name'] = $user['Full_Name'];
            $_SESSION['role_name'] = $user['Role_Name'];
            $_SESSION['email'] = $user['EMAIL'];
            
            // Redirect based on role
            header("Location: ../" . strtolower($user['Role_Name']) . "/dashboard.php");
            exit();
        } else {
            setAlert("Invalid email or password", "error");
            session_unset();  // Clear session data
            session_destroy(); // Destroy session
            header("Location: login.php");
            exit();
        }
        
    } else {
        setAlert("Invalid email or password", "error");
    }
    
    header("Location: login.php");
    exit();
} else {
    header("Location: login.php");
    exit();
}
?>