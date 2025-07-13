<?php
// MongoDB connection
date_default_timezone_set('Europe/Istanbul');

if (!class_exists("MongoDB\Driver\Manager")) {
    exit;
}

try {
    $uri = "mongodb+srv://username:password@cluster0.gi1h2yo.mongodb.net/?retryWrites=true&w=majority&tlsAllowInvalidCertificates=true&appName=Cluster0";
    $manager = new MongoDB\Driver\Manager($uri);
} catch (MongoDB\Driver\Exception\Exception $e) {
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'] ?? '';
    $ticket_message = $_POST['ticket_message'] ?? '';

    if (!empty($username) && !empty($ticket_message)) {
        try {
            // Find user
            $filter = ['username' => $username];
            $query = new MongoDB\Driver\Query($filter);
            $cursor = $manager->executeQuery("cs306.users", $query);
            $user = current(iterator_to_array($cursor));

            $new_ticket = [
                'ticket message' => $ticket_message,
                'creation date' => date('Y-m-d H:i:s'),
                'Status' => true,
                'comments' => []
            ];

            if ($user) {
                // Add ticket to existing user
                $bulk = new MongoDB\Driver\BulkWrite;
                $bulk->update(
                    ['_id' => $user->_id],
                    ['$push' => ['tickets' => $new_ticket]]
                );
            } else {
                // Create new user with ticket
                $new_user = [
                    'username' => $username,
                    'tickets' => [$new_ticket]
                ];
                $bulk = new MongoDB\Driver\BulkWrite;
                $bulk->insert($new_user);
            }

            $manager->executeBulkWrite('cs306.users', $bulk);
            
            // Return empty response (no message)
            exit;
            
        } catch (MongoDB\Driver\Exception\Exception $e) {
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create a Ticket</title>
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
        input, textarea, button {
            padding: 10px;
            margin-top: 10px;
            box-sizing: border-box;
        }
        .nav-links a {
            margin-right: 15px;
            text-decoration: none;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="case">
        <h2 class="section-title">Create a Ticket</h2>

        <div class="item">
            <form id="ticket-form" method="POST">
                <div class="mb-3">
                    <label for="username" class="form-label">Username:</label>
                    <input type="text" id="username" name="username" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="ticket_message" class="form-label">Message:</label>
                    <textarea id="ticket_message" name="ticket_message" rows="4" class="form-control" required></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Submit your ticket</button>
            </form>
        </div>
    </div>

    <p style="text-align: center; margin-top: 20px;">
        <a href="index.html" class="btn btn-secondary">
             <i class="bi bi-house-door"></i> Home
        </a>
        <a href="support_page.php" class="btn btn-secondary">
            <i class="bi bi-list"></i> View Tickets
        </a>
    </p>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('ticket-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        // Clear the form on successful submission
        document.getElementById('ticket-form').reset();
    })
    .catch(error => {
        // Silent error handling
    });
});
</script>
</body>
</html>