<?php
// controllers/auth_controller.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../config/db.php';

$action = $_GET['action'] ?? '';

if ($action === 'login') {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        header("Location: ../views/auth/login.php?status=wrong_credentials");
        exit();
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            // Set session kunci untuk keamanan & kompatibilitas foreign key
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['id']      = $user['id']; 
            $_SESSION['name']    = $user['name'];
            $_SESSION['email']   = $user['email'];
            $_SESSION['role']    = $user['role'];

            // Redirect sesuai 2 role utama (Pastikan lokasi relatif file views benar)
            if ($user['role'] === 'student') {
                header("Location: ../views/student/dashboard.php");
                exit();
            } elseif ($user['role'] === 'lecturer') {
                header("Location: ../views/lecturer/dashboard.php");
                exit();
            } else {
                header("Location: ../views/auth/login.php?status=wrong_credentials");
                exit();
            }
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

    if (empty($email) || empty($name) || empty($identity_number) || empty($password_raw)) {
        header("Location: ../views/auth/login.php?status=error");
        exit();
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