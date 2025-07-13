<?php
date_default_timezone_set('Europe/Istanbul');

error_reporting(E_ALL);
ini_set('display_errors', 1);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// MYSQL LOCAL DATABASE
$host = '127.0.0.1';
$db = 'cs306';
$user = 'root';
$pass = '12345678';
$message = "";

try {
    $conn = new mysqli($host, $user, $pass, $db);
    $conn->set_charset("utf8mb4");

    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['room_id']) && isset($_POST['patient_id'])) {
        $room_id = intval($_POST['room_id']);
        $patient_id = intval($_POST['patient_id']);

        // Call stored procedure
        $stmt = $conn->prepare("CALL choose_room(?, ?, @msg)");
        $stmt->bind_param("ii", $room_id, $patient_id);
        $stmt->execute();
        $stmt->close();

        // Get the message output
        $result = $conn->query("SELECT @msg AS message");
        $row = $result->fetch_assoc();
        $message = $row['message'];
    }

    // --- Fetch available rooms ---
    $query = "SELECT * FROM Rooms WHERE Availability = 1";
    $result = $conn->query($query);
    $room_options = "";

    if ($result && $result->num_rows > 0) {
        while ($room = $result->fetch_assoc()) {
            $room_id = $room['RoomID'];
            $bed_count = $room['BedCount'];

            $bed_result = $conn->query("CALL available_bed_number($room_id)");
            $freeBed = 0;

            if ($bed_result) {
                $bed_data = $bed_result->fetch_assoc();
                $freeBed = $bed_data['freeBed'];
                $bed_result->close();
                $conn->next_result(); // Stored procedure sonrası gerekli
            }

            $room_options .= "<option value='$room_id'>Room $room_id (Available Beds: $freeBed / Capacity: $bed_count)</option>";
        }
    } else {
        $room_options = "<option value=\"\" disabled>No available rooms</option>";
    }

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        echo $message;
        exit;
    }

} catch (mysqli_sql_exception $e) {
    $message = "❌ Connection or query error: " . $e->getMessage();
}

if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    echo $message;
    exit;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stored Procedure 1 Details Page</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 40px auto;
            max-width: 900px;
            background: #f9f9f9;
            color: #333;
        }
        h1 {
            text-align: center;
            margin-bottom: 25px;
            color: #2c3e50;
        }
        .case {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgb(0 0 0 / 0.1);
            padding: 20px 25px 30px;
            margin-bottom: 30px;
        }
        .form-label {
            font-weight: 600;
        }
         .form-select, .form-control {
            width: 100%;
            padding: 10px;
            border-radius: 6px;
            border: 1.8px solid #ccc;
            font-size: 1rem;
        }
         .form-select:focus, .form-control:focus {
            border-color: #2980b9;
            outline: none;
            box-shadow: 0 0 6px #2980b9aa;
        }
        .btn-primary {
            padding: 9px 18px;
            border: none;
            background-color: #2980b9;
            color: white;
            font-weight: 600;
            font-size: 1rem;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .btn-primary:hover {
            background-color: #1f618d;
        }
         .btn-secondary {
            padding: 9px 18px;
            border: none;
            background-color: #95a5a6;
            color: white;
            font-weight: 600;
            font-size: 1rem;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            text-decoration: none;
        }
        .btn-secondary:hover {
            background-color: #7f8c8d;
            color: white;
        }
        .alert {
            border-radius: 6px;
            padding: 12px 20px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .alert-danger {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
        .alert-success {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }
        .invalid-feedback {
            display: none;
            width: 100%;
            margin-top: 0.25rem;
            font-size: 0.875em;
            color: #dc3545;
        }
        form.was-validated .form-control:invalid ~ .invalid-feedback,
        form.was-validated .form-select:invalid ~ .invalid-feedback {
            display: block;
        }
    </style>
</head>
<body>
    <h1>Stored Procedure 1: Choose_Room</h1>

    <div class="case">
    <p>
        This interface lists all rooms with available beds. Users can assign patients to rooms by selecting a room and providing the corresponding patient ID. 
        The <strong>change_availability_rooms</strong> trigger ensures the room's availability status is updated automatically after each assignment..
        </p>
    </div>

    <div class="case">
        <h3>Assign Patient to Room</h3>
         <?php if ($message): ?>
            <div class="alert <?php echo strpos($message, 'successfully') !== false ? 'alert-success' : 'alert-danger'; ?>" role="alert">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form id="assignForm" method="post" novalidate>
            <div class="mb-3">
                <label for="room_id" class="form-label">Select Room:</label>
                <select name="room_id" id="room_id" class="form-select" required>
                    <option value="" selected disabled>Select a room</option>
                <?= $room_options ?>
                </select>
                <div class="invalid-feedback">Please select a room.</div>
            </div>
            
            <div class="mb-3">
                <label for="patient_id" class="form-label">Patient ID:</label>
                <input type="number" name="patient_id" id="patient_id" class="form-control" required>
                <div class="invalid-feedback">Please enter the Patient ID.</div>
            </div>

            <button type="submit" class="btn btn-primary">Reserve Room</button>
        </form>
    </div>

    <p style="text-align: center; margin-top: 20px;">
        <a href="index.html" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Back to Home Page
        </a>
    </p>
    
<div class="case" style="color: black; font-size: 18px;">
    <h3>Test Cases</h3>
    <ul style="list-style-type: none; padding-left: 0;">
        <li>
            <strong>Case 1:</strong> If a patient is already located in a room like patient <code>40441509</code>, 
            due to duplicate-entry, error message: 
            <em>"This patient is already assigned to a room."</em> will be displayed.
        </li>
        <hr style="border: 1px solid #ccc;">
        <li>
            <strong>Case 2:</strong> Successful insertion.<br>
            If patient <code>572697154</code> is inserted to room <code>6</code>, 
            then the available bed number of room 6 will be <code>1</code>.
        </li>
        <hr style="border: 1px solid #ccc;">
        <li>
            <strong>Case 3:</strong> Successful insertion and the room will not be displayed.<br>
            If patient <code>105950837</code> is inserted to room <code>6</code>, 
            then the available bed number of room 6 will be <code>0</code>. 
            Trigger will make it unavailable. Then, room 6 won’t be displayed anymore.
        </li>
    </ul>
</div>

</body>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById("assignForm").addEventListener("submit", function(e) {
    // Custom Bootstrap validation
    const form = e.target;
    if (!form.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
    } else {
        // If form is valid, proceed with AJAX submission
        e.preventDefault();
        const roomId = document.getElementById("room_id").value;
        const patientId = document.getElementById("patient_id").value;
        
        // Find and remove any existing alerts
        const existingAlerts = document.querySelectorAll('.alert');
        existingAlerts.forEach(alert => alert.remove());

        fetch(window.location.href, {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
                "X-Requested-With": "XMLHttpRequest"
            },
            body: `room_id=${encodeURIComponent(roomId)}&patient_id=${encodeURIComponent(patientId)}`
        })
        .then(response => response.text())
        .then(data => {
            // Create and display the new alert message
            const newAlert = document.createElement('div');
            newAlert.className = `alert ${data.startsWith(' ') ? 'alert-success' : 'alert-danger'}`;
            newAlert.setAttribute('role', 'alert');
            newAlert.innerHTML = data;
            
            // Insert the new alert before the form
            form.parentNode.insertBefore(newAlert, form);

            // If successful, update the room list but preserve selection
            if (data.startsWith(" ")) {
                // Store the current room ID before refreshing
                const currentRoomId = roomId;
                
                fetch(window.location.href)
                .then(res => res.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, "text/html");
                    const newOptions = doc.querySelector("#room_id").innerHTML;
                    const roomSelect = document.getElementById("room_id");
                    
                    // Update the options
                    roomSelect.innerHTML = newOptions;
                    
                    // Try to restore the previous selection if it still exists
                    if (currentRoomId && roomSelect.querySelector(`option[value="${currentRoomId}"]`)) {
                        roomSelect.value = currentRoomId;
                    }
                });

                // Remove the was-validated class to prevent validation messages
                form.classList.remove('was-validated');
            }
        })
        .catch(error => {
            const newAlert = document.createElement('div');
            newAlert.className = 'alert alert-danger';
            newAlert.setAttribute('role', 'alert');
            newAlert.innerHTML = 'Error: ' + error;
            form.parentNode.insertBefore(newAlert, form);
        });
    }

    form.classList.add('was-validated');
});
</script>
</html>
