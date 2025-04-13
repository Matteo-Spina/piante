<?php
// Gets all plants from the database
function getPlants() {
    try {
        require 'pdo.php';
        $plants = $pdo->query('SELECT id, number, ST_Y(location) AS latitude, ST_X(location) AS longitude, height, circumferences, height,
        common_name AS `common-name`, scientific_name AS `scientific-name`, insert_date AS date, user_id AS user
        FROM plants')->fetchAll();
        foreach ($plants as &$plant) {
            if (!is_null($plant['circumferences'])) {
                $plant['circumferences'] = json_decode($plant['circumferences']);
            } else {
                $plant['circumferences'] = array();
            }
        }
        // Adds user data to plants array
        $userIds = array();
        foreach ($plants as &$plant) {
            if (!in_array($plant['user'], $userIds)) $userIds[] = $plant['user'];
        }
        $users = $pdo->query('SELECT id, id, full_name AS name, email
        FROM users WHERE id IN ('. join(',', $userIds). ')')->fetchAll(PDO::FETCH_UNIQUE);
        foreach ($plants as &$plant) {
            if (isset($users[$plant['user']])) {
                $plant['user'] = $users[$plant['user']];
            }
        }
        // Add images data to plants array
        $plantIds = array();
        foreach ($plants as &$plant) {
            $plantIds[] = $plant['id'];
        }
        $images = $pdo->query('SELECT plant_id, id, CONCAT("' . UPLOADS_PATH .'", file_name) AS `file-path`
        FROM images WHERE plant_id IN ('. join(',', $plantIds). ')')->fetchAll(PDO::FETCH_GROUP);
        foreach ($plants as &$plant) {
            if (isset($images[$plant['id']])) {
                $plant['images'] = $images[$plant['id']];
            } else {
                $plant['images'] = array(); // Empty array
            }
        }
        return $plants;
    } catch (Exception $exception) {
        return error($exception->getMessage());
    }
}

// Receives a plant ID and searches for the plant in database
// With $complete false returns no user nor images
function getPlant($id, $complete = true) {
    require 'pdo.php';
    $stmt = $pdo->prepare('SELECT id, number, ST_Y(location) AS latitude, ST_X(location) AS longitude, circumferences, height,
    common_name AS `common-name`, scientific_name AS `scientific-name`, insert_date AS date, user_id AS user FROM plants WHERE id = ?');
    $stmt->execute([$id]);
    $plant = $stmt->fetch();
    if ($plant) {
        if (!is_null($plant['circumferences'])) {
            $plant['circumferences'] = json_decode($plant['circumferences']);
        } else {
            $plant['circumferences'] = array();
        }
        if ($complete) {
            $user = $pdo->query('SELECT id, full_name AS name, email FROM users WHERE id = '. $plant['user'])->fetch();
            if ($user) {
                $plant['user'] = $user;
            }
            $files = $pdo->query('SELECT id, file_name, plant_id FROM images WHERE plant_id = '. $plant['id'])->fetchAll();
            $plant['images'] = array();
            foreach ($files as $file) {
                $image = array();
                $image['id'] = $file['id'];
                $image['file-path'] = UPLOADS_PATH . $file['file_name'];
                $plant['images'][] = $image;
            }
        } else {
            unset($plant['user']);
        }
        return $plant;
    } else {
        return error('Pianta non trovata', 404);
    }
}

// Inserts a new plant in database
function postPlant() {
    require 'pdo.php';
    $error = require 'auth.php';
    if ($error) return $error;
    $stmt = $pdo->prepare("INSERT INTO plants (number, location, circumferences, height, common_name, scientific_name, user_id)
    VALUES (:num, POINT(:lon, :lat), :circ, :height, :common, :scientific, :user)");
    $stmt->bindParam(':num', $data['number']);
    $stmt->bindParam(':lon', $data['longitude']);
    $stmt->bindParam(':lat', $data['latitude']);
    if (isset($data['circumferences']) && !is_null($data['circumferences'])) {
        $circ = json_encode($data['circumferences']);
    }
    $stmt->bindParam(':circ', $circ);
    $stmt->bindParam(':height', $data['height']);
    $stmt->bindParam(':common', $data['common-name']);
    $stmt->bindParam(':scientific', $data['scientific-name']);
    $stmt->bindParam(':user', $data['user-id']);
    $stmt->execute();
    // Returns new plant
    $plant = getPlant($pdo->lastInsertId(), false);
    return [$plant, 201];
}

// Receives a JSON with plant ID and other data and updates the plant in database
// Doesn't update date, user and images
function putPlant($id) {
    require 'pdo.php';
    $error = require 'auth.php';
    if ($error) return $error;
    $stmt = $pdo->prepare('UPDATE plants SET number = :number, location = POINT(:longitude, :latitude),
    circumferences = :circumferences, height = :height, common_name = :common_name, scientific_name = :scientific_name
    WHERE id = :id');
    $stmt->bindParam(':number', $data['number']);
    $stmt->bindParam(':longitude', $data['longitude']);
    $stmt->bindParam(':latitude', $data['latitude']);
    if (isset($data['circumferences']) && !is_null($data['circumferences'])) {
        $circ = json_encode($data['circumferences']);
    }
    $stmt->bindParam(':circumferences', $circ);
    $stmt->bindParam(':height', $data['height']);
    $stmt->bindParam(':common_name', $data['common-name']);
    $stmt->bindParam(':scientific_name', $data['scientific-name']);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    if ($stmt->rowCount() == 1) {
        return getPlant($id, false);
    } else {
        return success('Nessuna pianta è stata modificata');
    }
}

// Deletes one plant
function deletePlant($id) {
    require 'pdo.php';
    $error = require 'auth.php';
    if ($error) return $error;
    $stmt = $pdo->prepare('DELETE FROM plants WHERE id = ?');
    $stmt->execute([$id]);
    if ($stmt->rowCount() == 1) {
        // TODO Eliminare anche immagini?
        //$stmt = $pdo->prepare("DELETE FROM images WHERE plant_id = $id");
        return success('Pianta eliminata');
    } else {
        return error('Pianta non trovata', 404);
    }
}
?>