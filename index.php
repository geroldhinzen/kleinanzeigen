<?php
/**
 * Hauptseite zur Erstellung neuer Artikel
 */

// Session starten
session_start();

// Konfiguration und Funktionen laden
require_once 'config/functions.php';

// Erstelle Uploads-Verzeichnis, falls nicht vorhanden
createDirectoryIfNotExists(UPLOADS_DIR);

// Erstelle Verzeichnis für Badges, falls nicht vorhanden
$pdfsDir = 'pdfs';
if (!file_exists($pdfsDir)) {
    mkdir($pdfsDir, 0777, true);
}

// Seitentitel festlegen
$pageTitle = 'Neuer Artikel';

// Initialisieren von Variablen
$title = $description = '';
$preis = $versandpreis = 0;
$status = 'Entwurf';
$textDescription = $textMail = '';
$betragMitPaypal = 0;
$selectedShippingMethodId = null;
$shippingMethodPrice = 0;

// Datenbank-Verbindung herstellen
$dbConnection = getDbConnection();

// Alle Versandmethoden laden
$shippingMethods = getShippingMethods($dbConnection);

// Formularverarbeitung
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    
    if ($_POST['action'] == 'generate') {
        // Formulardaten verarbeiten und Text generieren
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $preis = floatval($_POST['preis']);
        $versandpreis = floatval($_POST['versandpreis']);
        $status = $_POST['status'];
        $selectedShippingMethodId = isset($_POST['shipping_method_id']) ? intval($_POST['shipping_method_id']) : null;
        
        // Versandmethode abrufen, wenn vorhanden
        if ($selectedShippingMethodId) {
            $selectedMethod = getShippingMethodById($dbConnection, $selectedShippingMethodId);
            if ($selectedMethod) {
                $shippingMethodPrice = $selectedMethod['price'];
            }
        }
        
        // Texte generieren mit Konfigurationswerten
        $textDescription = generateDescription($dbConnection, $description);
        $textMail = generateMailText($dbConnection, $preis, $shippingMethodPrice);
        $betragMitPaypal = calculatePayPalTotal($preis, $shippingMethodPrice);
        
    } elseif ($_POST['action'] == 'save') {
        // Artikel in Datenbank speichern
        $title = trim($_POST['title']);
        $textDescription = trim($_POST['textDescription']);
        $preis = floatval($_POST['preis']);
        $versandpreis = floatval($_POST['versandpreis']);
        $textMail = trim($_POST['textMail']);
        $status = $_POST['status'];
        $selectedShippingMethodId = isset($_POST['shipping_method_id']) ? intval($_POST['shipping_method_id']) : null;
        
        // Ordnernamen für Artikelbilder erstellen
        $folderName = createFolderName($title);
        $articleFolder = UPLOADS_DIR . '/' . $folderName;
        
        // Ordner erstellen, falls nicht vorhanden
        createDirectoryIfNotExists($articleFolder);
        
        // Artikel in Datenbank speichern
        $stmt = $dbConnection->prepare("INSERT INTO articles (title, description, price, shipping_price, shipping_method_id, mail_text, status, image_folder) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssddisss", $title, $textDescription, $preis, $versandpreis, $selectedShippingMethodId, $textMail, $status, $folderName);
        
        if ($stmt->execute()) {
            $articleId = $dbConnection->insert_id;
            $stmt->close();
            
            // Bilder verarbeiten und hochladen
            $uploadedImages = processUploadedImages($_FILES, $articleId, $folderName);
            
            // Badge für den Artikel generieren
            generateArticlePdf($articleId, $title, $preis, $dbConnection);
            
            // Erfolgsmeldung setzen und zur Übersicht weiterleiten
            $_SESSION['success_message'] = "Artikel wurde erfolgreich gespeichert!";
            header("Location: overview.php");
            exit();
        } else {
            $_SESSION['error_message'] = "Fehler beim Speichern des Artikels: " . $dbConnection->error;
        }
    }
}

// Header einbinden
include 'includes/header.php';
?>

<?php if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'generate'): ?>
    <!-- Formular mit generiertem Text anzeigen -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h2 class="fs-4 mb-0">Artikel Details</h2>
        </div>
        <div class="card-body">
            <form action="index.php" method="post" enctype="multipart/form-data" class="custom-form">
                <input type="hidden" name="action" value="save">
                
                <div class="row mb-3">
                    <div class="col-md-8">
                        <label for="title" class="form-label">Titel:</label>
                        <input type="text" id="title" name="title" class="form-control" value="<?php echo htmlspecialchars($title); ?>" required maxlength="60" data-char-count="titleCounter">
                        <small id="titleCounter" class="form-text text-muted mt-1"></small>
                    </div>
                    <div class="col-md-4">
                        <label for="status" class="form-label">Status:</label>
                        <select id="status" name="status" class="form-select" required>
                            <option value="Entwurf" <?php echo $status == 'Entwurf' ? 'selected' : ''; ?>>Entwurf</option>
                            <option value="online" <?php echo $status == 'online' ? 'selected' : ''; ?>>Online</option>
                            <option value="verkauft" <?php echo $status == 'verkauft' ? 'selected' : ''; ?>>Verkauft</option>
                        </select>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="preis" class="form-label">Preis:</label>
                        <div class="input-group">
                            <input type="number" step="0.01" id="preis" name="preis" class="form-control" value="<?php echo htmlspecialchars($preis); ?>" required>
                            <span class="input-group-text">€</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label for="shipping_method_id" class="form-label">Versandmethode:</label>
                        <select id="shipping_method_id" name="shipping_method_id" class="form-select">
                            <option value="">-- Bitte wählen --</option>
                            <?php foreach ($shippingMethods as $method): ?>
                                <option value="<?php echo $method['id']; ?>" 
                                    <?php echo ($selectedShippingMethodId == $method['id']) ? 'selected' : ''; ?>
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
                            <input type="number" step="0.01" id="versandpreis" name="versandpreis" class="form-control" value="<?php echo htmlspecialchars($versandpreis); ?>" required>
                            <span class="input-group-text">€</span>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="formular-box">
                        <strong>Gesamtbetrag PayPal:</strong><br>
                        <?php echo $preis; ?> € * 1,029 + 0,35 € + <?php echo $shippingMethodPrice; ?> € (Versand) = <strong><?php echo number_format($betragMitPaypal, 2, ',', '.'); ?> €</strong>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="textDescription" class="form-label">Beschreibung:</label>
                    <textarea class="form-control large" id="textDescription" name="textDescription" required maxlength="4000" data-char-count="descriptionCounter"><?php echo htmlspecialchars($textDescription); ?></textarea>
                    <div class="d-flex justify-content-between align-items-center mt-1">
                        <small id="descriptionCounter" class="form-text text-muted"></small>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-action="copy" data-target="textDescription">
                            <i class="bi bi-clipboard"></i> Beschreibung kopieren
                        </button>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="textMail" class="form-label">Mail-Text:</label>
                    <textarea class="form-control large" id="textMail" name="textMail" required><?php echo htmlspecialchars($textMail); ?></textarea>
                    <div class="d-grid d-md-flex justify-content-md-end mt-1">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-action="copy" data-target="textMail">
                            <i class="bi bi-clipboard"></i> Mail-Text kopieren
                        </button>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="images" class="form-label">Bilder hochladen:</label>
                    <input type="file" id="images" name="images[]" class="form-control" multiple accept="image/*">
                    <div class="form-text">Wähle ein oder mehrere Bilder aus. Die Bilder werden automatisch optimiert.</div>
                </div>
                
                <div class="d-flex flex-wrap justify-content-between mt-4">
                    <a href="index.php" class="btn btn-secondary mb-2 mb-md-0">
                        <i class="bi bi-arrow-left"></i> Zurück zum Formular
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Speichern
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php else: ?>
    <!-- Formular für Neuen Artikel anzeigen -->
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h2 class="fs-4 mb-0">Neuen Artikel anlegen</h2>
        </div>
        <div class="card-body">
            <form action="index.php" method="post" class="custom-form">
                <input type="hidden" name="action" value="generate">
                
                <div class="mb-3">
                    <label for="title" class="form-label">Titel:</label>
                    <input type="text" id="title" name="title" class="form-control" placeholder="Artikeltitel" value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>" required maxlength="60" data-char-count="titleCounter">
                    <small id="titleCounter" class="form-text text-muted mt-1"></small>
                </div>
                
                <div class="mb-3">
                    <label for="description" class="form-label">Beschreibung:</label>
                    <textarea id="description" name="description" class="form-control" placeholder="Artikelbeschreibung" required maxlength="4000" data-char-count="descriptionCounter"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                    <small id="descriptionCounter" class="form-text text-muted mt-1"></small>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="preis" class="form-label">Preis:</label>
                        <div class="input-group">
                            <input type="number" step="0.01" id="preis" name="preis" class="form-control" placeholder="0.00" value="<?php echo isset($_POST['preis']) ? htmlspecialchars($_POST['preis']) : ''; ?>" required>
                            <span class="input-group-text">€</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label for="shipping_method_id" class="form-label">Versandmethode:</label>
                        <select id="shipping_method_id" name="shipping_method_id" class="form-select">
                            <option value="">-- Bitte wählen --</option>
                            <?php foreach ($shippingMethods as $method): ?>
                                <option value="<?php echo $method['id']; ?>" data-price="<?php echo $method['price']; ?>">
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
                            <input type="number" step="0.01" id="versandpreis" name="versandpreis" class="form-control" placeholder="0.00" value="<?php echo isset($_POST['versandpreis']) ? htmlspecialchars($_POST['versandpreis']) : ''; ?>" required>
                            <span class="input-group-text">€</span>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="status" class="form-label">Status:</label>
                    <select id="status" name="status" class="form-select" required>
                        <option value="Entwurf" selected>Entwurf</option>
                        <option value="online">Online</option>
                        <option value="verkauft">Verkauft</option>
                    </select>
                </div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> Text generieren
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

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