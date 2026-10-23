<?php
session_start();
require_once("../../../database/database.php");

// Vérifiez si l'utilisateur est connecté
if (!isset($_SESSION['login'])) {
    header('Location: ../../../index.php');
    exit();
}

// Générer un token CSRF si ce n'est pas déjà fait
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Génère un token CSRF sécurisé
}

// Vérifiez si l'ID de l'épreuve est fourni dans l'URL
if (!isset($_GET['id_epreuve'])) {
    $_SESSION['error'] = "ID de l'épreuve manquant.";
    header("Location: manage-events.php");
    exit();
}

$id_epreuve = filter_input(INPUT_GET, 'id_epreuve', FILTER_VALIDATE_INT);

// Vérifiez si l'ID de l'épreuve est un entier valide
if (!$id_epreuve && $id_epreuve !== 0) {
    $_SESSION['error'] = "ID de l'épreuve invalide.";
    header("Location: manage-events.php");
    exit();
}

// Vider les messages de succès précédents
if (isset($_SESSION['success'])) {
    unset($_SESSION['success']);
}

// Récupérez les informations de l'épreuve, ainsi que les sports et les lieux, pour affichage dans le formulaire
try {
    // L'heure est formatée en HH:MM, le format attendu par le champ <input type="time">
    $queryEpreuve = "SELECT date_epreuve, DATE_FORMAT(heure_epreuve, '%H:%i') AS heure_epreuve, id_lieu, id_sport FROM EPREUVE WHERE id_epreuve = :idEpreuve";
    $statementEpreuve = $connexion->prepare($queryEpreuve);
    $statementEpreuve->bindParam(":idEpreuve", $id_epreuve, PDO::PARAM_INT);
    $statementEpreuve->execute();

    if ($statementEpreuve->rowCount() > 0) {
        $epreuve = $statementEpreuve->fetch(PDO::FETCH_ASSOC);
    } else {
        $_SESSION['error'] = "Épreuve non trouvée.";
        header("Location: manage-events.php");
        exit();
    }

    $statementSports = $connexion->prepare("SELECT id_sport, nom_sport FROM SPORT ORDER BY nom_sport");
    $statementSports->execute();
    $sports = $statementSports->fetchAll(PDO::FETCH_ASSOC);

    $statementLieux = $connexion->prepare("SELECT id_lieu, nom_lieu FROM LIEU ORDER BY nom_lieu");
    $statementLieux->execute();
    $lieux = $statementLieux->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur de base de données : " . $e->getMessage();
    header("Location: manage-events.php");
    exit();
}

// Vérifiez si le formulaire est soumis
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // FILTER_VALIDATE_INT renvoie false si l'identifiant choisi dans la liste n'est pas un entier (null si le champ est absent)
    $idSport = filter_input(INPUT_POST, 'idSport', FILTER_VALIDATE_INT);
    $idLieu = filter_input(INPUT_POST, 'idLieu', FILTER_VALIDATE_INT);
    // trim() supprime les espaces au début et à la fin de la saisie ; ?? '' donne une chaîne vide si le champ n'a pas été envoyé
    $dateEpreuve = trim($_POST['dateEpreuve'] ?? '');
    $heureEpreuve = trim($_POST['heureEpreuve'] ?? '');

    // Vérification du token CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['error'] = "Token CSRF invalide.";
        header("Location: modify-event.php?id_epreuve=$id_epreuve");
        exit();
    }

    // Vérifiez si un des champs est vide ou invalide
    if (!$idSport || !$idLieu || empty($dateEpreuve) || empty($heureEpreuve)) {
        $_SESSION['error'] = "Tous les champs doivent être remplis.";
        header("Location: modify-event.php?id_epreuve=$id_epreuve");
        exit();
    }

    // Vérifiez le format de la date (AAAA-MM-JJ) et que la date existe (pas de 31 février)
    $date = DateTime::createFromFormat('Y-m-d', $dateEpreuve);
    if (!$date || $date->format('Y-m-d') !== $dateEpreuve) {
        $_SESSION['error'] = "La date de l'épreuve est invalide.";
        header("Location: modify-event.php?id_epreuve=$id_epreuve");
        exit();
    }

    // Vérifiez le format de l'heure (HH:MM)
    $heure = DateTime::createFromFormat('H:i', $heureEpreuve);
    if (!$heure || $heure->format('H:i') !== $heureEpreuve) {
        $_SESSION['error'] = "L'heure de l'épreuve est invalide.";
        header("Location: modify-event.php?id_epreuve=$id_epreuve");
        exit();
    }

    try {
        // Vérifiez si une autre épreuve est identique (même sport, même lieu, même date et même heure)
        $queryCheck = "SELECT id_epreuve FROM EPREUVE WHERE id_sport = :idSport AND id_lieu = :idLieu AND date_epreuve = :dateEpreuve AND heure_epreuve = :heureEpreuve AND id_epreuve <> :idEpreuve";
        $statementCheck = $connexion->prepare($queryCheck);
        $statementCheck->bindParam(":idSport", $idSport, PDO::PARAM_INT);
        $statementCheck->bindParam(":idLieu", $idLieu, PDO::PARAM_INT);
        $statementCheck->bindParam(":dateEpreuve", $dateEpreuve, PDO::PARAM_STR);
        $statementCheck->bindParam(":heureEpreuve", $heureEpreuve, PDO::PARAM_STR);
        $statementCheck->bindParam(":idEpreuve", $id_epreuve, PDO::PARAM_INT);
        $statementCheck->execute();

        if ($statementCheck->rowCount() > 0) {
            $_SESSION['error'] = "L'épreuve existe déjà.";
            header("Location: modify-event.php?id_epreuve=$id_epreuve");
            exit();
        }

        // Requête pour mettre à jour l'épreuve
        $query = "UPDATE EPREUVE SET date_epreuve = :dateEpreuve, heure_epreuve = :heureEpreuve, id_lieu = :idLieu, id_sport = :idSport WHERE id_epreuve = :idEpreuve";
        $statement = $connexion->prepare($query);
        $statement->bindParam(":dateEpreuve", $dateEpreuve, PDO::PARAM_STR);
        $statement->bindParam(":heureEpreuve", $heureEpreuve, PDO::PARAM_STR);
        $statement->bindParam(":idLieu", $idLieu, PDO::PARAM_INT);
        $statement->bindParam(":idSport", $idSport, PDO::PARAM_INT);
        $statement->bindParam(":idEpreuve", $id_epreuve, PDO::PARAM_INT);

        // Exécutez la requête
        if ($statement->execute()) {
            $_SESSION['success'] = "L'épreuve a été modifiée avec succès.";
            header("Location: manage-events.php");
            exit();
        } else {
            $_SESSION['error'] = "Erreur lors de la modification de l'épreuve.";
            header("Location: modify-event.php?id_epreuve=$id_epreuve");
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Erreur de base de données : " . $e->getMessage();
        header("Location: modify-event.php?id_epreuve=$id_epreuve");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../../css/normalize.css">
    <link rel="stylesheet" href="../../../css/styles-computer.css">
    <link rel="stylesheet" href="../../../css/styles-responsive.css">
    <link rel="shortcut icon" href="../../../img/favicon.ico" type="image/x-icon">
    <title>Modifier une Épreuve - Jeux Olympiques - Los Angeles 2028</title>
</head>

<body>
    <header>
        <nav>
            <!-- Menu vers les pages d'administration -->
            <ul class="menu">
                <li><a href="../admin.php">Accueil Administration</a></li>
                <li><a href="../admin-users/manage-users.php">Gestion Administrateurs</a></li>
                <li><a href="../admin-sports/manage-sports.php">Gestion Sports</a></li>
                <li><a href="../admin-places/manage-places.php">Gestion Lieux</a></li>
                <li><a href="../admin-countries/manage-countries.php">Gestion Pays</a></li>
                <li><a href="../admin-events/manage-events.php">Gestion Calendrier</a></li>
                <li><a href="../admin-athletes/manage-athletes.php">Gestion Athlètes</a></li>
                <li><a href="../admin-genres/manage-genres.php">Gestion Genres</a></li>
                <li><a href="../admin-results/manage-results.php">Gestion Résultats</a></li>
                <li><a href="../../logout.php">Déconnexion</a></li>
            </ul>
        </nav>
    </header>

    <main>
        <h1>Modifier une Épreuve</h1>

        <!-- Affichage des messages d'erreur ou de succès -->
        <?php
        if (isset($_SESSION['error'])) {
            echo '<p style="color: red;">' . htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8') . '</p>';
            unset($_SESSION['error']);
        }
        if (isset($_SESSION['success'])) {
            echo '<p style="color: green;">' . htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8') . '</p>';
            unset($_SESSION['success']);
        }
        ?>

        <form action="modify-event.php?id_epreuve=<?php echo $id_epreuve; ?>" method="post"
            onsubmit="return confirm('Êtes-vous sûr de vouloir modifier cette épreuve?')">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <label for="idSport">Sport :</label>
            <select name="idSport" id="idSport" required>
                <?php foreach ($sports as $sport) { ?>
                    <!-- Le sport actuel de l'épreuve est présélectionné -->
                    <option value="<?php echo $sport['id_sport']; ?>" <?php if ($sport['id_sport'] == $epreuve['id_sport']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($sport['nom_sport'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php } ?>
            </select>
            <label for="idLieu">Lieu :</label>
            <select name="idLieu" id="idLieu" required>
                <?php foreach ($lieux as $lieu) { ?>
                    <!-- Le lieu actuel de l'épreuve est présélectionné -->
                    <option value="<?php echo $lieu['id_lieu']; ?>" <?php if ($lieu['id_lieu'] == $epreuve['id_lieu']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($lieu['nom_lieu'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php } ?>
            </select>
            <label for="dateEpreuve">Date :</label>
            <input type="date" name="dateEpreuve" id="dateEpreuve"
                value="<?php echo htmlspecialchars($epreuve['date_epreuve']); ?>" required>
            <label for="heureEpreuve">Heure :</label>
            <input type="time" name="heureEpreuve" id="heureEpreuve"
                value="<?php echo htmlspecialchars($epreuve['heure_epreuve']); ?>" required>
            <input type="submit" value="Modifier l'Épreuve">
        </form>

        <p class="paragraph-link">
            <a class="link-home" href="manage-events.php">Retour à la gestion du calendrier</a>
        </p>
    </main>

    <footer>
        <figure>
            <img src="../../../img/logo-jo.png" alt="logo Jeux Olympiques - Los Angeles 2028">
        </figure>
    </footer>
</body>

</html>
