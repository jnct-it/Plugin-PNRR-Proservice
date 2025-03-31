/**
 * Gestione della pagina master
 */
function initMasterPage($) {
    'use strict';
    
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
}