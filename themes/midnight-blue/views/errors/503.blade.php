<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance – Be Right Back</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: system-ui, -apple-system, sans-serif; background: #fafafa; color: #111; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 2rem; }
        .container { text-align: center; max-width: 480px; }
        .icon { font-size: 3rem; margin-bottom: 1rem; }
        h1 { font-size: 1.5rem; font-weight: 600; margin-bottom: 0.5rem; }
        p { color: #6b7280; }
        .msg { margin-top: 1.5rem; padding: 1rem; background: #f3f4f6; border-radius: 8px; font-size: 0.875rem; color: #374151; }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🔧</div>
        <h1>Under Maintenance</h1>
        <p>We're performing scheduled maintenance. We'll be back shortly.</p>
        @if(!empty($exception->getMessage()))
            <div class="msg">{{ $exception->getMessage() }}</div>
        @endif
    </div>
</body>
</html>
