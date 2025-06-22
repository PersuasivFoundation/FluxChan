<?php
$boards = array_filter(glob("boards/*"), "is_dir");
?>

<!DOCTYPE html>
<html>
<head><title>OpenChan</title></head>
<body>
<h1>Boards</h1>
<ul>
<?php foreach ($boards as $path): 
    $board = basename($path); ?>
    <li><a href="board.php?board=<?= $board ?>">/<?= $board ?>/</a></li>
<?php endforeach; ?>
</ul>
</body>
</html>
