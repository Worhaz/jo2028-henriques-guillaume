<?php
session_start();
require_once("../../../database/database.php");

// Vérifiez si l'utilisateur est connecté
if (!isset($_SESSION['login'])) {
    header('Location: ../../../index.php');
    exit();
}

// Vérifiez si les deux ID du résultat (athlète et épreuve) sont fournis dans l'URL
if (!isset($_GET['id_athlete']) || !isset($_GET['id_epreuve'])) {
    $_SESSION['error'] = "ID du résultat manquant.";
    header("Location: manage-results.php");
    exit();
} else {
    $id_athlete = filter_input(INPUT_GET, 'id_athlete', FILTER_VALIDATE_INT);
    $id_epreuve = filter_input(INPUT_GET, 'id_epreuve', FILTER_VALIDATE_INT);

    // Vérifiez si les deux ID sont des entiers valides
    if ($id_athlete === false || $id_epreuve === false) {
        $_SESSION['error'] = "ID du résultat invalide.";
        header("Location: manage-results.php");
        exit();
    } else {
        try {
            // Aucune autre table ne dépend de PARTICIPER : le résultat peut être supprimé sans vérification préalable
            // Préparez la requête SQL pour supprimer le résultat
            $sql = "DELETE FROM PARTICIPER WHERE id_athlete = :id_athlete AND id_epreuve = :id_epreuve";
            // Exécutez la requête SQL avec les paramètres
            $statement = $connexion->prepare($sql);
            $statement->bindParam(':id_athlete', $id_athlete, PDO::PARAM_INT);
            $statement->bindParam(':id_epreuve', $id_epreuve, PDO::PARAM_INT);
            $statement->execute();

            // Message de succès, ou d'erreur si aucun résultat ne correspond à ces ID
            if ($statement->rowCount() > 0) {
                $_SESSION['success'] = "Le résultat a été supprimé avec succès.";
            } else {
                $_SESSION['error'] = "Résultat non trouvé.";
            }

            // Redirigez vers la page précédente après la suppression
            header('Location: manage-results.php');
            exit();
        } catch (PDOException $e) {
            $_SESSION['error'] = "Erreur lors de la suppression du résultat : " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            header('Location: manage-results.php');
            exit();
        }
    }
}

// Afficher les erreurs en PHP (fonctionne à condition d’avoir activé l’option en local)
error_reporting(E_ALL);
ini_set("display_errors", 1);
?>
