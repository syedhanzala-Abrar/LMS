<?php

header("Content-Type: application/json");


// ==========================================
// DATABASE CONNECTION
// ==========================================

$host = "127.0.0.1";
$port = "5432";
$dbname = "LMS";
$username = "postgres";
$password = "SHA2456101717";

$conn = pg_connect(
    "host=$host port=$port dbname=$dbname user=$username password=$password"
);

if(!$conn)
{
    echo json_encode([
        "status" => false,
        "message" => "Database Connection Failed"
    ]);
    exit;
}


// ==========================================
// CHECK REQUEST METHOD
// ==========================================

if($_SERVER['REQUEST_METHOD'] != 'POST')
{
    echo json_encode([
        "status" => false,
        "message" => "Only POST Method Allowed"
    ]);
    exit;
}


// ==========================================
// GET INPUTS
// ==========================================

$categorycode = trim($_POST['categorycode'] ?? '');
$userid       = trim($_POST['userid'] ?? '');
$token        = trim($_POST['token'] ?? '');


// ==========================================
// VALIDATION
// ==========================================

if(
    empty($categorycode) ||
    empty($userid) ||
    empty($token)
)
{
    echo json_encode([
        "status" => false,
        "message" => "All Parameters Required"
    ]);
    exit;
}


// ==========================================
// CHECK USER TOKEN
// ==========================================

$tokenQuery = "
SELECT *
FROM usertokens
WHERE userid = $1
AND token = $2
AND status = TRUE
";

$tokenResult = pg_query_params(
    $conn,
    $tokenQuery,
    array($userid, $token)
);

if(!$tokenResult)
{
    echo json_encode([
        "status" => false,
        "message" => "Token Query Failed",
        "error" => pg_last_error($conn)
    ]);
    exit;
}


if(pg_num_rows($tokenResult) == 0)
{
    echo json_encode([
        "status" => false,
        "message" => "Invalid UserID Or Token"
    ]);
    exit;
}


// ==========================================
// GET ALL ISSUED BOOKS
// BASED ON CATEGORY CODE
// ==========================================

$booksQuery = "

SELECT

    t.transactionid,
    t.admissionno,
    t.bookcode,
    b.booktitle,
    b.author,
    b.categorycode,
    t.issuedate,
    t.returndate,
    t.status

FROM transactions t

INNER JOIN books b
ON t.bookcode = b.bookcode

WHERE
    b.categorycode = $1
    AND t.status = 'issued'

ORDER BY t.issuedate DESC

";


$booksResult = pg_query_params(
    $conn,
    $booksQuery,
    array($categorycode)
);


if(!$booksResult)
{
    echo json_encode([
        "status" => false,
        "message" => "Books Query Failed",
        "error" => pg_last_error($conn)
    ]);
    exit;
}


// ==========================================
// STORE RESULTS
// ==========================================

$books = [];

while($row = pg_fetch_assoc($booksResult))
{
    $books[] = $row;
}


// ==========================================
// FINAL RESPONSE
// ==========================================

if(count($books) > 0)
{
    echo json_encode([
        "status" => true,
        "message" => "Issued Books Found",
        "count" => count($books),
        "data" => $books
    ]);
}
else
{
    echo json_encode([
        "status" => false,
        "message" => "No Issued Books Found"
    ]);
}


// ==========================================
// CLOSE CONNECTION
// ==========================================

pg_close($conn);

?>