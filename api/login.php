<?php
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];


/** @var mysqli $mysqli */

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit();
}

$input = json_decode(file_get_contents("php://input"), true);

if (empty($input["username"])) {
    http_response_code(400);
    echo json_encode(["error" => "Username is required"]);
    exit();
}

$username = $input["username"];

// Check if user exists
$stmt = $mysqli->prepare("SELECT * FROM players WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // User exists - generate new token
    $player = $result->fetch_assoc();
    $token = bin2hex(random_bytes(32));

    $stmt = $mysqli->prepare("UPDATE players SET token = ? WHERE id = ?");
    $stmt->bind_param("si", $token, $player["id"]);
    $stmt->execute();

    echo json_encode([
        "message" => "Login successful",
        "player_id" => $player["id"],
        "username" => $username,
        "token" => $token
    ]);
} else {
    // New user - create account
    $token = bin2hex(random_bytes(32));

    $stmt = $mysqli->prepare("INSERT INTO players (username, token) VALUES (?, ?)");
    $stmt->bind_param("ss", $username, $token);
    $stmt->execute();

    echo json_encode([
        "message" => "Player created",
        "player_id" => $stmt->insert_id,
        "username" => $username,
        "token" => $token
    ]);
}

