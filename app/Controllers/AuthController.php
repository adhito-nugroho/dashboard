<?php

namespace App\Controllers;

class AuthController {
    public function showLogin(): void {
        if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
            header('Location: ' . base_url('seksi'));
            exit;
        }
        
        $pageTitle = 'Login Admin';
        include __DIR__ . '/../../views/auth/login.php';
    }

    public function login(): void {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        // Kredensial hardcoded untuk admin sementara
        if ($username === 'admin' && $password === 'admin') {
            $_SESSION['is_admin'] = true;
            header('Location: ' . base_url('seksi'));
            exit;
        } else {
            $_SESSION['error'] = 'Username atau password salah!';
            header('Location: ' . base_url('login'));
            exit;
        }
    }

    public function logout(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        header('Location: ' . base_url());
        exit;
    }
}
