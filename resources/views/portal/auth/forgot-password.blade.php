<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password &middot; {{ config('school.name', 'School Portal') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/portal.css'])
    <style>
        .portal-login-shell {
            min-height: 100vh;
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
            box-shadow: 0 20px 25px -5px rgba(15, 23, 42, 0.08);
            border: 1px solid #E2E8F0;
            padding: 36px 32px;
        }
    </style>
</head>
<body class="portal-body">
    <div class="portal-login-shell">
        <div class="portal-login-card">
            <div style="text-align: center; margin-bottom: 24px;">
                <div style="width: 52px; height: 52px; border-radius: 14px; background: #DBEAFE; color: #2563EB; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 14px;">
                    <x-icon name="lock" size="24" />
                </div>
                <h1 style="font-size: 20px; font-weight: 700; margin: 0 0 6px 0; color: #0F172A;">Reset Portal Password</h1>
                <p style="font-size: 13.5px; color: #64748B; margin: 0;">Enter your Admission No, Roll No, or registered Phone / Email</p>
            </div>

            @if(session('status'))
                <div style="background:#DCFCE7; border: 1px solid #BBF7D0; color: #166534; padding: 12px 14px; border-radius: 10px; font-size: 13px; margin-bottom: 18px;">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('portal.password.forgot.submit') }}">
                @csrf
                <div style="margin-bottom: 20px;">
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:8px;">Account Identifier</label>
                    <input type="text" name="identifier" required placeholder="Admission No / Phone / Email" style="width:100%; height:46px; padding:0 14px; border:1px solid #CBD5E1; border-radius:10px; outline:none; font-family:inherit; box-sizing:border-box;">
                </div>

                <button type="submit" class="portal-btn-primary" style="width:100%; height:46px; justify-content:center;">
                    Request Reset Assistance
                </button>
            </form>

            <div style="text-align: center; margin-top: 24px;">
                <a href="{{ route('portal.login') }}" style="color: #2563EB; font-size: 13.5px; text-decoration: none; font-weight: 600;">
                    &larr; Back to Portal Login
                </a>
            </div>
        </div>
    </div>
</body>
</html>
