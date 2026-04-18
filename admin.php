<?php
header('Content-Type: text/html; charset=UTF-8');
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

// HTTP-авторизация
function requireHttpAuth() {
    $db = getDb();
    $authenticated = false;

    if (!empty($_SERVER['PHP_AUTH_USER']) && !empty($_SERVER['PHP_AUTH_PW'])) {
        $stmt = $db->prepare("SELECT password_hash FROM admins WHERE login = ?");
        $stmt->execute([$_SERVER['PHP_AUTH_USER']]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($admin && password_verify($_SERVER['PHP_AUTH_PW'], $admin['password_hash'])) {
            $authenticated = true;
        }
    }

    if (!$authenticated) {
        header('WWW-Authenticate: Basic realm="Admin Panel"');
        header('HTTP/1.0 401 Unauthorized');
        echo 'Доступ запрещён.';
        exit();
    }
}

requireHttpAuth();

$db = getDb();
$message = '';

// Удаление
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $db->prepare("DELETE FROM application_languages WHERE application_id = ?")->execute([$id]);
    $db->prepare("DELETE FROM application WHERE id = ?")->execute([$id]);
    header('Location: admin.php?deleted=1');
    exit();
}

// Сохранение редактирования
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    $id        = (int)$_POST['edit_id'];
    $fio       = trim($_POST['fio'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $birthdate = trim($_POST['birthdate'] ?? '');
    $gender    = $_POST['gender'] ?? '';
    $languages = $_POST['languages'] ?? [];
    $bio       = trim($_POST['bio'] ?? '');

    $db->prepare("
        UPDATE application SET fio=?, phone=?, email=?, birthdate=?, gender=?, biography=?
        WHERE id=?
    ")->execute([$fio, $phone, $email, $birthdate, $gender, $bio, $id]);

    $db->prepare("DELETE FROM application_languages WHERE application_id=?")->execute([$id]);
    $stmt = $db->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (?,?)");
    foreach ($languages as $lang) {
        $stmt->execute([$id, (int)$lang]);
    }

    header('Location: admin.php?saved=1');
    exit();
}

// Загружаем всех пользователей
$users = $db->query("SELECT * FROM application ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

// Загружаем языки для каждого пользователя
$userLangs = [];
$rows = $db->query("SELECT application_id, language_id FROM application_languages")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) {
    $userLangs[$row['application_id']][] = $row['language_id'];
}

// Статистика по языкам
$stats = $db->query("
    SELECT pl.name, COUNT(al.application_id) as cnt
    FROM programming_languages pl
    LEFT JOIN application_languages al ON pl.id = al.language_id
    GROUP BY pl.id, pl.name
    ORDER BY cnt DESC
")->fetchAll(PDO::FETCH_ASSOC);

$langList = [
    1=>'Pascal',2=>'C',3=>'C++',4=>'JavaScript',5=>'PHP',
    6=>'Python',7=>'Java',8=>'Haskell',9=>'Clojure',
    10=>'Prolog',11=>'Scala',12=>'Go'
];

// Какого пользователя редактируем
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : null;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Админ панель</title>
    <link rel="stylesheet" href="style.css">
    <style>
        table { width: 100%; border-collapse: collapse; margin-bottom: 30px; font-size: 13px; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background: #f5f5f5; }
        .btn { padding: 4px 10px; border: none; border-radius: 3px; cursor: pointer; font-size: 12px; text-decoration: none; display: inline-block; }
        .btn-edit { background: #2196F3; color: white; }
        .btn-delete { background: #f44336; color: white; }
        .btn-save { background: #4CAF50; color: white; }
        h2 { margin-top: 30px; }
        .stats-table { width: auto; }
        .edit-form input, .edit-form select, .edit-form textarea { width: 100%; box-sizing: border-box; padding: 4px; font-size: 12px; }
    </style>
</head>
<body style="padding: 20px;">

<h1>Админ панель</h1>

<?php if (!empty($_GET['deleted'])): ?>
    <div class="success-message">Пользователь удалён</div>
<?php endif; ?>
<?php if (!empty($_GET['saved'])): ?>
    <div class="success-message">Данные сохранены</div>
<?php endif; ?>

<h2>Пользователи</h2>

<table>
    <tr>
        <th>ID</th><th>ФИО</th><th>Телефон</th><th>Email</th>
        <th>Дата рождения</th><th>Пол</th><th>Языки</th><th>Биография</th><th>Действия</th>
    </tr>
    <?php foreach ($users as $user): ?>
        <?php $uid = $user['id']; ?>
        <tr>
            <?php if ($editId === $uid): ?>
                <form action="admin.php" method="POST" class="edit-form">
                <input type="hidden" name="edit_id" value="<?= $uid ?>">
                <td><?= $uid ?></td>
                <td><input name="fio" value="<?= htmlspecialchars($user['fio']) ?>"></td>
                <td><input name="phone" value="<?= htmlspecialchars($user['phone']) ?>"></td>
                <td><input name="email" value="<?= htmlspecialchars($user['email']) ?>"></td>
                <td><input type="date" name="birthdate" value="<?= htmlspecialchars($user['birthdate']) ?>"></td>
                <td>
                    <select name="gender">
                        <option value="male" <?= $user['gender']==='male' ? 'selected' : '' ?>>М</option>
                        <option value="female" <?= $user['gender']==='female' ? 'selected' : '' ?>>Ж</option>
                    </select>
                </td>
                <td>
                    <select name="languages[]" multiple size="6">
                        <?php foreach ($langList as $lid => $lname): ?>
                            <option value="<?= $lid ?>"
                                <?= in_array($lid, $userLangs[$uid] ?? []) ? 'selected' : '' ?>>
                                <?= $lname ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td><textarea name="bio"><?= htmlspecialchars($user['biography'] ?? '') ?></textarea></td>
                <td>
                    <button type="submit" class="btn btn-save">Сохранить</button>
                    <a href="admin.php" class="btn">Отмена</a>
                </td>
                </form>
            <?php else: ?>
                <td><?= $uid ?></td>
                <td><?= htmlspecialchars($user['fio']) ?></td>
                <td><?= htmlspecialchars($user['phone']) ?></td>
                <td><?= htmlspecialchars($user['email']) ?></td>
                <td><?= htmlspecialchars($user['birthdate']) ?></td>
                <td><?= $user['gender'] === 'male' ? 'М' : 'Ж' ?></td>
                <td>
                    <?php foreach ($userLangs[$uid] ?? [] as $lid): ?>
                        <?= htmlspecialchars($langList[$lid] ?? $lid) ?><br>
                    <?php endforeach; ?>
                </td>
                <td><?= htmlspecialchars($user['biography'] ?? '') ?></td>
                <td>
                    <a href="admin.php?edit=<?= $uid ?>" class="btn btn-edit">Ред.</a>
                    <a href="admin.php?delete=<?= $uid ?>"
                       class="btn btn-delete"
                       onclick="return confirm('Удалить пользователя?')">Удалить</a>
                </td>
            <?php endif; ?>
        </tr>
    <?php endforeach; ?>
</table>

<h2>Статистика по языкам</h2>
<table class="stats-table">
    <tr><th>Язык</th><th>Количество пользователей</th></tr>
    <?php foreach ($stats as $stat): ?>
        <tr>
            <td><?= htmlspecialchars($stat['name']) ?></td>
            <td><?= $stat['cnt'] ?></td>
        </tr>
    <?php endforeach; ?>
</table>

</body>
</html>
