<?php http_response_code(404); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>404 - Not Found</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            text-align: center;
            padding: 40px;
            font-family: sans-serif;
            background: #f4f4f4;
            color: #333;
        }
        h1 {
            font-size: 48px;
            margin-bottom: 10px;
        }
        p {
            font-size: 20px;
        }
        a {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 15px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 6px;
        }
        a:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>

    <h1>404 - Thread Not Found</h1>
    <p>The thread you are looking for might have been removed, renamed, or doesn't exist.</p>
    <a href="index.php">← Return to Home</a>

</body>
</html>
