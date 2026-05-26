<?php
session_start();
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Получаем данные (из JSON или из POST)
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    $name = $input['name'] ?? '';
    $phone = $input['phone'] ?? '';
    $email = $input['email'] ?? '';
    $dob = $input['dob'] ?? '';
    $gender = $input['gender'] ?? '';
    $languages = $input['languages'] ?? [];
    $bio = $input['bio'] ?? '';
    $contract = isset($input['contract']) ? 1 : 0;

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO application (name, phone, email, dob, gender, bio) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $phone, $email, $dob, $gender, $bio]);
        $appId = $pdo->lastInsertId();

        $stmt = $pdo->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (?, (SELECT id FROM languages WHERE name = ?))");
        foreach ($languages as $lang) {
            $stmt->execute([$appId, $lang]);
        }

        $username = 'user_' . bin2hex(random_bytes(4));
        $password = bin2hex(random_bytes(4));

        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, application_id) VALUES (?, ?, ?)");
        $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $appId]);

        $pdo->commit();

        // Возвращаем JSON для fetch
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'login' => $username,
            'password' => $password
        ]);
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Если GET-запрос — показываем страницу с формой (не должно сюда попадать, но на всякий случай)
?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Регистрация</title></head>
<body>
<form method="POST">
    <input name="name" placeholder="ФИО" required><br>
    <input name="phone" placeholder="Телефон" required><br>
    <input name="email" placeholder="Email" required><br>
    <input type="date" name="dob" required><br>
    <label><input type="radio" name="gender" value="male"> Муж</label>
    <label><input type="radio" name="gender" value="female"> Жен</label><br>
    <select name="languages[]" multiple><option>PHP</option></select><br>
    <textarea name="bio"></textarea><br>
    <label><input type="checkbox" name="contract"> Согласен</label><br>
    <button type="submit">Сохранить</button>
</form>
</body>
</html>
u82255@web-server:~/www/project$ cat form.php
<?php
session_start();
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Получаем данные (из JSON или из POST)
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    $name = $input['name'] ?? '';
    $phone = $input['phone'] ?? '';
    $email = $input['email'] ?? '';
    $dob = $input['dob'] ?? '';
    $gender = $input['gender'] ?? '';
    $languages = $input['languages'] ?? [];
    $bio = $input['bio'] ?? '';
    $contract = isset($input['contract']) ? 1 : 0;

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO application (name, phone, email, dob, gender, bio) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $phone, $email, $dob, $gender, $bio]);
        $appId = $pdo->lastInsertId();

        $stmt = $pdo->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (?, (SELECT id FROM languages WHERE name = ?))");
        foreach ($languages as $lang) {
            $stmt->execute([$appId, $lang]);
        }

        $username = 'user_' . bin2hex(random_bytes(4));
        $password = bin2hex(random_bytes(4));

        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, application_id) VALUES (?, ?, ?)");
        $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $appId]);

        $pdo->commit();

        // Возвращаем JSON для fetch
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'login' => $username,
            'password' => $password
        ]);
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Если GET-запрос — показываем страницу с формой (не должно сюда попадать, но на всякий случай)
?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Регистрация</title></head>
<body>
<form method="POST">
    <input name="name" placeholder="ФИО" required><br>
    <input name="phone" placeholder="Телефон" required><br>
    <input name="email" placeholder="Email" required><br>
    <input type="date" name="dob" required><br>
    <label><input type="radio" name="gender" value="male"> Муж</label>
    <label><input type="radio" name="gender" value="female"> Жен</label><br>
    <select name="languages[]" multiple><option>PHP</option></select><br>
    <textarea name="bio"></textarea><br>
    <label><input type="checkbox" name="contract"> Согласен</label><br>
    <button type="submit">Сохранить</button>
</form>
</body>
</html>
