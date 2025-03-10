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

// Function to sanitize and validate table names
function isValidTableName($tableName) {
    // You could dynamically fetch valid tables from the database or add more here
    return preg_match('/^[a-zA-Z0-9_]+$/', $tableName);  // Basic validation for table name
}

// Check if the table name is provided via URL and is valid
if (isset($_GET['table']) && isValidTableName($_GET['table'])) {
    $tableName = $_GET['table'];

    // Get the connection to the agricole database
    $conn = getAgricoleConnection();

    // Pagination setup
    $limit = 20;  // Number of rows per page
    $page = isset($_GET['page']) ? $_GET['page'] : 1;
    $offset = ($page - 1) * $limit;

    // Query to fetch data from the table with LIMIT and OFFSET for pagination
    $sql = "SELECT * FROM `$tableName` LIMIT $limit OFFSET $offset";
    $result = $conn->query($sql);

    // Fetch all rows
    $data = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }

    // Get the total number of rows in the table for pagination
    $countSql = "SELECT COUNT(*) AS total FROM `$tableName`";
    $countResult = $conn->query($countSql);
    $totalRows = $countResult->fetch_assoc()['total'];
    $totalPages = ceil($totalRows / $limit);

    // Close the database connection
    $conn->close();
} else {
    echo "Table non valide ou non spécifiée.";
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voir Table - <?php echo htmlspecialchars($tableName); ?></title>
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
        .table-container {
            max-height: 500px;
            overflow-y: auto;
        }
        .pagination-container {
            margin-top: 30px;
            text-align: center;
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
    <h1 class="text-center">Table: <?php echo htmlspecialchars($tableName); ?></h1>
    
    <?php if (!empty($data)): ?>
        <div class="table-container">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <?php
                        // Dynamically output the table headers based on the first row's keys (column names)
                        foreach (array_keys($data[0]) as $column) {
                            echo "<th>" . htmlspecialchars($column) . "</th>";
                        }
                        ?>
                        <th>Action</th> <!-- Column for action buttons -->
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data as $row): ?>
                        <tr>
                            <?php
                            // Output each column's value in the row
                            foreach ($row as $value) {
                                echo "<td>" . htmlspecialchars($value) . "</td>";
                            }

                            // Detect ID column (improved method)
                            $rowID = null;
                            foreach ($row as $column => $value) {
                                if (preg_match('/_id$|^id$/', $column)) { // Detect columns ending in _id or named id
                                    $rowID = $value;
                                    break;
                                }
                            }

                            // If no ID found, use the first column as fallback
                            if ($rowID === null) {
                                $rowID = reset($row); 
                            }
                            ?>

                            <td>
                                <?php if ($rowID): ?>
                                    <a href="edit_row.php?table=<?php echo urlencode($tableName); ?>&id=<?php echo $rowID; ?>" class="btn btn-primary btn-sm">
                                        Modifier
                                    </a>
                                    <a href="#" onclick="confirmDelete('<?php echo urlencode($tableName); ?>', '<?php echo urlencode($rowID); ?>')" class="btn btn-danger btn-sm">
                                        Supprimer
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">Aucune clé d'ID trouvée</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <div class="pagination-container">
            <nav>
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo ($page == 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="view_table.php?table=<?php echo urlencode($tableName); ?>&page=<?php echo ($page - 1); ?>">Précédent</a>
                    </li>

                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                            <a class="page-link" href="view_table.php?table=<?php echo urlencode($tableName); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>

                    <li class="page-item <?php echo ($page == $totalPages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="view_table.php?table=<?php echo urlencode($tableName); ?>&page=<?php echo ($page + 1); ?>">Suivant</a>
                    </li>
                </ul>
            </nav>
        </div>

    <?php else: ?>
        <p>Aucune donnée disponible pour cette table.</p>
    <?php endif; ?>

    <a href="view_tables.php" class="btn btn-secondary">Retour</a>
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

    function confirmDelete(table, id) {
        // JavaScript confirmation message
        if (confirm("Êtes-vous sûr de vouloir supprimer cette ligne ?")) {
            // If confirmed, redirect to delete_row.php to delete the row
            window.location.href = "delete_row.php?table=" + table + "&id=" + id;
        }
    }
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
