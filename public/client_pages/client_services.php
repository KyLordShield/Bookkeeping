<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Our Services</title>
    <link rel="stylesheet" href="../assets/css_file/client_pages.css">
    <link rel="stylesheet" href="../assets/css_file/navigation_bar.css">
    <style>
       
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar will be inserted here via PHP -->
         <?php include '../partials/navigation_bar.php'; ?>
        
        <div class="main-content">
            <div class="header">
                <h1>Our Services</h1>
                <p>Browse and select the services you need for your business</p>
            </div>

            <div class="filter-buttons">
                <button class="filter-btn active" onclick="filterServices('all')">All</button>
                <button class="filter-btn" onclick="filterServices('legal')">Legal</button>
                <button class="filter-btn" onclick="filterServices('finance')">Finance</button>
                <button class="filter-btn" onclick="filterServices('consulting')">Consulting</button>
            </div>

            <div class="services-grid">
                <!-- Service Card 1 -->
                <div class="service-card" data-category="legal">
                    <div class="service-icon">⚖️</div>
                    <div class="service-card-title">Business Registration</div>
                    <div class="service-description">Complete business registration and incorporation services with legal compliance</div>
                    <div class="service-price-section">
                        <div class="service-price">PRICE HERE</div>
                        <button class="select-service-btn" onclick="openModal('Business Registration', 'Legal', '$???')">Select Service</button>
                    </div>
                </div>

                <!-- Service Card 2 -->
                <div class="service-card" data-category="legal">
                    <div class="service-icon">⚖️</div>
                    <div class="service-card-title">Permit & Licensing</div>
                    <div class="service-description">Assistance with obtaining necessary business permits and licenses</div>
                    <div class="service-price-section">
                        <div class="service-price">PRICE HERE</div>
                        <button class="select-service-btn" onclick="openModal('Permit & Licensing', 'Legal', '$???')">Select Service</button>
                    </div>
                </div>

                <!-- Service Card 3 -->
                <div class="service-card" data-category="finance">
                    <div class="service-icon">📊</div>
                    <div class="service-card-title">Business Registration</div>
                    <div class="service-description">Complete business registration and incorporation services with legal compliance</div>
                    <div class="service-price-section">
                        <div class="service-price">PRICE HERE</div>
                        <button class="select-service-btn" onclick="openModal('Business Registration', 'Finance', '$???')">Select Service</button>
                    </div>
                </div>

                <!-- Service Card 4 -->
                <div class="service-card" data-category="consulting">
                    <div class="service-icon">📋</div>
                    <div class="service-card-title">Business Registration</div>
                    <div class="service-description">Complete business registration and incorporation services with legal compliance</div>
                    <div class="service-price-section">
                        <div class="service-price">PRICE HERE</div>
                        <button class="select-service-btn" onclick="openModal('Business Registration', 'Consulting', '$???')">Select Service</button>
                    </div>
                </div>

                <!-- Service Card 5 -->
                <div class="service-card" data-category="consulting">
                    <div class="service-icon">👥</div>
                    <div class="service-card-title">Business Registration</div>
                    <div class="service-description">Complete business registration and incorporation services with legal compliance</div>
                    <div class="service-price-section">
                        <div class="service-price">PRICE HERE</div>
                        <button class="select-service-btn" onclick="openModal('Business Registration', 'Consulting', '$???')">Select Service</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div id="serviceModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title" id="modalTitle">Business Registration</div>
                <div class="modal-category" id="modalCategory">Legal</div>
            </div>

            <div class="modal-description">
                Complete business registration and incorporation services with legal compliance
            </div>

            <div class="modal-price" id="modalPrice">$???</div>

            <div class="section-title">Document Submission Method</div>
            <div class="section-subtitle">Choose how you'd like to submit your documents</div>

            <div class="delivery-options">
                <div class="delivery-option" onclick="selectDelivery('online')">
                    <div style="font-size: 24px;">💻</div>
                    <div class="delivery-option-title">Online Upload</div>
                    <div class="delivery-option-desc">Upload documents digitally</div>
                </div>
                <div class="delivery-option" onclick="selectDelivery('onsite')">
                    <div style="font-size: 24px;">🏢</div>
                    <div class="delivery-option-title">On-Site Delivery</div>
                    <div class="delivery-option-desc">Bring documents to office</div>
                </div>
                <div class="delivery-option" onclick="selectDelivery('pickup')">
                    <div style="font-size: 24px;">🚗</div>
                    <div class="delivery-option-title">Staff Pick up</div>
                    <div class="delivery-option-desc">We'll collect from you</div>
                </div>
            </div>

            <!-- On-Site Delivery Instructions -->
            <div id="onsiteInstructions" class="delivery-instructions">
                <div class="delivery-instructions-title">On-Site Delivery Instructions</div>
                <ul>
                    <li>Bring all required documents listed below</li>
                    <li>Office Hours: Monday - Friday, 9:00 AM - 5:00 PM</li>
                    <li>Address will be sent to your email after submission</li>
                    <li>Please bring valid ID for verification</li>
                </ul>
                <div style="margin-top: 15px; font-weight: bold; font-size: 12px;">Required Documents:</div>
                <ul>
                    <li>Bank Statements</li>
                    <li>Receipts and Invoices</li>
                    <li>Payroll Information</li>
                    <li>Previous Financial Records</li>
                </ul>
            </div>

            <!-- Staff Pickup Instructions -->
            <div id="pickupInstructions" class="delivery-instructions">
                <div class="delivery-instructions-title">Staff Pickup Service</div>
                <ul>
                    <li>Our staff will contact you within 24 hours</li>
                    <li>Schedule a convenient pickup time within 1 week</li>
                    <li>Prepare all documents in a sealed envelope</li>
                    <li>Free pickup service within city limits</li>
                </ul>
                <div style="margin-top: 15px; font-weight: bold; font-size: 12px;">Documents to Prepare:</div>
                <ul>
                    <li>Bank Statements</li>
                    <li>Receipts and Invoices</li>
                    <li>Payroll Information</li>
                    <li>Previous Financial Records</li>
                </ul>
            </div>

            <!-- Online Upload Section -->
            <div id="onlineUploadSection" class="required-docs-section" style="display: none;">
                <div class="section-title">Required Documents</div>
                <div class="section-subtitle">Please upload all required documents to proceed with your application</div>

                <div class="document-item">
                    <div class="document-name">Business plan Document</div>
                    <button class="upload-btn">Upload</button>
                </div>
            </div>

            <div class="modal-actions">
                <button class="cancel-btn" onclick="closeModal()">Cancel</button>
                <button class="submit-btn">Submit Application</button>
            </div>
        </div>
    </div>

    <script>
        let currentDeliveryMethod = '';

        function filterServices(category) {
            const cards = document.querySelectorAll('.service-card');
            const buttons = document.querySelectorAll('.filter-btn');
            
            buttons.forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            cards.forEach(card => {
                if (category === 'all' || card.dataset.category === category) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        function openModal(title, category, price) {
            document.getElementById('modalTitle').textContent = title;
            document.getElementById('modalCategory').textContent = category;
            document.getElementById('modalPrice').textContent = price;
            document.getElementById('serviceModal').classList.add('active');
            currentDeliveryMethod = '';
            
            // Reset all selections
            document.querySelectorAll('.delivery-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            document.querySelectorAll('.delivery-instructions').forEach(inst => {
                inst.classList.remove('active');
            });
            document.getElementById('onlineUploadSection').style.display = 'none';
        }

        function closeModal() {
            document.getElementById('serviceModal').classList.remove('active');
        }

        function selectDelivery(method) {
            currentDeliveryMethod = method;
            
            // Remove all selections
            document.querySelectorAll('.delivery-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            document.querySelectorAll('.delivery-instructions').forEach(inst => {
                inst.classList.remove('active');
            });
            
            // Add selection to clicked option
            event.currentTarget.classList.add('selected');
            
            // Show appropriate content
            if (method === 'online') {
                document.getElementById('onlineUploadSection').style.display = 'block';
            } else if (method === 'onsite') {
                document.getElementById('onsiteInstructions').classList.add('active');
                document.getElementById('onlineUploadSection').style.display = 'none';
            } else if (method === 'pickup') {
                document.getElementById('pickupInstructions').classList.add('active');
                document.getElementById('onlineUploadSection').style.display = 'none';
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('serviceModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>