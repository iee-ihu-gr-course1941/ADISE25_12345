<?php
function authenticate($mysqli) {
    $headers = getallheaders();

    if (!isset($headers["Authorization"])) {
        http_response_code(401);
        echo json_encode(["error" => "Missing token"]);
        exit();
    }

    $token = str_replace("Bearer ", "", $headers["Authorization"]);

    $stmt = $mysqli->prepare("SELECT * FROM players WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        http_response_code(401);
        echo json_encode(["error" => "Invalid token"]);
        exit();
    }

    return $result->fetch_assoc();
}
