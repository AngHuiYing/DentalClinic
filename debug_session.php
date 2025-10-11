<?php
session_start();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Session Debug</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .debug-box { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .session-item { padding: 8px; border-bottom: 1px solid #eee; }
        .key { font-weight: bold; color: #333; }
        .value { color: #666; margin-left: 10px; }
    </style>
</head>
<body>
    <div class="debug-box">
        <h2>Current Session Values</h2>
        <?php if (empty($_SESSION)): ?>
            <p>No session data found. Please log in first.</p>
            <a href="patient/login.php">Patient Login</a> | 
            <a href="doctor/login.php">Doctor Login</a> | 
            <a href="admin/login.php">Admin Login</a>
        <?php else: ?>
            <?php foreach ($_SESSION as $key => $value): ?>
                <div class="session-item">
                    <span class="key"><?php echo htmlspecialchars($key); ?>:</span>
                    <span class="value"><?php echo htmlspecialchars($value); ?></span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <h3>User Status</h3>
        <div class="session-item">
            <span class="key">Logged in:</span>
            <span class="value"><?php echo isset($_SESSION['user_id']) ? 'Yes' : 'No'; ?></span>
        </div>
        <div class="session-item">
            <span class="key">Role (role):</span>
            <span class="value"><?php echo isset($_SESSION['role']) ? $_SESSION['role'] : 'Not set'; ?></span>
        </div>
        <div class="session-item">
            <span class="key">Role (user_role):</span>
            <span class="value"><?php echo isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'Not set'; ?></span>
        </div>
        
        <div style="margin-top: 20px;">
            <a href="doctor_reviews.php?doctor_id=1">Test Doctor Reviews Page</a>
        </div>
    </div>
</body>
</html>