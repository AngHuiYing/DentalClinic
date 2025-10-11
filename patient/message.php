<?php
session_start();
include "../includes/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'patient') {
    header("Location: ../patient/login.php");
    exit;
}

$patient_id = $_SESSION['user_id'];

// 從 users 拿 name / email / phone
$stmt = $conn->prepare("SELECT name, email, phone FROM users WHERE id = ?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Message - Dental Clinic</title>
    
    <!-- Bootstrap 5.3.0 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6.4.0 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --clinic-primary: #2d5aa0;
            --clinic-secondary: #4a9396;
            --clinic-accent: #84c69b;
            --clinic-light: #f1f8e8;
            --clinic-warm: #f9f7ef;
            --clinic-text: #2c3e50;
            --clinic-muted: #7f8c8d;
            --clinic-success: #27ae60;
            --clinic-warning: #f39c12;
            --clinic-danger: #e74c3c;
            --clinic-white: #ffffff;
            --clinic-shadow: 0 2px 10px rgba(45, 90, 160, 0.1);
            --clinic-shadow-hover: 0 8px 25px rgba(45, 90, 160, 0.15);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--clinic-light);
            color: var(--clinic-text);
            line-height: 1.6;
            min-height: 100vh;
        }

        .page-header {
            background: linear-gradient(135deg, var(--clinic-primary) 0%, var(--clinic-secondary) 100%);
            color: white;
            padding: 2.5rem 0;
            margin-bottom: 2rem;
            position: relative;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                radial-gradient(circle at 25% 25%, rgba(255, 255, 255, 0.1) 2px, transparent 2px),
                radial-gradient(circle at 75% 75%, rgba(255, 255, 255, 0.1) 2px, transparent 2px);
            background-size: 40px 40px;
        }

        .page-header .container {
            position: relative;
            z-index: 1;
        }

        .page-header h1 {
            font-size: 2.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            text-align: center;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .page-header p {
            font-size: 1rem;
            opacity: 0.95;
            text-align: center;
        }

        .message-container {
            max-width: 700px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .message-card {
            background: var(--clinic-white);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--clinic-shadow);
            border: 1px solid rgba(45, 90, 160, 0.08);
        }

        .card-header-custom {
            background: linear-gradient(135deg, var(--clinic-secondary) 0%, var(--clinic-accent) 100%);
            color: white;
            padding: 1.5rem 2rem;
        }

        .card-header-custom h3 {
            font-size: 1.4rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .card-body-custom {
            padding: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--clinic-text);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.95rem;
        }

        .form-control {
            border: 2px solid #e8ecef;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: var(--clinic-warm);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--clinic-primary);
            background: white;
            box-shadow: 0 0 0 3px rgba(45, 90, 160, 0.1);
        }

        .form-control:disabled {
            background: #f8f9fa;
            border-color: #dee2e6;
            color: var(--clinic-muted);
            cursor: not-allowed;
        }

        .message-textarea {
            min-height: 150px;
            resize: vertical;
            font-family: inherit;
        }

        .form-text {
            color: var(--clinic-muted);
            font-size: 0.85rem;
            margin-top: 0.5rem;
            padding: 0.5rem 1rem;
            background: rgba(74, 147, 150, 0.05);
            border-radius: 8px;
            border-left: 3px solid var(--clinic-secondary);
        }

        .send-btn {
            width: 100%;
            padding: 1rem 2rem;
            background: linear-gradient(135deg, var(--clinic-primary) 0%, var(--clinic-secondary) 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .send-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(45, 90, 160, 0.3);
        }

        .send-btn:active {
            transform: translateY(0);
        }

        .send-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .send-btn:hover::before {
            left: 100%;
        }

        .user-info-section {
            background: var(--clinic-warm);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(45, 90, 160, 0.08);
        }

        .user-info-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
            color: var(--clinic-primary);
            font-weight: 600;
        }

        .info-grid {
            display: grid;
            gap: 1rem;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            background: var(--clinic-white);
            border-radius: 8px;
            border: 1px solid rgba(45, 90, 160, 0.05);
        }

        .info-icon {
            width: 35px;
            height: 35px;
            background: linear-gradient(135deg, var(--clinic-primary), var(--clinic-secondary));
            color: white;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
        }

        .info-content {
            flex: 1;
        }

        .info-label {
            font-size: 0.8rem;
            color: var(--clinic-muted);
            margin-bottom: 0.2rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-value {
            font-weight: 600;
            color: var(--clinic-text);
        }

        .contact-tips {
            background: linear-gradient(135deg, rgba(45, 90, 160, 0.05) 0%, rgba(74, 147, 150, 0.05) 100%);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(45, 90, 160, 0.1);
        }

        .tips-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            color: var(--clinic-primary);
            font-weight: 600;
        }

        .tips-list {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .tips-list li {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
            font-size: 0.9rem;
            color: var(--clinic-text);
        }

        .tips-list li:last-child {
            margin-bottom: 0;
        }

        .tips-list li i {
            color: var(--clinic-secondary);
            margin-top: 0.2rem;
        }

        @media (max-width: 768px) {
            .page-header h1 {
                font-size: 1.8rem;
            }

            .message-container {
                padding: 0 0.5rem;
            }

            .card-body-custom {
                padding: 1.5rem;
            }

            .card-header-custom {
                padding: 1.2rem 1.5rem;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Animation for form elements */
        .message-card {
            animation: fadeInUp 0.6s ease forwards;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <h1><i class="fas fa-envelope"></i> Send Message</h1>
            <p>Contact our medical staff or administration team</p>
        </div>
    </div>

    <div class="message-container">
        <div class="message-card">
            <div class="card-header-custom">
                <h3>
                    <i class="fas fa-paper-plane"></i>
                    Contact Administration
                </h3>
            </div>

            <div class="card-body-custom">
                <!-- User Information Section -->
                <div class="user-info-section">
                    <div class="user-info-header">
                        <i class="fas fa-user-circle"></i>
                        Your Contact Information
                    </div>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">Full Name</div>
                                <div class="info-value"><?= htmlspecialchars($user['name']) ?></div>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">Email Address</div>
                                <div class="info-value"><?= htmlspecialchars($user['email']) ?></div>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-phone"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">Phone Number</div>
                                <div class="info-value"><?= htmlspecialchars($user['phone']) ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contact Tips -->
                <div class="contact-tips">
                    <div class="tips-header">
                        <i class="fas fa-lightbulb"></i>
                        Message Guidelines
                    </div>
                    <ul class="tips-list">
                        <li>
                            <i class="fas fa-check-circle"></i>
                            <span>Be specific about your concern or question</span>
                        </li>
                        <li>
                            <i class="fas fa-check-circle"></i>
                            <span>Include appointment details if relevant</span>
                        </li>
                        <li>
                            <i class="fas fa-check-circle"></i>
                            <span>Mention doctor's name for specific inquiries</span>
                        </li>
                        <li>
                            <i class="fas fa-check-circle"></i>
                            <span>For urgent matters, please call directly</span>
                        </li>
                    </ul>
                </div>

                <!-- Message Form -->
                <form action="send_message.php" method="POST">
                    <!-- Hidden fields for user data -->
                    <input type="hidden" name="name" value="<?= htmlspecialchars($user['name']) ?>">
                    <input type="hidden" name="email" value="<?= htmlspecialchars($user['email']) ?>">
                    <input type="hidden" name="tel" value="<?= htmlspecialchars($user['phone']) ?>">

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-comment-medical"></i>
                            Your Message
                        </label>
                        <textarea name="message" class="form-control message-textarea" 
                                placeholder="Please type your message here..." 
                                required></textarea>
                        <div class="form-text">
                            <i class="fas fa-info-circle"></i>
                            If you have a question for a specific doctor, please mention their name in the message. Our administration team will ensure your message reaches the right person.
                        </div>
                    </div>

                    <button type="submit" class="send-btn">
                        <i class="fas fa-paper-plane"></i>
                        Send Message
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Add some interactivity
        document.addEventListener('DOMContentLoaded', function() {
            const textarea = document.querySelector('.message-textarea');
            const sendButton = document.querySelector('.send-btn');

            // Character counter functionality
            textarea.addEventListener('input', function() {
                if (this.value.length > 0) {
                    sendButton.style.background = 'linear-gradient(135deg, var(--clinic-success) 0%, var(--clinic-secondary) 100%)';
                } else {
                    sendButton.style.background = 'linear-gradient(135deg, var(--clinic-primary) 0%, var(--clinic-secondary) 100%)';
                }
            });

            // Form submission with loading state
            const form = document.querySelector('form');
            
            form.addEventListener('submit', function(e) {
                const message = textarea.value.trim();
                if (!message) {
                    e.preventDefault();
                    alert('Please enter a message before sending.');
                    return false;
                }
                
                // Show loading state
                const originalText = sendButton.innerHTML;
                sendButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
                sendButton.disabled = true;
                
                // Reset button after 10 seconds as fallback
                setTimeout(function() {
                    sendButton.innerHTML = originalText;
                    sendButton.disabled = false;
                }, 10000);
                
                // Allow the form to submit naturally
                return true;
            });

            // Auto-resize textarea
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 300) + 'px';
            });
        });
    </script>
</body>
</html>
