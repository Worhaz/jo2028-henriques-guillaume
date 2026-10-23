<?php
session_start();
require_once("../../../database/database.php");

// Vérifiez si l'utilisateur est connecté
if (!isset($_SESSION['login'])) {
    header('Location: ../../../index.php');
    exit();
}

// Vérifiez si l'ID de l'administrateur est fourni dans l'URL
if (!isset($_GET['id_admin'])) {
    $_SESSION['error'] = "ID de l'administrateur manquant.";
    header("Location: manage-users.php");
    exit();
} else {
    $id_admin = filter_input(INPUT_GET, 'id_admin', FILTER_VALIDATE_INT);

    // Vérifiez si l'ID de l'administrateur est un entier valide
    if ($id_admin === false) {
        $_SESSION['error'] = "ID de l'administrateur invalide.";
        header("Location: manage-users.php");
        exit();
    } elseif ($id_admin == $_SESSION['id_admin']) {
        // Un administrateur ne peut pas supprimer son propre compte
        $_SESSION['error'] = "Vous ne pouvez pas supprimer votre propre compte.";
        header("Location: manage-users.php");
        exit();
    } else {
        try {
            // Préparez la requête SQL pour supprimer l'administrateur
            $sql = "DELETE FROM ADMINISTRATEUR WHERE id_admin = :id_admin";
            // Exécutez la requête SQL avec le paramètre
            $statement = $connexion->prepare($sql);
            $statement->bindParam(':id_admin', $id_admin, PDO::PARAM_INT);
            $statement->execute();

            // Message de succès, ou d'erreur si aucun administrateur ne correspond à cet ID
            if ($statement->rowCount() > 0) {
                $_SESSION['success'] = "L'administrateur a été supprimé avec succès.";
            } else {
                $_SESSION['error'] = "Administrateur non trouvé.";
            }

            // Redirigez vers la page précédente après la suppression
            header('Location: manage-users.php');
            exit();
        } catch (PDOException $e) {
            $_SESSION['error'] = "Erreur lors de la suppression de l'administrateur : " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            header('Location: manage-users.php');
            exit();
        }
    }
}

// Afficher les erreurs en PHP (fonctionne à condition d’avoir activé l’option en local)
error_reporting(E_ALL);
ini_set("display_errors", 1);
?>
