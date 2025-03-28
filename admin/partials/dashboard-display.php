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
$number_of_clones = count($clone_manager->get_clone_data());

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

// Ottieni le opzioni del plugin
$plugin_options = pnrr_get_option();

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
                <h3>Istruzioni</h3>
                <p>Il file CSV deve avere le seguenti colonne:</p>
                <ul>
                    <li><strong>slug</strong> - Identificativo univoco della pagina (obbligatorio)</li>
                    <li><strong>title</strong> - Titolo della pagina (obbligatorio)</li>
                    <li><strong>logo_url</strong> - URL dell'immagine del logo</li>
                    <li><strong>footer_text</strong> - Testo HTML per il footer</li>
                    <li><strong>home_url</strong> - URL della home esterna</li>
                </ul>
                <p>Esempio:</p>
                <pre>slug,title,logo_url,footer_text,home_url
pnrr-comune1,PNRR Comune di Roma,https://esempio.it/logo1.png,"&lt;p&gt;Footer Comune 1&lt;/p&gt;",https://comune1.it
pnrr-comune2,PNRR Comune di Milano,https://esempio.it/logo2.png,"&lt;p&gt;Footer Comune 2&lt;/p&gt;",https://comune2.it</pre>
                
                <p><strong>Nota:</strong> L'importazione sovrascriverà tutti i dati dei cloni esistenti.</p>
            </div>
        </div>
        
        <div id="pnrr-import-results" style="display: none;">
            <h2>Risultati Importazione</h2>
            <div class="results-container"></div>
        </div>
        
        <hr>
        
        <!-- Sezione per la visualizzazione dei dati dei cloni -->
        <div class="pnrr-data-section">
            <div class="pnrr-data-section-header">
                <h2>Gestione Dati Clone</h2>
                <div class="pnrr-data-actions">
                    <button id="pnrr-sync-button" class="button button-secondary">
                        <span class="dashicons dashicons-update"></span> Sincronizza dati
                    </button>
                    
                    <div class="sync-options">
                        <label>
                            <input type="checkbox" id="sync-remove-option" name="sync_remove_option" value="1">
                            Rimuovi dati dei cloni eliminati
                        </label>
                    </div>
                </div>
            </div>
            
            <div id="pnrr-sync-progress" style="display: none;">
                <div class="progress-bar">
                    <div class="progress-bar-fill" style="width: 0%;"></div>
                </div>
                <div class="progress-status">Sincronizzazione in corso...</div>
            </div>
            
            <div id="pnrr-sync-feedback" style="display: none;"></div>
            
            <div class="pnrr-table-controls">
                <div class="table-search">
                    <input type="text" id="pnrr-search-input" placeholder="Cerca..." class="regular-text">
                    <button type="button" id="pnrr-search-clear" class="button">Cancella</button>
                </div>
                <div class="table-filters">
                    <label class="show-deleted-checkbox">
                        <input type="checkbox" id="show-deleted-clones" name="show_deleted_clones">
                        Mostra cloni eliminati
                    </label>
                </div>
                <div class="table-length">
                    <label>
                        Mostra 
                        <select id="pnrr-table-length">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="-1">Tutti</option>
                        </select>
                        elementi
                    </label>
                </div>
            </div>
            
            <div class="pnrr-table-container">
                <table id="pnrr-clones-table" class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th class="sortable" data-sort="slug">Slug</th>
                            <th class="sortable" data-sort="title">Titolo</th>
                            <th class="sortable" data-sort="home_url">Home URL</th>
                            <th class="sortable" data-sort="logo_url">Logo URL</th>
                            <th>Footer</th>
                            <th class="sortable" data-sort="status">Stato</th>
                            <th class="no-sort">Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Recupera l'impostazione utente dai cookie (default: non mostrare eliminati)
                        $show_deleted = isset($_COOKIE['pnrr_show_deleted_clones']) ? 
                            filter_var($_COOKIE['pnrr_show_deleted_clones'], FILTER_VALIDATE_BOOLEAN) : false;
                        
                        // Carica i dati filtrati
                        $all_clones = $clone_manager->get_clone_data(false, $show_deleted);
                        
                        if (empty($all_clones)) : 
                        ?>
                        <tr>
                            <td colspan="7" class="no-items">Nessun dato clone disponibile.</td>
                        </tr>
                        <?php else : ?>
                            <?php foreach ($all_clones as $index => $clone) : 
                                $is_deleted = isset($clone['status']) && $clone['status'] === 'deleted';
                                $is_disabled = isset($clone['enabled']) && !$clone['enabled'];
                                $is_discovered = isset($clone['discovered']) && $clone['discovered'];
                                
                                $row_class = $is_deleted ? 'deleted' : ($is_disabled ? 'disabled' : '');
                                if ($is_discovered) {
                                    $row_class .= ' discovered';
                                }
                            ?>
                            <tr data-id="<?php echo esc_attr($index); ?>" class="<?php echo esc_attr($row_class); ?>">
                                <td><?php echo esc_html($clone['slug']); ?></td>
                                <td>
                                    <?php 
                                    echo esc_html($clone['title']); 
                                    if ($is_discovered) {
                                        echo ' <span class="discovery-badge" title="Scoperta durante sincronizzazione">🔍</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if (!empty($clone['home_url'])) : ?>
                                    <a href="<?php echo esc_url($clone['home_url']); ?>" target="_blank"><?php echo esc_html($clone['home_url']); ?></a>
                                    <?php else : ?>
                                    <span class="not-set">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($clone['logo_url'])) : ?>
                                    <a href="<?php echo esc_url($clone['logo_url']); ?>" target="_blank" class="image-preview-link" data-image="<?php echo esc_url($clone['logo_url']); ?>">
                                        <span class="dashicons dashicons-format-image"></span> Anteprima
                                    </a>
                                    <?php else : ?>
                                    <span class="not-set">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    if (!empty($clone['footer_text'])) {
                                        $excerpt = wp_strip_all_tags($clone['footer_text']);
                                        echo esc_html(substr($excerpt, 0, 50)) . (strlen($excerpt) > 50 ? '...' : '');
                                    } else {
                                        echo '<span class="not-set">-</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if ($is_deleted) : ?>
                                        <span class="status-indicator deleted">
                                            Eliminato
                                        </span>
                                    <?php else : ?>
                                        <span class="status-indicator <?php echo isset($clone['enabled']) && $clone['enabled'] ? 'active' : 'inactive'; ?>">
                                            <?php echo isset($clone['enabled']) && $clone['enabled'] ? 'Attivo' : 'Inattivo'; ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="actions">
                                    <button type="button" class="button edit-clone" data-id="<?php echo esc_attr($index); ?>" <?php echo $is_deleted ? 'disabled' : ''; ?>>
                                        <span class="dashicons dashicons-edit"></span>
                                    </button>
                                    <?php if (!$is_deleted) : ?>
                                    <button type="button" class="button toggle-clone" data-id="<?php echo esc_attr($index); ?>">
                                        <?php if (isset($clone['enabled']) && $clone['enabled']) : ?>
                                        <span class="dashicons dashicons-hidden"></span>
                                        <?php else : ?>
                                        <span class="dashicons dashicons-visibility"></span>
                                        <?php endif; ?>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="pnrr-table-pagination">
                <div class="tablenav-pages">
                    <span class="displaying-num"></span>
                    <span class="pagination-links">
                        <button type="button" class="button first-page" aria-label="Prima pagina">
                            <span class="dashicons dashicons-controls-skipback"></span>
                        </button>
                        <button type="button" class="button prev-page" aria-label="Pagina precedente">
                            <span class="dashicons dashicons-controls-back"></span>
                        </button>
                        <span class="paging-input">
                            Pagina <span class="current-page">1</span> di <span class="total-pages">0</span>
                        </span>
                        <button type="button" class="button next-page" aria-label="Pagina successiva">
                            <span class="dashicons dashicons-controls-forward"></span>
                        </button>
                        <button type="button" class="button last-page" aria-label="Ultima pagina">
                            <span class="dashicons dashicons-controls-skipforward"></span>
                        </button>
                    </span>
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
        <div id="edit-clone-modal" class="pnrr-modal" style="display: none;">
            <div class="pnrr-modal-content">
                <div class="pnrr-modal-header">
                    <h3>Modifica Clone</h3>
                    <span class="pnrr-modal-close">&times;</span>
                </div>
                <div class="pnrr-modal-body">
                    <form id="edit-clone-form">
                        <input type="hidden" id="edit-clone-id" name="clone_id" value="">
                        
                        <div class="form-field">
                            <label for="edit-clone-slug">Slug:</label>
                            <input type="text" id="edit-clone-slug" name="slug" class="regular-text" required>
                            <p class="description">Identificativo univoco per la pagina.</p>
                        </div>
                        
                        <div class="form-field">
                            <label for="edit-clone-title">Titolo:</label>
                            <input type="text" id="edit-clone-title" name="title" class="regular-text" required>
                            <p class="description">Titolo visibile della pagina.</p>
                        </div>
                        
                        <div class="form-field">
                            <label for="edit-clone-home-url">Home URL:</label>
                            <input type="url" id="edit-clone-home-url" name="home_url" class="regular-text">
                            <p class="description">URL di destinazione per il pulsante Home.</p>
                        </div>
                        
                        <div class="form-field media-field">
                            <label for="edit-clone-logo-url">Logo:</label>
                            <div class="media-input-wrapper">
                                <input type="url" id="edit-clone-logo-url" name="logo_url" class="regular-text" readonly>
                                <button type="button" class="button select-media-button" id="select-logo-button">
                                    <span class="dashicons dashicons-format-image"></span> Seleziona immagine
                                </button>
                                <button type="button" class="button remove-media-button" id="remove-logo-button">
                                    <span class="dashicons dashicons-no"></span> Rimuovi
                                </button>
                            </div>
                            <div class="image-preview">
                                <img id="logo-preview" src="" alt="" style="max-width: 150px; max-height: 150px; display: none;">
                            </div>
                            <p class="description">Logo che verrà mostrato nella pagina clone.</p>
                        </div>
                        
                        <div class="form-field">
                            <label for="edit-clone-footer-text">Testo Footer:</label>
                            <textarea id="edit-clone-footer-text" name="footer_text" rows="5" class="large-text"></textarea>
                            <p class="description">È possibile utilizzare HTML per formattare il testo del footer.</p>
                        </div>
                        
                        <div class="form-field">
                            <label for="edit-clone-enabled">Stato:</label>
                            <select id="edit-clone-enabled" name="enabled">
                                <option value="1">Attivo</option>
                                <option value="0">Inattivo</option>
                            </select>
                            <p class="description">Se impostato su inattivo, il clone non verrà generato durante la clonazione.</p>
                        </div>
                    </form>
                </div>
                <div class="pnrr-modal-footer">
                    <div class="spinner-container">
                        <span class="spinner" id="edit-clone-spinner"></span>
                    </div>
                    <button type="button" id="save-clone-button" class="button button-primary">Salva Modifiche</button>
                    <button type="button" class="pnrr-modal-close button">Annulla</button>
                </div>
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
