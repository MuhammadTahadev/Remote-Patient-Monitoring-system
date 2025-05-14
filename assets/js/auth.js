// // auth.js - Authentication related JavaScript

// document.addEventListener('DOMContentLoaded', function() {
//     // Login form validation
//     const loginForm = document.querySelector('form[action="process_login.php"]');
//     if (loginForm) {
//         loginForm.addEventListener('submit', function(e) {
//             const email = document.getElementById('email').value.trim();
//             const password = document.getElementById('password').value.trim();
            
//             if (!email || !password) {
//                 e.preventDefault();
//                 alert('Please fill in all fields');
//             }
//         });
//     }
    
//     // Registration form validation
//     const registerForm = document.querySelector('form[action="process_register.php"]');
//     if (registerForm) {
//         registerForm.addEventListener('submit', function(e) {
//             const role = document.getElementById('role').value;
//             const password = document.getElementById('password').value;
//             const confirmPassword = document.getElementById('confirm_password').value;

//             // Basic validation
//             if (password !== confirmPassword) {
//                 e.preventDefault();
//                 alert('Passwords do not match');
//                 return;
//             }

//             // Role-specific validation
//             if (role === '1') { // Patient
//                 const emergency = document.getElementById('emergency_contact_number').value;
//                 const address = document.getElementById('address').value;
//                 const org = document.getElementById('organization_id_patient').value;
                
//                 if (!emergency || !address || !org) {
//                     e.preventDefault();
//                     alert('Please fill all required patient fields');
//                     return;
//                 }
//             } else if (role === '2') { // Doctor
//                 const spec = document.getElementById('specialization').value;
//                 const license = document.getElementById('license_number').value;
//                 const org = document.getElementById('organization_id_doctor').value;
                
//                 if (!spec || !license || !org) {
//                     e.preventDefault();
//                     alert('Please fill all required doctor fields');
//                     return;
//                 }
//             } else if (role === '3') { // Admin
//                 const hospital = document.getElementById('hospital_organization').value;
//                 if (!hospital) {
//                     e.preventDefault();
//                     alert('Please enter hospital/organization name');
//                     return;
//                 }
//             }

//             // If we get here, form is valid - let it submit naturally
//             return true;
//         });
//     }
    
//     // Forgot password form
//     const forgotPasswordForm = document.querySelector('form[action="process_forgot_password.php"]');
//     if (forgotPasswordForm) {
//         forgotPasswordForm.addEventListener('submit', function(e) {
//             const email = document.getElementById('email').value.trim();
            
//             if (!email) {
//                 e.preventDefault();
//                 alert('Please enter your email');
//             }
//         });
//     }
    
//     // Toggle password visibility
//     const togglePasswordButtons = document.querySelectorAll('.toggle-password');
//     togglePasswordButtons.forEach(button => {
//         button.addEventListener('click', function() {
//             const input = this.previousElementSibling;
//             const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
//             input.setAttribute('type', type);
//             this.querySelector('i').classList.toggle('fa-eye');
//             this.querySelector('i').classList.toggle('fa-eye-slash');
//         });
//     });
// });
