<?php

session_start();

header("Content-Type: application/json");

// DATABASE CONNECTION
$host = "localhost";
$port = "5432";
$dbname = "LMS";
$username = "postgres";
$password = "SHA2456101717";

try {

    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";

    $pdo = new PDO($dsn, $username, $password);

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "status" => 500,
        "message" => "Database connection failed",
        "error" => $e->getMessage()
    ]);

    exit;
}

// ONLY GET METHOD
if ($_SERVER['REQUEST_METHOD'] != 'GET') {

    echo json_encode([
        "status" => 405,
        "message" => "Invalid Request Method"
    ]);

    exit;
}

/*
FOR TESTING PURPOSE
COMMENT THESE 2 LINES IN PRODUCTION
*/
$_SESSION['userid'] = "STF001";
$_SESSION['token'] = "abc123";

/*
USE SESSION DATA , in use this below two lines prod
*/
$userid = $_SESSION['userid'] ?? '';
$token  = $_SESSION['token'] ?? '';

$author = $_GET['author'] ?? '';

// CHECK EMPTY PARAMETERS
if (
    empty($author) ||
    empty($userid) ||
    empty($token)
) {

    echo json_encode([
        "status" => 400,
        "message" => "Missing Parameters"
    ]);

    exit;
}

try {

    // VALIDATE TOKEN
    $checkToken = $pdo->prepare("
        SELECT *
        FROM UserTokens
        WHERE userid = ?
        AND token = ?
        AND status = TRUE
    ");

    $checkToken->execute([$userid, $token]);

    if ($checkToken->rowCount() == 0) {

        echo json_encode([
            "status" => 401,
            "message" => "Invalid Userid or Token"
        ]);

        exit;
    }

    // SEARCH BOOKS
    $searchBooks = $pdo->prepare("
        SELECT
            bookcode,
            booktitle,
            author,
            publishedyear,
            status
        FROM Books
        WHERE author ILIKE ?
        AND isactive = TRUE
    ");

    $searchBooks->execute(["%$author%"]);

    $books = $searchBooks->fetchAll(PDO::FETCH_ASSOC);

    // RESPONSE
    if (count($books) > 0) {

        echo json_encode([
            "status" => 200,
            "message" => "Books Found",
            "data" => $books
        ]);

    } else {

        echo json_encode([
            "status" => 202,
            "message" => "No Author Found"
        ]);
    }

} catch (PDOException $e) {

    echo json_encode([
        "status" => 500,
        "message" => "Database Error",
        "error" => $e->getMessage()
    ]);
}
?>