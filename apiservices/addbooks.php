<?php
// session_start(); // Uncomment in production

header("Content-Type: application/json");

// ONLY POST METHOD ALLOWED
if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Only POST method is allowed."
    ]);

    exit;
}

// FOR TESTING PURPOSE
// In production use session variables
$session_userid = $_POST["userid"] ?? "";
$session_token  = $_POST["token"] ?? "";

// PRODUCTION VERSION
// $session_userid = $_SESSION["userid"] ?? "";
// $session_token  = $_SESSION["token"] ?? "";

if (!$session_userid || !$session_token) {

    http_response_code(401);

    echo json_encode([
        "success" => false,
        "message" => "Unauthorized access."
    ]);

    exit;
}

// DATABASE CONFIG
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
        "success" => false,
        "message" => "Database connection failed.",
        "error" => $e->getMessage()
    ]);

    exit;
}

/* TOKEN CHECK */
$tokenCheck = $pdo->prepare("
    SELECT *
    FROM usertokens
    WHERE userid = :userid
    AND token = :token
");

$tokenCheck->execute([
    ":userid" => $session_userid,
    ":token"  => $session_token
]);

if ($tokenCheck->rowCount() == 0) {

    http_response_code(401);

    echo json_encode([
        "success" => false,
        "message" => "Invalid userid or token."
    ]);

    exit;
}

/* TOKEN STATUS CHECK */
$statusCheck = $pdo->prepare("
    SELECT *
    FROM usertokens
    WHERE userid = :userid
    AND token = :token
    AND status = true
");

$statusCheck->execute([
    ":userid" => $session_userid,
    ":token"  => $session_token
]);

if ($statusCheck->rowCount() == 0) {

    http_response_code(401);

    echo json_encode([
        "success" => false,
        "message" => "Token expired."
    ]);

    exit;
}

// INPUT PARAMETERS
$categorycode  = trim($_POST["categorycode"] ?? "");
$title         = trim($_POST["title"] ?? "");
$author        = trim($_POST["author"] ?? "");
$publishedyear = trim($_POST["publishedyear"] ?? "");
$description   = trim($_POST["description"] ?? "");

// REQUIRED FIELD VALIDATION
if (
    empty($categorycode) ||
    empty($bookid) ||
    empty($title) ||
    empty($author) ||
    empty($publishedyear)
) {
    http_response_code(422);

    echo json_encode([
        "success" => false,
        "message" => "Required fields are missing."
    ]);

    exit;
}


try {

    $sql = "INSERT INTO books (
            categorycode,
            bookid,
            booktitle,
            author,
            publishyear,
            description,
            introducedby,
            status,
            isactive
        )
        VALUES (
            :categorycode,
            :bookid,
            :booktitle,
            :author,
            :publishyear,
            :description,
            :introducedby,
            'available',
            true
        )";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
    ":categorycode" => $categorycode,
    ":bookid" => $bookid,
    ":booktitle" => $title,
    ":author" => $author,
    ":publishyear" => $publishedyear,
    ":description" => $description,
    ":introducedby" => $session_userid
]);

    http_response_code(201);

    echo json_encode([
        "successcode" => "200",
        "success" => true,
        "message" => "Book added successfully."
    ]);

} catch (PDOException $e) {

    if ($e->getCode() == "23505") {

        http_response_code(409);

        echo json_encode([
            "success" => false,
            "message" => "Book already exists."
        ]);

    } else {

        http_response_code(500);

        echo json_encode([
            "success" => false,
            "message" => "Failed to add book.",
            "error" => $e->getMessage()
        ]);
    }
}
?>