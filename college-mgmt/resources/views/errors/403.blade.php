<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>Access Restricted - EduManage</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --surface: #ffffff;
            --page: #f6f8fb;
            --text: #172033;
            --muted: #667085;
            --border: #d9e2ef;
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --warning-bg: #fff7ed;
            --warning: #c2410c;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background:
                radial-gradient(circle at top left, rgba(37, 99, 235, .10), transparent 32rem),
                var(--page);
            color: var(--text);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            margin: 0;
            padding: 24px;
        }

        .error-shell {
            width: min(100%, 760px);
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: 0 24px 80px rgba(21, 31, 48, .10);
            overflow: hidden;
        }

        .error-body {
            padding: 36px;
        }

        .error-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 10px;
            border-radius: 999px;
            background: var(--warning-bg);
            color: var(--warning);
            font-weight: 700;
            font-size: .78rem;
            letter-spacing: .02em;
            text-transform: uppercase;
        }

        .error-title {
            margin: 18px 0 10px;
            font-size: clamp(1.8rem, 4vw, 2.6rem);
            line-height: 1.1;
            font-weight: 800;
            letter-spacing: 0;
        }

        .error-msg {
            color: var(--muted);
            font-size: 1rem;
            max-width: 56ch;
            margin-bottom: 24px;
        }

        .action-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .btn-primary {
            background: var(--primary);
            border-color: var(--primary);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
        }

        .help-strip {
            border-top: 1px solid var(--border);
            background: #fbfdff;
            padding: 18px 36px;
            color: var(--muted);
            font-size: .92rem;
        }
    </style>
</head>
<body>
<main class="error-shell" role="main">
    <section class="error-body">
        <span class="error-badge"><i class="bi bi-shield-lock"></i> Error 403</span>
        <h1 class="error-title">This area is restricted</h1>
        <p class="error-msg">
            Your account does not have permission to open this page. If your role recently changed,
            sign out and back in, or ask an administrator to review your access.
        </p>
        <div class="action-row">
            <a href="{{ url('/') }}" class="btn btn-primary">
                <i class="bi bi-house me-2"></i>Go to my dashboard
            </a>
            <button type="button" class="btn btn-outline-secondary" onclick="history.back()">
                <i class="bi bi-arrow-left me-2"></i>Go back
            </button>
        </div>
    </section>
    <section class="help-strip">
        Access is controlled by role, department, and program permissions. Avoid sharing credentials;
        request the exact access you need instead.
    </section>
</main>
</body>
</html>
