/**
 * Gestione dell'identificazione delle pagine clone esistenti
 */
function initIdentifyProcess($) {
    'use strict';
    
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
}