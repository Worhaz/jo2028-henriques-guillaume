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
        header("Location: add-event.php");
        exit();
    }

    // Vérifiez si un des champs est vide ou invalide
    if (!$idSport || !$idLieu || empty($dateEpreuve) || empty($heureEpreuve)) {
        $_SESSION['error'] = "Tous les champs doivent être remplis.";
        header("Location: add-event.php");
        exit();
    }

    // Vérifiez le format de la date (AAAA-MM-JJ) et que la date existe (pas de 31 février)
    $date = DateTime::createFromFormat('Y-m-d', $dateEpreuve);
    if (!$date || $date->format('Y-m-d') !== $dateEpreuve) {
        $_SESSION['error'] = "La date de l'épreuve est invalide.";
        header("Location: add-event.php");
        exit();
    }

    // Vérifiez le format de l'heure (HH:MM)
    $heure = DateTime::createFromFormat('H:i', $heureEpreuve);
    if (!$heure || $heure->format('H:i') !== $heureEpreuve) {
        $_SESSION['error'] = "L'heure de l'épreuve est invalide.";
        header("Location: add-event.php");
        exit();
    }

    try {
        // Vérifiez si l'épreuve existe déjà (même sport, même lieu, même date et même heure)
        $queryCheck = "SELECT id_epreuve FROM EPREUVE WHERE id_sport = :idSport AND id_lieu = :idLieu AND date_epreuve = :dateEpreuve AND heure_epreuve = :heureEpreuve";
        $statementCheck = $connexion->prepare($queryCheck);
        $statementCheck->bindParam(":idSport", $idSport, PDO::PARAM_INT);
        $statementCheck->bindParam(":idLieu", $idLieu, PDO::PARAM_INT);
        $statementCheck->bindParam(":dateEpreuve", $dateEpreuve, PDO::PARAM_STR);
        $statementCheck->bindParam(":heureEpreuve", $heureEpreuve, PDO::PARAM_STR);
        $statementCheck->execute();

        if ($statementCheck->rowCount() > 0) {
            $_SESSION['error'] = "L'épreuve existe déjà.";
            header("Location: add-event.php");
            exit();
        } else {
            // Requête pour ajouter une épreuve
            $query = "INSERT INTO EPREUVE (date_epreuve, heure_epreuve, id_lieu, id_sport) VALUES (:dateEpreuve, :heureEpreuve, :idLieu, :idSport)";
            $statement = $connexion->prepare($query);
            $statement->bindParam(":dateEpreuve", $dateEpreuve, PDO::PARAM_STR);
            $statement->bindParam(":heureEpreuve", $heureEpreuve, PDO::PARAM_STR);
            $statement->bindParam(":idLieu", $idLieu, PDO::PARAM_INT);
            $statement->bindParam(":idSport", $idSport, PDO::PARAM_INT);

            // Exécutez la requête
            if ($statement->execute()) {
                $_SESSION['success'] = "L'épreuve a été ajoutée avec succès.";
                header("Location: manage-events.php");
                exit();
            } else {
                $_SESSION['error'] = "Erreur lors de l'ajout de l'épreuve.";
                header("Location: add-event.php");
                exit();
            }
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Erreur de base de données : " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        header("Location: add-event.php");
        exit();
    }
}

// Récupérez la liste des sports et des lieux pour remplir les listes déroulantes du formulaire
try {
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

// Afficher les erreurs en PHP (fonctionne à condition d’avoir activé l’option en local)
error_reporting(E_ALL);
ini_set("display_errors", 1);
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
    <title>Ajouter une Épreuve - Jeux Olympiques - Los Angeles 2028</title>
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
        <h1>Ajouter une Épreuve</h1>
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
        <form action="add-event.php" method="post" onsubmit="return confirm('Êtes-vous sûr de vouloir ajouter cette épreuve ?')">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <label for="idSport">Sport :</label>
            <select name="idSport" id="idSport" required>
                <option value="">-- Choisissez un sport --</option>
                <?php foreach ($sports as $sport) { ?>
                    <option value="<?php echo $sport['id_sport']; ?>"><?php echo htmlspecialchars($sport['nom_sport'], ENT_QUOTES, 'UTF-8'); ?></option>
                <?php } ?>
            </select>
            <label for="idLieu">Lieu :</label>
            <select name="idLieu" id="idLieu" required>
                <option value="">-- Choisissez un lieu --</option>
                <?php foreach ($lieux as $lieu) { ?>
                    <option value="<?php echo $lieu['id_lieu']; ?>"><?php echo htmlspecialchars($lieu['nom_lieu'], ENT_QUOTES, 'UTF-8'); ?></option>
                <?php } ?>
            </select>
            <label for="dateEpreuve">Date :</label>
            <input type="date" name="dateEpreuve" id="dateEpreuve" required>
            <label for="heureEpreuve">Heure :</label>
            <input type="time" name="heureEpreuve" id="heureEpreuve" required>
            <input type="submit" value="Ajouter l'Épreuve">
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
