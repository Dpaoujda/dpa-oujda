<?php
// Function to handle file upload
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

function uploadFile($file) {
    $targetDir = "uploads/";

    // Generate a unique name for the file using uniqid() and the current timestamp
    $uniqueName = uniqid(time() . "_", true) . '.' . strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));

    // Create the target file path with the unique name
    $targetFile = $targetDir . $uniqueName;
    $fileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
    $message = ""; // Initialize message variable to avoid undefined variable notice

    // Check if the file is an Excel file
    if ($fileType != "xlsx" && $fileType != "xls") {
        $message = "❌ Type de fichier invalide. Seuls les fichiers Excel sont autorisés.";
        return $message;
    }

    // Attempt to move the uploaded file to the server's upload directory
    if (move_uploaded_file($file["tmp_name"], $targetFile)) {
        $message = "✅ Le fichier " . basename($file["name"]) . " a été téléchargé.";

        // Define the full path to Python executable
        $pythonPath = "C:\\Python312\\python.exe";  // Make sure this path is correct for your setup
        
        // Execute the Python script to process the uploaded Excel file
        $command = escapeshellcmd("$pythonPath process_excel.py " . escapeshellarg(realpath($targetFile)) . " 2>&1");
        $output = shell_exec($command);
        
        // Check if there's output from the Python script
        if (empty($output)) {
            // If the output is empty, assume the script ran successfully
            $message .= "<pre>✅ Script Python exécuté avec succès. Données insérées.</pre>";
        } else {
            // If there is any output, assume an error occurred in the Python script
            $message .= "<pre>❌ Erreur d'exécution du script Python: $output</pre>";
        }

        return $message;
    } else {
        $message = "❌ Désolé, il y a eu une erreur lors du téléchargement de votre fichier.";
        return $message;
    }
}

// Handle the file upload if the form is submitted
$message = ""; // Initialize message variable
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["excelFile"])) {
    $file = $_FILES["excelFile"];
    
    // Call the function to upload the file and process it
    $message = uploadFile($file);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Excel File</title>
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
        .form-container {
            width: 60%;
            margin: 0 auto;
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            margin-top: 60px;
        }
        .form-control {
            margin-bottom: 20px;
        }
        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
            border-color: #bee5eb;
            width: 50%;
            margin-left: auto;
            margin-right: auto;
            margin-top: 20px;
        }
        .footer {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            text-align: center;
            font-size: 14px;
            color: #777;
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

<div class="form-container">
    <h2>Importer un fichier Excel</h2>
    <form action="upload.php" method="post" enctype="multipart/form-data">
        <label for="excelFile" class="form-label">Sélectionnez un fichier Excel (.xlsx/.xls):</label>
        <input type="file" name="excelFile" class="form-control" accept=".xls, .xlsx" required>
        
        <!-- Icon and Button placed in a flex container -->
        <div class="d-flex align-items-center justify-content-center mt-3">
            <button type="submit" class="btn btn-success"><i class="fas fa-upload"></i> Importer</button>
        </div>
    </form>

    <?php if ($message): ?>
        <div class="alert alert-info mt-4">
            <strong>Info:</strong> <?= $message ?>
        </div>
    <?php endif; ?>
</div>


<div class="footer">
    <p>© 2025 DPA D'OUJDA. Tous droits réservés.</p>
</div>

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
