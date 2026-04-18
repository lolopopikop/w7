<?php
header('Content-Type: text/html; charset=UTF-8');
session_start();

require_once __DIR__ . '/config.php';

function getDb() {
    static $db = null;
    if ($db === null) {
        $db = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }
    return $db;
}

$errors = [];
$isAuth = isset($_SESSION['app_id']);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $fio       = trim($_POST['fio'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $birthdate = trim($_POST['birthdate'] ?? '');
    $gender    = $_POST['gender'] ?? '';
    $languages = $_POST['languages'] ?? [];
    $bio       = trim($_POST['bio'] ?? '');
    $contract  = isset($_POST['contract']) ? 1 : 0;

    // Валидация
    if (empty($fio)) {
        $errors['fio'] = 'ФИО обязательно для заполнения';
    } elseif (strlen($fio) > 150) {
        $errors['fio'] = 'ФИО не должно превышать 150 символов';
    } elseif (!preg_match('/^[a-zA-Zа-яА-ЯёЁ\s\-]+$/u', $fio)) {
        $errors['fio'] = 'ФИО может содержать только буквы, пробелы и дефис';
    }

    if (empty($phone)) {
        $errors['phone'] = 'Телефон обязателен для заполнения';
    } elseif (!preg_match('/^[\d\s\+\(\)\-]{5,20}$/', $phone)) {
        $errors['phone'] = 'Телефон: только цифры, +, -, пробелы и скобки (5-20 символов)';
    }

    if (empty($email)) {
        $errors['email'] = 'Email обязателен для заполнения';
    } elseif (!preg_match('/^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/', $email)) {
        $errors['email'] = 'Email: только латинские буквы, цифры, точка, дефис, подчёркивание и @';
    }

    if (empty($birthdate)) {
        $errors['birthdate'] = 'Дата рождения обязательна';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthdate)) {
        $errors['birthdate'] = 'Неверный формат даты';
    } elseif (strtotime($birthdate) > time()) {
        $errors['birthdate'] = 'Дата рождения не может быть в будущем';
    }

    if (empty($gender)) {
        $errors['gender'] = 'Выберите пол';
    } elseif (!in_array($gender, ['male', 'female'])) {
        $errors['gender'] = 'Недопустимое значение пола';
    }

    if (empty($languages)) {
        $errors['languages'] = 'Выберите хотя бы один язык программирования';
    } else {
        $validLangIds = array_map('strval', range(1, 12));
        foreach ($languages as $lang) {
            if (!in_array($lang, $validLangIds)) {
                $errors['languages'] = 'Выбран недопустимый язык программирования';
                break;
            }
        }
    }

    // Чекбокс контракта — только для новых (не авторизованных)
    if (!$isAuth && !$contract) {
        $errors['contract'] = 'Необходимо подтвердить ознакомление с контрактом';
    }

    $formData = compact('fio','phone','email','birthdate','gender','languages','bio','contract');

    if (!empty($errors)) {
        setcookie('form_errors', json_encode($errors), 0, '/');
        setcookie('form_data',   json_encode($formData), 0, '/');
        header('Location: index.php');
        exit();
    }

    // Нет ошибок
    try {
        $db = getDb();

        if ($isAuth) {
            // Обновляем существующую запись
            $app_id = $_SESSION['app_id'];

            $db->beginTransaction();

            $stmt = $db->prepare("
                UPDATE application
                SET fio=?, phone=?, email=?, birthdate=?, gender=?, biography=?
                WHERE id=?
            ");
            $stmt->execute([$fio, $phone, $email, $birthdate, $gender, $bio, $app_id]);

            $db->prepare("DELETE FROM application_languages WHERE application_id=?")->execute([$app_id]);

            $stmt = $db->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (?,?)");
            foreach ($languages as $lang) {
                $stmt->execute([$app_id, $lang]);
            }

            $db->commit();

            header('Location: index.php?save=1');
            exit();

        } else {
            // Новая запись — генерируем логин и пароль
            $login    = 'user_' . substr(md5(uniqid()), 0, 8);
            $password = substr(md5(uniqid() . rand()), 0, 10);
            $passHash = password_hash($password, PASSWORD_DEFAULT);

            $db->beginTransaction();

            $stmt = $db->prepare("
                INSERT INTO application (fio, phone, email, birthdate, gender, biography, contract, login, password_hash)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$fio, $phone, $email, $birthdate, $gender, $bio, $contract, $login, $passHash]);
            $app_id = $db->lastInsertId();

            $stmt = $db->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (?,?)");
            foreach ($languages as $lang) {
                $stmt->execute([$app_id, $lang]);
            }

            $db->commit();

            // Сохраняем данные в куки на год
            $year = time() + 365 * 24 * 60 * 60;
            setcookie('form_data', json_encode($formData), $year, '/');

            // Передаём логин/пароль через сессию чтобы показать один раз
            $_SESSION['new_credentials'] = ['login' => $login, 'password' => $password];

            header('Location: index.php?save=1');
            exit();
        }

    } catch (PDOException $e) {
        die('Ошибка БД: ' . htmlspecialchars($e->getMessage()));
    }
}

// GET
$errors   = [];
$formData = [];

if (!empty($_COOKIE['form_errors'])) {
    $errors = json_decode($_COOKIE['form_errors'], true) ?? [];
    setcookie('form_errors', '', time() - 1, '/');
}

// Если авторизован — грузим данные из БД
if ($isAuth) {
    try {
        $db = getDb();
        $app_id = $_SESSION['app_id'];
        $stmt = $db->prepare("SELECT * FROM application WHERE id=?");
        $stmt->execute([$app_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt2 = $db->prepare("SELECT language_id FROM application_languages WHERE application_id=?");
        $stmt2->execute([$app_id]);
        $langs = $stmt2->fetchAll(PDO::FETCH_COLUMN);

        $formData = [
            'fio'       => $row['fio'],
            'phone'     => $row['phone'],
            'email'     => $row['email'],
            'birthdate' => $row['birthdate'],
            'gender'    => $row['gender'],
            'languages' => $langs,
            'bio'       => $row['biography'],
            'contract'  => $row['contract'],
        ];
    } catch (PDOException $e) {
        die('Ошибка БД: ' . htmlspecialchars($e->getMessage()));
    }
} elseif (!empty($_COOKIE['form_data'])) {
    $formData = json_decode($_COOKIE['form_data'], true) ?? [];
}

function fd($key, $formData) {
    return isset($formData[$key]) ? htmlspecialchars($formData[$key]) : '';
}

// Забираем credentials из сессии (показываем один раз)
$newCredentials = null;
if (!empty($_SESSION['new_credentials'])) {
    $newCredentials = $_SESSION['new_credentials'];
    unset($_SESSION['new_credentials']);
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Анкета</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="top-bar">
    <?php if ($isAuth): ?>
        <span>Вы вошли как <strong><?= htmlspecialchars($_SESSION['login']) ?></strong></span>
        <a href="logout.php">Выйти</a>
    <?php else: ?>
        <a href="login.php">Войти для редактирования</a>
    <?php endif; ?>
</div>

<?php if (!empty($_GET['save'])): ?>
    <div class="success-message">Данные успешно сохранены</div>
<?php endif; ?>

<?php if ($newCredentials): ?>
    <div class="credentials-box">
        <strong>Сохраните ваши данные для входа (показываются один раз!):</strong><br>
        Логин: <code><?= htmlspecialchars($newCredentials['login']) ?></code><br>
        Пароль: <code><?= htmlspecialchars($newCredentials['password']) ?></code>
    </div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="errors">
        <?php foreach ($errors as $error): ?>
            <div class="error-message">⚠️ <?= htmlspecialchars($error) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include('form.php'); ?>

</body>
</html>
