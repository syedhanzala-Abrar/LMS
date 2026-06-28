<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

$host = "localhost";
$port = "5432";
$dbname = "LMS";
$user = "postgres";
$password = "SHA2456101717";

try {

    // ==============================
    // DATABASE CONNECTION
    // ==============================

    $conn = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname",
        $user,
        $password
    );

    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ==============================
    // CHECK REQUEST METHOD
    // ==============================

    if ($_SERVER['REQUEST_METHOD'] != 'POST') {

        http_response_code(405);

        echo json_encode([
            "status" => false,
            "message" => "Only POST Method Allowed"
        ]);

        exit;
    }

    // ==============================
    // GET INPUTS
    // ==============================

    $userid      = $_POST['userid'] ?? '';
    $token       = $_POST['token'] ?? '';
    $admissionno = $_POST['admissionno'] ?? '';
    $bookcode    = $_POST['bookcode'] ?? '';

    // ==============================
    // VALIDATE AUTH INPUTS
    // ==============================

    if (empty($userid) || empty($token)) {

        http_response_code(401);

        echo json_encode([
            "status" => false,
            "message" => "userid and token are required"
        ]);

        exit;
    }

    // ==============================
    // VERIFY TOKEN
    // ==============================

    $tokenSql = "
        SELECT *
        FROM usertokens
        WHERE userid = :userid
        AND token = :token
    ";

    $tokenStmt = $conn->prepare($tokenSql);

    $tokenStmt->bindParam(':userid', $userid);
    $tokenStmt->bindParam(':token', $token);

    $tokenStmt->execute();

    $tokenData = $tokenStmt->fetch(PDO::FETCH_ASSOC);

    // TOKEN NOT FOUND

    if (!$tokenData) {

        http_response_code(401);

        echo json_encode([
            "status" => false,
            "message" => "Invalid Token"
        ]);

        exit;
    }

    // ==============================
    // CHECK TOKEN STATUS
    // ==============================

    if ($tokenData['status'] != true) {

        http_response_code(401);

        echo json_encode([
            "status" => false,
            "message" => "Token Expired Please Login Again"
        ]);

        exit;
    }

    // ==============================
    // VALIDATE BOOK/ADMISSION INPUTS
    // ==============================

    if (empty($admissionno)) {

        http_response_code(400);

        echo json_encode([
            "status" => false,
            "message" => "admission Number Required"
        ]);

        exit;
    }

    if (empty($bookcode)) {

        http_response_code(400);

        echo json_encode([
            "status" => false,
            "message" => "Book Code Required"
        ]);

        exit;
    }

    // ==============================
    // CHECK ADMISSION NUMBER
    // ==============================

    $admissionSql = "
        SELECT *
        FROM admissions
        WHERE admissionno = :admissionno
    ";

    $admissionStmt = $conn->prepare($admissionSql);

    $admissionStmt->bindParam(':admissionno', $admissionno);

    $admissionStmt->execute();

    if ($admissionStmt->rowCount() == 0) {

        http_response_code(404);

        echo json_encode([
            "status" => false,
            "message" => "admission Number Does Not Exist"
        ]);

        exit;
    }

    // ==============================
    // CHECK BOOK CODE
    // ==============================

    $bookSql = "
        SELECT *
        FROM books
        WHERE bookcode = :bookcode
    ";

    $bookStmt = $conn->prepare($bookSql);

    $bookStmt->bindParam(':bookcode', $bookcode);

    $bookStmt->execute();

    $bookData = $bookStmt->fetch(PDO::FETCH_ASSOC);

    if (!$bookData) {

        http_response_code(404);

        echo json_encode([
            "status" => false,
            "message" => "Book Code Does Not Exist"
        ]);

        exit;
    }

    // ==============================
    // CHECK BOOK STATUS
    // ==============================

    if ($bookData['status'] == 'issued') {

        $resetSql = "
            UPDATE books
            SET status = 'Available'
            WHERE bookcode = :bookcode
        ";

        $resetStmt = $conn->prepare($resetSql);
        $resetStmt->bindParam(':bookcode', $bookcode);
        $resetStmt->execute();

    }

    // ==============================
    // INSERT TRANSACTION
    // ==============================

    $insertSql = "
        INSERT INTO transactions
        (
            admissionno,
            bookcode,
            createdby
        )
        VALUES
        (
            :admissionno,
            :bookcode,
            :userid
        )
    ";

    $insertStmt = $conn->prepare($insertSql);

    $insertStmt->bindParam(':admissionno', $admissionno);
    $insertStmt->bindParam(':bookcode', $bookcode);
    $insertStmt->bindParam(':userid', $userid);

    $insertStmt->execute();

    // ==============================
    // UPDATE BOOK STATUS
    // ==============================

    $updateSql = "
        UPDATE books
        SET status = 'issued'
        WHERE bookcode = :bookcode
    ";

    $updateStmt = $conn->prepare($updateSql);

    $updateStmt->bindParam(':bookcode', $bookcode);

    $updateStmt->execute();

    // ==============================
    // INSERT LOG
    // ==============================

    $activity = "issued Book : " . $bookcode;

    $logSql = "
        INSERT INTO logs
        (
            userid,
            activity
        )
        VALUES
        (
            :userid,
            :activity
        )
    ";
    


    $logStmt = $conn->prepare($logSql);

    $logStmt->bindParam(':userid', $userid);
    $logStmt->bindParam(':activity', $activity);

    $logStmt->execute();

      
    // http_response_code(200);

    echo json_encode([
        "status" => true,
        "message" => "Book issued Successfully",
        "userid" => $userid,
        "admissionno" => $admissionno,
        "bookcode" => $bookcode
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "status" => false,
        "message" => $e->getMessage()
    ]);
}
?>