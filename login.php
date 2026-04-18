<?php
header('Content-Type: text/html; charset=UTF-8');
session_start();

require_once __DIR__ . '/config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $login    = trim($_POST['login'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($login) || empty($password)) {
        $error = 'Введите логин и пароль';
    } else {
        try {
            $db = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8',
                DB_USER, DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            $stmt = $db->prepare("SELECT id, login, password_hash FROM application WHERE login=?");
            $stmt->execute([$login]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['app_id'] = $user['id'];
                $_SESSION['login']  = $user['login'];
                header('Location: index.php');
                exit();
            } else {
                $error = 'Неверный логин или пароль';
            }
        } catch (PDOException $e) {
            $error = 'Ошибка БД: ' . htmlspecialchars($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Вход</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h2>Вход</h2>
    <?php if ($error): ?>
        <div class="error-message">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form action="login.php" method="POST">
        <div class="form-group">
            <label>Логин:</label>
            <input type="text" name="login" value="<?= htmlspecialchars($_POST['login'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label>Пароль:</label>
            <input type="password" name="password">
        </div>
        <div class="form-group">
            <button type="submit">Войти</button>
        </div>
    </form>
    <p><a href="index.php">← Назад к форме</a></p>
</div>
</body>
</html>
