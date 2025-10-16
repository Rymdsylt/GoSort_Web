<?php
//TO UPDATE BACKEND AND STATIC TEXTS
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <style>
        :root {
            --primary-green: #2e7d32;
            --light-green: #66bb6a;
            --dark-gray: #333;
            --medium-gray: #6b7280;
            --light-gray: #f9fafb;
        }

        body {
            background-color: var(--light-gray);
            font-family: 'Poppins', sans-serif;
        }

        .content-area {
            padding: 0 0 2rem;
            overflow-y: auto;
        }

        .support-card {
            background-color: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .section-header {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--dark-gray);
            margin-bottom: 1.2rem;
            border-bottom: 2px solid var(--primary-green);
            padding-bottom: 6px;
        }

        .faq-item {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            margin-bottom: 10px;
            background-color: #fdfdfd;
        }

        .faq-item button {
            width: 100%;
            text-align: left;
            padding: 12px 16px;
            background: none;
            border: none;
            font-weight: 600;
            color: var(--dark-gray);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .faq-item button:hover {
            color: var(--primary-green);
        }

        .faq-body {
            padding: 0 16px 12px 16px;
            color: var(--medium-gray);
            font-size: 0.95rem;
        }

        .contact-info i {
            color: var(--primary-green);
            margin-right: 10px;
        }

        .contact-info p {
            margin-bottom: 6px;
            color: var(--dark-gray);
        }

        .feedback-box {
            border-radius: 12px;
            border: 1px solid #ddd;
            padding: 1rem;
            background: #fafafa;
        }

        .btn-custom {
            background-color: var(--primary-green);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 8px 16px;
            font-weight: 600;
            transition: background 0.3s ease;
        }

        .btn-custom:hover {
            background-color: #1f3a13;
        }

    </style>
</head>
<body>
<div class="content-area">
    <div class="support-card">
        <div class="section-header">Help & Support</div>
        <p class="text-secondary">
            Welcome to the GoSort Help & Support Center. Here you can find answers to common questions,
            troubleshooting steps, and ways to contact our support team for assistance with your GoSort device or system.
        </p>
    </div>

    <!-- Quick Start Guide -->
    <div class="support-card">
        <div class="section-header">Quick Start Guide</div>
        <ul class="list-group list-group-flush">
            <li class="list-group-item"><strong>Step 1:</strong> Power on the GoSort device and ensure it’s connected to electricity.</li>
            <li class="list-group-item"><strong>Step 2:</strong> Connect to Wi-Fi or ensure your system is linked to the GoSort dashboard.</li>
            <li class="list-group-item"><strong>Step 3:</strong> Place waste items one at a time on the sorting tray.</li>
            <li class="list-group-item"><strong>Step 4:</strong> View sorting results on the dashboard or indicator panel.</li>
            <li class="list-group-item"><strong>Step 5:</strong> Empty bins regularly to maintain efficiency.</li>
        </ul>
    </div>

    <!-- FAQs -->
    <div class="support-card">
        <div class="section-header">Frequently Asked Questions</div>

        <div class="faq-item">
            <button data-bs-toggle="collapse" data-bs-target="#faq1">
                Why is my GoSort device not detecting the waste?
                <i class="bi bi-chevron-down"></i>
            </button>
            <div id="faq1" class="collapse faq-body">
                Check if the sensor lens is clean and unobstructed. Wipe gently with a dry cloth. Also, make sure the device is powered and connected.
            </div>
        </div>

        <div class="faq-item">
            <button data-bs-toggle="collapse" data-bs-target="#faq2">
                What kinds of materials can GoSort identify?
                <i class="bi bi-chevron-down"></i>
            </button>
            <div id="faq2" class="collapse faq-body">
                GoSort can automatically identify recyclable (plastic, metal, paper) and non-recyclable waste based on sensor calibration.
            </div>
        </div>

        <div class="faq-item">
            <button data-bs-toggle="collapse" data-bs-target="#faq3">
                How often should I clean the device?
                <i class="bi bi-chevron-down"></i>
            </button>
            <div id="faq3" class="collapse faq-body">
                It’s recommended to clean the sorting tray and bins once a week to ensure smooth sensor detection and operation.
            </div>
        </div>

        <div class="faq-item">
            <button data-bs-toggle="collapse" data-bs-target="#faq4">
                How do I update the GoSort software?
                <i class="bi bi-chevron-down"></i>
            </button>
            <div id="faq4" class="collapse faq-body">
                Updates are handled automatically when the system connects to the internet. You can also check manually in Settings &gt; System Update.
            </div>
        </div>
    </div>

    <!-- Troubleshooting -->
    <div class="support-card">
        <div class="section-header">Troubleshooting Guide</div>
        <table class="table table-bordered align-middle">
            <thead class="table-success">
                <tr>
                    <th>Issue</th>
                    <th>Possible Cause</th>
                    <th>Solution</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Device not powering on</td>
                    <td>Loose power cable or faulty outlet</td>
                    <td>Check plug and outlet connection. Try another socket.</td>
                </tr>
                <tr>
                    <td>Sensor misreads item</td>
                    <td>Dirty or blocked sensor</td>
                    <td>Clean the sensor lens with a soft cloth.</td>
                </tr>
                <tr>
                    <td>Bins not moving</td>
                    <td>Internal obstruction</td>
                    <td>Turn off device, clear blockage, and restart.</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Contact Support -->
    <div class="support-card">
        <div class="section-header">Contact Technical Support</div>
        <div class="contact-info">
            <p><i class="bi bi-envelope-fill"></i> Email: gosort.support@gmail.com</p>
            <p><i class="bi bi-telephone-fill"></i> Phone: (02) 1234 5678</p>
            <p><i class="bi bi-clock-fill"></i> Hours: Monday–Friday, 8:00 AM – 5:00 PM</p>
        </div>
    </div>

    <!-- Feedback Form -->
    <div class="support-card">
        <div class="section-header">Send Feedback</div>
        <div class="feedback-box">
            <form id="feedbackForm">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Your Name</label>
                    <input type="text" class="form-control" placeholder="Enter your name">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Message</label>
                    <textarea class="form-control" rows="3" placeholder="Share your thoughts or report an issue..."></textarea>
                </div>
                <button type="button" class="btn-custom"><i class="bi bi-send"></i> Submit</button>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
