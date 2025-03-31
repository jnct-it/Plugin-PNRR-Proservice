/**
 * Gestione tabella dei cloni (ricerca, ordinamento, paginazione)
 */
function initTableManagement($) {
    'use strict';
    
    // Cache dei dati della tabella originale
    var $table = $('#pnrr-clones-table');
    var $rows = $table.find('tbody tr').toArray();
    var $tableControls = $('.pnrr-table-controls');
    var $pagination = $('.pnrr-table-pagination');
    var $displayingNum = $('.displaying-num');
    var currentPage = 1;
    var itemsPerPage = parseInt($('#pnrr-table-length').val()) || 10;
    var totalItems = $rows.length;
    var sortColumn = 'title';
    var sortDirection = 'asc';
    var filteredRows = $rows;
    
    // Inizializzazione
    initTable();
    
    // Funzione per inizializzare la tabella
    function initTable() {
        updatePagination();
        renderTable();
        setupEventListeners();
    }
    
    // Aggiorna le informazioni di paginazione
    function updatePagination() {
        var totalPages = Math.max(1, Math.ceil(filteredRows.length / itemsPerPage));
        
        // Aggiorna conteggio risultati
        $displayingNum.text(filteredRows.length + ' elementi');
        
        // Aggiorna numero pagine
        $('.total-pages').text(totalPages);
        $('.current-page').text(currentPage);
        
        // Abilita/disabilita pulsanti di navigazione
        $('.first-page, .prev-page').prop('disabled', currentPage === 1);
        $('.last-page, .next-page').prop('disabled', currentPage === totalPages);
    }
    
    // Render della tabella con paginazione
    function renderTable() {
        var startIdx = (currentPage - 1) * itemsPerPage;
        var endIdx = Math.min(startIdx + itemsPerPage, filteredRows.length);
        var visibleRows = filteredRows.slice(startIdx, endIdx);
        
        // Pulisci tabella
        $table.find('tbody').empty();
        
        // Nessun risultato
        if (visibleRows.length === 0) {
            $table.find('tbody').append('<tr><td colspan="7" class="no-items">Nessun risultato trovato.</td></tr>');
            return;
        }
        
        // Aggiungi righe visibili
        for (var i = 0; i < visibleRows.length; i++) {
            $table.find('tbody').append(visibleRows[i]);
        }
        
        // Aggiorna paginazione
        updatePagination();
    }
    
    // Setup degli event listener
    function setupEventListeners() {
        // Ricerca
        $('#pnrr-search-input').on('keyup', function() {
            var searchText = $(this).val().toLowerCase();
            filterTable(searchText);
        });
        
        // Reset ricerca
        $('#pnrr-search-clear').on('click', function() {
            $('#pnrr-search-input').val('');
            filterTable('');
        });
        
        // Ordinamento colonne
        $('.sortable').on('click', function() {
            var column = $(this).data('sort');
            sortTable(column);
        });
        
        // Seleziona numero elementi per pagina
        $('#pnrr-table-length').on('change', function() {
            itemsPerPage = parseInt($(this).val());
            currentPage = 1; // Reset alla prima pagina
            renderTable();
        });
        
        // Navigazione paginazione
        $('.first-page').on('click', function() {
            if ($(this).prop('disabled')) return;
            currentPage = 1;
            renderTable();
        });
        
        $('.prev-page').on('click', function() {
            if ($(this).prop('disabled')) return;
            currentPage--;
            renderTable();
        });
        
        $('.next-page').on('click', function() {
            if ($(this).prop('disabled')) return;
            currentPage++;
            renderTable();
        });
        
        $('.last-page').on('click', function() {
            if ($(this).prop('disabled')) return;
            currentPage = Math.ceil(filteredRows.length / itemsPerPage);
            renderTable();
        });
        
        // Gestione della casella di controllo "Mostra cloni eliminati"
        $('#show-deleted-clones').on('change', function() {
            var showDeleted = $(this).is(':checked');
            
            // Salva la preferenza in un cookie (valido per 30 giorni)
            var expirationDate = new Date();
            expirationDate.setDate(expirationDate.getDate() + 30);
            document.cookie = 'pnrr_show_deleted_clones=' + showDeleted + '; expires=' + expirationDate.toUTCString() + '; path=/';
            
            // Aggiorna la tabella tramite AJAX
            $.ajax({
                url: pnrr_cloner.ajax_url,
                type: 'POST',
                data: {
                    action: 'pnrr_get_filtered_clones',
                    nonce: pnrr_cloner.nonce,
                    show_deleted: showDeleted
                },
                beforeSend: function() {
                    // Mostra un indicatore di caricamento
                    $('#pnrr-clones-table tbody').html(
                        '<tr><td colspan="7" class="loading-data">Caricamento dati in corso...</td></tr>'
                    );
                },
                success: function(response) {
                    if (response.success) {
                        // Aggiorna il contenuto della tabella
                        $('#pnrr-clones-table tbody').html(response.data.html);
                        
                        // Aggiorna il conteggio degli elementi mostrati
                        $('.displaying-num').text(response.data.count + ' elementi');
                        
                        // Reset alla prima pagina
                        currentPage = 1;
                        
                        // Ricrea l'array delle righe della tabella per le funzionalità di ordinamento e ricerca
                        $rows = $('#pnrr-clones-table tbody tr').toArray();
                        filteredRows = $rows;
                        
                        // Aggiorna la paginazione
                        updatePagination();
                        renderTable();
                    } else {
                        // Mostra un messaggio di errore
                        $('#pnrr-clones-table tbody').html(
                            '<tr><td colspan="7" class="error-message">Errore: ' + response.data.message + '</td></tr>'
                        );
                    }
                },
                error: function() {
                    // Gestione errore di connessione
                    $('#pnrr-clones-table tbody').html(
                        '<tr><td colspan="7" class="error-message">Errore di connessione</td></tr>'
                    );
                }
            });
        });
    }
    
    // Filtra la tabella in base al testo di ricerca
    function filterTable(searchText) {
        if (searchText === '') {
            filteredRows = $rows;
        } else {
            filteredRows = [];
            for (var i = 0; i < $rows.length; i++) {
                var rowText = $($rows[i]).text().toLowerCase();
                if (rowText.indexOf(searchText) !== -1) {
                    filteredRows.push($rows[i]);
                }
            }
        }
        
        currentPage = 1; // Reset alla prima pagina
        sortTable(sortColumn, false); // Mantieni l'ordinamento corrente
    }
    
    // Ordina la tabella
    function sortTable(column, toggleDirection = true) {
        // Aggiorna intestazioni colonne
        $('.sortable').removeClass('asc desc');
        
        if (column === sortColumn && toggleDirection) {
            sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            sortColumn = column;
            if (toggleDirection) {
                sortDirection = 'asc';
            }
        }
        
        // Aggiorna indicatore di ordinamento
        $('.sortable[data-sort="' + column + '"]').addClass(sortDirection);
        
        // Indice colonna
        var columnMap = {
            'slug': 0,
            'title': 1,
            'home_url': 2,
            'logo_url': 3
        };
        
        var columnIdx = columnMap[column];
        
        // Ordina array
        filteredRows.sort(function(a, b) {
            var valA = $(a).find('td').eq(columnIdx).text().toLowerCase();
            var valB = $(b).find('td').eq(columnIdx).text().toLowerCase();
            
            if (valA < valB) {
                return sortDirection === 'asc' ? -1 : 1;
            }
            if (valA > valB) {
                return sortDirection === 'asc' ? 1 : -1;
            }
            return 0;
        });
        
        renderTable();
    }
}