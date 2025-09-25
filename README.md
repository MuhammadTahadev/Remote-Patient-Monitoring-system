# Still Under Development


# RPM - Remote Patient Monitoring System

## Project Description
RPM is an advanced Remote Patient Monitoring system designed to connect patients and healthcare providers for better health outcomes. The platform enables manual entry of vital signs by patients with near real-time alert updates to healthcare teams via periodic polling, smart alerts, and detailed health reports, facilitating proactive and patient-centered care anytime, anywhere.

## Key Features
- **Real-Time Health Tracking:** Manual entry of vital signs by patients with near real-time alert updates to healthcare teams via periodic polling.
- **Smart Alerts:** Automated notifications for concerning health patterns or medication reminders.
- **Health Reports:** Detailed analytics and reports to track patient progress over time.
- **Role-Based Access:** Separate dashboards and functionalities for Admins, Doctors, and Patients.
- **Secure Authentication:** User registration and login with role-specific data capture.
- **Communication:** Direct chat functionality between doctors and patients.
- **Data Visualization:** Trends and history views for patient vitals and health data.

## User Roles and Capabilities

### Admin
- Manage doctors and patients within their organization.
- View counts of active doctors and patients.
- Reinstate archived users.

### Doctor
- View and manage assigned patients.
- Generate and access health reports.
- Monitor recent patient activity.
- Communicate with patients via chat.
- Access alert history and notifications.

### Patient
- Record and view recent vitals.
- Access vitals history and health trends.
- Chat directly with assigned doctors.
- View detailed health reports.

## Installation and Setup
1. Ensure you have a web server with PHP and MySQL installed (e.g., XAMPP).
2. Clone or download the repository into your web server's root directory.
3. Configure your database and update connection settings as needed.
4. Start the web server and navigate to the project URL (e.g., `http://localhost/rpm-system`).
5. Use the registration page to create user accounts for different roles.
6. Log in to access role-specific dashboards and features.

### Database Setup
Create database `rpm_system` in MySQL/phpMyAdmin, then create the following tables and keys.

#### Schema overview (tables → key relations)
- `Role` ← `User.Role_ID`
- `Organization` ← `Doctor.Organization_ID`, `Patient.Organization_ID`, `Admin.Organization_ID`
- `User` ← `Doctor.User_ID`, `Patient.User_ID`, `Admin.User_ID`, `Notifications.User_ID` (recipient), `Notifications.Sender_ID`, `Reports.Generated_By_User_ID`
- `Doctor` ← `Patient.Primary_Doctor_ID`, `DoctorPatientMapping.Doctor_ID`, `Alerts.Doctor_ID`, `Reports.Doctor_ID`
- `Patient` ← `DoctorPatientMapping.Patient_ID`, `HealthData.Patient_ID`, `Alerts.Patient_ID`, `Reports.Patient_ID`
- `AlertThreshold` standalone (used for vitals evaluation)

#### Minimal DDL (create these tables/keys)
Note: adjust types/lengths as needed.

```sql
CREATE TABLE Role (
  Role_ID INT AUTO_INCREMENT PRIMARY KEY,
  Role_Name VARCHAR(50) NOT NULL UNIQUE
);

CREATE TABLE Organization (
  organization_id INT AUTO_INCREMENT PRIMARY KEY,
  hospital_organization VARCHAR(255) NOT NULL UNIQUE
);

CREATE TABLE User (
  User_ID INT AUTO_INCREMENT PRIMARY KEY,
  Full_Name VARCHAR(255) NOT NULL,
  EMAIL VARCHAR(255) NOT NULL UNIQUE,
  Phone_Number VARCHAR(50) NULL,
  Date_of_Birth DATE NOT NULL,
  Password VARCHAR(255) NOT NULL,
  Role_ID INT NOT NULL,
  status ENUM('active','archived') NOT NULL DEFAULT 'active',
  FOREIGN KEY (Role_ID) REFERENCES Role(Role_ID)
);

CREATE TABLE Doctor (
  Doctor_ID INT AUTO_INCREMENT PRIMARY KEY,
  User_ID INT NOT NULL UNIQUE,
  Specialization VARCHAR(255) NULL,
  License_Number VARCHAR(100) NULL,
  Organization_ID INT NOT NULL,
  FOREIGN KEY (User_ID) REFERENCES User(User_ID) ON DELETE CASCADE,
  FOREIGN KEY (Organization_ID) REFERENCES Organization(organization_id)
);

CREATE TABLE Admin (
  Admin_ID INT AUTO_INCREMENT PRIMARY KEY,
  User_ID INT NOT NULL UNIQUE,
  Organization_ID INT NOT NULL,
  FOREIGN KEY (User_ID) REFERENCES User(User_ID) ON DELETE CASCADE,
  FOREIGN KEY (Organization_ID) REFERENCES Organization(organization_id)
);

CREATE TABLE Patient (
  Patient_ID INT AUTO_INCREMENT PRIMARY KEY,
  User_ID INT NOT NULL UNIQUE,
  Emergency_Contact_Number VARCHAR(50) NOT NULL,
  Address VARCHAR(500) NOT NULL,
  Primary_Doctor_ID INT NULL,
  Organization_ID INT NOT NULL,
  FOREIGN KEY (User_ID) REFERENCES User(User_ID) ON DELETE CASCADE,
  FOREIGN KEY (Primary_Doctor_ID) REFERENCES Doctor(Doctor_ID) ON DELETE SET NULL,
  FOREIGN KEY (Organization_ID) REFERENCES Organization(organization_id)
);

CREATE TABLE DoctorPatientMapping (
  Mapping_ID INT AUTO_INCREMENT PRIMARY KEY,
  Doctor_ID INT NOT NULL,
  Patient_ID INT NOT NULL,
  Assignment_Date DATE NOT NULL,
  Is_Primary TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_doctor_patient (Doctor_ID, Patient_ID),
  FOREIGN KEY (Doctor_ID) REFERENCES Doctor(Doctor_ID) ON DELETE CASCADE,
  FOREIGN KEY (Patient_ID) REFERENCES Patient(Patient_ID) ON DELETE CASCADE
);

CREATE TABLE HealthData (
  Data_ID INT AUTO_INCREMENT PRIMARY KEY,
  Patient_ID INT NOT NULL,
  Heart_Rate INT NULL,
  Systolic_BP INT NULL,
  Diastolic_BP INT NULL,
  Glucose_Level DECIMAL(7,2) NULL,
  Oxygen_Saturation DECIMAL(5,2) NULL,
  Temperature DECIMAL(5,2) NULL,
  Weight DECIMAL(7,2) NULL,
  Notes TEXT NULL,
  Timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_health_patient_ts (Patient_ID, Timestamp),
  FOREIGN KEY (Patient_ID) REFERENCES Patient(Patient_ID) ON DELETE CASCADE
);

CREATE TABLE Alerts (
  Alert_ID INT AUTO_INCREMENT PRIMARY KEY,
  Doctor_ID INT NOT NULL,
  Patient_ID INT NOT NULL,
  Alert_Type VARCHAR(100) NOT NULL,
  Message VARCHAR(1000) NOT NULL,
  Severity ENUM('Info','Warning','Critical','Unknown') NOT NULL DEFAULT 'Warning',
  Status ENUM('Unread','Read','Resolved') NOT NULL DEFAULT 'Unread',
  Created_At TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_alerts_doctor_patient (Doctor_ID, Patient_ID),
  FOREIGN KEY (Doctor_ID) REFERENCES Doctor(Doctor_ID) ON DELETE CASCADE,
  FOREIGN KEY (Patient_ID) REFERENCES Patient(Patient_ID) ON DELETE CASCADE
);

CREATE TABLE Notifications (
  Notification_ID INT AUTO_INCREMENT PRIMARY KEY,
  User_ID INT NOT NULL,
  Sender_ID INT NOT NULL,
  Alert_Type VARCHAR(100) NULL,
  Message VARCHAR(1000) NOT NULL,
  Sent_At TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  Status ENUM('Sent','Read') NOT NULL DEFAULT 'Sent',
  INDEX idx_notifications_user_status (User_ID, Status),
  FOREIGN KEY (User_ID) REFERENCES User(User_ID) ON DELETE CASCADE,
  FOREIGN KEY (Sender_ID) REFERENCES User(User_ID) ON DELETE CASCADE
);

CREATE TABLE Reports (
  Report_ID INT AUTO_INCREMENT PRIMARY KEY,
  Patient_ID INT NOT NULL,
  Doctor_ID INT NULL,
  Generated_On DATETIME NOT NULL,
  Report_Period VARCHAR(50) NOT NULL,
  Report_Type VARCHAR(50) NOT NULL,
  File_Path VARCHAR(500) NOT NULL,
  Generated_By_User_ID INT NOT NULL,
  INDEX idx_reports_patient_time (Patient_ID, Generated_On),
  FOREIGN KEY (Patient_ID) REFERENCES Patient(Patient_ID) ON DELETE CASCADE,
  FOREIGN KEY (Doctor_ID) REFERENCES Doctor(Doctor_ID) ON DELETE SET NULL,
  FOREIGN KEY (Generated_By_User_ID) REFERENCES User(User_ID) ON DELETE CASCADE
);

CREATE TABLE AlertThreshold (
  Threshold_ID INT AUTO_INCREMENT PRIMARY KEY,
  Metric_Type VARCHAR(100) NOT NULL UNIQUE,
  Min_Value DECIMAL(10,2) NULL,
  Max_Value DECIMAL(10,2) NULL,
  Severity ENUM('Warning','Critical') NOT NULL DEFAULT 'Warning',
  Description VARCHAR(255) NULL
);

-- Recommended seed data
INSERT INTO Role (Role_Name) VALUES ('Patient'), ('Doctor'), ('Admin');
INSERT INTO Organization (hospital_organization) VALUES ('General Hospital');
INSERT INTO AlertThreshold (Metric_Type, Min_Value, Max_Value, Severity, Description) VALUES
('Heart Rate', 60, 100, 'Warning', 'Normal resting heart rate range'),
('Systolic BP', 90, 140, 'Critical', 'Normal systolic blood pressure range'),
('Diastolic BP', 60, 90, 'Critical', 'Normal diastolic blood pressure range'),
('Glucose', 70, 140, 'Warning', 'Normal fasting glucose range'),
('Oxygen Saturation', 95, 100, 'Critical', 'Normal oxygen saturation range'),
('Temperature', 97.0, 99.0, 'Warning', 'Normal body temperature (°F)'),
('Weight', 100, 200, 'Warning', 'Typical adult weight range (lbs)');
```

## Usage
- Access the landing page to learn about the system and navigate to login or registration.
- Register as a Patient, Doctor, or Admin with role-specific information.
- Log in to access your dashboard and utilize the features available for your role.
- Admins can manage users, doctors can monitor patients and generate reports, and patients can track their health data and communicate with doctors.

## Technologies Used
- PHP for backend logic and server-side processing.
- MySQL for database management.
- HTML, CSS, and JavaScript for frontend interface and interactivity.
- Font Awesome for icons and UI elements.
