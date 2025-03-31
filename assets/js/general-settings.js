/**
 * Gestione delle impostazioni generali
 */
function initGeneralSettings($) {
    'use strict';
    
    // Gestione del salvataggio delle impostazioni generali
    $('#save-general-settings').on('click', function(e) {
        e.preventDefault();
        
        var numberOfClones = $('#number-of-clones').val();
        var feedbackEl = $('#general-settings-feedback');
        
        // Validazione
        if (!numberOfClones || numberOfClones < 1) {
            feedbackEl.html('<div class="notice notice-error inline"><p>Inserisci un numero valido (minimo 1)</p></div>')
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
                action: 'pnrr_save_general_settings',
                nonce: pnrr_cloner.nonce,
                number_of_clones: numberOfClones
            },
            success: function(response) {
                if (response.success) {
                    feedbackEl.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>')
                              .show().delay(3000).fadeOut();
                    
                    // Aggiorna il testo informativo nella pagina
                    $('.pnrr-cloner-info p:first').text('Questo plugin ti permette di clonare una pagina master Elementor e creare ' + response.data.number_of_clones + ' versioni con percorsi e contenuti personalizzati.');
                    
                    // Aggiorna testo nella barra di progresso
                    $('.progress-status').text('0 / ' + response.data.number_of_clones + ' pagine clonate');
                } else {
                    feedbackEl.html('<div class="notice notice-error inline"><p>Errore: ' + response.data.message + '</p></div>')
                              .show();
                }
                
                // Riabilita il pulsante
                $('#save-general-settings').prop('disabled', false).text('Salva Impostazioni');
            },
            error: function() {
                feedbackEl.html('<div class="notice notice-error inline"><p>Errore di connessione</p></div>')
                          .show();
                $('#save-general-settings').prop('disabled', false).text('Salva Impostazioni');
            }
        });
    });
    
    // Validazione dell'input per il numero di cloni
    $('#number-of-clones').on('input', function() {
        var value = $(this).val();
        if (value && parseInt(value) > 1000) {
            $(this).val(1000);
        } else if (value && parseInt(value) < 1) {
            $(this).val(1);
        }
    });
}