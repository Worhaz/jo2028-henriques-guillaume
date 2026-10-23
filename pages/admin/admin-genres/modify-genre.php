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

// Vérifiez si l'ID du genre est fourni dans l'URL
if (!isset($_GET['id_genre'])) {
    $_SESSION['error'] = "ID du genre manquant.";
    header("Location: manage-genres.php");
    exit();
}

$id_genre = filter_input(INPUT_GET, 'id_genre', FILTER_VALIDATE_INT);

// Vérifiez si l'ID du genre est un entier valide
if (!$id_genre && $id_genre !== 0) {
    $_SESSION['error'] = "ID du genre invalide.";
    header("Location: manage-genres.php");
    exit();
}

// Vider les messages de succès précédents
if (isset($_SESSION['success'])) {
    unset($_SESSION['success']);
}

// Récupérez les informations du genre pour affichage dans le formulaire
try {
    $queryGenre = "SELECT nom_genre FROM GENRE WHERE id_genre = :idGenre";
    $statementGenre = $connexion->prepare($queryGenre);
    $statementGenre->bindParam(":idGenre", $id_genre, PDO::PARAM_INT);
    $statementGenre->execute();

    if ($statementGenre->rowCount() > 0) {
        $genre = $statementGenre->fetch(PDO::FETCH_ASSOC);
    } else {
        $_SESSION['error'] = "Genre non trouvé.";
        header("Location: manage-genres.php");
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur de base de données : " . $e->getMessage();
    header("Location: manage-genres.php");
    exit();
}

// Vérifiez si le formulaire est soumis
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // trim() supprime les espaces au début et à la fin de la saisie ; ?? '' donne une chaîne vide si le champ n'a pas été envoyé
    $nomGenre = trim($_POST['nomGenre'] ?? '');

    // Vérification du token CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['error'] = "Token CSRF invalide.";
        header("Location: modify-genre.php?id_genre=$id_genre");
        exit();
    }

    // Vérifiez si le champ est vide
    if (empty($nomGenre)) {
        $_SESSION['error'] = "Le champ doit être rempli.";
        header("Location: modify-genre.php?id_genre=$id_genre");
        exit();
    }

    try {
        // Vérifiez si un autre genre porte déjà ce nom
        $queryCheck = "SELECT id_genre FROM GENRE WHERE nom_genre = :nomGenre AND id_genre <> :idGenre";
        $statementCheck = $connexion->prepare($queryCheck);
        $statementCheck->bindParam(":nomGenre", $nomGenre, PDO::PARAM_STR);
        $statementCheck->bindParam(":idGenre", $id_genre, PDO::PARAM_INT);
        $statementCheck->execute();

        if ($statementCheck->rowCount() > 0) {
            $_SESSION['error'] = "Le genre existe déjà.";
            header("Location: modify-genre.php?id_genre=$id_genre");
            exit();
        }

        // Requête pour mettre à jour le genre
        $query = "UPDATE GENRE SET nom_genre = :nomGenre WHERE id_genre = :idGenre";
        $statement = $connexion->prepare($query);
        $statement->bindParam(":nomGenre", $nomGenre, PDO::PARAM_STR);
        $statement->bindParam(":idGenre", $id_genre, PDO::PARAM_INT);

        // Exécutez la requête
        if ($statement->execute()) {
            $_SESSION['success'] = "Le genre a été modifié avec succès.";
            header("Location: manage-genres.php");
            exit();
        } else {
            $_SESSION['error'] = "Erreur lors de la modification du genre.";
            header("Location: modify-genre.php?id_genre=$id_genre");
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Erreur de base de données : " . $e->getMessage();
        header("Location: modify-genre.php?id_genre=$id_genre");
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
    <title>Modifier un Genre - Jeux Olympiques - Los Angeles 2028</title>
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
        <h1>Modifier un Genre</h1>

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

        <form action="modify-genre.php?id_genre=<?php echo $id_genre; ?>" method="post"
            onsubmit="return confirm('Êtes-vous sûr de vouloir modifier ce genre?')">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <label for="nomGenre">Nom du genre :</label>
            <input type="text" name="nomGenre" id="nomGenre"
                value="<?php echo htmlspecialchars($genre['nom_genre']); ?>" required>
            <input type="submit" value="Modifier le Genre">
        </form>

        <p class="paragraph-link">
            <a class="link-home" href="manage-genres.php">Retour à la gestion des genres</a>
        </p>
    </main>

    <footer>
        <figure>
            <img src="../../../img/logo-jo.png" alt="logo Jeux Olympiques - Los Angeles 2028">
        </figure>
    </footer>
</body>

</html>
