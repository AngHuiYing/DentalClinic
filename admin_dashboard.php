<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<?php include 'navbar.php'; ?>
<br>
<body class="container mt-5">

    <h2>Welcome, Admin <?php echo $_SESSION['user_name']; ?>!</h2>
    <hr>

    <ul class="list-group">
        <li class="list-group-item"><a href="manage_users.php">Manage Users</a></li>
        <li class="list-group-item"><a href="manage_appointments.php">Manage Appointments</a></li>
        <li class="list-group-item"><a href="clinic_settings.php">Clinic Settings</a></li>
    </ul>

    <a href="logout.php" class="btn btn-danger mt-3">Logout</a>

</body>
</html>
