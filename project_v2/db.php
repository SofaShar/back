<?php
//файл соединения с базой данных mysql
$host   = 'localhost';
$dbname = 'form_db';
$user   = 'user1';
$pass   = '123';
//Подключаемся к базе
$pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);
