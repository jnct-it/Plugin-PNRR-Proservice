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
    </div>
</div>
