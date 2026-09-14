<?php
// controllers/auth_controller.php
session_start();
require_once __DIR__ . '/../config/db.php';

$action = $_GET['action'] ?? '';

if ($action === 'login') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        header("Location: ../views/auth/login.php?status=wrong_credentials");
        exit();
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['role']    = $user['role'];

            if ($user['role'] === 'student') {
                header("Location: ../views/student/dashboard.php");
            } elseif ($user['role'] === 'lecturer') {
                header("Location: ../views/auth/GURU/DashboardGuru.php");
            } elseif ($user['role'] === 'admin') {
                header("Location: ../views/admin/dashboard.php");
            }
            exit();
        } else {
            header("Location: ../views/auth/login.php?status=wrong_credentials");
            exit();
        }
    } catch (PDOException $e) {
        die("Error Login: " . $e->getMessage());
    }

} elseif ($action === 'register') {
    $email           = trim($_POST['email'] ?? '');
    $name            = trim($_POST['name'] ?? '');
    $identity_number = trim($_POST['identity_number'] ?? '');
    $password_raw    = trim($_POST['password'] ?? '');
    $role            = trim($_POST['role'] ?? 'student');

    // Validasi data wajib isi
    if (empty($email) || empty($name) || empty($identity_number) || empty($password_raw)) {
        die("Gagal: Semua field wajib diisi!");
    }

    $password = password_hash($password_raw, PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare("INSERT INTO users (name, identity_number, email, password, role) VALUES (:name, :identity_number, :email, :password, :role)");
        $stmt->execute([
            'name'            => $name,
            'identity_number' => $identity_number,
            'email'           => $email,
            'password'        => $password,
            'role'            => $role
        ]);

        header("Location: ../views/auth/login.php?status=registered");
        exit();
    } catch (PDOException $e) {
        die("Error Database Register: " . $e->getMessage());
    }
} else {
    header("Location: ../views/auth/login.php");
    exit();
}