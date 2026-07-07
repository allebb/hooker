<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Hooker - The automated deployment and workflow bot!</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bitcount+Single&display=swap" rel="stylesheet">
    <style>
        body {
            background: #2b2b2b;
            margin: 0;
            height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-family: Arial, Helvetica, sans-serif;
            color: #d4d4d4;
        }
        h1 {
            margin: 0;
            font-size: 3.5em;
            color: #fff;
            font-family: "Bitcount Single", Arial, Helvetica, sans-serif;
            letter-spacing: 4px;
        }
        p.strapline {
            margin: 8px 0 0;
            color: #999;
            font-style: italic;
        }
        img {
            width: 200px;
            height: auto;
            margin-top: 24px;
        }
        .status {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 20px;
            color: #999;
            font-size: 0.9em;
        }
        .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #2ecc71;
            box-shadow: 0 0 6px #2ecc71;
            animation: pulse 1.5s ease-in-out infinite;
        }
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(46, 204, 113, 0.7);
            }
            70% {
                box-shadow: 0 0 0 8px rgba(46, 204, 113, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(46, 204, 113, 0);
            }
        }
    </style>
</head>
<body>
<h1>Hooker</h1>
<p class="strapline">The automated deployment and workflow bot!</p>
<img src="assets/hooker.png" alt="Hooker">
<div class="status">
    <span class="dot"></span>
    <span>Hooker engine is running and listening for initiation requests...</span>
</div>
</body>
</html>
