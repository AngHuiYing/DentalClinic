<?php
include '../includes/db.php';
session_start(); // 确保 SESSION 被启用
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat with a Doctor</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        #chat-box {
            height: 400px;
            overflow-y: scroll;
            border: 1px solid #ccc;
            padding: 10px;
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
    <div class="container mt-3">
        <h3>Chat with a Doctor</h3>

        <!-- Doctor Selection Dropdown -->
        <select id="doctor-list" class="form-control">
            <option value="">Select a Doctor</option>
        </select>

        <div id="chat-box" class="d-flex flex-column mt-3"></div>
        <textarea id="message" class="form-control mt-2" placeholder="Type a message"></textarea>
        <button class="btn btn-primary mt-2" onclick="sendMessage()">Send</button>
    </div>

    <script>
        $(document).ready(function() {
            console.log("jQuery Loaded:", $.fn.jquery); // 确保 jQuery 正确加载
            loadDoctors();

            $("#doctor-list").change(function() {
                console.log("Doctor selected:", $(this).val());
                fetchMessages();
            });

            setInterval(fetchMessages, 2000);
        });

        function loadDoctors() {
    console.log("Fetching doctors...");
    $.get("../patient/get_doctors.php", function(data) {
        console.log("Doctors JSON Response:", data);
        try {
            let doctors = typeof data === "string" ? JSON.parse(data) : data;
            console.log("Parsed Doctors:", doctors);

            if (Array.isArray(doctors)) {
                $("#doctor-list").html('<option value="">Select a Doctor</option>');
                doctors.forEach(doc => {
                    $("#doctor-list").append(`<option value="${doc.id}">${doc.name}</option>`);
                });
            } else {
                console.error("Invalid data format received:", data);
            }
        } catch (e) {
            console.error("Error parsing doctors JSON:", e, data);
        }
    }).fail(function(jqXHR, textStatus, errorThrown) {
        console.error("Error fetching doctors:", textStatus, errorThrown);
    });
}

let last_message_id = 0; // 记录上次收到的最新消息ID

function fetchMessages() {
    let receiver_id = $("#doctor-list").val();
    if (!receiver_id) {
        console.log("⚠️ No doctor selected!");
        return;
    }
    
    console.log("Fetching messages for doctor ID:", receiver_id);

    $.get("../get_messages.php", { receiver_id: receiver_id }, function (data) {
        console.log("Messages JSON:", data);
        try {
            let messages = JSON.parse(data);
            $("#chat-box").html("");
            messages.forEach(msg => {
                let className = msg.sender_id == <?php echo $_SESSION['user_id']; ?> ? 'sent' : 'received';
                $("#chat-box").append(`<div class="message ${className}">${msg.message}</div>`);
            });
            $("#chat-box").scrollTop($("#chat-box")[0].scrollHeight);
        } catch (e) {
            console.error("Error parsing messages JSON:", e);
        }
    }).fail(function(jqXHR, textStatus, errorThrown) {
        console.error("Error fetching messages:", textStatus, errorThrown);
    });
}

function sendMessage() {
    let message = $("#message").val();
    let receiver_id = $("#doctor-list").val();
    let sender_id = <?php echo $_SESSION['user_id']; ?>; // 获取当前用户ID

    if (message.trim() !== "" && receiver_id) {
        console.log("Sending message to receiver_id:", receiver_id); // 确保 receiver_id 是 7

        $.post("../send_messages.php", { 
            message: message, 
            receiver_id: receiver_id 
        }, function (response) {
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
}

    </script>
</body>
</html>
