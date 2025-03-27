<?php
include '../includes/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    die("Unauthorized access");
}

$doctor_id = $_SESSION['user_id']; // 假设医生登录时 session 里存的是 user_id
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat with Patients</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        #chat-box {
            height: 400px;
            overflow-y: scroll;
            border: 1px solid #ccc;
            padding: 10px;
            background-color: white;
        }
        .message {
            padding: 8px;
            margin: 5px;
            border-radius: 5px;
            max-width: 70%;
        }
        .sent {
            background-color: #007bff;
            color: white;
            align-self: flex-end;
        }
        .received {
            background-color: #f1f1f1;
            color: black;
            align-self: flex-start;
        }
    </style>
</head>
<body>
<?php include '../includes/navbar.php'; ?>
    <div class="container mt-3">
        <h3>Chat with Patients</h3>

        <!-- 选择病人 -->
        <select id="patient-list" class="form-control">
            <option value="">Select a Patient</option>
        </select>

        <div id="chat-box" class="d-flex flex-column mt-3"></div>
        <textarea id="message" class="form-control mt-2" placeholder="Type a message"></textarea>
        <button class="btn btn-primary mt-2" onclick="sendMessage()">Send</button>
    </div>

    <script>
        $(document).ready(function() {
            console.log("jQuery Loaded:", $.fn.jquery);
            loadPatients();

            $("#patient-list").change(function() {
                console.log("Patient selected:", $(this).val());
                fetchMessages();
            });

            setInterval(fetchMessages, 2000);
        });

        function loadPatients() {
            console.log("Fetching patients...");
            $.get("../doctor/get_patient.php", function(data) {
                console.log("Patients JSON Response:", data);
                try {
                    let patients = typeof data === "string" ? JSON.parse(data) : data;
                    if (Array.isArray(patients)) {
                        $("#patient-list").html('<option value="">Select a Patient</option>');
                        patients.forEach(patient => {
                            $("#patient-list").append(`<option value="${patient.id}">${patient.name}</option>`);
                        });
                    } else {
                        console.error("Invalid data format received:", data);
                    }
                } catch (e) {
                    console.error("Error parsing patients JSON:", e, data);
                }
            }).fail(function(jqXHR, textStatus, errorThrown) {
                console.error("Error fetching patients:", textStatus, errorThrown);
            });
        }

        function fetchMessages() {
            let receiver_id = $("#patient-list").val();
            if (!receiver_id) {
                console.log("⚠️ No patient selected!");
                return;
            }

            console.log("📩 Fetching messages with patient ID:", receiver_id);
            $.get("../doctor/get_messages.php", { receiver_id: receiver_id }, function(data) {
                console.log("📨 Messages JSON:", data);
                try {
                    let messages = JSON.parse(data);
                    $("#chat-box").html("");
                    messages.forEach(msg => {
                        let className = msg.sender_id == <?php echo $doctor_id; ?> ? 'sent' : 'received';
                        $("#chat-box").append(`<div class="message ${className}">${msg.message}</div>`);
                    });
                    $("#chat-box").scrollTop($("#chat-box")[0].scrollHeight);
                } catch (e) {
                    console.error("⚠️ Error parsing messages JSON:", e);
                }
            }).fail(function(jqXHR, textStatus, errorThrown) {
                console.error("⚠️ Error fetching messages:", textStatus, errorThrown);
            });
        }

        function sendMessage() {
            let message = $("#message").val().trim();
            let receiver_id = $("#patient-list").val();
            let sender_id = <?php echo $doctor_id; ?>;

            if (!message || !receiver_id) {
                console.error("Invalid message or receiver ID!");
                return;
            }

            $.post("../doctor/send_messages.php", { 
                message: message, 
                receiver_id: receiver_id 
            }, function(response) {
                console.log("Send message response:", response);
                try {
                    let res = JSON.parse(response);
                    if (res.status === "success") {
                        $("#message").val("");
                        fetchMessages();
                    } else {
                        console.error("Message send failed:", res);
                    }
                } catch (e) {
                    console.error("Error parsing send message response:", e);
                }
            }).fail(function(jqXHR, textStatus, errorThrown) {
                console.error("Error sending message:", textStatus, errorThrown);
            });
        }
    </script>
</body>
</html>
