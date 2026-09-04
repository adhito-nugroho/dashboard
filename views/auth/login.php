<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Dashboard Anggaran CDK Bojonegoro</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=Source+Serif+4:ital,opsz,wght@0,8..60,500;0,8..60,600;0,8..60,700;1,8..60,400&display=swap" rel="stylesheet">

    <!-- Tailwind CSS CDN + Config -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            corePlugins: {
                preflight: false,
            },
            theme: {
                extend: {
                    colors: {
                        forest: {
                            900: '#1F3D2B',
                            700: '#33553F',
                        },
                        moss: {
                            500: '#6B9080',
                            100: '#E4EBE6',
                        },
                        gold: {
                            500: '#B8874B',
                            100: '#F3E7D4',
                        },
                        paper: {
                            50: '#F7F5EF',
                            100: '#EFEAE0',
                        },
                        ink: {
                            900: '#23241F',
                            600: '#5C5A50',
                        },
                    },
                    fontFamily: {
                        serif: ['"Source Serif 4"', 'serif'],
                        sans: ['"IBM Plex Sans"', 'sans-serif'],
                    }
                }
            }
        };
    </script>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/css/style.css">
    <style>
        :root {
            --forest-900: #1F3D2B;
            --forest-700: #33553F;
            --moss-500: #6B9080;
            --moss-100: #E4EBE6;
            --gold-500: #B8874B;
            --gold-100: #F3E7D4;
            --paper-50: #F7F5EF;
            --paper-100: #EFEAE0;
            --ink-900: #23241F;
            --ink-600: #5C5A50;
            --border: #DBD5C6;
            --primary: #1F3D2B;
            --primary-dark: #33553F;
        }
        body {
            min-height: 100vh;
            background: #EFEAE0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'IBM Plex Sans', sans-serif;
            color: #23241F;
        }
        h1, h2, h3, h4, .font-serif {
            font-family: 'Source Serif 4', serif;
        }
        .login-wrapper {
            width: 100%;
            max-width: 920px;
            background: #fff;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(35, 36, 31, 0.08), 0 12px 32px -12px rgba(35, 36, 31, 0.18);
            border: 1px solid #DBD5C6;
            display: flex;
            min-height: 520px;
        }
        .login-brand-panel {
            flex: 1.05;
            background: #1F3D2B;
            color: #fff;
            padding: 2.75rem 2.5rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }
        .contour-pattern {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            opacity: 0.15;
            pointer-events: none;
        }
        .login-brand-panel .brand-content { position: relative; z-index: 1; }
        .login-brand-icon {
            width: 64px; height: 64px;
            border-radius: 12px;
            background: #fff;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 1.75rem;
            padding: 6px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
        }
        .login-brand-icon img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
        }
        .login-brand-panel h2 {
            font-family: 'Source Serif 4', serif;
            font-size: 1.7rem;
            line-height: 1.25;
            font-weight: 600;
            margin-bottom: 0.75rem;
        }
        .login-form-panel {
            flex: 1;
            padding: 3.25rem 2.75rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: #F7F5EF;
        }
        .login-form-panel h4 {
            font-family: 'Source Serif 4', serif;
            font-size: 1.45rem;
            font-weight: 600;
            color: #23241F;
        }
        .login-form-panel .form-label {
            font-weight: 500;
            font-size: 0.85rem;
            color: #23241F;
            margin-bottom: 0.4rem;
        }
        .login-form-panel .input-group {
            border-radius: 6px;
            overflow: hidden;
            border: 1px solid #DBD5C6;
            background: #fff;
            transition: all 0.15s ease;
        }
        .login-form-panel .input-group-text {
            background: #fff;
            border: none;
            color: #5C5A50;
            padding: 0.65rem 0.85rem;
        }
        .login-form-panel .form-control {
            border: none;
            padding: 0.65rem 0.85rem;
            font-family: 'IBM Plex Sans', sans-serif;
            font-size: 0.875rem;
            color: #23241F;
            background: #fff;
        }
        .login-form-panel .input-group:focus-within {
            border-color: #B8874B !important;
            box-shadow: 0 0 0 3px #F3E7D4 !important;
        }
        .login-form-panel .form-control:focus {
            box-shadow: none;
            outline: none;
        }
        .btn-login {
            background: #1F3D2B;
            border: none;
            color: #fff;
            font-weight: 600;
            font-size: 0.95rem;
            padding: 0.75rem;
            border-radius: 6px;
            transition: background 0.15s ease;
            margin-top: 0.5rem;
        }
        .btn-login:hover {
            background: #33553F;
            color: #fff;
        }
        .btn-login:focus {
            background: #33553F;
            box-shadow: 0 0 0 3px #F3E7D4;
        }
        .btn-toggle-password {
            background: #fff;
            border: none;
            cursor: pointer;
            color: #5C5A50;
        }
        .login-footer-note {
            font-size: 0.75rem;
            color: #5C5A50;
            text-align: center;
            margin-top: 2rem;
            line-height: 1.5;
        }
        /* Chrome/Edge autofill */
        .login-form-panel .form-control:-webkit-autofill,
        .login-form-panel .form-control:-webkit-autofill:hover,
        .login-form-panel .form-control:-webkit-autofill:focus {
            -webkit-box-shadow: 0 0 0 1000px #fff inset;
            -webkit-text-fill-color: #23241F;
            transition: background-color 5000s ease-in-out 0s;
            caret-color: #23241F;
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
            <svg class="contour-pattern" viewBox="0 0 400 460" preserveAspectRatio="none">
                <path d="M-20,80 C60,40 120,120 200,90 C280,60 320,130 420,100" stroke="#fff" stroke-width="1.4" fill="none"/>
                <path d="M-20,140 C60,100 120,180 200,150 C280,120 320,190 420,160" stroke="#fff" stroke-width="1.4" fill="none"/>
                <path d="M-20,200 C60,160 120,240 200,210 C280,180 320,250 420,220" stroke="#fff" stroke-width="1.4" fill="none"/>
                <path d="M-20,260 C60,220 120,300 200,270 C280,240 320,310 420,280" stroke="#fff" stroke-width="1.4" fill="none"/>
                <path d="M-20,320 C60,280 120,360 200,330 C280,300 320,370 420,340" stroke="#fff" stroke-width="1.4" fill="none"/>
                <path d="M-20,380 C60,340 120,420 200,390 C280,360 320,430 420,400" stroke="#fff" stroke-width="1.4" fill="none"/>
            </svg>
            <div class="brand-content">
                <div class="login-brand-icon">
                    <img src="<?= base_url('images/logo_jatim.png') ?>" alt="Logo Jawa Timur">
                </div>
                <div style="font-size:0.75rem;font-weight:500;letter-spacing:0.04em;color:#F3E7D4;margin-bottom:0.5rem;opacity:0.9;">Dinas Kehutanan Provinsi Jawa Timur</div>
                <h2 class="fw-bold mb-2">Cabang Dinas<br>Kehutanan Wilayah<br>Bojonegoro</h2>
                <p class="mb-0" style="color:#D9E2DC;font-size:0.875rem;line-height:1.5;">
                    Dashboard Anggaran — sistem pengelolaan anggaran &amp; transaksi seksi.
                </p>
            </div>
            <div class="login-left-footer mt-4" style="font-size:0.75rem;color:#B9C7BF;line-height:1.5;position:relative;z-index:1;">
                © 2026 Dinas Kehutanan Provinsi Jawa Timur<br>Cabang Dinas Kehutanan Wilayah Bojonegoro
            </div>
        </div>

        <!-- PANEL KANAN: FORM LOGIN -->
        <div class="login-form-panel">
            <div class="mb-4">
                <h4 class="fw-bold mb-1">Masuk ke akun Anda</h4>
                <p class="text-muted mb-0" style="font-size:0.875rem;color:#5C5A50 !important;">Gunakan akun seksi yang terdaftar untuk melanjutkan.</p>
            </div>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger py-2 small mb-3">
                    <i class="bi bi-exclamation-circle me-1"></i> <?= htmlspecialchars($_SESSION['error']) ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <form action="/login" method="POST">
                <div class="mb-3">
                    <label class="form-label">Nama pengguna</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person text-muted"></i></span>
                        <input type="text" name="username" class="form-control" placeholder="Masukkan nama pengguna"
                               required autofocus autocomplete="username">
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label">Kata sandi</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-key text-muted"></i></span>
                        <input type="password" name="password" id="passwordInput" class="form-control"
                               placeholder="Masukkan kata sandi" required autocomplete="current-password">
                        <button type="button" class="input-group-text btn-toggle-password" id="btnTogglePassword">
                            <i class="bi bi-eye" id="iconTogglePassword"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn btn-login w-100 text-white">
                    Masuk
                </button>
            </form>

            <p class="login-footer-note">
                Lupa kata sandi? Hubungi admin Subbagian Tata Usaha.
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
