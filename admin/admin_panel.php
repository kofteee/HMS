<?php
// Set timezone to Turkey
date_default_timezone_set('Europe/Istanbul');

if (!class_exists("MongoDB\Driver\Manager")) {
    echo "MongoDB extension not installed";
    exit;
}

try {
    $uri = "mongodb+srv://username:password@cluster0.gi1h2yo.mongodb.net/?retryWrites=true&w=majority&tlsAllowInvalidCertificates=true&appName=Cluster0";
    $manager = new MongoDB\Driver\Manager($uri);

    // Get all users
    $query = new MongoDB\Driver\Query([]);
    $cursor = $manager->executeQuery("cs306.users", $query);
    $users = iterator_to_array($cursor);
} catch (MongoDB\Driver\Exception\Exception $e) {
    echo "Connection failed: " . $e->getMessage();
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 40px auto;
            max-width: 1200px;
            background: #f9f9f9;
            color: #333;
        }
        .case {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgb(0 0 0 / 0.1);
            padding: 20px 25px 30px;
            margin-bottom: 30px;
        }
        .ticket-box {
            margin-top: 15px;
            padding: 15px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            background: #f8f9fa;
        }
        .ticket-box:hover {
            background: #e9ecef;
        }
        .section-title {
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
        }
        .btn-primary {
            background-color: #2980b9;
            border: none;
        }
        .btn-primary:hover {
            background-color: #1f618d;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="case">
        <h2 class="section-title">Admin Panel </h2>

        <div class="tickets-container">
            <?php
            $hasActiveTickets = false;
            foreach ($users as $user) {
                if (isset($user->tickets) && is_array($user->tickets)) {
                    foreach ($user->tickets as $index => $ticket) {
                        if (($ticket->Status ?? 0) == 1) {
                            $hasActiveTickets = true;
                            echo "<div class='ticket-box'>";
                            echo "<div class='row'>";
                            echo "<div class='col-md-8'>";
                            echo "<strong>Username:</strong> " . ($user->username ?? "N/A") . "<br>";
                            echo "<strong>Status:</strong> " . (($ticket->Status ?? 0) == 1 ? 'Active' : 'Inactive') . "<br>";
                            echo "<strong>Body:</strong> " . ($ticket->{'ticket message'} ?? "N/A") . "<br>";
                            echo "<strong>Created At:</strong> " . ($ticket->{'creation date'} ?? "N/A") . "<br>";
                            echo "</div>";
                            echo "<div class='col-md-4 text-end'>";
                            echo "<a href='admin_ticket_details.php?username=" . urlencode($user->username) . "&ticket_index=" . $index . "' class='btn btn-primary'>View Details</a>";
                            echo "</div>";
                            echo "</div>";
                            echo "</div>";
                        }
                    }
                }
            }
            if (!$hasActiveTickets) {
                echo "<p class='alert alert-info'>No active tickets found.</p>";
            }
            ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 