<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approvative BDP Consultancy</title>
    <link rel="stylesheet" href="assets/css_file/landing_page.css">
    <style>
        
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav>
        <div class="logo">
            <img src="../public/assets/images/Logo.png" alt="Approvative Logo">
        </div>
        <div class="nav-links">
            <a href="#home">HOME</a>
            <a href="#services">SERVICES</a>
            <a href="#about">ABOUT US</a>
            <a href="login_page.php" class="btn-login">LOG IN</a>
            <a href="register_page.php" class="btn-signup">SIGN UP</a>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero">
        <div class="hero-image">
            <img src="../public/assets/images/Landing.png" alt="Business Documents">
        </div>
        <div class="hero-content">
            <h1>APPROVATIVE</h1>
            <h2>BUSINESS DOCUMENTS</h2>
            <p><strong>Processing and Consultancy</strong></p>
            <p>Provides hassle-free government permit and license applications, facilitating compliance, and helping businesses save time and money. Our services include business registration, audit design, and ensuring smooth operations.</p>
            <button class="btn-get-started" onclick="openModal()">GET STARTED</button>
        </div>
    </section>

    <!-- Services Section -->
    <section id="services" class="services">
        <h2>OUR SERVICES</h2>
        <div class="services-grid">
            <div class="service-card">
                <div class="service-image">
                    <img src="https://images.unsplash.com/photo-1554224155-8d04cb21cd6c?w=400&h=300&fit=crop" alt="Bookkeeping Single Proprietor">
                </div>
                <h3>Bookkeeping<br>Single Proprietor</h3>
                <p>Comprehensive bookkeeping services tailored for sole proprietors.</p>
                <button class="btn-avail" onclick="openModal()">AVAIL SERVICE</button>
            </div>
            <div class="service-card">
                <div class="service-image">
                    <img src="https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=400&h=300&fit=crop" alt="Bookkeeping Corporation">
                </div>
                <h3>Bookkeeping<br>Corporation</h3>
                <p>Comprehensive bookkeeping services tailored for corporations.</p>
                <button class="btn-avail" onclick="openModal()">AVAIL SERVICE</button>
            </div>
            <div class="service-card">
                <div class="service-image">
                    <img src="https://images.unsplash.com/photo-1521791136064-7986c2920216?w=400&h=300&fit=crop" alt="Human Resource Services">
                </div>
                <h3>Human Resource<br>(HR) Services</h3>
                <p>Complete HR solutions to manage your workforce effectively.</p>
                <button class="btn-avail" onclick="openModal()">AVAIL SERVICE</button>
            </div>
            <div class="service-card">
                <div class="service-image">
                    <img src="https://images.unsplash.com/photo-1450101499163-c8848c66ca85?w=400&h=300&fit=crop" alt="Business Registration">
                </div>
                <h3>Business Registration<br>(One-Time)</h3>
                <p>Hassle-free business registration services for new ventures.</p>
                <button class="btn-avail" onclick="openModal()">AVAIL SERVICE</button>
            </div>
            <div class="service-card">
                <div class="service-image">
                    <img src="https://images.unsplash.com/photo-1589829085413-56de8ae18c73?w=400&h=300&fit=crop" alt="Renewals & Compliance">
                </div>
                <h3>Renewals &<br>Compliance</h3>
                <p>Ensure your business stays compliant with timely renewals.</p>
                <button class="btn-avail" onclick="openModal()">AVAIL SERVICE</button>
            </div>
        </div>
        <p class="services-tagline">We handle the paperwork so you can focus on your business.</p>
    </section>

    <!-- About Section -->
    <section id="about" class="about">
        <h2>ABOUT US</h2>
        <div class="about-content">
            <div class="about-card">
                <h3>
                    <img src="../public/assets/images/WhoWeAre.png" alt="Who We Are" class="about-icon">
                    Who We Are
                </h3>
                <p>APPROVATIVE is a trusted business support and consultancy firm dedicated to helping businesses navigate government requirements. We offer business registration services, business registration, and compliance solutions tailored to your needs.</p>
            </div>
            <div class="about-card">
                <h3>
                    <img src="../public/assets/images/OurMission.png" alt="Our Mission" class="about-icon">
                    Our Mission
                </h3>
                <p>We believe business owners should focus on growing their business, not getting bogged down by paperwork. Our mission is to handle all the complexities so you can concentrate on what matters most.</p>
            </div>
        </div>

        <!-- Contact Section -->
        <div class="contact">
            <h2>CONTACT US</h2>
            <div class="contact-grid">
                <div class="contact-item">
                    <img src="../public/assets/images/EmailLogo.png" alt="Email" class="contact-icon">
                    <h3>Email</h3>
                    <p>abdjeravelimar@gmail.com</p>
                </div>
                <div class="contact-item">
                    <img src="../public/assets/images/PhoneLogo.png" alt="Phone" class="contact-icon">
                    <h3>Call Us</h3>
                    <p>Office Hours: Mon-Fri 8AM-6PM<br>+63 9090909090</p>
                </div>
                <div class="contact-item">
                    <img src="../public/assets/images/MapLogo.png" alt="Location" class="contact-icon">
                    <h3>Visit Us</h3>
                    <p>LGC prenton house, Cebu City</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="footer-section">
            <img src="../public/assets/images/Logo.png" alt="Approvative Logo" class="footer-logo">
            <p style="margin-top: 0.5rem; font-size: 0.85rem;">Approvative BDP Consultancy</p>
        </div>
        <div class="footer-section">
            <h3>SERVICES</h3>
            <ul>
                <li>• Bookkeeping - Single Proprietor</li>
                <li>• Bookkeeping - Corporation</li>
                <li>• Human Resource (HR) Services</li>
                <li>• Business Registration (One-Time)</li>
                <li>• Renewals & Compliance</li>
            </ul>
        </div>
        <div class="footer-section">
            <h3>CONTACT INFO</h3>
            <ul>
                <li>• Email: abdjeravelimar@gmail.com</li>
                <li>• Phone: +63 9090909090</li>
                <li>• Address: LGC prenton house, Cebu City</li>
            </ul>
        </div>
    </footer>

    <div class="footer-bottom">
        © 2025 APPROVATIVE. All rights reserved.
    </div>

    <!-- Modal -->
    <div class="modal" id="availModal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeModal()">&times;</span>
            <h2>Avail Our Services</h2>
            <p>Choose an option to continue:</p>
            
            <div class="modal-buttons">
                <button class="modal-btn login" onclick="loginAction()">Login to Avail</button>
                <button class="modal-btn register" onclick="registerAction()">Register New Account</button>
            </div>

            <div class="modal-contact">
                <h3>Or contact us directly:</h3>
                <div class="contact-methods">
                    <div class="contact-method">
                        <strong>📧 Email:</strong>
                        <a href="mailto:abdjeravelimar@gmail.com">abdjeravelimar@gmail.com</a>
                    </div>
                    <div class="contact-method">
                        <strong>📞 Phone:</strong>
                        <a href="tel:+639090909090">+63 9090909090</a>
                    </div>
                    <div class="contact-method">
                        <strong>📱 Facebook:</strong>
                        <a href="https://facebook.com" target="_blank">Message us on Facebook</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openModal() {
            document.getElementById('availModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('availModal').classList.remove('active');
        }

        function loginAction() {
            window.location.href = 'login_page.php';
        }

        function registerAction() {
            window.location.href = 'register_page.php';
        }

        // Close modal when clicking outside
        window.onclick = function(e) {
            const modal = document.getElementById('availModal');
            if (e.target === modal) {
                closeModal();
            }
        }

        // Smooth scrolling for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            });
        });
    </script>
</body>
</html>