<?php
// Assuming you already have a connection to the database
require_once 'db_config.php';
$conn = getUserAuthConnection();

// Select all users with plain text passwords
$result = $conn->query("SELECT id, password FROM users");

while ($row = $result->fetch_assoc()) {
    // Hash the plain text password
    $hashedPassword = password_hash($row['password'], PASSWORD_DEFAULT);
    
    // Update the user password to the hashed password
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $hashedPassword, $row['id']);
    $stmt->execute();
}

echo "Passwords have been updated to hashed format!";
?>
