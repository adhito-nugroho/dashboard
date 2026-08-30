<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Dashboard Anggaran CDK Bojonegoro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/css/style.css">
    <style>
        :root { --primary: #4f46e5; --primary-dark: #312e81; }
        body {
            min-height: 100vh;
            background: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-wrapper {
            width: 100%;
            max-width: 900px;
            background: #fff;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.12);
            display: flex;
            min-height: 520px;
        }
        .login-brand-panel {
            flex: 1;
            background: linear-gradient(135deg, #312e81 0%, #4338ca 55%, #4f46e5 100%);
            color: #fff;
            padding: 2.5rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
        }
        .login-brand-panel::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image: radial-gradient(circle at 20% 20%, rgba(255,255,255,0.08) 0%, transparent 50%),
                               radial-gradient(circle at 80% 80%, rgba(255,255,255,0.06) 0%, transparent 50%);
        }
        .login-brand-panel .brand-content { position: relative; z-index: 1; }
        .login-brand-icon {
            width: 64px; height: 64px;
            border-radius: 16px;
            background: rgba(255,255,255,0.12);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.8rem;
            margin-bottom: 1.5rem;
        }
        .login-form-panel {
            flex: 1;
            padding: 3rem 2.5rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .login-form-panel .form-label {
            font-weight: 600;
            font-size: 0.85rem;
            color: #1e293b;
        }
        .login-form-panel .input-group-text {
            background: #f8fafc;
            border-right: none;
        }
        .login-form-panel .form-control {
            border-left: none;
            padding: 0.65rem 0.85rem;
        }
        .login-form-panel .input-group:focus-within {
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
            border-radius: 8px;
        }
        .login-form-panel .input-group:focus-within .input-group-text,
        .login-form-panel .input-group:focus-within .form-control {
            border-color: #4f46e5;
        }
        .btn-login {
            background: linear-gradient(135deg, #4338ca, #4f46e5);
            border: none;
            font-weight: 700;
            padding: 0.7rem;
            border-radius: 8px;
            transition: all 0.15s ease;
        }
        .btn-login:hover {
            background: linear-gradient(135deg, #312e81, #4338ca);
        }
        .btn-toggle-password {
            background: #f8fafc;
            border-left: none;
            cursor: pointer;
        }
        .login-footer-note {
            font-size: 0.75rem;
            color: #94a3b8;
            text-align: center;
            margin-top: 2rem;
        }
        /* Chrome/Edge autofill tidak merusak input */
        .login-form-panel .form-control:-webkit-autofill,
        .login-form-panel .form-control:-webkit-autofill:hover,
        .login-form-panel .form-control:-webkit-autofill:focus {
            -webkit-box-shadow: 0 0 0 1000px #f8fafc inset;
            -webkit-text-fill-color: #0f172a;
            transition: background-color 5000s ease-in-out 0s;
            caret-color: #0f172a;
        }
        @media (max-width: 768px) {
            .login-wrapper { flex-direction: column; max-width: 420px; min-height: auto; }
            .login-brand-panel { padding: 1.75rem; text-align: center; }
            .login-brand-icon { margin: 0 auto 1rem; }
            .login-form-panel { padding: 2rem 1.75rem; }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <!-- PANEL KIRI: BRANDING -->
        <div class="login-brand-panel">
            <div class="brand-content">
                <div class="login-brand-icon">
                    <i class="bi bi-tree-fill"></i>
                </div>
                <h2 class="fw-bold mb-2" style="letter-spacing:-0.02em;">Dashboard Anggaran</h2>
                <p class="mb-0" style="opacity:.85;font-size:0.95rem;">
                    Sistem Pengelolaan Anggaran Seksi<br>
                    CDK Wilayah Bojonegoro
                </p>
                <hr style="border-color: rgba(255,255,255,0.2); margin: 1.5rem 0;">
                <p class="mb-0" style="opacity:.7;font-size:0.8rem;">
                    Dinas Kehutanan Provinsi Jawa Timur
                </p>
            </div>
        </div>

        <!-- PANEL KANAN: FORM LOGIN -->
        <div class="login-form-panel">
            <div class="mb-4">
                <h4 class="fw-bold mb-1" style="color:#0f172a;">Masuk ke Akun Anda</h4>
                <p class="text-muted mb-0" style="font-size:0.875rem;">Silakan login menggunakan akun seksi Anda.</p>
            </div>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger py-2 small mb-3">
                    <i class="bi bi-exclamation-circle me-1"></i> <?= htmlspecialchars($_SESSION['error']) ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <form action="/login" method="POST">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person text-muted"></i></span>
                        <input type="text" name="username" class="form-control" placeholder="Masukkan username"
                               required autofocus autocomplete="username">
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-key text-muted"></i></span>
                        <input type="password" name="password" id="passwordInput" class="form-control"
                               placeholder="Masukkan password" required autocomplete="current-password">
                        <button type="button" class="input-group-text btn-toggle-password" id="btnTogglePassword">
                            <i class="bi bi-eye" id="iconTogglePassword"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn btn-login btn-primary w-100 text-white">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Login
                </button>
            </form>

            <p class="login-footer-note">
                © 2026 Dinas Kehutanan Provinsi Jawa Timur<br>
                Cabang Dinas Kehutanan Wilayah Bojonegoro
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('btnTogglePassword').addEventListener('click', function() {
            const input = document.getElementById('passwordInput');
            const icon = document.getElementById('iconTogglePassword');
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            icon.classList.toggle('bi-eye', !isPassword);
            icon.classList.toggle('bi-eye-slash', isPassword);
        });
    </script>
</body>
</html>
