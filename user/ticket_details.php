<?php
if (!class_exists("MongoDB\Driver\Manager")) {
    echo "MongoDB extension not installed";
    exit;
}
date_default_timezone_set('Europe/Istanbul');

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

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['ajax']) && $_POST['ajax'] == "1") {
    $comment_text = $_POST['comment'] ?? '';
    $comment_username = $_POST['username'] ?? '';

    if (empty($comment_text) || empty($comment_username)) {
        echo json_encode(['success' => false, 'message' => 'Yorum ve kullanıcı adı boş bırakılamaz.']);
        exit;
    }

    try {
        $filter = ['username' => $username];
        $query = new MongoDB\Driver\Query($filter);
        $cursor = $manager->executeQuery("cs306.users", $query);
        $user = current(iterator_to_array($cursor));

        if ($user && isset($user->tickets[$ticket_index])) {
            $new_comment = [
                'username' => $comment_username,
                'comment' => $comment_text,
                'time' => date('Y-m-d H:i:s')
            ];

            $bulk = new MongoDB\Driver\BulkWrite;
            $update_filter = ['username' => $username];
            $update_options = ['$push' => ['tickets.' . $ticket_index . '.comments' => $new_comment]];
            $bulk->update($update_filter, $update_options);
            $manager->executeBulkWrite('cs306.users', $bulk);

            echo json_encode(['success' => true, 'comment' => $new_comment]);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => 'Bilet bulunamadı.']);
            exit;
        }
    } catch (MongoDB\Driver\Exception\Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Yorum ekleme hatası: ' . $e->getMessage()]);
        exit;
    }
}

if ($username !== '' && $ticket_index !== -1) {
    try {
        $filter = ['username' => $username];
        $query = new MongoDB\Driver\Query($filter);
        $cursor = $manager->executeQuery("cs306.users", $query);
        $user = current(iterator_to_array($cursor));

        if ($user && isset($user->tickets[$ticket_index])) {
            $ticket_details = $user->tickets[$ticket_index];
        } else {
            $error_message = "Bilet detayları bulunamadı.";
        }
    } catch (MongoDB\Driver\Exception\Exception $e) {
        $error_message = "Bilet çekme hatası: " . $e->getMessage();
    }
} else {
    $error_message = "Kullanıcı adı veya bilet indeksi belirtilmedi.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Ticket Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            margin: 40px auto;
            max-width: 900px;
            background: #f9f9f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .case { background: white; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); padding: 25px; margin-bottom: 30px; }
        .item { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 15px 20px; margin-bottom: 15px; }
        .section-title { color: #2c3e50; border-bottom: 2px solid #e9ecef; padding-bottom: 10px; margin-bottom: 20px; }
        .comments-box { border: 1px solid #ccc; padding: 10px; margin-top: 10px; }
        .comment { border-bottom: 1px solid #eee; padding-bottom: 5px; margin-bottom: 5px; }
        .comment-meta { font-size: 0.9em; color: #555; }
    </style>
</head>
<body>

<div class="container">
    <div class="case">
        <h2 class="section-title">Ticket Details</h2>

        <?php if ($error_message): ?>
            <p class="alert alert-danger"><?= $error_message ?></p>
        <?php endif; ?>

        <?php if ($ticket_details): ?>
            <div class="item">
                <p><strong>Username:</strong> <?= htmlspecialchars($username) ?></p>
                <p><strong>Body:</strong> <?= htmlspecialchars($ticket_details->{'ticket message'} ?? 'N/A') ?></p>
                <p><strong>Status:</strong> <?= ($ticket_details->Status ?? 0) == 1 ? 'Active' : 'Inactive' ?></p>
                <p><strong>Created At:</strong> <?= htmlspecialchars($ticket_details->{'creation date'} ?? 'N/A') ?></p>
            </div>

            <div class="section comments-section">
                <h3 class="section-title">Comments:</h3>
                <div class="comments-box" id="comments-container">
                <?php if (!empty($ticket_details->comments)): ?>
                        <?php
                        // Yorumları diziye çevir (MongoDB BSON tipi olabilir)
                        $commentsArray = is_array($ticket_details->comments) ? $ticket_details->comments : iterator_to_array($ticket_details->comments);
                        usort($commentsArray, fn($a, $b) => strtotime($a->time ?? '') <=> strtotime($b->time ?? ''));
                        foreach ($commentsArray as $comment): ?>
                            <div class="comment">
                                <p><?= htmlspecialchars($comment->comment ?? '') ?></p>
                                <p class="comment-meta">by <?= htmlspecialchars($comment->username ?? '') ?> at <?= htmlspecialchars($comment->time ?? '') ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p id="no-comments">No comments yet.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="section add-comment-section mt-4">
                <h3 class="section-title">Add a comment</h3>
                <form id="comment-form">
                    <div class="mb-3">
                        <textarea name="comment" id="comment" rows="4" class="form-control" placeholder="Add a comment" required></textarea>
                    </div>
                    <div class="mb-3">
                        <input type="text" name="username" id="comment-username" class="form-control" placeholder="Your Username" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Add Comment</button>
                    <p id="comment-error" class="text-danger mt-2"></p>
                </form>
            </div>

        <?php endif; ?>
    </div>

    <p class="text-center">
        <a href="support_page.php" class="btn btn-secondary">
             <i class="bi bi-arrow-left"></i> Back to Tickets
        </a>
    </p>
</div>

<script>
document.getElementById("comment-form").addEventListener("submit", function (e) {
    e.preventDefault();

    const comment = document.getElementById("comment").value.trim();
    const username = document.getElementById("comment-username").value.trim();
    const errorElem = document.getElementById("comment-error");

    if (!comment || !username) {
        errorElem.textContent = "Yorum ve kullanıcı adı boş bırakılamaz.";
        return;
    }

    const formData = new FormData();
    formData.append("comment", comment);
    formData.append("username", username);
    formData.append("ajax", "1");

    fetch(window.location.href, {
        method: "POST",
        body: formData
    }).then(res => res.json())
      .then(data => {
        if (data.success) {
            errorElem.textContent = "";
            const container = document.getElementById("comments-container");
            const newComment = document.createElement("div");
            newComment.className = "comment";
            newComment.innerHTML = `<p>${data.comment.comment}</p><p class="comment-meta">by ${data.comment.username} at ${data.comment.time}</p>`;

            const noCommentsMsg = document.getElementById("no-comments");
            if (noCommentsMsg) noCommentsMsg.remove();

            container.appendChild(newComment);

            document.getElementById("comment").value = "";
            document.getElementById("comment-username").value = "";
        } else {
            errorElem.textContent = data.message;
        }
    }).catch(err => {
        errorElem.textContent = "Bir hata oluştu. Lütfen tekrar deneyin.";
    });
});
</script>

</body>
</html>