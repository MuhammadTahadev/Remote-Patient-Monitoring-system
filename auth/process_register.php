<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitize($conn, $_POST['full_name']);
    $email = sanitize($conn, $_POST['email']);
    $dob = sanitize($conn, $_POST['dob']);
    $role_id = (int)$_POST['role'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Role-specific fields
    $emergency_contact_number = isset($_POST['emergency_contact_number']) ? sanitize($conn, $_POST['emergency_contact_number']) : null;
    $address = isset($_POST['address']) ? sanitize($conn, $_POST['address']) : null;
    $primary_doctor_id = !empty($_POST['primary_doctor_id']) ? (int)$_POST['primary_doctor_id'] : null;
    $patient_organization_id = isset($_POST['patient_organization_id']) ? (int)$_POST['patient_organization_id'] : null;
    $organization_id = isset($_POST['organization_id']) ? (int)sanitize($conn, $_POST['organization_id']) : null;
    $hospital_organization = isset($_POST['hospital_organization']) ? sanitize($conn, $_POST['hospital_organization']) : null;
    $specialization = isset($_POST['specialization']) ? sanitize($conn, $_POST['specialization']) : null;
    $license_number = isset($_POST['license_number']) ? sanitize($conn, $_POST['license_number']) : null;

    // Validate inputs
    if (empty($full_name) || empty($email) || empty($dob) || empty($role_id) || empty($password)) {
        setAlert("All fields are required", "error");
        header("Location: register.php");
        exit();
    }

    if ($password !== $confirm_password) {
        setAlert("Passwords do not match", "error");
        header("Location: register.php");
        exit();
    }

    // Check if email already exists
    if (emailExists($email)) {
        setAlert("This email is already registered. Please use a different email.", "error");
        header("Location: register.php");
        exit();
    }

    // Role-specific validation
    $role_name = getRoleName($role_id);
    switch (strtolower($role_name)) {
        case 'patient':
            if (empty($emergency_contact_number) || empty($address) || empty($primary_doctor_id) || empty($patient_organization_id)) {
                setAlert("Emergency contact number, address, primary doctor, and organization are required for patients", "error");
                header("Location: register.php");
                exit();
            }
            break;
        case 'doctor':
            if (empty($specialization) || empty($license_number) || empty($organization_id)) {
                setAlert("Specialization, license number, and hospital/organization are required for doctors", "error");
                header("Location: register.php");
                exit();
            }
            break;
        case 'admin':
            if (empty($hospital_organization)) {
                setAlert("Hospital/organization is required for admins", "error");
                header("Location: register.php");
                exit();
            }
            break;
        default:
            setAlert("Invalid role selected", "error");
            header("Location: register.php");
            exit();
    }

    // Hash password
    $hashed_password = hashPassword($password);

    // Start transaction
    $conn->begin_transaction();

    try {
        // Handle organization for admin
        if (strtolower($role_name) === 'admin') {
            // Check if organization already exists
            $stmt = $conn->prepare("SELECT organization_id FROM Organization WHERE hospital_organization = ?");
            $stmt->bind_param("s", $hospital_organization);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $organization_id = $result->fetch_assoc()['organization_id'];
            } else {
                // Insert new organization
                $stmt = $conn->prepare("INSERT INTO Organization (hospital_organization) VALUES (?)");
                $stmt->bind_param("s", $hospital_organization);
                $stmt->execute();
                $organization_id = $conn->insert_id;
            }
        }

        // Insert into User table
        $stmt = $conn->prepare("INSERT INTO User (Full_Name, EMAIL, Date_of_Birth, Password, Role_ID) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", $full_name, $email, $dob, $hashed_password, $role_id);
        $stmt->execute();
        $user_id = $conn->insert_id;

        // Insert into role-specific table
        switch (strtolower($role_name)) {
            case 'patient':
                $stmt = $conn->prepare("INSERT INTO Patient (User_ID, Emergency_Contact_Number, Address, Primary_Doctor_ID, Organization_ID) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("isssi", $user_id, $emergency_contact_number, $address, $primary_doctor_id, $patient_organization_id);
                $stmt->execute();
                $patient_id = $conn->insert_id;

                if ($primary_doctor_id !== null) {
                    $stmt = $conn->prepare("SELECT Doctor_ID FROM Doctor WHERE Doctor_ID = ? AND Organization_ID = ?");
                    $stmt->bind_param("ii", $primary_doctor_id, $patient_organization_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->num_rows === 0) {
                        throw new Exception("Invalid primary doctor selected or doctor not in selected organization");
                    }

                    $assignment_date = date('Y-m-d');
                    $is_primary = true;
                    $stmt = $conn->prepare("INSERT INTO DoctorPatientMapping (Doctor_ID, Patient_ID, Assignment_Date, Is_Primary) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("iisi", $primary_doctor_id, $patient_id, $assignment_date, $is_primary);
                    $stmt->execute();
                }
                break;
            case 'doctor':
                $stmt = $conn->prepare("INSERT INTO Doctor (User_ID, Specialization, License_Number, Organization_ID) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("issi", $user_id, $specialization, $license_number, $organization_id);
                $stmt->execute();
                break;
            case 'admin':
                $stmt = $conn->prepare("INSERT INTO Admin (User_ID, Organization_ID) VALUES (?, ?)");
                $stmt->bind_param("ii", $user_id, $organization_id);
                $stmt->execute();
                break;
            default:
                throw new Exception("Invalid role");
        }

        $conn->commit();
        setAlert('Registration successful! Please login.', 'success');
        header('Location: login.php');
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        setAlert('Registration failed: ' . $e->getMessage(), 'error');
        header("Location: register.php");
        exit();
    }
} else {
    header("Location: register.php");
    exit();
}
?>