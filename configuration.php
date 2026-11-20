<?php
/**
 * Konfigurationsseite für Texte und Einstellungen
 */

// Session starten
session_start();

// Konfiguration und Funktionen laden
require_once 'config/functions.php';

// Datenbank-Verbindung herstellen
$dbConnection = getDbConnection();

// Seitentitel festlegen
$pageTitle = 'Konfiguration';

// Formularverarbeitung
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    if ($_POST['action'] == 'save_config') {
        // Konfigurationen aktualisieren
        $success = true;
        
        // Alle übermittelten Konfigurationen speichern
        foreach ($_POST as $key => $value) {
            // action-Feld überspringen
            if ($key !== 'action') {
                $result = setConfigValue($dbConnection, $key, $value);
                if (!$result) {
                    $success = false;
                }
            }
        }
        
        if ($success) {
            $_SESSION['success_message'] = "Konfiguration wurde erfolgreich gespeichert!";
        } else {
            $_SESSION['error_message'] = "Fehler beim Speichern der Konfiguration: " . $dbConnection->error;
        }
        
        // Seite neu laden, um neue Konfigurationen anzuzeigen
        header("Location: configuration.php");
        exit();
    }
}

// Alle Konfigurationen holen
$configs = getAllConfigValues($dbConnection);

// Header einbinden
include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Konfiguration</h2>
    <a href="overview.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Zurück zur Übersicht
    </a>
</div>

<div class="card">
    <div class="card-header bg-primary text-white">
        <h2 class="fs-4 mb-0">Texte und Einstellungen</h2>
    </div>
    <div class="card-body">
        <form action="configuration.php" method="post" class="custom-form">
            <input type="hidden" name="action" value="save_config">
            
            <!-- Einleitungstext -->
            <div class="mb-3">
                <label for="text_intro" class="form-label">Einleitungstext:</label>
                <textarea id="text_intro" name="text_intro" class="form-control" rows="2"><?php echo htmlspecialchars(getConfigValue($dbConnection, 'text_intro')); ?></textarea>
                <small class="form-text text-muted">Dieser Text wird am Anfang jeder Artikelbeschreibung eingefügt.</small>
            </div>
            
            <!-- Rechtlicher Hinweistext -->
            <div class="mb-3">
                <label for="text_legal" class="form-label">Rechtlicher Hinweistext:</label>
                <textarea id="text_legal" name="text_legal" class="form-control" rows="4"><?php echo htmlspecialchars(getConfigValue($dbConnection, 'text_legal')); ?></textarea>
                <small class="form-text text-muted">Dieser Text enthält rechtliche Hinweise für die Artikelbeschreibung.</small>
            </div>
            
            <!-- PayPal-Hinweistext -->
            <div class="mb-3">
                <label for="text_paypal" class="form-label">PayPal-Hinweistext:</label>
                <textarea id="text_paypal" name="text_paypal" class="form-control" rows="2"><?php echo htmlspecialchars(getConfigValue($dbConnection, 'text_paypal')); ?></textarea>
                <small class="form-text text-muted">Dieser Text enthält Informationen zu PayPal-Gebühren.</small>
            </div>
            
            <!-- E-Mail-Vorlage -->
            <div class="mb-3">
                <label for="mail_template" class="form-label">E-Mail-Vorlage:</label>
                <textarea id="mail_template" name="mail_template" class="form-control" rows="6"><?php echo htmlspecialchars(getConfigValue($dbConnection, 'mail_template')); ?></textarea>
                <small class="form-text text-muted">Vorlage für E-Mail-Texte. Du kannst folgende Platzhalter verwenden: {BETRAG}, {PAYPAL_ACCOUNT}, {SENDER_CITY}, {SENDER_NAME}</small>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-4">
                    <label for="paypal_account" class="form-label">PayPal-Konto:</label>
                    <input type="text" id="paypal_account" name="paypal_account" class="form-control" value="<?php echo htmlspecialchars(getConfigValue($dbConnection, 'paypal_account')); ?>">
                    <small class="form-text text-muted">Dein PayPal-Konto für Zahlungen.</small>
                </div>
                <div class="col-md-4">
                    <label for="sender_city" class="form-label">Absender-Stadt:</label>
                    <input type="text" id="sender_city" name="sender_city" class="form-control" value="<?php echo htmlspecialchars(getConfigValue($dbConnection, 'sender_city')); ?>">
                    <small class="form-text text-muted">Deine Stadt für die E-Mail-Signatur.</small>
                </div>
                <div class="col-md-4">
                    <label for="sender_name" class="form-label">Absender-Name:</label>
                    <input type="text" id="sender_name" name="sender_name" class="form-control" value="<?php echo htmlspecialchars(getConfigValue($dbConnection, 'sender_name')); ?>">
                    <small class="form-text text-muted">Dein Name für die E-Mail-Signatur.</small>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="pdf_currency" class="form-label">Währung für PDF-Badges:</label>
                <input type="text" id="pdf_currency" name="pdf_currency" class="form-control" value="<?php echo htmlspecialchars(getConfigValue($dbConnection, 'pdf_currency')); ?>" maxlength="3">
                <small class="form-text text-muted">Währungssymbol für die generierten PDF-Badges (z.B. EUR, USD).</small>
            </div>
            
            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Konfiguration speichern
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header bg-info text-white">
        <h2 class="fs-4 mb-0">PDF-Vorschau</h2>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> Diese Vorschau zeigt, wie die generierten PDF-Badges für Ihre Artikel aussehen werden.
        </div>
        
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card border-primary">
                    <div class="card-body text-center">
                        <h5 class="card-title">Beispiel-Artikel</h5>
                        <p class="card-text fs-4 fw-bold"><?php echo number_format(120.00, 2, ',', '.') . ' ' . getConfigValue($dbConnection, 'pdf_currency'); ?></p>
                        <p class="card-text text-muted small">PDF-Badges haben eine Größe von 62mm x 62mm</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Footer einbinden
include 'includes/footer.php';
?>