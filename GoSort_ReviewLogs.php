<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoSort - Review Logs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        /* Root Variables */
        :root {
            --primary-green: #274a17ff;
            --light-green: #7AF146;
            --dark-gray: #1f2937;
            --medium-gray: #6b7280;
            --light-gray: #f3f4f6;
            --border-color: #368137;
        }

        /* General Styles */
        body {
            background-color: #F3F3EF !important;
            font-family: 'Inter', sans-serif !important;
            color: var(--dark-gray);
            padding: 20px;
        }

        /* Header Section */
        .monitor-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: transparent;
            border: none;
            color: var(--dark-gray);
            text-decoration: none;
            font-size: 1.5rem;
            margin-right: 1rem;
            transition: all 0.2s ease;
        }

        .back-button:hover {
            color: var(--primary-green);
        }

        /* Time Display */
        .time-display {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: white;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            border: 2px solid var(--border-color);
        }

        .time-display .time {
            font-size: 1rem;
            font-weight: 700;
            color: var(--primary-green);
            margin: 0;
        }

        .time-display .date {
            font-size: 0.875rem;
            color: var(--medium-gray);
            margin-top: 0.25rem;
        }

        /* Review Card */
        .review-card {
            background: white;
            border: 2px solid var(--border-color);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            position: relative;
            overflow: hidden;
            max-width: 900px;
            margin: 0 auto;
        }

        .review-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 6px;
            height: 100%;
            background: linear-gradient(to bottom, var(--light-green), var(--primary-green));
        }

        /* Counter */
        .counter-display {
            text-align: center;
            font-size: 0.875rem;
            color: var(--medium-gray);
            margin-bottom: 1rem;
            font-weight: 500;
        }

        /* Category Title */
        .category-title {
            font-size: 2.5rem;
            font-weight: 900;
            color: var(--dark-gray);
            margin: 0 0 0.5rem 0;
            letter-spacing: -0.02em;
            text-align: center;
        }

        .detection-subtitle {
            font-size: 1rem;
            color: var(--medium-gray);
            margin-bottom: 2rem;
            font-weight: 500;
            text-align: center;
        }

        .detected-item {
            display: inline-block;
            font-size: 1rem;
            font-weight: 600;
            color: var(--primary-green);
            background: #efffe8ff;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            margin-left: 0.5rem;
        }

        /* Image Display */
        .image-container {
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 400px;
            margin-bottom: 2rem;
        }

        .image-display {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 400px;
            background: #f9fafb;
            border-radius: 16px;
            border: 2px dashed #d1d5db;
            position: relative;
            overflow: hidden;
            width: 100%;
        }

        .image-display img {
            max-width: 100%;
            max-height: 450px;
            object-fit: contain;
            border-radius: 12px;
        }

        .placeholder-image {
            text-align: center;
            color: var(--medium-gray);
        }

        .placeholder-image i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }

        .placeholder-image p {
            font-size: 1rem;
            margin: 0;
        }

        /* Navigation Arrows */
        .nav-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 50px;
            height: 50px;
            background: white;
            border: 2px solid var(--border-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 10;
            font-size: 1.5rem;
            color: var(--dark-gray);
        }

        .nav-arrow:hover {
            background: var(--primary-green);
            color: white;
            transform: translateY(-50%) scale(1.1);
        }

        .nav-arrow:active {
            transform: translateY(-50%) scale(0.95);
        }

        .nav-arrow.disabled {
            opacity: 0.3;
            cursor: not-allowed;
            pointer-events: none;
        }

        .nav-arrow-left {
            left: -25px;
        }

        .nav-arrow-right {
            right: -25px;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 4rem;
            margin-top: 2rem;
        }

        .action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.75rem;
            background: transparent;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .action-btn:hover .btn-icon {
            transform: scale(1.1);
        }

        .btn-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            transition: all 0.3s ease;
        }

        .btn-icon.wrong {
            background: #fee;
            color: #dc2626;
        }

        .btn-icon.correct {
            background: #eff;
            color: #16a34a;
        }

        .action-btn:hover .btn-icon.wrong {
            background: #dc2626;
            color: white;
        }

        .action-btn:hover .btn-icon.correct {
            background: #16a34a;
            color: white;
        }

        .btn-label {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--dark-gray);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .category-title {
                font-size: 2rem;
            }

            .image-display {
                min-height: 300px;
            }

            .nav-arrow {
                width: 40px;
                height: 40px;
                font-size: 1.25rem;
            }

            .nav-arrow-left {
                left: -20px;
            }

            .nav-arrow-right {
                right: -20px;
            }

            .btn-icon {
                width: 60px;
                height: 60px;
                font-size: 2rem;
            }

            .action-buttons {
                gap: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex align-items-center mb-2 mt-3">
            <a href="GoSort_WasteMonitoringNavpage.php" class="back-button">
                <i class="bi bi-arrow-left"></i>
            </a>
            <h2 class="fw-bold mb-0">Bin Monitoring</h2>

            <!-- Time Display -->
            <div class="time-display ms-auto">
                <div class="time" id="currentTime">12:00 am</div>
                <div class="date" id="currentDate">Tuesday, April 15</div>
            </div>
        </div>

        <hr style="height: 1.5px; background-color: #000; opacity: 1;" class="mb-4">

        <!-- Review Card -->
        <div class="review-card">
            <!-- Counter -->
            <div class="counter-display">
                <span id="currentCount">1</span> out of <span id="totalCount">500</span>
            </div>

            <!-- Category -->
            <h1 class="category-title" id="wasteCategory">Biodegradable</h1>
            <p class="detection-subtitle">
                Detected: <span class="detected-item" id="detectedItem">Fruit Peel</span>
            </p>

            <!-- Image Container with Navigation -->
            <div class="image-container">
                <div class="nav-arrow nav-arrow-left" id="prevBtn">
                    <i class="bi bi-chevron-left"></i>
                </div>

                <div class="image-display">
                    <div class="placeholder-image" id="placeholderImage">
                        <i class="bi bi-camera-fill d-block"></i>
                        <p>No detections found</p>
                    </div>
                    <img id="detectionImage" src="" alt="Detection" style="display: none;">
                </div>

                <div class="nav-arrow nav-arrow-right" id="nextBtn">
                    <i class="bi bi-chevron-right"></i>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <button class="action-btn" id="wrongBtn">
                    <div class="btn-icon wrong">
                        <i class="bi bi-x-lg"></i>
                    </div>
                    <span class="btn-label">Wrong</span>
                </button>

                <button class="action-btn" id="correctBtn">
                    <div class="btn-icon correct">
                        <i class="bi bi-check-lg"></i>
                    </div>
                    <span class="btn-label">Correct</span>
                </button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sample detection data (replace with actual data from your backend)
        const detections = [
            {
                category: 'Biodegradable',
                item: 'Fruit Peel',
                image: 'https://images.unsplash.com/photo-1603833665858-e61d17a86224?w=500&h=500&fit=crop'
            },
            {
                category: 'Non-Biodegradable',
                item: 'Plastic Bottle',
                image: 'https://images.unsplash.com/photo-1602143407151-7111542de6e8?w=500&h=500&fit=crop'
            },
            {
                category: 'Biodegradable',
                item: 'Paper',
                image: 'https://images.unsplash.com/photo-1594843310722-12e197c55fc5?w=500&h=500&fit=crop'
            },
            {
                category: 'Non-Biodegradable',
                item: 'Aluminum Can',
                image: 'https://images.unsplash.com/photo-1622483767028-3f66f32aef97?w=500&h=500&fit=crop'
            },
            {
                category: 'Biodegradable',
                item: 'Food Waste',
                image: 'https://images.unsplash.com/photo-1628102491629-778571d893a3?w=500&h=500&fit=crop'
            }
        ];

        let currentIndex = 0;

        // Update time display
        function updateTime() {
            const now = new Date();
            let hours = now.getHours();
            const minutes = now.getMinutes().toString().padStart(2, '0');
            const ampm = hours >= 12 ? 'pm' : 'am';
            hours = hours % 12;
            hours = hours ? hours : 12;
            
            const timeString = `${hours}:${minutes} ${ampm}`;
            const options = { weekday: 'long', month: 'long', day: 'numeric' };
            const dateString = now.toLocaleDateString('en-US', options);
            
            document.getElementById('currentTime').textContent = timeString;
            document.getElementById('currentDate').textContent = dateString;
        }

        // Update display with current detection
        function updateDisplay() {
            if (detections.length === 0) {
                document.getElementById('placeholderImage').style.display = 'block';
                document.getElementById('detectionImage').style.display = 'none';
                return;
            }

            const detection = detections[currentIndex];
            
            // Update counter
            document.getElementById('currentCount').textContent = currentIndex + 1;
            document.getElementById('totalCount').textContent = detections.length;
            
            // Update category and item
            document.getElementById('wasteCategory').textContent = detection.category;
            document.getElementById('detectedItem').textContent = detection.item;
            
            // Update image
            const img = document.getElementById('detectionImage');
            img.src = detection.image;
            img.style.display = 'block';
            document.getElementById('placeholderImage').style.display = 'none';
            
            // Update arrow states
            updateArrowStates();
        }

        // Update arrow button states
        function updateArrowStates() {
            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');
            
            if (currentIndex === 0) {
                prevBtn.classList.add('disabled');
            } else {
                prevBtn.classList.remove('disabled');
            }
            
            if (currentIndex === detections.length - 1) {
                nextBtn.classList.add('disabled');
            } else {
                nextBtn.classList.remove('disabled');
            }
        }

        // Navigation functions
        function navigatePrev() {
            if (currentIndex > 0) {
                currentIndex--;
                updateDisplay();
            }
        }

        function navigateNext() {
            if (currentIndex < detections.length - 1) {
                currentIndex++;
                updateDisplay();
            }
        }

        // Action handlers
        function markWrong() {
            console.log('Marked as wrong:', detections[currentIndex]);
            // Add your logic here (e.g., API call to update database)
            alert('Marked as Wrong');
        }

        function markCorrect() {
            console.log('Marked as correct:', detections[currentIndex]);
            // Add your logic here (e.g., API call to update database)
            alert('Marked as Correct');
        }

        // Event listeners
        document.getElementById('prevBtn').addEventListener('click', navigatePrev);
        document.getElementById('nextBtn').addEventListener('click', navigateNext);
        document.getElementById('wrongBtn').addEventListener('click', markWrong);
        document.getElementById('correctBtn').addEventListener('click', markCorrect);

        // Keyboard navigation
        document.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowLeft') navigatePrev();
            if (e.key === 'ArrowRight') navigateNext();
        });

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            updateTime();
            setInterval(updateTime, 1000);
            updateDisplay();
        });
    </script>
</body>
</html>