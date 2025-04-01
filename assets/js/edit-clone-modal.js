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
        updateLogoPreview();
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
