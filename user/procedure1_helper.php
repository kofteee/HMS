<?php
date_default_timezone_set('Europe/Istanbul');

// MYSQL local database
$host = '127.0.0.1';
$db = 'cs306';
$user = 'root';
$pass = '12345678';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error);
}

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
            $conn->next_result();
        }

        $room_options .= "<option value='$room_id'>Room $room_id (Available Beds: $freeBed / Capacity: $bed_count)</option>";
    }
} else {
    $room_options = "<option disabled>No available rooms</option>";
}

echo $room_options;
$conn->close();
?>