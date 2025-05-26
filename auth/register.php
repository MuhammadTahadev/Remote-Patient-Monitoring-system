<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../config/config.php';
require_once '../includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header("Location: ../" . strtolower($_SESSION['role_name']) . "/dashboard.php");
    exit();
}

// Load all doctors with their organization info and specialization
$doctors = $conn->query("
    SELECT d.Doctor_ID, u.Full_Name, d.organization_id, d.Specialization 
    FROM Doctor d 
    JOIN User u ON d.User_ID = u.User_ID
");
$all_doctors = [];
while ($doctor = $doctors->fetch_assoc()) {
    $all_doctors[] = $doctor;
}

// Load all organizations for name lookup
$organizations = $conn->query("SELECT organization_id, hospital_organization FROM Organization");
$all_organizations = [];
while ($org = $organizations->fetch_assoc()) {
    $all_organizations[$org['organization_id']] = $org['hospital_organization'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-i18n="title">Register - RPM System</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <style>
        /* Existing styles remain unchanged */
        #load_doctors.btn {
            background-color: var(--primary);
            color: var(--white);
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 14px;
        }

        #load_doctors.btn:hover {
            background-color: var(--secondary);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        #load_doctors.btn:active {
            background-color: var(--primary-dark);
            transform: scale(0.95);
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        input:invalid,
        select:invalid,
        textarea:invalid {
            border: 2px solid #BC6C25;
            outline: none;
        }

        input:valid,
        select:valid,
        textarea:valid {
            border: 2px solid #609966;
        }

        form:invalid button[type="submit"] {
            pointer-events: none;
            opacity: 0.5;
            cursor: not-allowed;
        }

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

        input:invalid + .error-message,
        textarea:invalid + .error-message,
        select:invalid + .error-message {
            display: block;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: var(--light);
            padding: 25px;
            border-radius: 8px;
            max-width: 600px;
            width: 90%;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
            text-align: left;
            border: 1px solid var(--primary-dark);
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-content h3 {
            color: var(--primary-dark);
            margin-top: 0;
            text-align: center;
            border-bottom: 1px solid var(--primary);
            padding-bottom: 10px;
        }

        .modal-content .confirmation-details {
            margin-bottom: 20px;
        }

        .modal-content .confirmation-row {
            display: flex;
            margin-bottom: 10px;
        }

        .modal-content .confirmation-label {
            font-weight: bold;
            width: 40%;
            color: var(--primary-dark);
        }

        .modal-content .confirmation-value {
            width: 60%;
            word-break: break-word;
        }

        .modal-content .btn-container {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 20px;
        }

        .modal-content .btn {
            background-color: var(--primary);
            color: var(--white);
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s ease;
            min-width: 100px;
        }

        .modal-content .btn-cancel {
            background-color: var(--secondary);
        }

        .modal-content .btn:hover {
            background-color: var(--secondary);
        }

        .modal-content .btn-cancel:hover {
            background-color: var(--primary);
        }

        .modal-content .btn:active {
            background-color: var(--primary-dark);
        }

        .auth-wrapper {
            display: flex;
            max-width: 900px;
            margin: 50px auto;
            gap: 30px;
            align-items: flex-start;
        }

        .faq-section {
            flex: 1;
            max-width: 400px;
            background-color: var(--white);
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .faq-section.hidden {
            display: none;
        }

        .faq-section h2 {
            color: var(--primary-dark);
            font-size: 1.5rem;
            margin-bottom: 20px;
            text-align: center;
        }

        .faq-item {
            margin-bottom: 10px;
        }

        .faq-question {
            width: 100%;
            background-color: var(--primary);
            color: var(--white);
            padding: 12px 40px 12px 15px;
            border: none;
            border-radius: 4px;
            text-align: left;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s ease;
            position: relative;
        }

        .faq-question::after {
            content: '+';
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1.2rem;
            color: var(--white);
        }

        .faq-question.active::after {
            content: '−';
        }

        .faq-question:hover {
            background-color: var(--secondary);
        }

        .faq-question:active {
            background-color: var(--primary-dark);
        }

        .faq-answer {
            display: none;
            padding: 15px;
            background-color: var(--light);
            border-radius: 4px;
            margin-top: 5px;
            font-size: 0.9rem;
            color: var(--dark-gray);
        }

        .faq-answer.active {
            display: block;
        }

        .faq-answer p {
            margin: 0;
        }

        .floating-help {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            background-color: var(--primary);
            color: white;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 24px;
            cursor: pointer;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            z-index: 999;
            transition: all 0.3s ease;
        }

        .floating-help:hover {
            background-color: var(--secondary);
            transform: scale(1.1);
        }

        .floating-help:active {
            background-color: var(--primary-dark);
            transform: scale(0.95);
        }

        /* Language Dropdown Styling */
        .language-selector {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }

        .language-selector select {
            padding: 8px 12px;
            border-radius: 4px;
            border: 1px solid var(--primary);
            background-color: var(--white);
            color: var(--primary-dark);
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .language-selector select:hover {
            background-color: var(--light);
        }

        .language-selector select:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.2);
        }

        @media (max-width: 768px) {
            .auth-wrapper {
                flex-direction: column;
                max-width: 90%;
                gap: 20px;
            }

            .faq-section {
                max-width: 100%;
            }

            .floating-help {
                bottom: 20px;
                right: 20px;
                width: 40px;
                height: 40px;
                font-size: 20px;
            }

            .modal-content .confirmation-row {
                flex-direction: column;
            }

            .modal-content .confirmation-label,
            .modal-content .confirmation-value {
                width: 100%;
            }

            .language-selector {
                top: 15px;
                right: 15px;
            }

            .language-selector select {
                font-size: 12px;
                padding: 6px 10px;
            }
        }
    </style>
</head>
<body>
<!-- Language Selector -->
<div class="language-selector">
    <select id="language_select" onchange="changeLanguage()">
        <option value="en">English</option>
        <option value="ur">اردو</option>
    </select>
</div>

<div class="auth-wrapper">
    <div class="auth-container">
        <h2 data-i18n="register_title">Register for RPM System</h2>
        <?php displayAlert(); ?>
        <form id="register_form" action="process_register.php" method="post">
            <div class="form-group">
                <label for="full_name" data-i18n="full_name_label">Full Name:</label>
                <input type="text" id="full_name" name="full_name" pattern="[a-zA-Z\s]+" required aria-describedby="full_name_error">
                <small id="full_name_error" class="error-message" data-i18n="full_name_error">Name must contain only letters and spaces.</small>
            </div>
            <div class="form-group">
                <label for="email" data-i18n="email_label">Email:</label>
                <input type="email" id="email" name="email" required pattern="[a-zA-Z0-9._%+-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.[a-zA-Z]{2,6}$" maxlength="254" autocomplete="off" aria-describedby="email_error">
                <small id="email_error" class="error-message" data-i18n="email_error">Please enter a valid email (e.g., user@domain.com).</small>
            </div>
            <div class="form-group">
                <label for="dob" data-i18n="dob_label">Date of Birth:</label>
                <input type="date" id="dob" name="dob" required max="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="form-group">
                <label for="role" data-i18n="role_label">I am a:</label>
                <select id="role" name="role" required>
                    <option value="" data-i18n="select_role">Select role</option>
                    <?php
                    $roles = $conn->query("SELECT * FROM Role WHERE Role_Name IN ('Patient', 'Doctor', 'Admin')");
                    while ($role = $roles->fetch_assoc()) {
                        echo "<option value='{$role['Role_ID']}' data-i18n='role_{$role['Role_Name']}'>{$role['Role_Name']}</option>";
                    }
                    ?>
                </select>
            </div>

            <!-- Patient-specific fields (hidden by default) -->
            <div id="patient_fields" class="role-specific" style="display: none;">
                <div class="form-group">
                    <label for="patient_organization_id" data-i18n="patient_organization_label">Organization:</label>
                    <select id="patient_organization_id" name="patient_organization_id">
                        <option value="" data-i18n="select_organization">Select an organization</option>
                        <?php
                        $organizations->data_seek(0); // Reset pointer
                        while ($org = $organizations->fetch_assoc()) {
                            echo "<option value='{$org['organization_id']}'>{$org['hospital_organization']}</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="emergency_contact_number" data-i18n="emergency_contact_label">Emergency Contact Number:</label>
                    <input type="tel" id="emergency_contact_number" name="emergency_contact_number" pattern="[0-9]{11}" title="Enter a 11-digit phone number">
                    <small class="error-message" data-i18n="emergency_contact_error">Enter a 11-digit phone number.</small>
                </div>
                <div class="form-group">
                    <label for="address" data-i18n="address_label">Address:</label>
                    <textarea id="address" name="address"></textarea>
                </div>
                <div id="doctor_select_container" class="form-group" style="display: none;">
                    <button type="button" id="load_doctors" class="btn" data-i18n="load_doctors">Load Doctors</button>
                    <label for="primary_doctor_id" data-i18n="primary_doctor_label">Primary Doctor:</label>
                    <select id="primary_doctor_id" name="primary_doctor_id">
                        <option value="" data-i18n="select_doctor">Select a doctor</option>
                    </select>
                    <small style="color: #666;" data-i18n="doctor_select_note">If you change your organization, click "Load Doctors" again to view the doctors for the new organization.</small>
                </div>
            </div>

            <!-- Doctor-specific fields (hidden by default) -->
            <div id="doctor_fields" class="role-specific" style="display: none;">
                <div class="form-group">
                    <label for="specialization" data-i18n="specialization_label">Specialization:</label>
                    <input type="text" id="specialization" name="specialization" autocomplete="off">
                </div>
                <div class="form-group">
                    <label for="organization_id" data-i18n="doctor_organization_label">Hospital/Organization:</label>
                    <select id="organization_id" name="organization_id">
                        <option value="" data-i18n="select_organization">Select an organization</option>
                        <?php
                        $organizations->data_seek(0); // Reset pointer
                        while ($org = $organizations->fetch_assoc()) {
                            echo "<option value='{$org['organization_id']}'>{$org['hospital_organization']}</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="license_number" data-i18n="license_number_label">License Number:</label>
                    <input type="text" id="license_number" name="license_number">
                </div>
            </div>

            <!-- Admin-specific fields (hidden by default) -->
            <div id="admin_fields" class="role-specific" style="display: none;">
                <div class="form-group">
                    <label for="hospital_organization" data-i18n="hospital_organization_label">Hospital/Organization:</label>
                    <input type="text" id="hospital_organization" name="hospital_organization">
                </div>
            </div>

            <div class="form-group">
                <label for="password" data-i18n="password_label">Password:</label>
                <input type="password" id="password" name="password" required minlength="8" aria-describedby="password_error">
                <small style="color: #666;" data-i18n="password_note">Please remember your password or write it in a safe place - password recovery is not available.</small>
                <small id="password_error" class="error-message" data-i18n="password_error">Password must be at least 8 characters long.</small>
            </div>
            <div class="form-group">
                <label for="confirm_password" data-i18n="confirm_password_label">Confirm Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="8" aria-describedby="confirm_password_error">
                <small id="confirm_password_error" class="error-message" data-i18n="confirm_password_error">Passwords must match.</small>
            </div>
            <button type="button" id="register_button" class="btn" data-i18n="register_button">Register</button>
        </form>

        <!-- Error Modal -->
        <div id="error_modal" class="modal">
            <div class="modal-content">
                <p id="error_message"></p>
                <button type="button" id="close_modal" class="btn" data-i18n="ok_button">OK</button>
            </div>
        </div>

        <!-- Confirmation Modal -->
        <div id="confirm_modal" class="modal">
            <div class="modal-content">
                <h3 data-i18n="confirm_title">Please Confirm Your Details</h3>
                <p data-i18n="confirm_message">Here is the information you have submitted. Please check carefully before proceeding as these details cannot be changed later.</p>
                
                <div class="confirmation-details" id="confirmation_details">
                    <!-- Dynamic content will be inserted here -->
                </div>
                
                <div class="btn-container">
                    <button type="button" id="proceed_button" class="btn" data-i18n="proceed_button">Proceed</button>
                    <button type="button" id="cancel_button" class="btn btn-cancel" data-i18n="cancel_button">Cancel</button>
                </div>
            </div>
        </div>

        <div class="auth-links">
            <span data-i18n="already_account">Already have an account?</span> <a href="login.php" data-i18n="login_link">Login here</a>
        </div>
    </div>
    <div class="faq-section hidden">
        <h2 data-i18n="faq_title">Frequently Asked Questions</h2>
        <div class="faq-item">
            <button class="faq-question" data-i18n="faq_question_1">What information do I need to register?</button>
            <div class="faq-answer">
                <p data-i18n="faq_answer_1">All users must provide their full name, email, date of birth, role (Patient, Doctor, or Admin), password, and confirm their password. Additional requirements depend on your role:<br>
                - <strong>Patients</strong>: Select an organization, provide an emergency contact number, address, and choose a primary doctor.<br>
                - <strong>Doctors</strong>: Enter your specialization, select an organization, and provide a license number.<br>
                - <strong>Admins</strong>: Specify a hospital/organization name.</p>
            </div>
        </div>
        <div class="faq-item">
            <button class="faq-question" data-i18n="faq_question_2">How do I select my organization or doctor as a patient?</button>
            <div class="faq-answer">
                <p data-i18n="faq_answer_2">After selecting "Patient" as your role, choose your organization from the dropdown. Then, click the "Load Doctors" button to populate the list of doctors associated with that organization. Select your primary doctor from this list. If you change your organization, click "Load Doctors" again to refresh the list.</p>
            </div>
        </div>
        <div class="faq-item">
            <button class="faq-question" data-i18n="faq_question_3">Is my data secure during registration?</button>
            <div class="faq-answer">
                <p data-i18n="faq_answer_3">Yes, your data is protected with secure protocols. Ensure you use a strong password (at least 8 characters) and keep it safe, as password recovery is not available for security reasons.</p>
            </div>
        </div>
        <div class="faq-item">
            <button class="faq-question" data-i18n="faq_question_4">What happens after I register?</button>
            <div class="faq-answer">
                <p data-i18n="faq_answer_4">After submitting your details, you'll see a confirmation modal to review your information. Once confirmed, your account will be created, and you'll be redirected to the Login page after entering your login credentials you will be redirected to your dashboard based on the role you selected previously.</p>
            </div>
        </div>
        <div class="faq-item">
            <button class="faq-question" data-i18n="faq_question_5">Can I edit my details after registration?</button>
            <div class="faq-answer">
                <p data-i18n="faq_answer_5">Registration details cannot be changed after submission due to system restrictions. Please review all information carefully in the confirmation modal before proceeding.</p>
            </div>
        </div>
    </div>
</div>

<!-- Floating Help Button -->
<div class="floating-help">?</div>

<script>
const allDoctors = <?php echo json_encode($all_doctors); ?>;
const allOrganizations = <?php echo json_encode($all_organizations); ?>;

// Translation object for English and Urdu
const translations = {
    en: {
        title: "Register - RPM System",
        register_title: "Register for RPM System",
        full_name_label: "Full Name:",
        full_name_error: "Name must contain only letters and spaces.",
        email_label: "Email:",
        email_error: "Please enter a valid email (e.g., user@domain.com).",
        dob_label: "Date of Birth:",
        role_label: "I am a:",
        select_role: "Select role",
        role_Patient: "Patient",
        role_Doctor: "Doctor",
        role_Admin: "Admin",
        patient_organization_label: "Organization:",
        select_organization: "Select an organization",
        emergency_contact_label: "Emergency Contact Number:",
        emergency_contact_error: "Enter a 11-digit phone number.",
        address_label: "Address:",
        load_doctors: "Load Doctors",
        primary_doctor_label: "Primary Doctor:",
        select_doctor: "Select a doctor",
        doctor_select_note: "If you change your organization, click 'Load Doctors' again to view the doctors for the new organization.",
        specialization_label: "Specialization:",
        doctor_organization_label: "Hospital/Organization:",
        license_number_label: "License Number:",
        hospital_organization_label: "Hospital/Organization:",
        password_label: "Password:",
        password_note: "Please remember your password or write it in a safe place - password recovery is not available.",
        password_error: "Password must be at least 8 characters long.",
        confirm_password_label: "Confirm Password:",
        confirm_password_error: "Passwords must match.",
        register_button: "Register",
        ok_button: "OK",
        confirm_title: "Please Confirm Your Details",
        confirm_message: "Here is the information you have submitted. Please check carefully before proceeding as these details cannot be changed later.",
        proceed_button: "Proceed",
        cancel_button: "Cancel",
        already_account: "Already have an account?",
        login_link: "Login here",
        faq_title: "Frequently Asked Questions",
        faq_question_1: "What information do I need to register?",
        faq_answer_1: "All users must provide their full name, email, date of birth, role (Patient, Doctor, or Admin), password, and confirm their password. Additional requirements depend on your role:<br>- <strong>Patients</strong>: Select an organization, provide an emergency contact number, address, and choose a primary doctor.<br>- <strong>Doctors</strong>: Enter your specialization, select an organization, and provide a license number.<br>- <strong>Admins</strong>: Specify a hospital/organization name.",
        faq_question_2: "How do I select my organization or doctor as a patient?",
        faq_answer_2: "After selecting 'Patient' as your role, choose your organization from the dropdown. Then, click the 'Load Doctors' button to populate the list of doctors associated with that organization. Select your primary doctor from this list. If you change your organization, click 'Load Doctors' again to refresh the list.",
        faq_question_3: "Is my data secure during registration?",
        faq_answer_3: "Yes, your data is protected with secure protocols. Ensure you use a strong password (at least 8 characters) and keep it safe, as password recovery is not available for security reasons.",
        faq_question_4: "What happens after I register?",
        faq_answer_4: "After submitting your details, you'll see a confirmation modal to review your information. Once confirmed, your account will be created, and you'll be redirected to the Login page after entering your login credentials you will be redirected to your dashboard based on the role you selected previously.",
        faq_question_5: "Can I edit my details after registration?",
        faq_answer_5: "Registration details cannot be changed after submission due to system restrictions. Please review all information carefully in the confirmation modal before proceeding.",
        no_doctors_found: "No doctors found in this organization."
    },
    ur: {
        title: "رجسٹریشن - آر پی ایم سسٹم",
        register_title: "آر پی ایم سسٹم کے لیے رجسٹر کریں",
        full_name_label: "مکمل نام:",
        full_name_error: "نام میں صرف حروف اور خالی جگہ ہونی چاہیے۔",
        email_label: "ای میل:",
        email_error: "براہ کرم ایک درست ای میل درج کریں (جیسے، user@domain.com)۔",
        dob_label: "تاریخ پیدائش:",
        role_label: "میں ہوں:",
        select_role: "کردار منتخب کریں",
        role_Patient: "مریض",
        role_Doctor: "ڈاکٹر",
        role_Admin: "ایڈمن",
        patient_organization_label: "تنظیم:",
        select_organization: "ایک تنظیم منتخب کریں",
        emergency_contact_label: "ہنگامی رابطہ نمبر:",
        emergency_contact_error: "11 ہندسوں کا فون نمبر درج کریں۔",
        address_label: "پتہ:",
        load_doctors: "ڈاکٹرز لوڈ کریں",
        primary_doctor_label: "بنیادی ڈاکٹر:",
        select_doctor: "ایک ڈاکٹر منتخب کریں",
        doctor_select_note: "اگر آپ اپنی تنظیم تبدیل کرتے ہیں، تو نئی تنظیم کے ڈاکٹرز دیکھنے کے لیے دوبارہ 'ڈاکٹرز لوڈ کریں' پر کلک کریں۔",
        specialization_label: "خصوصیت:",
        doctor_organization_label: "ہسپتال/تنظیم:",
        license_number_label: "لائسنس نمبر:",
        hospital_organization_label: "ہسپتال/تنظیم:",
        password_label: "پاس ورڈ:",
        password_note: "براہ کرم اپنا پاس ورڈ یاد رکھیں یا اسے محفوظ جگہ پر لکھیں - پاس ورڈ کی بازیابی دستیاب نہیں ہے۔",
        password_error: "پاس ورڈ کم از کم 8 حروف کا ہونا چاہیے۔",
        confirm_password_label: "پاس ورڈ کی تصدیق کریں:",
        confirm_password_error: "پاس ورڈز کا میل ہونا ضروری ہے۔",
        register_button: "رجسٹر کریں",
        ok_button: "ٹھیک ہے",
        confirm_title: "براہ کرم اپنی تفصیلات کی تصدیق کریں",
        confirm_message: "یہ وہ معلومات ہیں جو آپ نے جمع کرائی ہیں۔ براہ کرم آگے بڑھنے سے پہلے احتیاط سے چیک کریں کیونکہ ان تفصیلات کو بعد میں تبدیل نہیں کیا جا سکتا۔",
        proceed_button: "آگے بڑھیں",
        cancel_button: "منسوخ کریں",
        already_account: "پہلے سے اکاؤنٹ ہے؟",
        login_link: "یہاں لاگ ان کریں",
        faq_title: "اکثر پوچھے جانے والے سوالات",
        faq_question_1: "رجسٹریشن کے لیے مجھے کون سی معلومات درکار ہیں؟",
        faq_answer_1: "تمام صارفین کو اپنا مکمل نام، ای میل، تاریخ پیدائش، کردار (مریض، ڈاکٹر، یا ایڈمن)، پاس ورڈ، اور پاس ورڈ کی تصدیق فراہم کرنی ہوگی۔ اضافی تقاضے آپ کے کردار پر منحصر ہیں:<br>- <strong>مریض</strong>: ایک تنظیم منتخب کریں، ہنگامی رابطہ نمبر، پتہ، اور بنیادی ڈاکٹر کا انتخاب کریں۔<br>- <strong>ڈاکٹرز</strong>: اپنی خصوصیت درج کریں، ایک تنظیم منتخب کریں، اور لائسنس نمبر فراہم کریں۔<br>- <strong>ایڈمنز</strong>: ہسپتال/تنظیم کا نام بتائیں۔",
        faq_question_2: "مریض کے طور پر میں اپنی تنظیم یا ڈاکٹر کا انتخاب کیسے کروں؟",
        faq_answer_2: "اپنا کردار 'مریض' منتخب کرنے کے بعد، ڈراپ ڈاؤن سے اپنی تنظیم منتخب کریں۔ پھر، اس تنظیم سے وابستہ ڈاکٹرز کی فہرست بنانے کے لیے 'ڈاکٹرز لوڈ کریں' بٹن پر کلک کریں۔ اس فہرست سے اپنا بنیادی ڈاکٹر منتخب کریں۔ اگر آپ اپنی تنظیم تبدیل کرتے ہیں، تو فہرست کو تازہ کرنے کے لیے دوبارہ 'ڈاکٹرز لوڈ کریں' پر کلک کریں۔",
        faq_question_3: "کیا رجسٹریشن کے دوران میرا ڈیٹا محفوظ ہے؟",
        faq_answer_3: "جی ہاں، آپ کا ڈیٹا محفوظ پروٹوکولز کے ساتھ محفوظ ہے۔ یقینی بنائیں کہ آپ ایک مضبوط پاس ورڈ (کم از کم 8 حروف) استعمال کریں اور اسے محفوظ رکھیں، کیونکہ سیکیورٹی وجوہات کی بنا پر پاس ورڈ کی بازیابی دستیاب نہیں ہے۔",
        faq_question_4: "رجسٹریشن کے بعد کیا ہوتا ہے؟",
        faq_answer_4: "اپنی تفصیلات جمع کرانے کے بعد، آپ اپنی معلومات کا جائزہ لینے کے لیے ایک تصدیقی موڈل دیکھیں گے۔ تصدیق کے بعد، آپ کا اکاؤنٹ بن جائے گا، اور آپ لاگ ان پیج پر منتقل ہو جائیں گے جہاں لاگ ان کی تفصیلات درج کرنے کے بعد آپ کو آپ کے منتخب کردہ کردار کے مطابق ڈیش بورڈ پر منتقل کر دیا جائے گا۔",
        faq_question_5: "کیا میں رجسٹریشن کے بعد اپنی تفصیلات میں ترمیم کر سکتا ہوں؟",
        faq_answer_5: "سسٹم کی پابندیوں کی وجہ سے رجسٹریشن کے بعد تفصیلات تبدیل نہیں کی جا سکتیں۔ براہ کرم تصدیقی موڈل میں تمام معلومات کو احتیاط سے جائزہ لیں۔",
        no_doctors_found: "اس تنظیم میں کوئی ڈاکٹر نہیں ملے۔"
    }
};

// Function to change language
function changeLanguage() {
    const lang = document.getElementById('language_select').value;
    document.querySelectorAll('[data-i18n]').forEach(element => {
        const key = element.getAttribute('data-i18n');
        if (translations[lang][key]) {
            element.innerHTML = translations[lang][key];
        }
    });

    // Update placeholder for no doctors found
    const primaryDoctorSelect = document.getElementById('primary_doctor_id');
    if (primaryDoctorSelect.options.length === 2 && primaryDoctorSelect.options[1].disabled) {
        primaryDoctorSelect.options[1].text = translations[lang].no_doctors_found;
    }

    // Update confirmation modal dynamically
    if (document.getElementById('confirm_modal').style.display === 'flex') {
        showConfirmModal(); // Refresh confirmation modal with new language
    }
}

// Function to validate doctor's organization
function validateDoctorOrganization(doctorId, patientOrgId, doctors, organizations) {
    if (!doctorId || !patientOrgId) {
        return null; // No validation needed if either is empty
    }
    const selectedDoctor = doctors.find(doctor => doctor.Doctor_ID == doctorId);
    if (selectedDoctor && selectedDoctor.organization_id != patientOrgId) {
        const orgName = organizations[patientOrgId] || translations[document.getElementById('language_select').value].select_organization;
        return translations[document.getElementById('language_select').value].doctor_organization_error || 
               `This doctor was not found in the ${orgName} organization. If you have changed the organization, kindly click "Load Doctors" again to refresh the list.`;
    }
    return null; // Valid
}

// Show modal with error message
function showErrorModal(message) {
    const modal = document.getElementById('error_modal');
    const errorMessage = document.getElementById('error_message');
    errorMessage.textContent = message;
    modal.style.display = 'flex';
}

// Hide modal
function hideErrorModal() {
    const modal = document.getElementById('error_modal');
    modal.style.display = 'none';
}

// Function to get role name from ID
function getRoleName(roleId) {
    const lang = document.getElementById('language_select').value;
    const roles = {
        '1': translations[lang].role_Patient,
        '2': translations[lang].role_Doctor,
        '3': translations[lang].role_Admin
    };
    return roles[roleId] || translations[lang].select_role;
}

// Function to get organization name from ID
function getOrganizationName(orgId) {
    return allOrganizations[orgId] || translations[document.getElementById('language_select').value].select_organization;
}

// Function to get doctor name from ID
function getDoctorName(doctorId) {
    if (!doctorId) return translations[document.getElementById('language_select').value].select_doctor;
    const doctor = allDoctors.find(d => d.Doctor_ID == doctorId);
    return doctor ? `${doctor.Full_Name} (${doctor.Specialization || translations[document.getElementById('language_select').value].select_doctor})` : translations[document.getElementById('language_select').value].select_doctor;
}

// Show confirmation modal with all user data
function showConfirmModal() {
    const lang = document.getElementById('language_select').value;
    // Get all form values
    const formData = {
        full_name: document.getElementById('full_name').value,
        email: document.getElementById('email').value,
        dob: document.getElementById('dob').value,
        role: getRoleName(document.getElementById('role').value),
        
        // Patient fields
        patient_organization_id: document.getElementById('patient_organization_id') ? 
            getOrganizationName(document.getElementById('patient_organization_id').value) : null,
        emergency_contact_number: document.getElementById('emergency_contact_number') ? 
            document.getElementById('emergency_contact_number').value || translations[lang].select_doctor : null,
        address: document.getElementById('address') ? 
            document.getElementById('address').value || translations[lang].select_doctor : null,
        primary_doctor_id: document.getElementById('primary_doctor_id') ? 
            getDoctorName(document.getElementById('primary_doctor_id').value) : null,
        
        // Doctor fields
        specialization: document.getElementById('specialization') ? 
            document.getElementById('specialization').value || translations[lang].select_doctor : null,
        doctor_organization_id: document.getElementById('organization_id') ? 
            getOrganizationName(document.getElementById('organization_id').value) : null,
        license_number: document.getElementById('license_number') ? 
            document.getElementById('license_number').value || translations[lang].select_doctor : null,
        
        // Admin fields
        hospital_organization: document.getElementById('hospital_organization') ? 
            document.getElementById('hospital_organization').value || translations[lang].select_doctor : null
    };

    // Build the confirmation HTML
    let confirmationHTML = `
        <div class="confirmation-row">
            <div class="confirmation-label">${translations[lang].full_name_label}</div>
            <div class="confirmation-value">${formData.full_name}</div>
        </div>
        <div class="confirmation-row">
            <div class="confirmation-label">${translations[lang].email_label}</div>
            <div class="confirmation-value">${formData.email}</div>
        </div>
        <div class="confirmation-row">
            <div class="confirmation-label">${translations[lang].dob_label}</div>
            <div class="confirmation-value">${formData.dob}</div>
        </div>
        <div class="confirmation-row">
            <div class="confirmation-label">${translations[lang].role_label}</div>
            <div class="confirmation-value">${formData.role}</div>
        </div>`;

    // Add role-specific fields
    if (formData.role === translations[lang].role_Patient) {
        confirmationHTML += `
            <div class="confirmation-row">
                <div class="confirmation-label">${translations[lang].patient_organization_label}</div>
                <div class="confirmation-value">${formData.patient_organization_id}</div>
            </div>
            <div class="confirmation-row">
                <div class="confirmation-label">${translations[lang].emergency_contact_label}</div>
                <div class="confirmation-value">${formData.emergency_contact_number}</div>
            </div>
            <div class="confirmation-row">
                <div class="confirmation-label">${translations[lang].address_label}</div>
                <div class="confirmation-value">${formData.address}</div>
            </div>
            <div class="confirmation-row">
                <div class="confirmation-label">${translations[lang].primary_doctor_label}</div>
                <div class="confirmation-value">${formData.primary_doctor_id}</div>
            </div>`;
    } else if (formData.role === translations[lang].role_Doctor) {
        confirmationHTML += `
            <div class="confirmation-row">
                <div class="confirmation-label">${translations[lang].specialization_label}</div>
                <div class="confirmation-value">${formData.specialization}</div>
            </div>
            <div class="confirmation-row">
                <div class="confirmation-label">${translations[lang].doctor_organization_label}</div>
                <div class="confirmation-value">${formData.doctor_organization_id}</div>
            </div>
            <div class="confirmation-row">
                <div class="confirmation-label">${translations[lang].license_number_label}</div>
                <div class="confirmation-value">${formData.license_number}</div>
            </div>`;
    } else if (formData.role === translations[lang].role_Admin) {
        confirmationHTML += `
            <div class="confirmation-row">
                <div class="confirmation-label">${translations[lang].hospital_organization_label}</div>
                <div class="confirmation-value">${formData.hospital_organization}</div>
            </div>`;
    }

    // Insert the confirmation HTML
    document.getElementById('confirmation_details').innerHTML = confirmationHTML;
    
    // Show the modal
    document.getElementById('confirm_modal').style.display = 'flex';
}

// Hide confirmation modal
function hideConfirmModal() {
    document.getElementById('confirm_modal').style.display = 'none';
}

// Toggle FAQ section visibility
function toggleFAQ() {
    const faqSection = document.querySelector('.faq-section');
    faqSection.classList.toggle('hidden');
    
    // Scroll to FAQ section if it's being shown
    if (!faqSection.classList.contains('hidden')) {
        faqSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}

document.getElementById('role').addEventListener('change', function() {
    document.querySelectorAll('.role-specific').forEach(function(field) {
        field.style.display = 'none';
    });

    const roleId = this.value;
    const roleMap = {
        '1': translations[document.getElementById('language_select').value].role_Patient,
        '2': translations[document.getElementById('language_select').value].role_Doctor,
        '3': translations[document.getElementById('language_select').value].role_Admin
    };

    const roleName = roleMap[roleId] || '';

    if (roleName === translations[document.getElementById('language_select').value].role_Patient) {
        document.getElementById('patient_fields').style.display = 'block';
    } else if (roleName === translations[document.getElementById('language_select').value].role_Doctor) {
        document.getElementById('doctor_fields').style.display = 'block';
    } else if (roleName === translations[document.getElementById('language_select').value].role_Admin) {
        document.getElementById('admin_fields').style.display = 'block';
    }
});

document.getElementById('patient_organization_id').addEventListener('change', function() {
    const orgId = this.value;
    if (orgId) {
        document.getElementById('doctor_select_container').style.display = 'block';
    } else {
        document.getElementById('doctor_select_container').style.display = 'none';
        const select = document.getElementById('primary_doctor_id');
        select.innerHTML = `<option value="" data-i18n="select_doctor">${translations[document.getElementById('language_select').value].select_doctor}</option>`;
    }
});

document.getElementById('load_doctors').addEventListener('click', function() {
    const orgId = document.getElementById('patient_organization_id').value;
    const lang = document.getElementById('language_select').value;
    if (!orgId) return;

    const select = document.getElementById('primary_doctor_id');
    select.innerHTML = `<option value="" data-i18n="select_doctor">${translations[lang].select_doctor}</option>`;

    const filteredDoctors = allDoctors.filter(doctor => doctor.organization_id == orgId);

    if (filteredDoctors.length === 0) {
        const option = document.createElement('option');
        option.value = '';
        option.text = translations[lang].no_doctors_found;
        option.disabled = true;
        select.appendChild(option);
        return;
    }

    filteredDoctors.forEach(doctor => {
        const option = document.createElement('option');
        option.value = doctor.Doctor_ID;
        const specialization = doctor.Specialization ? ` (${doctor.Specialization})` : '';
        option.text = `${doctor.Full_Name}${specialization}`;
        select.appendChild(option);
    });
});

// Handle register button click to show confirmation modal
document.getElementById('register_button').addEventListener('click', function(event) {
    event.preventDefault(); // Prevent immediate form submission

    // Validate form before showing modal
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    if (password !== confirmPassword) {
        showErrorModal(translations[document.getElementById('language_select').value].confirm_password_error);
        return;
    }

    // Validate doctor organization for patient role
    const roleId = document.getElementById('role').value;
    if (roleId === '1') { // Patient role
        const patientOrgId = document.getElementById('patient_organization_id').value;
        const doctorId = document.getElementById('primary_doctor_id').value;

        const errorMessage = validateDoctorOrganization(doctorId, patientOrgId, allDoctors, allOrganizations);
        if (errorMessage) {
            showErrorModal(errorMessage);
            return;
        }
    }

    // If validation passes, show confirmation modal with all data
    showConfirmModal();
});

// Handle proceed button in confirmation modal
document.getElementById('proceed_button').addEventListener('click', function() {
    document.getElementById('register_form').submit(); // Submit the form
});

// Handle cancel button in confirmation modal
document.getElementById('cancel_button').addEventListener('click', function() {
    hideConfirmModal(); // Close modal to allow user to edit
});

// Real-time password match feedback
document.getElementById('confirm_password').addEventListener('input', function() {
    const password = document.getElementById('password').value;
    const confirmPassword = this.value;
    const errorMessage = document.querySelector('#confirm_password + .error-message');
    const lang = document.getElementById('language_select').value;
    
    if (password && confirmPassword && password !== confirmPassword) {
        this.setCustomValidity(translations[lang].confirm_password_error);
        errorMessage.style.display = 'block';
    } else {
        this.setCustomValidity('');
        errorMessage.style.display = 'none';
    }
});

// Clear custom validity when password changes
document.getElementById('password').addEventListener('input', function() {
    const confirmPassword = document.getElementById('confirm_password');
    confirmPassword.setCustomValidity('');
    const errorMessage = document.querySelector('#confirm_password + .error-message');
    errorMessage.style.display = 'none';
});

// Close error modal on button click
document.getElementById('close_modal').addEventListener('click', hideErrorModal);

// FAQ Accordion Functionality
document.querySelectorAll('.faq-question').forEach(button => {
    button.addEventListener('click', () => {
        const answer = button.nextElementSibling;
        const isActive = answer.classList.contains('active');

        // Close all other answers
        document.querySelectorAll('.faq-answer').forEach(ans => {
            ans.classList.remove('active');
            ans.previousElementSibling.classList.remove('active');
        });

        // Toggle the clicked answer and button
        if (!isActive) {
            answer.classList.add('active');
            button.classList.add('active');
        }
    });
});

// Floating help button functionality
document.querySelector('.floating-help').addEventListener('click', toggleFAQ);

// Initialize language on page load
document.addEventListener('DOMContentLoaded', () => {
    changeLanguage();
});
</script>
</body>
</html>