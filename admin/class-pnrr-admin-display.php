<?php
/**
 * Classe per gestire la visualizzazione dell'interfaccia amministrativa
 * 
 * Si occupa del rendering e della formattazione dell'interfaccia utente
 * 
 * @since 1.0.0
 */

// Assicurarsi che il plugin non sia accessibile direttamente
if (!defined('ABSPATH')) {
    exit;
}

class PNRR_Admin_Display {
    
    /**
     * Risultati dell'ultima sincronizzazione
     *
     * @var array
     */
    private $sync_results = array();
    
    /**
     * Costruttore
     */
    public function __construct() {
        // Inizializzazioni specifiche per il display
    }
    
    /**
     * Renderizza la pagina di amministrazione principale
     * 
     * @param array $sync_results Risultati della sincronizzazione automatica
     */
    public function render_admin_page($sync_results = array()) {
        // Salva i risultati della sincronizzazione per l'uso nei template
        $this->sync_results = $sync_results;
        
        // Includi il template dalla directory partials
        if (file_exists(PNRR_PLUGIN_DIR . 'admin/partials/dashboard-display.php')) {
            $sync_results = $this->sync_results; // Rendi disponibile nel template
            require_once PNRR_PLUGIN_DIR . 'admin/partials/dashboard-display.php';
        } else {
            echo '<div class="error"><p>Errore: Template dashboard-display.php non trovato.</p></div>';
        }
    }
    
    /**
     * Visualizza la pagina dashboard del plugin
     */
    public function display_dashboard() {
        // Dopo la visualizzazione principale, aggiungiamo le istruzioni per gli shortcode
        if (file_exists(PNRR_PLUGIN_DIR . 'admin/partials/shortcode-instructions.php')) {
            include PNRR_PLUGIN_DIR . 'admin/partials/shortcode-instructions.php';
        }
    }
    
    /**
     * Renderizza il contenuto della tabella dei cloni
     * 
     * @param array $clones Dati dei cloni
     * @param bool $show_deleted Se mostrare i cloni eliminati
     */
    public function render_clones_table($clones, $show_deleted = false) {
        if (empty($clones)) {
            echo '<tr><td colspan="8" class="no-items">Nessun dato clone disponibile.</td></tr>';
            return;
        }
        
        foreach ($clones as $index => $clone) {
            $is_deleted = isset($clone['status']) && $clone['status'] === 'deleted';
            $is_disabled = isset($clone['enabled']) && !$clone['enabled'];
            $is_discovered = isset($clone['discovered']) && $clone['discovered'];
            
            $row_class = $is_deleted ? 'deleted' : ($is_disabled ? 'disabled' : '');
            if ($is_discovered) {
                $row_class .= ' discovered';
            }
            
            // Salta i cloni eliminati se non devono essere mostrati
            if ($is_deleted && !$show_deleted) {
                continue;
            }
            
            // Visualizza il titolo senza il prefisso nella tabella di amministrazione utilizzando la funzione centralizzata
            $display_title = pnrr_remove_title_prefix($clone['title']);
            ?>
            <tr data-id="<?php echo esc_attr($index); ?>" class="<?php echo esc_attr($row_class); ?>"
                data-address="<?php echo esc_attr(isset($clone['address']) ? $clone['address'] : ''); ?>"
                data-contacts="<?php echo esc_attr(isset($clone['contacts']) ? $clone['contacts'] : ''); ?>"
                data-cup="<?php echo esc_attr(isset($clone['cup']) ? $clone['cup'] : ''); ?>"
                data-other-info="<?php echo esc_attr(isset($clone['other_info']) ? $clone['other_info'] : ''); ?>">
                <td><?php echo esc_html($clone['slug']); ?></td>
                <td>
                    <?php 
                    echo esc_html($display_title); 
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
                    <?php if (!empty($clone['cup'])) : ?>
                        <?php echo esc_html($clone['cup']); ?>
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
                    // Mostra l'indirizzo
                    if (!empty($clone['address'])) {
                        $excerpt = wp_strip_all_tags($clone['address']);
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
            <?php
        }
    }
    
    /**
     * Renderizza una notifica di risultati
     * 
     * @param array $results Risultati da mostrare
     * @param string $type Tipo di notifica (success, error, warning, info)
     */
    public function render_results_notice($results, $type = 'info') {
        if (empty($results)) {
            return;
        }
        
        $class = 'notice notice-' . $type . ' inline is-dismissible';
        
        echo '<div class="' . esc_attr($class) . '">';
        
        if (isset($results['message'])) {
            echo '<p>' . esc_html($results['message']) . '</p>';
        }
        
        if (isset($results['details']) && is_array($results['details'])) {
            echo '<ul>';
            foreach ($results['details'] as $detail) {
                echo '<li>' . esc_html($detail) . '</li>';
            }
            echo '</ul>';
        }
        
        echo '</div>';
    }
}
?>
