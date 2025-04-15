/**
 * Script per la gestione dell'UI avanzata del modale di modifica cloni
 */
jQuery(document).ready(function($) {
    'use strict';
    
    // Tab navigation functionality
    $('.tab-button').on('click', function() {
        var tabId = $(this).data('tab');
        
        // Update active tab button
        $('.tab-button').removeClass('active');
        $(this).addClass('active');
        
        // Show corresponding tab content
        $('.tab-content').removeClass('active');
        $('#' + tabId).addClass('active');
    });
    
    // Toggle switch functionality
    $('#edit-clone-enabled-toggle').on('change', function() {
        var isChecked = $(this).is(':checked');
        $('#edit-clone-enabled').val(isChecked ? '1' : '0');
        $('#toggle-status').text(isChecked ? 'Attivo' : 'Inattivo');
    });
    
    // Logo preview functionality
    $('#edit-clone-logo-url').on('input', function() {
        updateLogoPreview();
    });
    
    function updateLogoPreview() {
        var url = $('#edit-clone-logo-url').val().trim();
        if (url) {
            $('#logo-preview').attr('src', url).show();
            $('#logo-placeholder').hide();
            $('#remove-logo-button').show();
        } else {
            $('#logo-preview').hide();
            $('#logo-placeholder').show();
            $('#remove-logo-button').hide();
        }
    }
    
    // Remove logo button
    $('#remove-logo-button').on('click', function() {
        $('#edit-clone-logo-url').val('');
        $('#logo-preview').attr('src', '').hide();
        $(this).hide();
    });
    
    // Form validation
    $('#edit-clone-slug').on('input', function() {
        var slug = $(this).val().trim();
        var validSlug = /^[a-z0-9-]+$/.test(slug);
        
        if (!validSlug && slug) {
            $('#slug-validation').text('Lo slug può contenere solo lettere minuscole, numeri e trattini.');
        } else {
            $('#slug-validation').text('');
        }
    });
    
    // Ensure proper initialization when opening modal
    $(document).on('click', '.edit-clone', function() {
        var cloneId = $(this).data('id');
        var $row = $('tr[data-id="' + cloneId + '"]');
        
        console.log("Apertura modale per il clone ID:", cloneId);
        
        // Popola il form con i dati del clone
        $('#edit-clone-id').val(cloneId);
        $('#edit-clone-slug').val($row.find('td:eq(0) a').text().trim());
        $('#edit-clone-title').val($row.find('td:eq(1)').text().trim());
        
        // Home URL
        var homeUrl = '';
        var $homeUrlLink = $row.find('td:eq(2) a');
        if ($homeUrlLink.length > 0) {
            homeUrl = $homeUrlLink.attr('href');
        }
        $('#edit-clone-home-url').val(homeUrl);
        
        // CUP - La colonna CUP è la quarta (indice 3)
        var cupValue = $row.find('td:eq(3)').text().trim();
        if (cupValue === '-') {
            cupValue = '';
        }
        $('#edit-clone-cup').val(cupValue);
        
        // Logo URL - La quinta colonna (indice 4) contiene il logo
        var logoUrl = '';
        var $logoCell = $row.find('td:eq(4)');
        var $logoLink = $logoCell.find('a.image-preview-link');
        
        console.log("URL Logo - Elemento trovato:", $logoLink.length > 0 ? "Sì" : "No");
        
        if ($logoLink.length > 0) {
            logoUrl = $logoLink.data('image');
            console.log("URL Logo recuperato dalla cella:", logoUrl);
            
            // Mostra anteprima
            if (logoUrl) {
                $('#logo-preview').attr('src', logoUrl).show();
                $('#remove-logo-button').show();
            }
        }
        
        // ***MODIFICATO: Imposta il valore del campo direttamente e con un breve ritardo
        // per evitare sovrascritture da altri script
        setTimeout(function() {
            $('#edit-clone-logo-url').val(logoUrl);
            console.log("URL Logo impostato sul campo:", logoUrl);
        }, 100);
        
        // Address dalla data attribute
        var address = $row.data('address') || '';
        $('#edit-clone-address').val(address);
        console.log("Indirizzo recuperato:", address);
        
        // Contacts dalla data attribute
        var contacts = $row.data('contacts') || '';
        $('#edit-clone-contacts').val(contacts);
        console.log("Contatti recuperati:", contacts);
        
        // Other info dalla data attribute
        var otherInfo = $row.data('other-info') || '';
        $('#edit-clone-other-info').val(otherInfo);
        console.log("Altre info recuperate:", otherInfo);
        
        // Status
        var isEnabled = !$row.hasClass('disabled');
        $('#edit-clone-enabled').val(isEnabled ? '1' : '0');
        
        // Mostra il modal
        $('#edit-clone-modal').show();
        
        // ***AGGIUNTO: Verifica dopo che il modal è visibile se il campo è stato valorizzato
        setTimeout(function() {
            console.log("Verifica valore URL Logo dopo apertura modal:", $('#edit-clone-logo-url').val());
            // Se ancora vuoto, prova a impostarlo nuovamente
            if (!$('#edit-clone-logo-url').val() && logoUrl) {
                $('#edit-clone-logo-url').val(logoUrl);
                console.log("URL Logo reimpostato dopo apertura modal:", logoUrl);
            }
        }, 300);
        
        // Reset tab state
        $('.tab-button').removeClass('active');
        $('.tab-button[data-tab="basic-info"]').addClass('active');
        $('.tab-content').removeClass('active');
        $('#basic-info').addClass('active');
        
        // Update toggle switch state based on the hidden value field
        var enabled = $('#edit-clone-enabled').val() === '1';
        $('#edit-clone-enabled-toggle').prop('checked', enabled);
        $('#toggle-status').text(enabled ? 'Attivo' : 'Inattivo');
        
        // Update logo preview
        updateLogoPreview();
    });

    // Close modal buttons
    $('.pnrr-modal-close, .pnrr-modal-close-button').on('click', function() {
        $(this).closest('.pnrr-modal').hide();
    });

    // Close modal when clicking outside
    $(window).on('click', function(event) {
        if ($(event.target).hasClass('pnrr-modal')) {
            $('.pnrr-modal').hide();
        }
    });
});
