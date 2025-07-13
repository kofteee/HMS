<?php
date_default_timezone_set('Europe/Istanbul');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Enable mysqli exceptions
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

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        
        $response = ['success' => false, 'message' => '', 'appointments' => []];
        
        try {
            $docID = $conn->real_escape_string($_POST['docID']);
            $startDate = $conn->real_escape_string($_POST['startDate']);
            $endDate = $conn->real_escape_string($_POST['endDate']);
            
            // Server-side validation
            if (!ctype_digit($docID)) {
                $response['message'] = "Doctor ID must be a positive integer";
                echo json_encode($response);
                exit;
            }

            // Check if doctor exists
            $checkDoctor = "SELECT COUNT(*) as count FROM Doctors WHERE DoctorID = '$docID'";
            $result = $conn->query($checkDoctor);
            
            if (!$result) {
                throw new Exception("Database error while checking doctor");
            }
            
            $row = $result->fetch_assoc();
            
            if ($row['count'] == 0) {
                $response['message'] = "DoctorID " . $docID . " is not found in Doctor.s";
                echo json_encode($response);
                exit;
            }
            
            // Call the stored procedure
            $sql = "CALL display_doc_appointments('$docID', '$startDate', '$endDate')";
            
            if ($result = $conn->query($sql)) {
                // Store first result set (appointments)
                while ($row = $result->fetch_assoc()) {
                    // Ensure DoctorName is not null
                    $row['DoctorName'] = $row['PersonnelName'] ?? 'Unknown Doctor';
                    $response['appointments'][] = $row;
                }
                $result->free();
                
                // Consume additional result sets if any
                while ($conn->more_results()) {
                    $conn->next_result();
                    if ($extra_result = $conn->store_result()) {
                        $extra_result->free();
                    }
                }
                
                if (empty($response['appointments'])) {
                    $response['message'] = "No appointments found for the specified criteria.";
                } else {
                    $response['success'] = true;
                    $response['message'] = "Appointments retrieved successfully.";
                }
            } else {
                throw new Exception("Error executing appointment query");
            }
        } catch (Exception $e) {
            $response['message'] = "An error occurred: " . $e->getMessage();
        }
        
        echo json_encode($response);
        exit;
    }

} catch (mysqli_sql_exception $e) {
    $message = "Connection or query error: " . $e->getMessage();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stored Procedure 3 Details Page</title>
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
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
    </style>
</head>
<body>
    <h1>Stored Procedure 3: Display Doctor Appointments</h1>

    <div class="case">
        <p>
            This interface allows you to view appointments for a specific doctor within a given date range.<br>
            Enter the Doctor ID and select the date range to see all appointments.<br>
            The results will show Doctor ID, Doctor Name, Patient ID, Patient Name, and Appointment Date.<br>
            If no appointments are found for the specified criteria, an error message will be displayed.<br>
            The system will verify if the entered Doctor ID exists in the database before showing appointments.
        </p>
    </div>

    <div class="case">
        <h3>View Doctor Appointments</h3>
        <div id="message-container"></div>
        <div id="loading" style="display: none;">Loading appointments...</div>
        
        <form id="appointment-form" method="POST" novalidate>
            <div class="mb-3">
                <label for="docID" class="form-label">Doctor ID:</label>
                <input type="number" name="docID" id="docID" class="form-control" required>
                <div class="invalid-feedback">Please enter a valid Doctor ID.</div>
            </div>
            
            <div class="mb-3">
                <label for="startDate" class="form-label">Start Date:</label>
                <input type="date" name="startDate" id="startDate" class="form-control" placeholder="YYYY-MM-DD" required>
                <div class="invalid-feedback">Please select a start date.</div>
            </div>
            
            <div class="mb-3">
                <label for="endDate" class="form-label">End Date:</label>
                <input type="date" name="endDate" id="endDate" class="form-control" required>
                <div class="invalid-feedback">Please select an end date.</div>
            </div>

            <button type="submit" class="btn btn-primary">Search Appointments</button>
        </form>

        <div class="case" style="margin-top: 10px;">
            <h2 class="section-title">Test Cases</h2>
            <div class="test-case">
                <p><strong>Test Case 1:</strong> Display appointments of doctor with Doctor ID 611, from 01/01/2023 to 01/01/2026</p>
                <ul>
                    <li>Expected Result: Table consisting of 3 rows</li>
                    <li>Patients: Wyndham Allace</li>
                </ul>
            </div>
            <div class="test-case">
                <p><strong>Test Case 2:</strong> Display appointments of doctor with Doctor ID 612, from 01/01/2025 to 01/02/2025</p>
                <ul>
                    <li>Expected Result: No appointments found for specified criteria</li>
                </ul>
            </div>
        </div>
        <div id="results-container"></div>
    </div>

    <p style="text-align: center; margin-top: 20px;">
        <a href="index.html" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Back to Home Page
        </a>
    </p>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    $(document).ready(function() {
        function validateDocID() {
            const $field = $('#docID');
            const $error = $('#docID_error');
            const value = $field.val().trim();
            $error.hide().text('');
            $field.removeClass('error valid');

            if (!value) {
                $error.text('Doctor ID is required').show();
                $field.addClass('error');
                return false;
            }
            if (!/^\d+$/.test(value)) {
                $error.text('Doctor ID must be a positive integer').show();
                $field.addClass('error');
                return false;
            }

            $field.addClass('valid');
            return true;
        }

        function validateDateField(fieldId, errorId) {
            const $field = $(`#${fieldId}`);
            const $error = $(`#${errorId}`);
            const value = $field.val();
            $error.hide().text('');
            $field.removeClass('error valid');

            if (!value) {
                $error.text('Date is required').show();
                $field.addClass('error');
                return false;
            }

            $field.addClass('valid');
            return true;
        }

        function validateDateRange() {
            const start = new Date($('#startDate').val());
            const end = new Date($('#endDate').val());
            const $startError = $('#startDate_error');
            const $endError = $('#endDate_error');

            if (start > end) {
                $endError.text('End date must be after or equal to start date').show();
                $('#endDate').addClass('error');
                return false;
            }

            return true;
        }

        $('#appointment-form').on('submit', function(e) {
            e.preventDefault();

            const isDocValid = validateDocID();
            const isStartValid = validateDateField('startDate', 'startDate_error');
            const isEndValid = validateDateField('endDate', 'endDate_error');
            const isRangeValid = validateDateRange();

            if (isDocValid && isStartValid && isEndValid && isRangeValid) {
                $('#loading').show();
                $('#message-container').empty();
                $('#results-container').empty();

                $.ajax({
                    type: 'POST',
                    url: '',
                    data: $(this).serialize() + '&ajax=1',
                    dataType: 'json',
                    success: function(response) {
                        $('#loading').hide();
                        if (response.success) {
                            $('#message-container').html('<div class="alert alert-success">' + response.message + '</div>');
                            if (response.appointments.length > 0) {
                                let tableHtml = `<table class="table">
                                    <thead>
                                        <tr>
                                            <th>Doctor ID</th>
                                            <th>Doctor Name</th>
                                            <th>Patient ID</th>
                                            <th>Patient Name</th>
                                            <th>Appointment Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>`;

                                response.appointments.forEach(function(appt) {
                                    tableHtml += `<tr>
                                        <td>${escapeHtml(appt.DoctorID || '')}</td>
                                        <td>${escapeHtml(appt.DoctorName || 'Unknown Doctor')}</td>
                                        <td>${escapeHtml(appt.PatientID || '')}</td>
                                        <td>${escapeHtml(appt.PatientName || 'Unknown Patient')}</td>
                                        <td>${escapeHtml(appt.AppointmentDate || '')}</td>
                                    </tr>`;
                                });

                                tableHtml += `</tbody></table>`;
                                $('#results-container').html(tableHtml);
                            }
                        } else {
                            $('#message-container').html('<div class="alert alert-danger">' + response.message + '</div>');
                        }
                    },
                    error: function(xhr, status, error) {
                        $('#loading').hide();
                        let errorMsg = 'Error: ' + error;
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response && response.message) {
                                errorMsg = response.message;
                            }
                        } catch (e) { }
                        $('#message-container').html('<div class="alert alert-danger">' + errorMsg + '</div>');
                    }
                });
            } else {
                $('#message-container').html('<div class="alert alert-danger">Invalid form.</div>');
            }
        });

        function escapeHtml(unsafe) {
            if (unsafe === null || unsafe === undefined) return '';
            return unsafe.toString()
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
    });
    </script>
</body>
</html>