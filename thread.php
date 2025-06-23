<?php


$board = $_GET['board'] ?? '';
$id = $_GET['id'] ?? '';

// Validate input here—this might trigger a redirect, so it needs to happen before any output
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
    header("Location: 404.php"); // 404.php
    exit; 
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = 'Anonymous';
    $content = trim($_POST['content']);

    if (!$content) {
        header("Location: thread.php?board=$board&id=$id"); 
        exit;
    }

    $newPostId = uniqid();
    $reply = [
        'id' => $newPostId,
        'name' => $name,
        'content' => $content,
        'timestamp' => time()
    ];

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $image = $_FILES['image'];
        $ext = strtolower(pathinfo($image['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (in_array($ext, $allowed)) {
            $imageName = uniqid() . '.' . $ext;
            $target = "$threadDir/$imageName";
            if (move_uploaded_file($image['tmp_name'], $target)) {
                $reply['image'] = $imageName;
            }
        }
    }

    $posts = json_decode(file_get_contents($threadFile), true) ?? []; 
    $posts[] = $reply;

    if (file_put_contents($threadFile, json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
        header("Location: thread.php?board=$board&id=$id");
        exit;
    } else {
        die("Error writing to post file.");
    }
}


$posts = json_decode(file_get_contents($threadFile), true) ?? [];


function renderContent($text, $allPosts) {
    $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');


    $escaped = preg_replace_callback('/&gt;&gt;(\w+)/', function($matches) use ($allPosts) {
        $quoteId = $matches[1];
        $quotedPost = null;

        foreach ($allPosts as $post) {
            if ($post['id'] === $quoteId) {
                $quotedPost = $post;
                break;
            }
        }

        if ($quotedPost) {
            $link = '<a href="#p' . htmlspecialchars($quoteId) . '">&gt;&gt;' . htmlspecialchars($quoteId) . '</a>';
            $preview = '<span class="quote-preview" style="display:none;"></span>'; // Populated by JS
            return $link . $preview;
        } else {
            return '&gt;&gt;' . htmlspecialchars($quoteId);
        }
    }, $escaped);

    // Classic Greentext Handle.
    $escaped = preg_replace('/^&gt;(.*)$/m', '<span class="greentext">&gt;$1</span>', $escaped);

    return nl2br($escaped);
}

// Now start Outputting the HTML.
?>
<!DOCTYPE html>
<html>
<head>
    <title>/<?= htmlspecialchars($board) ?>/ - Thread</title>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="styles/dark.css">
    <style>
       
    </style>
</head>
<body>
<style>

.toggleable-image {
    width: 200px;
    max-width: 100%;
    height: auto;
    cursor: pointer;
    transition: all 0.3s ease;
}

.toggleable-image.expanded {
    width: auto;
    max-width: none;
    height: auto;
    z-index: 10;
    position: relative;
}


</style>
<h2><a href="board.php?board=<?= htmlspecialchars($board) ?>">← Back to /<?= htmlspecialchars($board) ?>/</a></h2>

<?php foreach ($posts as $p): ?>
    <div class="post" id="p<?= htmlspecialchars($p['id']) ?>">
        <div class="post-header">
            <strong><?= htmlspecialchars($p['name'] ?? 'Anonymous') ?></strong>
            <span style="margin-left: 10px;"><?= date('m/d/y(D)H:i:s', $p['timestamp']) ?></span>
            <span class="post-id" onclick="insertQuoteId('<?= htmlspecialchars($p['id']) ?>')">No.<?= htmlspecialchars($p['id']) ?></span>
        </div>
        <div class="post-content">
            <?php
            if (!empty($p['image'])):
                $imageFile = "$threadDir/" . basename($p['image']);
                $imageURL = "boards/$board/threads/$id/" . rawurlencode($p['image']);

                if (file_exists($imageFile)):
                    $imageSize = round(filesize($imageFile) / 1024) . " KB";
                    $info = @getimagesize($imageFile);
                    $dimensions = ($info) ? $info[0] . "x" . $info[1] : 'N/A';
            ?>
                <div class="file-info">
                    File: <a href="<?= htmlspecialchars($imageURL) ?>" target="_blank"><?= htmlspecialchars($p['image']) ?></a> (<?= $imageSize ?>, <?= $dimensions ?>)
                </div>
                <div>
                    <img src="<?= htmlspecialchars($imageURL) ?>" class="toggleable-image">
                </div>
            <?php endif; endif; ?>

            <?= renderContent($p['content'], $posts) ?>
        </div>
    </div>
<?php endforeach; ?>

<hr>

<form method="post" enctype="multipart/form-data">
    <textarea name="content" placeholder="Reply..." required></textarea>
    <input type="file" name="image" accept="image/*" id="imageInput">
    <button type="submit">Post Reply</button>
</form>

<script>
window.onload = function () {
    const savedPosition = localStorage.getItem('scrollPosition');
    if (savedPosition !== null) {
        window.scrollTo(0, parseInt(savedPosition, 10));
        localStorage.removeItem('scrollPosition');
    }
};

document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('form');

    if (form) {
        form.addEventListener('submit', () => {
            localStorage.setItem('scrollPosition', window.scrollY);
        });
    }
});


document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('img.toggleable-image').forEach(img => {
        img.addEventListener('click', function () {
            this.classList.toggle('expanded');
        });
    });
});

    function insertQuoteId(postId) {
        const textarea = document.querySelector('textarea[name="content"]');
        if (textarea) {
            const currentContent = textarea.value.trim();
            const quoteText = `>>${postId}\n`;

            if (currentContent.length > 0 && !currentContent.endsWith('\n')) {
                textarea.value += '\n' + quoteText;
            } else {
                textarea.value += quoteText;
            }
            textarea.focus();
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('a[href^="#p"]').forEach(link => {
            link.addEventListener('mouseenter', function() {
                const targetId = this.getAttribute('href').substring(1);
                const quotedPostElement = document.getElementById(targetId);
                const previewElement = this.nextElementSibling;

                if (quotedPostElement && previewElement && previewElement.classList.contains('quote-preview')) {
                    const postHeader = quotedPostElement.querySelector('.post-header');
                    const postContent = quotedPostElement.querySelector('.post-content');

                    if (postHeader && postContent) {
                        let previewHtml = '<strong>' + postHeader.querySelector('strong').textContent + '</strong> No.' + targetId + '<br>';

                        let textNodes = Array.from(postContent.childNodes).filter(node => node.nodeType === Node.TEXT_NODE);
                        let textContent = textNodes.map(node => node.textContent).join(' ').trim();

                        const imgElement = postContent.querySelector('img');
                        if (imgElement) {
                            const filename = imgElement.src.split('/').pop();
                            previewHtml += `(Image: ${decodeURIComponent(filename)})<br>`;
                        }

                        previewHtml += textContent.substring(0, 200) + (textContent.length > 200 ? '...' : '');
                        previewElement.innerHTML = previewHtml;
                    }
                    previewElement.style.display = 'block';

                    const rect = this.getBoundingClientRect();
                    let top = rect.bottom + window.scrollY + 5;
                    let left = rect.left + window.scrollX;

                    if (left + previewElement.offsetWidth > window.innerWidth + window.scrollX) {
                        left = window.innerWidth + window.scrollX - previewElement.offsetWidth - 10;
                    }
                    if (top + previewElement.offsetHeight > window.innerHeight + window.scrollY) {
                        top = rect.top + window.scrollY - previewElement.offsetHeight - 5;
                        if (top < window.scrollY) {
                            top = window.scrollY + 10;
                        }
                    }
                    if (left < window.scrollX) {
                        left = window.scrollX + 10;
                    }

                    previewElement.style.left = left + 'px';
                    previewElement.style.top = top + 'px';
                }
            });

            link.addEventListener('mouseleave', function() {
                const previewElement = this.nextElementSibling;
                if (previewElement && previewElement.classList.contains('quote-preview')) {
                    previewElement.style.display = 'none';
                }
            });
        });
    });


    function insertQuoteId(postId) {
        const textarea = document.querySelector('textarea[name="content"]');
        if (textarea) {
            const currentContent = textarea.value.trim();
            const quoteText = `>>${postId}\n`; 

            
            if (currentContent.length > 0 && !currentContent.endsWith('\n')) {
                textarea.value += '\n' + quoteText;
            } else {
                textarea.value += quoteText;
            }
            textarea.focus(); 
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
       
        document.querySelectorAll('a[href^="#p"]').forEach(link => {
            
            link.addEventListener('mouseenter', function() {
                const targetId = this.getAttribute('href').substring(1); // Remove '#'
                const quotedPostElement = document.getElementById(targetId);
                const previewElement = this.nextElementSibling; 

                if (quotedPostElement && previewElement && previewElement.classList.contains('quote-preview')) {
                    const postHeader = quotedPostElement.querySelector('.post-header');
                    const postContent = quotedPostElement.querySelector('.post-content');

                    if (postHeader && postContent) {
                        let previewHtml = '<strong>' + postHeader.querySelector('strong').textContent + '</strong> No.' + targetId + '<br>';

                        let textNodes = Array.from(postContent.childNodes).filter(node => node.nodeType === Node.TEXT_NODE);
                        let textContent = textNodes.map(node => node.textContent).join(' ').trim();

                        const imgElement = postContent.querySelector('img');
                        if (imgElement) {
                            const filename = imgElement.src.split('/').pop();
                            previewHtml += `(Image: ${decodeURIComponent(filename)})<br>`;
                        }

                        previewHtml += textContent.substring(0, 200) + (textContent.length > 200 ? '...' : '');
                        previewElement.innerHTML = previewHtml;
                    }
                    previewElement.style.display = 'block';

                    const rect = this.getBoundingClientRect();
                    let top = rect.bottom + window.scrollY + 5;
                    let left = rect.left + window.scrollX;

                    if (left + previewElement.offsetWidth > window.innerWidth + window.scrollX) {
                        left = window.innerWidth + window.scrollX - previewElement.offsetWidth - 10;
                    }
                    if (top + previewElement.offsetHeight > window.innerHeight + window.scrollY) {
                        top = rect.top + window.scrollY - previewElement.offsetHeight - 5;
                        if (top < window.scrollY) {
                            top = window.scrollY + 10;
                        }
                    }
                    if (left < window.scrollX) {
                        left = window.scrollX + 10;
                    }

                    previewElement.style.left = left + 'px';
                    previewElement.style.top = top + 'px';
                }
            });

            
            link.addEventListener('mouseleave', function() {
                const previewElement = this.nextElementSibling;
                if (previewElement && previewElement.classList.contains('quote-preview')) {
                    previewElement.style.display = 'none';
                }
            });

           
            link.addEventListener('click', function(event) {
               
                event.preventDefault();

                const targetId = this.getAttribute('href').substring(1); // Get the post ID
                const quotedPostElement = document.getElementById(targetId);

                if (quotedPostElement) {

                    quotedPostElement.scrollIntoView({ behavior: 'smooth', block: 'start' });

                    quotedPostElement.classList.add('highlight');

                    
                    setTimeout(() => {
                        quotedPostElement.classList.remove('highlight');
                    }, 1500); 
                }
            });
        });

        
        if (window.location.hash) {
            const targetId = window.location.hash.substring(1); // Remove '#'
            const quotedPostElement = document.getElementById(targetId);

            if (quotedPostElement) {
               

                quotedPostElement.classList.add('highlight');
                setTimeout(() => {
                    quotedPostElement.classList.remove('highlight');
                }, 1500);
            }
        }
    });

    function insertQuoteId(postId) {
    const textarea = document.querySelector('textarea[name="content"]');
    if (textarea) {
        const currentContent = textarea.value.trim();
        const quoteText = `>>${postId}\n`;

        if (currentContent.length > 0 && !currentContent.endsWith('\n')) {
            textarea.value += '\n' + quoteText;
        } else {
            textarea.value += quoteText;
        }
        textarea.focus();
    }
}
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('a[href^="#p"]').forEach(link => {
        link.addEventListener('mouseenter', function() {
            const targetId = this.getAttribute('href').substring(1);
            const quotedPostElement = document.getElementById(targetId);
            const previewElement = this.nextElementSibling;

            if (quotedPostElement && previewElement && previewElement.classList.contains('quote-preview')) {
                const postHeader = quotedPostElement.querySelector('.post-header');
                const postContent = quotedPostElement.querySelector('.post-content');

                if (postHeader && postContent) {
                    let previewHtml = '<strong>' + postHeader.querySelector('strong').textContent + '</strong> No.' + targetId + '<br>';

                    let textNodes = Array.from(postContent.childNodes).filter(node => node.nodeType === Node.TEXT_NODE);
                    let textContent = textNodes.map(node => node.textContent).join(' ').trim();

                    const imgElement = postContent.querySelector('img');
                    if (imgElement) {
                        const filename = imgElement.src.split('/').pop();
                        previewHtml += `(Image: ${decodeURIComponent(filename)})<br>`;
                    }

                    previewHtml += textContent.substring(0, 200) + (textContent.length > 200 ? '...' : '');
                    previewElement.innerHTML = previewHtml;
                }
                previewElement.style.display = 'block';

                const rect = this.getBoundingClientRect();
                let top = rect.bottom + window.scrollY + 5;
                let left = rect.left + window.scrollX;

                if (left + previewElement.offsetWidth > window.innerWidth + window.scrollX) {
                    left = window.innerWidth + window.scrollX - previewElement.offsetWidth - 10;
                }
                if (top + previewElement.offsetHeight > window.innerHeight + window.scrollY) {
                    top = rect.top + window.scrollY - previewElement.offsetHeight - 5;
                    if (top < window.scrollY) {
                        top = window.scrollY + 10;
                    }
                }
                if (left < window.scrollX) {
                    left = window.scrollX + 10;
                }

                previewElement.style.left = left + 'px';
                previewElement.style.top = top + 'px';
            }
        });

        link.addEventListener('mouseleave', function() {
            const previewElement = this.nextElementSibling;
            if (previewElement && previewElement.classList.contains('quote-preview')) {
                previewElement.style.display = 'none';
            }
        });

        link.addEventListener('click', function(event) {
            event.preventDefault();

            const targetId = this.getAttribute('href').substring(1);
            const quotedPostElement = document.getElementById(targetId);

            if (quotedPostElement) {
                quotedPostElement.scrollIntoView({ behavior: 'smooth', block: 'start' });

                quotedPostElement.classList.add('highlight');

                setTimeout(() => {
                    quotedPostElement.classList.remove('highlight');
                }, 1500);
            }
        });
    });

    if (window.location.hash) {
        const targetId = window.location.hash.substring(1);
        const quotedPostElement = document.getElementById(targetId);

        if (quotedPostElement) {
            quotedPostElement.classList.add('highlight');
            setTimeout(() => {
                quotedPostElement.classList.remove('highlight');
            }, 1500);
        }
    }

    const replyTextarea = document.querySelector('textarea[name="content"]');
    const imageInput = document.getElementById('imageInput');

    if (replyTextarea && imageInput) {
        replyTextarea.addEventListener('paste', function(event) {
            const items = (event.clipboardData || event.originalEvent.clipboardData).items;
            let imageFound = false;

            for (const item of items) {
                if (item.type.indexOf('image') !== -1) {
                    const file = item.getAsFile();
                    if (file) {
                        event.preventDefault();
                        imageFound = true;

                        const dataTransfer = new DataTransfer();
                        dataTransfer.items.add(file);

                        imageInput.files = dataTransfer.files;

                        console.log('Pasted image:', file.name, file.type, file.size, 'bytes');
                        break;
                    }
                }
            }

            if (!imageFound) {
            }
        });
    }
});
</script>

</body>
</html>