/**
 * Gestione della sincronizzazione dei dati dei cloni
 */
function initSyncProcess($) {
    'use strict';
    
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
}