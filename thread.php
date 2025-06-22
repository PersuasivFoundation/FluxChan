<?php
$board = $_GET['board'] ?? '';
$id = $_GET['id'] ?? '';

if (!preg_match('/^\w+$/', $board) || !preg_match('/^\w+$/', $id)) {
    die("Invalid board or ID.");
}

$threadDir = __DIR__ . "/boards/$board/threads/$id";
$threadFile = "$threadDir/post.json";

if (!is_dir($threadDir)) {
    header("Location: 404.php");
    exit;
}
if (!file_exists($threadFile)) {
    header("Location: 404.php");
    exit;
}


$posts = json_decode(file_get_contents($threadFile), true) ?? [];

function renderContent($text) {
    $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $escaped = preg_replace('/&gt;&gt;(\w+)/', '<a href="#p$1">&gt;&gt;$1</a>', $escaped);
    $escaped = preg_replace('/^&gt;(.*)$/m', '<span class="greentext">&gt;$1</span>', $escaped);
    return nl2br($escaped);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>/<?= htmlspecialchars($board) ?>/ - Thread</title>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<h2><a href="board.php?board=<?= htmlspecialchars($board) ?>">← Back to /<?= htmlspecialchars($board) ?>/</a></h2>

<?php foreach ($posts as $p): ?>
    <div class="post" id="p<?= htmlspecialchars($p['id']) ?>">
        <div class="post-header">
            <strong><?= htmlspecialchars($p['name'] ?? 'Anonymous') ?></strong>
            <span style="margin-left: 10px;"><?= date('m/d/y(D)H:i:s', $p['timestamp']) ?></span>
            <span class="post-id">No.<?= htmlspecialchars($p['id']) ?></span>
        </div>
        <div class="post-content">
            <?php
            // Image from JSON
            if (!empty($p['image'])):
                $imageFile = "$threadDir/" . basename($p['image']);
                $imageURL = "boards/$board/threads/$id/" . rawurlencode($p['image']);

                if (file_exists($imageFile)):
                    $imageSize = round(filesize($imageFile) / 1024) . " KB";
                    $info = getimagesize($imageFile);
                    $dimensions = $info[0] . "x" . $info[1];
            ?>
                <div class="file-info">
                    File: <a href="<?= htmlspecialchars($imageURL) ?>" target="_blank"><?= htmlspecialchars($p['image']) ?></a> (<?= $imageSize ?>, <?= $dimensions ?>)
                </div>
                <div>
                    <img src="<?= htmlspecialchars($imageURL) ?>" style="max-width:200px;">
                </div>
            <?php endif; endif; ?>

            <?= renderContent($p['content']) ?>
        </div>
    </div>
<?php endforeach; ?>

<hr>

<form method="post" enctype="multipart/form-data">
    <textarea name="content" placeholder="Reply..." required></textarea>
    <input type="file" name="image" accept="image/*">
    <button type="submit">Post Reply</button>
</form>

</body>
</html>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = 'Anonymous';
    $content = trim($_POST['content']);
    if (!$content) exit;

    $reply = [
        'id' => uniqid(),
        'name' => $name,
        'content' => $content,
        'timestamp' => time()
    ];

    // Handle image
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $image = $_FILES['image'];
        $ext = strtolower(pathinfo($image['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($ext, $allowed)) {
            $imageName = uniqid() . '.' . $ext;
            $target = "$threadDir/$imageName";
            move_uploaded_file($image['tmp_name'], $target);
            $reply['image'] = $imageName;
        }
    }

    $posts[] = $reply;
    file_put_contents($threadFile, json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    header("Location: thread.php?board=$board&id=$id");
    exit;
}
?>
