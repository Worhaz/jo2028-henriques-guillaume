<?php
session_start();
require_once("../../../database/database.php");

// Vérifiez si l'utilisateur est connecté
if (!isset($_SESSION['login'])) {
    header('Location: ../../../index.php');
    exit();
}

// Vérifiez si l'ID du pays est fourni dans l'URL
if (!isset($_GET['id_pays'])) {
    $_SESSION['error'] = "ID du pays manquant.";
    header("Location: manage-countries.php");
    exit();
} else {
    $id_pays = filter_input(INPUT_GET, 'id_pays', FILTER_VALIDATE_INT);

    // Vérifiez si l'ID du lieu est un entier valide
    if ($id_pays === false) {
        $_SESSION['error'] = "ID du Pays invalide.";
        header("Location: manage-countries.php");
        exit();
    } else {
        try {
            // Vérifiez si le Pays est utilisé par un athlète (clé étrangère id_pays dans la table ATHLETE)
            $queryCheck = "SELECT id_athlete FROM ATHLETE WHERE id_pays = :id_pays";
            $statementCheck = $connexion->prepare($queryCheck);
            $statementCheck->bindParam(':id_pays', $id_pays, PDO::PARAM_INT);
            $statementCheck->execute();

            if ($statementCheck->rowCount() > 0) {
                $_SESSION['error'] = "Ce Pays ne peut pas être supprimé car il est utilisé par un ou plusieurs athlètes.";
                header('Location: manage-countries.php');
                exit();
            }

            // Préparez la requête SQL pour supprimer le pays
            $sql = "DELETE FROM PAYS WHERE id_pays = :id_pays";
            // Exécutez la requête SQL avec le paramètre
            $statement = $connexion->prepare($sql);
            $statement->bindParam(':id_pays', $id_pays, PDO::PARAM_INT);
            $statement->execute();

            // Message de succès, ou d'erreur si aucun pays ne correspond à cet ID
            if ($statement->rowCount() > 0) {
                $_SESSION['success'] = "Le pays a été supprimé avec succès.";
            } else {
                $_SESSION['error'] = "Pays non trouvé.";
            }

            // Redirigez vers la page précédente après la suppression
            header('Location: manage-countries.php');
            exit();
        } catch (PDOException $e) {
            $_SESSION['error'] = "Erreur lors de la suppression du pays : " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            header('Location: manage-countries.php');
            exit();
        }
    }
}

// Afficher les erreurs en PHP (fonctionne à condition d’avoir activé l’option en local)
error_reporting(E_ALL);
ini_set("display_errors", 1);
?>
