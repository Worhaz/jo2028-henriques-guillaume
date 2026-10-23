<?php
session_start();
require_once("../../../database/database.php");

// Vérifiez si l'utilisateur est connecté
if (!isset($_SESSION['login'])) {
    header('Location: ../../../index.php');
    exit();
}

// Vérifiez si l'ID du genre est fourni dans l'URL
if (!isset($_GET['id_genre'])) {
    $_SESSION['error'] = "ID du genre manquant.";
    header("Location: manage-genres.php");
    exit();
} else {
    $id_genre = filter_input(INPUT_GET, 'id_genre', FILTER_VALIDATE_INT);

    // Vérifiez si l'ID du genre est un entier valide
    if ($id_genre === false) {
        $_SESSION['error'] = "ID du genre invalide.";
        header("Location: manage-genres.php");
        exit();
    } else {
        try {
            // Vérifiez si le genre est utilisé par un athlète (clé étrangère id_genre dans la table ATHLETE)
            $queryCheck = "SELECT id_athlete FROM ATHLETE WHERE id_genre = :id_genre";
            $statementCheck = $connexion->prepare($queryCheck);
            $statementCheck->bindParam(':id_genre', $id_genre, PDO::PARAM_INT);
            $statementCheck->execute();

            if ($statementCheck->rowCount() > 0) {
                $_SESSION['error'] = "Ce genre ne peut pas être supprimé car il est utilisé par un ou plusieurs athlètes.";
                header('Location: manage-genres.php');
                exit();
            }

            // Préparez la requête SQL pour supprimer le genre
            $sql = "DELETE FROM GENRE WHERE id_genre = :id_genre";
            // Exécutez la requête SQL avec le paramètre
            $statement = $connexion->prepare($sql);
            $statement->bindParam(':id_genre', $id_genre, PDO::PARAM_INT);
            $statement->execute();

            // Message de succès, ou d'erreur si aucun genre ne correspond à cet ID
            if ($statement->rowCount() > 0) {
                $_SESSION['success'] = "Le genre a été supprimé avec succès.";
            } else {
                $_SESSION['error'] = "Genre non trouvé.";
            }

            // Redirigez vers la page précédente après la suppression
            header('Location: manage-genres.php');
            exit();
        } catch (PDOException $e) {
            $_SESSION['error'] = "Erreur lors de la suppression du genre : " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            header('Location: manage-genres.php');
            exit();
        }
    }
}

// Afficher les erreurs en PHP (fonctionne à condition d’avoir activé l’option en local)
error_reporting(E_ALL);
ini_set("display_errors", 1);
?>
