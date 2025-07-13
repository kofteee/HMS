<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('Europe/Istanbul');

// MYSQL LOCAL DATABASE
$host = '127.0.0.1';
$db = 'cs306';
$user = 'root';
$pass = '12345678';

try {
    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    $patient_id = $_POST['patient_id'] ?? 0;
    $room_id = $_POST['room_id'] ?? 0;

    // Önce hasta var mı kontrol et
    $check_patient_sql = "SELECT 1 FROM Patients WHERE PatientID = ?";
    $check_patient_stmt = $conn->prepare($check_patient_sql);
    $check_patient_stmt->bind_param("i", $patient_id);
    $check_patient_stmt->execute();
    $check_patient_stmt->store_result();

    if ($check_patient_stmt->num_rows === 0) {
        echo "Error: Patient with ID " . htmlspecialchars($patient_id) . " does not exist.";
        exit;
    }

    // Oda var mı kontrol et
    $check_room_sql = "SELECT 1 FROM Rooms WHERE RoomID = ?";
    $check_room_stmt = $conn->prepare($check_room_sql);
    $check_room_stmt->bind_param("i", $room_id);
    $check_room_stmt->execute();
    $check_room_stmt->store_result();

    if ($check_room_stmt->num_rows === 0) {
        echo "Error: Room with ID " . htmlspecialchars($room_id) . " does not exist.";
        exit;
    }

    // Hasta ve oda varsa ekleme yap
    $sql_insert = "INSERT INTO LocatedIn (patientID, roomID) VALUES (?, ?)";
    $stmt = $conn->prepare($sql_insert);
    $stmt->bind_param("ii", $patient_id, $room_id);
    $stmt->execute();

    // Availability kontrolü
    $sql_avail = "SELECT Availability FROM Rooms WHERE RoomID = ?";
    $stmt2 = $conn->prepare($sql_avail);
    $stmt2->bind_param("i", $room_id);
    $stmt2->execute();
    $stmt2->bind_result($availability);
    $stmt2->fetch();
    $stmt2->close();

    if ($availability == 1) {
        echo "Patient added, room is still available.";
    } else {
        echo "Patient added, room is now unavailable.";
    }

    $stmt->close();
    $check_patient_stmt->close();
    $check_room_stmt->close();

} catch (mysqli_sql_exception $e) {
    if ($e->getCode() == 1062) {
        echo "Error: Duplicate entry. Patient with ID " . htmlspecialchars($patient_id) . " is already located in a room.";
    } else {
        echo "" . $e->getMessage();
    }
} catch (Exception $e) {
    echo "General Error: " . $e->getMessage();
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}
?>