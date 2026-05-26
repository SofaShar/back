<?php
$user = 'user1'; 
$password = '123'; 
try {
    $pdo = new PDO('mysql:host=localhost;dbname=u68857', $user, $password,
        [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    die("<p style='color:red;'>Ошибка подключения к базе данных: " . $e->getMessage() . "</p>");
}
?>
