<?php
// For now, using hard coded values
// require_once '../config.php';

// Values should match either your development environment or the production
// environment

// Database connection parameters using a Unix socket and socket authentication
$dsn = 'mysql:unix_socket=/run/mysqld/mysqld.sock;dbname=piante;charset=utf8mb4';
$username = 'matteo'; // Should match your system user
$password = ''; // No password needed with socket-based authentication

// $pdo = new PDO(DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASSWORD);
$pdo = new PDO($dsn, $username, $password);

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
