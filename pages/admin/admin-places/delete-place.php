<?php
session_start();
require_once("../../../database/database.php");

// Vérifiez si l'utilisateur est connecté
if (!isset($_SESSION['login'])) {
    header('Location: ../../../index.php');
    exit();
}

// Vérifiez si l'ID du lieu est fourni dans l'URL
if (!isset($_GET['id_lieu'])) {
    $_SESSION['error'] = "ID du lieu manquant.";
    header("Location: manage-places.php");
    exit();
} else {
    $id_lieu = filter_input(INPUT_GET, 'id_lieu', FILTER_VALIDATE_INT);

    // Vérifiez si l'ID du lieu est un entier valide
    if ($id_lieu === false) {
        $_SESSION['error'] = "ID du lieu invalide.";
        header("Location: manage-places.php");
        exit();
    } else {
        try {
            // Vérifiez si le lieu est utilisé par une épreuve (clé étrangère id_lieu dans la table EPREUVE)
            $queryCheck = "SELECT id_epreuve FROM EPREUVE WHERE id_lieu = :id_lieu";
            $statementCheck = $connexion->prepare($queryCheck);
            $statementCheck->bindParam(':id_lieu', $id_lieu, PDO::PARAM_INT);
            $statementCheck->execute();

            if ($statementCheck->rowCount() > 0) {
                $_SESSION['error'] = "Ce lieu ne peut pas être supprimé car il est utilisé par une ou plusieurs épreuves.";
                header('Location: manage-places.php');
                exit();
            }

            // Préparez la requête SQL pour supprimer le lieu
            $sql = "DELETE FROM LIEU WHERE id_lieu = :id_lieu";
            // Exécutez la requête SQL avec le paramètre
            $statement = $connexion->prepare($sql);
            $statement->bindParam(':id_lieu', $id_lieu, PDO::PARAM_INT);
            $statement->execute();

            // Message de succès, ou d'erreur si aucun lieu ne correspond à cet ID
            if ($statement->rowCount() > 0) {
                $_SESSION['success'] = "Le lieu a été supprimé avec succès.";
            } else {
                $_SESSION['error'] = "Lieu non trouvé.";
            }

            // Redirigez vers la page précédente après la suppression
            header('Location: manage-places.php');
            exit();
        } catch (PDOException $e) {
            $_SESSION['error'] = "Erreur lors de la suppression du lieu : " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            header('Location: manage-places.php');
            exit();
        }
    }
}

// Afficher les erreurs en PHP (fonctionne à condition d’avoir activé l’option en local)
error_reporting(E_ALL);
ini_set("display_errors", 1);
?>
