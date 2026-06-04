<?php

header("Content-Type: application/json");

// ==========================================
// DATABASE CONNECTION
// ==========================================

$host = "localhost";
$port = "5432";
$dbname = "LMS";
$username = "postgres";
$password = "SHA2456101717";

$conn = pg_connect(
    "host=$host port=$port dbname=$dbname user=$username password=$password"
);

if (!$conn) {
    echo json_encode([
        "status" => false,
        "message" => "Database Connection Failed"
    ]);
    exit;
}

// ==========================================
// CHECK REQUEST METHOD
// ==========================================

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    echo json_encode([
        "status" => false,
        "message" => "Only POST Method Allowed"
    ]);
    exit;
}

// ==========================================
// GET INPUTS
// ==========================================

$deptcode    = trim($_POST['deptcode'] ?? '');
$studentname = trim($_POST['studentname'] ?? '');
$fathername  = trim($_POST['fathername'] ?? '');
$address     = trim($_POST['address'] ?? '');
$contactno   = trim($_POST['contactno'] ?? '');
$dob         = trim($_POST['dob'] ?? '');
$createdby   = trim($_POST['createdby'] ?? ''); // Now Mandatory
$profilepic  = trim($_POST['profilepic'] ?? '');

// ==========================================
// VALIDATION
// ==========================================

// Added $createdby to the mandatory parameter check
if (
    empty($deptcode) ||
    empty($studentname) ||
    empty($fathername) ||
    empty($dob) ||
    empty($createdby)
) {
    echo json_encode([
        "status" => false,
        "message" => "Required parameters missing (deptcode, studentname, fathername, dob, createdby)"
    ]);
    exit;
}

// Validate Date Format (YYYY-MM-DD)
if (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $dob)) {
    echo json_encode([
        "status" => false,
        "message" => "Invalid DOB format. Use YYYY-MM-DD"
    ]);
    exit;
}

// ==========================================
// VERIFY FOREIGN KEYS (DEPARTMENT & STAFF)
// ==========================================

// Verify Department Exists
$deptCheck = pg_query_params($conn, "SELECT 1 FROM department WHERE deptcode = $1", array($deptcode));
if (pg_num_rows($deptCheck) == 0) {
    echo json_encode([
        "status" => false,
        "message" => "Invalid Department Code. Department does not exist."
    ]);
    exit;
}

// Verify Staff Member Exists (Removed !empty check because it is now mandatory)
$staffCheck = pg_query_params($conn, "SELECT 1 FROM staff WHERE staffid = $1", array($createdby));
if (pg_num_rows($staffCheck) == 0) {
    echo json_encode([
        "status" => false,
        "message" => "Invalid Staff ID. Creator does not exist."
    ]);
    exit;
}

// ==========================================
// AUTO-INCREMENT IDNO FOR CURRENT YEAR
// ==========================================

$currentYear = (int)date("Y");

$idQuery = "
    SELECT COALESCE(MAX(idno), 0) + 1 AS next_id 
    FROM admissions 
    WHERE admyear = $1 AND deptcode = $2
";

$idResult = pg_query_params($conn, $idQuery, array($currentYear, $deptcode));
if (!$idResult) {
    echo json_encode([
        "status" => false,
        "message" => "Failed to calculate internal ID system allocation.",
        "error" => pg_last_error($conn)
    ]);
    exit;
}

$idRow = pg_fetch_assoc($idResult);
$nextIdNo = (int)$idRow['next_id'];

// ==========================================
// INSERT NEW STUDENT REGISTRATION
// ==========================================

$admissionno = $deptcode . $currentYear . str_pad($nextIdNo, 3, "0", STR_PAD_LEFT);

$insertQuery = "
    INSERT INTO admissions (
        deptcode, 
        admyear, 
        idno,
        admissionno,
        studentname, 
        fathername, 
        address, 
        contactno, 
        dob, 
        createdby, 
        isactive, 
        profilepic
    ) 
    VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12)
    RETURNING admissionno, joindate;
";

$params = [
    $deptcode,
    $currentYear,
    $nextIdNo,
    $admissionno,
    $studentname,
    $fathername,
    !empty($address) ? $address : null,
    !empty($contactno) ? $contactno : null,
    $dob,
    $createdby,
    'true',
    !empty($profilepic) ? $profilepic : null
];
$insertResult = pg_query_params($conn, $insertQuery, $params);

if (!$insertResult) {
    echo json_encode([
        "status" => false,
        "message" => "Student Registration Failed",
        "error" => pg_last_error($conn)
    ]);
    exit;
}

$returnedRow = pg_fetch_assoc($insertResult);

// ==========================================
// FINAL RESPONSE
// ==========================================

echo json_encode([
    "status" => true,
    "message" => "Student Registered Successfully",
    "data" => [
        "admissionno" => $returnedRow['admissionno'], 
        "studentname" => $studentname,
        "deptcode" => $deptcode,
        "admyear" => $currentYear,
        "idno" => $nextIdNo,
        "joindate" => $returnedRow['joindate']
    ]
]);

// ==========================================
// CLOSE CONNECTION
// ==========================================

pg_close($conn);

?>