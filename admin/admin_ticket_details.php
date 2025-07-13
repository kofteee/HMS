<?php
date_default_timezone_set('Europe/Istanbul');

if (!class_exists("MongoDB\Driver\Manager")) {
    echo "MongoDB extension not installed";
    exit;
}

try {
    $uri = "mongodb+srv://username:password@cluster0.gi1h2yo.mongodb.net/?retryWrites=true&w=majority&tlsAllowInvalidCertificates=true&appName=Cluster0";
    $manager = new MongoDB\Driver\Manager($uri);
} catch (MongoDB\Driver\Exception\Exception $e) {
    echo "Connection failed: " . $e->getMessage();
    exit;
}

$ticket_details = null;
$error_message = '';
$username = $_GET['username'] ?? '';
$ticket_index = isset($_GET['ticket_index']) ? (int)$_GET['ticket_index'] : -1;

// Deactivate ticket if requested
if (isset($_POST['deactivate']) && $username !== '' && $ticket_index !== -1) {
    try {
        $filter = ['username' => $username];
        $query = new MongoDB\Driver\Query($filter);
        $cursor = $manager->executeQuery("cs306.users", $query);
        $user = current(iterator_to_array($cursor));

        if ($user && isset($user->tickets[$ticket_index])) {
            $bulk = new MongoDB\Driver\BulkWrite;
            $bulk->update(
                ['username' => $username],
                ['$set' => ["tickets.$ticket_index.Status" => false]]
            );
            $manager->executeBulkWrite('cs306.users', $bulk);
            header("Location: ../admin/admin_panel.php");
            exit;
        }
    } catch (Exception $e) {
        $error_message = "Error deactivating ticket: " . $e->getMessage();
    }
}

// Add comment if submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['comment']) && $username !== '' && $ticket_index !== -1) {
    $comment_text = $_POST['comment'] ?? '';
    
    if (empty($comment_text)) {
        $error_message = "Comment cannot be empty.";
    } else {
        try {
            $new_comment = [
                'comment' => $comment_text,
                'username' => 'admin',
                'time' => date('Y-m-d H:i:s')
            ];

            $bulk = new MongoDB\Driver\BulkWrite;
            $bulk->update(
                ['username' => $username],
                ['$push' => ["tickets.$ticket_index.comments" => $new_comment]]
            );
            $manager->executeBulkWrite('cs306.users', $bulk);
            
            header("Location: admin_ticket_details.php?username=" . urlencode($username) . "&ticket_index=" . $ticket_index);
            exit;
        } catch (Exception $e) {
            $error_message = "Error adding comment: " . $e->getMessage();
        }
    }
}

// Fetch ticket details
if ($username !== '' && $ticket_index !== -1) {
    try {
        $filter = ['username' => $username];
        $query = new MongoDB\Driver\Query($filter);
        $cursor = $manager->executeQuery("cs306.users", $query);
        $user = current(iterator_to_array($cursor));

        if ($user && isset($user->tickets[$ticket_index])) {
            $ticket_details = $user->tickets[$ticket_index];

            // Sort comments by time
            if (isset($ticket_details->comments) && is_array($ticket_details->comments)) {
                usort($ticket_details->comments, function($a, $b) {
                    $timeA = isset($a->time) ? strtotime($a->time) : 0;
                    $timeB = isset($b->time) ? strtotime($b->time) : 0;
                    return $timeA - $timeB;
                });
            }
        } else {
            $error_message = "Ticket details not found.";
        }
    } catch (MongoDB\Driver\Exception\Exception $e) {
        $error_message = "Error fetching ticket: " . $e->getMessage();
    }
} else if (!isset($_POST['comment'])) {
    $error_message = "Username or ticket index not specified.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Ticket Details</title>
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
        .btn-danger {
            background-color: #dc3545;
            border: none;
        }
        .btn-danger:hover {
            background-color: #c82333;
        }
        .comment {
            background: #fff;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            border: 1px solid #dee2e6;
        }
        .comment-meta {
            color: #6c757d;
            font-size: 0.9em;
            margin-top: 5px;
        }
        /* New styles for AJAX feedback */
        #form-feedback {
            display: none;
            margin-top: 10px;
            padding: 10px;
            border-radius: 5px;
        }
        .feedback-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .feedback-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="case">
        <h2 class="section-title">Admin - Ticket Details</h2>

        <?php if ($error_message): ?>
            <p class="alert alert-danger"><?= $error_message ?></p>
        <?php endif; ?>

        <?php if ($ticket_details): ?>
            <?php if (($ticket_details->Status ?? 0) == 1): ?>
                <form method="POST" style="margin-bottom: 20px;">
                    <button type="submit" name="deactivate" class="btn btn-danger">
                        <i class="bi bi-x-circle"></i> Deactivate Ticket
                    </button>
                </form>
            <?php endif; ?>

            <div class="item">
                <p><strong>Username:</strong> <?= $username ?? 'N/A' ?></p>
                <p><strong>Body:</strong> <?= $ticket_details->{'ticket message'} ?? 'N/A' ?></p>
                <p><strong>Status:</strong> <?= ($ticket_details->Status ?? 0) == 1 ? 'Active' : 'Inactive' ?></p>
                <p><strong>Created At:</strong> <?= $ticket_details->{'creation date'} ?? 'N/A' ?></p>
            </div>

            <div class="section comments-section">
                <h3 class="section-title">Comments:</h3>
                <div class="comments-box" id="comments-container">
                    <?php if (isset($ticket_details->comments) && is_array($ticket_details->comments) && count($ticket_details->comments) > 0): ?>
                        <?php foreach ($ticket_details->comments as $comment): ?>
                            <div class="comment">
                                <p><?= $comment->comment ?? '' ?></p>
                                <p class="comment-meta">by <?= $comment->username ?? 'N/A' ?> at <?= $comment->time ?? 'N/A' ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No comments yet.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="section add-comment-section">
                <h3 class="section-title">Add a comment</h3>
                <form id="comment-form" method="POST">
                    <div class="mb-3">
                        <textarea name="comment" rows="4" class="form-control" placeholder="Add a comment" required></textarea>
                    </div>
                    <div id="form-feedback"></div>
                    <button type="submit" class="btn btn-primary">
                        <span id="submit-text">Add Comment</span>
                        <span id="submit-spinner" class="spinner-border spinner-border-sm" role="status" aria-hidden="true" style="display: none;"></span>
                    </button>
                </form>
            </div>

        <?php endif; ?>
    </div>

    <p style="text-align: center; margin-top: 20px;">
        <a href="admin_panel.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Back to Admin Panel
        </a>
    </p>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('comment-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const form = this;
    const formData = new FormData(form);
    const feedbackDiv = document.getElementById('form-feedback');
    const submitText = document.getElementById('submit-text');
    const submitSpinner = document.getElementById('submit-spinner');
    
    // Show loading state
    submitText.textContent = 'Adding...';
    submitSpinner.style.display = 'inline-block';
    form.querySelector('button[type="submit"]').disabled = true;
    
    // Hide any previous feedback
    feedbackDiv.style.display = 'none';
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(text => {
        try {
            // Try to parse as JSON (for error responses)
            const data = JSON.parse(text);
            if (data.error) {
                throw new Error(data.error);
            }
        } catch {
            // If not JSON or no error, assume success
            const parser = new DOMParser();
            const doc = parser.parseFromString(text, 'text/html');
            const newComments = doc.getElementById('comments-container')?.innerHTML;
            
            if (newComments) {
                document.getElementById('comments-container').innerHTML = newComments;
                form.reset();
            }
        }
    })
    .catch(error => {
        // Show error feedback
        feedbackDiv.textContent = error.message || 'Error adding comment';
        feedbackDiv.className = 'feedback-error';
        feedbackDiv.style.display = 'block';
    })
    .finally(() => {
        // Restore button state
        submitText.textContent = 'Add Comment';
        submitSpinner.style.display = 'none';
        form.querySelector('button[type="submit"]').disabled = false;
        
        // Hide feedback after 3 seconds
        setTimeout(() => {
            feedbackDiv.style.display = 'none';
        }, 3000);
    });
});
</script>
</body>
</html>