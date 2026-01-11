<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approvative BDP Consultancy</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
        }

        /* Navigation */
        nav {
            background-color: #000;
            padding: 1rem 3rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .logo {
    height: 90px;            /* bigger logo height */
    display: flex;
    align-items: center;
}

.logo img {
    height: 100%;
    width: auto;             /* keeps aspect ratio */
    max-width: 260px;        /* allows wider logo */
}


        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .nav-links a {
            color: #fff;
            text-decoration: none;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: color 0.3s;
        }

        .nav-links a:hover {
            color: #7D1C19;
        }

        .btn-login, .btn-signup {
            padding: 0.5rem 1.5rem;
            border: 2px solid #fff;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-signup {
            background-color: #7D1C19;
            border-color: #7D1C19;
        }

        .btn-signup:hover {
            background-color: #5a1412;
        }

        /* Hero Section */
        .hero {
            background-color: #7D1C19;
            padding: 7rem 5rem;    
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 650px;
        }

        .hero-image {
    flex: 1.2;               /* gives image more space */
    display: flex;
    justify-content: center;
}

.hero-image img {
    width: 100%;
    max-width: 650px;        /* MUCH wider hero image */
    height: auto;
    border-radius: 12px;
    box-shadow: 0 15px 40px rgba(0,0,0,0.35);
}


        .hero-content {
            flex: 1;
            color: #fff;
            padding: 2rem;
        }

        .hero-content h1 {
            font-size: 3rem;
            letter-spacing: 8px;
            margin-bottom: 1rem;
        }

        .hero-content h2 {
            font-size: 1.8rem;
            letter-spacing: 4px;
            margin-bottom: 1rem;
        }

        .hero-content p {
            font-size: 1rem;
            line-height: 1.8;
            margin-bottom: 2rem;
            max-width: 600px;
        }

        .btn-get-started {
            background-color: #fff;
            color: #7D1C19;
            padding: 1rem 2.5rem;
            border: none;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
            text-transform: uppercase;
            transition: all 0.3s;
            font-size: 1rem;
        }

        .btn-get-started:hover {
            background-color: #f0f0f0;
            transform: translateY(-2px);
        }

        /* Services Section */
        .services {
            background-color: #e0e0e0;
            padding: 5rem 3rem;
        }

        .services h2 {
            text-align: center;
            font-size: 2.5rem;
            letter-spacing: 4px;
            margin-bottom: 3rem;
        }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
            max-width: 1400px;
            margin-left: auto;
            margin-right: auto;
        }

        .service-card {
            background-color: #fff;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .service-image {
            width: 100%;
            height: 180px;
            border-radius: 5px;
            margin-bottom: 1rem;
            overflow: hidden;
        }

        .service-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .service-card h3 {
            font-size: 1rem;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            min-height: 50px;
        }

        .service-card p {
            font-size: 0.85rem;
            line-height: 1.5;
            margin-bottom: 1rem;
            color: #666;
        }

        .btn-avail {
            background-color: #7D1C19;
            color: #fff;
            padding: 0.6rem 1.5rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-transform: uppercase;
            font-size: 0.8rem;
            transition: all 0.3s;
            width: 100%;
        }

        .btn-avail:hover {
            background-color: #5a1412;
        }

        .services-tagline {
            text-align: center;
            font-size: 1.8rem;
            font-style: italic;
            margin-top: 3rem;
        }

        /* About Section */
        .about {
            background-color: #7D1C19;
            padding: 5rem 3rem;
        }

        .about h2 {
            text-align: center;
            color: #fff;
            font-size: 2.5rem;
            letter-spacing: 4px;
            margin-bottom: 3rem;
        }

        .about-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }

        .about-card {
            background-color: #fff;
            padding: 2.5rem;
            border-radius: 10px;
        }

        .about-card h3 {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .about-icon {
            width: 50px;
            height: 50px;
        }

        .about-card p {
            font-size: 1rem;
            line-height: 1.8;
            color: #333;
        }

        /* Contact Section */
        .contact {
            background-color: #c0c0c0;
            padding: 4rem 3rem;
        }

        .contact h2 {
            text-align: center;
            font-size: 2.5rem;
            letter-spacing: 4px;
            margin-bottom: 3rem;
        }

        .contact-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 3rem;
            text-align: center;
            max-width: 1200px;
            margin: 0 auto;
        }

        .contact-item {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .contact-icon {
            width: 80px;
            height: 80px;
            margin-bottom: 1.5rem;
        }

        .contact-item h3 {
            font-size: 1.3rem;
            text-transform: uppercase;
            margin-bottom: 1rem;
        }

        .contact-item p {
            font-size: 1.1rem;
            color: #333;
            line-height: 1.6;
        }

        /* Footer */
        footer {
            background-color: #000;
            color: #fff;
            padding: 3rem;
            display: flex;
            justify-content: space-between;
            align-items: start;
        }

        .footer-logo {
            height: 60px;
            margin-bottom: 1rem;
        }

        .footer-section h3 {
            color: #7D1C19;
            margin-bottom: 1rem;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .footer-section ul {
            list-style: none;
        }

        .footer-section ul li {
            margin-bottom: 0.5rem;
            font-size: 0.85rem;
        }

        .footer-bottom {
            text-align: center;
            padding: 1rem;
            background-color: #000;
            color: #999;
            font-size: 0.8rem;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.7);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background-color: #fff;
            padding: 3rem;
            border-radius: 15px;
            max-width: 500px;
            width: 90%;
            position: relative;
            box-shadow: 0 10px 50px rgba(0,0,0,0.3);
        }

        .modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 2rem;
            cursor: pointer;
            color: #999;
            transition: color 0.3s;
        }

        .modal-close:hover {
            color: #7D1C19;
        }

        .modal-content h2 {
            color: #7D1C19;
            margin-bottom: 1rem;
            text-align: center;
        }

        .modal-content p {
            text-align: center;
            margin-bottom: 2rem;
            color: #666;
        }

        .modal-buttons {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .modal-btn {
            padding: 1rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: bold;
            text-transform: uppercase;
            transition: all 0.3s;
        }

        .modal-btn.login {
            background-color: #7D1C19;
            color: #fff;
        }

        .modal-btn.login:hover {
            background-color: #5a1412;
        }

        .modal-btn.register {
            background-color: #fff;
            color: #7D1C19;
            border: 2px solid #7D1C19;
        }

        .modal-btn.register:hover {
            background-color: #f9f9f9;
        }

        .modal-contact {
            border-top: 2px solid #e0e0e0;
            padding-top: 1.5rem;
        }

        .modal-contact h3 {
            color: #333;
            margin-bottom: 1rem;
            font-size: 1rem;
        }

        .contact-methods {
            display: flex;
            flex-direction: column;
            gap: 0.8rem;
        }

        .contact-method {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.8rem;
            background-color: #f5f5f5;
            border-radius: 8px;
            transition: background-color 0.3s;
        }

        .contact-method:hover {
            background-color: #e0e0e0;
        }

        .contact-method strong {
            color: #7D1C19;
            min-width: 80px;
        }

        .contact-method a {
            color: #333;
            text-decoration: none;
        }

        .contact-method a:hover {
            color: #7D1C19;
        }

        @media (max-width: 768px) {
            .hero {
                flex-direction: column;
                text-align: center;
            }

            .hero-content h1 {
                font-size: 2rem;
            }

            footer {
                flex-direction: column;
                gap: 2rem;
            }
        }
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