<?php

// This script is for developers who wish to connect to their own MariaDB
// instance to create a new db 'piante'.

// Temp instructions:
// Mariadb instance connection parameters using a Unix socket and socket authentication
$dsn = 'mysql:unix_socket=/run/mysqld/mysqld.sock;';
$username = 'matteo'; // Should match your system user
$password = ''; // No password needed with socket-based authentication

try {
    echo "Trying to connect \n";
    // Connecting to the database, returns a ref to PDO instance
    $pdo = new PDO($dsn, $username, $password);
    // Set error mode to Exception so that errors throw exceptions
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connection successful, \n";

    echo "Creating database 'piante' \n";
    $sql = file_get_contents('database.sql');
    $pdo->exec($sql);
    // echo 'Complimenti!<br>Hai creato il database <code>' . DB_NAME . '</code>.';
    echo "Database 'piante' created \n";
} catch (PDOException $exception) {
    // If the connection fails, output the error message
    echo "Error " . $exception->getMessage();
}

// $pdo = null; // Close the connection (optional)
