<?php
/**
 * Detailseite für einen einzelnen Artikel
 */

// Session starten
session_start();

// Konfiguration und Funktionen laden
require_once 'config/functions.php';

// Datenbank-Verbindung herstellen
$dbConnection = getDbConnection();

// Erstelle Uploads-Verzeichnis, falls nicht vorhanden
createDirectoryIfNotExists(UPLOADS_DIR);

// Erstelle Verzeichnis für Badges, falls nicht vorhanden
$pdfsDir = 'pdfs';
if (!file_exists($pdfsDir)) {
    mkdir($pdfsDir, 0777, true);
}

// Prüfen, ob eine ID übergeben wurde
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "Kein Artikel ausgewählt!";
    header("Location: overview.php");
    exit();
}

$articleId = intval($_GET['id']);

// Alle Versandmethoden laden
$shippingMethods = getShippingMethods($dbConnection);

// Formularverarbeitung
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action']) && $_POST['action'] == 'update') {
        // Artikel aktualisieren
        $title = trim($_POST['title']);
        $textDescription = trim($_POST['textDescription']);
        $preis = floatval($_POST['preis']);
        $versandpreis = floatval($_POST['versandpreis']);
        $textMail = trim($_POST['textMail']);
        $status = $_POST['status'];
        $shippingMethodId = isset($_POST['shipping_method_id']) ? intval($_POST['shipping_method_id']) : null;
        
        // Artikel in Datenbank aktualisieren
        $stmt = $dbConnection->prepare("UPDATE articles SET title = ?, description = ?, price = ?, shipping_price = ?, shipping_method_id = ?, mail_text = ?, status = ? WHERE id = ?");
        $stmt->bind_param("ssddissi", $title, $textDescription, $preis, $versandpreis, $shippingMethodId, $textMail, $status, $articleId);
        $success = $stmt->execute();
        $stmt->close();
        
        if ($success) {
            // Ordnernamen holen
            $query = "SELECT image_folder FROM articles WHERE id = ?";
            $stmt = $dbConnection->prepare($query);
            $stmt->bind_param("i", $articleId);
            $stmt->execute();
            $stmt->bind_result($folderName);
            $stmt->fetch();
            $stmt->close();
            
            // Bilder verarbeiten und hochladen, wenn vorhanden
            if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
                $uploadedImages = processUploadedImages($_FILES, $articleId, $folderName);
            }
            
            // Badge neu generieren oder aktualisieren
            generateArticlePdf($articleId, $title, $preis, $dbConnection);
            
            // Erfolgsmeldung setzen
            $_SESSION['success_message'] = "Artikel wurde erfolgreich aktualisiert!";
            
            // Aktuelle Seite neu laden, um neuen Stand zu zeigen
            header("Location: detail.php?id=" . $articleId);
            exit();
        } else {
            $_SESSION['error_message'] = "Fehler beim Aktualisieren des Artikels: " . $dbConnection->error;
        }
    } elseif (isset($_POST['action']) && $_POST['action'] == 'delete_image' && isset($_POST['image_id'])) {
        // Bild löschen
        $imageId = intval($_POST['image_id']);
        
        // Bildpfad holen
        $query = "SELECT image_path FROM images WHERE id = ? AND article_id = ?";
        $stmt = $dbConnection->prepare($query);
        $stmt->bind_param("ii", $imageId, $articleId);
        $stmt->execute();
        $stmt->bind_result($imagePath);
        $stmt->fetch();
        $stmt->close();
        
        // Datei löschen, falls vorhanden
        if (file_exists($imagePath)) {
            unlink($imagePath);
        }
        
        // Aus Datenbank löschen
        $stmt = $dbConnection->prepare("DELETE FROM images WHERE id = ? AND article_id = ?");
        $stmt->bind_param("ii", $imageId, $articleId);
        $stmt->execute();
        $stmt->close();
        
        // Erfolgsmeldung setzen
        $_SESSION['success_message'] = "Bild wurde erfolgreich gelöscht!";
        
        // Seite neu laden
        header("Location: detail.php?id=" . $articleId);
        exit();
    } elseif (isset($_POST['action']) && $_POST['action'] == 'delete_article') {
        // Artikel löschen
        
        // Zuerst Bildpfade holen
        $query = "SELECT image_path FROM images WHERE article_id = ?";
        $stmt = $dbConnection->prepare($query);
        $stmt->bind_param("i", $articleId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Alle Bilddateien löschen
        while ($row = $result->fetch_assoc()) {
            if (file_exists($row['image_path'])) {
                unlink($row['image_path']);
            }
        }
        $stmt->close();
        
        // Ordnernamen holen
        $query = "SELECT image_folder FROM articles WHERE id = ?";
        $stmt = $dbConnection->prepare($query);
        $stmt->bind_param("i", $articleId);
        $stmt->execute();
        $stmt->bind_result($folderName);
        $stmt->fetch();
        $stmt->close();
        
        $articleFolder = UPLOADS_DIR . '/' . $folderName;
        
        // Badge-Datei löschen, falls vorhanden
        $badgePath = "pdfs/article_" . $articleId . ".html";
        if (file_exists($badgePath)) {
            unlink($badgePath);
        }
        
        // Artikelordner löschen, falls vorhanden
        if (file_exists($articleFolder) && is_dir($articleFolder)) {
            // Versuche, das Verzeichnis zu löschen (nur wenn leer)
            @rmdir($articleFolder);
        }
        
        // Aus Datenbank löschen (Bilder werden über CASCADE gelöscht)
        $stmt = $dbConnection->prepare("DELETE FROM articles WHERE id = ?");
        $stmt->bind_param("i", $articleId);
        $stmt->execute();
        $stmt->close();
        
        // Erfolgsmeldung setzen
        $_SESSION['success_message'] = "Artikel wurde erfolgreich gelöscht!";
        
        // Zur Übersicht weiterleiten
        header("Location: overview.php");
        exit();
    }
}

// Artikeldetails holen
$query = "SELECT a.*, sm.price as shipping_method_price 
          FROM articles a 
          LEFT JOIN shipping_methods sm ON a.shipping_method_id = sm.id 
          WHERE a.id = ?";
$stmt = $dbConnection->prepare($query);
$stmt->bind_param("i", $articleId);
$stmt->execute();
$article = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Falls Artikel nicht existiert, zur Übersicht weiterleiten
if (!$article) {
    $_SESSION['error_message'] = "Artikel nicht gefunden!";
    header("Location: overview.php");
    exit();
}

// Die ausgewählte Versandmethode holen
$selectedShippingMethod = null;
if (!empty($article['shipping_method_id'])) {
    $selectedShippingMethod = getShippingMethodById($dbConnection, $article['shipping_method_id']);
}

// Artikelbilder holen
$query = "SELECT id, image_path FROM images WHERE article_id = ?";
$stmt = $dbConnection->prepare($query);
$stmt->bind_param("i", $articleId);
$stmt->execute();
$imagesResult = $stmt->get_result();
$stmt->close();

// Badge-Pfad holen oder erstellen
$badgePath = getArticlePdfPath($article, $dbConnection);

// Seitentitel festlegen
$pageTitle = 'Artikel: ' . $article['title'];

// Header einbinden
include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Artikel bearbeiten</h2>
    <div>
        <a href="<?php echo $badgePath; ?>" class="btn btn-outline-danger me-2" target="_blank">
            <i class="bi bi-file-earmark-text"></i> Artikel-Badge anzeigen
        </a>
        <a href="overview.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Zurück zur Übersicht
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form action="detail.php?id=<?php echo $articleId; ?>" method="post" enctype="multipart/form-data" class="custom-form" data-form="detail-form">
            <input type="hidden" name="action" value="update">
            
            <div class="article-header">
                <div>
                    <h3 class="h5 mb-2">Status: 
                        <span class="badge <?php 
                            if ($article['status'] == 'Entwurf') echo 'bg-warning text-dark';
                            elseif ($article['status'] == 'online') echo 'bg-success';
                            else echo 'bg-danger';
                        ?>">
                            <?php echo $article['status']; ?>
                        </span>
                    </h3>
                </div>
                <div class="text-muted">
                    <small>Erstellt am: <?php echo date('d.m.Y H:i', strtotime($article['created_at'])); ?></small><br>
                    <small>Letzte Aktualisierung: <?php echo date('d.m.Y H:i', strtotime($article['updated_at'])); ?></small>
                </div>
            </div>

            <?php
// Berechnung der PayPal-Gebühren (nur für den Artikelpreis)
$price = floatval($article['price']);
$shippingPrice = floatval($article['shipping_price']);

// PayPal-Gebühren: 2,49% + 0,35 EUR
$paypalFee = ($price * 0.0249) + 0.35;
$totalWithPaypal = $price + $paypalFee + $shippingPrice;
?>

<!-- PayPal-Preisberechnung Box -->
<div class="card mb-3">
    <div class="card-header bg-light">
        <h5 class="mb-0 fs-6">PayPal-Berechnung</h5>
    </div>
    <div class="card-body">
        <div class="formular-box">
            <div class="row">
                <div class="col-md-6">
                    <strong>Artikelpreis:</strong> <?php echo number_format($price, 2, ',', '.'); ?> €<br>
                    <strong>PayPal-Gebühr (2,49% + 0,35 €):</strong> <?php echo number_format($paypalFee, 2, ',', '.'); ?> €<br>
                    <strong>Versandkosten:</strong> <?php echo number_format($shippingPrice, 2, ',', '.'); ?> €
                </div>
                <div class="col-md-6">
                    <div class="alert alert-info mb-0">
                        <strong>Gesamtbetrag bei PayPal-Zahlung:</strong><br>
                        <?php echo number_format($totalWithPaypal, 2, ',', '.'); ?> €
                    </div>
                </div>
            </div>
        </div>
        <div class="small text-muted mt-2">
            <i class="bi bi-info-circle"></i> Die PayPal-Gebühr wird nur auf den Artikelpreis berechnet, nicht auf die Versandkosten.
        </div>
    </div>
</div>
            
            <div class="row mb-3">
                <div class="col-md-8">
                    <label for="title" class="form-label">Titel:</label>
                    <input type="text" id="title" name="title" class="form-control" value="<?php echo htmlspecialchars($article['title']); ?>" required maxlength="60" data-char-count="titleCounter">
                    <small id="titleCounter" class="form-text text-muted mt-1"></small>
                </div>
                <div class="col-md-4">
                    <label for="status" class="form-label">Status:</label>
                    <select id="status" name="status" class="form-select" required>
                        <option value="Entwurf" <?php echo $article['status'] == 'Entwurf' ? 'selected' : ''; ?>>Entwurf</option>
                        <option value="online" <?php echo $article['status'] == 'online' ? 'selected' : ''; ?>>Online</option>
                        <option value="verkauft" <?php echo $article['status'] == 'verkauft' ? 'selected' : ''; ?>>Verkauft</option>
                    </select>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-4">
                    <label for="preis" class="form-label">Preis:</label>
                    <div class="input-group">
                        <input type="number" step="0.01" id="preis" name="preis" class="form-control" value="<?php echo htmlspecialchars($article['price']); ?>" required>
                        <span class="input-group-text">€</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <label for="shipping_method_id" class="form-label">Versandmethode:</label>
                    <select id="shipping_method_id" name="shipping_method_id" class="form-select">
                        <option value="">-- Bitte wählen --</option>
                        <?php foreach ($shippingMethods as $method): ?>
                            <option value="<?php echo $method['id']; ?>" 
                                <?php echo ($article['shipping_method_id'] == $method['id']) ? 'selected' : ''; ?>
                                data-price="<?php echo $method['price']; ?>">
                                <?php if ($method['provider'] === '' && $method['product_name'] === 'Kein Versand'): ?>
                                    Kein Versand
                                <?php else: ?>
                                    <?php echo $method['provider'] . ' ' . $method['product_name'] . ' ' . $method['max_weight'] . 'kg'; ?>
                                    (<?php echo $method['max_length'] . 'x' . $method['max_width'] . 'x' . $method['max_height']; ?> cm)
                                    - <?php echo number_format($method['price'], 2, ',', '.'); ?> €
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text text-muted mt-1">Bei Auswahl wird der Versandpreis automatisch übernommen</small>
                </div>
                <div class="col-md-4">
                    <label for="versandpreis" class="form-label">Versandpreis:</label>
                    <div class="input-group">
                        <input type="number" step="0.01" id="versandpreis" name="versandpreis" class="form-control" value="<?php echo htmlspecialchars($article['shipping_price']); ?>" required>
                        <span class="input-group-text">€</span>
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="textDescription" class="form-label">Beschreibung:</label>
                <textarea class="form-control large" id="textDescription" name="textDescription" required maxlength="4000" data-char-count="descriptionCounter"><?php echo htmlspecialchars($article['description']); ?></textarea>
                <div class="d-flex justify-content-between align-items-center mt-1">
                    <small id="descriptionCounter" class="form-text text-muted"></small>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-action="copy" data-target="textDescription">
                        <i class="bi bi-clipboard"></i> Beschreibung kopieren
                    </button>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="textMail" class="form-label">Mail-Text:</label>
                <textarea class="form-control large" id="textMail" name="textMail" required><?php echo htmlspecialchars($article['mail_text']); ?></textarea>
                <div class="d-grid d-md-flex justify-content-md-end mt-1">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-action="copy" data-target="textMail">
                        <i class="bi bi-clipboard"></i> Mail-Text kopieren
                    </button>
                </div>
            </div>
            
            <div class="mb-4">
                <h4 class="h5 mb-3">Bilder</h4>
                <div class="images-container">
                    <?php 
                    if ($imagesResult->num_rows > 0) {
                        while ($image = $imagesResult->fetch_assoc()) {
                            echo '<div class="image-item">';
                            echo '<img src="' . htmlspecialchars($image['image_path']) . '" alt="Artikelbild">';
                            echo '<a href="#" class="delete-image-btn" data-bs-toggle="modal" data-bs-target="#deleteImageModal' . $image['id'] . '">';
                            echo '<i class="bi bi-trash"></i>';
                            echo '</a>';
                            echo '</div>';
                        }
                    } else {
                        echo '<div class="alert alert-info">Keine Bilder vorhanden</div>';
                    }
                    ?>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="images" class="form-label">Neue Bilder hochladen:</label>
                <input type="file" id="images" name="images[]" class="form-control" multiple accept="image/*">
                <div class="form-text">Wähle ein oder mehrere Bilder aus. Die Bilder werden automatisch optimiert.</div>
            </div>
            
            <div class="d-flex flex-wrap justify-content-between mt-4">
                <div>
                    <a href="overview.php" class="btn btn-secondary me-2">
                        <i class="bi bi-arrow-left"></i> Zurück zur Übersicht
                    </a>
                    <button type="submit" class="btn btn-primary" id="saveButton">
                        <i class="bi bi-save"></i> Speichern
                    </button>
                    <small class="d-block mt-2 text-muted">Zum Speichern: Klicken oder CMD+S / STRG+S drücken</small>
                </div>
                <button type="button" id="deleteButton" class="btn btn-danger">
                    <i class="bi bi-trash"></i> Artikel löschen
                </button>
            </div>
        </form>
    </div>
</div>

<?php
// Bild-Lösch-Modals außerhalb des Hauptformulars platzieren
if ($imagesResult->num_rows > 0) {
    // Wir müssen das Ergebnis neu abrufen, da wir es bereits durchlaufen haben
    $stmt = $dbConnection->prepare("SELECT id, image_path FROM images WHERE article_id = ?");
    $stmt->bind_param("i", $articleId);
    $stmt->execute();
    $imagesResult = $stmt->get_result();
    $stmt->close();
    
    while ($image = $imagesResult->fetch_assoc()) {
        echo '<div class="modal fade" id="deleteImageModal' . $image['id'] . '" tabindex="-1" aria-hidden="true">';
        echo '<div class="modal-dialog modal-sm">';
        echo '<div class="modal-content">';
        echo '<div class="modal-header">';
        echo '<h5 class="modal-title">Bild löschen</h5>';
        echo '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>';
        echo '</div>';
        echo '<div class="modal-body text-center">';
        echo '<p>Bild wirklich löschen?</p>';
        echo '<img src="' . htmlspecialchars($image['image_path']) . '" alt="Vorschau" style="max-width: 100%; max-height: 150px; margin-bottom: 15px;">';
        echo '<form method="post" action="detail.php?id=' . $articleId . '">';
        echo '<input type="hidden" name="action" value="delete_image">';
        echo '<input type="hidden" name="image_id" value="' . $image['id'] . '">';
        echo '<button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Abbrechen</button>';
        echo '<button type="submit" class="btn btn-danger">Löschen</button>';
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
}
?>

<!-- Bestätigungsdialog für das Löschen des Artikels -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Artikel löschen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <div class="modal-body">
                <p>Möchten Sie diesen Artikel wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                <form method="post" action="detail.php?id=<?php echo $articleId; ?>">
                    <input type="hidden" name="action" value="delete_article">
                    <button type="submit" class="btn btn-danger">Löschen bestätigen</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Toast für erfolgreiches Kopieren -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1050">
    <div id="copyToast" class="toast hide" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="2000">
        <div class="toast-header">
            <i class="bi bi-clipboard-check text-success me-2"></i>
            <strong class="me-auto">Kleinanzeigen</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body">
            Text wurde in die Zwischenablage kopiert!
        </div>
    </div>
</div>

<?php
// Footer einbinden
include 'includes/footer.php';
?>