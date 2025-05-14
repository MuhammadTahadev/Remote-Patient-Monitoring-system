<?php
require_once '../config/config.php';
require_once '../includes/header.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Ensure only doctors can access
requireRole('Admin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    Welcome To Admin Dashboard  
</body>
</html>

<style>
    /* Doctor Dashboard Specific Styles */
/* Doctor Dashboard Modern Styles */
.doctor-dashboard {
    padding: 2rem;
    max-width: 1400px;
    margin: 0 auto;
}

.doctor-dashboard h2 {
    color: var(--primary-dark);
    font-size: 2.2rem;
    margin-bottom: 1.5rem;
    font-weight: 700;
    text-align: center;
    position: relative;
    padding-bottom: 1rem;
}

.doctor-dashboard h2::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 80px;
    height: 4px;
    background: var(--secondary);
    border-radius: 2px;
}

.dashboard-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2.5rem;
}

/* Dashboard Cards */
.dashboard-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    padding: 1.75rem;
    transition: all 0.3s ease;
    border: 1px solid rgba(0, 0, 0, 0.05);
    position: relative;
    overflow: hidden;
}

.dashboard-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 0;
    background: var(--secondary);
    transition: height 0.3s ease;
}

.dashboard-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12);
    border-color: rgba(221, 161, 94, 0.2);
}

.dashboard-card:hover::before {
    height: 100%;
}

.card-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    color: white;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    margin-bottom: 1.25rem;
    position: relative;
    transition: transform 0.3s ease;
}

.dashboard-card:hover .card-icon {
    transform: rotate(10deg) scale(1.1);
}

.card-content h3 {
    color: var(--primary-dark);
    font-size: 1.3rem;
    margin-bottom: 0.75rem;
    font-weight: 600;
}

.card-value {
    font-size: 2rem;
    font-weight: 700;
    color: var(--secondary-dark);
    margin: 0.5rem 0 1.5rem;
    letter-spacing: -0.5px;
}

.card-content p {
    color: var(--dark-gray);
    font-size: 0.95rem;
    line-height: 1.5;
    margin-bottom: 1.5rem;
}

/* Buttons */
.btn-primary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.75rem 1.5rem;
    background-color: var(--primary-dark);
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    margin-top: auto;
    align-self: flex-start;
}

.btn-primary:hover {
    background-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(96, 108, 56, 0.3);
}

.btn-primary i {
    margin-right: 0.5rem;
}

/* Chat Card Specific */
.dashboard-card .card-icon .badge {
    position: absolute;
    top: -8px;
    right: -8px;
    background-color: var(--secondary);
    color: white;
    border-radius: 50%;
    width: 26px;
    height: 26px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    font-weight: 700;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
    animation: pulse 1.5s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

/* Recent Activity Section */
.recent-activity {
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    padding: 2rem;
    margin-top: 2rem;
}

.recent-activity h3 {
    color: var(--primary-dark);
    font-size: 1.5rem;
    margin-bottom: 1.5rem;
    font-weight: 600;
    position: relative;
    padding-bottom: 0.75rem;
}

.recent-activity h3::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 60px;
    height: 3px;
    background: var(--secondary);
    border-radius: 2px;
}

.activity-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    margin-top: 1rem;
}

.activity-table thead th {
    background-color: var(--primary);
    color: white;
    padding: 1rem;
    text-align: left;
    font-weight: 500;
    position: sticky;
    top: 0;
}

.activity-table th:first-child {
    border-top-left-radius: 8px;
}

.activity-table th:last-child {
    border-top-right-radius: 8px;
}

.activity-table td {
    padding: 1rem;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.activity-table tr:last-child td {
    border-bottom: none;
}

.activity-table tr:hover td {
    background-color: rgba(96, 108, 56, 0.03);
}

.btn-link {
    color: var(--primary);
    background: none;
    border: none;
    padding: 0.5rem 0;
    margin-right: 1rem;
    font: inherit;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
}

.btn-link:hover {
    color: var(--secondary-dark);
    transform: translateX(3px);
}

.btn-link i {
    margin-right: 0.5rem;
    font-size: 0.9rem;
}

/* Responsive Adjustments */
@media (max-width: 1200px) {
    .dashboard-cards {
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    }
}

@media (max-width: 992px) {
    .doctor-dashboard {
        padding: 1.5rem;
    }
    
    .recent-activity {
        padding: 1.5rem;
    }
}

@media (max-width: 768px) {
    .doctor-dashboard h2 {
        font-size: 1.9rem;
    }
    
    .recent-activity h3 {
        font-size: 1.3rem;
    }
    
    .activity-table {
        font-size: 0.9rem;
    }
    
    .activity-table th,
    .activity-table td {
        padding: 0.8rem;
    }
}

@media (max-width: 576px) {
    .doctor-dashboard {
        padding: 1rem;
    }
    
    .dashboard-cards {
        grid-template-columns: 1fr;
    }
    
    .dashboard-card {
        padding: 1.5rem;
    }
    
    .card-content h3 {
        font-size: 1.2rem;
    }
    
    .card-value {
        font-size: 1.8rem;
    }
    
    .recent-activity {
        padding: 1.25rem;
        overflow-x: auto;
    }
    
    .activity-table {
        min-width: 600px;
    }
}
</style>
<?php require_once '../includes/footer.php'; ?>