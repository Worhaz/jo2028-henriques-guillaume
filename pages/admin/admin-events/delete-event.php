<?php
session_start();
require_once("../../../database/database.php");

// Vérifiez si l'utilisateur est connecté
if (!isset($_SESSION['login'])) {
    header('Location: ../../../index.php');
    exit();
}

// Vérifiez si l'ID de l'épreuve est fourni dans l'URL
if (!isset($_GET['id_epreuve'])) {
    $_SESSION['error'] = "ID de l'épreuve manquant.";
    header("Location: manage-events.php");
    exit();
} else {
    $id_epreuve = filter_input(INPUT_GET, 'id_epreuve', FILTER_VALIDATE_INT);

    // Vérifiez si l'ID de l'épreuve est un entier valide
    if ($id_epreuve === false) {
        $_SESSION['error'] = "ID de l'épreuve invalide.";
        header("Location: manage-events.php");
        exit();
    } else {
        try {
            // Vérifiez si l'épreuve a des résultats (clé étrangère id_epreuve dans la table PARTICIPER)
            $queryCheck = "SELECT id_athlete FROM PARTICIPER WHERE id_epreuve = :id_epreuve";
            $statementCheck = $connexion->prepare($queryCheck);
            $statementCheck->bindParam(':id_epreuve', $id_epreuve, PDO::PARAM_INT);
            $statementCheck->execute();

            if ($statementCheck->rowCount() > 0) {
                $_SESSION['error'] = "Cette épreuve ne peut pas être supprimée car elle a un ou plusieurs résultats.";
                header('Location: manage-events.php');
                exit();
            }

            // Préparez la requête SQL pour supprimer l'épreuve
            $sql = "DELETE FROM EPREUVE WHERE id_epreuve = :id_epreuve";
            // Exécutez la requête SQL avec le paramètre
            $statement = $connexion->prepare($sql);
            $statement->bindParam(':id_epreuve', $id_epreuve, PDO::PARAM_INT);
            $statement->execute();

            // Message de succès, ou d'erreur si aucune épreuve ne correspond à cet ID
            if ($statement->rowCount() > 0) {
                $_SESSION['success'] = "L'épreuve a été supprimée avec succès.";
            } else {
                $_SESSION['error'] = "Épreuve non trouvée.";
            }

            // Redirigez vers la page précédente après la suppression
            header('Location: manage-events.php');
            exit();
        } catch (PDOException $e) {
            $_SESSION['error'] = "Erreur lors de la suppression de l'épreuve : " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            header('Location: manage-events.php');
            exit();
        }
    }
}

// Afficher les erreurs en PHP (fonctionne à condition d’avoir activé l’option en local)
error_reporting(E_ALL);
ini_set("display_errors", 1);
?>
