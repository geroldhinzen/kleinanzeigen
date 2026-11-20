<?php
/**
 * Datenbank-Konfigurationsdatei
 */

// Datenbankkonfiguration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'root');
define('DB_NAME', 'kleinanzeigen');
define('DB_PORT', 8889);

/**
 * Stellt eine Datenbankverbindung her
 * 
 * @return mysqli Die Datenbankverbindung
 */
function getDbConnection() {
    $dbConnection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    
    if ($dbConnection->connect_error) {
        die("Datenbankverbindung fehlgeschlagen: " . $dbConnection->connect_error);
    }
    
    // Zeichensatz auf UTF-8 setzen
    $dbConnection->set_charset("utf8mb4");
    
    return $dbConnection;
}