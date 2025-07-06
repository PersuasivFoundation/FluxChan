<?php
session_start();
ini_set('memory_limit', '1G'); 
set_time_limit(100);              

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    error_log("CSRF token mismatch or missing");
    die("Invalid CSRF token.");
}


    $board = $_GET['board'] ?? '';
    $id = $_GET['id'] ?? '';

    if (!preg_match('/^\w+$/', $board) || !preg_match('/^\w+$/', $id)) {
        error_log("Invalid board or ID in POST request: board=$board, id=$id");
        die("Invalid board or ID.");
    }

    $threadDir = __DIR__ . "/boards/$board/threads/$id";
    $threadFile = "$threadDir/post.json";

    if (!is_dir($threadDir)) {
        error_log("Attempt to post to non-existent thread directory: $threadDir");
        header("Location: 404.php");
        exit;
    }
    if (!file_exists($threadFile)) {
        $posts = [];
    } else {
        $posts = json_decode(file_get_contents($threadFile), true) ?? [];
    }

    $name = 'Anonymous';
    $content = trim($_POST['content']);

    // Check for uploaded images: prioritize 'manualImage' then 'image' (clipboard)
    $uploadedImage = null;
    if (isset($_FILES['manualImage']) && $_FILES['manualImage']['error'] === UPLOAD_ERR_OK) {
        $uploadedImage = $_FILES['manualImage'];
    } elseif (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadedImage = $_FILES['image'];
    }

    // If no content and no image, redirect back
    if (!$content && !$uploadedImage) {
        header("Location: thread.php?board=$board&id=$id");
        exit;
    }

    $reply = [
        'id' => uniqid(),
        'name' => $name,
        'content' => $content,
        'timestamp' => time()
    ];

    if ($uploadedImage) {
        $image = $uploadedImage;
        $ext = strtolower(pathinfo($image['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($ext, $allowed)) {
            $imageName = uniqid() . '.' . $ext;
            $target = "$threadDir/$imageName";
            
            if (move_uploaded_file($image['tmp_name'], $target)) {
                $reply['image'] = $imageName;
            } else {
                error_log("Failed to move uploaded file: " . $image['tmp_name'] . " to " . $target);
            }
        } else {
            error_log("Attempted to upload invalid file type: " . $image['name']);
        }
    }

    $posts[] = $reply;
    file_put_contents($threadFile, json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    header("Location: thread.php?board=$board&id=$id");
    exit;
}

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
    
    $escaped = preg_replace('/&gt;&gt;(\w+)/', '<a href="javascript:void(0);" class="quotelink" data-target="p$1">&gt;&gt;$1</a>', $escaped);
    
    $escaped = preg_replace('/^&gt;(.*)$/m', '<span class="greentext">&gt;$1</span>', $escaped);
    
    $escaped = preg_replace('/^&gt;&gt;&gt;(.*)$/m', '<blockquote>$1</blockquote>', $escaped);

    return nl2br($escaped);
}



?>
<!DOCTYPE html>
<html>
<head>
    <title>/<?= htmlspecialchars($board) ?>/ - Thread</title>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="styles/dark.css">

</head>
<body>

<h2><a href="board.php?board=<?= htmlspecialchars($board) ?>">← Back to /<?= htmlspecialchars($board) ?>/</a></h2>

<?php foreach ($posts as $p): ?>
    <div class="post" id="p<?= htmlspecialchars($p['id']) ?>">
        <div class="post-header">
            <strong><?= htmlspecialchars($p['name'] ?? 'Anonymous') ?></strong>
            <span style="margin-left: 10px;"><?= date('m/d/y(D)H:i:s', $p['timestamp']) ?></span>
            <span class="post-id">No.<?= htmlspecialchars($p['id']) ?></span>
            <a href="javascript:void(0);" 
   class="quote-button" 
   data-post-id="<?= htmlspecialchars($p['id']) ?>">
   [Quote]
</a>


        </div>
        <div class="post-content">
            <?php
            if (!empty($p['image'])):
                $imageFile = "$threadDir/" . basename($p['image']);
                $imagePath = "$threadDir/" . basename($p['image']);
$imagePath = "$threadDir/" . basename($p['image']);
$imageURL = sprintf("boards/%s/threads/%s/%s", rawurlencode($board), rawurlencode($id), rawurlencode($p['image']));

                if (file_exists($imageFile)):
                    $imageSize = round(filesize($imageFile) / 1024) . " KB";
                    $info = @getimagesize($imageFile);
                    $dimensions = ($info) ? $info[0] . "x" . $info[1] : "N/A";
            ?>
                <div class="file-info">
                    File: <a href="<?= htmlspecialchars($imageURL) ?>" target="_blank"><?= htmlspecialchars($p['image']) ?></a> (<?= $imageSize ?>, <?= $dimensions ?>)
                </div>
                <div>
                    <?php
    $imagePath = "$threadDir/" . basename($p['image']);
    $fullURL = "boards/$board/threads/$id/" . rawurlencode($p['image']);
$displaySrc = $fullURL;
?>
<img src="<?= htmlspecialchars($displaySrc) ?>" class="toggle-image" data-fullsrc="<?= htmlspecialchars($fullURL) ?>">


                </div>
            <?php 
                endif; 
            endif; 
            ?>

            <?= renderContent($p['content']) ?>
        </div>
    </div>
<?php endforeach; ?>

<hr>

<form method="post" enctype="multipart/form-data" id="postForm">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

    <textarea name="content" id="postContent" placeholder="Reply..." required></textarea><br>
    <input type="file" name="image" id="imageInput" accept="image/*" style="display:none;"><input type="file" name="manualImage" accept="image/*" id="manualImageInput"><br> <span id="clipboardImageStatus" style="display: none;"></span>
    <div id="imagePreviewContainer" style="margin-top: 10px;"></div>

    <button type="submit">Post Reply</button>
    
</form>

<script>
  function quotePost(postId) {
    const textarea = document.getElementById('postContent');
    const quoteLine = `>>${postId}\n`;

    if (textarea.value.trim() === '') {
        textarea.value = quoteLine;
    } else {
        if (!textarea.value.endsWith('\n')) {
            textarea.value += '\n';
        }
        textarea.value += quoteLine;
    }

    textarea.focus();
}


    function handleQuoteLinkClick(event) {
        event.preventDefault();

        const targetId = this.dataset.target;
        const targetElement = document.getElementById(targetId);

        if (targetElement) {
            targetElement.scrollIntoView({ behavior: 'smooth', block: 'start' });

            document.querySelectorAll('.post.highlight').forEach(el => {
                el.classList.remove('highlight');
            });

            targetElement.classList.add('highlight');

            setTimeout(() => {
                targetElement.classList.remove('highlight');
            }, 1500); 
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.quotelink').forEach(link => {
            link.addEventListener('click', handleQuoteLinkClick);
        });

        if (window.location.hash) {
            const initialHashId = window.location.hash.substring(1);
            const initialTargetElement = document.getElementById(initialHashId);
            
            if (initialTargetElement && initialTargetElement.classList.contains('post')) {
                initialTargetElement.classList.add('highlight');
                setTimeout(() => {
                    initialTargetElement.classList.remove('highlight');
                }, 1500); 
            }
        }

        const postContentTextarea = document.getElementById('postContent');
        const imageInput = document.getElementById('imageInput');
        const manualImageInput = document.getElementById('manualImageInput'); 
        const clipboardImageStatus = document.getElementById('clipboardImageStatus'); 

        postContentTextarea.addEventListener('paste', (event) => {
            const items = (event.clipboardData || event.originalEvent.clipboardData).items;
            let foundImage = false;

            for (const item of items) {
                if (item.type.indexOf('image') !== -1) {
                    const blob = item.getAsFile();
                    if (blob) {
                        
                        const file = new File([blob], "clipboard-image.png", { type: blob.type });
                        
                        // Create a DataTransfer object to hold the file
                        const dataTransfer = new DataTransfer();
                        dataTransfer.items.add(file);
                        
                        // Assign the DataTransfer object's files to the hidden input
                        imageInput.files = dataTransfer.files;
                        foundImage = true;
                        event.preventDefault(); 
                        
                        
                        clipboardImageStatus.textContent = `Pasted: ${file.name}`;
                        clipboardImageStatus.style.display = 'inline';
                        
                        
                        manualImageInput.value = ''; 
                        previewImage(file);  
                        break;
                    }
                }
            }
        });

        // Clear the hidden clipboard image input and status if the user manually selects an image
        manualImageInput.addEventListener('change', (event) => {
            if (event.target.files.length > 0) {
                imageInput.value = ''; 
                clipboardImageStatus.style.display = 'none'; 
                clipboardImageStatus.textContent = ''; 
                 previewImage(file); 
            }
        });

        // Also clear clipboard image status if the user focuses on textarea and no image is manually selected
        postContentTextarea.addEventListener('focus', () => {
            if (manualImageInput.files.length === 0 && imageInput.files.length === 0) {
                clipboardImageStatus.style.display = 'none';
                clipboardImageStatus.textContent = '';
            }
        });
    });

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.toggle-image').forEach(img => {
        const fullSrc = img.getAttribute('data-fullsrc');
        const originalSrc = img.getAttribute('src');

        img.addEventListener('click', () => {
            if (!img.classList.contains('expanded')) {
                img.setAttribute('src', fullSrc);
                img.classList.add('expanded');
            } else {
                img.setAttribute('src', originalSrc);
                img.classList.remove('expanded');
            }
        });
    });
});



// Now save that scroll position so you wouldn't get frusturated
document.getElementById('postForm').addEventListener('submit', () => {
    localStorage.setItem('scrollY', window.scrollY);
});


window.addEventListener('load', () => {
    const savedScrollY = localStorage.getItem('scrollY');
    if (savedScrollY !== null) {
        window.scrollTo(0, parseInt(savedScrollY));
        localStorage.removeItem('scrollY'); // Only use once
    }
});
document.addEventListener('DOMContentLoaded', () => {



    document.querySelectorAll('.quote-button').forEach(btn => {
        btn.addEventListener('click', () => {
            const postId = btn.dataset.postId;
            const content = btn.dataset.postContent;
            quotePost(postId, content);
        });
    });
});

function previewImage(file) {
    const container = document.getElementById('imagePreviewContainer');
    container.innerHTML = ''; // clear previous

    const reader = new FileReader();
    reader.onload = function (e) {
        const img = document.createElement('img');
        img.src = e.target.result;
        img.style.maxWidth = '200px';
        img.style.maxHeight = '200px';
        img.style.border = '1px solid #555';
        img.style.marginTop = '5px';
        container.appendChild(img);
    };
    reader.readAsDataURL(file);
}

</script>

</body>
<footer>&copy powered by <a href="https://github.com/PersuasivFoundation/FluxChan">Fluxchan</a>
</html>
