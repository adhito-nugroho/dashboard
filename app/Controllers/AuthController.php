<?php

namespace App\Controllers;

require_once __DIR__ . '/../../config/database.php';

class AuthController {
    public function showLogin(): void {
        if (isset($_SESSION['user_id'])) {
            $this->redirectToDashboard($_SESSION['role']);
            exit;
        }
        
        $pageTitle = 'Login';
        include __DIR__ . '/../../views/auth/login.php';
    }

    public function login(): void {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        try {
            $db = \Database::getConnection();
            $stmt = $db->prepare("SELECT id, username, password, role, seksi_id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['seksi_id'] = $user['seksi_id'];
                $_SESSION['is_admin'] = ($user['role'] === 'admin');
                
                $this->redirectToDashboard($user['role']);
                exit;
            } else {
                $_SESSION['error'] = 'Username atau password salah!';
                header('Location: ' . base_url('login'));
                exit;
            }
        } catch (\PDOException $e) {
            error_log('Login error: ' . $e->getMessage());
            $_SESSION['error'] = 'Terjadi kesalahan sistem. Silakan coba lagi.';
            header('Location: ' . base_url('login'));
            exit;
        }
    }

    private function redirectToDashboard(string $role): void {
        switch ($role) {
            case 'tu':
                header('Location: ' . base_url('dashboard/tu'));
                break;
            case 'rlpm':
                header('Location: ' . base_url('dashboard/rlpm'));
                break;
            case 'tkuk':
                header('Location: ' . base_url('dashboard/tkuk'));
                break;
            case 'admin':
            default:
                header('Location: ' . base_url('seksi'));
                break;
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
