/**
 * JavaScript per la gestione dell'interfaccia admin del plugin PNRR Page Cloner
 */

jQuery(document).ready(function($) {
    // Gestione del salvataggio della pagina master
    $('#save-master-page').on('click', function(e) {
        e.preventDefault();
        
        var pageId = $('#master-page-select').val();
        var feedbackEl = $('#master-page-feedback');
        
        // Validazione
        if (!pageId) {
            feedbackEl.html('<div class="notice notice-error inline"><p>Seleziona una pagina valida</p></div>')
                      .show().delay(3000).fadeOut();
            return;
        }
        
        // Disabilita il pulsante durante il salvataggio
        $(this).prop('disabled', true).text('Salvataggio...');
        
        // Invia richiesta AJAX
        $.ajax({
            url: pnrr_cloner.ajax_url,
            type: 'POST',
            data: {
                action: 'pnrr_save_master_page',
                nonce: pnrr_cloner.nonce,
                page_id: pageId
            },
            success: function(response) {
                if (response.success) {
                    feedbackEl.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>')
                              .show();
                    
                    // Ricarica la pagina per mostrare la nuova selezione
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    feedbackEl.html('<div class="notice notice-error inline"><p>Errore: ' + response.data.message + '</p></div>')
                              .show();
                    $('#save-master-page').prop('disabled', false).text('Salva Selezione');
                }
            },
            error: function() {
                feedbackEl.html('<div class="notice notice-error inline"><p>Errore di connessione</p></div>')
                          .show();
                $('#save-master-page').prop('disabled', false).text('Salva Selezione');
            }
        });
    });

    // Gestione del processo di clonazione
    $('#pnrr-clone-button').on('click', function() {
        // Mostra la barra di progresso
        $('#pnrr-clone-progress').show();
        $('#pnrr-clone-results').show().find('.results-container').empty();
        
        // Disabilita il pulsante durante il processo
        $(this).prop('disabled', true);
        
        // Inizia il processo di clonazione
        cloneNextPage(0);
    });
    
    function cloneNextPage(index) {
        $.ajax({
            url: pnrr_cloner.ajax_url,
            type: 'POST',
            data: {
                action: 'clone_pnrr_pages',
                nonce: pnrr_cloner.nonce,
                clone_index: index
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.completed) {
                        // Processo completato
                        $('.progress-bar-fill').css('width', '100%');
                        $('.progress-status').text('Clonazione completata!');
                        $('#pnrr-clone-button').prop('disabled', false);
                        return;
                    }
                    
                    // Aggiorna la barra di progresso
                    var progress = (response.data.clone_index / response.data.total) * 100;
                    $('.progress-bar-fill').css('width', progress + '%');
                    $('.progress-status').text(response.data.clone_index + ' / ' + response.data.total + ' pagine clonate');
                    
                    // Aggiungi risultato
                    $('.results-container').append(
                        '<div class="clone-result success">' +
                        '<span class="dashicons dashicons-yes"></span> ' +
                        'Pagina "' + response.data.page_title + '" creata: ' +
                        '<a href="' + response.data.page_url + '" target="_blank">Visualizza</a>' +
                        '</div>'
                    );
                    
                    // Procedi con il prossimo clone
                    cloneNextPage(response.data.clone_index);
                } else {
                    // Errore
                    $('.results-container').append(
                        '<div class="clone-result error">' +
                        '<span class="dashicons dashicons-no"></span> ' +
                        'Errore durante la clonazione dell\'indice ' + response.data.clone_index + ': ' + 
                        response.data.message +
                        '</div>'
                    );
                    
                    // Continua con il prossimo nonostante l'errore
                    cloneNextPage(response.data.clone_index + 1);
                }
            },
            error: function() {
                $('.results-container').append(
                    '<div class="clone-result error">' +
                    '<span class="dashicons dashicons-no"></span> ' +
                    'Errore di connessione durante la richiesta Ajax' +
                    '</div>'
                );
                
                // Riabilita il pulsante in caso di errore
                $('#pnrr-clone-button').prop('disabled', false);
            }
        });
    }

    // Gestione dell'eliminazione delle pagine clone
    $('#pnrr-delete-button').on('click', function() {
        // Mostra il modal di conferma
        $('#delete-confirm-modal').show();
    });
    
    // Chiudi il modal quando l'utente fa clic sulla X o su Annulla
    $('.pnrr-modal-close, #cancel-delete').on('click', function() {
        $('#delete-confirm-modal').hide();
    });
    
    // Chiudi il modal se l'utente fa clic al di fuori di esso
    $(window).on('click', function(event) {
        if ($(event.target).is('#delete-confirm-modal')) {
            $('#delete-confirm-modal').hide();
        }
    });
    
    // Gestisci l'eliminazione quando l'utente conferma
    $('#confirm-delete').on('click', function() {
        // Nascondi il modal
        $('#delete-confirm-modal').hide();
        
        // Mostra la barra di progresso e il contenitore dei risultati
        $('#pnrr-delete-progress').show();
        $('#pnrr-delete-results').show().find('.results-container').empty();
        
        // Disabilita il pulsante di eliminazione
        $('#pnrr-delete-button').prop('disabled', true);
        
        // Esegui la chiamata AJAX per eliminare le pagine
        $.ajax({
            url: pnrr_cloner.ajax_url,
            type: 'POST',
            data: {
                action: 'pnrr_delete_all_clones',
                nonce: pnrr_cloner.nonce,
                update_clone_data: true, // Aggiorna sempre i dati
                remove_clone_data: false // Non rimuovere i record, ma marcali come eliminati
            },
            success: function(response) {
                // Rimuovi la barra di progresso
                $('#pnrr-delete-progress').hide();
                
                if (response.success) {
                    // Aggiorna il contenitore dei risultati
                    var result = response.data;
                    var resultHtml = '<div class="delete-summary">' +
                                    '<p><strong>Eliminazione completata:</strong></p>' +
                                    '<ul>' +
                                    '<li>' + result.deleted + ' pagine eliminate con successo</li>';
                    
                    if (result.skipped > 0) {
                        resultHtml += '<li>' + result.skipped + ' pagine non eliminate a causa di errori</li>';
                    }
                    
                    resultHtml += '</ul></div>';
                    
                    // Aggiungi messaggi di errore se presenti
                    if (result.errors && result.errors.length > 0) {
                        resultHtml += '<div class="delete-errors">' +
                                    '<p><strong>Errori riscontrati:</strong></p>' +
                                    '<ul>';
                                    
                        $.each(result.errors, function(index, error) {
                            resultHtml += '<li>' + error + '</li>';
                        });
                        
                        resultHtml += '</ul></div>';
                    }
                    
                    $('#pnrr-delete-results .results-container').html(resultHtml);
                    
                    // Aggiorna il conteggio delle pagine clone o nascondi la sezione se non ce ne sono più
                    if (result.deleted > 0) {
                        var remainingClones = parseInt($('.clone-count').text()) - result.deleted;
                        
                        if (remainingClones <= 0) {
                            $('.delete-info').html('<div class="notice notice-success inline"><p>Tutte le pagine clone sono state eliminate con successo.</p></div>');
                        } else {
                            $('.clone-count').text(remainingClones);
                            $('#pnrr-delete-button').prop('disabled', false);
                        }
                    } else {
                        $('#pnrr-delete-button').prop('disabled', false);
                    }
                } else {
                    // Mostra l'errore
                    $('#pnrr-delete-results .results-container').html(
                        '<div class="notice notice-error inline">' +
                        '<p>Errore durante l\'eliminazione: ' + response.data.message + '</p>' +
                        '</div>'
                    );
                    
                    // Riabilita il pulsante
                    $('#pnrr-delete-button').prop('disabled', false);
                }
            },
            error: function() {
                // Rimuovi la barra di progresso
                $('#pnrr-delete-progress').hide();
                
                // Mostra errore di connessione
                $('#pnrr-delete-results .results-container').html(
                    '<div class="notice notice-error inline">' +
                    '<p>Errore di connessione durante l\'eliminazione. Riprova.</p>' +
                    '</div>'
                );
                
                // Riabilita il pulsante
                $('#pnrr-delete-button').prop('disabled', false);
            }
        });
    });

    // Gestione dell'identificazione delle pagine clone esistenti
    $('#pnrr-identify-button').on('click', function() {
        // Mostra la barra di progresso e il contenitore dei risultati
        $('#pnrr-identify-progress').show();
        $('#pnrr-identify-results').show().find('.results-container').empty();
        
        // Disabilita il pulsante durante il processo
        $(this).prop('disabled', true);
        
        // Esegui la chiamata AJAX per identificare le pagine clone
        $.ajax({
            url: pnrr_cloner.ajax_url,
            type: 'POST',
            data: {
                action: 'pnrr_mark_existing_clones',
                nonce: pnrr_cloner.nonce
            },
            success: function(response) {
                // Nascondi la barra di progresso
                $('#pnrr-identify-progress .progress-bar-fill').css('width', '100%');
                
                if (response.success) {
                    // Mostra i risultati
                    var result = response.data;
                    var summaryHtml = '<div class="identify-summary">' +
                                     '<p><strong>Identificazione completata:</strong></p>' +
                                     '<ul>' +
                                     '<li>' + result.identified + ' potenziali pagine clone identificate</li>' +
                                     '<li>' + result.updated + ' pagine aggiornate con successo</li>';
                    
                    if (result.skipped > 0) {
                        summaryHtml += '<li>' + result.skipped + ' pagine saltate (già marcate o errori)</li>';
                    }
                    
                    summaryHtml += '</ul></div>';
                    
                    // Mostra i dettagli delle pagine trovate
                    if (result.details && result.details.length > 0) {
                        summaryHtml += '<div class="identify-details">' +
                                      '<p><strong>Dettagli pagine trovate:</strong></p>' +
                                      '<table class="widefat">' +
                                      '<thead><tr><th>ID</th><th>Titolo</th><th>Slug</th><th>Stato</th></tr></thead>' +
                                      '<tbody>';
                        
                        $.each(result.details, function(index, page) {
                            var statusClass = '';
                            if (page.status === 'marcata') {
                                statusClass = 'success';
                            } else if (page.status === 'errore') {
                                statusClass = 'error';
                            } else {
                                statusClass = 'info';
                            }
                            
                            summaryHtml += '<tr class="' + statusClass + '">' +
                                          '<td>' + page.id + '</td>' +
                                          '<td>' + page.title + '</td>' +
                                          '<td>' + page.slug + '</td>' +
                                          '<td>' + page.status + '</td>' +
                                          '</tr>';
                        });
                        
                        summaryHtml += '</tbody></table></div>';
                    }
                    
                    // Aggiornamento conteggio pagine clone se necessario
                    if (result.updated > 0) {
                        var currentCount = parseInt($('.clone-count').text() || 0);
                        $('.clone-count').text(currentCount + result.updated);
                        
                        // Mostra il pulsante di eliminazione se ci sono cloni
                        if (currentCount + result.updated > 0 && $('.delete-info .notice').length === 0) {
                            $('.delete-info').show();
                            $('#pnrr-delete-button').prop('disabled', false);
                        }
                    }
                    
                    $('#pnrr-identify-results .results-container').html(summaryHtml);
                    
                    // Aggiorna stato progresso
                    $('#pnrr-identify-progress .progress-status').text('Identificazione completata');
                    
                    // Attendi un momento e poi nascondi la progress bar
                    setTimeout(function() {
                        $('#pnrr-identify-progress').fadeOut();
                    }, 1500);
                } else {
                    // Mostra errore
                    $('#pnrr-identify-results .results-container').html(
                        '<div class="notice notice-error inline">' +
                        '<p>Errore durante l\'identificazione: ' + response.data.message + '</p>' +
                        '</div>'
                    );
                    
                    // Nascondi la progress bar
                    $('#pnrr-identify-progress').hide();
                }
                
                // Riabilita il pulsante
                $('#pnrr-identify-button').prop('disabled', false);
            },
            error: function() {
                // Nascondi la progress bar
                $('#pnrr-identify-progress').hide();
                
                // Mostra errore
                $('#pnrr-identify-results .results-container').html(
                    '<div class="notice notice-error inline">' +
                    '<p>Errore di connessione durante l\'identificazione. Riprova.</p>' +
                    '</div>'
                );
                
                // Riabilita il pulsante
                $('#pnrr-identify-button').prop('disabled', false);
            }
        });
    });

    // Gestione dell'importazione CSV
    $('#pnrr-import-form').on('submit', function(e) {
        e.preventDefault();
        
        var fileInput = $('#csv-file')[0];
        
        // Verifica se è stato selezionato un file
        if (fileInput.files.length === 0) {
            $('#pnrr-import-feedback').html(
                '<div class="notice notice-error inline">' +
                '<p>Seleziona un file CSV da importare</p>' +
                '</div>'
            ).show();
            return;
        }
        
        // Verifica estensione file
        var fileName = fileInput.files[0].name;
        var fileExt = fileName.split('.').pop().toLowerCase();
        
        if (fileExt !== 'csv') {
            $('#pnrr-import-feedback').html(
                '<div class="notice notice-error inline">' +
                '<p>Il file selezionato non è un CSV valido (.csv)</p>' +
                '</div>'
            ).show();
            return;
        }
        
        // Preparazione FormData per l'upload
        var formData = new FormData();
        formData.append('action', 'pnrr_import_csv');
        formData.append('nonce', pnrr_cloner.nonce);
        formData.append('csv_file', fileInput.files[0]);
        
        // Mostra la barra di progresso
        $('#pnrr-import-progress').show();
        $('#pnrr-import-results').show().find('.results-container').empty();
        
        // Disabilita il form durante il caricamento
        $('#pnrr-import-form button[type="submit"]').prop('disabled', true);
        
        // Esegui la chiamata AJAX per l'importazione
        $.ajax({
            url: pnrr_cloner.ajax_url,
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function(response) {
                // Aggiorna la barra di progresso
                $('#pnrr-import-progress .progress-bar-fill').css('width', '100%');
                
                if (response.success) {
                    // Mostra feedback positivo
                    $('#pnrr-import-feedback').html(
                        '<div class="notice notice-success inline">' +
                        '<p>' + response.data.message + '</p>' +
                        '</div>'
                    ).show();
                    
                    // Aggiorna i risultati
                    $('#pnrr-import-results .results-container').html(
                        '<div class="import-summary">' +
                        '<p><strong>Importazione completata con successo:</strong></p>' +
                        '<ul>' +
                        '<li>' + response.data.imported + ' cloni importati</li>' +
                        '</ul>' +
                        '<p>Ricarica la pagina per vedere i dati aggiornati.</p>' +
                        '</div>'
                    );
                    
                    // Svuota il campo file
                    $('#csv-file').val('');
                } else {
                    // Mostra feedback negativo
                    $('#pnrr-import-feedback').html(
                        '<div class="notice notice-error inline">' +
                        '<p>Errore durante l\'importazione: ' + response.data.message + '</p>' +
                        '</div>'
                    ).show();
                    
                    $('#pnrr-import-results .results-container').html(
                        '<div class="notice notice-error inline">' +
                        '<p>Importazione fallita. Verifica il formato del file e riprova.</p>' +
                        '</div>'
                    );
                }
                
                // Aggiorna stato progresso
                $('#pnrr-import-progress .progress-status').text('Importazione completata');
                
                // Attendi un momento e poi nascondi la progress bar
                setTimeout(function() {
                    $('#pnrr-import-progress').fadeOut();
                }, 1500);
                
                // Riabilita il form
                $('#pnrr-import-form button[type="submit"]').prop('disabled', false);
            },
            error: function() {
                // Nascondi la barra di progresso
                $('#pnrr-import-progress').hide();
                
                // Mostra errore
                $('#pnrr-import-feedback').html(
                    '<div class="notice notice-error inline">' +
                    '<p>Errore di connessione durante l\'importazione. Riprova.</p>' +
                    '</div>'
                ).show();
                
                // Riabilita il form
                $('#pnrr-import-form button[type="submit"]').prop('disabled', false);
            }
        });
    });

    // Tabella cloni - Paginazione, ordinamento e ricerca
    $(document).ready(function() {
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
            
            // Anteprima immagine logo
            $(document).on('click', '.image-preview-link', function(e) {
                e.preventDefault();
                var imageUrl = $(this).data('image');
                if (imageUrl) {
                    $('#preview-image').attr('src', imageUrl);
                    $('#image-preview-modal').show();
                }
            });
            
            // Modifica clone
            $(document).on('click', '.edit-clone', function() {
                var cloneId = $(this).data('id');
                var $row = $('tr[data-id="' + cloneId + '"]');
                
                // Popola il form con i dati del clone
                $('#edit-clone-id').val(cloneId);
                $('#edit-clone-slug').val($row.find('td:eq(0)').text());
                $('#edit-clone-title').val($row.find('td:eq(1)').text());
                
                var homeUrl = $row.find('td:eq(2) a').attr('href') || '';
                $('#edit-clone-home-url').val(homeUrl);
                
                var logoUrl = $row.find('td:eq(3) a').data('image') || '';
                $('#edit-clone-logo-url').val(logoUrl);
                
                // Per il footer, dobbiamo ottenere l'HTML completo dal dataset
                var footerText = $row.data('footer-text') || '';
                $('#edit-clone-footer-text').val(footerText);
                
                // Status
                var isEnabled = !$row.hasClass('disabled');
                $('#edit-clone-enabled').val(isEnabled ? '1' : '0');
                
                // Mostra il modal
                $('#edit-clone-modal').show();
            });
            
            // Salvataggio modifiche clone
            $('#save-clone-button').on('click', function() {
                var formData = $('#edit-clone-form').serialize();
                
                $.ajax({
                    url: pnrr_cloner.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'pnrr_update_clone',
                        nonce: pnrr_cloner.nonce,
                        data: formData
                    },
                    beforeSend: function() {
                        $('#save-clone-button').prop('disabled', true).text('Salvataggio...');
                    },
                    success: function(response) {
                        if (response.success) {
                            // Chiudi il modal
                            $('#edit-clone-modal').hide();
                            
                            // Mostra notifica
                            alert('Clone aggiornato con successo');
                            
                            // Ricarica la pagina per visualizzare i dati aggiornati
                            location.reload();
                        } else {
                            alert('Errore durante il salvataggio: ' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('Errore di connessione durante il salvataggio');
                    },
                    complete: function() {
                        $('#save-clone-button').prop('disabled', false).text('Salva Modifiche');
                    }
                });
            });
            
            // Abilita/disabilita clone
            $(document).on('click', '.toggle-clone', function() {
                var $button = $(this);
                var cloneId = $button.data('id');
                var $row = $('tr[data-id="' + cloneId + '"]');
                var currentState = !$row.hasClass('disabled');
                
                $.ajax({
                    url: pnrr_cloner.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'pnrr_toggle_clone',
                        nonce: pnrr_cloner.nonce,
                        clone_id: cloneId,
                        enabled: !currentState
                    },
                    beforeSend: function() {
                        $button.prop('disabled', true);
                    },
                    success: function(response) {
                        if (response.success) {
                            // Aggiorna lo stato visivo
                            if (currentState) {
                                $row.addClass('disabled');
                                $row.find('.status-indicator')
                                    .removeClass('active')
                                    .addClass('inactive')
                                    .text('Inattivo');
                                $button.find('.dashicons')
                                    .removeClass('dashicons-hidden')
                                    .addClass('dashicons-visibility');
                            } else {
                                $row.removeClass('disabled');
                                $row.find('.status-indicator')
                                    .removeClass('inactive')
                                    .addClass('active')
                                    .text('Attivo');
                                $button.find('.dashicons')
                                    .removeClass('dashicons-visibility')
                                    .addClass('dashicons-hidden');
                            }
                        } else {
                            alert('Errore: ' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('Errore di connessione');
                    },
                    complete: function() {
                        $button.prop('disabled', false);
                    }
                });
            });
            
            // Chiusura modali
            $('.pnrr-modal-close').on('click', function() {
                $(this).closest('.pnrr-modal').hide();
            });
            
            $(window).on('click', function(event) {
                if ($(event.target).hasClass('pnrr-modal')) {
                    $('.pnrr-modal').hide();
                }
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
    });

    // Gestione del selettore media e modifica clone
    $(document).ready(function() {
        let mediaUploader = null;
        
        // Inizializzazione del selettore media
        $('#select-logo-button').on('click', function(e) {
            e.preventDefault();
            
            // Se l'uploader esiste già, apri direttamente
            if (mediaUploader) {
                mediaUploader.open();
                return;
            }
            
            // Crea il media uploader
            mediaUploader = wp.media({
                title: 'Seleziona o carica un logo',
                button: {
                    text: 'Usa questa immagine'
                },
                multiple: false
            });
            
            // Quando un'immagine viene selezionata
            mediaUploader.on('select', function() {
                const attachment = mediaUploader.state().get('selection').first().toJSON();
                $('#edit-clone-logo-url').val(attachment.url);
                
                // Mostra anteprima
                $('#logo-preview').attr('src', attachment.url).show();
                
                // Mostra il pulsante di rimozione
                $('#remove-logo-button').show();
            });
            
            mediaUploader.open();
        });
        
        // Rimozione del logo
        $('#remove-logo-button').on('click', function(e) {
            e.preventDefault();
            $('#edit-clone-logo-url').val('');
            $('#logo-preview').attr('src', '').hide();
            $(this).hide();
        });
        
        // Apertura del modale di modifica con tutti i dati
        $(document).on('click', '.edit-clone', function() {
            var cloneId = $(this).data('id');
            var $row = $('tr[data-id="' + cloneId + '"]');
            
            // Popola il form con i dati del clone
            $('#edit-clone-id').val(cloneId);
            $('#edit-clone-slug').val($row.find('td:eq(0)').text().trim());
            $('#edit-clone-title').val($row.find('td:eq(1)').text().trim());
            
            // Home URL
            var homeUrl = '';
            var $homeUrlLink = $row.find('td:eq(2) a');
            if ($homeUrlLink.length > 0) {
                homeUrl = $homeUrlLink.attr('href');
            }
            $('#edit-clone-home-url').val(homeUrl);
            
            // Logo URL
            var logoUrl = '';
            var $logoLink = $row.find('td:eq(3) a');
            if ($logoLink.length > 0) {
                logoUrl = $logoLink.data('image');
                
                // Mostra anteprima
                $('#logo-preview').attr('src', logoUrl).show();
                $('#remove-logo-button').show();
            } else {
                $('#logo-preview').hide();
                $('#remove-logo-button').hide();
            }
            $('#edit-clone-logo-url').val(logoUrl);
            
            // Footer Text - Recupera il valore completo dal data attribute
            var footerText = $row.data('footer-text') || '';
            if (!footerText) {
                // Se non è nell'attributo, prendiamo il testo della cella (potrebbe essere troncato)
                footerText = $row.find('td:eq(4)').text().trim();
                if (footerText === '-') footerText = '';
            }
            $('#edit-clone-footer-text').val(footerText);
            
            // Stato
            var isEnabled = $row.find('.status-indicator').hasClass('active');
            $('#edit-clone-enabled').val(isEnabled ? '1' : '0');
            
            // Mostra il modal
            $('#edit-clone-modal').show();
        });
        
        // Salvataggio modifiche clone con feedback migliorato
        $('#save-clone-button').on('click', function() {
            // Validazione base
            var slug = $('#edit-clone-slug').val().trim();
            var title = $('#edit-clone-title').val().trim();
            
            if (!slug || !title) {
                alert('I campi Slug e Titolo sono obbligatori.');
                return;
            }
            
            var formData = $('#edit-clone-form').serialize();
            
            $.ajax({
                url: pnrr_cloner.ajax_url,
                type: 'POST',
                data: {
                    action: 'pnrr_update_clone',
                    nonce: pnrr_cloner.nonce,
                    data: formData
                },
                beforeSend: function() {
                    // Mostra spinner e disabilita pulsante
                    $('#edit-clone-spinner').addClass('is-active');
                    $('#save-clone-button').prop('disabled', true);
                },
                success: function(response) {
                    if (response.success) {
                        // Aggiorna direttamente la riga della tabella senza ricaricare la pagina
                        var cloneId = $('#edit-clone-id').val();
                        var $row = $('tr[data-id="' + cloneId + '"]');
                        
                        // Aggiorna i dati visibili nella tabella
                        $row.find('td:eq(0)').text(slug);
                        $row.find('td:eq(1)').text(title);
                        
                        // Aggiorna Home URL
                        var homeUrl = $('#edit-clone-home-url').val();
                        var $homeCell = $row.find('td:eq(2)');
                        if (homeUrl) {
                            if ($homeCell.find('a').length > 0) {
                                $homeCell.find('a').attr('href', homeUrl).text(homeUrl);
                            } else {
                                $homeCell.html('<a href="' + homeUrl + '" target="_blank">' + homeUrl + '</a>');
                            }
                        } else {
                            $homeCell.html('<span class="not-set">-</span>');
                        }
                        
                        // Aggiorna Logo URL
                        var logoUrl = $('#edit-clone-logo-url').val();
                        var $logoCell = $row.find('td:eq(3)');
                        if (logoUrl) {
                            if ($logoCell.find('a').length > 0) {
                                $logoCell.find('a').attr('href', logoUrl).data('image', logoUrl);
                            } else {
                                $logoCell.html('<a href="' + logoUrl + '" target="_blank" class="image-preview-link" data-image="' + logoUrl + '">' +
                                               '<span class="dashicons dashicons-format-image"></span> Anteprima</a>');
                            }
                        } else {
                            $logoCell.html('<span class="not-set">-</span>');
                        }
                        
                        // Aggiorna Footer
                        var footerText = $('#edit-clone-footer-text').val();
                        var $footerCell = $row.find('td:eq(4)');
                        if (footerText) {
                            var excerpt = $('<div>').html(footerText).text();
                            excerpt = excerpt.length > 50 ? excerpt.substring(0, 50) + '...' : excerpt;
                            $footerCell.text(excerpt);
                            // Salva anche il testo completo nell'attributo data
                            $row.data('footer-text', footerText);
                        } else {
                            $footerCell.html('<span class="not-set">-</span>');
                        }
                        
                        // Aggiorna stato
                        var enabled = $('#edit-clone-enabled').val() === '1';
                        if (enabled && $row.hasClass('disabled')) {
                            $row.removeClass('disabled');
                            $row.find('.status-indicator')
                                .removeClass('inactive')
                                .addClass('active')
                                .text('Attivo');
                            $row.find('.toggle-clone .dashicons')
                                .removeClass('dashicons-visibility')
                                .addClass('dashicons-hidden');
                        } else if (!enabled && !$row.hasClass('disabled')) {
                            $row.addClass('disabled');
                            $row.find('.status-indicator')
                                .removeClass('active')
                                .addClass('inactive')
                                .text('Inattivo');
                            $row.find('.toggle-clone .dashicons')
                                .removeClass('dashicons-hidden')
                                .addClass('dashicons-visibility');
                        }
                        
                        // Chiudi il modal e mostra notifica
                        $('#edit-clone-modal').hide();
                        
                        // Mostra notifica
                        $('<div class="notice notice-success is-dismissible"><p>Clone aggiornato con successo!</p></div>')
                            .insertAfter('.pnrr-data-section h2')
                            .delay(3000)
                            .fadeOut(function() {
                                $(this).remove();
                            });
                    } else {
                        alert('Errore durante il salvataggio: ' + response.data.message);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    alert('Errore di connessione durante il salvataggio: ' + textStatus);
                    console.error(errorThrown);
                },
                complete: function() {
                    // Nascondi spinner e riabilita pulsante
                    $('#edit-clone-spinner').removeClass('is-active');
                    $('#save-clone-button').prop('disabled', false);
                }
            });
        });
    });

    // Gestione della sincronizzazione dei dati dei cloni
    $(document).ready(function() {
        // Gestione del click sul pulsante di sincronizzazione
        $('#pnrr-sync-button').on('click', function() {
            // Ottieni l'opzione di rimozione
            var removeOption = $('#sync-remove-option').is(':checked');
            
            // Mostra la barra di progresso
            $('#pnrr-sync-progress').show();
            $('#pnrr-sync-progress .progress-bar-fill').css('width', '0%');
            $('#pnrr-sync-progress .progress-status').text('Sincronizzazione in corso...');
            $('#pnrr-sync-feedback').hide();
            
            // Disabilita il pulsante durante la sincronizzazione
            $(this).prop('disabled', true);
            
            // Esegui la chiamata AJAX per la sincronizzazione
            $.ajax({
                url: pnrr_cloner.ajax_url,
                type: 'POST',
                data: {
                    action: 'pnrr_sync_clone_data',
                    nonce: pnrr_cloner.nonce,
                    mark_only: !removeOption  // Se removeOption è true, mark_only sarà false
                },
                beforeSend: function() {
                    // Aggiorna barra di progresso
                    $('#pnrr-sync-progress .progress-bar-fill').css('width', '50%');
                },
                success: function(response) {
                    // Aggiorna la barra di progresso
                    $('#pnrr-sync-progress .progress-bar-fill').css('width', '100%');
                    
                    if (response.success) {
                        // Prepara il report di sincronizzazione
                        var result = response.data.result;
                        var reportHtml = '<div class="sync-report">' +
                                        '<div class="sync-report-title">Sincronizzazione completata</div>' +
                                        '<div class="sync-stats">' +
                                        '<span class="sync-stat-item">Totale cloni: ' + result.total + '</span>' +
                                        '<span class="sync-stat-item">Pagine esistenti: ' + result.existing + '</span>';
                                        
                        if (result.discovered && result.discovered > 0) {
                            reportHtml += '<span class="sync-stat-item highlight">Pagine scoperte: ' + result.discovered + '</span>';
                        }
                        
                        reportHtml += '<span class="sync-stat-item">Pagine mancanti: ' + result.missing + '</span>' +
                                      '<span class="sync-stat-item">Record aggiornati: ' + result.updated + '</span>' +
                                      '</div>';
                        
                        if (removeOption) {
                            reportHtml += '<p>I record dei cloni le cui pagine non esistono più sono stati rimossi.</p>';
                        } else {
                            reportHtml += '<p>I cloni le cui pagine non esistono più sono stati contrassegnati come "Eliminati".</p>';
                        }
                        
                        if (result.discovered && result.discovered > 0) {
                            reportHtml += '<p><strong>Nota:</strong> Sono state scoperte ' + result.discovered + ' nuove pagine clone che non erano presenti nei dati.</p>';
                        }
                        
                        reportHtml += '</div>';
                        
                        // Mostra il report
                        $('#pnrr-sync-feedback').html(reportHtml).show();
                        
                        // Aggiorna lo stato della progress bar
                        $('#pnrr-sync-progress .progress-status').text('Sincronizzazione completata!');
                        
                        // Ricarica la pagina dopo un breve ritardo
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        // Mostra messaggio di errore
                        $('#pnrr-sync-feedback').html(
                            '<div class="notice notice-error inline">' +
                            '<p>Errore: ' + response.data.message + '</p>' +
                            '</div>'
                        ).show();
                        
                        // Aggiorna lo stato della progress bar
                        $('#pnrr-sync-progress .progress-status').text('Sincronizzazione fallita');
                    }
                },
                error: function() {
                    // Mostra errore di connessione
                    $('#pnrr-sync-feedback').html(
                        '<div class="notice notice-error inline">' +
                        '<p>Errore di connessione durante la sincronizzazione</p>' +
                        '</div>'
                    ).show();
                    
                    // Nascondi la progress bar
                    $('#pnrr-sync-progress').hide();
                },
                complete: function() {
                    // Riabilita il pulsante
                    $('#pnrr-sync-button').prop('disabled', false);
                    
                    // Nascondi la progress bar dopo un breve ritardo
                    setTimeout(function() {
                        $('#pnrr-sync-progress').fadeOut();
                    }, 1500);
                }
            });
        });
    });

    // Gestione della casella di controllo "Mostra cloni eliminati"
    $(document).ready(function() {
        // Gestione del cambio di stato della casella
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
    });
});