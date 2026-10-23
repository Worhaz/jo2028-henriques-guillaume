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
    $idAthlete = filter_input(INPUT_POST, 'idAthlete', FILTER_VALIDATE_INT);
    $idEpreuve = filter_input(INPUT_POST, 'idEpreuve', FILTER_VALIDATE_INT);
    // trim() supprime les espaces au début et à la fin de la saisie ; ?? '' donne une chaîne vide si le champ n'a pas été envoyé
    $resultat = trim($_POST['resultat'] ?? '');

    // Vérification du token CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['error'] = "Token CSRF invalide.";
        header("Location: add-result.php");
        exit();
    }

    // Vérifiez si un des champs est vide ou invalide
    // (on compare le résultat à '' plutôt que d'utiliser empty(), car empty() considère "0" comme vide)
    if (!$idAthlete || !$idEpreuve || $resultat === '') {
        $_SESSION['error'] = "Tous les champs doivent être remplis.";
        header("Location: add-result.php");
        exit();
    }

    // Vérifiez la longueur du résultat (la colonne resultat est limitée à 100 caractères)
    if (mb_strlen($resultat) > 100) {
        $_SESSION['error'] = "Le résultat ne doit pas dépasser 100 caractères.";
        header("Location: add-result.php");
        exit();
    }

    try {
        // Vérifiez si l'athlète a déjà un résultat pour cette épreuve (le couple id_athlete / id_epreuve est la clé primaire)
        $queryCheck = "SELECT id_athlete FROM PARTICIPER WHERE id_athlete = :idAthlete AND id_epreuve = :idEpreuve";
        $statementCheck = $connexion->prepare($queryCheck);
        $statementCheck->bindParam(":idAthlete", $idAthlete, PDO::PARAM_INT);
        $statementCheck->bindParam(":idEpreuve", $idEpreuve, PDO::PARAM_INT);
        $statementCheck->execute();

        if ($statementCheck->rowCount() > 0) {
            $_SESSION['error'] = "Cet athlète a déjà un résultat pour cette épreuve.";
            header("Location: add-result.php");
            exit();
        } else {
            // Requête pour ajouter un résultat
            $query = "INSERT INTO PARTICIPER (id_athlete, id_epreuve, resultat) VALUES (:idAthlete, :idEpreuve, :resultat)";
            $statement = $connexion->prepare($query);
            $statement->bindParam(":idAthlete", $idAthlete, PDO::PARAM_INT);
            $statement->bindParam(":idEpreuve", $idEpreuve, PDO::PARAM_INT);
            $statement->bindParam(":resultat", $resultat, PDO::PARAM_STR);

            // Exécutez la requête
            if ($statement->execute()) {
                $_SESSION['success'] = "Le résultat a été ajouté avec succès.";
                header("Location: manage-results.php");
                exit();
            } else {
                $_SESSION['error'] = "Erreur lors de l'ajout du résultat.";
                header("Location: add-result.php");
                exit();
            }
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Erreur de base de données : " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        header("Location: add-result.php");
        exit();
    }
}

// Récupérez la liste des athlètes et des épreuves pour remplir les listes déroulantes du formulaire
try {
    $queryAthletes = "SELECT at.id_athlete, at.nom_athlete, at.prenom_athlete, pa.nom_pays
                      FROM ATHLETE at
                      INNER JOIN PAYS pa ON at.id_pays = pa.id_pays
                      ORDER BY at.nom_athlete, at.prenom_athlete";
    $statementAthletes = $connexion->prepare($queryAthletes);
    $statementAthletes->execute();
    $athletes = $statementAthletes->fetchAll(PDO::FETCH_ASSOC);

    $queryEpreuves = "SELECT ep.id_epreuve, sp.nom_sport, DATE_FORMAT(ep.date_epreuve, '%d/%m/%Y') AS date, DATE_FORMAT(ep.heure_epreuve, '%H:%i') AS heure, li.nom_lieu
                      FROM EPREUVE ep
                      INNER JOIN SPORT sp ON ep.id_sport = sp.id_sport
                      INNER JOIN LIEU li ON ep.id_lieu = li.id_lieu
                      ORDER BY ep.date_epreuve, ep.heure_epreuve";
    $statementEpreuves = $connexion->prepare($queryEpreuves);
    $statementEpreuves->execute();
    $epreuves = $statementEpreuves->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur de base de données : " . $e->getMessage();
    header("Location: manage-results.php");
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
    <title>Ajouter un Résultat - Jeux Olympiques - Los Angeles 2028</title>
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
        <h1>Ajouter un Résultat</h1>
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
        <form action="add-result.php" method="post" onsubmit="return confirm('Êtes-vous sûr de vouloir ajouter ce résultat ?')">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <label for="idAthlete">Athlète :</label>
            <select name="idAthlete" id="idAthlete" required>
                <option value="">-- Choisissez un athlète --</option>
                <?php foreach ($athletes as $athlete) { ?>
                    <!-- Exemple d'affichage : MARTIN Antoine (France) -->
                    <option value="<?php echo $athlete['id_athlete']; ?>"><?php echo htmlspecialchars($athlete['nom_athlete'] . " " . $athlete['prenom_athlete'] . " (" . $athlete['nom_pays'] . ")", ENT_QUOTES, 'UTF-8'); ?></option>
                <?php } ?>
            </select>
            <label for="idEpreuve">Épreuve :</label>
            <select name="idEpreuve" id="idEpreuve" required>
                <option value="">-- Choisissez une épreuve --</option>
                <?php foreach ($epreuves as $epreuve) { ?>
                    <!-- Exemple d'affichage : 100m - 20/07/2028 14:30 - Los Angeles Memorial Coliseum -->
                    <option value="<?php echo $epreuve['id_epreuve']; ?>"><?php echo htmlspecialchars($epreuve['nom_sport'] . " - " . $epreuve['date'] . " " . $epreuve['heure'] . " - " . $epreuve['nom_lieu'], ENT_QUOTES, 'UTF-8'); ?></option>
                <?php } ?>
            </select>
            <label for="resultat">Résultat :</label>
            <input type="text" name="resultat" id="resultat" maxlength="100" required>
            <input type="submit" value="Ajouter le Résultat">
        </form>
        <p class="paragraph-link">
            <a class="link-home" href="manage-results.php">Retour à la gestion des résultats</a>
        </p>
    </main>

    <footer>
        <figure>
            <img src="../../../img/logo-jo.png" alt="logo Jeux Olympiques - Los Angeles 2028">
        </figure>
    </footer>

</body>

</html>
