<?php
// Since in database email is unique, checks if the email already exists
// Registers a new user inserting received JSON data in database
// Returns the user just created
function postUser() {
    require 'pdo.php';
    $data = json_decode(file_get_contents('php://input'), true);
    if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
        return error('Nome, email e password sono necessari');
    }
    // Checks existing email
    $email = $data['email'];
    $existing = $pdo->query("SELECT email FROM users WHERE email = '$email'")->fetch();
    if ($existing) {
        return error('Email già esistente', 409);
    }
    // Inserts user data in database
    $stmt = $pdo->prepare('INSERT INTO users (full_name, email, password, token) VALUES (?, ?, ?, ?)');
    $name = $data['name'];
    $password = password_hash($data['password'], PASSWORD_DEFAULT);
    $token = base64_encode(random_bytes(32));
    $stmt->execute([$name, $email, $password, $token]);
    // Sends confirmation email
    $id = $pdo->lastInsertId();
    sendEmail($id, $name, $email, $token);
    // Returns user just created
    $user = $pdo->query("SELECT id, full_name AS name, email, role FROM users WHERE id = $id")->fetch();
    return [$user, 201];
}

// Resends the confirmation email to the user with the given email
function resendConfirmation($email) {
    require 'pdo.php';
    $stmt = $pdo->prepare("SELECT id, full_name AS name, role, token FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user) {
        if ($user['role'] == 'invalid') {
            // Recreates the token in the strange case it is missing
            if (is_null($user['token'])) {
                $token = base64_encode(random_bytes(32));
                $stmt = $pdo->prepare("UPDATE users SET token = ? WHERE email = ?");
                $stmt->execute([$token, $email]);
            } else {
                $token = $user['token'];
            }
            // Sends the email
            if (sendEmail($user['id'], $user['name'], $email, $token)) {
                return success('Email di conferma inviata');
            } else {
                return error('Email non inviata', 500);
            }
        } else {
            return error('Email già confermata', 409);
        }
    } else {
        return error('Utente non trovato', 404);
    }
}

// Returns true on success
function sendEmail($id, $name, $email, $token) {
    $token = urlencode($token);
    $subject = 'Benvenuto nella Mappa delle piante';
    $link = "https://michelesalvador.it/piante/confirm?id=$id&token=$token";
    $message = "<p>Ciao $name,<br>benvenuto nella Mappa delle piante.</p>
        <p>Per completare la registrazione clicca questo link:</p>
        <p><strong><a href=\"$link\">$link</a></strong></p>
        <p>Se non hai richiesto questa email, puoi ignorarla.</p>
        <p>Grazie,<br>Il team di Mappa delle piante</p>";
    return mail($email, $subject, $message, "From: fame@libero.it\r\nContent-Type: text/html; charset=UTF-8");
}

// Confirms the user by setting the role to editor and the token to NULL in database
// Returns the user just updated
function confirmUser($id) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE users SET role = 'editor', token = NULL WHERE id = ?");
    $stmt->execute([$id]);
    if ($stmt->rowCount() == 1) {
        return $pdo->query("SELECT id, full_name AS name, email, role FROM users WHERE id = $id")->fetch();
    } else {
        return error('Utente non trovato', 404);
    }
}

// Updates one user with the data received in JSON format
// Returns the user just updated
function putUser($id) {
    global $pdo;
    $targetUser = $pdo->query("SELECT role FROM users WHERE id = $id")->fetch();
    if (!$targetUser) {
        return error('Utente non trovato', 404);
    }
    $updates = array(
        'full_name' => 'name',
        'email' => 'email',
        'role' => 'role',
        'password' => 'password'
    );
    // Data into $updates
    $data = json_decode(file_get_contents('php://input'), true);
    foreach ($updates as $key => $value) {
        if (isset($data[$value])) { // Actual values only
            // Checks existing email
            if ($key == 'email') {
                $email = $data[$value];
                $existing = $pdo->query("SELECT email FROM users WHERE email = '$email' AND id != $id")->fetch();
                if ($existing) {
                    return error('Email già esistente', 409);
                }
            }
            // Only admin can change the role
            else if ($key == 'role') {
                if ($targetUser['role'] != $data[$value]) {
                    $token = getToken();
                    $currentUser = $pdo->query("SELECT role FROM users WHERE token = '$token'")->fetch();
                    if ($currentUser['role'] != 'admin') {
                        return error('Solo un amministratore può modificare il ruolo', 401);
                    }
                }
            }
            if ($key == 'password') {
                $updates[$key] = password_hash($data[$value], PASSWORD_DEFAULT);
            } else {
                $updates[$key] = $data[$value];
            }
        } else {
            unset($updates[$key]);
        }
    }
    if ($updates) {
        $stmt = $pdo->prepare("UPDATE users SET " . implode(', ', array_map(function($key) {
            return "$key = ?";
        }, array_keys($updates))) . " WHERE id = ?");
        $updates['id'] = $id;
        $stmt->execute(array_values($updates));
        if ($stmt->rowCount() == 1) {
            return getUser($id);
        } else {
            return success('Nessun utente è stato modificato');
        }
    } else {
        return error('Nessun dato da aggiornare');
    }
}

// Sends an email to the user with the given email address to reset the password
function sendResetEmail($email) {
    require 'pdo.php';
    $stmt = $pdo->prepare("SELECT id, full_name AS name, token FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user) {
        $id = $user['id'];
        if (is_null($user['token'])) {
            $token = base64_encode(random_bytes(32));
            $stmt = $pdo->prepare("UPDATE users SET token = ? WHERE id = ?");
            $stmt->execute([$token, $id]);
        } else {
            $token = $user['token'];
        }
        // Sends the email
        $subject = 'Reimposta la password di Mappa delle piante';
        $token = urlencode($token);
        $link = "https://michelesalvador.it/piante/reset?id=$id&token=$token";
        $message = "<p>Ciao {$user['name']},<br>per reimpostare la tua password clicca questo link:</p>
            <p><strong><a href=\"$link\">$link</a></strong></p>
            <p>Se non hai richiesto questa email, puoi ignorarla.</p>
            <p>Grazie,<br>Il team di Mappa delle piante</p>";
        if (mail($email, $subject, $message, "From: fame@libero.it\r\nContent-Type: text/html; charset=UTF-8")) {
            return success('Email di reset password inviata');
        } else {
            return error('Email non inviata', 500);
        }
    } else {
        return error('Utente non trovato', 404);
    }
}

function getUsers() {
    global $pdo;
    return $pdo->query("SELECT id, full_name AS name, email, role FROM users")->fetchAll();
}

function getUser($id) {
    global $pdo;
    $user = $pdo->query("SELECT id, full_name AS name, email, role, token FROM users WHERE id = $id")->fetch();
    if ($user) {
        return $user;
    } else {
        return error('Utente non trovato', 404);
    }
}

function deleteUser($id) {
    global $pdo;
    $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
    $stmt->execute([$id]);
    if ($stmt->rowCount() == 1) {
        return success('Utente eliminato');
    } else {
        return error('Utente non trovato', 404);
    }
}
?>