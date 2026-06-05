<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>EduManage — Complete Institute Management Platform</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; color: #1e293b; }

        /* Hero */
        .hero-section {
            background: linear-gradient(135deg, #312e81 0%, #4f46e5 60%, #2563eb 100%);
            padding: 5rem 0 4.5rem;
            position: relative;
            overflow: hidden;
        }
        .hero-section::before {
            content: '';
            position: absolute;
            top: -120px; right: -120px;
            width: 420px; height: 420px;
            border-radius: 50%;
            background: rgba(255,255,255,.04);
            pointer-events: none;
        }
        .hero-section::after {
            content: '';
            position: absolute;
            bottom: -80px; left: -80px;
            width: 300px; height: 300px;
            border-radius: 50%;
            background: rgba(255,255,255,.03);
            pointer-events: none;
        }

        .hero-badge {
            display: inline-block;
            background: rgba(255,255,255,.12);
            color: rgba(255,255,255,.9);
            border: 1px solid rgba(255,255,255,.2);
            padding: .35rem .95rem;
            border-radius: 999px;
            font-size: .75rem;
            font-weight: 600;
            letter-spacing: .05em;
            text-transform: uppercase;
            margin-bottom: 1.5rem;
        }

        .hero-title {
            font-size: clamp(2rem, 5vw, 3.2rem);
            font-weight: 800;
            color: #fff;
            line-height: 1.15;
            margin-bottom: 1.25rem;
        }

        .hero-sub {
            font-size: 1rem;
            color: rgba(255,255,255,.75);
            max-width: 580px;
            margin: 0 auto 2.5rem;
            line-height: 1.7;
        }

        .btn-hero-primary {
            background: #fff;
            color: #4f46e5;
            font-weight: 700;
            border: 2px solid #fff;
            border-radius: 8px;
            padding: .7rem 1.8rem;
            font-size: .93rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            transition: background .15s, transform .1s;
        }
        .btn-hero-primary:hover { background: #f0f0ff; color: #3730a3; }

        .btn-hero-outline {
            background: transparent;
            color: rgba(255,255,255,.9);
            font-weight: 600;
            border: 2px solid rgba(255,255,255,.35);
            border-radius: 8px;
            padding: .7rem 1.8rem;
            font-size: .93rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            transition: border-color .15s, background .15s;
        }
        .btn-hero-outline:hover { border-color: rgba(255,255,255,.75); background: rgba(255,255,255,.08); color: #fff; }

        /* Stats bar */
        .stats-bar { background: #f0f0ff; border-top: 1px solid #e0e7ff; border-bottom: 1px solid #e0e7ff; padding: 2rem 0; }
        .stat-item { text-align: center; }
        .stat-num { font-size: 2rem; font-weight: 800; color: #4f46e5; }
        .stat-lbl { font-size: .78rem; color: #64748b; font-weight: 500; margin-top: .15rem; }

        /* Features section */
        .features-section { padding: 5rem 0; background: #f8faff; }
        .section-tag { font-size: .72rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: #4f46e5; margin-bottom: .4rem; }
        .section-title { font-size: clamp(1.6rem, 3vw, 2.2rem); font-weight: 800; color: #0f172a; margin-bottom: .6rem; }
        .section-sub { font-size: .9rem; color: #64748b; max-width: 520px; margin: 0 auto; }

        .feature-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 1.75rem 1.5rem;
            height: 100%;
            transition: box-shadow .2s, transform .18s;
        }
        .feature-card:hover { box-shadow: 0 8px 30px rgba(0,0,0,.09); transform: translateY(-3px); }

        .feature-icon {
            width: 52px; height: 52px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem;
            margin-bottom: 1.2rem;
        }
        .fi-blue   { background: #eff6ff; color: #2563eb; }
        .fi-green  { background: #f0fdf4; color: #16a34a; }
        .fi-amber  { background: #fffbeb; color: #d97706; }
        .fi-purple { background: #f5f3ff; color: #7c3aed; }
        .fi-cyan   { background: #ecfeff; color: #0891b2; }
        .fi-red    { background: #fff1f2; color: #e11d48; }

        .feature-card h5 { font-size: .98rem; font-weight: 700; color: #0f172a; margin-bottom: .45rem; }
        .feature-card p  { font-size: .83rem; color: #64748b; margin: 0; line-height: 1.6; }

        /* Roles section */
        .roles-section { padding: 5rem 0; background: #fff; }

        .role-card {
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 2rem;
            height: 100%;
            transition: box-shadow .2s;
        }
        .role-card:hover { box-shadow: 0 6px 24px rgba(0,0,0,.08); }

        .role-badge {
            display: inline-block;
            font-size: .7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            padding: .25rem .65rem;
            border-radius: 999px;
            margin-bottom: 1rem;
        }

        .role-title { font-size: 1.15rem; font-weight: 800; color: #0f172a; margin-bottom: .9rem; }
        .role-bullet { display: flex; align-items: flex-start; gap: .6rem; margin-bottom: .5rem; font-size: .83rem; color: #475569; }
        .role-bullet i { color: #4f46e5; flex-shrink: 0; margin-top: .1rem; }

        /* Nav */
        .site-nav { position: relative; z-index: 2; }
        .nav-brand { font-size: 1.1rem; font-weight: 800; color: #fff; text-decoration: none; display: flex; align-items: center; gap: .4rem; }
        .nav-brand:hover { color: #fff; }

        /* Footer */
        .site-footer { background: #0f172a; color: rgba(255,255,255,.55); padding: 2rem 0; text-align: center; font-size: .8rem; }
        .site-footer a { color: rgba(255,255,255,.75); text-decoration: none; }
        .site-footer a:hover { color: #fff; }
    </style>
</head>
<body>

{{-- HERO WITH EMBEDDED NAV --}}
<div class="hero-section">
    <div class="container">
        {{-- Nav --}}
        <div class="site-nav d-flex align-items-center justify-content-between mb-5 pb-1">
            <a href="/" class="nav-brand">
                <i class="bi bi-mortarboard-fill"></i> EduManage
            </a>
            <div class="d-flex gap-2">
                @auth
                <a href="{{ url('/dashboard') }}" class="btn-hero-outline" style="padding:.45rem 1.1rem;font-size:.82rem">
                    <i class="bi bi-grid"></i> Dashboard
                </a>
                @else
                <a href="{{ route('login') }}" class="btn-hero-primary" style="padding:.45rem 1.3rem;font-size:.82rem">
                    <i class="bi bi-box-arrow-in-right"></i> Sign In
                </a>
                @endauth
            </div>
        </div>

        {{-- Hero Content --}}
        <div class="text-center" style="position:relative;z-index:1">
            <div class="hero-badge"><i class="bi bi-building me-1"></i>Built for AICTE-Approved Institutes</div>
            <h1 class="hero-title">The Complete Institute<br>Management Platform</h1>
            <p class="hero-sub">
                Built for AICTE-approved institutes. Manage students, faculty, timetables, fees,
                attendance, and more — all in one place.
            </p>
            <div class="d-flex gap-3 justify-content-center flex-wrap">
                <a href="{{ route('login') }}" class="btn-hero-primary">
                    <i class="bi bi-box-arrow-in-right"></i> Sign In Now
                </a>
                <a href="#features" class="btn-hero-outline">
                    <i class="bi bi-chevron-down"></i> Learn More
                </a>
            </div>
        </div>
    </div>
</div>

{{-- STATS BAR --}}
<div class="stats-bar">
    <div class="container">
        <div class="row g-3">
            <div class="col-6 col-md-3 stat-item">
                <div class="stat-num">3</div>
                <div class="stat-lbl">User Portals</div>
            </div>
            <div class="col-6 col-md-3 stat-item">
                <div class="stat-num">75%</div>
                <div class="stat-lbl">Attendance Threshold Alerts</div>
            </div>
            <div class="col-6 col-md-3 stat-item">
                <div class="stat-num">10.0</div>
                <div class="stat-lbl">CGPA Scale (AICTE)</div>
            </div>
            <div class="col-6 col-md-3 stat-item">
                <div class="stat-num">∞</div>
                <div class="stat-lbl">Students & Faculty</div>
            </div>
        </div>
    </div>
</div>

{{-- FEATURES GRID --}}
<section class="features-section" id="features">
    <div class="container">
        <div class="text-center mb-5">
            <div class="section-tag">Platform Features</div>
            <h2 class="section-title">Everything Your Institute Needs</h2>
            <p class="section-sub">Six powerful modules working seamlessly to make administration effortless.</p>
        </div>

        <div class="row g-4">
            <div class="col-md-6 col-lg-4">
                <div class="feature-card">
                    <div class="feature-icon fi-blue"><i class="bi bi-grid-3x3-gap"></i></div>
                    <h5>Smart Timetable</h5>
                    <p>Conflict-free scheduling with multi-room management. Instantly view weekly schedules for any course or teacher.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="feature-card">
                    <div class="feature-icon fi-green"><i class="bi bi-check2-square"></i></div>
                    <h5>Attendance Tracking</h5>
                    <p>Real-time marking with automatic 75% threshold alerts. Students get instant visibility into their attendance status.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="feature-card">
                    <div class="feature-icon fi-amber"><i class="bi bi-cash-coin"></i></div>
                    <h5>Fee Management</h5>
                    <p>Structured fee types, installment plans, digital receipts, and complete payment history with PDF downloads.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="feature-card">
                    <div class="feature-icon fi-purple"><i class="bi bi-award"></i></div>
                    <h5>Grade Reports</h5>
                    <p>SGPA/CGPA calculation with downloadable grade cards. Supports O, A+, A, B+, B, C, D, F grade scales per AICTE norms.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="feature-card">
                    <div class="feature-icon fi-cyan"><i class="bi bi-house-heart"></i></div>
                    <h5>Parent Portal</h5>
                    <p>Keep parents informed with real-time notifications on attendance, results, and important notices from the institute.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="feature-card">
                    <div class="feature-icon fi-red"><i class="bi bi-briefcase"></i></div>
                    <h5>Placement Tracking</h5>
                    <p>End-to-end placement drive management. Track companies, applications, results, and placement statistics institute-wide.</p>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- THREE PORTALS SECTION --}}
<section class="roles-section">
    <div class="container">
        <div class="text-center mb-5">
            <div class="section-tag">Role-Based Portals</div>
            <h2 class="section-title">Three Portals, One Platform</h2>
            <p class="section-sub">Each role gets a tailored experience with only the tools they need.</p>
        </div>

        <div class="row g-4">
            {{-- Admin --}}
            <div class="col-md-4">
                <div class="role-card">
                    <div class="role-badge" style="background:#eff6ff;color:#1d4ed8">
                        <i class="bi bi-shield-check me-1"></i>Admin Portal
                    </div>
                    <div class="role-title">Administrator</div>
                    <div class="role-bullet"><i class="bi bi-check-circle-fill"></i>Manage students, teachers &amp; courses</div>
                    <div class="role-bullet"><i class="bi bi-check-circle-fill"></i>Configure timetables and classrooms</div>
                    <div class="role-bullet"><i class="bi bi-check-circle-fill"></i>Set fee structures and academic years</div>
                    <div class="role-bullet"><i class="bi bi-check-circle-fill"></i>View system-wide attendance &amp; reports</div>
                    <div class="role-bullet"><i class="bi bi-check-circle-fill"></i>Create and manage exam schedules</div>
                </div>
            </div>

            {{-- Teacher --}}
            <div class="col-md-4">
                <div class="role-card">
                    <div class="role-badge" style="background:#f0fdf4;color:#16a34a">
                        <i class="bi bi-person-workspace me-1"></i>Teacher Portal
                    </div>
                    <div class="role-title">Faculty</div>
                    <div class="role-bullet"><i class="bi bi-check-circle-fill"></i>View personal weekly timetable</div>
                    <div class="role-bullet"><i class="bi bi-check-circle-fill"></i>Mark daily attendance per class</div>
                    <div class="role-bullet"><i class="bi bi-check-circle-fill"></i>Enter exam marks for students</div>
                    <div class="role-bullet"><i class="bi bi-check-circle-fill"></i>View enrolled students by subject</div>
                    <div class="role-bullet"><i class="bi bi-check-circle-fill"></i>Bulk present/absent marking</div>
                </div>
            </div>

            {{-- Student --}}
            <div class="col-md-4">
                <div class="role-card">
                    <div class="role-badge" style="background:#f5f3ff;color:#7c3aed">
                        <i class="bi bi-mortarboard me-1"></i>Student Portal
                    </div>
                    <div class="role-title">Student</div>
                    <div class="role-bullet"><i class="bi bi-check-circle-fill"></i>Dashboard with attendance &amp; SGPA KPIs</div>
                    <div class="role-bullet"><i class="bi bi-check-circle-fill"></i>Subject-wise attendance with 75% alerts</div>
                    <div class="role-bullet"><i class="bi bi-check-circle-fill"></i>SGPA, CGPA &amp; grade card download</div>
                    <div class="role-bullet"><i class="bi bi-check-circle-fill"></i>Fee status and payment receipts</div>
                    <div class="role-bullet"><i class="bi bi-check-circle-fill"></i>Weekly timetable and institute notices</div>
                </div>
            </div>
        </div>

        {{-- CTA Button --}}
        <div class="text-center mt-5">
            <a href="{{ route('login') }}"
               class="d-inline-flex align-items-center gap-2 text-white fw-bold text-decoration-none rounded px-4 py-3"
               style="background:#4f46e5;font-size:1rem;border-radius:10px!important;transition:background .15s"
               onmouseover="this.style.background='#3730a3'"
               onmouseout="this.style.background='#4f46e5'">
                <i class="bi bi-box-arrow-in-right"></i>
                Get Started — Sign In Now
            </a>
        </div>
    </div>
</section>

{{-- FOOTER --}}
<footer class="site-footer">
    <div class="container">
        <div class="d-flex align-items-center justify-content-center gap-2 mb-2">
            <i class="bi bi-mortarboard-fill" style="font-size:1.05rem;color:#818cf8"></i>
            <span style="font-weight:700;color:#f1f5f9;font-size:.9rem">EduManage</span>
        </div>
        <div>&copy; 2026 EduManage. Built for Indian Institutes.</div>
        <div class="mt-2 d-flex justify-content-center gap-3">
            <a href="{{ route('login') }}">Sign In</a>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
