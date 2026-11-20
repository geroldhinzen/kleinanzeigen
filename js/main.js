/**
 * Hauptskriptdatei für Kleinanzeigen
 */
document.addEventListener('DOMContentLoaded', function() {
    
    // ---- Zeichenzähler für Eingabefelder ----
    const charCountFields = document.querySelectorAll('[data-char-count]');
    
    charCountFields.forEach(field => {
        const maxLength = parseInt(field.getAttribute('maxlength')) || 0;
        const counterId = field.getAttribute('data-char-count');
        const counterElement = document.getElementById(counterId);
        
        if (counterElement) {
            // Initiale Anzeige des Zählers
            updateCharCounter(field, counterElement, maxLength);
            
            // Zähler bei Eingabe aktualisieren
            field.addEventListener('input', function() {
                updateCharCounter(field, counterElement, maxLength);
            });
        }
    });
    
    function updateCharCounter(field, counterElement, maxLength) {
        const currentLength = field.value.length;
        counterElement.textContent = currentLength + '/' + maxLength + ' Zeichen';
        
        // Styling für den Zähler anpassen
        if (currentLength >= maxLength) {
            counterElement.classList.add('text-danger');
            counterElement.classList.remove('text-warning', 'text-muted');
        } else if (currentLength >= maxLength * 0.8) {
            counterElement.classList.add('text-warning');
            counterElement.classList.remove('text-danger', 'text-muted');
        } else {
            counterElement.classList.add('text-muted');
            counterElement.classList.remove('text-warning', 'text-danger');
        }
    }
    
    // ---- Versandmethode und -preis aktualisieren ----
    const shippingMethodSelect = document.getElementById('shipping_method_id');
    const versandpreisField = document.getElementById('versandpreis');
    const preisField = document.getElementById('preis');
    
    if (shippingMethodSelect && versandpreisField) {
        // Bei Änderung der Versandmethode
        shippingMethodSelect.addEventListener('change', function() {
            updateShippingPrice();
        });
        
        // Bei Änderung des Preises
        if (preisField) {
            preisField.addEventListener('input', function() {
                // Wenn eine Versandmethode ausgewählt ist, Preis aktualisieren
                if (shippingMethodSelect.value) {
                    updateShippingPrice();
                }
            });
        }
        
        // Funktion zur Aktualisierung des Versandpreises
        function updateShippingPrice() {
            const selectedOption = shippingMethodSelect.options[shippingMethodSelect.selectedIndex];
            if (selectedOption && selectedOption.value) {
                const shippingMethodPrice = parseFloat(selectedOption.getAttribute('data-price') || 0);
                const artikelPreis = parseFloat(preisField.value || 0);
                
               
                    // Versandpreis ist die Summe aus Artikelpreis und Versandmethode-Preis
                    const gesamtpreis = artikelPreis + shippingMethodPrice;
                    versandpreisField.value = gesamtpreis.toFixed(2);
                
            }
        }
        
        // Initial ausführen, falls bereits eine Methode ausgewählt ist
        if (shippingMethodSelect.value) {
            updateShippingPrice();
        }
    }
    
    // ---- Copy-Funktion für Textfelder ----
    const copyButtons = document.querySelectorAll('[data-action="copy"]');
    
    copyButtons.forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            copyText(targetId);
        });
    });
    
    function copyText(elementId) {
        const copyText = document.getElementById(elementId);
        if (!copyText) return;
        
        copyText.select();
        copyText.setSelectionRange(0, 99999);
        
        navigator.clipboard.writeText(copyText.value)
            .then(() => {
                // Toast-Nachricht anzeigen
                const toast = new bootstrap.Toast(document.getElementById('copyToast'));
                toast.show();
            })
            .catch(() => {
                alert("Kopieren fehlgeschlagen. Bitte manuell kopieren.");
            });
    }
    
    // ---- Live-Suche in der Übersicht ----
    const searchField = document.getElementById('searchField');
    
    if (searchField) {
        searchField.addEventListener('input', function() {
            const searchValue = this.value.toLowerCase().trim();
            
            if (searchValue.length < 3 && searchValue.length > 0) {
                return; // Mindestens 3 Zeichen für die Suche erforderlich
            }
            
            const tableRows = document.querySelectorAll('.article-list tbody tr');
            
            tableRows.forEach(row => {
                const title = row.querySelector('td:first-child').textContent.toLowerCase();
                
                if (searchValue === '' || title.includes(searchValue)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
    
    // ---- Status-Filter in der Übersicht ----
    const statusFilter = document.getElementById('statusFilter');
    
    if (statusFilter) {
        statusFilter.addEventListener('change', function() {
            const selectedStatus = this.value;
            const tableRows = document.querySelectorAll('.article-list tbody tr');
            
            tableRows.forEach(row => {
                const status = row.getAttribute('data-status');
                
                if (selectedStatus === '' || status === selectedStatus) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
    
    // ---- Sortierung in der Übersicht ----
    const sortButtons = document.querySelectorAll('[data-sort]');
    
    if (sortButtons.length > 0) {
        sortButtons.forEach(button => {
            button.addEventListener('click', function() {
                const sortField = this.getAttribute('data-sort');
                const isAsc = this.getAttribute('data-sort-direction') === 'asc';
                
                // Sortierrichtung umkehren
                this.setAttribute('data-sort-direction', isAsc ? 'desc' : 'asc');
                
                // Icons in allen Sortierbuttons aktualisieren
                sortButtons.forEach(btn => {
                    const icon = btn.querySelector('i');
                    if (icon) {
                        icon.className = 'bi bi-arrow-down-up';
                    }
                });
                
                // Icon im aktuellen Button aktualisieren
                const icon = this.querySelector('i');
                if (icon) {
                    icon.className = isAsc ? 'bi bi-arrow-down' : 'bi bi-arrow-up';
                }
                
                // Tabelle sortieren
                sortTable(sortField, !isAsc);
            });
        });
    }
    
    function sortTable(field, asc) {
        const table = document.querySelector('.article-list');
        if (!table) return;
        
        const tbody = table.querySelector('tbody');
        if (!tbody) return;
        
        const rows = Array.from(tbody.querySelectorAll('tr'));
        
        // Sortieren
        rows.sort((a, b) => {
            let aValue, bValue;
            
            switch(field) {
                case 'title':
                    aValue = a.querySelector('td:nth-child(1)')?.textContent.toLowerCase() || '';
                    bValue = b.querySelector('td:nth-child(1)')?.textContent.toLowerCase() || '';
                    break;
                case 'price':
                    aValue = parseFloat(a.querySelector('td:nth-child(2)')?.textContent.replace('€', '').replace(',', '.') || '0');
                    bValue = parseFloat(b.querySelector('td:nth-child(2)')?.textContent.replace('€', '').replace(',', '.') || '0');
                    break;
                case 'created':
                    aValue = new Date(a.getAttribute('data-created') || 0);
                    bValue = new Date(b.getAttribute('data-created') || 0);
                    break;
                case 'updated':
                    aValue = new Date(a.getAttribute('data-updated') || 0);
                    bValue = new Date(b.getAttribute('data-updated') || 0);
                    break;
                default:
                    return 0;
            }
            
            if (aValue < bValue) {
                return asc ? -1 : 1;
            }
            if (aValue > bValue) {
                return asc ? 1 : -1;
            }
            return 0;
        });
        
        // Sortierte Zeilen wieder einfügen
        rows.forEach(row => {
            tbody.appendChild(row);
        });
    }
    
    // ---- CMD+S / CTRL+S für Speichern im Detailformular ----
    document.addEventListener('keydown', function(e) {
        if ((e.metaKey || e.ctrlKey) && e.key === 's') {
            // Nur in der Detail-Ansicht mit dem Formular
            const detailForm = document.querySelector('form[data-form="detail-form"]');
            if (detailForm) {
                e.preventDefault(); // Standard-Browser-Speichern verhindern
                e.stopPropagation(); // Event-Bubbling stoppen
                
                // Sicherstellen, dass kein Modal geöffnet ist
                const openModals = document.querySelectorAll('.modal.show');
                if (openModals.length > 0) {
                    return; // Nicht speichern, wenn ein Modal geöffnet ist
                }
                
                // Formular direkt abschicken (nicht über Button klicken, da das Probleme verursachen kann)
                detailForm.submit();
            }
        }
    });
    
    // ---- Modale Dialoge ----
    const deleteButton = document.getElementById('deleteButton');
    if (deleteButton) {
        deleteButton.addEventListener('click', function(e) {
            const deleteModal = document.getElementById('deleteModal');
            if (deleteModal) {
                const bsDeleteModal = new bootstrap.Modal(deleteModal);
                bsDeleteModal.show();
            }
        });
    }
});