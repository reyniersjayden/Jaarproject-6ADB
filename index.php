<?php
session_start();
require "database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION["user_id"];
$username = $_SESSION["username"] ?? "Gebruiker";

if (isset($_GET["weekzoek"])) {
    try {
        $gekozenDatum = new DateTime($_GET["weekzoek"]);
    } catch (Exception $e) {
        $gekozenDatum = new DateTime();
    }

    $huidige_week = (int)$gekozenDatum->format("W");
    $huidig_jaar = (int)$gekozenDatum->format("o");
} else {
    $huidige_week = isset($_GET["week"]) ? (int)$_GET["week"] : (int)date("W");
    $huidig_jaar = isset($_GET["jaar"]) ? (int)$_GET["jaar"] : (int)date("o");
}

$startWeek = new DateTime();
$startWeek->setISODate($huidig_jaar, $huidige_week);
$startWeek->setTime(0, 0, 0);

$eindeWeek = clone $startWeek;
$eindeWeek->modify("+6 days");

$vorigeWeek = clone $startWeek;
$vorigeWeek->modify("-7 days");

$volgendeWeek = clone $startWeek;
$volgendeWeek->modify("+7 days");

$dagenNL = ["Ma", "Di", "Wo", "Do", "Vr", "Za", "Zo"];
$weekdagen = [];
$temp = clone $startWeek;

for ($i = 0; $i < 7; $i++) {
    $weekdagen[] = [
        "dag_kort" => $dagenNL[$i],
        "db" => $temp->format("Y-m-d"),
        "dag_nummer" => $temp->format("d"),
        "maand_nummer" => $temp->format("m"),
        "toon" => $temp->format("d/m/Y")
    ];

    $temp->modify("+1 day");
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add"])) {
    $stmt = $conn->prepare("INSERT INTO tasks (user_id, datum, tijd, taak, beschrijving) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $user_id, $_POST["datum"], $_POST["tijd"], $_POST["taak"], $_POST["beschrijving"]);
    $stmt->execute();

    header("Location: index.php?week=$huidige_week&jaar=$huidig_jaar");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["edit"])) {
    $stmt = $conn->prepare("UPDATE tasks SET taak = ?, tijd = ?, beschrijving = ? WHERE id = ? AND user_id = ? AND done = 0");
    $stmt->bind_param("sssii", $_POST["taak"], $_POST["tijd"], $_POST["beschrijving"], $_POST["id"], $user_id);
    $stmt->execute();

    header("Location: index.php?week=$huidige_week&jaar=$huidig_jaar");
    exit;
}

if (isset($_GET["done"])) {
    $doneId = (int)$_GET["done"];

    $stmt = $conn->prepare("UPDATE tasks SET done = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $doneId, $user_id);
    $stmt->execute();

    header("Location: index.php?week=$huidige_week&jaar=$huidig_jaar");
    exit;
}

if (isset($_GET["delete"])) {
    $deleteId = (int)$_GET["delete"];

    $stmt = $conn->prepare("DELETE FROM tasks WHERE id = ? AND user_id = ? AND done = 0");
    $stmt->bind_param("ii", $deleteId, $user_id);
    $stmt->execute();

    header("Location: index.php?week=$huidige_week&jaar=$huidig_jaar");
    exit;
}

$startStr = $startWeek->format("Y-m-d");
$eindeStr = $eindeWeek->format("Y-m-d");

/* Weektaken */
$stmtWeek = $conn->prepare("
    SELECT * FROM tasks
    WHERE user_id = ?
    AND done = 0
    AND datum BETWEEN ? AND ?
    ORDER BY datum ASC, tijd ASC
");
$stmtWeek->bind_param("iss", $user_id, $startStr, $eindeStr);
$stmtWeek->execute();

$resultWeek = $stmtWeek->get_result();
$tasks = [];

while ($row = $resultWeek->fetch_assoc()) {
    $tasks[$row["datum"]][] = $row;
}

/* Alle openstaande taken */
$stmtAll = $conn->prepare("
    SELECT * FROM tasks
    WHERE user_id = ?
    AND done = 0
    ORDER BY datum ASC, tijd ASC
");
$stmtAll->bind_param("i", $user_id);
$stmtAll->execute();

$resultAll = $stmtAll->get_result();
$allTasks = [];

while ($row = $resultAll->fetch_assoc()) {
    $allTasks[] = $row;
}

/* Voltooide taken */
$stmtDone = $conn->prepare("
    SELECT * FROM tasks
    WHERE user_id = ?
    AND done = 1
    ORDER BY datum DESC, tijd DESC
");
$stmtDone->bind_param("i", $user_id);
$stmtDone->execute();

$resultDone = $stmtDone->get_result();
$doneTasks = [];

while ($row = $resultDone->fetch_assoc()) {
    $doneTasks[] = $row;
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planner</title>

    <link rel="icon" type="image/png" href="IMG%26meer/flavicon%20planning-it.png?v=10">
    <link rel="apple-touch-icon" href="IMG%26meer/flavicon%20planning-it.png?v=10">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=400">
</head>

<body>

<div class="container-fluid px-3 px-md-4 py-4">

    <div class="bg-white rounded-4 shadow-sm p-3 p-md-4 mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-stretch align-items-md-center gap-3">

            <div class="d-grid d-md-flex gap-2" style="grid-template-columns: 1fr 1fr;">
                <button class="btn btn-primary fw-semibold" data-bs-toggle="modal" data-bs-target="#addModal">
                    + Taak toevoegen
                </button>

                <button class="btn btn-outline-primary fw-semibold" data-bs-toggle="modal" data-bs-target="#allTasksModal">
                    Alle taken
                </button>
            </div>

            <div class="d-flex flex-column flex-md-row align-items-stretch align-items-md-center gap-2">
                <div class="btn-group w-100">
                    <a href="?week=<?= (int)$vorigeWeek->format('W') ?>&jaar=<?= (int)$vorigeWeek->format('o') ?>" class="btn btn-outline-primary">←</a>

                    <button class="btn btn-light border fw-semibold flex-grow-1" disabled>
                        <?= $startWeek->format("d/m/Y") ?> - <?= $eindeWeek->format("d/m/Y") ?>
                    </button>

                    <a href="?week=<?= (int)$volgendeWeek->format('W') ?>&jaar=<?= (int)$volgendeWeek->format('o') ?>" class="btn btn-outline-primary">→</a>
                </div>

                <form method="GET" class="d-flex gap-2">
                    <input type="date" name="weekzoek" class="form-control" required>
                    <button class="btn btn-primary fw-semibold">Zoek</button>
                </form>
            </div>

            <div class="dropdown align-self-end align-self-md-auto order-first order-md-last">
                <button class="btn btn-primary rounded-circle fw-bold d-flex align-items-center justify-content-center"
                        type="button"
                        data-bs-toggle="dropdown"
                        aria-expanded="false"
                        style="width: 50px; height: 50px;">
                    <?= strtoupper(substr($username, 0, 1)) ?>
                </button>

                <ul class="dropdown-menu dropdown-menu-end shadow-sm rounded-4 border-0 p-2" style="width: 260px;">
                    <li class="px-3 py-2 fw-bold">
                        <?= htmlspecialchars($username) ?>
                    </li>

                    <li><hr class="dropdown-divider"></li>

                    <li>
                        <button class="dropdown-item rounded-3 fw-semibold"
                                type="button"
                                data-bs-toggle="modal"
                                data-bs-target="#doneTasksModal">
                            Voltooide taken
                        </button>
                    </li>

                    <li><hr class="dropdown-divider"></li>

                    <li>
                        <a class="dropdown-item text-danger rounded-3" href="logout.php">
                            Uitloggen
                        </a>
                    </li>
                </ul>
            </div>

        </div>
    </div>

    <div class="row g-4">
        <?php foreach ($weekdagen as $d): ?>
            <?php $aantalTaken = !empty($tasks[$d["db"]]) ? count($tasks[$d["db"]]) : 0; ?>

            <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
                <a href="#" class="text-decoration-none" data-bs-toggle="modal" data-bs-target="#dayModal<?= $d["db"] ?>">
                    <div class="day-card bg-white rounded-4 shadow-sm p-4 h-100">
                        <div class="d-flex justify-content-between align-items-start mb-4">
                            <span class="badge rounded-pill text-bg-primary px-3 py-2">
                                <?= $d["dag_kort"] ?>
                            </span>

                            <span class="badge rounded-pill <?= $aantalTaken > 0 ? 'text-bg-success' : 'text-bg-light text-muted border' ?>">
                                <?= $aantalTaken ?>
                            </span>
                        </div>

                        <div class="display-5 fw-bold text-dark lh-1">
                            <?= $d["dag_nummer"] ?>
                        </div>

                        <div class="text-muted fw-semibold mb-4">
                            / <?= $d["maand_nummer"] ?>
                        </div>

                        <div class="small text-muted">
                            <?= $aantalTaken === 0 ? "Geen taken gepland" : ($aantalTaken === 1 ? "1 taak gepland" : "$aantalTaken taken gepland") ?>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>

</div>

<?php foreach ($weekdagen as $d): ?>
    <div class="modal fade" id="dayModal<?= $d["db"] ?>" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content rounded-4 border-0">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><?= $d["dag_kort"] ?> <?= $d["toon"] ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <?php if (!empty($tasks[$d["db"]])): ?>
                        <?php foreach ($tasks[$d["db"]] as $task): ?>
                            <div class="task-item p-3 rounded-4 mb-3 d-flex justify-content-between align-items-start"
                                 data-bs-toggle="modal"
                                 data-bs-target="#editModal<?= $task["id"] ?>">

                                <div>
                                    <div class="text-primary fw-bold small mb-1">
                                        <?= date("H:i", strtotime($task["tijd"])) ?>
                                    </div>

                                    <div class="fw-bold">
                                        <?= htmlspecialchars($task["taak"]) ?>
                                    </div>

                                    <?php if (!empty($task["beschrijving"])): ?>
                                        <div class="text-muted small mt-1">
                                            <?= nl2br(htmlspecialchars($task["beschrijving"])) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <a href="?week=<?= $huidige_week ?>&jaar=<?= $huidig_jaar ?>&done=<?= $task["id"] ?>"
                                   class="btn btn-success btn-sm ms-2"
                                   onclick="event.stopPropagation();">
                                    ✔
                                </a>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted text-center my-4">Geen taken voor deze dag.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<div class="modal fade" id="allTasksModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Alle taken overzicht</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <?php if (!empty($allTasks)): ?>
                    <?php foreach ($allTasks as $task): ?>
                        <div class="task-item p-3 rounded-4 mb-3 d-flex justify-content-between align-items-start"
                             data-bs-toggle="modal"
                             data-bs-target="#editModal<?= $task["id"] ?>">

                            <div>
                                <div class="text-primary fw-bold small mb-1">
                                    <?= date("d/m/Y", strtotime($task["datum"])) ?>
                                    om <?= date("H:i", strtotime($task["tijd"])) ?>
                                </div>

                                <div class="fw-bold">
                                    <?= htmlspecialchars($task["taak"]) ?>
                                </div>

                                <?php if (!empty($task["beschrijving"])): ?>
                                    <div class="text-muted small mt-1">
                                        <?= nl2br(htmlspecialchars($task["beschrijving"])) ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <a href="?week=<?= $huidige_week ?>&jaar=<?= $huidig_jaar ?>&done=<?= $task["id"] ?>"
                               class="btn btn-success btn-sm ms-2"
                               onclick="event.stopPropagation();">
                                ✔
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted text-center my-4">Je hebt geen openstaande taken.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="doneTasksModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Voltooide taken</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <?php if (!empty($doneTasks)): ?>
                    <?php foreach ($doneTasks as $task): ?>
                        <div class="task-item p-3 rounded-4 mb-3">
                            <div class="text-primary fw-bold small mb-1">
                                <?= date("d/m/Y", strtotime($task["datum"])) ?>
                                om <?= date("H:i", strtotime($task["tijd"])) ?>
                            </div>

                            <div class="fw-bold text-decoration-line-through text-muted">
                                <?= htmlspecialchars($task["taak"]) ?>
                            </div>

                            <?php if (!empty($task["beschrijving"])): ?>
                                <div class="text-muted small mt-1">
                                    <?= nl2br(htmlspecialchars($task["beschrijving"])) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted text-center my-4">Je hebt nog geen voltooide taken.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php foreach ($allTasks as $task): ?>
    <div class="modal fade" id="editModal<?= $task["id"] ?>" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold">Taak bewerken</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <input type="hidden" name="id" value="<?= $task["id"] ?>">

                        <label class="form-label">Titel</label>
                        <input type="text" name="taak" class="form-control mb-3" value="<?= htmlspecialchars($task["taak"]) ?>" required>

                        <label class="form-label">Uur</label>
                        <input type="time" name="tijd" class="form-control mb-3" value="<?= date("H:i", strtotime($task["tijd"])) ?>" required>

                        <label class="form-label">Beschrijving</label>
                        <textarea name="beschrijving" class="form-control" rows="4"><?= htmlspecialchars($task["beschrijving"]) ?></textarea>
                    </div>

                    <div class="modal-footer justify-content-between">
                        <a href="?week=<?= $huidige_week ?>&jaar=<?= $huidig_jaar ?>&delete=<?= $task["id"] ?>" class="btn btn-outline-danger">
                            Verwijder
                        </a>

                        <button name="edit" class="btn btn-primary">
                            Opslaan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Nieuwe taak toevoegen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <label class="form-label">Datum</label>
                    <input type="date" name="datum" class="form-control mb-3" required>

                    <label class="form-label">Uur</label>
                    <input type="time" name="tijd" class="form-control mb-3" required>

                    <label class="form-label">Titel</label>
                    <input type="text" name="taak" class="form-control mb-3" placeholder="Bijvoorbeeld: Wiskunde leren" required>

                    <label class="form-label">Beschrijving</label>
                    <textarea name="beschrijving" class="form-control" rows="4" placeholder="Extra uitleg of notities"></textarea>
                </div>

                <div class="modal-footer">
                    <button name="add" class="btn btn-primary">
                        Toevoegen
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
