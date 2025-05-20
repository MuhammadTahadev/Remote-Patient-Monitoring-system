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
    <title>Register - RPM System</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <style>
        /* Load Doctors Button Styling */
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

        /* Modal Styling */
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
            padding: 20px;
            border-radius: 8px;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
            text-align: center;
            border: 1px solid var(--primary-dark);
        }

        .modal-content p {
            color: var(--dark-gray);
            font-size: 16px;
            margin: 0 0 20px;
        }

        .modal-content .btn {
            background-color: var(--primary);
            color: var(--white);
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s ease;
            margin: 0 10px;
        }

        .modal-content .btn:hover {
            background-color: var(--secondary);
        }

        .modal-content .btn:active {
            background-color: var(--primary-dark);
        }

        /* Auth Wrapper for Side-by-Side Layout */
        .auth-wrapper {
            display: flex;
            max-width: 900px;
            margin: 50px auto;
            gap: 30px;
            align-items: flex-start;
        }

        /* FAQ Section */
        .faq-section {
            flex: 1;
            max-width: 400px;
            background-color: var(--white);
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
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

        /* Responsive Adjustments for Auth Wrapper */
        @media (max-width: 768px) {
            .auth-wrapper {
                flex-direction: column;
                max-width: 90%;
                gap: 20px;
            }

            .faq-section {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-container">
        <h2>Register for RPM System</h2>
        <?php displayAlert(); ?>
        <form id="register_form" action="process_register.php" method="post">
            <div class="form-group">
                <label for="full_name">Full Name:</label>
                <input type="text" id="full_name" name="full_name" pattern="[a-zA-Z\s]+" required aria-describedby="full_name_error">
                <small id="full_name_error" class="error-message">Name must contain only letters and spaces.</small>
            </div>
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required pattern="[a-zA-Z0-9._%+-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.[a-zA-Z]{2,6}$" maxlength="254" autocomplete="off" aria-describedby="email_error">
                <small id="email_error" class="error-message">Please enter a valid email (e.g., user@domain.com).</small>
            </div>
            <div class="form-group">
                <label for="dob">Date of Birth:</label>
                <input type="date" id="dob" name="dob" required max="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="form-group">
                <label for="role">I am a:</label>
                <select id="role" name="role" required>
                    <option value="">Select role</option>
                    <?php
                    $roles = $conn->query("SELECT * FROM Role WHERE Role_Name IN ('Patient', 'Doctor', 'Admin')");
                    while ($role = $roles->fetch_assoc()) {
                        echo "<option value='{$role['Role_ID']}'>{$role['Role_Name']}</option>";
                    }
                    ?>
                </select>
            </div>

            <!-- Patient-specific fields (hidden by default) -->
            <div id="patient_fields" class="role-specific" style="display: none;">
                <div class="form-group">
                    <label for="patient_organization_id">Organization:</label>
                    <select id="patient_organization_id" name="patient_organization_id">
                        <option value="">Select an organization</option>
                        <?php
                        $organizations->data_seek(0); // Reset pointer
                        while ($org = $organizations->fetch_assoc()) {
                            echo "<option value='{$org['organization_id']}'>{$org['hospital_organization']}</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="emergency_contact_number">Emergency Contact Number:</label>
                    <input type="tel" id="emergency_contact_number" name="emergency_contact_number" pattern="[0-9]{11}" title="Enter a 11-digit phone number">
                    <small class="error-message">Enter a 11-digit phone number.</small>
                </div>
                <div class="form-group">
                    <label for="address">Address:</label>
                    <textarea id="address" name="address"></textarea>
                </div>
                <div id="doctor_select_container" class="form-group" style="display: none;">
                    <button type="button" id="load_doctors" class="btn">Load Doctors</button>
                    <label for="primary_doctor_id">Primary Doctor:</label>
                    <select id="primary_doctor_id" name="primary_doctor_id">
                        <option value="">Select a doctor</option>
                    </select>
                    <small style="color: #666;">If you change your organization, click "Load Doctors" again to view the doctors for the new organization.</small>
                </div>
            </div>

            <!-- Doctor-specific fields (hidden by default) -->
            <div id="doctor_fields" class="role-specific" style="display: none;">
                <div class="form-group">
                    <label for="specialization">Specialization:</label>
                    <input type="text" id="specialization" name="specialization" autocomplete="off">
                </div>
                <div class="form-group">
                    <label for="organization_id">Hospital/Organization:</label>
                    <select id="organization_id" name="organization_id">
                        <option value="">Select an organization</option>
                        <?php
                        $organizations->data_seek(0); // Reset pointer
                        while ($org = $organizations->fetch_assoc()) {
                            echo "<option value='{$org['organization_id']}'>{$org['hospital_organization']}</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="license_number">License Number:</label>
                    <input type="text" id="license_number" name="license_number">
                </div>
            </div>

            <!-- Admin-specific fields (hidden by default) -->
            <div id="admin_fields" class="role-specific" style="display: none;">
                <div class="form-group">
                    <label for="hospital_organization">Hospital/Organization:</label>
                    <input type="text" id="hospital_organization" name="hospital_organization">
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required minlength="8" aria-describedby="password_error">
                <small style="color: #666;">Please remember your password or write it in a safe place - password recovery is not available.</small>
                <small id="password_error" class="error-message">Password must be at least 8 characters long.</small>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="8" aria-describedby="confirm_password_error">
                <small id="confirm_password_error" class="error-message">Passwords must match.</small>
            </div>
            <button type="button" id="register_button" class="btn">Register</button>
        </form>

        <!-- Error Modal -->
        <div id="error_modal" class="modal">
            <div class="modal-content">
                <p id="error_message"></p>
                <button type="button" id="close_modal" class="btn">OK</button>
            </div>
        </div>

        <!-- Confirmation Modal -->
        <div id="confirm_modal" class="modal">
            <div class="modal-content">
                <p>Please recheck all details before proceeding. Once submitted, these details cannot be changed.</p>
                <button type="button" id="proceed_button" class="btn">Proceed</button>
                <button type="button" id="check_again_button" class="btn">Check Again</button>
            </div>
        </div>

        <div class="auth-links">
            Already have an account? <a href="login.php">Login here</a>
        </div>
    </div>
    <div class="faq-section">
        <h2>Frequently Asked Questions</h2>
        <div class="faq-item">
            <button class="faq-question">What information do I need to register?</button>
            <div class="faq-answer">
                <p>All users must provide their full name, email, date of birth, role (Patient, Doctor, or Admin), password, and confirm their password. Additional requirements depend on your role:<br>
                - <strong>Patients</strong>: Select an organization, provide an emergency contact number, address, and choose a primary doctor.<br>
                - <strong>Doctors</strong>: Enter your specialization, select an organization, and provide a license number.<br>
                - <strong>Admins</strong>: Specify a hospital/organization name.</p>
            </div>
        </div>
        <div class="faq-item">
            <button class="faq-question">How do I select my organization or doctor as a patient?</button>
            <div class="faq-answer">
                <p>After selecting "Patient" as your role, choose your organization from the dropdown. Then, click the "Load Doctors" button to populate the list of doctors associated with that organization. Select your primary doctor from this list. If you change your organization, click "Load Doctors" again to refresh the list.</p>
            </div>
        </div>
        <div class="faq-item">
            <button class="faq-question">Is my data secure during registration?</button>
            <div class="faq-answer">
                <p>Yes, your data is protected with secure protocols. Ensure you use a strong password (at least 8 characters) and keep it safe, as password recovery is not available for security reasons.</p>
            </div>
        </div>
        <div class="faq-item">
            <button class="faq-question">What happens after I register?</button>
            <div class="faq-answer">
                <p>After submitting your details, you’ll see a confirmation modal to review your information. Once confirmed, your account will be created, and you’ll be redirected to the Login page after entering your login credentials you will be redirected to your dashboard based on the role you selected previously.</p>
            </div>
        </div>
        <div class="faq-item">
            <button class="faq-question">Can I edit my details after registration?</button>
            <div class="faq-answer">
                <p>Registration details cannot be changed after submission due to system restrictions. Please review all information carefully in the confirmation modal before proceeding.</p>
            </div>
        </div>
    </div>
</div>

<script>
const allDoctors = <?php echo json_encode($all_doctors); ?>;
const allOrganizations = <?php echo json_encode($all_organizations); ?>;

// Function to validate doctor's organization
function validateDoctorOrganization(doctorId, patientOrgId, doctors, organizations) {
    if (!doctorId || !patientOrgId) {
        return null; // No validation needed if either is empty
    }
    const selectedDoctor = doctors.find(doctor => doctor.Doctor_ID == doctorId);
    if (selectedDoctor && selectedDoctor.organization_id != patientOrgId) {
        const orgName = organizations[patientOrgId] || 'selected';
        return `This doctor was not found in the ${orgName} organization. If you have changed the organization, kindly click "Load Doctors" again to refresh the list.`;
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

// Show confirmation modal
function showConfirmModal() {
    const modal = document.getElementById('confirm_modal');
    modal.style.display = 'flex';
}

// Hide confirmation modal
function hideConfirmModal() {
    const modal = document.getElementById('confirm_modal');
    modal.style.display = 'none';
}

document.getElementById('role').addEventListener('change', function() {
    document.querySelectorAll('.role-specific').forEach(function(field) {
        field.style.display = 'none';
    });

    const roleId = this.value;
    const roleMap = {
        '1': 'Patient',
        '2': 'Doctor',
        '3': 'Admin'
    };

    const roleName = roleMap[roleId] || '';

    if (roleName === 'Patient') {
        document.getElementById('patient_fields').style.display = 'block';
    } else if (roleName === 'Doctor') {
        document.getElementById('doctor_fields').style.display = 'block';
    } else if (roleName === 'Admin') {
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
        select.innerHTML = '<option value="">Select a doctor</option>';
    }
});

document.getElementById('load_doctors').addEventListener('click', function() {
    const orgId = document.getElementById('patient_organization_id').value;
    if (!orgId) return;

    const select = document.getElementById('primary_doctor_id');
    select.innerHTML = '<option value="">Select a doctor</option>';
    
    const filteredDoctors = allDoctors.filter(doctor => doctor.organization_id == orgId);
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
        showErrorModal('Passwords do not match.');
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

    // If validation passes, show confirmation modal
    showConfirmModal();
});

// Handle proceed button in confirmation modal
document.getElementById('proceed_button').addEventListener('click', function() {
    document.getElementById('register_form').submit(); // Submit the form
});

// Handle check again button in confirmation modal
document.getElementById('check_again_button').addEventListener('click', function() {
    hideConfirmModal(); // Close modal to allow user to review
});

// Real-time password match feedback
document.getElementById('confirm_password').addEventListener('input', function() {
    const password = document.getElementById('password').value;
    const confirmPassword = this.value;
    const errorMessage = document.querySelector('#confirm_password + .error-message');
    
    if (password && confirmPassword && password !== confirmPassword) {
        this.setCustomValidity('Passwords do not match.');
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
</script>
</body>
</html>
