<?php
// Database configuration

// MYSQL LOCAL DATABASE
$db_host = '127.0.0.1';
$db_user = 'root';
$db_pass = '12345678';
$db_name = 'cs306';
date_default_timezone_set('Europe/Istanbul');

// Establish connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => "Connection failed: " . $conn->connect_error]));
}

// Handle AJAX request for Bill Details
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_bill'])) {
    header('Content-Type: application/json');

    $response = ['success' => false, 'message' => '', 'bill' => null];

    try {
        $billID = $conn->real_escape_string($_POST['bill_id'] ?? '');

        if (empty($billID) || !is_numeric($billID)) {
            throw new Exception("Valid Bill ID is required");
        }

        $sql = "SELECT BillID, TotalCost, MedicineCost FROM Bills WHERE BillID = '$billID'";
        $result = $conn->query($sql);

        if ($result) {
            if ($result->num_rows > 0) {
                $bill = $result->fetch_assoc();
                $response['success'] = true;
                $response['bill'] = $bill;
                $response['message'] = "Bill details found.";
            } else {
                $response['message'] = "Bill with ID $billID not found.";
            }
            $result->free();
        } else {
            throw new Exception("Database error: " . $conn->error);
        }
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }

    echo json_encode($response);
    exit;
}

// Handle AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');

    $response = ['success' => false, 'message' => ''];

    try {
        $medicine_id = $conn->real_escape_string($_POST['medicine_id'] ?? '');
        $press_id = $conn->real_escape_string($_POST['press_id'] ?? '');

        if (empty($medicine_id) || empty($press_id)) {
            throw new Exception("All fields are required");
        }

        if (!is_numeric($medicine_id) || !is_numeric($press_id)) {
            throw new Exception("Medicine ID and Prescription ID must be numbers");
        }

        // Check if Medicine exists
        $med_check = $conn->prepare("SELECT 1 FROM Medicines WHERE MedicineID = ?");
        $med_check->bind_param("i", $medicine_id);
        $med_check->execute();
        $med_check->store_result();
        if ($med_check->num_rows === 0) {
            throw new Exception("Error: Medicine with ID $medicine_id does not exist.");
        }
        $med_check->close();

        // Check if Prescription exists
        $press_check = $conn->prepare("SELECT 1 FROM Prescription WHERE PressID = ?");
        $press_check->bind_param("i", $press_id);
        $press_check->execute();
        $press_check->store_result();
        if ($press_check->num_rows === 0) {
            throw new Exception("Error: Prescription with ID $press_id does not exist.");
        }
        $press_check->close();

        // Perform insert
        $sql = "INSERT INTO Included (MedicineID, PressID) VALUES ('$medicine_id', '$press_id')";
        if ($conn->query($sql)) {
            $response['success'] = true;
            $response['message'] = "Medicine added to prescription successfully! The bill has been updated automatically.";
        } else {
            if ($conn->errno == 1062) {
                $response['message'] = "Error: Medicine with ID $medicine_id is already included in Prescription $press_id.";
            } else if ($conn->errno == 1644 || strpos($conn->error, '45000') !== false) {
                $response['message'] = "Error: Invalid medicine or prescription ID!";
            } else {
                throw new Exception("Database error: " . $conn->error);
            }
        }
    } catch (Exception $e) {
        if($e->getCode() == 1062)
        {
            $response['message'] = "Error: Medicine with ID $medicine_id is already included in Prescription $press_id.";
        }
        else
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
    <title>Trigger 2: Medicine Cost Adder</title>
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
         .alert-info {
            background-color: #d1ecf1;
            border-color: #bee5eb;
            color: #0c5460;
        }
        .section-title {
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
        }
         .form-group .validation-error {
             display: block; /* Show validation error */
             color: #dc3545;
             font-size: 0.875em;
             margin-top: 0.25rem;
         }
         input.error, select.error {
             border-color: #dc3545;
         }
         input.valid, select.valid {
             border-color: #28a745;
         }
         /* Keep specific test case styling for readability but within the case structure */
         .test-cases {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #e9ecef; /* Separator line */
         }
        .test-case {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px dashed #ccc; /* Dashed line between cases */
        }
         .test-case:last-child {
             border-bottom: none; /* No border for the last test case */
             padding-bottom: 0;
         }
         .test-case strong {
             color: #333;
         }
         .test-case ul {
             list-style: disc inside;
             padding-left: 15px;
             margin-top: 5px;
         }
         .test-case li {
             margin-bottom: 3px;
         }

    </style>
</head>
<body>
    <h1>Trigger 2: Medicine Cost Adder</h1>

    <div class="case">
        <h2 class="section-title">Description</h2>
        <p>
            This trigger automatically updates the bill costs when a medicine is added to a prescription. It:
        </p>
        <ul>
            <li>Gets triggered when a new medicine is added to the Included table</li>
            <li>Retrieves the medicine price from the Medicines table</li>
            <li>Finds the associated bill through the Bill_Pres table</li>
            <li>Updates the MedicineCost and TotalCost in the Bills table</li>
            <li>Maintains accurate billing records automatically</li>
        </ul>
    </div>

    <div class="case">
        <h2 class="section-title">Add Medicine to Prescription</h2>
        <div id="message-container"></div>
        
        <form id="medicine-form" method="POST" novalidate>
            <div class="mb-3">
                <label for="medicine_id" class="form-label">Medicine ID:</label>
                <input type="text" name="medicine_id" id="medicine_id" class="form-control" required>
                <span class="validation-error" id="medicine_id_error"></span>
            </div>
            <div class="mb-3">
                <label for="press_id" class="form-label">Prescription ID:</label>
                <input type="text" name="press_id" id="press_id" class="form-control" required>
                <span class="validation-error" id="press_id_error"></span>
            </div>
            <button type="submit" class="btn btn-primary">Add Medicine</button>
        </form>
    </div>
    
    <div class="case">
        <h2 class="section-title">View Bill Details</h2>
        <div id="bill-message-container"></div>
        <div id="bill-loading" style="display: none;">Loading bill details...</div>
        
        <form id="bill-details-form" method="POST" novalidate>
            <div class="mb-3">
                <label for="bill_id" class="form-label">Bill ID:</label>
                <input type="text" name="bill_id" id="bill_id" class="form-control" required>
                <div class="invalid-feedback">Please enter a valid Bill ID.</div>
            </div>
            <button type="submit" class="btn btn-primary">View Details</button>
        </form>
        
        <div id="bill-results-container" class="mt-4"></div>
    </div>

    <div class="case">
        <h2 class="section-title">Test Cases for Medicine Cost Adder</h2>
        <div class="test-cases">
            <div class="test-case">
                <p><strong>Test Case 1:</strong> Add Abilify (MedicineID: 80) to Prescription 123123</p>
                <ul>
                    <li>Expected Result: Bill 1001's MedicineCost will increase by 140</li>
                </ul>
            </div>
            <div class="test-case">
                <p><strong>Test Case 2:</strong> Add Plavix (MedicineID: 1171) to Prescription 232323</p>
                <ul>
                    <li>Expected Result: Bill 1002's MedicineCost will increase by 130.00</li>
                </ul>
            </div>
            <div class="test-case">
                <p><strong>Test Case 3:</strong> Add Humira (MedicineID: 3799) to Prescription 345</p>
                <ul>
                    <li>Expected Result: Bill 1010's MedicineCost will increase by 80.00</li>
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
        // Reset validation on focus
        $('input').on('focus', function() {
            $(this).removeClass('error valid');
            $(this).next('.validation-error').hide();
        });

        // Validation functions
        function validateMedicineId() {
            const $field = $('#medicine_id');
            const $error = $('#medicine_id_error');
            const value = $field.val().trim();
            const regex = /^\d+$/;
            
            $error.hide().text('');
            $field.removeClass('is-invalid is-valid'); // Use Bootstrap validation classes
            $field.next('.invalid-feedback').remove(); // Remove previous feedback

            if (!value) {
                 $field.addClass('is-invalid').after('<div class="invalid-feedback">Medicine ID is required</div>');
                return false;
            }
            
            if (!regex.test(value)) {
                 $field.addClass('is-invalid').after('<div class="invalid-feedback">Medicine ID must contain only numbers</div>');
                return false;
            }
            
            $field.addClass('is-valid');
            return true;
        }
        
        function validatePressId() {
            const $field = $('#press_id');
            const $error = $('#press_id_error'); // This span is no longer needed with Bootstrap validation
            const value = $field.val().trim();
            const regex = /^\d+$/;
            
            $error.hide().text(''); // This is no longer needed
            $field.removeClass('is-invalid is-valid'); // Use Bootstrap validation classes
            $field.next('.invalid-feedback').remove(); // Remove previous feedback

            if (!value) {
                $field.addClass('is-invalid').after('<div class="invalid-feedback">Prescription ID is required</div>');
                return false;
            }
            
            if (!regex.test(value)) {
                $field.addClass('is-invalid').after('<div class="invalid-feedback">Prescription ID must contain only numbers</div>');
                return false;
            }
            
            $field.addClass('is-valid');
            return true;
        }
        
        // Validate on input change
        $('#medicine_id').on('input', validateMedicineId);
        $('#press_id').on('input', validatePressId);
        
        // Form submission
        $('#medicine-form').on('submit', function(e) {
            e.preventDefault();
            
            // Validate all fields
            const isMedicineIdValid = validateMedicineId();
            const isPressIdValid = validatePressId();
            
            if (isMedicineIdValid && isPressIdValid) {
                $.ajax({
                    type: 'POST',
                    url: '',
                    data: $(this).serialize() + '&ajax=1',
                    dataType: 'json',
                    success: function(response) {
                        if (typeof response === 'object' && response !== null) {
                            // Use Bootstrap alert classes
                            var messageClass = response.success ? 'alert-success' : 'alert-danger';
                            var messageHtml = '<div class="alert ' + messageClass + '">' + response.message + '</div>';
                            
                            $('#message-container').html(messageHtml);
                            
                            if (response.success) {
                                $('#medicine-form')[0].reset();
                                $('input').removeClass('is-invalid is-valid'); // Use Bootstrap validation classes
                                // Remove validation feedback elements
                                $('.invalid-feedback').remove();
                            }
                        } else {
                            $('#message-container').html('<div class="alert alert-danger">Invalid response from server</div>');
                        }
                    },
                    error: function(xhr, status, error) {
                         // Use Bootstrap alert class for error
                        $('#message-container').html('<div class="alert alert-danger">Error: ' + error + '</div>');
                    }
                });
            }
             // Display a general error if form is invalid after attempting submission
            else {
                 $('#message-container').html('<div class="alert alert-danger">Please fix the errors in the form before submitting.</div>');
            }
        });

        // Form submission for viewing bill details
        $('#bill-details-form').on('submit', function(e) {
            e.preventDefault(); // Prevent default form submission

            const billId = $('#bill_id').val().trim();

            if (!billId) {
                $('#bill-message-container').html('<div class="alert alert-danger">Please enter a Bill ID.</div>');
                return;
            }

            $('#bill-loading').show();
            $('#bill-message-container').empty();
            $('#bill-results-container').empty();

            $.ajax({
                type: 'POST',
                url: '',
                data: { bill_id: billId, ajax_bill: 1 }, // Send bill_id and ajax_bill flag
                dataType: 'json',
                success: function(response) {
                    $('#bill-loading').hide();
                    if (response.success && response.bill) {
                        $('#bill-message-container').html('<div class="alert alert-success">' + response.message + '</div>');
                        const bill = response.bill;
                        const resultsHtml = `
                            <h4>Bill Information</h4>
                            <table class="table table-bordered">
                                <tr><th>Bill ID</th><td>${escapeHtml(bill.BillID)}</td></tr>
                                <tr><th>Total Cost</th><td>${escapeHtml(bill.TotalCost)}</td></tr>
                                <tr><th>Medicine Cost</th><td>${escapeHtml(bill.MedicineCost)}</td></tr>
                            </table>
                        `;
                        $('#bill-results-container').html(resultsHtml);
                    } else {
                        $('#bill-message-container').html('<div class="alert alert-danger">' + (response.message || 'Bill not found or an error occurred.') + '</div>');
                    }
                },
                error: function(xhr, status, error) {
                    $('#bill-loading').hide();
                    let errorMsg = 'Error: ' + error;
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response && response.message) {
                            errorMsg = response.message;
                        }
                    } catch (e) { }
                    $('#bill-message-container').html('<div class="alert alert-danger">Error: ' + errorMsg + '</div>');
                }
            });
        });

        // Helper function for HTML escaping (assuming this is needed)
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