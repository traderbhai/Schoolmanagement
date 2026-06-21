<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $emailTitle ?? config('app.name') }}</title>
    <style>
        body { margin: 0; padding: 0; background-color: #f4f6f9; font-family: Arial, Helvetica, sans-serif; }
        .wrapper { width: 100%; background-color: #f4f6f9; padding: 30px 0; }
        .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .header { background-color: #1e3a5f; padding: 30px 40px; text-align: center; }
        .header h1 { margin: 0; color: #ffffff; font-size: 22px; font-weight: 700; letter-spacing: 0.5px; }
        .header p { margin: 6px 0 0; color: #a8c4e0; font-size: 13px; }
        .body { padding: 36px 40px; color: #333333; font-size: 15px; line-height: 1.7; }
        .body h2 { color: #1e3a5f; font-size: 20px; margin: 0 0 16px; }
        .body p { margin: 0 0 14px; }
        .info-box { background-color: #f0f5fb; border-left: 4px solid #1e3a5f; padding: 16px 20px; border-radius: 4px; margin: 20px 0; }
        .info-box p { margin: 4px 0; font-size: 14px; }
        .info-box strong { color: #1e3a5f; }
        .btn { display: inline-block; background-color: #1e3a5f; color: #ffffff !important; text-decoration: none; padding: 12px 28px; border-radius: 5px; font-size: 14px; font-weight: 600; margin: 16px 0; }
        .alert-warning { background-color: #fff8e1; border-left: 4px solid #f59e0b; padding: 14px 18px; border-radius: 4px; margin: 16px 0; }
        .alert-danger  { background-color: #fef2f2; border-left: 4px solid #ef4444; padding: 14px 18px; border-radius: 4px; margin: 16px 0; }
        .alert-success { background-color: #f0fdf4; border-left: 4px solid #22c55e; padding: 14px 18px; border-radius: 4px; margin: 16px 0; }
        .footer { background-color: #f0f5fb; padding: 20px 40px; text-align: center; font-size: 12px; color: #888888; border-top: 1px solid #e5e7eb; }
        .footer a { color: #1e3a5f; text-decoration: none; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="container">
        <div class="header">
            <h1>{{ env('INSTITUTE_NAME', config('app.name')) }}</h1>
            <p>College Management System</p>
        </div>
        <div class="body">
            @yield('content')
        </div>
        <div class="footer">
            <p>This is an automated email. Please do not reply to this message.</p>
            <p style="margin-top:8px;">
                {{ env('INSTITUTE_NAME', config('app.name')) }} - All rights reserved.<br>
                <small>If you believe you received this email in error, please contact support.</small>
            </p>
        </div>
    </div>
</div>
</body>
</html>
