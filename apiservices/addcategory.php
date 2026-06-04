<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(201);
    echo json_encode(["status_code" => 201, "status" => "failed", "message" => "Method Not Allowed. Use POST."]);
    exit;
}

$db_host = "localhost";
$db_port = "5432";
$db_name = "lms";
$db_user = "jaswantha";
$db_pass = "9849150209";

$input = json_decode(file_get_contents("php://input"), true);
if (empty($input)) {
    $input = $_POST;
}

$user_id       = isset($input['user_id'])      ? trim($input['user_id'])      : null;
$token         = isset($input['token'])        ? trim($input['token'])        : null;
$dept_code     = isset($input['deptcode'])     ? trim($input['deptcode'])     : null;
$category_id   = isset($input['category'])     ? trim($input['category'])     : null;
$category_name = isset($input['name'])         ? trim($input['name'])         : null;
$description   = isset($input['description'])  ? trim($input['description'])  : null;
$no_days       = isset($input['no_days'])       ? (int)$input['no_days']       : null;
$introducedby  = isset($input['introducedby'])  ? trim($input['introducedby']) : null;
$isactive      = true;

// Validate required fields
$errors = [];
if (empty($user_id))       $errors[] = "user_id is required.";
if (empty($token))         $errors[] = "token is required.";
if (empty($dept_code))     $errors[] = "deptcode is required.";
if (empty($category_id))   $errors[] = "category is required.";
if (empty($category_name)) $errors[] = "name is required.";
if (empty($description))   $errors[] = "description is required.";
if (empty($no_days))       $errors[] = "no_days is required.";
if (empty($introducedby))  $errors[] = "introducedby is required.";

if (!empty($errors)) {
    http_response_code(201);
    echo json_encode(["status_code" => 201, "status" => "failed", "message" => "Validation failed.", "errors" => $errors]);
    exit;
}

// Validate category_id format
if (!preg_match('/^[a-zA-Z0-9]+$/', $category_id)) {
    http_response_code(201);
    echo json_encode(["status_code" => 201, "status" => "failed", "message" => "The category '$category_id' you entered is incorrect. Only letters and numbers are allowed."]);
    exit;
}

// Validate category_name format
if (!preg_match('/^[a-zA-Z\s]+$/', $category_name)) {
    http_response_code(201);
    echo json_encode(["status_code" => 201, "status" => "failed", "message" => "The name '$category_name' you entered is incorrect. Only letters and spaces are allowed."]);
    exit;
}

// Validate description format
if (!preg_match('/^[a-zA-Z0-9\s]+$/', $description)) {
    http_response_code(201);
    echo json_encode(["status_code" => 201, "status" => "failed", "message" => "The description '$description' you entered is incorrect. Only letters, numbers and spaces are allowed."]);
    exit;
}

// Connect to PostgreSQL
$dsn = "pgsql:host=$db_host;port=$db_port;dbname=$db_name";
try {
    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    http_response_code(201);
    echo json_encode(["status_code" => 201, "status" => "failed", "message" => "Database connection failed."]);
    exit;
}

// Check if user_id exists in usertokens
$stmt = $pdo->prepare("SELECT token FROM usertokens WHERE user_id = :user_id AND status = TRUE ORDER BY loggedin_date DESC LIMIT 1");
$stmt->execute([':user_id' => $user_id]);
$row = $stmt->fetch();
if (!$row) {
    http_response_code(201);
    echo json_encode(["status_code" => 201, "status" => "failed", "message" => "User ID '$user_id' does not exist. Please login again."]);
    exit;
}

// Check if token matches
if ($row['token'] !== $token) {
    http_response_code(201);
    echo json_encode(["status_code" => 201, "status" => "failed", "message" => "Invalid token. Please login again."]);
    exit;
}

// Check if dept_code exists
$stmt = $pdo->prepare("SELECT 1 FROM departments WHERE dept_code = :dept_code");
$stmt->execute([':dept_code' => $dept_code]);
if (!$stmt->fetch()) {
    http_response_code(201);
    echo json_encode(["status_code" => 201, "status" => "failed", "message" => "dept_code '$dept_code' does not exist. Try with an existing dept_code."]);
    exit;
}

// Check if staff_id exists
$stmt = $pdo->prepare("SELECT 1 FROM staff WHERE staff_id = :staff_id");
$stmt->execute([':staff_id' => $introducedby]);
if (!$stmt->fetch()) {
    http_response_code(201);
    echo json_encode(["status_code" => 201, "status" => "failed", "message" => "Staff ID '$introducedby' does not exist. Try with an existing staff ID."]);
    exit;
}

// Check if category_id already exists
$stmt = $pdo->prepare("SELECT 1 FROM categories WHERE dept_code = :dept_code AND category_id = :category_id");
$stmt->execute([':dept_code' => $dept_code, ':category_id' => $category_id]);
if ($stmt->fetch()) {
    http_response_code(201);
    echo json_encode(["status_code" => 201, "status" => "failed", "message" => "Category '$category_id' already exists. Try with a different category."]);
    exit;
}

// Check if category_name already exists
$stmt = $pdo->prepare("SELECT 1 FROM categories WHERE dept_code = :dept_code AND category_name = :category_name");
$stmt->execute([':dept_code' => $dept_code, ':category_name' => $category_name]);
if ($stmt->fetch()) {
    http_response_code(201);
    echo json_encode(["status_code" => 201, "status" => "failed", "message" => "Category name '$category_name' already exists. Try with a different name."]);
    exit;
}

// Check if description already exists
$stmt = $pdo->prepare("SELECT 1 FROM categories WHERE dept_code = :dept_code AND description = :description");
$stmt->execute([':dept_code' => $dept_code, ':description' => $description]);
if ($stmt->fetch()) {
    http_response_code(201);
    echo json_encode(["status_code" => 201, "status" => "failed", "message" => "Description '$description' already exists. Try with a different description."]);
    exit;
}

// Insert into categories table
try {
    $sql = "INSERT INTO categories (dept_code, category_id, category_name, description, no_days, isactive, introducedby)
            VALUES (:dept_code, :category_id, :category_name, :description, :no_days, :isactive, :introducedby)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':dept_code'     => $dept_code,
        ':category_id'   => $category_id,
        ':category_name' => $category_name,
        ':description'   => $description,
        ':no_days'       => $no_days,
        ':isactive'      => $isactive,
        ':introducedby'  => $introducedby,
    ]);

    http_response_code(200);
    echo json_encode(["status_code" => 200, "status" => "success", "message" => "Category added successfully."]);

} catch (PDOException $e) {
    http_response_code(201);
    echo json_encode(["status_code" => 201, "status" => "failed", "message" => "Failed to insert category."]);
}