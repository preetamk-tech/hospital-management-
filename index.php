<?php
require_once 'config/db.php';

if (isLoggedIn()) {
    if (hasRole('patient')) redirect('patient/dashboard.php');
    elseif (hasRole('doctor')) redirect('doctor/dashboard.php');
    elseif (hasRole('receptionist')) redirect('receptionist/dashboard.php');
    elseif (hasRole('admin')) redirect('admin/dashboard.php');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediQueue - Smart Hospital Queue Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="stylesheet" href="assets/css/chatbot.css">
</head>

<body>
    <script>
        window.MediBotConfig = {
            apiUrl: "api/chatbot.php",
            role: "visitor",
            userName: "Visitor",
            page: "index.php"
        };
    </script>
    <!-- Navigation Bar -->
    <nav class="main-navbar">
        <a href="#" class="logo">
            <i class="fas fa-hospital-user"></i>
            <h2>MediQueue</h2>
        </a>
        <div class="nav-links">
            <a href="#">Home</a>
            <a href="#Services">Services</a>
            <a href="#about">About</a>
            <a href="#contact">Contact</a>
            <div class="btn-group-header">
                <a href="auth/login.php" class="nav-btn">Login</a>
                <a href="auth/register.php" class="nav-btn">Register</a>
            </div>
        </div>
    </nav>

    <!-- slider -->
    <div class="slider">
        <img src="assets/img/slide/image1.jpeg" alt="hospital slide">
        <img src="assets/img/slide/image2.jpeg" alt="hospital slide">
        <img src="assets/img/slide/image3.jpeg" alt="hospital slide">
        <img src="assets/img/slide/image4.jpeg" alt="hospital slide">
        <img src="assets/img/slide/image5.jpeg" alt="hospital slide">
        <img src="assets/img/slide/image6.jpeg" alt="hospital slide">
        <img src="assets/img/slide/image7.jpeg" alt="hospital slide">
        <div class="hero-content">
            <h1>Welcome to MediQueue</h1>
            <p>Smart Hospital Queue Management System</p>
            <div class="btn-group">
                <a href="live_dashboard.php" class="btn btn-outline">Live Dashboard</a>
            </div>
        </div>
    </div>

    <!-- Services Section -->
    <div id="Services" class="hero-section">
        <h1>Services</h1>
        <div class="contener">
            <div class="content">
                <i class="fas fa-ticket-alt"></i>
                <h3>Smart Token System</h3>
                <p>Real-time queue management with priority for seniors and emergencies</p>
            </div>
            <div class="content">
                <i class="fas fa-chart-line"></i>
                <h3>Live Dashboard</h3>
                <p>Track queue status in real-time from anywhere</p>
            </div>
            <div class="content">
                <i class="fas fa-bell"></i>
                <h3>Instant Notifications</h3>
                <p>Get notified when your token is called</p>
            </div>
            <div class="content">
                <i class="fas fa-heart"></i>
                <h3>Senior Priority</h3>
                <p>Special queue for senior citizens (60+ years)</p>
            </div>
            <div class="content">
                <i class="fas fa-ambulance"></i>
                <h3>Emergency Handling</h3>
                <p>Emergency cases get immediate priority</p>
            </div>
            <div class="content">
                <i class="fas fa-notes-medical"></i>
                <h3>Digital Records</h3>
                <p>Maintain complete medical history digitally</p>
            </div>
        </div>
    </div>

    <!-- About Section -->
    <div id="about" class="hero-section" style="padding-top: 40px;">
        <div class="about-main-wrapper">
            <div class="about-grid">
                <div class="about-text-card">
                    <h1 style="font-size: 2.2rem; margin-bottom: 1.2rem; color: white;">About MediQueue</h1>
                    <p>
                        <strong>MediQueue</strong> is a smart hospital queue management system designed to reduce waiting time and improve patient experience. 
                        Our system allows patients to book tokens online, track their position in real-time, and receive instant notifications 
                        when their turn arrives.
                    </p>
                    <p>
                        We prioritize senior citizens and emergency cases to ensure faster and more efficient healthcare services. 
                        With digital records and live dashboards, MediQueue helps hospitals manage patient flow smoothly and effectively.
                    </p>
                    <p style="margin-top: 1rem;">
                        <i class="fas fa-graduation-cap"></i> This project is developed as a college <strong>mini-project</strong> 
                        by two dedicated students — Preetam (Backend with PHP) and Ali (UI with CSS).
                    </p>
                </div>
                <div class="feature-list-about">
                    <h3 style="color: #fff; margin-bottom: 1rem; font-size: 1.5rem;"><i class="fas fa-check-circle"></i> Key Benefits</h3>
                    <ul>
                        <li><i class="fas fa-clock"></i> ✔ Reduce waiting time</li>
                        <li><i class="fas fa-chart-simple"></i> ✔ Real-time tracking</li>
                        <li><i class="fas fa-shield-heart"></i> ✔ Priority for seniors & emergencies</li>
                        <li><i class="fas fa-file-medical"></i> ✔ Digital medical records</li>
                        <li><i class="fas fa-mobile-alt"></i> ✔ Instant mobile notifications</li>
                    </ul>
                </div>
            </div>

            <div class="team-showcase">
                <div class="team-title">
                    <i class="fas fa-users"></i> Meet the Creators
                </div>
                <div class="students-flex">
                    <div class="student-card-modern">
                        <div class="student-img-wrapper">
                            <img class="student-img-modern" src="https://randomuser.me/api/portraits/men/32.jpg" alt="Preetam">
                        </div>
                        <div class="student-info-modern">
                            <div class="student-name-modern">Preetam Kumar</div>
                            <div class="student-role-modern"><i class="fas fa-database"></i> Backend Developer (PHP)</div>
                            <div class="student-bio-modern">Robust queue logic, MySQL, priority engine & real-time sync.</div>
                            <div class="tech-tag"><i class="fab fa-php"></i> PHP · MySQL · API</div>
                        </div>
                    </div>
                    <div class="student-card-modern">
                        <div class="student-img-wrapper">
                            <img class="student-img-modern" src="https://randomuser.me/api/portraits/men/45.jpg" alt="Ali">
                        </div>
                        <div class="student-info-modern">
                            <div class="student-name-modern">Ali Khan</div>
                            <div class="student-role-modern"><i class="fas fa-paintbrush"></i> UI / Frontend Designer</div>
                            <div class="student-bio-modern">Responsive interfaces, live dashboards, patient-centric design.</div>
                            <div class="tech-tag"><i class="fab fa-css3-alt"></i> HTML5 · CSS3 · JS · Flex/Grid</div>
                        </div>
                    </div>
                </div>
                <p style="text-align: center; margin-top: 2rem; color: #f9f3cf; background: rgba(0,0,0,0.3); width: fit-content; margin-left: auto; margin-right: auto; padding: 0.5rem 1.8rem; border-radius: 60px;">
                    <i class="fas fa-code-branch"></i> College Mini-Project | BCA AI&DS | Innovation in Healthcare
                </p>
            </div>
        </div>
    </div>

    <!-- Contact Section -->
    <div id="contact" class="hero-section" style="padding-top: 20px;">
        <div class="contact-wrapper">
            <h1 style="text-align: center; font-size: 2.5rem; margin-bottom: 2rem; color: white;">
                <i class="fas fa-headset"></i> Contact Us
            </h1>
            <div class="contact-grid">
                <div class="contact-info-card">
                    <h3><i class="fas fa-map-pin"></i> Get in Touch</h3>
                    <p><i class="fas fa-hospital"></i> <strong>MediQueue Healthcare</strong><p> graphic era hill university</p>
                    <p><i class="fas fa-phone-alt"></i> +91 12345 67890</p>
                    <p><i class="fas fa-envelope"></i> support@mediqueue.com</p>
                    <p><i class="fas fa-clock"></i> 24/7 Emergency Support</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                <div class="contact-form-card">
                    <h3><i class="fas fa-paper-plane"></i> Send Message</h3>
                    <form action="" method="POST">
                        <div class="form-group">
                            <input type="text" name="name" placeholder="Your Name" required>
                        </div>
                        <div class="form-group">
                            <input type="email" name="email" placeholder="Email Address" required>
                        </div>
                        <div class="form-group">
                            <input type="text" name="subject" placeholder="Subject">
                        </div>
                        <div class="form-group">
                            <textarea name="message" rows="4" placeholder="Your Message..." required></textarea>
                        </div>
                        <button type="submit" class="submit-btn"><i class="fas fa-paper-plane"></i> Send Message</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer style="background: rgba(0,0,0,0.8); padding: 2rem; text-align: center; color: white;">
        <p>&copy; <?php echo date('Y'); ?> MediQueue - Hospital Queue Management System</p>
        <p style="margin-top: 0.5rem; font-size: 0.875rem;">
            <i class="fas fa-heart" style="color: #e53e3e;"></i> By <b>PREETAM KUMAR</b> and <b>ALI KHAN</b> from BCA AI&DS
        </p>
    </footer>
    <script src="assets/js/chatbot.js"></script>
</body>

</html>
