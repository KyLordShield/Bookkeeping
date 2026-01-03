<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Our Services</title>
    <link rel="stylesheet" href="../assets/css_file/client_pages.css">
    <link rel="stylesheet" href="../assets/css_file/navigation_bar.css">
    <style>
        /* Optional: Add any page-specific overrides here if needed */
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar Navigation -->
        <?php include '../partials/navigation_bar.php'; ?>
        
        <div class="main-content">
            <div class="header">
                <h1>Our Services</h1>
                <p>Browse and select the services you need for your business</p>
            </div>

           

            <div class="services-grid">
                <!-- Example Service Cards -->
                <div class="service-card" data-category="finance">
                    <div class="service-icon">📊</div>
                    <div class="service-card-title">Bookkeeping – Single Proprietor</div>
                    <div class="service-description">Comprehensive bookkeeping services tailored for sole proprietors</div>
                    <div class="service-price-section">
                        
                        <button class="select-service-btn" onclick="openMeetingModal('Bookkeeping – Corporation')">Request Meeting</button>
                    </div>
                </div>


                <div class="service-card" data-category="finance">
                    <div class="service-icon">📊</div>
                    <div class="service-card-title">Bookkeeping – Corporation</div>
                    <div class="service-description">Full-scale corporate bookkeeping and financial management</div>
                    <div class="service-price-section">
                        <button class="select-service-btn" onclick="openMeetingModal('Bookkeeping – Corporation')">Request Meeting</button>
                    </div>
                </div>

                <div class="service-card" data-category="finance">
                    <div class="service-icon">📊</div>
                    <div class="service-card-title">Human Resources (HR) Services</div>
                    <div class="service-description">Complete HR solutions for your business needs</div>
                    <div class="service-price-section">
                        <button class="select-service-btn" onclick="openMeetingModal('Bookkeeping – Corporation')">Request Meeting</button>
                    </div>
                </div>

                <div class="service-card" data-category="finance">
                    <div class="service-icon">📊</div>
                    <div class="service-card-title">Business Registration (One-Time)</div>
                    <div class="service-description">Hassle-free business registration and setup services</div>
                    <div class="service-price-section">
                        <button class="select-service-btn" onclick="openMeetingModal('Bookkeeping – Corporation')">Request Meeting</button>
                    </div>
                </div>

                <div class="service-card" data-category="finance">
                    <div class="service-icon">📊</div>
                    <div class="service-card-title">Renewals & Compliance</div>
                    <div class="service-description">Stay compliant with timely renewals and regulatory updates</div>
                    <div class="service-price-section">
                        <button class="select-service-btn" onclick="openMeetingModal('Bookkeeping – Corporation')">Request Meeting</button>
                    </div>
                </div>

                <!-- Add more service cards as needed -->
            </div>
        </div>
    </div>

    <!-- NEW MODAL: Request Meeting (Matches your screenshot exactly) -->
    <div id="meetingModal" class="modal-overlay">
        <div class="modal-window">
            <div class="modal-header">
                <h2>Request Meeting</h2>
                <span class="close-btn" onclick="closeMeetingModal()">&times;</span>
            </div>

            <div class="modal-body">
                <div class="service-name">
                    Service: <strong id="modalServiceName">Bookkeeping – Corporation</strong>
                </div>

                <label for="preferredDate">Preferred Date</label>
                <input type="date" id="preferredDate" name="preferredDate" placeholder="dd -- yyyy" required>

                <label for="preferredTime">Preferred Time</label>
                <input type="time" id="preferredTime" name="preferredTime" required>
                <p class="business-hours">Business hours: 9:00 AM - 6:00 PM</p>

                <label for="additionalNotes">Additional Notes?</label>
                <textarea id="additionalNotes" placeholder="Any specific requirements or questions..." rows="4"></textarea>
            </div>

            <div class="modal-footer">
                <button class="submit-request-btn" onclick="submitMeetingRequest()">Submit Request</button>
            </div>
        </div>
    </div>

    <script>
        // Filter services (unchanged)
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

        // Open the new Request Meeting modal
        function openMeetingModal(serviceName) {
            document.getElementById('modalServiceName').textContent = serviceName;
            document.getElementById('meetingModal').classList.add('active');
            
            // Optional: reset form
            document.getElementById('preferredDate').value = '';
            document.getElementById('preferredTime').value = '';
            document.getElementById('additionalNotes').value = '';
        }

        // Close modal
        function closeMeetingModal() {
            document.getElementById('meetingModal').classList.remove('active');
        }

        // Submit handler (you can connect this to your backend later)
        function submitMeetingRequest() {
            const service = document.getElementById('modalServiceName').textContent;
            const date = document.getElementById('preferredDate').value;
            const time = document.getElementById('preferredTime').value;
            const notes = document.getElementById('additionalNotes').value;

            if (!date || !time) {
                alert('Please select both date and time.');
                return;
            }

            alert(`Meeting request submitted!\nService: ${service}\nDate: ${date}\nTime: ${time}\nNotes: ${notes || 'None'}`);
            closeMeetingModal();
            // Later: send via AJAX to your PHP backend
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('meetingModal');
            if (event.target === modal) {
                closeMeetingModal();
            }
        }
    </script>

    <!-- Inline CSS for the new modal (add this to your client_pages.css later if preferred) -->
    <style>
       
    </style>
</body>
</html>