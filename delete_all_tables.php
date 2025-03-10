<?php
require_once 'db_config.php';

$conn = getAgricoleConnection();

// Get all table names
$sql = "SHOW TABLES";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $tableName = $row[array_keys($row)[0]]; // Get table name dynamically
        $conn->query("DROP TABLE `$tableName`");
    }
}

$conn->close();

// Redirect back with success message
header("Location: view_tables.php?msg=deleted_all");
exit();
?>
