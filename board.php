<?php
$board = $_GET['board'] ?? '';
if (!preg_match('/^\w+$/', $board) || !is_dir("boards/$board")) die("Invalid board.");

$boardDir = "boards/$board";
$threadsDir = "$boardDir/threads";
@mkdir($threadsDir, 0777, true);

// Handle new thread
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = 'Anonymous';
    $content = trim($_POST['content'] ?? '');

    if (!$content) die("Thread content is required.");

    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        die("Image upload required.");
    }

    $image = $_FILES['image'];
    $ext = strtolower(pathinfo($image['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($ext, $allowed)) die("Invalid image type.");

    $id = bin2hex(random_bytes(5));
    $threadFolder = "$threadsDir/$id";
    mkdir($threadFolder, 0777, true);

    $imageName = "$id.$ext";
    move_uploaded_file($image['tmp_name'], "$threadFolder/$imageName");

    $thread = [
        'id' => $id,
        'name' => $name,
        'content' => $content,
        'timestamp' => time(),
        'image' => $imageName
    ];

    file_put_contents("$threadFolder/post.json", json_encode([$thread], JSON_PRETTY_PRINT));

    header("Location: thread.php?board=$board&id=$id");
    exit;
}

function renderContent($text) {
    $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $escaped = preg_replace('/&gt;&gt;(\w+)/', '<a href="#p$1">&gt;&gt;$1</a>', $escaped);
    $escaped = preg_replace('/^&gt;(.*)$/m', '<span class="greentext">&gt;$1</span>', $escaped);
    return nl2br($escaped);
}

$threadFolders = glob("$threadsDir/*", GLOB_ONLYDIR);
usort($threadFolders, function($a, $b) {
    return filemtime("$b/post.json") <=> filemtime("$a/post.json");
});

$threadsPerPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$totalPages = ceil(count($threadFolders) / $threadsPerPage);
$start = ($page - 1) * $threadsPerPage;
$pagedThreads = array_slice($threadFolders, $start, $threadsPerPage);
?>

<!DOCTYPE html>
<html>
<head>
    <title>/<?= htmlspecialchars($board) ?>/</title>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="styles/dark.css"> <!--- gonna implement some of the switcher --->
</head>
<body>
<a href="index.php">Back to Board List.</a>
<h1>/<?= htmlspecialchars($board) ?>/</h1>

<form method="post" enctype="multipart/form-data">
    <textarea name="content" placeholder="Create new thread..." required></textarea>
    <input type="file" name="image" accept="image/*" required>
    <button type="submit">Create Thread</button>
</form>

<?php foreach ($pagedThreads as $folder):
    $posts = json_decode(file_get_contents("$folder/post.json"), true);
    $op = $posts[0];
    $replyCount = count($posts) - 1;
    $imagePath = "$folder/{$op['image']}";
?>
<div class="thread">
    <div class="post">
        <div class="post-header">
            <strong><?= htmlspecialchars($op['name']) ?: 'Anonymous' ?></strong>
            <span style="margin-left: 10px;"><?= date('m/d/y(D)H:i:s', $op['timestamp']) ?></span>
            <span class="post-id"> No.<?= $op['id'] ?></span>
        </div>
        <div class="post-content">
            <?php if (!empty($op['image']) && file_exists($imagePath)):
                $size = round(filesize($imagePath) / 1024) . " KB";
                $dim = @getimagesize($imagePath);
                $dimensions = $dim ? "{$dim[0]}x{$dim[1]}" : "unknown";
                $filename = basename($op['image']);
            ?>
          <div class="file-info">
    File: <a href="<?= $imagePath ?>" target="_blank"><?= $filename ?></a> (<?= $size ?>, <?= $dimensions ?>)
</div>
<?php endif; ?>
<div class="post-body">
    <img src="<?= $imagePath ?>" class="post-image toggle-image" data-full="false" style="max-width:200px; cursor: zoom-in; display: block; margin-bottom: 5px;">
    <div class="post-text" style="clear: both;"> <?= renderContent(strlen($op['content']) > 500 ? substr($op['content'], 0, 500) . '…' : $op['content']) ?>
    </div>
</div>

        <?php if (isset($posts[1])): $r = $posts[1]; ?>
        <div class="inline-reply">
            <div class="post-header">
                <strong><?= htmlspecialchars($r['name']) ?: 'Anonymous' ?></strong>
                <span style="margin-left: 10px;"><?= date('m/d/y(D)H:i:s', $r['timestamp']) ?></span>
                <span class="post-id">No.<?= $r['id'] ?></span>
            </div>
            <div class="post-content">
                <?php
                $replyImagePath = isset($r['image']) ? "$folder/{$r['image']}" : '';
                if (isset($r['image']) && !empty($r['image']) && file_exists($replyImagePath)) {
                    $size = round(filesize($replyImagePath) / 1024) . " KB";
                    $dim = @getimagesize($replyImagePath);
                    $dimensions = $dim ? "{$dim[0]}x{$dim[1]}" : "unknown";
                    $filename = basename($r['image']);
                    echo "<div class='file-info'>File: <a href='$replyImagePath' target='_blank'>$filename</a> ($size, $dimensions)</div>";
                    echo "<img src='$replyImagePath' class='post-image'>";

                }
                ?>
                <?= renderContent($r['content']) ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php
    
$shown = [$op['id']];
if (isset($posts[1])) $shown[] = $posts[1]['id'];

$recentReplies = array_slice($posts, -3);
foreach ($recentReplies as $r) {
    if (in_array($r['id'], $shown)) continue;


        $replyImagePath = isset($r['image']) ? "$folder/{$r['image']}" : '';
    ?>
    <div class="post">
        <div class="post-header">
            <strong><?= htmlspecialchars($r['name']) ?: 'Anonymous' ?></strong>
            <span style="margin-left: 10px;"><?= date('m/d/y(D)H:i:s', $r['timestamp']) ?></span>
            <span class="post-id">No.<?= $r['id'] ?></span>
        </div>
        <div class="post-content">
            <?php
            if (isset($r['image']) && !empty($r['image']) && file_exists($replyImagePath)) {
                $size = round(filesize($replyImagePath) / 1024) . " KB";
                $dim = @getimagesize($replyImagePath);
                $dimensions = $dim ? "{$dim[0]}x{$dim[1]}" : "unknown";
                $filename = basename($r['image']);
                echo "<div class='file-info'>File: <a href='$replyImagePath' target='_blank'>$filename</a> ($size, $dimensions)</div>";
                echo "<div><img src='$replyImagePath' style='max-width:200px;'></div>";
            }
            ?>
            <?= renderContent($r['content']) ?>
        </div>
    </div>
    <?php } ?>

<?php if ($replyCount > 3): ?>
    <div class="omitted">
        <?= $replyCount - 3 ?> repl<?= $replyCount - 3 === 1 ? "y" : "ies" ?> omitted.
    </div>
<?php endif; ?>
<div class="post-footer">
    <a href="thread.php?board=<?= $board ?>&id=<?= $op['id'] ?>">[View Thread]</a>
</div>


</div>
<?php endforeach; ?>


<script>

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.post-image').forEach(img => {
    img.addEventListener('click', () => {
      img.classList.toggle('expanded');
    });
  });
});


document.querySelectorAll('.toggle-image').forEach(img => {
    img.addEventListener('click', () => {
        const isFull = img.getAttribute('data-full') === 'true';
        if (isFull) {
            img.style.maxWidth = '200px';
            img.style.cursor = 'zoom-in';
            img.setAttribute('data-full', 'false');
        } else {
            img.style.maxWidth = '100%';
            img.style.cursor = 'zoom-out';
            img.setAttribute('data-full', 'true');
        }
    });
});
</script>
</div>
</div>
 <a href="index.php">Back to Board List.</a>

 <div class="post-pagination">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <?php if ($i == $page): ?>
            <strong>[<?= $i ?>]</strong>
        <?php else: ?>
            <a href="?board=<?= $board ?>&page=<?= $i ?>">[<?= $i ?>]</a>
        <?php endif; ?>
    <?php endfor; ?>
    <br>
   
</div>
<footer>&copy powered by <a href="https://github.com/PersuasivFoundation/FluxChan">Fluxchan</a>
</body>
</html>
