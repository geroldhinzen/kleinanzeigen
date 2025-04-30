<?php
// Starte eine Session, wenn noch keine aktiv ist
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - Kleinanzeigen' : 'Kleinanzeigen'; ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Eigenes CSS -->
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header class="bg-primary text-white py-3 mb-4">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1 class="mb-0">
                        <a href="overview.php" class="text-white text-decoration-none">Kleinanzeigen</a>
                    </h1>
                </div>
                <div class="col-md-6 text-md-end mt-3 mt-md-0">
                    <a href="overview.php" class="btn btn-outline-light me-2">
                        <i class="bi bi-list"></i> Übersicht
                    </a>
                    <a href="index.php" class="btn btn-light">
                        <i class="bi bi-plus-circle"></i> Neu
                    </a>
                </div>
            </div>
        </div>
    </header>
    
    <main class="container mb-5">
        <?php
        // Erfolgsmeldungen anzeigen
        if (isset($_SESSION['success_message'])) {
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">';
            echo $_SESSION['success_message'];
            echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
            echo '</div>';
            unset($_SESSION['success_message']);
        }
        
        // Fehlermeldungen anzeigen
        if (isset($_SESSION['error_message'])) {
            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
            echo $_SESSION['error_message'];
            echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
            echo '</div>';
            unset($_SESSION['error_message']);
        }
        ?>