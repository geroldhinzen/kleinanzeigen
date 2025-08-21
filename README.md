# Kleinanzeigen

Kleinanzeigen ist eine schlanke PHP-Anwendung zum Erstellen und Verwalten von Kleinanzeigen. Sie unterstützt das Generieren von PDF-Badges und das Hochladen von Bildern.

## Installation

1. Repository klonen:
   ```bash
   git clone <repository-url>
   cd kleinanzeigen
   ```
2. Datenbankzugang in `config/database.php` anpassen und erforderliche Tabellen in einer MySQL-Datenbank anlegen.
3. Optional: Beispiel-Daten importieren.
4. Lokalen Entwicklungsserver starten:
   ```bash
   php -S localhost:8000
   ```
5. Im Browser `http://localhost:8000/index.php` öffnen.

## Nutzung

- Über **index.php** neue Artikel anlegen, Texte generieren und Bilder hochladen.
- In **overview.php** bestehende Artikel einsehen und verwalten.
- **configuration.php** bietet Einstellungen für Texte, PayPal und PDF-Badges.
- Generierte PDF-Badges werden im Ordner `pdfs/` abgelegt, hochgeladene Bilder unter `uploads/`.

## Lizenz

Dieses Projekt steht unter der [MIT-Lizenz](LICENSE).
