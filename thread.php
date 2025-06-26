<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
               onclick="quotePost('<?= htmlspecialchars($p['id']) ?>', '<?= addslashes(preg_replace('/\r?\n/', '\n', $p['content'])) ?>')" 
               class="quote-button">[Quote]</a>
        </div>
        <div class="post-content">
            <?php
            if (!empty($p['image'])):
                $imageFile = "$threadDir/" . basename($p['image']);
                $imageURL = "boards/$board/threads/$id/" . rawurlencode($p['image']);

                if (file_exists($imageFile)):
                    $imageSize = round(filesize($imageFile) / 1024) . " KB";
                    $info = @getimagesize($imageFile);
                    $dimensions = ($info) ? $info[0] . "x" . $info[1] : "N/A";
            ?>
                <div class="file-info">
                    File: <a href="<?= htmlspecialchars($imageURL) ?>" target="_blank"><?= htmlspecialchars($p['image']) ?></a> (<?= $imageSize ?>, <?= $dimensions ?>)
                </div>
                <div>
                    <img src="<?= htmlspecialchars($imageURL) ?>" class="toggle-image" data-fullsrc="<?= htmlspecialchars($imageURL) ?>">

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
    <textarea name="content" id="postContent" placeholder="Reply..." required></textarea><br>
    <input type="file" name="image" id="imageInput" accept="image/*" style="display:none;"><input type="file" name="manualImage" accept="image/*" id="manualImageInput"><br> <span id="clipboardImageStatus" style="display: none;"></span>
    <button type="submit">Post Reply</button>
</form>

<script>
    function quotePost(postId, postContent) {
        const textarea = document.getElementById('postContent');
        let quoteText = '';

        if (postContent.includes('\n')) {
            quoteText = postContent.split('\n')
                                     .map(line => `>>>${line.trim()}`)
                                     .join('\n') + '\n';
        } else {
            quoteText = `>>${postId}\n`;
        }
        
        if (textarea.value.trim() === '') {
            textarea.value = quoteText;
        } else {
            if (!textarea.value.endsWith('\n')) {
                textarea.value += '\n';
            }
            textarea.value += quoteText;
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
        const imageInput = document.getElementById('imageInput'); // Hidden input for clipboard image
        const manualImageInput = document.getElementById('manualImageInput'); // Manual upload input
        const clipboardImageStatus = document.getElementById('clipboardImageStatus'); // Span to show status

        postContentTextarea.addEventListener('paste', (event) => {
            const items = (event.clipboardData || event.originalEvent.clipboardData).items;
            let foundImage = false;

            for (const item of items) {
                if (item.type.indexOf('image') !== -1) {
                    const blob = item.getAsFile();
                    if (blob) {
                        // Create a new File object with a generic name and the correct type
                        const file = new File([blob], "clipboard-image.png", { type: blob.type });
                        
                        // Create a DataTransfer object to hold the file
                        const dataTransfer = new DataTransfer();
                        dataTransfer.items.add(file);
                        
                        // Assign the DataTransfer object's files to the hidden input
                        imageInput.files = dataTransfer.files;
                        foundImage = true;
                        event.preventDefault(); // Prevent the image data from being pasted as text
                        
                        // Display the filename and make the status visible
                        clipboardImageStatus.textContent = `Pasted: ${file.name}`;
                        clipboardImageStatus.style.display = 'inline';
                        
                        // Clear manual image selection if clipboard image is pasted
                        manualImageInput.value = ''; 
                        
                        break;
                    }
                }
            }
        });

        // Clear the hidden clipboard image input and status if the user manually selects an image
        manualImageInput.addEventListener('change', (event) => {
            if (event.target.files.length > 0) {
                imageInput.value = ''; // Clear the hidden clipboard image input
                clipboardImageStatus.style.display = 'none'; // Hide the clipboard status
                clipboardImageStatus.textContent = ''; // Clear the text
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
        img.addEventListener('click', () => {
            img.classList.toggle('expanded');
        });
    });
});


// Save scroll position on form submit
document.getElementById('postForm').addEventListener('submit', () => {
    localStorage.setItem('scrollY', window.scrollY);
});

// Restore scroll position on page load
window.addEventListener('load', () => {
    const savedScrollY = localStorage.getItem('scrollY');
    if (savedScrollY !== null) {
        window.scrollTo(0, parseInt(savedScrollY));
        localStorage.removeItem('scrollY'); // Only use once
    }
});

</script>

</body>
</html>
