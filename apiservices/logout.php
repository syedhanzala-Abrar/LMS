<?php
session_start();

// Get input (support both JSON + form-data)
$data = json_decode(file_get_contents("php://input"), true);

// Check JSON first, then form-data (works for both raw JSON and form-data in Postman)
$token = $data["token"] ?? ($_POST["token"] ?? '');

if ($token == "") {
    http_response_code(400);
    echo json_encode(["status"=>"failure","message"=>"Missing token"]);
    exit;
}


$host = "localhost";
$port = "5432";
$dbname = "LMS";
$user = "postgres";
$password_db = "SHA2456101717";

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $pdo = new PDO($dsn, $user, $password_db);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $update = $pdo->prepare("UPDATE usertokens 
                             SET loggedoutdate = CURRENT_TIMESTAMP, status = FALSE 
                             WHERE token = :token AND status = TRUE");
    $update->execute(['token' => $token]);

    // Clear PHP session
    session_unset();
    session_destroy();

    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "message" => "Logout successful"
    ]);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "failure",
        "message" => $e->getMessage()
    ]);
    exit;
}


