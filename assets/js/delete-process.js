/**
 * Gestione dell'eliminazione delle pagine clone
 */
function initDeleteProcess($) {
    'use strict';
    
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
}