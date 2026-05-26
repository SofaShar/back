// Сообщение из сессии (для отображения результата)
$msg = '';
if (isset($_SESSION['admin_msg'])) {
    $msg = $_SESSION['admin_msg'];
    unset($_SESSION['admin_msg']);
}

// Обработка удаления (через POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user_id'])) {
    $id = (int)$_POST['delete_user_id'];

    $stmt = $pdo->prepare("SELECT application_id FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && $user['application_id']) {
        $pdo->prepare("DELETE FROM application WHERE id = ?")->execute([$user['application_id']]);
    }
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);

    $_SESSION['admin_msg'] = '<div class="success">✅ Пользователь удалён</div>';
    header('Location: admin.php');
    exit;
}

// Обработка редактирования
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user_id'])) {
    $user_id = $_POST['edit_user_id'];
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    $dob = $_POST['dob'];
    $gender = $_POST['gender'];
    $bio = $_POST['bio'];
    $languages = $_POST['languages'] ?? [];

    $stmt = $pdo->prepare("SELECT application_id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && $user['application_id']) {
        $app_id = $user['application_id'];

        $stmt = $pdo->prepare("UPDATE application SET name=?, phone=?, email=?, dob=?, gender=?, bio=? WHERE id=?");
        $stmt->execute([$name, $phone, $email, $dob, $gender, $bio, $app_id]);

        $pdo->prepare("DELETE FROM application_languages WHERE application_id = ?")->execute([$app_id]);
        $stmtLang = $pdo->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (?, (SELECT id FROM languages WHERE name = ?))");
        foreach ($languages as $lang) {
            $stmtLang->execute([$app_id, $lang]);
        }

        $_SESSION['admin_msg'] = '<div class="success">✅ Анкета обновлена</div>';
    } else {
        $_SESSION['admin_msg'] = '<div class="error">❌ У пользователя нет анкеты</div>';
    }
    header('Location: admin.php');
    exit;
}

// Статистика по языкам
$stmt = $pdo->query("
    SELECT l.name, COUNT(al.language_id) as cnt
    FROM languages l
    LEFT JOIN application_languages al ON l.id = al.language_id
    GROUP BY l.id
    ORDER BY cnt DESC, l.name
");
$langStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Все пользователи
$stmt = $pdo->query("
    SELECT u.id, u.username, u.created_at, a.id as app_id, a.name, a.phone, a.email, a.dob, a.gender, a.bio,
           GROUP_CONCAT(l.name SEPARATOR ', ') as languages
    FROM users u
    LEFT JOIN application a ON u.application_id = a.id
    LEFT JOIN application_languages al ON a.id = al.application_id
    LEFT JOIN languages l ON al.language_id = l.id
    GROUP BY u.id
    ORDER BY u.id DESC
");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$allLanguages = $pdo->query("SELECT name FROM languages ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Админ-панель</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial; background: #1e3c72; padding: 20px; }
        .container { max-width: 1400px; margin: 0 auto; background: white; border-radius: 20px; padding: 25px; }
        h1 { color: #1e3c72; margin-bottom: 20px; }
        h2 { color: #1e3c72; margin: 20px 0 15px; font-size: 1.5em; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
        .top-bar a { color: #2a5298; text-decoration: none; margin: 0 10px; }
        .logout-btn { background: #e53e3e; color: white !important; padding: 8px 15px; border-radius: 8px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 15px; margin-bottom: 30px; }
        .stat-card { background: linear-gradient(135deg, #1e3c72, #2a5298); color: white; padding: 15px; border-radius: 15px; text-align: center; }
        .stat-number { font-size: 28px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #e2e8f0; padding: 10px; text-align: left; vertical-align: top; }
        th { background: #1e3c72; color: white; }
        .edit-btn { background: #2a5298; color: white; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer; margin-right: 5px; }
        .delete-btn { background: #e53e3e; color: white; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer; }
        .success { background: #c6f6d5; color: #22543d; padding: 10px; border-radius: 10px; margin-bottom: 20px; }
        .error { background: #fed7d7; color: #742a2a; padding: 10px; border-radius: 10px; margin-bottom: 20px; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 1000; }
        .modal-content { background: white; padding: 25px; border-radius: 20px; width: 500px; max-width: 90%; max-height: 80%; overflow-y: auto; }
        .modal-content input, .modal-content select, .modal-content textarea { width: 100%; padding: 8px; margin: 8px 0; border: 1px solid #ccc; border-radius: 8px; }
        .languages-list { display: grid; grid-template-columns: repeat(2, 1fr); gap: 5px; margin: 10px 0; }
        .close { float: right; cursor: pointer; font-size: 24px; }
    </style>
</head>
<body>
<div class="container">
    <div class="top-bar">
        <div>
            <a href="/project/index.php" style="background:#2a5298; color:white; padding:8px 16px; border-radius:8px; text-decoration:none;">🏠 На сайт</a>
            <a href="/project/login.php">🔐 Вход в проект</a>
            <a href="/project/edit.php">✏️ Мой профиль</a>
        </div>
        <a href="?logout=1" class="logout-btn">🚪 Выйти из админки</a>
    </div>

    <h1>👑 Админ-панель проекта</h1>
    <?= $msg ?>

    <h2>📊 Статистика по языкам программирования</h2>
    <div class="stats-grid">
        <?php foreach ($langStats as $stat): ?>
            <div class="stat-card">
                <div class="stat-number"><?= $stat['cnt'] ?></div>
                <div class="stat-name"><?= htmlspecialchars($stat['name']) ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <h2>👥 Все пользователи</h2>
    <table>
        <thead>
            <tr><th>ID</th><th>Логин</th><th>Дата регистрации</th><th>ФИО</th><th>Телефон</th><th>Email</th><th>Дата рожд.</th><th>Пол</th><th>Языки</th><th>Биография</th><th>Действия</th></tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= $user['id'] ?></td>
                    <td><?= htmlspecialchars($user['username']) ?></td>
                    <td><?= $user['created_at'] ?></td>
                    <td><?= htmlspecialchars($user['name'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($user['phone'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($user['email'] ?? '—') ?></td>
                    <td><?= $user['dob'] ?? '—' ?></td>
                    <td><?= ($user['gender'] == 'male') ? 'Мужской' : 'Женский' ?></td>
                    <td><?= htmlspecialchars(substr($user['languages'] ?? '', 0, 50)) ?>...</td>
                    <td><?= htmlspecialchars(substr($user['bio'] ?? '', 0, 50)) ?>...</td>
                    <td>
                        <?php if ($user['app_id']): ?>
                            <button class="edit-btn" onclick="openEditModal(<?= htmlspecialchars(json_encode($user)) ?>)">✏️ Ред.</button>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Удалить пользователя?')">
                                <input type="hidden" name="delete_user_id" value="<?= $user['id'] ?>">
                                <button type="submit" class="delete-btn">🗑️ Удалить</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Модальное окно редактирования -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <h3>✏️ Редактирование анкеты</h3>
        <form method="POST">
            <input type="hidden" name="edit_user_id" id="edit_user_id">
            <label>ФИО:</label><input type="text" name="name" id="edit_name">
            <label>Телефон:</label><input type="text" name="phone" id="edit_phone">
            <label>Email:</label><input type="email" name="email" id="edit_email">
            <label>Дата рождения:</label><input type="date" name="dob" id="edit_dob">
            <label>Пол:</label>
            <select name="gender" id="edit_gender">
                <option value="male">Мужской</option>
                <option value="female">Женский</option>
            </select>
            <label>Любимые языки:</label>
            <div class="languages-list" id="edit_languages">
                <?php foreach ($allLanguages as $lang): ?>
                    <label><input type="checkbox" name="languages[]" value="<?= htmlspecialchars($lang) ?>"> <?= htmlspecialchars($lang) ?></label>
                <?php endforeach; ?>
            </div>
            <label>Биография:</label><textarea name="bio" id="edit_bio" rows="4"></textarea>
            <button type="submit" style="background:#2a5298; color:white; padding:10px; border:none; border-radius:8px;">💾 Сохранить</button>
        </form>
    </div>
</div>

<script>
function openEditModal(user) {
    document.getElementById('edit_user_id').value = user.id;
    document.getElementById('edit_name').value = user.name || '';
    document.getElementById('edit_phone').value = user.phone || '';
    document.getElementById('edit_email').value = user.email || '';
    document.getElementById('edit_dob').value = user.dob || '';
    document.getElementById('edit_gender').value = user.gender || 'male';
    document.getElementById('edit_bio').value = user.bio || '';
    let langs = user.languages ? user.languages.split(', ').map(l => l.trim()) : [];
    document.querySelectorAll('#edit_languages input').forEach(cb => {
        cb.checked = langs.includes(cb.value);
    });
    document.getElementById('editModal').style.display = 'flex';
}
function closeModal() { document.getElementById('editModal').style.display = 'none'; }
window.onclick = function(e) { if (e.target == document.getElementById('editModal')) closeModal(); }
</script>
</body>
</html>
