<?php
date_default_timezone_set('Europe/Istanbul');

// MYSQL LOCAL DATABASE
$db_host = '127.0.0.1';
$db_user = 'root';
$db_pass = '12345678';
$db_name = 'cs306';

// Establish connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => "Connection failed: " . $conn->connect_error]));
}

// Fetch available patients
$query = "SELECT PatientID, PatientName FROM Patients";
$result = $conn->query($query);
$patient_options = "";

if ($result && $result->num_rows > 0) {
    while ($patient = $result->fetch_assoc()) {
        $patient_id = $patient['PatientID'];
        $patient_name = $patient['PatientName'];
        $patient_options .= "<option value='$patient_id'>$patient_name (ID: $patient_id)</option>";
    }
} else {
    $patient_options = "<option value=\"\" disabled>No patients available</option>";
}

// Handle AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    $response = ['success' => false, 'message' => '', 'prescriptions' => []];
    
    try {
        $patientID = $conn->real_escape_string($_POST['patient_id'] ?? '');
        $theYear = $conn->real_escape_string($_POST['year'] ?? '');

        // Validate inputs aren't empty
        if (empty($patientID) || empty($theYear)) {
            throw new Exception("Patient ID or Year is missing.");
        }

        // Validate numeric inputs
        if (!is_numeric($patientID)) {
            throw new Exception("Patient ID must be a number");
        }

        if (!is_numeric($theYear) || strlen($theYear) !== 4) {
            throw new Exception("Year must be a 4-digit number");
        }
        
        // Call the stored procedure
        $sql = "CALL display_prescription('$patientID', '$theYear')";
        
        if ($result = $conn->query($sql)) {
            // Store first result set (prescriptions)
            while ($row = $result->fetch_assoc()) {
                $response['prescriptions'][] = $row;
            }
            $result->free();
            
            // If using MySQL, consume additional result sets
            while ($conn->more_results()) {
                $conn->next_result();
                if ($extra_result = $conn->store_result()) {
                    $extra_result->free();
                }
            }
            
            if (empty($response['prescriptions'])) {
                $response['message'] = "Patient has no prescription.";
            } else {
                $response['success'] = true;
            }
        } else {
            throw new Exception("Error executing query: " . $conn->error);
        }
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
    
    echo json_encode($response);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stored Procedure 2: Display_Prescription</title>
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
        .section-title {
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
        }
        .table {
            width: 100%;
            margin-bottom: 1rem;
            background-color: transparent;
            border-collapse: collapse;
        }
        .table th,
        .table td {
            padding: 0.75rem;
            vertical-align: top;
            border-top: 1px solid #dee2e6;
        }
        .table thead th {
            vertical-align: bottom;
            border-bottom: 2px solid #dee2e6;
            background-color: #f8f9fa;
        }
        .table tbody + tbody {
            border-top: 2px solid #dee2e6;
        }
        .test-cases {
            margin: 20px 0;
        }
        .test-case {
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <h1>Stored Procedure 2: Display_Prescription</h1>

    <div class="case">
        <h2 class="section-title">Description</h2>
        <p>This stored procedure retrieves and displays all prescriptions for a specific patient in a given year. It shows detailed information including prescription details, patient information, doctor information, and medicine details.</p>
    </div>

    <div class="case">
        <h2 class="section-title">Display Prescription</h2>
        <div id="message-container"></div>
        <div id="loading" style="display: none;">Loading prescriptions...</div>
        <div id="results-container">
            <!-- Results will be loaded here via AJAX -->
        </div>
        
        <form id="prescription-form" method="post" novalidate>
            <div class="mb-3">
                <label for="patient_id" class="form-label">Select Patient:</label>
                <select class="form-select" id="patient_id" name="patient_id" required>
                    <option value="" selected disabled>Select a patient</option>
                    <?php echo $patient_options; ?>
                </select>
            </div>
            <div class="mb-3">
                <label for="year" class="form-label">Year:</label>
                <input type="number" class="form-control" id="year" name="year" min="1900" max="<?php echo date('Y'); ?>" required>
            </div>
            <button type="submit" class="btn btn-primary">View Prescriptions</button>
        </form>

        <div class="case">
            <h2 class="section-title">Test Cases</h2>
            <div class="test-case">
                <p><strong>Test Case 1:</strong> View prescriptions for Bertine Silvers (PatientID: 296049359) in 2025</p>
                <ul>
                    <li>Expected Result: Should show prescription 123123 with Lipitor, Nexium, and Plavix</li>
                    <li>Doctor: Morganica Whitrod (Dermatology)</li>
                </ul>
            </div>
            <div class="test-case">
                <p><strong>Test Case 2:</strong> View prescriptions for Bob Todman (PatientID: 157435238) in 2025</p>
                <ul>
                    <li>Expected Result: Should show prescription 232323 with Nexium</li>
                    <li>Doctor: Martyn Sim (Pediatrics)</li>
                </ul>
            </div>
            <div class="test-case">
                <p><strong>Test Case 3:</strong> View prescriptions for Wyndham Allardyce (PatientID: 40441509) in 2025</p>
                <ul>
                    <li>Expected Result: Should show prescription 23445 with Plavix</li>
                    <li>Doctor: Morganica Whitrod (Dermatology)</li>
                </ul>
            </div>
            <div class="test-case">
                <p><strong>Test Case 4:</strong> View prescriptions for Muire Buddleigh (PatientID: 797224685) in 2025</p>
                <ul>
                    <li>Expected Result: Should show prescription 53467546 with Advair Diskus</li>
                    <li>Doctor: Martyn Sim (Pediatrics)</li>
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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    $(document).ready(function() {
        $('#prescription-form').on('submit', function(e) {
            console.log('Form submit event triggered.');
            e.preventDefault();

            // Basic validation (can enhance later if needed)
            const patientId = $('#patient_id').val();
            const year = $('#year').val();

            if (!patientId || !year) {
                $('#message-container').html('<div class="alert alert-danger">Please select a patient and enter a year.</div>');
                return;
            }

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
                        $('#message-container').html('<div class="alert alert-success">' + (response.message || 'Successfull.') + '</div>');
                        if (response.prescriptions.length > 0) {
                            let tableHtml = `<div class="table-responsive"><table class="table"><thead><tr>
                                <th>Prescription ID</th>
                                <th>Patient ID</th>
                                <th>Patient Name</th>
                                <th>Doctor ID</th>
                                <th>Doctor Name</th>
                                <th>Speciality</th>
                                <th>Medicine Name</th>
                                <th>Dosage</th>
                                <th>Prescription Date</th>
                            </tr></thead><tbody>`;

                            response.prescriptions.forEach(function(prescription) {
                                tableHtml += `<tr>
                                    <td>${escapeHtml(prescription.PressID || '')}</td>
                                    <td>${escapeHtml(prescription.PatientID || '')}</td>
                                    <td>${escapeHtml(prescription.PatientName || '')}</td>
                                    <td>${escapeHtml(prescription.DoctorID || '')}</td>
                                    <td>${escapeHtml(prescription.DoctorName || '')}</td>
                                    <td>${escapeHtml(prescription.Speciality || '')}</td>
                                    <td>${escapeHtml(prescription.MedicineName || '')}</td>
                                    <td>${escapeHtml(prescription.Dosage || '')}</td>
                                    <td>${escapeHtml(prescription.PressDate || '')}</td>
                                </tr>`;
                            });

                            tableHtml += `</tbody></table></div>`;
                            $('#results-container').html(tableHtml);
                        } else {
                             $('#message-container').html('<div class="alert alert-info">No prescription found.</div>');
                        }
                    } else {
                        $('#message-container').html('<div class="alert alert-danger">' + (response.message || 'An unknown error occurred.') + '</div>');
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