<?php
// Инициализация сессии
session_start();

// Подключение к базе данных
require 'db.php';

$msg = ''; // Сообщение об ошибке (если будет)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Функция для извлечения JSON-данных из тела запроса
    function getJsonInput(): ?array {
        $ct = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($ct, 'application/json') === 0) {
            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true);
            return is_array($data) ? $data : null;
        }
        return null;
    }

    $json = getJsonInput(); // Попытка получить данные из JSON
    $isAJAX = $json !== null; // Флаг, был ли запрос AJAX
    $input = $json ?? $_POST; // Использовать JSON или POST-данные

    // Поиск пользователя по логину
    $stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE username = :user");
    $stmt->execute([':user' => $input['username']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Проверка пароля и установка сессии
    if ($user && password_verify($input['password'], $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        setcookie("username", $input['username'], time() + 3600, '/'); // Установка cookie на 1 час
        header('Location: /project/edit.php');
        exit;
    } else {
        $msg = 'Неверный логин или пароль.';
        // Ответ в формате JSON при AJAX-запросе
        if ($isAJAX) {
            echo json_encode([
                'success' => false,
                'error' => $msg
            ]);
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Доступ к редактированию</title>
    <link rel='stylesheet' href='profileloginstyle.css'>
    <script defer src="/project/login.js"></script>
</head>
<body>
  <div class="page-container">
    <form class="login-form" action="login.php" method="post">
      <h1 class="form-title">Вход для редактирования</h1>
      <?php if ($msg): ?>
        <div class="error-message"><?= htmlspecialchars($msg) ?></div>
      <?php endif; ?>
      <div class="form-group">
        <label for="username" class="form-label">Логин:</label>
        <input type="text" id="username" name="username" required class="form-input">
      </div>
      <div class="form-group">
        <label for="password" class="form-label">Пароль:</label>
        <input type="password" id="password" name="password" required class="form-input">
      </div>
      <div class="form-actions">
        <input type="submit" value="Войти" class="buttons submit-button">
      </div>
    </form>

    <form class="register-form" action="/project/index.php" method="get">
      <p class="register-prompt">Еще не зарегистрированы?</p>
      <div class="form-actions">
        <input type="submit" value="Зарегистрироваться" class="buttons register-button">
      </div>
    </form>
<div style="text-align: center; margin-top: 15px;">
    <a href="/project/index.php" style="color: #e0effd; text-decoration: none;">← Вернуться на сайт</a>
</div>

  </div>
</body>

</html>
