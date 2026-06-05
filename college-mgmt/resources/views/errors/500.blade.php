<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>Server Error — EduManage</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f8fafc; font-family: 'Inter', sans-serif; }
        .error-card { text-align: center; padding: 60px 40px; max-width: 480px; }
        .error-code { font-size: 7rem; font-weight: 900; color: #fee2e2; line-height: 1; }
        .error-title { font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 16px 0 8px; }
        .error-msg { color: #64748b; margin-bottom: 32px; }
    </style>
</head>
<body>
<div class="error-card">
    <div class="error-code">500</div>
    <div class="error-title">Server Error</div>
    <p class="error-msg">Something went wrong on our end. Please try again later or contact the administrator if the problem persists.</p>
    <a href="{{ url('/') }}" class="btn btn-primary"><i class="bi bi-house me-2"></i>Go Home</a>
    <a href="javascript:history.back()" class="btn btn-outline-secondary ms-2"><i class="bi bi-arrow-left me-2"></i>Go Back</a>
</div>
</body>
</html>
