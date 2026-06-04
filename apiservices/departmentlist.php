<?php

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $userid = $_POST['userid'];
    $token = $_POST['token'];

    $host = 'localhost';
    $db = 'LMS';
    $user = 'postgres';
    $dbPassword = 'SHA2456101717';

    try {

        $dsn = "pgsql:host=$host;port=5432;dbname=$db";

        $pdo = new PDO($dsn, $user, $dbPassword);

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // CHECK TOKEN

        $checkQuery = "
            SELECT *
            FROM usertokens
            WHERE userid = '$userid'
            AND token = '$token' and status = 'true'
        ";
        
        
        $checkStmt = $pdo->query($checkQuery);


        if ($checkStmt->rowCount() > 0) {

            // FETCH DEPARTMENTS

            $query = "
                SELECT *
                FROM department
            ";

            $stmt = $pdo->query($query);

            $department = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'statuscode' => 200,
                'status' => 'success',
                'message' => 'Department fetched successfully',
                'data' => $department
            ]);

        } else {

            echo json_encode([
                'statuscode' => 201,
                'status' => 'fail',
                'message' => 'Invalid userid or token'
            ]);
        }

    } catch (PDOException $e) {

        echo json_encode([
            'statuscode' => 201,
            'status' => 'fail',
            'message' => $e->getMessage()
        ]);
    }

} else {

    echo json_encode([
        'statuscode' => 201,
        'status' => 'fail',
        'message' => 'Invalid request method'
        
    ]);
    
}


?>