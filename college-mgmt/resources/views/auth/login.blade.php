<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - EduManage</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/css/app.css" rel="stylesheet">
    <style>
        html, body { height: 100%; margin: 0; }
        body { font-family: var(--font-base); background: #f1f5f9; display: flex; }

        .login-wrapper {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }

        /* Left panel - visible only on lg+ */
        .login-panel {
            flex: 0 0 45%;
            max-width: 45%;
            background: linear-gradient(145deg, #312e81 0%, #4f46e5 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 3rem 3.5rem;
            position: relative;
            overflow: hidden;
        }

        /* Decorative circles */
        .login-panel::before {
            content: '';
            position: absolute;
            width: 350px; height: 350px;
            border: 60px solid rgba(255,255,255,.06);
            border-radius: 50%;
            top: -80px; right: -80px;
        }
        .login-panel::after {
            content: '';
            position: absolute;
            width: 220px; height: 220px;
            border: 40px solid rgba(255,255,255,.05);
            border-radius: 50%;
            bottom: 40px; left: -60px;
        }

        .panel-dots {
            position: absolute;
            bottom: 2rem; right: 2rem;
            display: grid;
            grid-template-columns: repeat(5, 8px);
            gap: 8px;
        }
        .panel-dots span {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: rgba(255,255,255,.18);
            display: block;
        }
        .panel-dots span:nth-child(3n+1) { background: rgba(255,255,255,.35); }

        .panel-logo {
            width: 52px; height: 52px;
            background: rgba(255,255,255,.15);
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.6rem; color: #fff;
            margin-bottom: 2.5rem;
            backdrop-filter: blur(4px);
        }

        .panel-title {
            font-size: 2rem; font-weight: 700;
            color: #fff; line-height: 1.2;
            margin-bottom: .75rem;
        }

        .panel-tagline {
            font-size: .95rem;
            color: rgba(255,255,255,.65);
            line-height: 1.6;
            max-width: 340px;
        }

        .panel-features {
            margin-top: 2.5rem;
            list-style: none;
            padding: 0;
        }
        .panel-features li {
            display: flex; align-items: center; gap: .7rem;
            color: rgba(255,255,255,.8);
            font-size: .88rem;
            margin-bottom: .75rem;
        }
        .panel-features li i {
            width: 28px; height: 28px;
            background: rgba(255,255,255,.12);
            border-radius: 7px;
            display: flex; align-items: center; justify-content: center;
            font-size: .85rem; flex-shrink: 0;
        }

        /* Right form panel */
        .login-form-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2.5rem 2rem;
            background: #fff;
        }

        .login-card {
            width: 100%;
            max-width: 400px;
        }

        .login-card .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-card .login-header .logo-sm {
            width: 44px; height: 44px;
            background: #4f46e5;
            border-radius: 11px;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 1.3rem; color: #fff;
            margin-bottom: 1rem;
        }

        .login-card .login-header h2 {
            font-size: 1.45rem; font-weight: 700;
            color: #0f172a; margin-bottom: .3rem;
        }

        .login-card .login-header p {
            font-size: .85rem; color: #64748b; margin: 0;
        }

        .login-input-group {
            position: relative;
        }
        .login-input-group .input-icon {
            position: absolute; left: .9rem; top: 50%;
            transform: translateY(-50%);
            color: #94a3b8; font-size: .95rem;
            pointer-events: none;
        }
        .login-input-group input {
            padding-left: 2.5rem;
        }
        .login-input-group .toggle-pass {
            position: absolute; right: .9rem; top: 50%;
            transform: translateY(-50%);
            color: #94a3b8; cursor: pointer; border: none;
            background: none; font-size: .95rem; padding: 0;
        }
        .login-input-group .toggle-pass:hover { color: #4f46e5; }

        .btn-login {
            background: #4f46e5;
            color: #fff;
            border: none;
            border-radius: 8px;
            width: 100%;
            padding: .65rem 1rem;
            font-size: .9rem;
            font-weight: 600;
            letter-spacing: .01em;
            transition: background .15s, transform .1s;
            display: flex; align-items: center; justify-content: center; gap: .4rem;
        }
        .btn-login:hover { background: #3730a3; color: #fff; }
        .btn-login:active { transform: scale(.98); }

        .login-divider {
            display: flex; align-items: center; gap: .75rem;
            margin: 1.25rem 0;
        }
        .login-divider::before, .login-divider::after {
            content: ''; flex: 1; height: 1px; background: #e2e8f0;
        }
        .login-divider span { font-size: .75rem; color: #94a3b8; white-space: nowrap; }

        .form-check-label { font-size: .82rem; color: #475569; }
        .form-check-input:checked { background-color: #4f46e5; border-color: #4f46e5; }

        .powered-by {
            text-align: center;
            margin-top: 2.5rem;
            font-size: .72rem;
            color: #94a3b8;
        }

        .alert-session {
            background: #dcfce7; color: #166534;
            border-left: 4px solid #16a34a;
            border-radius: 8px; font-size: .82rem;
            padding: .65rem .9rem; margin-bottom: 1rem;
        }

        .invalid-feedback-custom {
            font-size: .75rem; color: #dc2626; margin-top: .25rem;
        }

        /* Mobile: hide left panel */
        @media (max-width: 991.98px) {
            .login-panel { display: none; }
            .login-form-panel { background: #f1f5f9; }
            .login-card { background: #fff; border-radius: 14px; padding: 2rem; box-shadow: 0 4px 24px rgba(0,0,0,.08); }
        }
    </style>
</head>
<body>

<div class="login-wrapper">

    {{-- LEFT PANEL --}}
    <div class="login-panel d-none d-lg-flex flex-column justify-content-center">
        <div class="panel-logo">
            <i class="bi bi-mortarboard-fill"></i>
        </div>

        <h1 class="panel-title">Smarter College<br>Management</h1>
        <p class="panel-tagline">
            A unified platform for admins, teachers, and students - timetables, attendance, results, fees, and more.
        </p>

        <ul class="panel-features">
            <li><i class="bi bi-calendar3"></i> Automated timetable scheduling</li>
            <li><i class="bi bi-check2-square"></i> Real-time attendance tracking</li>
            <li><i class="bi bi-award"></i> Exam results &amp; grade reports</li>
            <li><i class="bi bi-cash-coin"></i> Integrated fee management</li>
        </ul>

        {{-- Decorative dots grid --}}
        <div class="panel-dots">
            @for($i = 0; $i < 25; $i++)
                <span></span>
            @endfor
        </div>
    </div>

    {{-- RIGHT FORM PANEL --}}
    <div class="login-form-panel">
        <div class="login-card">

            <div class="login-header">
                <div class="logo-sm d-lg-none mb-2"><i class="bi bi-mortarboard-fill"></i></div>
                <h2>Welcome back</h2>
                <p>Sign in to your EduManage account</p>
            </div>

            {{-- Session status --}}
            @if(session('status'))
                <div class="alert-session">
                    <i class="bi bi-info-circle me-1"></i>{{ session('status') }}
                </div>
            @endif

            {{-- Validation errors --}}
            @if($errors->any())
                <div class="alert alert-danger py-2 mb-3" style="font-size:.82rem;border-radius:8px;border:none;border-left:4px solid #dc2626;background:#fee2e2;color:#991b1b;">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf

                {{-- Email --}}
                <div class="mb-3">
                    <label for="email" class="form-label">Email address</label>
                    <div class="login-input-group">
                        <i class="bi bi-envelope input-icon"></i>
                        <input
                            type="email"
                            class="form-control @error('email') is-invalid @enderror"
                            id="email"
                            name="email"
                            value="{{ old('email') }}"
                            required
                            autofocus
                            autocomplete="username"
                            placeholder="you@example.com"
                        >
                    </div>
                </div>

                {{-- Password --}}
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label for="password" class="form-label mb-0">Password</label>
                        @if(Route::has('password.request'))
                            <a href="{{ route('password.request') }}" style="font-size:.75rem;color:#4f46e5;text-decoration:none;">Forgot password?</a>
                        @endif
                    </div>
                    <div class="login-input-group">
                        <i class="bi bi-lock input-icon"></i>
                        <input
                            type="password"
                            class="form-control @error('password') is-invalid @enderror"
                            id="password"
                            name="password"
                            required
                            autocomplete="current-password"
                            placeholder="Password"
                        >
                        <button type="button" class="toggle-pass" aria-label="Show/hide password" onclick="togglePassword()">
                            <i class="bi bi-eye" id="passEyeIcon"></i>
                        </button>
                    </div>
                </div>

                {{-- Remember me --}}
                <div class="mb-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember_me">
                        <label class="form-check-label" for="remember_me">Keep me signed in</label>
                    </div>
                </div>

                <button type="submit" class="btn-login">
                    <i class="bi bi-box-arrow-in-right"></i> Sign In
                </button>
            </form>

            <div class="powered-by">
                Powered by <strong style="color:#4f46e5;">EduManage</strong> - College Management System
            </div>
        </div>
    </div>

</div>

<script>
function togglePassword() {
    var input = document.getElementById('password');
    var icon  = document.getElementById('passEyeIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye';
    }
}
</script>

</body>
</html>
