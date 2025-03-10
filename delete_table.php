<?php
// Include database connection settings
require_once 'db_config.php';

// Check if the 'table' parameter is passed in the URL
if (isset($_GET['table'])) {
    $tableName = $_GET['table'];

    // Sanitize the table name to prevent SQL injection (basic validation)
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) {
        header("Location: view_tables.php?msg=invalidtable");
        exit;
    }

    // Get the connection to the agricole database
    $conn = getAgricoleConnection();

    // Make sure the table exists
    $checkTableSql = "SHOW TABLES LIKE '$tableName'";
    $checkTableResult = $conn->query($checkTableSql);

    if ($checkTableResult->num_rows > 0) {
        // Proceed with table deletion
        $deleteSql = "DROP TABLE `$tableName`";
        if ($conn->query($deleteSql)) {
            // Table deleted successfully
            header("Location: view_tables.php?msg=deleted");
        } else {
            // Error in deletion
            header("Location: view_tables.php?msg=error");
        }
    } else {
        // Table doesn't exist
        header("Location: view_tables.php?msg=notfound");
    }

    // Close the database connection
    $conn->close();
} else {
    // Table name is not provided in the URL
    header("Location: view_tables.php?msg=error");
}
?>
