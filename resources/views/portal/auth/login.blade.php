<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student & Parent Login &middot; {{ config('school.name', 'School Portal') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/portal.css', 'resources/js/portal.js'])
    <style>
        .portal-login-shell {
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #EFF6FF 0%, #F8FAFC 50%, #EEF2F6 100%);
            padding: 24px;
        }
        .portal-login-card {
            width: 100%;
            max-width: 440px;
            background: #FFFFFF;
            border-radius: 20px;
            box-shadow: 0 20px 25px -5px rgba(15, 23, 42, 0.08), 0 8px 10px -6px rgba(15, 23, 42, 0.04);
            border: 1px solid #E2E8F0;
            padding: 36px 32px;
        }
        .portal-login-header {
            text-align: center;
            margin-bottom: 28px;
        }
        .portal-login-logo {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            background: linear-gradient(135deg, #2563EB, #1D4ED8);
            color: white;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.28);
        }
        .portal-login-title {
            font-size: 22px;
            font-weight: 800;
            color: #0F172A;
            margin: 0 0 6px 0;
            letter-spacing: -0.4px;
        }
        .portal-login-subtitle {
            font-size: 13.5px;
            color: #64748B;
            margin: 0;
        }
        .portal-tabs {
            display: flex;
            background: #F1F5F9;
            padding: 4px;
            border-radius: 12px;
            margin-bottom: 24px;
            gap: 4px;
        }
        .portal-tab-btn {
            flex: 1;
            padding: 9px 12px;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            color: #64748B;
            background: none;
            transition: all 0.15s ease;
            text-align: center;
        }
        .portal-tab-btn.active {
            background: #FFFFFF;
            color: #2563EB;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        }
        .portal-form-group {
            margin-bottom: 18px;
        }
        .portal-form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #0F172A;
            margin-bottom: 7px;
        }
        .portal-input-container {
            position: relative;
            display: flex;
            align-items: center;
        }
        .portal-input-container svg {
            position: absolute;
            left: 14px;
            color: #94A3B8;
            pointer-events: none;
        }
        .portal-input-container input {
            width: 100%;
            height: 46px;
            padding: 0 14px 0 42px;
            border-radius: 10px;
            border: 1px solid #CBD5E1;
            background: #F8FAFC;
            color: #0F172A;
            font-size: 14px;
            font-family: inherit;
            outline: none;
            transition: all 0.15s ease;
            box-sizing: border-box;
        }
        .portal-input-container input:focus {
            border-color: #2563EB;
            background: #FFFFFF;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }
        .portal-login-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 13px;
            margin-bottom: 24px;
        }
        .portal-remember-me {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #64748B;
            cursor: pointer;
        }
        .portal-remember-me input {
            width: 16px;
            height: 16px;
            border-radius: 4px;
            accent-color: #2563EB;
        }
        .portal-login-submit {
            width: 100%;
            height: 48px;
            border-radius: 12px;
            background: #2563EB;
            color: white;
            font-weight: 600;
            font-size: 14px;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
            transition: all 0.15s ease;
        }
        .portal-login-submit:hover {
            background: #1D4ED8;
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.35);
        }
        .portal-login-footer {
            margin-top: 24px;
            text-align: center;
            font-size: 13px;
            color: #64748B;
        }
    </style>
</head>
<body class="portal-body">
    <div class="portal-login-shell">
        <div class="portal-login-card">
            <div class="portal-login-header">
                <div class="portal-login-logo">
                    <x-icon name="school" size="28" />
                </div>
                <h1 class="portal-login-title">{{ config('school.name', 'School Portal') }}</h1>
                <p class="portal-login-subtitle">Student & Parent Unified Portal</p>
            </div>

            <!-- Role Tabs -->
            <div class="portal-tabs" role="tablist">
                <button type="button" id="tab-student" class="portal-tab-btn {{ ($activeType ?? 'student') === 'student' ? 'active' : '' }}" onclick="switchTab('student')" role="tab">
                    🎓 Student Login
                </button>
                <button type="button" id="tab-parent" class="portal-tab-btn {{ ($activeType ?? '') === 'parent' ? 'active' : '' }}" onclick="switchTab('parent')" role="tab">
                    👨‍👩‍👧 Parent Login
                </button>
            </div>

            @if(session('status'))
                <div style="background:#DCFCE7; border: 1px solid #BBF7D0; color: #166534; padding: 12px 14px; border-radius: 10px; font-size: 13px; margin-bottom: 18px;">
                    {{ session('status') }}
                </div>
            @endif

            @if($errors->any())
                <div style="background:#FEE2E2; border: 1px solid #FECACA; color: #991B1B; padding: 12px 14px; border-radius: 10px; font-size: 13px; margin-bottom: 18px;">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('portal.login.submit') }}" id="portal-login-form">
                @csrf
                <input type="hidden" name="type" id="portal-login-type" value="{{ $activeType ?? 'student' }}">

                <div class="portal-form-group">
                    <label for="portal-identifier" id="identifier-label">
                        {{ ($activeType ?? 'student') === 'student' ? 'Admission No or Roll No' : 'Phone Number or Email' }}
                    </label>
                    <div class="portal-input-container">
                        <x-icon name="user" size="18" />
                        <input
                            type="text"
                            id="portal-identifier"
                            name="identifier"
                            value="{{ old('identifier') }}"
                            placeholder="{{ ($activeType ?? 'student') === 'student' ? 'e.g. ADM-1001 or Roll 12' : 'e.g. 03001234567 or parent@school.com' }}"
                            required
                            autofocus
                        >
                    </div>
                </div>

                <div class="portal-form-group">
                    <label for="portal-password">Password</label>
                    <div class="portal-input-container">
                        <x-icon name="lock" size="18" />
                        <input
                            type="password"
                            id="portal-password"
                            name="password"
                            placeholder="Enter your password"
                            required
                        >
                    </div>
                </div>

                <div class="portal-login-actions">
                    <label class="portal-remember-me">
                        <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
                        <span>Remember me</span>
                    </label>
                    <a href="{{ route('portal.password.forgot') }}" style="color: #2563EB; text-decoration: none; font-weight: 500;">
                        Forgot password?
                    </a>
                </div>

                <button type="submit" class="portal-login-submit" id="portal-submit-btn">
                    Sign in to Portal
                </button>
            </form>

            <div class="portal-login-footer">
                <span>Are you a teacher or staff member?</span><br>
                <a href="{{ route('login') }}" style="color: #2563EB; font-weight: 600; text-decoration: none; margin-top: 4px; display: inline-block;">
                    Go to Staff & Admin Login &rarr;
                </a>
            </div>
        </div>
    </div>

    <script>
        function switchTab(type) {
            const studentTab = document.getElementById('tab-student');
            const parentTab = document.getElementById('tab-parent');
            const typeInput = document.getElementById('portal-login-type');
            const label = document.getElementById('identifier-label');
            const input = document.getElementById('portal-identifier');

            typeInput.value = type;

            if (type === 'student') {
                studentTab.classList.add('active');
                parentTab.classList.remove('active');
                label.innerText = 'Admission No or Roll No';
                input.placeholder = 'e.g. ADM-1001 or Roll 12';
            } else {
                parentTab.classList.add('active');
                studentTab.classList.remove('active');
                label.innerText = 'Phone Number or Email';
                input.placeholder = 'e.g. 03001234567 or parent@school.com';
            }
            input.focus();
        }
    </script>
</body>
</html>
