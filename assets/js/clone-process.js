/**
 * Gestione del processo di clonazione delle pagine
 */
function initCloneProcess($) {
    'use strict';
    
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
}