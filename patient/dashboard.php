<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'patient') {
    header("Location: ../patient/login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
<?php include '../includes/navbar.php'; ?>
<div class="container mt-4">
    <h2>Patient Dashboard</h2>
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5>My Appointments</h5>
                </div>
                <div class="card-body">
                    <p>View and manage your upcoming appointments.</p>
                    <a href="my_appointments.php" class="btn btn-primary">View Appointments</a>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Book Appointment</h5>
                </div>
                <div class="card-body">
                    <p>Schedule a new appointment with our doctors.</p>
                    <a href="../all_doctors.php" class="btn btn-success">Book Now</a>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5>My Medical History</h5>
                </div>
                <div class="card-body">
                    <p>View and manage your upcoming appointments.</p>
                    <a href="patient_history.php" class="btn btn-primary">View Appointments</a>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Send Message</h5>
                </div>
                <div class="card-body">
                    <p>Send a message to admin/doctors if you have any question.</p>
                    <a href="message.php" class="btn btn-primary">Send Message</a>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Chat with Doctor</h5>
                </div>
                <div class="card-body">
                    <p>Chat with doctor if have any question.</p>
                    <a href="chat_system.php" class="btn btn-success">Chat</a>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html> 