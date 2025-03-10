<?php
session_start();
require_once 'db_config.php';
$user_name = 'Invité'; // Default user name

// Function to get user's name from the database
function getUserName($user_id) {
    $auth_conn = getUserAuthConnection(); // Ensure this function is working correctly
    if (!$auth_conn) {
        return 'Invité';
    }

    $sql = "SELECT nom FROM users WHERE id = ?";
    $stmt = $auth_conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $user_id); // Bind user_id to the query
        $stmt->execute(); // Execute the query
        $result = $stmt->get_result(); // Get the result

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['nom']; // Get the 'nom' column value
        }
        $stmt->close(); // Close the prepared statement
    } else {
        echo "Error preparing statement: " . $auth_conn->error;
    }
    closeConnection($auth_conn); // Close the connection
    return 'Invité'; // Fallback if no user found
}
if (isset($_SESSION['user_id'])) {
    $user_name = getUserName($_SESSION['user_id']);
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
unset($_SESSION['message']);

// Validate input parameters
if (!isset($_GET['id'], $_GET['table'])) {
    die("Paramètres invalides.");
}

$id = $_GET['id'];
$table = $_GET['table'];

// Get database connection
$conn = getAgricoleConnection();

// Check if table exists
$tableCheck = $conn->query("SHOW TABLES LIKE '$table'");
if ($tableCheck->num_rows == 0) {
    die("La table '$table' n'existe pas.");
}

// Fetch the primary key column dynamically
$primaryKey = null;
$keyResult = $conn->query("SHOW KEYS FROM `$table` WHERE Key_name = 'PRIMARY'");

if ($keyResult && $row = $keyResult->fetch_assoc()) {
    $primaryKey = $row['Column_name'];
}

if (!$primaryKey) {
    die("Aucune clé primaire trouvée pour la table '$table'. Veuillez vérifier votre schéma de base de données.");
}

// Detect correct ID type (integer or string)
$idType = is_numeric($id) ? 'i' : 's';

// Fetch the row data for the given ID
$sql = "SELECT * FROM `$table` WHERE `$primaryKey` = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param($idType, $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Ligne introuvable.");
}

$row = $result->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updateSql = "UPDATE `$table` SET ";
    $params = [];
    $types = '';

    foreach ($row as $column => $value) {
        if ($column !== $primaryKey) {
            $updateSql .= "`$column` = ?, ";
            $params[] = $_POST[$column];
            $types .= is_numeric($_POST[$column]) ? 'i' : 's'; // Detect type
        }
    }

    // Remove last comma and add WHERE clause
    $updateSql = rtrim($updateSql, ", ") . " WHERE `$primaryKey` = ?";
    $params[] = $id;
    $types .= $idType;

    // Prepare and execute update statement
    $stmt = $conn->prepare($updateSql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();

    // Redirect after successful update
    header("Location: view_table.php?table=" . urlencode($table));
    exit;
}

closeConnection($conn);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier la ligne - <?php echo htmlspecialchars($table); ?></title>

    <!-- Bootstrap 5.3.0 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f4f6f9;
        }
        .header-bg {
            background: linear-gradient(135deg, #2E7D32, #1B5E20);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            padding: 15px;
            color: white;
            font-weight: bold;
            display: flex;
            align-items: center;
        }
        .menu-btn {
            font-size: 1.8rem;
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            margin-right: auto;
        }
        .header-title {
            flex-grow: 1;
            text-align: center;
            margin: 0;
        }
        .sidebar {
            position: fixed;
            top: 0;
            left: -300px;
            width: 300px;
            height: 100%;
            background: linear-gradient(135deg, #2E7D32, #1B5E20);
            padding-top: 20px;
            transition: all 0.4s ease-in-out;
            box-shadow: 2px 0 15px rgba(0, 0, 0, 0.3);
        }
        .sidebar a, .logout-btn {
            padding: 15px 20px;
            display: block;
            color: white;
            font-size: 18px;
            text-decoration: none;
            transition: 0.3s;
            background: none;
            border: none;
            width: 100%;
            text-align: left;
        }
        .sidebar a:hover, .logout-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            padding-left: 30px;
            transition: 0.3s;
        }
        .sidebar.show {
            left: 0;
        }
        .close-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
        }
        .close-btn:hover {
            color: #f44336;
        }
        .search-container {
            margin: 20px auto;
            width: 90%;
            max-width: 400px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .search-input {
            flex: 1;
            padding: 10px;
            border-radius: 25px;
            border: 1px solid #ddd;
            outline: none;
        }
        .search-btn {
            background: #2E7D32;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 50%;
            transition: 0.3s;
        }
        .search-btn:hover {
            background: #1B5E20;
        }
        .container-custom {
            margin-top: 80px;
            padding-top: 50px;
        }
        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
            border-color: #bee5eb;
        }
        .user-info {
            text-align: center;
            margin-top: 20px;
            color: white;
        }
        .user-info i {
            font-size: 2rem;
        }
    </style>
</head>
<body>

<header class="header-bg">
    <button class="menu-btn" id="menuToggle"><i class="fas fa-bars"></i></button>
    <h2 class="header-title">DPA D'OUJDA</h2>
</header>

<nav id="sidebarMenu" class="sidebar">
    <button class="close-btn" id="closeMenuBtn"><i class="fas fa-times"></i></button>
    <a href="index.php"><i class="fas fa-home"></i> Accueil</a>
    <a href="view_tables.php"><i class="fas fa-table"></i> Voir les tableaux</a>
    <a href="upload.php"><i class="fas fa-upload"></i> Importer un fichier Excel</a>
    <form action="logout.php" method="post">
        <button type="submit" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Déconnexion</button>
    </form>
    <div class="user-info">
        <i class="fas fa-user-circle"></i>
        <p>Bonjour, <strong><?php echo htmlspecialchars($user_name); ?></strong>!</p>
    </div>
</nav>

<div class="container-custom container mt-5 pt-5">
    <h1>Modifier la ligne dans la table : <?php echo htmlspecialchars($table); ?></h1>

    <form method="POST">
        <?php foreach ($row as $column => $value): ?>
            <?php if ($column !== $primaryKey): ?>
                <div class="mb-3">
                    <label for="<?= htmlspecialchars($column) ?>" class="form-label"><?= htmlspecialchars($column) ?> :</label>
                    <input type="text" id="<?= htmlspecialchars($column) ?>" name="<?= htmlspecialchars($column) ?>" class="form-control" value="<?= htmlspecialchars($value) ?>" required>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>

        <button type="submit" class="btn btn-custom btn-primary">Enregistrer les modifications</button>
    </form>

    <a href="view_table.php?table=<?= urlencode($table) ?>" class="btn btn-outline-secondary mt-3">Annuler</a>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        let menuToggle = document.getElementById("menuToggle");
        let closeMenuBtn = document.getElementById("closeMenuBtn");
        let sidebarMenu = document.getElementById("sidebarMenu");

        // Open the sidebar on menu button click
        menuToggle.addEventListener("click", function() {
            sidebarMenu.classList.toggle("show");
        });

        // Close the sidebar on close button click
        closeMenuBtn.addEventListener("click", function() {
            sidebarMenu.classList.remove("show");
        });
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
