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
    $nomAdmin = trim($_POST['nomAdmin'] ?? '');
    $prenomAdmin = trim($_POST['prenomAdmin'] ?? '');
    $login = filter_input(INPUT_POST, 'login', FILTER_SANITIZE_SPECIAL_CHARS);

    // Ancienne version : encodait l'apostrophe avant l'enregistrement, d'où un double encodage à l'affichage
    // $nomAdmin = filter_input(INPUT_POST, 'nomAdmin', FILTER_SANITIZE_SPECIAL_CHARS);
    // $prenomAdmin = filter_input(INPUT_POST, 'prenomAdmin', FILTER_SANITIZE_SPECIAL_CHARS);


    // Le mot de passe n'est pas filtré : il doit être haché exactement tel qu'il a été saisi
    $password = filter_input(INPUT_POST, 'password', FILTER_UNSAFE_RAW);

    // Vérification du token CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['error'] = "Token CSRF invalide.";
        header("Location: add-user.php");
        exit();
    }

    // Vérifiez si un des champs est vide
    if (empty($nomAdmin) || empty($prenomAdmin) || empty($login) || empty($password)) {
        $_SESSION['error'] = "Tous les champs doivent être remplis.";
        header("Location: add-user.php");
        exit();
    }

    // Vérifiez le format du login (la page de connexion supprime les autres caractères)
    if (!preg_match('/^[A-Za-z0-9._-]+$/', $login)) {
        $_SESSION['error'] = "Le login ne peut contenir que des lettres sans accent, des chiffres, des points, des tirets et des underscores.";
        header("Location: add-user.php");
        exit();
    }

    try {
        // Vérifiez si le login existe déjà
        $queryCheck = "SELECT id_admin FROM ADMINISTRATEUR WHERE login = :login";
        $statementCheck = $connexion->prepare($queryCheck);
        $statementCheck->bindParam(":login", $login, PDO::PARAM_STR);
        $statementCheck->execute();

        if ($statementCheck->rowCount() > 0) {
            $_SESSION['error'] = "Ce login existe déjà.";
            header("Location: add-user.php");
            exit();
        } else {
            // Hachage du mot de passe avec Bcrypt : seul le hash est enregistré en base
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);

            // Requête pour ajouter un administrateur
            $query = "INSERT INTO ADMINISTRATEUR (nom_admin, prenom_admin, login, password) VALUES (:nomAdmin, :prenomAdmin, :login, :password)";
            $statement = $connexion->prepare($query);
            $statement->bindParam(":nomAdmin", $nomAdmin, PDO::PARAM_STR);
            $statement->bindParam(":prenomAdmin", $prenomAdmin, PDO::PARAM_STR);
            $statement->bindParam(":login", $login, PDO::PARAM_STR);
            $statement->bindParam(":password", $passwordHash, PDO::PARAM_STR);

            // Exécutez la requête
            if ($statement->execute()) {
                $_SESSION['success'] = "L'administrateur a été ajouté avec succès.";
                header("Location: manage-users.php");
                exit();
            } else {
                $_SESSION['error'] = "Erreur lors de l'ajout de l'administrateur.";
                header("Location: add-user.php");
                exit();
            }
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Erreur de base de données : " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        header("Location: add-user.php");
        exit();
    }
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
    <title>Ajouter un Administrateur - Jeux Olympiques - Los Angeles 2028</title>
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
        <h1>Ajouter un Administrateur</h1>
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
        <form action="add-user.php" method="post" onsubmit="return confirm('Êtes-vous sûr de vouloir ajouter cet administrateur ?')">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <label for="nomAdmin">Nom :</label>
            <input type="text" name="nomAdmin" id="nomAdmin" required>
            <label for="prenomAdmin">Prénom :</label>
            <input type="text" name="prenomAdmin" id="prenomAdmin" required>
            <label for="login">Login :</label>
            <input type="text" name="login" id="login" required>
            <label for="password">Mot de passe :</label>
            <input type="password" name="password" id="password" required>
            <input type="submit" value="Ajouter l'Administrateur">
        </form>
        <p class="paragraph-link">
            <a class="link-home" href="manage-users.php">Retour à la gestion des administrateurs</a>
        </p>
    </main>

    <footer>
        <figure>
            <img src="../../../img/logo-jo.png" alt="logo Jeux Olympiques - Los Angeles 2028">
        </figure>
    </footer>

</body>

</html>
