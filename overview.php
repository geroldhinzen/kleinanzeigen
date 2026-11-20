<?php
/**
 * Übersichtsseite für alle Artikel
 */

// Session starten
session_start();

// Konfiguration und Funktionen laden
require_once 'config/functions.php';

// Datenbank-Verbindung herstellen
$dbConnection = getDbConnection();

// Seitentitel festlegen
$pageTitle = 'Artikelübersicht';

// Filter und Sortierung
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$sortField = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$sortDirection = isset($_GET['direction']) && $_GET['direction'] === 'asc' ? 'ASC' : 'DESC';

// SQL-Abfrage vorbereiten
$query = "SELECT a.id, a.title, a.price, a.status, a.created_at, a.updated_at, 
          (SELECT image_path FROM images WHERE article_id = a.id ORDER BY id ASC LIMIT 1) AS thumbnail 
          FROM articles a";

// Status-Filter hinzufügen, wenn gesetzt
if (!empty($statusFilter)) {
    $query .= " WHERE a.status = ?";
}

// Sortierung hinzufügen
$query .= " ORDER BY " . ($sortField === 'price' ? 'a.price' : ($sortField === 'title' ? 'a.title' : ($sortField === 'updated_at' ? 'a.updated_at' : 'a.created_at'))) . " $sortDirection";

// Abfrage vorbereiten und ausführen
$stmt = $dbConnection->prepare($query);

if (!empty($statusFilter)) {
    $stmt->bind_param("s", $statusFilter);
}

$stmt->execute();
$result = $stmt->get_result();

// Erstelle Verzeichnis für Badges, falls nicht vorhanden
$pdfsDir = 'pdfs';
if (!file_exists($pdfsDir)) {
    mkdir($pdfsDir, 0777, true);
}

// Header einbinden
include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Artikelübersicht</h2>
    <div>
        <a href="configuration.php" class="btn btn-outline-primary me-2">
            <i class="bi bi-gear"></i> Konfiguration
        </a>
        <a href="index.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Neuen Artikel anlegen
        </a>
    </div>
</div>

<!-- Filter- und Suchbereich -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="search-filter">
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" id="searchField" class="form-control" placeholder="Nach Titel suchen (mind. 3 Zeichen)">
                </div>
            </div>
            <div class="col-md-6">
                <select id="statusFilter" class="form-select">
                    <option value="">Alle Status</option>
                    <option value="Entwurf" <?php echo $statusFilter === 'Entwurf' ? 'selected' : ''; ?>>Entwurf</option>
                    <option value="online" <?php echo $statusFilter === 'online' ? 'selected' : ''; ?>>Online</option>
                    <option value="verkauft" <?php echo $statusFilter === 'verkauft' ? 'selected' : ''; ?>>Verkauft</option>
                </select>
            </div>
        </div>
    </div>
</div>

<?php if ($result->num_rows > 0): ?>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover article-list mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 80px;">Bild</th>
                        <th>
                            <button type="button" class="btn btn-sm btn-link text-decoration-none" data-sort="title" data-sort-direction="<?php echo $sortField === 'title' && $sortDirection === 'ASC' ? 'desc' : 'asc'; ?>">
                                Titel <i class="bi <?php echo $sortField === 'title' ? ($sortDirection === 'ASC' ? 'bi-arrow-up' : 'bi-arrow-down') : 'bi-arrow-down-up'; ?>"></i>
                            </button>
                        </th>
                        <th>
                            <button type="button" class="btn btn-sm btn-link text-decoration-none" data-sort="price" data-sort-direction="<?php echo $sortField === 'price' && $sortDirection === 'ASC' ? 'desc' : 'asc'; ?>">
                                Preis <i class="bi <?php echo $sortField === 'price' ? ($sortDirection === 'ASC' ? 'bi-arrow-up' : 'bi-arrow-down') : 'bi-arrow-down-up'; ?>"></i>
                            </button>
                        </th>
                        <th>Status</th>
                        <th>
                            <button type="button" class="btn btn-sm btn-link text-decoration-none" data-sort="created" data-sort-direction="<?php echo $sortField === 'created_at' && $sortDirection === 'ASC' ? 'desc' : 'asc'; ?>">
                                Erstellt am <i class="bi <?php echo $sortField === 'created_at' ? ($sortDirection === 'ASC' ? 'bi-arrow-up' : 'bi-arrow-down') : 'bi-arrow-down-up'; ?>"></i>
                            </button>
                        </th>
                        <th>
                            <button type="button" class="btn btn-sm btn-link text-decoration-none" data-sort="updated" data-sort-direction="<?php echo $sortField === 'updated_at' && $sortDirection === 'ASC' ? 'desc' : 'asc'; ?>">
                                Letzte Änderung <i class="bi <?php echo $sortField === 'updated_at' ? ($sortDirection === 'ASC' ? 'bi-arrow-up' : 'bi-arrow-down') : 'bi-arrow-down-up'; ?>"></i>
                            </button>
                        </th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <?php 
                        // Stelle sicher, dass das Badge existiert oder erstelle es
                        $badgePath = getArticlePdfPath($row, $dbConnection);
                        ?>
                        <tr 
                            data-status="<?php echo $row['status']; ?>"
                            data-created="<?php echo $row['created_at']; ?>"
                            data-updated="<?php echo $row['updated_at']; ?>">
                            <td class="text-center">
                                <?php if (!empty($row['thumbnail'])): ?>
                                    <img src="<?php echo htmlspecialchars($row['thumbnail']); ?>" alt="Thumbnail" class="article-thumbnail">
                                <?php else: ?>
                                    <div class="article-thumbnail-placeholder">
                                        <i class="bi bi-image text-muted"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td onclick="window.location='detail.php?id=<?php echo $row['id']; ?>'"><?php echo htmlspecialchars($row['title']); ?></td>
                            <td onclick="window.location='detail.php?id=<?php echo $row['id']; ?>'"><?php echo number_format($row['price'], 2, ',', '.'); ?> €</td>
                            <td onclick="window.location='detail.php?id=<?php echo $row['id']; ?>'">
                                <?php if ($row['status'] == 'Entwurf'): ?>
                                    <span class="badge bg-warning text-dark">Entwurf</span>
                                <?php elseif ($row['status'] == 'online'): ?>
                                    <span class="badge bg-success">Online</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Verkauft</span>
                                <?php endif; ?>
                            </td>
                            <td onclick="window.location='detail.php?id=<?php echo $row['id']; ?>'"><?php echo date('d.m.Y H:i', strtotime($row['created_at'])); ?></td>
                            <td onclick="window.location='detail.php?id=<?php echo $row['id']; ?>'"><?php echo date('d.m.Y H:i', strtotime($row['updated_at'])); ?></td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="detail.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-primary" title="Bearbeiten">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="<?php echo $badgePath; ?>" class="btn btn-sm btn-outline-danger" title="Artikel-Badge anzeigen" target="_blank">
                                        <i class="bi bi-file-earmark-text"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle me-2"></i> Keine Artikel gefunden.
        <?php if (!empty($statusFilter)): ?>
            <a href="overview.php" class="alert-link">Alle Artikel anzeigen</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php
// Footer einbinden
include 'includes/footer.php';
?>