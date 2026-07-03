<?php
// Database connection configuration
$host    = 'localhost';
$db      = 'bookshop';
$user    = 'root';
$pass    = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

$message = "";

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and collect inputs
    $method_name    = trim($_POST['method_name'] ?? '');
    $account_number = trim($_POST['account_number'] ?? '');
    $account_holder = trim($_POST['account_holder'] ?? '');
    $description    = trim($_POST['description'] ?? '');
    $is_active      = isset($_POST['is_active']) ? 1 : 0; // Checkbox returns 1 if checked, 0 if not

    // Basic Validation
    if (empty($method_name) || empty($account_number) || empty($account_holder)) {
        $message = "<p style='color: red;'>Please fill out all required fields.</p>";
    } else {
        try {
            // Prepare SQL Statement
            $sql = "INSERT INTO `payment_method` (`method_name`, `account_number`, `account_holder`, `is_active`, `description`) 
                    VALUES (:method_name, :account_number, :account_holder, :is_active, :description)";
            
            $stmt = $pdo->prepare($sql);
            
            // Execute with bound parameters
            $stmt->execute([
                ':method_name'    => $method_name,
                ':account_number' => $account_number,
                ':account_holder' => $account_holder,
                ':is_active'      => $is_active,
                ':description'    => $description
            ]);

            $message = "<p style='color: green;'>Payment method added successfully!</p>";
        } catch (\PDOException $e) {
            $message = "<p style='color: red;'>Error saving to database: " . $e->getMessage() . "</p>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - Add Payment Method</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background-color: #f9f9f9; }
        .form-container { max-width: 500px; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input[type="text"], .form-group textarea { width: 100%; padding: 8px; box-sizing: border-box; }
        .btn-submit { background-color: #007BFF; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; }
        .btn-submit:hover { background-color: #0056b3; }
    </style>
</head>
<body>

<div class="form-container">
    <h2>Add New Payment Method</h2>
    
    <?php echo $message; ?>

    <form action="" method="POST">
        <div class="form-group">
            <label for="method_name">Method Name (e.g., Bank Transfer, Stripe, PayPal) *</label>
            <input type="text" id="method_name" name="method_name" required>
        </div>

        <div class="form-group">
            <label for="account_number">Account / Routing Number *</label>
            <input type="text" id="account_number" name="account_number" required>
        </div>

        <div class="form-group">
            <label for="account_holder">Account Holder Name *</label>
            <input type="text" id="account_holder" name="account_holder" required>
        </div>

        <div class="form-group">
            <label for="description">Instructions / Description</label>
            <textarea id="description" name="description" rows="4"></textarea>
        </div>

        <div class="form-group">
            <label>
                <input type="checkbox" name="is_active" value="1" checked> Activate immediately
            </label>
        </div>

        <button type="submit" class="btn-submit">Save Payment Method</button>
    </form>
</div>

</body>
</html>