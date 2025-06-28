<?php
$boards = array_filter(glob("boards/*"), "is_dir");
?>

<!DOCTYPE html>
<html>
<head><title>FluxChan</title></head>
<body>
     <link rel="stylesheet" href="styles/dark.css">
<h1>Boards</h1>
<ul>
<?php foreach ($boards as $path): 
    $board = basename($path); ?>
    <li><a href="board.php?board=<?= $board ?>">/<?= $board ?>/</a></li>
<?php endforeach; ?>
</ul>
<footer>&copy powered by <a href="https://github.com/PersuasivFoundation/FluxChan">Fluxchan</a>
</body>
</html>
