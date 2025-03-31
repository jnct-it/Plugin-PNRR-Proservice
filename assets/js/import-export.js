/**
 * Gestione importazione ed esportazione dati
 */
function initImportExport($) {
    'use strict';
    
    // Gestione dell'importazione CSV
    $('#pnrr-import-form').on('submit', function(e) {
        e.preventDefault();
        
        var fileInput = $('#csv-file')[0];
        var createPages = $('#create-pages-checkbox').is(':checked');
        
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
        formData.append('create_pages', createPages ? '1' : '0');
        
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
                    var resultsHtml = '<div class="import-summary">' +
                        '<p><strong>Importazione completata con successo:</strong></p>' +
                        '<ul>' +
                        '<li>' + response.data.imported + ' cloni importati</li>';
                    
                    if (response.data.pages_created > 0) {
                        resultsHtml += '<li>' + response.data.pages_created + ' pagine create</li>';
                    }
                    
                    resultsHtml += '</ul>';
                    
                    // Se le pagine non sono state create, mostra il pulsante di generazione
                    if (response.data.show_generate_button) {
                        resultsHtml += '<div class="generate-pages-section">' +
                            '<p>Le configurazioni dei cloni sono state importate, ma le pagine non sono ancora state create.</p>' +
                            '<button type="button" id="pnrr-generate-pages-btn" class="button button-primary">' +
                            'Genera le pagine dei cloni ora</button>' +
                            '</div>';
                    }
                    
                    resultsHtml += '<p>Ricarica la pagina per vedere i dati aggiornati.</p>' +
                        '</div>';
                    
                    $('#pnrr-import-results .results-container').html(resultsHtml);
                    
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
    
    // Gestione del pulsante di generazione pagine dopo importazione
    $(document).on('click', '#pnrr-generate-pages-btn', function() {
        $(this).prop('disabled', true).text('Generazione pagine in corso...');
        
        // Avvia il processo di clonazione
        $('#pnrr-clone-button').trigger('click');
        
        // Scrollare alla sezione di clonazione
        $('html, body').animate({
            scrollTop: $('#pnrr-clone-results').offset().top - 50
        }, 500);
    });
}