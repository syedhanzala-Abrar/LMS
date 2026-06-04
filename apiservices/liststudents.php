<?php


header("Content-Type: application/json");


$host = "localhost";
$port = "5432";
$dbname = "LMS";
$dbuser = "postgres";
$dbpassword = "SHA2456101717";

try {

    $conn = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname",
        $dbuser,
        $dbpassword
    );

    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch(PDOException $e) {

    echo json_encode([
        "status" => false,
        "message" => $e->getMessage()
    ]);

    exit;
}



$deptcode = isset($_GET['deptcode']) 
            ? $_GET['deptcode'] 
            : '';

$registeredyear = isset($_GET['registeredyear']) 
                  ? $_GET['registeredyear'] 
                  : '';

$token = isset($_GET['token']) 
         ? $_GET['token'] 
         : '';

$userid = isset($_GET['userid']) 
          ? $_GET['userid'] 
          : '';



if(
    empty($deptcode) ||
    empty($registeredyear) ||
    empty($token) ||
    empty($userid)
){

    echo json_encode([
        "status" => false,
        "message" => "Missing required parameters"
    ]);

    exit;
}



$tokenquery = "
SELECT *
FROM usertokens
WHERE userid = :userid
AND token = :token
AND status = true
";

$stmt = $conn->prepare($tokenquery);

$stmt->bindParam(':userid', $userid);
$stmt->bindParam(':token', $token);

$stmt->execute();

if($stmt->rowCount() == 0){

    echo json_encode([
        "status" => false,
        "message" => "Invalid token"
    ]);

    exit;
}


$query = "
SELECT
    admissionno,
    studentname,
    fathername,
    address,
    contactno,
    dob,
    admyear,
    deptcode,
    joindate,
    isactive,
    profilepic
FROM admissions
WHERE deptcode = :deptcode
AND admyear = :registeredyear
ORDER BY studentname ASC
";

$stmt2 = $conn->prepare($query);

$stmt2->bindParam(':deptcode', $deptcode);
$stmt2->bindParam(':registeredyear', $registeredyear);

$stmt2->execute();

$students = $stmt2->fetchAll(PDO::FETCH_ASSOC);



if(count($students) > 0){

    echo json_encode([
        "status" => true,
        "message" => "Students List Found",
        "count" => count($students),
        "data" => $students
    ]);

} else {

    echo json_encode([
        "status" => false,
        "message" => "No Students Found"
    ]);
}

?>
