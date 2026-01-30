<?php
require_once 'config.php';
require_once '../includes/auth.php';

/** @var mysqli $mysqli */

$method = $_SERVER['REQUEST_METHOD'];
$player = authenticate($mysqli);

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit();
}

$input = json_decode(file_get_contents("php://input"), true);

// Validate input
if (!isset($input['game_id']) || !isset($input['card'])) {
    http_response_code(400);
    echo json_encode(["error" => "game_id and card are required"]);
    exit();
}

$game_id = intval($input['game_id']);
$card = $input['card'];

// Get game
$stmt = $mysqli->prepare("SELECT * FROM games WHERE id = ?");
$stmt->bind_param("i", $game_id);
$stmt->execute();
$game = $stmt->get_result()->fetch_assoc();

if (!$game) {
    http_response_code(404);
    echo json_encode(["error" => "Game not found"]);
    exit();
}

// Check game is active
if ($game['status'] !== 'active') {
    http_response_code(400);
    echo json_encode(["error" => "Game is not active"]);
    exit();
}

// Check if it is player's turn
if ($game['current_turn'] != $player['id']) {
    http_response_code(400);
    echo json_encode(["error" => "It's not your turn"]);
    exit();
}

// Get player's hand
$stmt = $mysqli->prepare("SELECT * FROM game_players WHERE game_id = ? AND player_id = ?");
$stmt->bind_param("ii", $game_id, $player['id']);
$stmt->execute();
$playerData = $stmt->get_result()->fetch_assoc();

$hand = json_decode($playerData['hand'], true);
$captured = json_decode($playerData['captured'], true);

// Check if card is in hand
if (!in_array($card, $hand)) {
    http_response_code(400);
    echo json_encode(["error" => "Card not in your hand"]);
    exit();
}

// Get table cards
$tableCards = json_decode($game['table_cards'], true);

//Get card value (without the symbol)
function getCardValue($card) {
    return substr($card, 0, -1);
}

// Count points from captured cards
function countPoints($cards): int
{
    $points = 0;

    foreach ($cards as $card) {
        $value = getCardValue($card);

        // 2 of Clubs = 1 point
        if ($card === '2C') {
            $points += 1;
        }
        // 10 of Diamonds = 2 points
        if ($card === '10D') {
            $points += 2;
        }
        // K, Q, J = 1 point each
        if (in_array($value, ['K', 'Q', 'J'])) {
            $points += 1;
        }
        // 10 (except 10D) = 1 point
        if ($value === '10' && $card !== '10D') {
            $points += 1;
        }
    }

    return $points;
}

// Calculate final scores
function calculateScores($mysqli, $game_id, $player_id, $opponent_id, $playerCaptured): array
{
    // Get opponent's captured cards
    $stmt = $mysqli->prepare("SELECT captured, xeres, jack_xeres FROM game_players WHERE game_id = ? AND player_id = ?");
    $stmt->bind_param("ii", $game_id, $opponent_id);
    $stmt->execute();
    $oppData = $stmt->get_result()->fetch_assoc();
    $opponentCaptured = json_decode($oppData['captured'], true);
    $opponentXeres = $oppData['xeres'];
    $opponentJackXeres = $oppData['jack_xeres'];

    // Get player's xeres
    $stmt = $mysqli->prepare("SELECT xeres, jack_xeres FROM game_players WHERE game_id = ? AND player_id = ?");
    $stmt->bind_param("ii", $game_id, $player_id);
    $stmt->execute();
    $playerData = $stmt->get_result()->fetch_assoc();
    $playerXeres = $playerData['xeres'];
    $playerJackXeres = $playerData['jack_xeres'];

    // Calculate points for each player
    $playerPoints = countPoints($playerCaptured);
    $playerPoints += ($playerXeres * 10);
    $playerPoints += ($playerJackXeres * 20);

    $opponentPoints = countPoints($opponentCaptured);
    $opponentPoints += ($opponentXeres * 10);
    $opponentPoints += ($opponentJackXeres * 20);

    // Bonus for most cards (3 points)
    if (count($playerCaptured) > count($opponentCaptured)) {
        $playerPoints += 3;
    } elseif (count($opponentCaptured) > count($playerCaptured)) {
        $opponentPoints += 3;
    }

    // Update scores in database
    $stmt = $mysqli->prepare("UPDATE game_players SET score = ? WHERE game_id = ? AND player_id = ?");
    $stmt->bind_param("iii", $playerPoints, $game_id, $player_id);
    $stmt->execute();

    $stmt = $mysqli->prepare("UPDATE game_players SET score = ? WHERE game_id = ? AND player_id = ?");
    $stmt->bind_param("iii", $opponentPoints, $game_id, $opponent_id);
    $stmt->execute();

    return [
        "your_score" => $playerPoints,
        "opponent_score" => $opponentPoints,
        "your_cards" => count($playerCaptured),
        "opponent_cards" => count($opponentCaptured),
        "your_xeres" => $playerXeres,
        "your_jack_xeres" => $playerJackXeres,
        "opponent_xeres" => $opponentXeres,
        "opponent_jack_xeres" => $opponentJackXeres,
        "winner" => $playerPoints > $opponentPoints ? "you" : ($opponentPoints > $playerPoints ? "opponent" : "tie")
    ];
}

// Play the card
$cardValue = getCardValue($card);
$topCard = count($tableCards) > 0 ? $tableCards[count($tableCards) - 1] : null;
$topCardValue = $topCard ? getCardValue($topCard) : null;

$isCapture = false;
$isXeri = false;
$isJackXeri = false;
$capturedCards = [];

// Check for capture: same value OR Jack captures all
if ($topCard && ($cardValue === $topCardValue || $cardValue === 'J')) {
    $isCapture = true;
    $capturedCards = $tableCards;
    $capturedCards[] = $card; // Include played card

    // Check for Xeri (capture single card)
    if (count($tableCards) === 1) {
        $isXeri = true;
        if ($cardValue === 'J') {
            $isJackXeri = true;
        }
    }

    // Clear table
    $tableCards = [];
} else {
    // No capture - add card to table
    $tableCards[] = $card;
}

// Remove card from hand
$hand = array_values(array_diff($hand, [$card]));

// Add captured cards to player's pile
$captured = array_merge($captured, $capturedCards);

// Update xeres count
$xeres = $playerData['xeres'];
$jackXeres = $playerData['jack_xeres'] ?? 0;

if ($isXeri) {
    if ($isJackXeri) {
        $jackXeres++;
    } else {
        $xeres++;
    }
}

// Determine next turn
$opponent_id = ($game['player1_id'] == $player['id']) ? $game['player2_id'] : $game['player1_id'];
$next_turn = $opponent_id;

// Update last_capture_by if capture happened
$last_capture = $game['last_capture_by'];
if ($isCapture) {
    $last_capture = $player['id'];
}

// Check if need to deal new cards (both hands empty)
$deck = json_decode($game['deck'], true);
$needDeal = false;

$stmt = $mysqli->prepare("SELECT hand FROM game_players WHERE game_id = ? AND player_id = ?");
$stmt->bind_param("ii", $game_id, $opponent_id);
$stmt->execute();
$opponentData = $stmt->get_result()->fetch_assoc();
$opponentHand = json_decode($opponentData['hand'], true);

if (count($hand) === 0 && count($opponentHand) === 0 && count($deck) > 0) {
    $needDeal = true;

    // Deal 6 cards to each player
    for ($i = 0; $i < 6 && count($deck) > 0; $i++) {
        $hand[] = array_pop($deck);
    }
    for ($i = 0; $i < 6 && count($deck) > 0; $i++) {
        $opponentHand[] = array_pop($deck);
    }

    // Update opponent's hand
    $stmt = $mysqli->prepare("UPDATE game_players SET hand = ? WHERE game_id = ? AND player_id = ?");
    $opponentHandJson = json_encode($opponentHand);
    $stmt->bind_param("sii", $opponentHandJson, $game_id, $opponent_id);
    $stmt->execute();
}

// Check for game end
$gameEnd = false;
$scores = null;

if (count($hand) === 0 && count($opponentHand) === 0 && count($deck) === 0) {
    $gameEnd = true;

    // Give remaining table cards to last capturer
    if (count($tableCards) > 0 && $last_capture) {
        if ($last_capture == $player['id']) {
            $captured = array_merge($captured, $tableCards);
        } else {
            // Add to opponent's captured
            $stmt = $mysqli->prepare("SELECT captured FROM game_players WHERE game_id = ? AND player_id = ?");
            $stmt->bind_param("ii", $game_id, $last_capture);
            $stmt->execute();
            $lastCapturerData = $stmt->get_result()->fetch_assoc();
            $lastCapturerCards = json_decode($lastCapturerData['captured'], true);
            $lastCapturerCards = array_merge($lastCapturerCards, $tableCards);

            $stmt = $mysqli->prepare("UPDATE game_players SET captured = ? WHERE game_id = ? AND player_id = ?");
            $capturedJson = json_encode($lastCapturerCards);
            $stmt->bind_param("sii", $capturedJson, $game_id, $last_capture);
            $stmt->execute();
        }
        $tableCards = [];
    }

    // Calculate scores
    $scores = calculateScores($mysqli, $game_id, $player['id'], $opponent_id, $captured);
}

// Update player's data
$stmt = $mysqli->prepare("UPDATE game_players SET hand = ?, captured = ?, xeres = ?, jack_xeres = ? WHERE game_id = ? AND player_id = ?");
$handJson = json_encode($hand);
$capturedJson = json_encode($captured);
$stmt->bind_param("ssiiii", $handJson, $capturedJson, $xeres, $jackXeres, $game_id, $player['id']);
$stmt->execute();

// Update game
$status = $gameEnd ? 'finished' : 'active';
$stmt = $mysqli->prepare("UPDATE games SET table_cards = ?, deck = ?, current_turn = ?, last_capture_by = ?, status = ? WHERE id = ?");
$tableJson = json_encode($tableCards);
$deckJson = json_encode($deck);
$stmt->bind_param("ssiisi", $tableJson, $deckJson, $next_turn, $last_capture, $status, $game_id);
$stmt->execute();

// Build response
$response = [
    "message" => $isCapture ? "Capture!" : "Card played",
    "card_played" => $card,
    "captured" => $isCapture,
    "captured_cards" => $capturedCards,
    "xeri" => $isXeri,
    "jack_xeri" => $isJackXeri,
    "your_hand" => $hand,
    "table_cards" => $tableCards,
    "cards_dealt" => $needDeal,
    "game_over" => $gameEnd
];

if ($gameEnd && $scores) {
    $response["message"] = "Game Over!";
    $response["final_scores"] = $scores;
}

echo json_encode($response);
