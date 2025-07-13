<?php
if (!class_exists("MongoDB\Driver\Manager")) {
    echo "MongoDB extension not installed";
    exit;
}
date_default_timezone_set('Europe/Istanbul');

try {
    $uri = "mongodb+srv://username:password@cluster0.gi1h2yo.mongodb.net/?retryWrites=true&w=majority&tlsAllowInvalidCertificates=true&appName=Cluster0";
    $manager = new MongoDB\Driver\Manager($uri);

    // Tüm kullanıcıları çek
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
    <title>Support Page</title>
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
        h1, h2, h3 {
            color: #2c3e50;
        }
        .case {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgb(0 0 0 / 0.1);
            padding: 20px 25px 30px;
            margin-bottom: 30px;
        }
        .item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px 20px;
            margin-bottom: 15px;
            border: 1px solid #dee2e6;
        }
        .item:last-child {
            margin-bottom: 0;
        }
        .section-title {
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
        }
        .btn-primary {
            padding: 8px 16px;
            border: none;
            background-color: #2980b9;
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary:hover {
            background-color: #1f618d;
            color: white;
        }
        .btn-secondary {
            padding: 8px 16px;
            border: none;
            background-color: #95a5a6;
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        .btn-secondary:hover {
            background-color: #7f8c8d;
            color: white;
        }
        /* Existing styles adjusted */
        select, button { /* Adjusted for Bootstrap, removed width: 100% */
             padding: 10px;
             margin-top: 10px;
        }
        .ticket-box { 
            margin-top: 15px;
            padding: 10px;
            border: 1px solid #ccc;
        }
        .home-button { /* This style might be overridden by .btn */
             background-color: #4CAF50;
             color: white;
             border: none;
             cursor: pointer;
             width: 200px;
             margin: 20px auto;
             display: block;
        }
        .home-button:hover {
             background-color: #45a049;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="case">
        <h2 class="section-title">Support Page</h2>

        <div class="item">
            <form method="GET">
                <label for="username" class="form-label">Select User:</label>
                <select name="username" id="username" class="form-select" onchange="this.form.submit()">
                    <option value="">-- Choose a user --</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?= $user->username ?>" <?= (isset($_GET['username']) && $_GET['username'] === $user->username) ? 'selected' : '' ?>>
                            <?= $user->username ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <div class="item">
            <h3 class="section-title">Results</h3>
            <?php
            if (isset($_GET['username']) && $_GET['username'] !== "") {
                $username = $_GET['username'];
                try {
                    // Subcollection taklidi: collection içinde her user için tickets dökümanı olabilir.
                    $filter = ['username' => $username];
                    $query = new MongoDB\Driver\Query($filter);
                    $cursor = $manager->executeQuery("cs306.users", $query);
                    $userData = current(iterator_to_array($cursor));

                    if (isset($userData->tickets) && is_array($userData->tickets) && count($userData->tickets) > 0) {
                        $index = 0;
                        $hasActiveTickets = false;
                        foreach ($userData->tickets as $ticket) {
                            // Only display tickets with Status = true
                            if (($ticket->Status ?? 0) == 1) {
                                $hasActiveTickets = true;
                                echo "<div class='ticket-box item'>"; // Added item class here
                                echo "<strong>Status:</strong> " . (($ticket->Status ?? 0) == 1 ? 'Active' : 'Inactive') . "<br>";
                                echo "<strong>Body:</strong> " . ($ticket->{'ticket message'} ?? "N/A") . "<br>";
                                echo "<strong>Created At:</strong> " . ($ticket->{'creation date'} ?? "N/A") . "<br><strong>Username:</strong> " . ($username ?? "N/A") . "<br>";
                                // View Details linkini ekle
                                echo "<a href='ticket_details.php?username=" . urlencode($username) . "&ticket_index=" . $index . "' class=\'btn btn-primary btn-sm\'>View Details</a>"; // Added btn classes
                                echo "</div>";
                            }
                            $index++;
                        }
                        if (!$hasActiveTickets) {
                            echo "<p>No active tickets found for <strong>$username</strong>.</p>";
                        }
                    } else {
                        echo "<p>No tickets found for <strong>$username</strong>.</p>";
                    }
                } catch (Exception $e) {
                    echo "Error fetching tickets: " . $e->getMessage();
                }
            } else {
                echo "<p>Please select a user to see tickets.</p>";
            }
            ?>
        </div>
    </div>

    <p style="text-align: center; margin-top: 20px;">
        <a href="/user" class="btn btn-secondary">
            <i class="bi bi-house-door"></i> Go to Homepage
        </a>
         <a href="create_ticket.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Create Ticket
        </a>
    </p>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>