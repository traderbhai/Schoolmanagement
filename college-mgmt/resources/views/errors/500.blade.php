<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>EduManage - Service Error</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <style>
        body { min-height: 100vh; display: grid; place-items: center; background: #f6f8fb; color: #172033; font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        .error-panel { width: min(560px, calc(100vw - 32px)); background: #fff; border: 1px solid #d9e1ec; border-radius: 8px; padding: 32px; box-shadow: 0 18px 45px rgba(15, 23, 42, .08); }
        .code { color: #0d6efd; font-weight: 700; letter-spacing: .08em; font-size: .8rem; text-transform: uppercase; }
        h1 { margin: 10px 0 12px; font-size: 1.6rem; }
        p { color: #566175; line-height: 1.55; }
        a { color: #0d6efd; font-weight: 600; }
    </style>
</head>
<body>
    <main class="error-panel">
        <div class="code">Service Error</div>
        <h1>The service hit a problem.</h1>
        <p>The page could not be loaded right now. Please check application logs, queue health, recent deployments, and failed jobs if this continues.</p>
        <a href="{{ url('/dashboard') }}">Back to dashboard</a>
    </main>
</body>
</html>
