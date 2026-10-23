<?php
session_start();
require_once("../../../database/database.php");

// Vérifiez si l'utilisateur est connecté
if (!isset($_SESSION['login'])) {
    header('Location: ../../../index.php');
    exit();
}

// Vérifiez si l'ID de l'athlète est fourni dans l'URL
if (!isset($_GET['id_athlete'])) {
    $_SESSION['error'] = "ID de l'athlète manquant.";
    header("Location: manage-athletes.php");
    exit();
} else {
    $id_athlete = filter_input(INPUT_GET, 'id_athlete', FILTER_VALIDATE_INT);

    // Vérifiez si l'ID de l'athlète est un entier valide
    if ($id_athlete === false) {
        $_SESSION['error'] = "ID de l'athlète invalide.";
        header("Location: manage-athletes.php");
        exit();
    } else {
        try {
            // Vérifiez si l'athlète a des résultats (clé étrangère id_athlete dans la table PARTICIPER)
            $queryCheck = "SELECT id_epreuve FROM PARTICIPER WHERE id_athlete = :id_athlete";
            $statementCheck = $connexion->prepare($queryCheck);
            $statementCheck->bindParam(':id_athlete', $id_athlete, PDO::PARAM_INT);
            $statementCheck->execute();

            if ($statementCheck->rowCount() > 0) {
                $_SESSION['error'] = "Cet athlète ne peut pas être supprimé car il a un ou plusieurs résultats.";
                header('Location: manage-athletes.php');
                exit();
            }

            // Préparez la requête SQL pour supprimer l'athlète
            $sql = "DELETE FROM ATHLETE WHERE id_athlete = :id_athlete";
            // Exécutez la requête SQL avec le paramètre
            $statement = $connexion->prepare($sql);
            $statement->bindParam(':id_athlete', $id_athlete, PDO::PARAM_INT);
            $statement->execute();

            // Message de succès, ou d'erreur si aucun athlète ne correspond à cet ID
            if ($statement->rowCount() > 0) {
                $_SESSION['success'] = "L'athlète a été supprimé avec succès.";
            } else {
                $_SESSION['error'] = "Athlète non trouvé.";
            }

            // Redirigez vers la page précédente après la suppression
            header('Location: manage-athletes.php');
            exit();
        } catch (PDOException $e) {
            $_SESSION['error'] = "Erreur lors de la suppression de l'athlète : " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            header('Location: manage-athletes.php');
            exit();
        }
    }
}

// Afficher les erreurs en PHP (fonctionne à condition d’avoir activé l’option en local)
error_reporting(E_ALL);
ini_set("display_errors", 1);
?>
