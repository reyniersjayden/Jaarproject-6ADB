<?php
ini_set('display_errors', 1); // Zet foutmeldingen aan (handig tijdens development)
error_reporting(E_ALL); // Toon alle mogelijke fouten en waarschuwingen

session_start(); // Start de sessie zodat we user_id kunnen gebruiken
require "database.php"; // Laadt de databaseconnectie ($conn)

// Controleert of de gebruiker ingelogd is
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php"); // Stuurt naar login als niet ingelogd
    exit; // Stopt verdere uitvoering
}

// Haalt de ingelogde user_id op uit de sessie
$user_id = $_SESSION["user_id"];

// Array met alle dagen van de week
$dagen = ["Maandag","Dinsdag","Woensdag","Donderdag","Vrijdag","Zaterdag","Zondag"];

/* Toevoegen van een taak */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["toevoegen"])) {

    // Haalt ingevulde gegevens uit het formulier
    $dag = $_POST["dag"];
    $tijd = $_POST["tijd"];
    $taak = $_POST["taak"];

    // Bereidt SQL-query voor om taak op te slaan
    $stmt = $conn->prepare("INSERT INTO tasks (user_id, dag, tijd, taak) VALUES (?, ?, ?, ?)");

    // Bindt de waarden veilig aan de query
    $stmt->bind_param("isss", $user_id, $dag, $tijd, $taak);

    // Voert de query uit (taak wordt opgeslagen)
    $stmt->execute();
}

/* Verwijderen van een taak */
if (isset($_GET["delete"])) {

    // Zet de id om naar integer (extra veiligheid)
    $delete_id = (int)$_GET["delete"];

    // Bereidt query voor om taak te verwijderen (alleen van deze gebruiker)
    $stmt = $conn->prepare("DELETE FROM tasks WHERE id = ? AND user_id = ?");

    // Bindt id en user_id aan de query
    $stmt->bind_param("ii", $delete_id, $user_id);

    // Voert de delete uit
    $stmt->execute();

    // Redirect om te voorkomen dat bij refresh opnieuw verwijderd wordt
    header("Location: index.php");
    exit;
}

/* Taken ophalen */

// Query om alle taken van de gebruiker op te halen
// ORDER BY FIELD zorgt dat dagen in juiste volgorde staan
$stmt = $conn->prepare("SELECT * FROM tasks WHERE user_id = ? ORDER BY FIELD(dag, 'Maandag','Dinsdag','Woensdag','Donderdag','Vrijdag','Zaterdag','Zondag'), tijd");

// Bindt user_id aan de query
$stmt->bind_param("i", $user_id);

// Voert de query uit
$stmt->execute();

// Haalt resultaat op
$result = $stmt->get_result();

// Lege array om taken per dag op te slaan
$tasks = [];

// Loopt door alle resultaten en groepeert per dag
while ($row = $result->fetch_assoc()) {
    $tasks[$row["dag"]][] = $row; // Zet taak onder juiste dag
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Weekplanner</title> <!-- Titel van de pagina -->

    <!-- Laadt Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<!-- Body met lichte achtergrond -->
<body class="bg-light">

<div class="container py-4">
    <h2>Weekplanner</h2> <!-- Titel -->

    <!-- Logout knop -->
    <a href="logout.php" class="btn btn-danger mb-3">Uitloggen</a>

    <!-- Formulier om taak toe te voegen -->
    <form method="POST" class="row g-2 mb-4">
        
        <!-- Dropdown voor dag -->
        <div class="col-md-3">
            <select name="dag" class="form-select" required>
                <?php foreach ($dagen as $dag): ?> <!-- Loopt door alle dagen -->
                    <option><?= $dag ?></option> <!-- Maakt optie per dag -->
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Input voor tijd -->
        <div class="col-md-3">
            <input type="time" name="tijd" class="form-control" required>
        </div>

        <!-- Input voor taak -->
        <div class="col-md-4">
            <input type="text" name="taak" class="form-control" placeholder="Taak" required>
        </div>

        <!-- Submit knop -->
        <div class="col-md-2">
            <button name="toevoegen" class="btn btn-primary w-100">Toevoegen</button>
        </div>
    </form>

    <!-- Weergave van taken per dag -->
    <div class="row">
        <?php foreach ($dagen as $dag): ?> <!-- Loopt door elke dag -->
            
            <div class="col-md-3 mb-4">
                <div class="card">

                    <!-- Header met dagnaam -->
                    <div class="card-header bg-primary text-white text-center">
                        <?= $dag ?>
                    </div>

                    <div class="card-body">

                        <!-- Controleert of er taken zijn voor deze dag -->
                        <?php if (!empty($tasks[$dag])): ?>

                            <!-- Loopt door alle taken van die dag -->
                            <?php foreach ($tasks[$dag] as $task): ?>

                                <div class="d-flex justify-content-between mb-2">
                                    
                                    <!-- Toont tijd en taak -->
                                    <span>
                                        <strong><?= $task["tijd"] ?></strong> - 
                                        <?= htmlspecialchars($task["taak"]) ?> <!-- Veilig tonen -->
                                    </span>

                                    <!-- Delete knop -->
                                    <a href="?delete=<?= $task["id"] ?>" class="btn btn-sm btn-danger">✕</a>
                                </div>

                            <?php endforeach; ?>

                        <?php else: ?>
                            <!-- Als er geen taken zijn -->
                            <p class="text-muted">Geen taken</p>
                        <?php endif; ?>

                    </div>
                </div>
            </div>

        <?php endforeach; ?>
    </div>
</div>

</body>
</html>
