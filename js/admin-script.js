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
                nonce: pnrr_cloner.nonce
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
});