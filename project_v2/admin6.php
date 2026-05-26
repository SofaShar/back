<?php
/**
 * ЗАДАНИЕ 6 + ЗАДАНИЕ 7
 * Админ-панель с HTTP Basic Auth
 */

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

header('Content-Type: text/html; charset=UTF-8');

$db_host = 'localhost';
$db_user = 'u82255';
$db_pass = '7423606';
$db_name = 'u82255';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Ошибка подключения к БД");
}

// HTTP Basic Auth
$admin_login = 'admin';
$admin_password = 'admin123';

if (!isset($_SERVER['PHP_AUTH_USER']) ||
    $_SERVER['PHP_AUTH_USER'] != $admin_login ||
    $_SERVER['PHP_AUTH_PW'] != $admin_password) {
    header('WWW-Authenticate: Basic realm="Admin Area"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Доступ запрещён';
    exit;
}

// Удаление записи
if (isset($_GET['delete'])) {
    if (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Ошибка CSRF');
    }
    $stmt = $pdo->prepare("DELETE FROM submissions WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header('Location: admin.php');
    exit;
}

// Редактирование записи
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_id'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Ошибка CSRF');
    }

    $id = $_POST['edit_id'];
    $stmt = $pdo->prepare("
        UPDATE submissions SET
        fio = ?, phone = ?, email = ?, birthdate = ?,
        gender = ?, biography = ?, contract_accepted = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $_POST['fio'], $_POST['phone'], $_POST['email'],
        $_POST['birthdate'], $_POST['gender'], $_POST['biography'],
        isset($_POST['contract_accepted']) ? 1 : 0, $id
    ]);

    $pdo->prepare("DELETE FROM submission_languages WHERE submission_id = ?")->execute([$id]);
    if (!empty($_POST['languages'])) {
        $stmtLang = $pdo->prepare("
            INSERT INTO submission_languages (submission_id, language_id)
            VALUES (?, (SELECT id FROM languages WHERE name = ?))
        ");
        foreach ($_POST['languages'] as $lang) {
            $stmtLang->execute([$id, $lang]);
        }
    }
    header('Location: admin.php');
    exit;
}

$submissions = $pdo->query("
    SELECT s.*, GROUP_CONCAT(l.name SEPARATOR ',') as languages_list
    FROM submissions s
    LEFT JOIN submission_languages sl ON s.id = sl.submission_id
    LEFT JOIN languages l ON sl.language_id = l.id
    GROUP BY s.id
    ORDER BY s.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$stats = $pdo->query("
    SELECT l.name, COUNT(sl.submission_id) as cnt
    FROM languages l
    LEFT JOIN submission_languages sl ON l.id = sl.language_id
    GROUP BY l.id
    ORDER BY cnt DESC, l.name
")->fetchAll(PDO::FETCH_ASSOC);

$allLanguages = $pdo->query("SELECT name FROM languages")->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Задание 6 — Админ-панель</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; padding: 30px; }
        .container { max-width: 1400px; margin: 0 auto; }
        h1, h2 { color: #1e3c72; margin-bottom: 20px; }
        table { width: 100%; background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.1); margin-bottom: 30px; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { background: #1e3c72; color: white; }
        tr:hover { background: #f8f9fa; }
        .edit-btn, .delete-btn { padding: 6px 12px; border: none; border-radius: 8px; cursor: pointer; font-size: 13px; text-decoration: none; display: inline-block; }
        .edit-btn { background: #2a5298; color: white; margin-right: 5px; }
        .delete-btn { background: #e53e3e; color: white; }
        .stats-card { background: white; border-radius: 16px; padding: 20px; margin-bottom: 30px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 15px; margin-top: 15px; }
        .stat-item { background: #edf2f7; padding: 12px; border-radius: 12px; text-align: center; }
        .stat-count { font-size: 28px; font-weight: bold; color: #2a5298; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; }
        .modal-content { background: white; padding: 25px; border-radius: 20px; width: 500px; max-width: 90%; }
        .modal-content input, .modal-content select, .modal-content textarea { width: 100%; padding: 8px; margin: 8px 0; border: 1px solid #ccc; border-radius: 8px; }
        .close { float: right; cursor: pointer; font-size: 24px; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔐 Админ-панель (Задание 6 + 7)</h1>

    <div class="stats-card">
        <h2>📊 Статистика: любимые языки программирования</h2>
        <div class="stats-grid">
            <?php foreach ($stats as $stat): ?>
                <div class="stat-item">
                    <div class="stat-count"><?= htmlspecialchars($stat['cnt'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div><?= htmlspecialchars($stat['name'], ENT_QUOTES, 'UTF-8') ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <h2>👥 Все пользователи</h2>
    <table>
        <thead>
            <tr><th>ID</th><th>ФИО</th><th>Email</th><th>Телефон</th><th>Дата</th><th>Пол</th><th>Языки</th><th>Биография</th><th>Действия</th></tr>
        </thead>
        <tbody>
            <?php foreach ($submissions as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($row['fio'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($row['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($row['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($row['birthdate'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= $row['gender'] == 'male' ? 'Муж' : 'Жен' ?></td>
                    <td><?= htmlspecialchars($row['languages_list'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars(substr($row['biography'] ?? '', 0, 50), ENT_QUOTES, 'UTF-8') ?>...</td>
                    <td>
                        <button class="edit-btn" onclick="openEdit(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>)">✏️</button>
                        <a href="?delete=<?= $row['id'] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="delete-btn" onclick="return confirm('Удалить?')">🗑️</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <h3>Редактирование записи</h3>
        <form method="POST">
            <input type="hidden" name="edit_id" id="edit_id">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
            <label>ФИО:</label><input type="text" name="fio" id="edit_fio" required>
            <label>Телефон:</label><input type="text" name="phone" id="edit_phone">
            <label>Email:</label><input type="email" name="email" id="edit_email">
            <label>Дата рождения:</label><input type="date" name="birthdate" id="edit_birthdate">
            <label>Пол:</label>
            <select name="gender" id="edit_gender">
                <option value="male">Мужской</option>
                <option value="female">Женский</option>
            </select>
            <label>Биография:</label><textarea name="biography" id="edit_biography" rows="3"></textarea>
            <label>Языки (Ctrl+выбор):</label>
            <select name="languages[]" multiple size="6" id="edit_languages">
                <?php foreach ($allLanguages as $lang): ?>
                    <option value="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
            <label><input type="checkbox" name="contract_accepted" id="edit_contract"> Контракт принят</label>
            <button type="submit">💾 Сохранить</button>
        </form>
    </div>
</div>

<script>
function openEdit(row) {
    document.getElementById('edit_id').value = row.id;
    document.getElementById('edit_fio').value = row.fio;
    document.getElementById('edit_phone').value = row.phone;
    document.getElementById('edit_email').value = row.email;
    document.getElementById('edit_birthdate').value = row.birthdate;
    document.getElementById('edit_gender').value = row.gender;
    document.getElementById('edit_biography').value = row.biography || '';
    document.getElementById('edit_contract').checked = row.contract_accepted == 1;
    let langs = row.languages_list ? row.languages_list.split(',') : [];
    let select = document.getElementById('edit_languages');
    for (let i = 0; i < select.options.length; i++) {
        select.options[i].selected = langs.includes(select.options[i].value);
    }
    document.getElementById('editModal').style.display = 'flex';
}
function closeModal() { document.getElementById('editModal').style.display = 'none'; }
window.onclick = function(e) { if (e.target == document.getElementById('editModal')) closeModal(); }
</script>
</body>
