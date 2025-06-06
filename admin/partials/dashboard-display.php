<?php
/**
 * Template per la pagina principale del plugin
 *
 * @since 1.0.0
 */

// Assicurarsi che il plugin non sia accessibile direttamente
if (!defined('ABSPATH')) {
    exit;
}

// Ottieni le istanze dalle variabili globali
global $pnrr_plugin;

// Verifica se le istanze principali sono disponibili
if (!isset($pnrr_plugin['core']) || !is_object($pnrr_plugin['core']) ||
    !isset($pnrr_plugin['clone_manager']) || !is_object($pnrr_plugin['clone_manager'])) {
    echo '<div class="error"><p>Errore: Istanze principali del plugin non trovate.</p></div>';
    return;
}

$core = $pnrr_plugin['core'];
$clone_manager = $pnrr_plugin['clone_manager'];

// Ottieni le opzioni del plugin
$plugin_options = pnrr_get_option();
$number_of_clones = isset($plugin_options['number_of_clones']) ? $plugin_options['number_of_clones'] : 75;

// Ottieni le pagine Elementor disponibili
$elementor_pages = $core->get_elementor_pages();

// Ottieni l'ID della pagina master selezionata
$master_page_id = $core->get_master_page_id();
$master_page = null;
$source_page_name = '';

if ($master_page_id && isset($elementor_pages[$master_page_id])) {
    $master_page = get_post($master_page_id);
    $source_page_name = $master_page->post_name;
}

// Ottieni il conteggio delle pagine clone
$clone_count = 0;
$args = array(
    'post_type' => 'page',
    'post_status' => array('publish', 'draft', 'trash'),
    'posts_per_page' => -1,
    'meta_query' => array(
        array(
            'key' => '_pnrr_is_clone',
            'value' => 'yes',
            'compare' => '='
        )
    ),
    'fields' => 'ids'
);
$clone_query = new WP_Query($args);
$clone_count = $clone_query->found_posts;
wp_reset_postdata();

// Sincronizza i dati automaticamente prima di visualizzare la tabella
// Questa sincronizzazione viene già eseguita nel metodo admin_page_display
// quindi usiamo i risultati di quella sincronizzazione
$sync_results = isset($sync_results) ? $sync_results : array();

// Se ci sono risultati della sincronizzazione automatica con nuove pagine scoperte, mostra una notifica
if (!empty($sync_results) && isset($sync_results['discovered']) && $sync_results['discovered'] > 0) {
    echo '<div class="notice notice-info inline is-dismissible">';
    echo '<p>Sono state scoperte <strong>' . intval($sync_results['discovered']) . '</strong> nuove pagine clone durante la sincronizzazione automatica.</p>';
    echo '</div>';
}
?>

<div class="wrap">
    <h1>PNRR Page Cloner</h1>
    
    <div class="pnrr-cloner-container">
        <div class="pnrr-cloner-info">
            <p>Questo plugin ti permette di clonare una pagina master Elementor e creare <?php echo intval($number_of_clones); ?> versioni con percorsi e contenuti personalizzati.</p>
            <p>Versione del plugin: <?php echo esc_html(PNRR_VERSION); ?></p>
        </div>
        
        <!-- Sezione per la configurazione generale -->
        <div class="pnrr-general-settings">
            <h2>Configurazione Generale</h2>
            
            <div class="settings-form">
                <div class="form-row">
                    <label for="number-of-clones">Numero di cloni da generare:</label>
                    <input type="number" id="number-of-clones" name="number_of_clones" min="1" max="1000" value="<?php echo intval($number_of_clones); ?>" class="small-text">
                    <button id="save-general-settings" class="button button-primary">Salva Impostazioni</button>
                </div>
                <div id="general-settings-feedback" style="display: none;"></div>
                <p class="description">Imposta il numero di cloni che verranno generati quando utilizzi il pulsante "Clona Pagina Master".</p>
            </div>
        </div>
        
        <hr>
        
        <!-- Sezione per la selezione della pagina master -->
        <div class="pnrr-master-selection">
            <h2>Seleziona Pagina Master</h2>
            
            <?php if (empty($elementor_pages)) : ?>
                <div class="notice notice-warning inline">
                    <p>Non sono state trovate pagine create con Elementor. Crea almeno una pagina con Elementor per utilizzare questo plugin.</p>
                </div>
            <?php else : ?>
                <div class="master-page-form">
                    <div class="form-row">
                        <label for="master-page-select">Seleziona una pagina Elementor:</label>
                        <select id="master-page-select" name="master_page_id">
                            <option value="">-- Seleziona una pagina --</option>
                            <?php foreach ($elementor_pages as $id => $title) : ?>
                                <option value="<?php echo esc_attr($id); ?>" <?php selected($master_page_id, $id); ?>>
                                    <?php echo esc_html($title); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button id="save-master-page" class="button button-primary">Salva Selezione</button>
                    </div>
                    <div id="master-page-feedback" style="display: none;"></div>
                </div>
                
                <?php if ($master_page) : ?>
                    <div class="master-page-info">
                        <h3>Pagina Master Selezionata</h3>
                        <table class="form-table">
                            <tr>
                                <th>Titolo:</th>
                                <td><?php echo esc_html($master_page->post_title); ?></td>
                            </tr>
                            <tr>
                                <th>Slug:</th>
                                <td><?php echo esc_html($master_page->post_name); ?></td>
                            </tr>
                            <tr>
                                <th>Azioni:</th>
                                <td>
                                    <a href="<?php echo esc_url(get_permalink($master_page_id)); ?>" target="_blank" class="button button-secondary">
                                        <span class="dashicons dashicons-visibility" style="vertical-align: text-top;"></span> Visualizza
                                    </a>
                                    <a href="<?php echo esc_url(admin_url('post.php?post=' . $master_page_id . '&action=edit')); ?>" target="_blank" class="button button-secondary">
                                        <span class="dashicons dashicons-edit" style="vertical-align: text-top;"></span> Modifica
                                    </a>
                                </td>
                            </tr>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <hr>
        
        <!-- Sezione per la clonazione delle pagine -->
        <div class="pnrr-cloner-actions">
            <h2>Clonazione Pagine</h2>
            
            <?php if (!$master_page_id) : ?>
                <div class="notice notice-warning inline">
                    <p>Seleziona prima una pagina master per abilitare la clonazione.</p>
                </div>
            <?php endif; ?>
            
            <button id="pnrr-clone-button" class="button button-primary" <?php echo !$master_page_id ? 'disabled' : ''; ?>>
                Clona Pagina Master
            </button>
            
            <div id="pnrr-clone-progress" style="display: none;">
                <div class="progress-bar">
                    <div class="progress-bar-fill" style="width: 0%;"></div>
                </div>
                <div class="progress-status">0 / <?php echo intval($number_of_clones); ?> pagine clonate</div>
            </div>
        </div>
        
        <div id="pnrr-clone-results" style="display: none;">
            <h2>Risultati Clonazione</h2>
            <div class="results-container"></div>
        </div>
        
        <hr>
        
        <!-- Sezione per l'eliminazione delle pagine clone -->
        <div class="pnrr-deletion-section">
            <h2>Eliminazione Pagine Clone</h2>
            
            <?php if ($clone_count > 0) : ?>
                <div class="delete-info">
                    <p>
                        <strong>Attenzione:</strong> Al momento ci sono <span class="clone-count"><?php echo intval($clone_count); ?></span> pagine clone.
                        L'eliminazione è permanente e non può essere annullata.
                    </p>
                    <button id="pnrr-delete-button" class="button button-danger">
                        <span class="dashicons dashicons-trash" style="vertical-align: text-top;"></span> 
                        Elimina Tutte le Pagine Clone
                    </button>
                    
                    <div id="pnrr-delete-progress" style="display: none;">
                        <div class="progress-bar">
                            <div class="progress-bar-fill" style="width: 0%;"></div>
                        </div>
                        <div class="progress-status">Eliminazione in corso...</div>
                    </div>
                </div>
            <?php else : ?>
                <div class="notice notice-info inline">
                    <p>Non ci sono pagine clone da eliminare.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <div id="pnrr-delete-results" style="display: none;">
            <h2>Risultati Eliminazione</h2>
            <div class="results-container"></div>
        </div>
        
        <!-- Modal di conferma per l'eliminazione -->
        <div id="delete-confirm-modal" class="pnrr-modal" style="display: none;">
            <div class="pnrr-modal-content">
                <div class="pnrr-modal-header">
                    <h3>Conferma Eliminazione</h3>
                    <span class="pnrr-modal-close">&times;</span>
                </div>
                <div class="pnrr-modal-body">
                    <p>Sei sicuro di voler eliminare <strong>tutte le <?php echo intval($clone_count); ?> pagine clone</strong>?</p>
                    <p>Questa azione è permanente e non può essere annullata.</p>
                </div>
                <div class="pnrr-modal-footer">
                    <button id="confirm-delete" class="button button-danger">Sì, elimina tutte le pagine clone</button>
                    <button id="cancel-delete" class="button button-secondary">Annulla</button>
                </div>
            </div>
        </div>
        
        <hr>
        
        <!-- Sezione per la gestione delle pagine clone -->
        <div class="pnrr-management-section">
            <h2>Gestione Pagine Clone</h2>
            
            <div class="management-info">
                <p>
                    Utilizza questa sezione per identificare pagine che potrebbero essere state create 
                    come cloni in precedenza, ma che non sono correttamente marcate come tali.
                </p>
                <button id="pnrr-identify-button" class="button button-secondary">
                    <span class="dashicons dashicons-search" style="vertical-align: text-top;"></span> 
                    Identifica pagine clone esistenti
                </button>
                
                <div id="pnrr-identify-progress" style="display: none;">
                    <div class="progress-bar">
                        <div class="progress-bar-fill" style="width: 0%;"></div>
                    </div>
                    <div class="progress-status">Identificazione in corso...</div>
                </div>
            </div>
        </div>
        
        <div id="pnrr-identify-results" style="display: none;">
            <h2>Risultati Identificazione</h2>
            <div class="results-container"></div>
        </div>
        
        <hr>
        
        <!-- Sezione per l'importazione CSV -->
        <div class="pnrr-import-section">
            <h2>Importa Dati</h2>
            
            <div class="import-info">
                <p>Utilizza questa sezione per importare i dati dei cloni da un file CSV.</p>
                
                <form id="pnrr-import-form" method="post" enctype="multipart/form-data">
                    <div class="form-row">
                        <label for="csv-file">Seleziona file CSV:</label>
                        <input type="file" id="csv-file" name="csv_file" accept=".csv" required>
                        <div class="import-options">
                            <label>
                                <input type="checkbox" name="create_pages" id="create-pages-checkbox" value="1">
                                Genera automaticamente le pagine dopo l'importazione
                            </label>
                            <p class="description">Se non selezionato, potrai generare le pagine successivamente.</p>
                        </div>
                        <button type="submit" class="button button-primary">Importa</button>
                    </div>
                </form>
                
                <div id="pnrr-import-progress" style="display: none;">
                    <div class="progress-bar">
                        <div class="progress-bar-fill" style="width: 0%;"></div>
                    </div>
                    <div class="progress-status">Importazione in corso...</div>
                </div>
                
                <div id="pnrr-import-feedback" style="display: none;"></div>
            </div>
            
            <div class="import-instructions">
                <?php include_once PNRR_PLUGIN_DIR . 'admin/partials/import-instructions.php'; ?>
            </div>
        </div>
        
        <div id="pnrr-import-results" style="display: none;">
            <h2>Risultati Importazione</h2>
            <div class="results-container"></div>
        </div>
        
        <hr>
        
        <!-- Sezione per la visualizzazione dei dati dei cloni -->
        <div class="pnrr-data-section">
            <h2>Gestione dati Cloni</h2>
            
            <div class="pnrr-table-wrapper">
                <div class="pnrr-table-controls">
                    <div class="pnrr-table-filters">
                        <input type="text" id="pnrr-search-input" placeholder="Cerca..." class="search-input">
                        <button type="button" id="pnrr-search-clear" class="button">Cancella</button>
                        
                        <div class="table-length-wrapper">
                            <label for="pnrr-table-length">Mostra:</label>
                            <select id="pnrr-table-length">
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                        </div>
                        
                        <div class="show-deleted-wrapper">
                            <input type="checkbox" id="show-deleted-clones" name="show_deleted" <?php echo isset($_COOKIE['pnrr_show_deleted_clones']) && $_COOKIE['pnrr_show_deleted_clones'] === 'true' ? 'checked' : ''; ?>>
                            <label for="show-deleted-clones">Mostra cloni eliminati</label>
                        </div>
                    </div>
                </div>
                
                <table id="pnrr-clones-table" class="widefat">
                    <thead>
                        <tr>
                            <th class="sortable" data-sort="slug">Slug</th>
                            <th class="sortable" data-sort="title">Titolo</th>
                            <th class="sortable" data-sort="home_url">URL Sito</th>
                            <th class="sortable" data-sort="cup">Codice CUP</th>
                            <th class="sortable" data-sort="logo_url">Logo</th>
                            <th>Indirizzo</th>
                            <th>Stato</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $show_deleted = isset($_COOKIE['pnrr_show_deleted_clones']) && $_COOKIE['pnrr_show_deleted_clones'] === 'true';
                        // Ottieni l'oggetto display dalla variabile globale
                        global $pnrr_plugin;
                        if (isset($pnrr_plugin['admin']) && is_object($pnrr_plugin['admin'])) {
                            $display_handler = $pnrr_plugin['admin']->get_display_handler();
                            if ($display_handler) {
                                // Il problema è qui: stiamo usando l'oggetto sbagliato
                                // Utilizza clone_manager invece di core per ottenere i cloni
                                if (isset($pnrr_plugin['clone_manager']) && is_object($pnrr_plugin['clone_manager'])) {
                                    $clones = $pnrr_plugin['clone_manager']->get_clone_data();
                                    $display_handler->render_clones_table($clones, $show_deleted); 
                                } else {
                                    echo '<tr><td colspan="8" class="no-items">Errore: Gestore cloni non disponibile.</td></tr>';
                                }
                            } else {
                                echo '<tr><td colspan="8" class="no-items">Errore: Display handler non trovato.</td></tr>';
                            }
                        } else {
                            echo '<tr><td colspan="8" class="no-items">Errore: Istanza admin non trovata.</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
                
                <div class="pnrr-table-pagination">
                    <span class="displaying-num"></span>
                    <div class="tablenav-pages">
                        <span class="pagination-links">
                            <button class="button first-page" aria-label="Vai alla prima pagina" disabled>
                                <span class="dashicons dashicons-controls-skipback"></span>
                            </button>
                            <button class="button prev-page" aria-label="Vai alla pagina precedente" disabled>
                                <span class="dashicons dashicons-controls-back"></span>
                            </button>
                            <span class="paging-input">
                                <span class="current-page">1</span> di
                                <span class="total-pages">1</span>
                            </span>
                            <button class="button next-page" aria-label="Vai alla pagina successiva">
                                <span class="dashicons dashicons-controls-forward"></span>
                            </button>
                            <button class="button last-page" aria-label="Vai all'ultima pagina">
                                <span class="dashicons dashicons-controls-skipforward"></span>
                            </button>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal per l'anteprima delle immagini -->
        <div id="image-preview-modal" class="pnrr-modal" style="display: none;">
            <div class="pnrr-modal-content">
                <div class="pnrr-modal-header">
                    <h3>Anteprima Immagine</h3>
                    <span class="pnrr-modal-close">&times;</span>
                </div>
                <div class="pnrr-modal-body">
                    <img id="preview-image" src="" alt="Anteprima" style="max-width: 100%;">
                </div>
            </div>
        </div>
        
        <!-- Modal per la modifica del clone -->
        <div id="edit-clone-modal" class="pnrr-modal">
            <div class="pnrr-modal-content">
                <span class="pnrr-modal-close">&times;</span>
                <h2>Modifica Clone</h2>
                <form id="edit-clone-form">
                    <input type="hidden" id="edit-clone-id" name="clone_id" value="">
                    
                    <div class="form-group">
                        <label for="edit-clone-slug">Slug:</label>
                        <input type="text" id="edit-clone-slug" name="slug" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit-clone-title">Titolo:</label>
                        <input type="text" id="edit-clone-title" name="title" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit-clone-home-url">URL Home:</label>
                        <input type="url" id="edit-clone-home-url" name="home_url">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit-clone-cup">Codice CUP:</label>
                        <input type="text" id="edit-clone-cup" name="cup">
                        <p class="description">Inserisci il Codice Unico di Progetto (CUP).</p>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit-clone-logo-url">URL Logo:</label>
                        <div class="logo-selector">
                            <input type="url" id="edit-clone-logo-url" name="logo_url">
                            <button type="button" id="select-logo-button" class="button">Seleziona Logo</button>
                            <button type="button" id="remove-logo-button" class="button" style="display:none;">Rimuovi</button>
                        </div>
                        <div class="logo-preview">
                            <img id="logo-preview" src="" alt="Anteprima Logo" style="display:none; max-width:200px; margin-top:10px;">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit-clone-address">Indirizzo:</label>
                        <textarea id="edit-clone-address" name="address" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit-clone-contacts">Contatti:</label>
                        <textarea id="edit-clone-contacts" name="contacts" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit-clone-other-info">Altre Informazioni:</label>
                        <textarea id="edit-clone-other-info" name="other_info" rows="4"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit-clone-enabled">Stato:</label>
                        <select id="edit-clone-enabled" name="enabled">
                            <option value="1">Attivo</option>
                            <option value="0">Inattivo</option>
                        </select>
                    </div>
                    
                    <div class="form-actions">
                        <span id="edit-clone-spinner" class="spinner"></span>
                        <button type="button" id="save-clone-button" class="button button-primary">Salva Modifiche</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    // Imposta il valore iniziale della checkbox in base al cookie
    jQuery(document).ready(function($) {
        var cookieValue = getCookie('pnrr_show_deleted_clones');
        $('#show-deleted-clones').prop('checked', cookieValue === 'true');
        
        function getCookie(name) {
            var nameEQ = name + "=";
            var ca = document.cookie.split(';');
            for(var i=0; i < ca.length; i++) {
                var c = ca[i];
                while (c.charAt(0) === ' ') c = c.substring(1, c.length);
                if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
            }
            return null;
        }
    });
</script>
