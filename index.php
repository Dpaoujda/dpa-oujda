<?php
// Start session to manage user authentication
session_start();
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page if not logged in
    header("Location: login.php");
    exit; // Ensure no further code is executed after the redirection
}

// Include database connection settings
require_once 'db_config.php';

// Initialize variables
$searchQuery = '';
$searchResults = [];
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

// Check if user is logged in and get user name
if (isset($_SESSION['user_id'])) {
    $user_name = getUserName($_SESSION['user_id']);
}

// Function to search in all tables of the agricole database
function searchInAgricole($searchQuery) {
    $conn = getAgricoleConnection(); // Connection to the agricole database
    if (!$conn) {
        echo "Error connecting to the agricole database.";
        return [];
    }

    $searchResults = [];
    $sql = "SHOW TABLES";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        while ($table = $result->fetch_assoc()) {
            $tableName = $table['Tables_in_agricole'];
            $columnsSql = "SHOW COLUMNS FROM `$tableName`";
            $columnsResult = $conn->query($columnsSql);

            if ($columnsResult) {
                $columns = [];
                while ($column = $columnsResult->fetch_assoc()) {
                    $columns[] = $column['Field'];
                }

                $conditions = [];
                $bindParams = [];

                // Search across all columns
                foreach ($columns as $column) {
                    $conditions[] = "`$column` LIKE ?";
                    $bindParams[] = $searchQuery;
                }

                if (!empty($conditions)) {
                    $searchSql = "SELECT * FROM `$tableName` WHERE " . implode(' OR ', $conditions);
                    $stmt = $conn->prepare($searchSql);

                    if ($stmt) {
                        $stmt->bind_param(str_repeat('s', count($bindParams)), ...$bindParams);
                        $stmt->execute();
                        $searchResult = $stmt->get_result();

                        if ($searchResult->num_rows > 0) {
                            $searchResults[$tableName] = $searchResult->fetch_all(MYSQLI_ASSOC);
                        }
                    } else {
                        echo "Error preparing statement: " . $conn->error;
                    }
                }
            } else {
                echo "Error fetching columns for table $tableName: " . $conn->error;
            }
        }
    } else {
        echo "Error fetching tables: " . $conn->error;
    }

    closeConnection($conn); // Close the connection
    return $searchResults;
}

// If there is a search query, fetch matching data
if (isset($_GET['query']) && !empty($_GET['query'])) {
    $searchQuery = "%" . $_GET['query'] . "%";
    $searchResults = searchInAgricole($searchQuery); // Perform search
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recherche de Tables - Agricole</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* Include the previous CSS code here */
        body {
            background-color: #f4f6f9;
            font-family: 'Poppins', sans-serif;
        }
        .table-container {
            margin-top: 30px;
            overflow-x: auto; /* Permet le défilement horizontal sur petits écrans */
            -webkit-overflow-scrolling: touch; /* Pour un défilement plus fluide sur mobile */
        }

        .table-custom {
            width: 100%; /* S'assure que le tableau occupe toute la largeur disponible */
            table-layout: auto; /* Permet un redimensionnement dynamique des colonnes */
        }

        .table-custom th, .table-custom td {
            text-align: center;
            padding: 12px 15px; /* Ajoute un peu de padding pour plus de lisibilité */
            white-space: nowrap; /* Empêche le texte de se briser sur plusieurs lignes */
        }

        /* Optionnel : Vous pouvez ajuster les colonnes pour qu'elles ne s'étendent pas trop en largeur sur petits écrans */
        @media (max-width: 768px) {
            .table-custom th, .table-custom td {
                font-size: 12px; /* Réduit la taille de la police sur les petits écrans */
                padding: 8px 10px; /* Réduit un peu le padding */
            }
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
        .header-bg {
            background: linear-gradient(135deg, #2E7D32, #1B5E20);
            padding: 15px;
            color: white;
            font-weight: bold;
            display: flex;
            align-items: center;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }
        .header-title {
            flex-grow: 1;
            text-align: center;
            margin: 0;
        }
        .form-container {
            width: 60%;
            margin: 40px auto;
            padding: 30px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        .form-title {
            text-align: center;
            margin-bottom: 30px;
        }
        .form-control {
            border-radius: 10px;
            padding: 10px 15px;
            margin-bottom: 20px;
            transition: border-color 0.3s ease;
        }
        .form-control:focus {
            border-color: #2E7D32;
        }
        .search-btn {
            width: 100%;
            padding: 12px;
            background-color: #2E7D32;
            border: none;
            color: white;
            border-radius: 10px;
            font-size: 16px;
            transition: background-color 0.3s ease;
        }
        .search-btn:hover {
            background-color: #1B5E20;
        }
        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
            border-color: #bee5eb;
            margin-top: 20px;
            width: 80%;
            margin-left: auto;
            margin-right: auto;
        }
        .footer {
            text-align: center;
            font-size: 14px;
            color: #777;
            margin-top: 40px;
        }
        .table-container {
            margin-top: 30px;
        }
        .table-custom th, .table-custom td {
            text-align: center;
        }
        .search-input {
            width: 100%;
            border-radius: 10px;
            padding: 10px 15px;
            font-size: 16px;
        }
        .table-custom {
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        /* Hamburger menu styles */
        .navbar-toggler-icon {
            background-color: #fff;
        }

        .navbar-nav .nav-link {
            color: white !important;
        }

        .navbar-nav .nav-link:hover {
            color: #1B5E20 !important;
        }
        .close-btn:hover {
            color: red;  /* Change the color to red on hover */
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
    <a href="upload.php"><i class="fas fa-upload"></i> Importer des données</a>
    <form action="logout.php" method="POST">
        <button type="submit" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Déconnexion</button>
    </form>
    <div class="user-info">
        <i class="fas fa-user-circle"></i>
        <p>Bonjour, <strong><?php echo htmlspecialchars($user_name); ?></strong>!</p>
    </div>
</nav>

<div class="container">
    <div class="form-container">
        <h1 class="form-title">Rechercher des Informations</h1>
        <!-- Search Form -->
        <form method="get" action="index.php">
            <input type="text" class="form-control search-input" name="query" placeholder="Rechercher par CIN, code RNA ou le Nom..." value="<?php echo htmlspecialchars($searchQuery); ?>" required>
            <button type="submit" class="search-btn">
                <i class="fas fa-search"></i> Rechercher <!-- Search icon added here -->
            </button>
        </form>
    </div>

    <!-- Display Search Results -->
    <?php if (!empty($searchResults)): ?>
        <div class="table-container">
            <?php foreach ($searchResults as $tableName => $results): ?>
            <h4>Table: <?php echo htmlspecialchars($tableName); ?></h4>
            <table class="table table-custom">
                <thead>
                    <tr>
                        <?php foreach (array_keys($results[0]) as $column): ?>
                        <th><?php echo htmlspecialchars($column); ?></th>
                        <?php endforeach; ?>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $row): ?>
                    <tr>
                        <?php foreach ($row as $cell): ?>
                        <td><?php echo htmlspecialchars($cell); ?></td>
                        <?php endforeach; ?>
                        <td>
                            <a href="edit_row.php?table=<?php echo urlencode($tableName); ?>&id=<?php echo urlencode($row['id']); ?>" class="btn btn-warning">Modifier</a>
                            <a href="delete_row.php?table=<?php echo urlencode($tableName); ?>&id=<?php echo urlencode($row['id']); ?>" 
   class="btn btn-danger"
   onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette ligne ?');">
    Supprimer
</a>

                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endforeach; ?>
        </div>
    <?php elseif (!empty($searchQuery) && empty($searchResults)): ?>
        <div class="alert alert-info">
            Aucun résultat trouvé pour votre recherche "<?php echo htmlspecialchars($searchQuery); ?>".
        </div>
    <?php endif; ?>
</div>

<footer class="footer">
    <p>&copy; 2025 DPA D'OUJDA. Tous droits réservés.</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('menuToggle').addEventListener('click', function() {
        document.getElementById('sidebarMenu').classList.add('show');
    });

    document.getElementById('closeMenuBtn').addEventListener('click', function() {
        document.getElementById('sidebarMenu').classList.remove('show');
    });
</script>
</body>
</html>
