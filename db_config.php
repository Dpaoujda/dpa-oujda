<?php
// Load environment variables for security (using the Dotenv library or custom .env file)
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

// Database connection settings for user_auth and agricole databases
define("DB_SERVER", getenv('DB_SERVER') ?: "localhost");
define("DB_USERNAME", getenv('DB_USERNAME') ?: "root");  // Default username for MySQL in WAMP
define("DB_PASSWORD", getenv('DB_PASSWORD') ?: "Mido");  // Your password for MySQL (consider using environment variables)
define("USER_AUTH_DB", getenv('USER_AUTH_DB') ?: "user_auth");  // Database for user authentication
define("AGRICOLE_DB", getenv('AGRICOLE_DB') ?: "agricole");  // Database for agricultural data

// Log file path for error logging
define("LOG_FILE", "db_error.log");

/**
 * Log error to a file
 */
function logError($error) {
    $timestamp = date("Y-m-d H:i:s");
    file_put_contents(LOG_FILE, "$timestamp - $error\n", FILE_APPEND);
}

/**
 * Create a connection to a MySQL database.
 * 
 * @param string $dbname The database name to connect to.
 * @return mysqli The database connection object.
 */
function createConnection($dbname) {
    $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, $dbname);

    // Check for connection errors
    if ($conn->connect_error) {
        logError("Connection failed to $dbname: " . $conn->connect_error);
        die("Unable to connect to database. Please try again later.");
    }

    return $conn;
}

/**
 * Get the user_auth database connection.
 * 
 * @return mysqli The user_auth database connection object.
 */
function getUserAuthConnection() {
    return createConnection(USER_AUTH_DB);
}

/**
 * Get the agricole database connection.
 * 
 * @return mysqli The agricole database connection object.
 */
function getAgricoleConnection() {
    return createConnection(AGRICOLE_DB);
}

/**
 * Close the database connection.
 * 
 * @param mysqli $conn The connection object to close.
 */
function closeConnection($conn) {
    if ($conn) {
        $conn->close();
    }
}

/**
 * Log and handle query errors.
 * 
 * @param string $query The SQL query that caused the error.
 * @param mysqli $conn The database connection object.
 */
function handleQueryError($query, $conn) {
    $error = $conn->error;
    logError("Query failed: $query\nError: $error");
    die("An error occurred. Please try again later.");
}
?>
