<?php
/**
 * Hilfsfunktionen für die Anwendung
 */

// Definiere Konstanten
define('UPLOADS_DIR', 'uploads');
define('MAX_UPLOAD_SIZE', 20 * 1024 * 1024); // 20MB

/**
 * Stellt eine Datenbankverbindung her
 * 
 * @return mysqli Die Datenbankverbindung
 */
function getDbConnection() {
    $dbHost = 'localhost';
    $dbUser = 'root';
    $dbPass = 'root';
    $dbName = 'kleinanzeigen';
    $dbPort = 8889;
    
    $dbConnection = new mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort);
    
    if ($dbConnection->connect_error) {
        die("Datenbankverbindung fehlgeschlagen: " . $dbConnection->connect_error);
    }
    
    // Zeichensatz auf UTF-8 setzen
    $dbConnection->set_charset("utf8mb4");
    
    return $dbConnection;
}

/**
 * Erstellt einen Ordnernamen basierend auf dem Titel
 * 
 * @param string $title Der Titel des Artikels
 * @return string Der erstellte Ordnername
 */
function createFolderName($title) {
    // Sonderzeichen entfernen und Leerzeichen durch Unterstriche ersetzen
    $folderName = preg_replace('/[^a-zA-Z0-9\s]/', '', $title);
    $folderName = str_replace(' ', '_', $folderName);
    // Auf 30 Zeichen kürzen
    $folderName = substr($folderName, 0, 30);
    // Timestamp hinzufügen für Eindeutigkeit
    $folderName .= '_' . time();
    return $folderName;
}

/**
 * Erstellt erforderliche Verzeichnisse, falls diese nicht existieren
 * 
 * @param string $path Der zu erstellende Pfad
 * @return bool Erfolgsstatus
 */
function createDirectoryIfNotExists($path) {
    if (!file_exists($path)) {
        return mkdir($path, 0777, true);
    }
    return true;
}

/**
 * Verkleinert und komprimiert ein Bild
 * 
 * @param string $sourceFile Quell-Bilddatei
 * @param string $targetFile Ziel-Bilddatei
 * @param int $maxWidth Maximale Breite
 * @param int $maxHeight Maximale Höhe
 * @param int $quality Qualität (1-100)
 * @return bool Erfolgsstatus
 */
function resizeImage($sourceFile, $targetFile, $maxWidth = 1200, $maxHeight = 1200, $quality = 85) {
    // Bildinformationen holen
    $imageInfo = getimagesize($sourceFile);
    if ($imageInfo === false) return false;
    
    $width = $imageInfo[0];
    $height = $imageInfo[1];
    $mimeType = $imageInfo['mime'];
    
    // Wenn das Bild bereits kleiner ist, einfach kopieren
    if ($width <= $maxWidth && $height <= $maxHeight && filesize($sourceFile) < 2000000) {
        return copy($sourceFile, $targetFile);
    }
    
    // Neue Dimensionen berechnen
    $ratio = min($maxWidth / $width, $maxHeight / $height);
    $newWidth = $width * $ratio;
    $newHeight = $height * $ratio;
    
    // Neues Bild erstellen
    $newImage = imagecreatetruecolor($newWidth, $newHeight);
    
    // Quellbild basierend auf MIME-Typ laden
    switch ($mimeType) {
        case 'image/jpeg':
            $sourceImage = imagecreatefromjpeg($sourceFile);
            break;
        case 'image/png':
            $sourceImage = imagecreatefrompng($sourceFile);
            // Transparenz erhalten
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
            break;
        case 'image/gif':
            $sourceImage = imagecreatefromgif($sourceFile);
            break;
        default:
            return false;
    }
    
    // Bild verkleinern
    imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    
    // Bild speichern
    $result = false;
    switch ($mimeType) {
        case 'image/jpeg':
            $result = imagejpeg($newImage, $targetFile, $quality);
            break;
        case 'image/png':
            // PNG-Qualität auf eine Skala von 0-9 umrechnen
            $pngQuality = 9 - round(($quality / 100) * 9);
            $result = imagepng($newImage, $targetFile, $pngQuality);
            break;
        case 'image/gif':
            $result = imagegif($newImage, $targetFile);
            break;
    }
    
    // Ressourcen freigeben
    imagedestroy($sourceImage);
    imagedestroy($newImage);
    
    return $result;
}

/**
 * Verarbeitet hochgeladene Bilder für einen Artikel
 * 
 * @param array $files Die hochgeladenen Dateien ($_FILES)
 * @param int $articleId Die ID des Artikels
 * @param string $folderName Der Ordnername für den Artikel
 * @return bool|int Anzahl der erfolgreichen Uploads oder false bei Fehler
 */
function processUploadedImages($files, $articleId, $folderName) {
    if (!isset($files['images']) || empty($files['images']['name'][0])) {
        return 0;
    }
    
    $dbConnection = getDbConnection();
    $articleFolder = UPLOADS_DIR . '/' . $folderName;
    $successCount = 0;
    
    // Stelle sicher, dass der Ordner existiert
    createDirectoryIfNotExists($articleFolder);
    
    $fileCount = count($files['images']['name']);
    
    for ($i = 0; $i < $fileCount; $i++) {
        if ($files['images']['error'][$i] === UPLOAD_ERR_OK) {
            $tmpName = $files['images']['tmp_name'][$i];
            $fileName = basename($files['images']['name'][$i]);
            $newFileName = uniqid() . '_' . $fileName;
            $tempDestination = $tmpName . '_resized';
            $finalDestination = $articleFolder . '/' . $newFileName;
            
            // Bild verkleinern und komprimieren
            if (resizeImage($tmpName, $tempDestination)) {
                if (rename($tempDestination, $finalDestination)) {
                    // Bildreferenz in Datenbank speichern
                    $relativePath = UPLOADS_DIR . '/' . $folderName . '/' . $newFileName;
                    $stmt = $dbConnection->prepare("INSERT INTO images (article_id, image_path) VALUES (?, ?)");
                    $stmt->bind_param("is", $articleId, $relativePath);
                    if ($stmt->execute()) {
                        $successCount++;
                    }
                    $stmt->close();
                }
            } else {
                // Fallback: Versuchen ohne Verkleinerung hochzuladen (bei kleinen Bildern)
                if (filesize($tmpName) < 2000000) { // Nur Dateien unter 2MB
                    if (move_uploaded_file($tmpName, $finalDestination)) {
                        // Bildreferenz in Datenbank speichern
                        $relativePath = UPLOADS_DIR . '/' . $folderName . '/' . $newFileName;
                        $stmt = $dbConnection->prepare("INSERT INTO images (article_id, image_path) VALUES (?, ?)");
                        $stmt->bind_param("is", $articleId, $relativePath);
                        if ($stmt->execute()) {
                            $successCount++;
                        }
                        $stmt->close();
                    }
                }
            }
        }
    }
    
    return $successCount;
}

/**
 * Holt alle Versandmethoden aus der Datenbank
 * 
 * @param mysqli $dbConnection Die Datenbankverbindung
 * @return array Array mit allen Versandmethoden
 */
function getShippingMethods($dbConnection) {
    $methods = [];
    $query = "SELECT * FROM shipping_methods ORDER BY provider, price ASC";
    $result = $dbConnection->query($query);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $methods[] = $row;
        }
    }
    
    return $methods;
}

/**
 * Holt eine Versandmethode anhand ihrer ID
 * 
 * @param mysqli $dbConnection Die Datenbankverbindung
 * @param int $id Die ID der Versandmethode
 * @return array|null Die Versandmethode oder null, wenn nicht gefunden
 */
function getShippingMethodById($dbConnection, $id) {
    $query = "SELECT * FROM shipping_methods WHERE id = ?";
    $stmt = $dbConnection->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

/**
 * Formatiert eine Versandmethode für die Anzeige
 * 
 * @param array $method Die Versandmethode
 * @return string Die formatierte Versandmethode
 */
function formatShippingMethod($method) {
    if (!$method) return '';
    
    if ($method['provider'] === '' && $method['product_name'] === 'Kein Versand') {
        return 'Kein Versand';
    }
    
    return sprintf(
        '%s %s %skg (%s x %s x %s cm) - %.2f €',
        $method['provider'],
        $method['product_name'],
        $method['max_weight'],
        $method['max_length'],
        $method['max_width'],
        $method['max_height'],
        $method['price']
    );
}

/**
 * Berechnet den PayPal-Gesamtbetrag
 * 
 * @param float $preis Der Preis des Artikels
 * @param float $versandpreis Der Versandpreis
 * @return float Der Gesamtbetrag mit PayPal-Gebühren
 */
function calculatePayPalTotal($preis, $versandpreis) {
    return ($preis * 1.029) + 0.35 + $versandpreis;
}

/**
 * Holt einen Konfigurationswert aus der Datenbank
 * 
 * @param mysqli $dbConnection Die Datenbankverbindung
 * @param string $key Der Schlüssel der Konfiguration
 * @param string $default Der Standardwert, falls der Schlüssel nicht existiert
 * @return string Der Konfigurationswert
 */
function getConfigValue($dbConnection, $key, $default = '') {
    $stmt = $dbConnection->prepare("SELECT config_value FROM config_texts WHERE config_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $stmt->bind_result($value);
    
    if ($stmt->fetch()) {
        $stmt->close();
        return $value;
    }
    
    $stmt->close();
    return $default;
}

/**
 * Speichert einen Konfigurationswert in der Datenbank
 * 
 * @param mysqli $dbConnection Die Datenbankverbindung
 * @param string $key Der Schlüssel der Konfiguration
 * @param string $value Der zu speichernde Wert
 * @param string $description Die Beschreibung der Konfiguration
 * @return bool Erfolgsstatus
 */
function setConfigValue($dbConnection, $key, $value, $description = null) {
    // Prüfen, ob der Schlüssel bereits existiert
    $checkStmt = $dbConnection->prepare("SELECT id FROM config_texts WHERE config_key = ?");
    $checkStmt->bind_param("s", $key);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $exists = $result->num_rows > 0;
    $checkStmt->close();
    
    if ($exists) {
        // Aktualisieren
        if ($description !== null) {
            $stmt = $dbConnection->prepare("UPDATE config_texts SET config_value = ?, description = ? WHERE config_key = ?");
            $stmt->bind_param("sss", $value, $description, $key);
        } else {
            $stmt = $dbConnection->prepare("UPDATE config_texts SET config_value = ? WHERE config_key = ?");
            $stmt->bind_param("ss", $value, $key);
        }
    } else {
        // Neu anlegen
        $stmt = $dbConnection->prepare("INSERT INTO config_texts (config_key, config_value, description) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $key, $value, $description);
    }
    
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

/**
 * Holt alle Konfigurationen aus der Datenbank
 * 
 * @param mysqli $dbConnection Die Datenbankverbindung
 * @return array Array mit allen Konfigurationen
 */
function getAllConfigValues($dbConnection) {
    $configs = [];
    $result = $dbConnection->query("SELECT * FROM config_texts ORDER BY config_key");
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $configs[] = $row;
        }
    }
    
    return $configs;
}

/**
 * Generiert die Beschreibung für einen Artikel basierend auf den Konfigurationswerten
 * 
 * @param mysqli $dbConnection Die Datenbankverbindung
 * @param string $description Die Artikelbeschreibung
 * @return string Der generierte Beschreibungstext
 */
function generateDescription($dbConnection, $description) {
    $addTextIntro = getConfigValue($dbConnection, 'text_intro', 'Ich biete folgendes Objekt zum privaten Verkauf an:');
    $addTextLegal = getConfigValue($dbConnection, 'text_legal', 'Nichtraucherhaushalt. Keine Haustiere. Ich schließe jegliche Sachmangelhaftung aus. Die Haftung auf Schadenersatz wegen Verletzungen von Gesundheit, Körper oder Leben und grob fahrlässiger und/oder vorsätzlicher Verletzungen meiner Pflichten als Verkäufer bleibt uneingeschränkt.');
    $addTextPayPal = getConfigValue($dbConnection, 'text_paypal', 'Zahlung via PayPal: Bei der Zahlung mit Käuferschutz via PayPal wird eine Gebühr von 2,49% des Verkaufspreises + 0,35 EUR Transaktionsgebühr fällig.');

    $fullDescription = $addTextIntro . "\n\n";
    $fullDescription .= $description . "\n\n";
    $fullDescription .= $addTextLegal . "\n\n";
    $fullDescription .= $addTextPayPal . "\n\n";
    
    return $fullDescription;
}

/**
 * Generiert den Mailtext für einen Artikel
 * 
 * @param mysqli $dbConnection Die Datenbankverbindung
 * @param float $preis Der Preis des Artikels
 * @param float $versandpreis Der Versandpreis
 * @return string Der generierte Mailtext
 */
function generateMailText($dbConnection, $preis, $versandpreis) {
    $betragMitPaypal = calculatePayPalTotal($preis, $versandpreis);
    $formattedBetrag = number_format(ceil($betragMitPaypal), 2, ',', '');
    
    $mailTemplate = getConfigValue($dbConnection, 'mail_template', 'Hallo,\n\nvielen Dank für dein Interesse an meinem Artikel. Wenn du mit PayPal zahlst, überweise den Gesamtbetrag in Höhe von {BETRAG} Euro an folgendes PayPal-Konto: {PAYPAL_ACCOUNT}\n\nDer Artikel wird einen Tag nach Geldeingang versendet.\n\nBeste Grüße aus {SENDER_CITY},\n{SENDER_NAME}');
    $paypalAccount = getConfigValue($dbConnection, 'paypal_account', 'www.paypal.me/gerold');
    $senderCity = getConfigValue($dbConnection, 'sender_city', 'Koblenz');
    $senderName = getConfigValue($dbConnection, 'sender_name', 'Gerold');
    
    // Platzhalter ersetzen
    $mailText = str_replace('{BETRAG}', $formattedBetrag, $mailTemplate);
    $mailText = str_replace('{PAYPAL_ACCOUNT}', $paypalAccount, $mailText);
    $mailText = str_replace('{SENDER_CITY}', $senderCity, $mailText);
    $mailText = str_replace('{SENDER_NAME}', $senderName, $mailText);
    
    return $mailText;
}
/**
 * Generiert eine HTML-Datei als Ersatz für PDF-Badge
 * 
 * @param int $articleId Die ID des Artikels
 * @param string $title Der Titel des Artikels
 * @param float $price Der Preis des Artikels
 * @param mysqli $dbConnection Die Datenbankverbindung
 * @return string Der Pfad zur HTML-Datei
 */
function generateArticlePdf($articleId, $title, $price, $dbConnection) {
    // Erstelle Verzeichnis für PDFs, falls nicht vorhanden
    $pdfsDir = 'pdfs';
    if (!file_exists($pdfsDir)) {
        mkdir($pdfsDir, 0777, true);
    }
    
    // HTML-Dateiname (statt PDF)
    $fileName = $pdfsDir . '/article_' . $articleId . '.html';
    
    // Währung aus Konfiguration holen
    $currency = getConfigValue($dbConnection, 'pdf_currency', 'EUR');
    
    // Einfache HTML-Datei erstellen, die wie ein Badge aussieht
    $html = '<!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Artikel: ' . htmlspecialchars($title) . '</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                width: 62mm;
                height: 62mm;
                margin: 0;
                padding: 5mm;
                box-sizing: border-box;
                border: 1px solid #ccc;
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
            }
            h1 {
                font-size: 12pt;
                margin-bottom: 10mm;
                text-align: center;
            }
            .price {
                font-size: 16pt;
                font-weight: bold;
                text-align: center;
            }
            @media print {
                body {
                    border: none;
                }
                @page {
                    size: 62mm 62mm;
                    margin: 0;
                }
            }
        </style>
    </head>
    <body>
        <h1>' . htmlspecialchars($title) . '</h1>
        <div class="price">' . number_format($price, 2, ',', '.') . ' ' . $currency . '</div>
    </body>
    </html>';
    
    // HTML in Datei speichern
    file_put_contents($fileName, $html);
    
    return $fileName;
}

/**
 * Prüft, ob ein Badge für einen Artikel existiert und erstellt es gegebenenfalls
 * 
 * @param array $article Der Artikel als assoziatives Array
 * @param mysqli $dbConnection Die Datenbankverbindung
 * @return string Der Pfad zur HTML-Datei
 */
function getArticlePdfPath($article, $dbConnection) {
    // Wir verwenden jetzt HTML statt PDF
    $fileName = 'pdfs/article_' . $article['id'] . '.html';
    
    // Prüfen, ob die Datei existiert
    if (!file_exists($fileName)) {
        // Datei erstellen
        $fileName = generateArticlePdf($article['id'], $article['title'], $article['price'], $dbConnection);
    }
    
    return $fileName;
}