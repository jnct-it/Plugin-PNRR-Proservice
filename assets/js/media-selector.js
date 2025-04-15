/**
 * Gestione del selettore media e funzioni correlate all'editing dei cloni
 */
function initMediaSelector($) {
    'use strict';
    
    let mediaUploader = null;
    
    // Media frame per la selezione del logo
    var logoMediaFrame;
    
    // Inizializzazione del selettore media
    $('#select-logo-button').on('click', function(e) {
        e.preventDefault();
        
        // Se l'uploader esiste già, apri direttamente
        if (logoMediaFrame) {
            logoMediaFrame.open();
            return;
        }
        
        // Crea il media uploader
        logoMediaFrame = wp.media({
            title: 'Seleziona o carica un logo',
            button: {
                text: 'Usa questa immagine'
            },
            multiple: false
        });
        
        // Quando un'immagine viene selezionata
        logoMediaFrame.on('select', function() {
            const attachment = logoMediaFrame.state().get('selection').first().toJSON();
            $('#edit-clone-logo-url').val(attachment.url);
            
            // Mostra anteprima
            $('#logo-preview').attr('src', attachment.url).show();
            
            // Mostra il pulsante di rimozione
            $('#remove-logo-button').show();
        });
        
        logoMediaFrame.open();
    });
    
    // Rimozione del logo
    $('#remove-logo-button').on('click', function(e) {
        e.preventDefault();
        $('#edit-clone-logo-url').val('');
        $('#logo-preview').attr('src', '').hide();
        $(this).hide();
    });
    
    // Anteprima delle immagini nella tabella
    $(document).on('click', '.image-preview-link', function(e) {
        e.preventDefault();
        var imageUrl = $(this).data('image');
        if (imageUrl) {
            $('#preview-image').attr('src', imageUrl);
            $('#image-preview-modal').show();
        }
    });
    
    // Chiusura modal di anteprima
    $('.pnrr-modal-close, .pnrr-modal-close-button').on('click', function() {
        $(this).closest('.pnrr-modal').hide();
    });
    
    // Chiusura modal cliccando fuori
    $(window).on('click', function(event) {
        if ($(event.target).hasClass('pnrr-modal')) {
            $('.pnrr-modal').hide();
        }
    });
    
    // Modifica clone - apertura modale
    $(document).on('click', '.edit-clone', function() {
        var cloneId = $(this).data('id');
        var $row = $('tr[data-id="' + cloneId + '"]');
        
        console.log("Apertura modale per il clone ID:", cloneId); // Debug
        
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
        
        // Campi aggiuntivi
        // Estrai i valori direttamente dagli attributi data
        var address = $row.attr('data-address') || '';
        var contacts = $row.attr('data-contacts') || '';
        var otherInfo = $row.attr('data-other-info') || '';
        
        console.log("Debug - Attributi data recuperati:");
        console.log("data-address:", $row.attr('data-address'));
        console.log("data-contacts:", $row.attr('data-contacts'));
        console.log("data-other-info:", $row.attr('data-other-info'));
        
        // Popola i campi
        $('#edit-clone-address').val(address);
        $('#edit-clone-contacts').val(contacts);
        $('#edit-clone-other-info').val(otherInfo);
        
        // Stato
        var isEnabled = $row.find('.status-indicator').hasClass('active');
        $('#edit-clone-enabled').val(isEnabled ? '1' : '0');
        
        // Mostra il modal
        $('#edit-clone-modal').show();
    });
    
    // Salvataggio modifiche clone
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
                    
                    // Aggiorna campi aggiuntivi nei data attributes
                    $row.attr('data-address', $('#edit-clone-address').val());
                    $row.attr('data-contacts', $('#edit-clone-contacts').val());
                    $row.attr('data-other-info', $('#edit-clone-other-info').val());
                    
                    // Aggiorna la visualizzazione dell'indirizzo nella tabella
                    var address = $('#edit-clone-address').val();
                    var $addressCell = $row.find('td:eq(4)');
                    if (address) {
                        var excerpt = $('<div>').html(address).text();
                        excerpt = excerpt.length > 50 ? excerpt.substring(0, 50) + '...' : excerpt;
                        $addressCell.text(excerpt);
                    } else {
                        $addressCell.html('<span class="not-set">-</span>');
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
                    
                    // Mostra notifica di successo
                    $('<div class="notice notice-success is-dismissible"><p>Clone aggiornato con successo!</p></div>')
                        .insertAfter('.pnrr-data-section h2')
                        .delay(3000)
                        .fadeOut(function() {
                            $(this).remove();
                        });
                } else {
                    // Mostra errore
                    alert('Errore durante il salvataggio: ' + response.data.message);
                }
            },
            error: function() {
                alert('Errore di connessione durante il salvataggio');
            },
            complete: function() {
                // Nascondi spinner e riabilita pulsante
                $('#edit-clone-spinner').removeClass('is-active');
                $('#save-clone-button').prop('disabled', false);
            }
        });
    });
    
    // Toggle abilitazione/disabilitazione di un clone
    $(document).on('click', '.toggle-clone', function() {
        var $button = $(this);
        var cloneId = $button.data('id');
        var $row = $('tr[data-id="' + cloneId + '"]');
        var currentlyEnabled = !$row.hasClass('disabled');
        
        $.ajax({
            url: pnrr_cloner.ajax_url,
            type: 'POST',
            data: {
                action: 'pnrr_toggle_clone',
                nonce: pnrr_cloner.nonce,
                clone_id: cloneId,
                enabled: !currentlyEnabled
            },
            beforeSend: function() {
                $button.prop('disabled', true);
            },
            success: function(response) {
                if (response.success) {
                    // Aggiorna lo stato visivo della riga
                    if (currentlyEnabled) {
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
}