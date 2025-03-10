<?php
require_once 'db_config.php';

if (!isset($_GET['table']) || !isset($_GET['id'])) {
    die("Table ou ID manquant.");
}

$tableName = $_GET['table'];
$id = $_GET['id'];

$conn = getAgricoleConnection();

// 🔥 Récupérer dynamiquement la clé primaire
$primaryKeyResult = $conn->query("SHOW KEYS FROM `$tableName` WHERE Key_name = 'PRIMARY'");
if ($primaryKeyResult->num_rows > 0) {
    $primaryKeyRow = $primaryKeyResult->fetch_assoc();
    $primaryKey = $primaryKeyRow['Column_name'];
} else {
    die("Impossible de trouver une clé primaire.");
}

// Supprimer la ligne avec la bonne clé primaire
$sql = "DELETE FROM `$tableName` WHERE `$primaryKey` = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $id);

if ($stmt->execute()) {
    echo "Suppression réussie.";
} else {
    echo "Erreur lors de la suppression : " . $stmt->error;
}

$conn->close();
header("Location: view_table.php?table=" . urlencode($tableName));
exit;
?>
