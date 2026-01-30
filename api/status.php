<?php
require_once 'config.php';
require_once '../includes/auth.php';

/** @var mysqli $mysqli */

$method = $_SERVER['REQUEST_METHOD'];
$player = authenticate($mysqli);

if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit();
}

// Check if game_id is provided
if (!isset($_GET['game_id'])) {
    http_response_code(400);
    echo json_encode(["error" => "game_id is required"]);
    exit();
}

$game_id = intval($_GET['game_id']);

// Get game info
$stmt = $mysqli->prepare("SELECT g.*, 
                                 p1.username as player1_name,
                                 p2.username as player2_name
                          FROM games g
                          JOIN players p1 ON g.player1_id = p1.id
                          LEFT JOIN players p2 ON g.player2_id = p2.id
                          WHERE g.id = ?");
$stmt->bind_param("i", $game_id);
$stmt->execute();
$game = $stmt->get_result()->fetch_assoc();

if (!$game) {
    http_response_code(404);
    echo json_encode(["error" => "Game not found"]);
    exit();
}

// Check if player is in this game
if ($game['player1_id'] != $player['id'] && $game['player2_id'] != $player['id']) {
    http_response_code(403);
    echo json_encode(["error" => "You are not in this game"]);
    exit();
}

// Get player's hand and score
$stmt = $mysqli->prepare("SELECT * FROM game_players WHERE game_id = ? AND player_id = ?");
$stmt->bind_param("ii", $game_id, $player['id']);
$stmt->execute();
$playerData = $stmt->get_result()->fetch_assoc();

// Get opponent's card count (not their actual cards)
$opponent_id = ($game['player1_id'] == $player['id']) ? $game['player2_id'] : $game['player1_id'];
$opponent_cards = 0;

if ($opponent_id) {
    $stmt = $mysqli->prepare("SELECT hand FROM game_players WHERE game_id = ? AND player_id = ?");
    $stmt->bind_param("ii", $game_id, $opponent_id);
    $stmt->execute();
    $opponentData = $stmt->get_result()->fetch_assoc();
    if ($opponentData) {
        $opponent_cards = count(json_decode($opponentData['hand'], true));
    }
}

// Determine if it's your turn
$is_your_turn = ($game['current_turn'] == $player['id']);

// Build response
$response = [
    "game_id" => $game_id,
    "status" => $game['status'],
    "your_hand" => json_decode($playerData['hand'], true),
    "table_cards" => json_decode($game['table_cards'], true),
    "cards_in_deck" => count(json_decode($game['deck'], true)),
    "opponent_cards" => $opponent_cards,
    "your_score" => $playerData['score'],
    "your_xeres" => $playerData['xeres'],
    "your_captured" => count(json_decode($playerData['captured'], true)),
    "is_your_turn" => $is_your_turn,
    "players" => [
        "player1" => $game['player1_name'],
        "player2" => $game['player2_name'] ?? "Waiting..."
    ]
];

echo json_encode($response);
