<?php
// Database configuration

// MYSQL LOCAL DATABASE
$db_host = '127.0.0.1';
$db_user = 'root';
$db_pass = '12345678';
$db_name = 'cs306';
date_default_timezone_set('Europe/Istanbul');

// Initialize variables
$message = '';
$conn = null;
$selected_patient = '';
$selected_doctor = '';
$selected_date = '';
$patient_options = ""; // Initialize empty, we'll add the default option in HTML

try {
    // Establish connection with error handling
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");

    // Fetch available patients for dropdown (do this before form handling)
    $query = "SELECT PatientID, PatientName FROM Patients ORDER BY PatientName";
    $result = $conn->query($query);
    if (!$result) {
        throw new Exception("❌ Error fetching patients: " . $conn->error);
    }

    while ($patient = $result->fetch_assoc()) {
        $patient_id = htmlspecialchars($patient['PatientID']);
        $patient_name = htmlspecialchars($patient['PatientName']);
        $patient_options .= "<option value='$patient_id'>$patient_name (ID: $patient_id)</option>";
    }

    // Handle form submission
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        // Validate and sanitize inputs
        $patient_id = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
        $doctor_id = filter_input(INPUT_POST, 'doctor_id', FILTER_VALIDATE_INT);
        $appointment_date = filter_input(INPUT_POST, 'appointment_date', FILTER_SANITIZE_STRING);

        // Store selected values to repopulate form
        $selected_patient = $patient_id;
        $selected_doctor = $doctor_id;
        $selected_date = $appointment_date;

        // Validate required fields
        if (!$patient_id || !$doctor_id || !$appointment_date) {
            throw new Exception("All fields are required and must be valid.");
        }

        // Validate date format
        $date_obj = DateTime::createFromFormat('Y-m-d', $appointment_date);
        if (!$date_obj || $date_obj->format('Y-m-d') !== $appointment_date) {
            throw new Exception("Invalid date format. Please use YYYY-MM-DD format.");
        }

        // Check if patient exists
        $check_patient = "SELECT 1 FROM Patients WHERE PatientID = ?";
        $stmt = $conn->prepare($check_patient);
        if (!$stmt) {
            throw new Exception("Database error: " . $conn->error);
        }
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 0) {
            throw new Exception("PatientID $patient_id not found in Patients table.");
        }
        $stmt->close();

        // Check if doctor exists and get specialty
        $check_doctor = "SELECT Speciality FROM Doctors WHERE DoctorID = ?";
        $stmt = $conn->prepare($check_doctor);
        if (!$stmt) {
            throw new Exception("Database error: " . $conn->error);
        }
        $stmt->bind_param("i", $doctor_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            throw new Exception("DoctorID $doctor_id not found in Doctors table.");
        }

        $doctor_data = $result->fetch_assoc();
        $specialty = $doctor_data['Speciality'];
        $stmt->close();

        // Try to insert the appointment
        $insert_query = "INSERT INTO Appointment (PatientID, DoctorID, AppointmentDate, Speciality) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_query);
        if (!$stmt) {
            throw new Exception("Database error: " . $conn->error);
        }
        $stmt->bind_param("iiss", $patient_id, $doctor_id, $appointment_date, $specialty);

        if ($stmt->execute()) {
            $message = "Appointment booked successfully!";
            // Clear form on success
            $selected_patient = '';
            $selected_doctor = '';
            $selected_date = '';
        } else {
            if ($conn->errno == 1644 || strpos($conn->error, '45000') !== false) {
                throw new Exception("Error: Patient already has an appointment for this specialty on the selected date!");
            } else {
                throw new Exception("Database error: " . $conn->error);
            }
        }
        $stmt->close();
    }

} catch (Exception $e) {
    $message = $e->getMessage();
} finally {
    // Close database connection if it exists
    if ($conn) {
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trigger 3: prevent_duplicate_appointments</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
        .section-title {
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
        }
        .test-cases {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 6px;
        }
        .test-case {
            margin: 10px 0;
        }
    </style>
    <script>
        $(document).ready(function() {
            $('form').on('submit', function(e) {
                e.preventDefault();
                
                $.ajax({
                    type: 'POST',
                    url: 'trigger3.php',
                    data: $(this).serialize(),
                    success: function(response) {
                        // Create a temporary div to parse the response
                        const tempDiv = $('<div>').html(response);
                        const alertDiv = tempDiv.find('.alert');
                        
                        if (alertDiv.length) {
                            // Remove existing alerts
                            $('.alert').remove();
                            
                            // Add the new alert
                            $('form').before(alertDiv);
                            
                            // If successful, clear the form
                            if (alertDiv.hasClass('alert-success')) {
                                $('form')[0].reset();
                            }
                        }
                    }
                });
            });
        });
    </script>
</head>
<body>
    <h1>Trigger 3: prevent_duplicate_appointments</h1>

    <div class="case">
        <h2 class="section-title">Description</h2>
        <p>This trigger prevents a patient from having multiple appointments with doctors of the same specialty on the same day.</p>
    </div>

    <div class="case">
        <h2 class="section-title">Test the Trigger</h2>
        <?php if ($message): ?>
            <div class="alert <?php echo strpos($message, 'booked') !== false ? 'alert-success' : 'alert-danger'; ?>" role="alert">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="post" novalidate>
            <div class="mb-3">
                <label for="patient_id" class="form-label">Select Patient:</label>
                <select class="form-select" id="patient_id" name="patient_id" required>
                    <option value="" disabled selected>Select a patient</option>
                    <?php echo $patient_options; ?>
                </select>
            </div>
            <div class="mb-3">
                <label for="doctor_id" class="form-label">Doctor ID:</label>
                <input type="text" class="form-control" id="doctor_id" name="doctor_id" required 
                       pattern="[0-9]+" title="Please enter a valid doctor ID (numbers only)"
                       value="<?php echo htmlspecialchars($selected_doctor); ?>">
            </div>
            <div class="mb-3">
                <label for="appointment_date" class="form-label">Appointment Date:</label>
                <input type="date" class="form-control" id="appointment_date" name="appointment_date" required
                       min="<?php echo date('Y-m-d'); ?>"
                       value="<?php echo htmlspecialchars($selected_date); ?>">
            </div>
            <button type="submit" class="btn btn-primary">Book Appointment</button>
        </form>

        <div class="test-cases">
            <h3>Test Cases</h3>
            <div class="test-case">
                <p><strong>Test Case 1:</strong> Try booking an appointment for same doctor with same speciality on same date (40441509, 611, 2025-10-27)</p>
                <ul>
                    <li>Expected Result: It won't work</li>
                </ul>
            </div>
            <div class="test-case">
                <p><strong>Test Case 2:</strong> Try booking an appointment for same doctor with same speciality on different date (40441509, 611, 2025-10-29)</p>
                <ul>
                    <li>Expected Result: It will work</li>
                </ul>
            </div>
            <div class="test-case">
                <p><strong>Test Case 3:</strong> Try booking an appointment for a different doctor with same speciality on same date (40441509, 612, 2025-10-27)</p>
                <ul>
                    <li>Expected Result: It won't work since the patient already has an appointment for same branch on this date</li>
                </ul>
            </div>
            <div class="test-case">
                <p><strong>Test Case 4:</strong> Try booking an appointment for a different doctor with same speciality on a different date (40441509, 612, 2025-10-28)</p>
                <ul>
                    <li>Expected Result: It will work</li>
                </ul>
            </div>
            <div class="test-case">
                <p><strong>Test Case 5:</strong> Try booking an appointment for a different doctor with different speciality on a same date (40441509, 2240, 2025-10-27)</p>
                <ul>
                    <li>Expected Result: It will work</li>
                </ul>
            </div>
            <div class="test-case">
                <p><strong>Test Case 6:</strong> Try booking an appointment for a different doctor with different speciality on a different date (40441509, 2240, 2025-10-28)</p>
                <ul>
                    <li>Expected Result: It will work</li>
                </ul>
            </div>
        </div>
    </div>

    <p style="text-align: center; margin-top: 20px;">
        <a href="index.html" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Back to Home Page
        </a>
    </p>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>