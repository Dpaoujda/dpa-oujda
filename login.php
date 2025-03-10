<?php
session_start();
require_once 'db_config.php'; // Include database configuration

// Make sure we connect to the user_auth database for login
$conn = getUserAuthConnection();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Check if the email exists in the user_auth database
    $stmt = $conn->prepare("SELECT id, email, password FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($id, $storedEmail, $storedPassword);
    $stmt->fetch();

    // Check if the user exists and verify the password
    if ($stmt->num_rows > 0) {
        // If the user is found, verify the password
        if (password_verify($password, $storedPassword)) {
            // Password is correct
            $_SESSION['user_id'] = $id;
            $_SESSION['email'] = $storedEmail;
            header("Location: index.php"); // Redirect to index page
            exit();
        } else {
            // Password is incorrect
            echo "<div class='alert alert-danger' role='alert'>Invalid email or password!</div>";
        }
    } else {
        // Email not found
        echo "<div class='alert alert-danger' role='alert'>Invalid email or password!</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
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
        .container {
            margin-top: 50px;
            text-align: center;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }
    </style>
</head>
<body>

<header class="header-bg">
    <button class="menu-btn" id="menuToggle"><i class="fas fa-bars"></i></button>
    <h2 class="header-title">DPA D'OUJDA</h2>
</header>

<div class="container mt-5">
    <h1 class="mb-4">Connexion</h1>
    <form method="POST">
        <div class="mb-3">
            <label for="email" class="form-label">Adresse e-mail</label>
            <input type="email" class="form-control" id="email" name="email" placeholder="E-mail" required>
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Mot de passe</label>
            <input type="password" class="form-control" id="password" name="password" placeholder="Mot de passe" required>
        </div>
        <button type="submit" class="btn btn-success w-100">Se connecter</button>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
