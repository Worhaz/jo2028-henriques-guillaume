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

// Vérifiez si l'ID de l'administrateur est fourni dans l'URL
if (!isset($_GET['id_admin'])) {
    $_SESSION['error'] = "ID de l'administrateur manquant.";
    header("Location: manage-users.php");
    exit();
}

$id_admin = filter_input(INPUT_GET, 'id_admin', FILTER_VALIDATE_INT);

// Vérifiez si l'ID de l'administrateur est un entier valide
if (!$id_admin && $id_admin !== 0) {
    $_SESSION['error'] = "ID de l'administrateur invalide.";
    header("Location: manage-users.php");
    exit();
}

// Vider les messages de succès précédents
if (isset($_SESSION['success'])) {
    unset($_SESSION['success']);
}

// Récupérez les informations de l'administrateur pour affichage dans le formulaire (sans le mot de passe)
try {
    $queryAdmin = "SELECT nom_admin, prenom_admin, login FROM ADMINISTRATEUR WHERE id_admin = :idAdmin";
    $statementAdmin = $connexion->prepare($queryAdmin);
    $statementAdmin->bindParam(":idAdmin", $id_admin, PDO::PARAM_INT);
    $statementAdmin->execute();

    if ($statementAdmin->rowCount() > 0) {
        $admin = $statementAdmin->fetch(PDO::FETCH_ASSOC);
    } else {
        $_SESSION['error'] = "Administrateur non trouvé.";
        header("Location: manage-users.php");
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur de base de données : " . $e->getMessage();
    header("Location: manage-users.php");
    exit();
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
        header("Location: modify-user.php?id_admin=$id_admin");
        exit();
    }

    // Vérifiez si le nom, le prénom ou le login est vide (le mot de passe est facultatif en modification)
    if (empty($nomAdmin) || empty($prenomAdmin) || empty($login)) {
        $_SESSION['error'] = "Le nom, le prénom et le login ne peuvent pas être vides.";
        header("Location: modify-user.php?id_admin=$id_admin");
        exit();
    }

    // Vérifiez le format du login (la page de connexion supprime les autres caractères)
    if (!preg_match('/^[A-Za-z0-9._-]+$/', $login)) {
        $_SESSION['error'] = "Le login ne peut contenir que des lettres sans accent, des chiffres, des points, des tirets et des underscores.";
        header("Location: modify-user.php?id_admin=$id_admin");
        exit();
    }

    try {
        // Vérifiez si le login est déjà utilisé par un autre administrateur
        $queryCheck = "SELECT id_admin FROM ADMINISTRATEUR WHERE login = :login AND id_admin <> :idAdmin";
        $statementCheck = $connexion->prepare($queryCheck);
        $statementCheck->bindParam(":login", $login, PDO::PARAM_STR);
        $statementCheck->bindParam(":idAdmin", $id_admin, PDO::PARAM_INT);
        $statementCheck->execute();

        if ($statementCheck->rowCount() > 0) {
            $_SESSION['error'] = "Ce login existe déjà.";
            header("Location: modify-user.php?id_admin=$id_admin");
            exit();
        }

        if (!empty($password)) {
            // Un nouveau mot de passe a été saisi : il est haché avec Bcrypt avant d'être enregistré
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);

            // Requête pour mettre à jour l'administrateur et son mot de passe
            $query = "UPDATE ADMINISTRATEUR SET nom_admin = :nomAdmin, prenom_admin = :prenomAdmin, login = :login, password = :password WHERE id_admin = :idAdmin";
            $statement = $connexion->prepare($query);
            $statement->bindParam(":password", $passwordHash, PDO::PARAM_STR);
        } else {
            // Le champ mot de passe est vide : requête pour mettre à jour l'administrateur en conservant l'ancien mot de passe
            $query = "UPDATE ADMINISTRATEUR SET nom_admin = :nomAdmin, prenom_admin = :prenomAdmin, login = :login WHERE id_admin = :idAdmin";
            $statement = $connexion->prepare($query);
        }
        $statement->bindParam(":nomAdmin", $nomAdmin, PDO::PARAM_STR);
        $statement->bindParam(":prenomAdmin", $prenomAdmin, PDO::PARAM_STR);
        $statement->bindParam(":login", $login, PDO::PARAM_STR);
        $statement->bindParam(":idAdmin", $id_admin, PDO::PARAM_INT);

        // Exécutez la requête
        if ($statement->execute()) {
            $_SESSION['success'] = "L'administrateur a été modifié avec succès.";
            header("Location: manage-users.php");
            exit();
        } else {
            $_SESSION['error'] = "Erreur lors de la modification de l'administrateur.";
            header("Location: modify-user.php?id_admin=$id_admin");
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Erreur de base de données : " . $e->getMessage();
        header("Location: modify-user.php?id_admin=$id_admin");
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
    <title>Modifier un Administrateur - Jeux Olympiques - Los Angeles 2028</title>
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
        <h1>Modifier un Administrateur</h1>

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

        <form action="modify-user.php?id_admin=<?php echo $id_admin; ?>" method="post"
            onsubmit="return confirm('Êtes-vous sûr de vouloir modifier cet administrateur?')">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <label for="nomAdmin">Nom :</label>
            <input type="text" name="nomAdmin" id="nomAdmin"
                value="<?php echo htmlspecialchars($admin['nom_admin']); ?>" required>
            <label for="prenomAdmin">Prénom :</label>
            <input type="text" name="prenomAdmin" id="prenomAdmin"
                value="<?php echo htmlspecialchars($admin['prenom_admin']); ?>" required>
            <label for="login">Login :</label>
            <input type="text" name="login" id="login"
                value="<?php echo htmlspecialchars($admin['login']); ?>" required>
            <label for="password">Nouveau mot de passe (laisser vide pour le conserver) :</label>
            <input type="password" name="password" id="password">
            <input type="submit" value="Modifier l'Administrateur">
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
