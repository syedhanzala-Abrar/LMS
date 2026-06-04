<?php

header('Content-Type: application/json');

// 1. Allow only POST
if ($_SERVER['REQUEST_METHOD'] !== "POST") {
    http_response_code(400);
    echo json_encode([
        "status" => "failure",
        "message" => "Invalid request method"
    ]);
    exit;
}

// 2. Get input
$deptcode = $_POST["deptcode"];
$deptname = $_POST["deptname"];
$description = $_POST["description"];
$user_id = $_POST["userid"];
$token = $_POST["token"];

// 3. Validate input
if ($deptcode == "" || $deptname == "" || $description == "" || $user_id == "" || $token == "") {
    http_response_code(400);
    echo json_encode([
        "status" => "failure",
        "message" => "Missing parameters"
    ]);
    exit;
}

// 4. Auto set introduceddate (today)
$introducedate = date("Y-m-d");

// 5. DB config
$host = "localhost";
$port = "5432";
$dbname = "LMS";
$user = "postgres";
$password_db = "SHA2456101717";

try {
    // 6. Connect DB
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $pdo = new PDO($dsn, $user, $password_db);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 7. Authenticate user
    $auth = $pdo->prepare("SELECT * FROM usertokens WHERE userid = :user_id AND token = :token AND status = 'true'");
    $auth->execute([
        'user_id' => $user_id,
        'token' => $token
    ]);

    if (!$auth->fetch()) {
        http_response_code(401);
        echo json_encode([
            "status" => "failure",
            "message" => "Unauthorized: Invalid user or token"
        ]);
        exit;
    }

    // 8. Check duplicate
    $check = $pdo->prepare("SELECT * FROM department WHERE deptcode = :deptcode OR deptname = :deptname");
    $check->execute([
        'deptcode' => $deptcode,
        'deptname' => $deptname
    ]);

    if ($check->fetch()) {
        http_response_code(201);
        echo json_encode([
            "status" => "failure",
            "message" => "newdepartment already exists"
        ]);
        exit;
    }

    // 9. Insert department
    $stmt = $pdo->prepare("INSERT INTO department (deptcode, deptname, description, introducedate) 
                           VALUES (:deptcode, :deptname, :description, :introducedate)");

    $result = $stmt->execute([
        'deptcode' => $deptcode,
        'deptname' => $deptname,
        'description' => $description,
        'introducedate' => $introducedate
    ]);

    // 10. Response
    if ($result) {
        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "message" => "newdepartment created successfully"
        ]);
    } else {
        http_response_code(201);
        echo json_encode([
            "status" => "failure",
            "message" => "newdepartment creation failed"
        ]);
    }

} catch (Exception $e) {
    http_response_code(201);
    echo json_encode([
        "status" => "failure",
        "message" => "new_department creation failed"
    ]);
}