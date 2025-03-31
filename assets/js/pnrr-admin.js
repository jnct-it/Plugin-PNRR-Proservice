/**
 * JavaScript principale per la gestione dell'interfaccia admin del plugin PNRR Page Cloner
 * Coordina tutti i moduli JS separati
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Verifica che l'oggetto pnrr_cloner sia disponibile
    if (typeof pnrr_cloner === 'undefined') {
        console.error('Errore: oggetto pnrr_cloner non disponibile');
        return;
    }
    
    // Inizializza i moduli solo se le loro funzioni di inizializzazione esistono
    if (typeof initMasterPage === 'function') {
        initMasterPage($);
    }
    
    if (typeof initCloneProcess === 'function') {
        initCloneProcess($);
    }
    
    if (typeof initDeleteProcess === 'function') {
        initDeleteProcess($);
    }
    
    if (typeof initIdentifyProcess === 'function') {
        initIdentifyProcess($);
    }
    
    if (typeof initImportExport === 'function') {
        initImportExport($);
    }
    
    if (typeof initTableManagement === 'function') {
        initTableManagement($);
    }
    
    if (typeof initMediaSelector === 'function') {
        initMediaSelector($);
    }
    
    if (typeof initSyncProcess === 'function') {
        initSyncProcess($);
    }
    
    if (typeof initGeneralSettings === 'function') {
        initGeneralSettings($);
    }
    
    console.log('PNRR Admin JS inizializzato con successo');
});