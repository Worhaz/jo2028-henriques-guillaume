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
    // trim() supprime les espaces au début et à la fin de la saisie ; ?? '' donne une chaîne vide si le champ n'a pas été envoyé
    $nomAthlete = trim($_POST['nomAthlete'] ?? '');
    $prenomAthlete = trim($_POST['prenomAthlete'] ?? '');
    // FILTER_VALIDATE_INT renvoie false si l'identifiant choisi dans la liste n'est pas un entier (null si le champ est absent)
    $idPays = filter_input(INPUT_POST, 'idPays', FILTER_VALIDATE_INT);
    $idGenre = filter_input(INPUT_POST, 'idGenre', FILTER_VALIDATE_INT);

    // Vérification du token CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['error'] = "Token CSRF invalide.";
        header("Location: add-athlete.php");
        exit();
    }

    // Vérifiez si un des champs est vide ou invalide
    if (empty($nomAthlete) || empty($prenomAthlete) || !$idPays || !$idGenre) {
        $_SESSION['error'] = "Tous les champs doivent être remplis.";
        header("Location: add-athlete.php");
        exit();
    }

    try {
        // Vérifiez si l'athlète existe déjà (même nom, même prénom et même pays)
        $queryCheck = "SELECT id_athlete FROM ATHLETE WHERE nom_athlete = :nomAthlete AND prenom_athlete = :prenomAthlete AND id_pays = :idPays";
        $statementCheck = $connexion->prepare($queryCheck);
        $statementCheck->bindParam(":nomAthlete", $nomAthlete, PDO::PARAM_STR);
        $statementCheck->bindParam(":prenomAthlete", $prenomAthlete, PDO::PARAM_STR);
        $statementCheck->bindParam(":idPays", $idPays, PDO::PARAM_INT);
        $statementCheck->execute();

        if ($statementCheck->rowCount() > 0) {
            $_SESSION['error'] = "L'athlète existe déjà.";
            header("Location: add-athlete.php");
            exit();
        } else {
            // Requête pour ajouter un athlète
            $query = "INSERT INTO ATHLETE (nom_athlete, prenom_athlete, id_pays, id_genre) VALUES (:nomAthlete, :prenomAthlete, :idPays, :idGenre)";
            $statement = $connexion->prepare($query);
            $statement->bindParam(":nomAthlete", $nomAthlete, PDO::PARAM_STR);
            $statement->bindParam(":prenomAthlete", $prenomAthlete, PDO::PARAM_STR);
            $statement->bindParam(":idPays", $idPays, PDO::PARAM_INT);
            $statement->bindParam(":idGenre", $idGenre, PDO::PARAM_INT);

            // Exécutez la requête
            if ($statement->execute()) {
                $_SESSION['success'] = "L'athlète a été ajouté avec succès.";
                header("Location: manage-athletes.php");
                exit();
            } else {
                $_SESSION['error'] = "Erreur lors de l'ajout de l'athlète.";
                header("Location: add-athlete.php");
                exit();
            }
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Erreur de base de données : " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        header("Location: add-athlete.php");
        exit();
    }
}

// Récupérez la liste des pays et des genres pour remplir les listes déroulantes du formulaire
try {
    $statementPays = $connexion->prepare("SELECT id_pays, nom_pays FROM PAYS ORDER BY nom_pays");
    $statementPays->execute();
    $listePays = $statementPays->fetchAll(PDO::FETCH_ASSOC);

    $statementGenres = $connexion->prepare("SELECT id_genre, nom_genre FROM GENRE ORDER BY nom_genre");
    $statementGenres->execute();
    $genres = $statementGenres->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur de base de données : " . $e->getMessage();
    header("Location: manage-athletes.php");
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
    <title>Ajouter un Athlète - Jeux Olympiques - Los Angeles 2028</title>
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
        <h1>Ajouter un Athlète</h1>
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
        <form action="add-athlete.php" method="post" onsubmit="return confirm('Êtes-vous sûr de vouloir ajouter cet athlète ?')">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <label for="nomAthlete">Nom :</label>
            <input type="text" name="nomAthlete" id="nomAthlete" required>
            <label for="prenomAthlete">Prénom :</label>
            <input type="text" name="prenomAthlete" id="prenomAthlete" required>
            <label for="idPays">Pays :</label>
            <select name="idPays" id="idPays" required>
                <option value="">-- Choisissez un pays --</option>
                <?php foreach ($listePays as $pays) { ?>
                    <option value="<?php echo $pays['id_pays']; ?>"><?php echo htmlspecialchars($pays['nom_pays'], ENT_QUOTES, 'UTF-8'); ?></option>
                <?php } ?>
            </select>
            <label for="idGenre">Genre :</label>
            <select name="idGenre" id="idGenre" required>
                <option value="">-- Choisissez un genre --</option>
                <?php foreach ($genres as $genre) { ?>
                    <option value="<?php echo $genre['id_genre']; ?>"><?php echo htmlspecialchars($genre['nom_genre'], ENT_QUOTES, 'UTF-8'); ?></option>
                <?php } ?>
            </select>
            <input type="submit" value="Ajouter l'Athlète">
        </form>
        <p class="paragraph-link">
            <a class="link-home" href="manage-athletes.php">Retour à la gestion des athlètes</a>
        </p>
    </main>

    <footer>
        <figure>
            <img src="../../../img/logo-jo.png" alt="logo Jeux Olympiques - Los Angeles 2028">
        </figure>
    </footer>

</body>

</html>
