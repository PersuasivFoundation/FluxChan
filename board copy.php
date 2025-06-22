<?php
$board = $_GET['board'] ?? '';
if (!preg_match('/^\w+$/', $board) || !is_dir("boards/$board")) die("Invalid board.");

$boardDir = "boards/$board";
$threadListFile = "$boardDir/board.json";
$threadsDir = "$boardDir/threads";

@mkdir($threadsDir, 0777, true);
if (!file_exists($threadListFile)) file_put_contents($threadListFile, '[]');

$threads = json_decode(file_get_contents($threadListFile), true) ?? [];

// Handle new thread
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = 'Anonymous';
    $content = trim($_POST['content'] ?? '');

    if (!$content) {
        die("Thread content is required.");
    }

    // Image required
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        die("Image upload required.");
    }

    $image = $_FILES['image'];
    $ext = strtolower(pathinfo($image['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if (!in_array($ext, $allowed)) {
        die("Invalid image type.");
    }

    $id = uniqid();
    $imageName = $id . '.' . $ext;
    move_uploaded_file($image['tmp_name'], "$threadsDir/$imageName");

    $thread = [
        'id' => $id,
        'name' => $name,
        'content' => $content,
        'timestamp' => time(),
        'image' => $imageName
    ];

    $threads[] = $thread;
    file_put_contents($threadListFile, json_encode($threads, JSON_PRETTY_PRINT));
    file_put_contents("$threadsDir/$id.json", json_encode([$thread], JSON_PRETTY_PRINT));

    header("Location: board.php?board=$board");
    exit;
}

// Function to render content with greentext
function renderContent($text) {
    $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $greentexted = preg_replace('/^&gt;(.*)$/m', '<span class="greentext">&gt;$1</span>', $escaped);
    return nl2br($greentexted);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>/<?= htmlspecialchars($board) ?>/</title>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<h1>/<?= htmlspecialchars($board) ?>/</h1>

<form method="post" enctype="multipart/form-data">
    <textarea name="content" placeholder="Create new thread..." required></textarea>
    <input type="file" name="image" accept="image/*" required>  
    <button type="submit">Create Thread</button>
</form>


<?php foreach (array_reverse($threads) as $t): 
    $threadPath = "$threadsDir/{$t['id']}.json";
    $posts = file_exists($threadPath) ? json_decode(file_get_contents($threadPath), true) : [];
    $replyCount = count($posts) - 1;
?>
<div class="thread">
    <!-- OP Post -->
    <div class="post">
        <div class="post-header">
            <strong><?= htmlspecialchars($t['name']) ?: 'Anonymous' ?></strong>
            <span style="margin-left: 10px;"><?= date('m/d/y(D)H:i:s', $t['timestamp']) ?></span>
            <span class="post-id">No.<?= $t['id'] ?></span>
        </div>
        <div class="post-content">
            <?php if (!empty($t['image'])): 
    $imagePath = "boards/$board/threads/" . htmlspecialchars($t['image']);
    $fullPath = __DIR__ . "/$imagePath";
    if (file_exists($fullPath)) {
        $imageSize = filesize($fullPath);
        $imageInfo = getimagesize($fullPath);
        $formattedSize = round($imageSize / 1024) . " KB";
        $dimensions = $imageInfo[0] . "x" . $imageInfo[1];
        $filename = basename($t['image']);
    ?>
    <div class="file-info">
        File: <a href="<?= $imagePath ?>" target="_blank"><?= $filename ?></a> (<?= $formattedSize ?>, <?= $dimensions ?>)
    </div>
    <div>
        <img src="<?= $imagePath ?>" style="max-width:200px;">
    </div>
<?php } endif; ?>

            <?= renderContent(strlen($t['content']) > 500 ? substr($t['content'], 0, 500) . '…' : $t['content']) ?>
        </div>

        <!-- Inline first reply -->
        <?php if (isset($posts[1])): 
            $r = $posts[1]; ?>
        <div class="inline-reply">
            <div class="post-header">
                <strong><?= htmlspecialchars($r['name']) ?: 'Anonymous' ?></strong>
                <span style="margin-left: 10px;"><?= date('m/d/y(D)H:i:s', $r['timestamp']) ?></span>
                <span class="post-id">No.<?= $r['id'] ?></span>
            </div>
            <div class="post-content">
                <?= renderContent($r['content']) ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Recent Replies -->
    <?php 
    $recentReplies = array_slice($posts, -3);
    foreach ($recentReplies as $i => $r) {
        if ($i === 0 || ($posts[1] ?? null) === $r) continue;
    ?>
    <div class="post">
        <div class="post-header">
            <strong><?= htmlspecialchars($r['name']) ?: 'Anonymous' ?></strong>
            <span style="margin-left: 10px;"><?= date('m/d/y(D)H:i:s', $r['timestamp']) ?></span>
            <span class="post-id">No.<?= $r['id'] ?></span>
        </div>
        <div class="post-content">
            <?= renderContent($r['content']) ?>
        </div>
    </div>
    <?php } ?>

    <?php if ($replyCount > 3): ?>
        <div class="omitted"><?= $replyCount - 3 ?> repl<?= $replyCount - 3 === 1 ? "y" : "ies" ?> omitted.</div>
    <?php endif; ?>

    <div style="margin-top:10px;">
        <a href="thread.php?board=<?= $board ?>&id=<?= $t['id'] ?>">[View Thread]</a>
    </div>
</div>
<?php endforeach; ?>

</body>
</html>
