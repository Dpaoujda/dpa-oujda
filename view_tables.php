<?php
// Include database connection settings
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

// Get the connection to the agricole database
$conn = getAgricoleConnection();

// Query to fetch all tables in the 'agricole' database
$sql = "SHOW TABLES";
$result = $conn->query($sql);

// Fetch all the tables from the database
$tables = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $tables[] = $row;
    }
} else {
    $tables = [];
}

// Get the total number of tables
$totalTables = count($tables);

// Initialize an array to store table names and row counts
$tableRowCounts = [];

foreach ($tables as $table) {
    $tableName = $table['Tables_in_agricole'];
    $countSql = "SELECT COUNT(*) AS rowCount FROM `$tableName`";
    $countResult = $conn->query($countSql);
    $rowCount = 0;
    if ($countResult && $countResult->num_rows > 0) {
        $row = $countResult->fetch_assoc();
        $rowCount = $row['rowCount'];
    }
    $tableRowCounts[$tableName] = $rowCount;
}

// Close the database connection
$conn->close();

// Check for success or error messages from deletion
$msg = '';
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'deleted') {
        $msg = "<div class='alert alert-success alert-dismissible fade show' role='alert'>
                    La table a été supprimée avec succès.
                    <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                </div>";
    } elseif ($_GET['msg'] == 'error') {
        $msg = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
                    Erreur lors de la suppression. Veuillez réessayer.
                    <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                </div>";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voir les Tables - Agricole</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            background-color: #f4f6f9;
            font-family: 'Poppins', sans-serif;
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
            z-index: 1050;
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
        .tables-list {
            margin-top: 40px;
        }
        .table-container {
            margin: 20px;
        }
        .table-custom th, .table-custom td {
            text-align: center;
            padding: 15px;
            vertical-align: middle;
        }
        .table-custom th {
            background-color: #2E7D32;
            color: #fff;
            font-size: 1.1rem;
        }
        .table-custom td {
            background-color: #fafafa;
            font-size: 1rem;
        }
        .table-custom tr:hover {
            background-color: #f1f1f1;
            transition: background-color 0.3s ease;
        }
        .table-custom td a {
            color: #fff;
            background-color: #007bff;
            padding: 8px 12px;
            border-radius: 4px;
            text-decoration: none;
        }
        .table-custom td a:hover {
            background-color: #0056b3;
        }
        .alert-warning {
            background-color: #fff3cd;
            border-color: #ffeeba;
            padding: 8px 15px;
            font-size: 1.5rem;
            display: inline-block;
            border-radius: 5px;
            text-align: center;
            margin: 0 auto;
            width: auto;
        }
        .total-rows {
            font-weight: bold;
            font-size: 1.2rem;
            margin-top: 30px;
        }
        .text-center {
            text-align: center !important;
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

<div class="container mt-5 pt-5">
    <h1 class="text-center mb-4">Tableaux de la base de données DPA d'Oujda</h1>

    <!-- Displaying Success/Error Message -->
    <?php if ($msg): ?>
        <div class="text-center mb-4">
            <?php echo $msg; ?>
        </div>
    <?php endif; ?>

    <div class="tables-list">
        <?php if (count($tables) > 0): ?>
            <div class="table-container">
                <table class="table table-custom table-striped">
                    <thead>
                        <tr>
                            <th scope="col">Nom de la Table</th>
                            <th scope="col">Nombre de Lignes</th>
                            <th scope="col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tables as $table): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($table['Tables_in_agricole']); ?></td>
                                <td><?php echo $tableRowCounts[$table['Tables_in_agricole']]; ?></td>
                                <td>
                                    <a href="view_table.php?table=<?php echo urlencode($table['Tables_in_agricole']); ?>" class="btn btn-info">
                                        Voir
                                    </a>
                                    <button onclick="confirmDelete('<?php echo $table['Tables_in_agricole']; ?>')" class="btn btn-danger">
                                        Supprimer
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="total-rows text-center">
                    <p>Total des tableaux dans la base de données : <?php echo $totalTables; ?></p>
                </div>
                <div class="text-center mb-3">
                    <button onclick="confirmDeleteAll()" class="btn btn-danger">
                        Supprimer toutes les tables
                    </button>
                </div>

            </div>
        <?php else: ?>
            <div class="alert alert-warning text-center d-flex justify-content-center mx-auto w-auto d-inline-block p-2" style="width: 50vw;">
                <small>Aucune table trouvée dans la base de données.</small>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    function confirmDelete(tableName) {
        if (confirm("Êtes-vous sûr de vouloir supprimer la table " + tableName + "?")) {
            window.location.href = 'delete_table.php?table=' + encodeURIComponent(tableName);
        }
    }

    document.addEventListener("DOMContentLoaded", function() {
        let menuToggle = document.getElementById("menuToggle");
        let closeMenuBtn = document.getElementById("closeMenuBtn");
        let sidebarMenu = document.getElementById("sidebarMenu");

        menuToggle.addEventListener("click", function() {
            sidebarMenu.classList.toggle("show");
        });

        closeMenuBtn.addEventListener("click", function() {
            sidebarMenu.classList.remove("show");
        });
    });

    function confirmDeleteAll() {
        if (confirm("⚠️ Êtes-vous sûr de vouloir supprimer TOUTES les tables ? Cette action est irréversible !")) {
            window.location.href = 'delete_all_tables.php';
        }
    }
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
