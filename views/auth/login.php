<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1b3225">
    <title>Masuk — Monitoring Anggaran CDK Bojonegoro</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;600&family=IBM+Plex+Sans:wght@400;600&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/css/style.css">
    <style>
        /* Token desain — dipakai juga oleh prototipe dashboard monitoring. */
        :root {
            --forest-950: #14271c;
            --forest-900: #1b3225;
            --forest-800: #1f3d2b;
            --forest-700: #2e5d3f;
            --forest-600: #3a6e4c;
            --forest-100: #dfe8df;
            --gold-500: #b8874b;
            --paper: #f4f1e9;
            --paper-2: #efeae0;
            --surface: #fffdf8;
            --surface-2: #f7f3ea;
            --ink: #23241f;
            --ink-2: #5c5a50;
            --ink-3: #66645a;
            --line: #ded7c8;
            --line-strong: #cfc6b4;
            --line-soft: #ede8dc;
            --danger-text: #8c2f24;
            --danger-bg: #f6e4e0;
            --danger-line: #e3bbb3;
            --font-sans: "IBM Plex Sans", system-ui, -apple-system, "Segoe UI", sans-serif;
            --font-mono: "IBM Plex Mono", ui-monospace, "Cascadia Mono", monospace;
            --fs-1: 12px;
            --fs-2: 14px;
            --fs-3: 22px;
            --fw-normal: 400;
            --fw-strong: 600;
            --radius-sm: 4px;
            --radius: 6px;
            --focus: #3a6e4c;
            --transition: 150ms ease;
        }

        body {
            margin: 0;
            min-height: 100vh;
            padding: 32px 24px;
            display: grid;
            place-items: center;
            background: var(--paper-2);
            color: var(--ink);
            font-family: var(--font-sans);
            font-size: var(--fs-2);
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }
        ::selection { background: var(--forest-100); }

        .login-shell {
            width: 100%;
            max-width: 900px;
            display: grid;
            grid-template-columns: minmax(0, 0.95fr) minmax(0, 1.05fr);
            background: var(--surface);
            border: 1px solid var(--line-strong);
            border-radius: var(--radius);
            overflow: hidden;
        }

        /* Panel identitas */
        .brand-panel {
            padding: 32px;
            background: var(--forest-900);
            color: #eef2ed;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 32px;
        }
        .brand-top { display: flex; align-items: center; gap: 12px; }
        .brand-mark {
            flex: none;
            width: 44px;
            height: 44px;
            padding: 4px;
            display: grid;
            place-items: center;
            background: var(--surface);
            border-radius: var(--radius-sm);
        }
        .brand-mark img { width: 100%; height: 100%; object-fit: contain; display: block; }
        .brand-org {
            display: block;
            font-size: var(--fs-1);
            line-height: 1.3;
            color: #a6b6a9;
        }
        .brand-app {
            display: block;
            margin-top: 2px;
            font-size: var(--fs-2);
            font-weight: var(--fw-strong);
            line-height: 1.3;
            color: #fff;
        }
        .brand-main {
            padding-top: 28px;
            border-top: 1px solid rgba(255, 255, 255, .14);
        }
        .brand-kicker {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0 0 8px;
            font-size: var(--fs-1);
            color: #c9d6cc;
        }
        .brand-kicker::before {
            content: "";
            width: 2px;
            height: 12px;
            border-radius: 1px;
            background: var(--gold-500);
        }
        .brand-main h1 {
            margin: 0;
            font-size: var(--fs-3);
            font-weight: var(--fw-strong);
            line-height: 1.25;
            letter-spacing: -.01em;
            color: #fff;
        }
        .brand-desc {
            max-width: 34ch;
            margin: 12px 0 0;
            font-size: var(--fs-2);
            line-height: 1.55;
            color: #b9c7bf;
        }
        .brand-foot {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding-top: 16px;
            border-top: 1px solid rgba(255, 255, 255, .14);
            font-size: var(--fs-1);
            color: #a6b6a9;
        }
        .brand-foot .ta {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: var(--font-mono);
        }
        .brand-foot .ta::before {
            content: "";
            width: 5px;
            height: 5px;
            border-radius: 1px;
            background: var(--gold-500);
        }

        /* Panel formulir */
        .form-panel {
            padding: 40px 44px;
            display: flex;
            align-items: center;
            background: var(--surface);
        }
        .form-inner { width: 100%; max-width: 344px; margin: 0 auto; }
        .form-head h2 {
            margin: 0;
            font-size: var(--fs-3);
            font-weight: var(--fw-strong);
            line-height: 1.3;
            letter-spacing: -.01em;
            color: var(--ink);
        }
        .form-head p {
            margin: 6px 0 0;
            font-size: var(--fs-2);
            line-height: 1.5;
            color: var(--ink-2);
        }

        .form-error {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            margin-top: 20px;
            padding: 10px 12px;
            background: var(--danger-bg);
            border: 1px solid var(--danger-line);
            border-left: 2px solid var(--danger-text);
            border-radius: var(--radius-sm);
            color: var(--danger-text);
            font-size: var(--fs-1);
            font-weight: var(--fw-strong);
            line-height: 1.45;
        }
        .form-error i { font-size: 13px; line-height: 1.3; }

        .login-form {
            display: flex;
            flex-direction: column;
            gap: 16px;
            margin-top: 24px;
        }
        .field { display: flex; flex-direction: column; gap: 6px; }
        .field label {
            font-size: var(--fs-1);
            font-weight: var(--fw-strong);
            color: var(--ink);
        }
        .control {
            display: flex;
            align-items: center;
            background: var(--surface);
            border: 1px solid var(--line-strong);
            border-radius: var(--radius-sm);
            transition: border-color var(--transition);
        }
        .control:focus-within {
            border-color: var(--forest-700);
            outline: 2px solid var(--focus);
            outline-offset: 1px;
        }
        .control-icon {
            flex: none;
            width: 40px;
            display: grid;
            place-items: center;
            color: var(--ink-3);
            font-size: 14px;
        }
        .control input {
            flex: 1;
            min-width: 0;
            height: 42px;
            padding: 0 12px 0 0;
            border: 0;
            background: transparent;
            color: var(--ink);
            font-family: var(--font-sans);
            font-size: var(--fs-2);
            outline: none;
        }
        .control input::placeholder { color: var(--ink-3); opacity: 1; }
        .toggle {
            flex: none;
            width: 40px;
            height: 42px;
            padding: 0;
            display: grid;
            place-items: center;
            border: 0;
            border-left: 1px solid var(--line-soft);
            border-radius: 0 3px 3px 0;
            background: transparent;
            color: var(--ink-2);
            cursor: pointer;
        }
        .toggle:hover { background: var(--surface-2); color: var(--ink); }
        .toggle:focus-visible {
            outline: 2px solid var(--focus);
            outline-offset: -2px;
        }

        .btn-login {
            width: 100%;
            height: 44px;
            margin-top: 4px;
            border: 1px solid var(--forest-800);
            border-radius: var(--radius-sm);
            background: var(--forest-800);
            color: #fff;
            font-family: var(--font-sans);
            font-size: var(--fs-2);
            font-weight: var(--fw-strong);
            cursor: pointer;
            transition: background-color var(--transition), border-color var(--transition);
        }
        .btn-login:hover { background: var(--forest-700); border-color: var(--forest-700); }
        .btn-login:focus-visible {
            outline: 2px solid var(--focus);
            outline-offset: 2px;
        }

        .form-note {
            margin: 20px 0 0;
            font-size: var(--fs-1);
            line-height: 1.5;
            color: var(--ink-2);
            text-align: center;
        }
        .form-meta {
            margin: 32px 0 0;
            padding-top: 16px;
            border-top: 1px solid var(--line-soft);
            font-size: var(--fs-1);
            line-height: 1.45;
            color: var(--ink-3);
            text-align: center;
        }

        /* Chrome/Edge autofill */
        .control input:-webkit-autofill,
        .control input:-webkit-autofill:hover,
        .control input:-webkit-autofill:focus {
            -webkit-box-shadow: 0 0 0 1000px var(--surface) inset;
            -webkit-text-fill-color: var(--ink);
            transition: background-color 5000s ease-in-out 0s;
            caret-color: var(--ink);
        }

        @media (max-width: 860px) {
            body { padding: 16px; }
            .login-shell {
                grid-template-columns: 1fr;
                max-width: 440px;
            }
            .brand-panel { padding: 24px; gap: 24px; }
            .brand-main { padding-top: 20px; }
            .brand-desc { max-width: none; }
            .form-panel { padding: 28px 24px 32px; }
            .form-inner { max-width: none; }
        }
        @media (max-width: 420px) {
            .form-panel { padding: 24px 20px 28px; }
        }
        @media (prefers-reduced-motion: reduce) {
            * { transition-duration: .01ms !important; }
        }
    </style>
</head>
<body>
    <main class="login-shell">
        <!-- Panel identitas -->
        <section class="brand-panel" aria-label="Identitas aplikasi">
            <div class="brand-top">
                <div class="brand-mark">
                    <img src="<?= base_url('images/logo_jatim.png') ?>" alt="Logo Jawa Timur">
                </div>
                <div>
                    <span class="brand-org">Dinas Kehutanan Provinsi Jawa Timur</span>
                    <span class="brand-app">Monitoring Anggaran</span>
                </div>
            </div>

            <div class="brand-main">
                <p class="brand-kicker">Cabang Dinas Kehutanan</p>
                <h1>Wilayah Bojonegoro</h1>
                <p class="brand-desc">Pagu, RAK, realisasi, dan verifikasi transaksi per seksi dalam satu dashboard.</p>
            </div>

            <div class="brand-foot">
                <span class="ta">TA <?= date('Y') ?></span>
                <span>CDK Wilayah Bojonegoro</span>
            </div>
        </section>

        <!-- Panel formulir -->
        <section class="form-panel" aria-labelledby="loginTitle">
            <div class="form-inner">
                <header class="form-head">
                    <h2 id="loginTitle">Masuk ke akun</h2>
                    <p>Gunakan akun seksi yang terdaftar untuk melanjutkan.</p>
                </header>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="form-error" role="alert">
                        <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
                        <span><?= htmlspecialchars($_SESSION['error']) ?></span>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <form action="/login" method="POST" class="login-form">
                    <div class="field">
                        <label for="username">Nama pengguna</label>
                        <div class="control">
                            <span class="control-icon"><i class="bi bi-person" aria-hidden="true"></i></span>
                            <input type="text" id="username" name="username" placeholder="Masukkan nama pengguna"
                                   required autofocus autocomplete="username">
                        </div>
                    </div>
                    <div class="field">
                        <label for="passwordInput">Kata sandi</label>
                        <div class="control">
                            <span class="control-icon"><i class="bi bi-key" aria-hidden="true"></i></span>
                            <input type="password" id="passwordInput" name="password" placeholder="Masukkan kata sandi"
                                   required autocomplete="current-password">
                            <button type="button" class="toggle" id="btnTogglePassword" aria-label="Tampilkan kata sandi" aria-pressed="false">
                                <i class="bi bi-eye" id="iconTogglePassword" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="btn-login">Masuk</button>
                </form>

                <p class="form-note">Lupa kata sandi? Hubungi admin Subbagian Tata Usaha.</p>
                <p class="form-meta">© <?= date('Y') ?> Dinas Kehutanan Provinsi Jawa Timur</p>
            </div>
        </section>
    </main>

    <script>
        document.getElementById('btnTogglePassword').addEventListener('click', function () {
            const input = document.getElementById('passwordInput');
            const icon = document.getElementById('iconTogglePassword');
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            this.setAttribute('aria-pressed', String(show));
            this.setAttribute('aria-label', show ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
            icon.classList.toggle('bi-eye', !show);
            icon.classList.toggle('bi-eye-slash', show);
        });
    </script>
</body>
</html>
