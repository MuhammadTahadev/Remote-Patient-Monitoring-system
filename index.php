<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RPM | Remote Patient Monitoring</title>
    <link rel="stylesheet" href="assets/css/home.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Header -->
    <header>
        <div class="container">
            <div class="logo">
                <i class="fas fa-heartbeat"></i>
                <h1>RPM</h1>
            </div>
            <nav class="navbar">
                <a href="#" class="active">Home</a>|
                <a href="#features">Features</a>|
                <a href="#about">About</a>|
                <a href="#contact">Contact</a>|
                <a href="auth/login.php">Login</a>|
                <a href="auth/register.php">Register</a>
            </nav>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="hero-content">
                <h2>Monitor Your Health <span>Anytime, Anywhere</span></h2>
                <p>Our advanced remote monitoring system connects patients and doctors for better health outcomes. Stay on top of your health with real-time data and professional support.</p>
                <a href="auth/register.php" class="btn">Get Started</a>
                <a href="#features" class="btn btn-secondary">Learn More</a>
            </div>
            <div class="hero-image">
                <img src="https://images.unsplash.com/photo-1579684385127-1ef15d508118?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80" alt="Doctor reviewing patient data remotely">
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features">
        <div class="container">
            <h2>Key Features</h2>
            <p class="section-subtitle">Comprehensive remote monitoring for better healthcare</p>
            
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-heartbeat"></i>
                    </div>
                    <h3>Real-Time Health Tracking</h3>
                    <p>Continuous monitoring of vital signs with instant updates to your healthcare team.</p>
                </div>
                
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-bell"></i>
                    </div>
                    <h3>Smart Alerts</h3>
                    <p>Automated notifications for concerning health patterns or medication reminders.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3>Health Reports</h3>
                    <p>Detailed analytics and reports to track your progress over time.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="about">
        <div class="container">
            <div class="about-image">
                <img src="https://images.unsplash.com/photo-1581056771107-24ca5f033842?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80" alt="Doctor using tablet">
            </div>
            <div class="about-content">
                <h2>Our Mission</h2>
                <p>At RPM, we believe in making healthcare more accessible, efficient, and patient-centered. Our remote patient monitoring system bridges the gap between patients and healthcare providers, enabling proactive care and better health outcomes.</p>
                <p>With cutting-edge technology and a compassionate approach, we're transforming the way healthcare is delivered - one connection at a time.</p>
                <a href="#" class="btn">Meet Our Team</a>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta">
        <div class="container">
            <h2>Ready to take control of your health?</h2>
            <p>Join thousands of patients and healthcare providers using our platform.</p>
            <a href="#" class="btn">Get Started Today</a>
        </div>
    </section>

    <!-- Footer -->
    <footer id="contact">
        <div class="container">
            <div class="footer-brand">
                <div class="logo">
                    <i class="fas fa-heartbeat"></i>
                    <h2>RPM</h2>
                </div>
                <p>Innovative remote patient monitoring for better healthcare outcomes.</p>
                <div class="social-links">
                    <a href="#"><i class="fab fa-facebook"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-linkedin"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
            
            <div class="footer-links">
                <h3>Quick Links</h3>
                <ul>
                    <li><a href="#" class="active">Home</a></li>
                    <li><a href="#features">Features</a></li>
                    <li><a href="#about">About Us</a></li>
                    <li><a href="#">Privacy Policy</a></li>
                    <li><a href="#">Terms of Service</a></li>
                </ul>
            </div>
            
            <div class="footer-contact">
                <h3>Contact Us</h3>
                <ul>
                    <li><i class="fas fa-map-marker-alt"></i> XYZ-Hub</li>
                    <li><i class="fas fa-phone"></i> 051-3273921</li>
                    <li><i class="fas fa-envelope"></i> info@RPM.com</li>
                </ul>
            </div>
        </div>
        
        <div class="copyright">
            <p>&copy; 2025 RPM. All rights reserved.</p>
        </div>
    </footer>

    <script src="nav.js"></script>
</body>
</html>
