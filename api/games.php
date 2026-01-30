<?php
require_once 'config.php';
require_once '../includes/auth.php';

/** @var mysqli $mysqli */

$method = $_SERVER['REQUEST_METHOD'];
$player = authenticate($mysqli);

function createDeck() {
    $suits = ['H', 'D', 'C', 'S']; // Hearts, Diamonds, Clubs, Spades
    $values = ['A', '2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K'];
    $deck = [];

    foreach ($suits as $suit) {
        foreach ($values as $value) {
            $deck[] = $value . $suit;
        }
    }

    shuffle($deck);
    return $deck;
}


function dealCards(&$deck, $count): array
{
    $cards = [];
    for ($i = 0; $i < $count; $i++) {
        if (count($deck) > 0) {
            $cards[] = array_pop($deck);
        }
    }
    return $cards;
}

switch ($method) {
    case 'GET':
        // List available games (waiting for player2)
        if (isset($_GET['id'])) {
            // Get specific game
            $stmt = $mysqli->prepare("SELECT * FROM games WHERE id = ?");
            $stmt->bind_param("i", $_GET['id']);
            $stmt->execute();
            $game = $stmt->get_result()->fetch_assoc();

            if (!$game) {
                http_response_code(404);
                echo json_encode(["error" => "Game not found"]);
                exit();
            }

            echo json_encode($game);
        } else {
            // List waiting games
            $result = $mysqli->query("SELECT g.id, g.status, g.created_at, p.username as player1 
                                      FROM games g 
                                      JOIN players p ON g.player1_id = p.id 
                                      WHERE g.status = 'waiting'");
            $games = [];
            while ($row = $result->fetch_assoc()) {
                $games[] = $row;
            }
            echo json_encode($games);
        }
        break;

    case 'POST':
        if (isset($_GET['id'])) {
            // JOIN existing game
            $game_id = intval($_GET['id']);

            // Check if game exists and is waiting
            $stmt = $mysqli->prepare("SELECT * FROM games WHERE id = ? AND status = 'waiting'");
            $stmt->bind_param("i", $game_id);
            $stmt->execute();
            $game = $stmt->get_result()->fetch_assoc();

            if (!$game) {
                http_response_code(400);
                echo json_encode(["error" => "Game not available"]);
                exit();
            }

            // Can't join your own game
            if ($game['player1_id'] == $player['id']) {
                http_response_code(400);
                echo json_encode(["error" => "Cannot join your own game"]);
                exit();
            }

            // Get deck and deal cards to player2
            $deck = json_decode($game['deck'], true);
            $hand2 = dealCards($deck, 6);

            // Update game
            $stmt = $mysqli->prepare("UPDATE games SET player2_id = ?, deck = ?, status = 'active' WHERE id = ?");
            $deckJson = json_encode($deck);
            $stmt->bind_param("isi", $player['id'], $deckJson, $game_id);
            $stmt->execute();

            // Add player2 to game_players
            $stmt = $mysqli->prepare("INSERT INTO game_players (game_id, player_id, hand, captured) VALUES (?, ?, ?, '[]')");
            $handJson = json_encode($hand2);
            $stmt->bind_param("iis", $game_id, $player['id'], $handJson);
            $stmt->execute();

            echo json_encode([
                "message" => "Joined game successfully",
                "game_id" => $game_id,
                "status" => "active",
                "your_hand" => $hand2
            ]);

        } else {
            // CREATE new game
            $deck = createDeck();
            $hand1 = dealCards($deck, 6);
            $tableCards = dealCards($deck, 4);

            // Insert game
            $stmt = $mysqli->prepare("INSERT INTO games (player1_id, deck, table_cards, current_turn, status) VALUES (?, ?, ?, ?, 'waiting')");
            $deckJson = json_encode($deck);
            $tableJson = json_encode($tableCards);
            $stmt->bind_param("issi", $player['id'], $deckJson, $tableJson, $player['id']);
            $stmt->execute();
            $game_id = $stmt->insert_id;

            // Add player1 to game_players
            $stmt = $mysqli->prepare("INSERT INTO game_players (game_id, player_id, hand, captured) VALUES (?, ?, ?, '[]')");
            $handJson = json_encode($hand1);
            $stmt->bind_param("iis", $game_id, $player['id'], $handJson);
            $stmt->execute();

            echo json_encode([
                "message" => "Game created - waiting for opponent",
                "game_id" => $game_id,
                "status" => "waiting",
                "your_hand" => $hand1,
                "table_cards" => $tableCards
            ]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(["error" => "Method not allowed"]);
}
