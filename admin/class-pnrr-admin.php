<?php
/**
 * Classe per la gestione dell'interfaccia amministrativa
 * 
 * Gestisce il menu, le pagine e le funzionalità amministrative del plugin
 * 
 * @since 1.0.0
 */

// Assicurarsi che il plugin non sia accessibile direttamente
if (!defined('ABSPATH')) {
    exit;
}

class PNRR_Admin {
    
    /**
     * Costruttore
     */
    public function __construct() {
        // Aggiunta delle azioni e dei filtri per l'admin
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_clone_pnrr_pages', array($this, 'clone_pages_ajax_handler'));
        add_action('wp_ajax_pnrr_save_master_page', array($this, 'save_master_page_ajax_handler'));
        add_action('wp_ajax_pnrr_delete_all_clones', array($this, 'ajax_delete_all_clones'));
        add_action('wp_ajax_pnrr_mark_existing_clones', array($this, 'ajax_mark_existing_clones'));
        add_action('wp_ajax_pnrr_import_csv', array($this, 'ajax_import_csv'));
        add_action('wp_ajax_pnrr_update_clone', array($this, 'ajax_update_clone'));
        add_action('wp_ajax_pnrr_toggle_clone', array($this, 'ajax_toggle_clone'));
        add_action('wp_ajax_pnrr_sync_clone_data', array($this, 'ajax_sync_clone_data'));
        add_action('wp_ajax_pnrr_get_filtered_clones', array($this, 'ajax_get_filtered_clones'));
    }
    
    /**
     * Aggiunge la voce di menu nella dashboard di WordPress
     */
    public function add_admin_menu() {
        add_menu_page(
            'PNRR Page Cloner',
            'PNRR Cloner',
            'manage_options',
            'pnrr-page-cloner',
            array($this, 'admin_page_display'),
            'dashicons-admin-page',
            30
        );
    }
    
    /**
     * Carica gli script e i CSS per la pagina di amministrazione
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook != 'toplevel_page_pnrr-page-cloner') {
            return;
        }
        
        // Utilizzo i nomi dei file standardizzati
        wp_enqueue_style('pnrr-admin-css', PNRR_PLUGIN_URL . 'css/admin.css', array(), PNRR_VERSION);
        wp_enqueue_script('pnrr-admin-js', PNRR_PLUGIN_URL . 'js/admin-script.js', array('jquery'), PNRR_VERSION, true);
        
        // Includi i script di WordPress Media per il selettore immagine
        wp_enqueue_media();
        
        wp_localize_script('pnrr-admin-js', 'pnrr_cloner', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pnrr_cloner_nonce'),
        ));
    }
    
    /**
     * Visualizza la pagina di amministrazione
     */
    public function admin_page_display() {
        // Esegui sincronizzazione automatica prima di visualizzare la pagina
        $this->perform_auto_sync();
        
        // Include il template dalla directory partials
        if (file_exists(PNRR_PLUGIN_DIR . 'admin/partials/dashboard-display.php')) {
            require_once PNRR_PLUGIN_DIR . 'admin/partials/dashboard-display.php';
        } else {
            echo '<div class="error"><p>Errore: Template dashboard-display.php non trovato.</p></div>';
        }
    }
    
    /**
     * Esegue una sincronizzazione automatica prima di mostrare la dashboard
     */
    private function perform_auto_sync() {
        global $pnrr_plugin;
        
        // Opzione per disabilitare la sincronizzazione automatica
        $auto_sync_enabled = apply_filters('pnrr_enable_auto_sync', true);
        if (!$auto_sync_enabled) {
            return;
        }
        
        if (!isset($pnrr_plugin['clone_manager']) || !is_object($pnrr_plugin['clone_manager'])) {
            return;
        }
        
        // Esegui la sincronizzazione con opzione di solo marcatura (non rimuovere dati)
        $pnrr_plugin['clone_manager']->sync_clone_data(true);
    }
    
    /**
     * Gestisce la richiesta Ajax per la clonazione delle pagine
     */
    public function clone_pages_ajax_handler() {
        // Verifica di sicurezza
        check_ajax_referer('pnrr_cloner_nonce', 'nonce');
        
        // Verifica dei permessi
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'));
            return;
        }
        
        global $pnrr_plugin;
        
        // Controlla se l'istanza del clone manager è disponibile
        if (!isset($pnrr_plugin['clone_manager']) || !is_object($pnrr_plugin['clone_manager'])) {
            wp_send_json_error(array('message' => 'Errore: Gestore dei cloni non trovato'));
            return;
        }
        
        $clone_manager = $pnrr_plugin['clone_manager'];
        
        // Ottieni la pagina master
        $source_page = $clone_manager->get_master_page();
        if (!$source_page) {
            wp_send_json_error(array('message' => 'Pagina master non trovata'));
            return;
        }
        
        $clone_data = $clone_manager->get_clone_data();
        $clone_index = isset($_POST['clone_index']) ? intval($_POST['clone_index']) : 0;
        
        if ($clone_index >= count($clone_data)) {
            // Esegui sincronizzazione finale dopo aver completato tutte le clonazioni
            $clone_manager->sync_clone_data(true);
            
            wp_send_json_success(array('completed' => true));
            return;
        }
        
        // Clona la pagina
        $result = $clone_manager->clone_single_page($source_page, $clone_data[$clone_index]);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message(),
                'clone_index' => $clone_index
            ));
            return;
        }
        
        wp_send_json_success(array(
            'completed' => false,
            'clone_index' => $clone_index + 1,
            'total' => count($clone_data),
            'page_id' => $result,
            'page_title' => $clone_data[$clone_index]['title'],
            'page_url' => get_permalink($result)
        ));
    }
    
    /**
     * Gestisce la richiesta Ajax per salvare la pagina master
     */
    public function save_master_page_ajax_handler() {
        // Verifica di sicurezza
        check_ajax_referer('pnrr_cloner_nonce', 'nonce');
        
        // Verifica dei permessi
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'));
            return;
        }
        
        global $pnrr_plugin;
        
        // Controlla se l'istanza principale è disponibile
        if (!isset($pnrr_plugin['core']) || !is_object($pnrr_plugin['core'])) {
            wp_send_json_error(array('message' => 'Errore: Istanza principale del plugin non trovata'));
            return;
        }
        
        $core = $pnrr_plugin['core'];
        $page_id = isset($_POST['page_id']) ? intval($_POST['page_id']) : 0;
        
        // Verifica che la pagina esista e sia una pagina Elementor
        if (!$core->is_valid_elementor_page($page_id)) {
            wp_send_json_error(array('message' => 'La pagina selezionata non è valida o non usa Elementor'));
            return;
        }
        
        // Salva l'ID della pagina master
        $result = $core->set_master_page_id($page_id);
        
        if ($result) {
            $page = get_post($page_id);
            wp_send_json_success(array(
                'message' => 'Pagina master "' . esc_html($page->post_title) . '" salvata con successo',
                'page_id' => $page_id,
                'page_title' => $page->post_title
            ));
        } else {
            wp_send_json_error(array('message' => 'Errore durante il salvataggio delle impostazioni'));
        }
    }
    
    /**
     * Gestisce la richiesta Ajax per eliminare tutte le pagine clone
     */
    public function ajax_delete_all_clones() {
        // Verifica di sicurezza
        check_ajax_referer('pnrr_cloner_nonce', 'nonce');
        
        // Verifica dei permessi
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'));
            return;
        }
        
        global $pnrr_plugin;
        
        // Controlla se l'istanza principale è disponibile
        if (!isset($pnrr_plugin['core']) || !is_object($pnrr_plugin['core'])) {
            wp_send_json_error(array('message' => 'Errore: Istanza principale del plugin non trovata'));
            return;
        }
        
        $core = $pnrr_plugin['core'];
        
        // Determina modalità (marca o rimuovi effettivamente i dati)
        $update_clone_data = isset($_POST['update_clone_data']) ? (bool)$_POST['update_clone_data'] : true;
        $remove_clone_data = isset($_POST['remove_clone_data']) ? (bool)$_POST['remove_clone_data'] : false;
        
        // Esegui l'eliminazione
        $result = $core->delete_all_clones(true, $update_clone_data, $remove_clone_data);
        
        if ($result['deleted'] > 0 || empty($result['errors'])) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error(array(
                'message' => 'Nessuna pagina è stata eliminata',
                'details' => $result
            ));
        }
    }
    
    /**
     * Gestisce la richiesta Ajax per identificare e marcare pagine clone esistenti
     */
    public function ajax_mark_existing_clones() {
        // Verifica di sicurezza
        check_ajax_referer('pnrr_cloner_nonce', 'nonce');
        
        // Verifica dei permessi
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'));
            return;
        }
        
        global $pnrr_plugin;
        
        // Controlla se l'istanza principale è disponibile
        if (!isset($pnrr_plugin['core']) || !is_object($pnrr_plugin['core'])) {
            wp_send_json_error(array('message' => 'Errore: Istanza principale del plugin non trovata'));
            return;
        }
        
        $core = $pnrr_plugin['core'];
        
        // Esegui l'identificazione e marcatura
        $result = $core->mark_existing_clones();
        
        // Invia risposta
        wp_send_json_success($result);
    }
    
    /**
     * Processa l'importazione di un file CSV
     * 
     * @param string $file_path Percorso al file CSV temporaneo
     * @return array|WP_Error Risultato dell'importazione o errore
     */
    public function process_csv_import($file_path) {
        global $pnrr_plugin;
        
        // Verifica che l'istanza del clone manager sia disponibile
        if (!isset($pnrr_plugin['clone_manager']) || !is_object($pnrr_plugin['clone_manager'])) {
            return new WP_Error('clone_manager_not_found', 'Errore: Gestore dei cloni non trovato');
        }
        
        $clone_manager = $pnrr_plugin['clone_manager'];
        
        // Analizza il file CSV
        $csv_data = pnrr_parse_csv_file($file_path);
        
        if (is_wp_error($csv_data)) {
            return $csv_data;
        }
        
        // Prepara i dati nel formato corretto per il plugin
        $clone_data = array();
        foreach ($csv_data as $row) {
            $clone_data[] = array(
                'slug' => sanitize_title($row['slug']),
                'title' => sanitize_text_field($row['title']),
                'logo_url' => esc_url_raw($row['logo_url']),
                'footer_text' => wp_kses_post($row['footer_text']),
                'home_url' => esc_url_raw($row['home_url']),
                'enabled' => isset($row['enabled']) ? (bool)$row['enabled'] : true,
                'last_updated' => current_time('mysql')
            );
        }
        
        // Aggiorna i dati dei cloni
        $option_name = $clone_manager->get_option_name();
        $result = update_option($option_name, $clone_data);
        
        if (!$result) {
            return new WP_Error('update_failed', 'Impossibile aggiornare i dati dei cloni');
        }
        
        // Aggiorna anche la proprietà del clone manager (ricarica i dati)
        $clone_manager->reload_data();
        
        return array(
            'imported' => count($clone_data),
            'success' => true
        );
    }
    
    /**
     * Gestisce la richiesta Ajax per l'importazione CSV
     */
    public function ajax_import_csv() {
        // Verifica di sicurezza
        check_ajax_referer('pnrr_cloner_nonce', 'nonce');
        
        // Verifica dei permessi
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'));
            return;
        }
        
        // Verifica che sia stato caricato un file
        if (!isset($_FILES['csv_file'])) {
            wp_send_json_error(array('message' => 'Nessun file caricato'));
            return;
        }
        
        // Valida il file caricato
        $file_path = pnrr_validate_uploaded_csv($_FILES['csv_file']);
        
        if (is_wp_error($file_path)) {
            wp_send_json_error(array(
                'message' => $file_path->get_error_message()
            ));
            return;
        }
        
        // Processa l'importazione
        $result = $this->process_csv_import($file_path);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message()
            ));
            return;
        }
        
        // Restituisci il risultato dell'importazione
        wp_send_json_success(array(
            'message' => sprintf(
                'Importazione completata: %d cloni importati con successo',
                $result['imported']
            ),
            'imported' => $result['imported']
        ));
    }
    
    /**
     * Gestisce la richiesta Ajax per aggiornare i dati di un clone
     */
    public function ajax_update_clone() {
        // Verifica di sicurezza
        check_ajax_referer('pnrr_cloner_nonce', 'nonce');
        
        // Verifica dei permessi
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'));
            return;
        }
        
        // Ottieni i dati inviati
        parse_str($_POST['data'], $clone_data);
        
        // Validazione campi obbligatori
        if (!isset($clone_data['clone_id']) || !isset($clone_data['slug']) || !isset($clone_data['title'])) {
            wp_send_json_error(array('message' => 'Dati incompleti. I campi obbligatori sono mancanti.'));
            return;
        }
        
        $clone_index = intval($clone_data['clone_id']);
        
        global $pnrr_plugin;
        if (!isset($pnrr_plugin['clone_manager']) || !is_object($pnrr_plugin['clone_manager'])) {
            wp_send_json_error(array('message' => 'Gestore dei cloni non disponibile'));
            return;
        }
        
        $clone_manager = $pnrr_plugin['clone_manager'];
        
        // Validazione aggiuntiva dello slug (deve essere unico)
        $existing_clones = $clone_manager->get_clone_data();
        foreach ($existing_clones as $index => $clone) {
            // Ignora l'attuale clone in fase di modifica
            if ($index === $clone_index) {
                continue;
            }
            
            if ($clone['slug'] === sanitize_title($clone_data['slug'])) {
                wp_send_json_error(array('message' => 'Lo slug è già in uso da un altro clone. Scegli uno slug unico.'));
                return;
            }
        }
        
        // Prepara i dati aggiornati con sanitizzazione
        $update_data = array(
            'slug' => sanitize_title($clone_data['slug']),
            'title' => sanitize_text_field($clone_data['title']),
            'home_url' => esc_url_raw($clone_data['home_url']),
            'logo_url' => esc_url_raw($clone_data['logo_url']),
            'footer_text' => wp_kses_post($clone_data['footer_text']),
            'enabled' => isset($clone_data['enabled']) && intval($clone_data['enabled']) === 1,
            'last_updated' => current_time('mysql')
        );
        
        // Aggiorna i dati
        $result = $clone_manager->update_clone_data($clone_index, $update_data);
        
        if ($result) {
            // Registra l'aggiornamento nei log se disponibile
            if (method_exists($pnrr_plugin['core'], 'log_action')) {
                $pnrr_plugin['core']->log_action(
                    'update_clone', 
                    $clone_index, 
                    $update_data['title']
                );
            }
            
            wp_send_json_success(array(
                'message' => 'Clone aggiornato con successo',
                'clone_id' => $clone_index,
                'data' => $update_data
            ));
        } else {
            wp_send_json_error(array('message' => 'Errore durante l\'aggiornamento del clone'));
        }
    }
    
    /**
     * Gestisce la richiesta Ajax per attivare/disattivare un clone
     */
    public function ajax_toggle_clone() {
        // Verifica di sicurezza
        check_ajax_referer('pnrr_cloner_nonce', 'nonce');
        
        // Verifica dei permessi
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'));
            return;
        }
        
        $clone_id = isset($_POST['clone_id']) ? intval($_POST['clone_id']) : 0;
        $enabled = isset($_POST['enabled']) ? (bool)$_POST['enabled'] : false;
        
        global $pnrr_plugin;
        if (!isset($pnrr_plugin['clone_manager']) || !is_object($pnrr_plugin['clone_manager'])) {
            wp_send_json_error(array('message' => 'Gestore dei cloni non disponibile'));
            return;
        }
        
        $clone_manager = $pnrr_plugin['clone_manager'];
        
        // Aggiorna lo stato
        $result = $clone_manager->update_clone_data($clone_id, array('enabled' => $enabled));
        
        if ($result) {
            wp_send_json_success(array(
                'message' => $enabled ? 'Clone attivato' : 'Clone disattivato',
                'enabled' => $enabled
            ));
        } else {
            wp_send_json_error(array('message' => 'Errore durante l\'aggiornamento dello stato'));
        }
    }
    
    /**
     * Esegue una sincronizzazione automatica dei dati prima di visualizzare la tabella
     * 
     * @param bool $auto_sync Se true, esegue una sincronizzazione automatica
     * @return array Risultati della sincronizzazione o array vuoto
     */
    public function maybe_sync_clone_data($auto_sync = true) {
        global $pnrr_plugin;
        
        if (!$auto_sync) {
            return array();
        }
        
        if (!isset($pnrr_plugin['clone_manager']) || !is_object($pnrr_plugin['clone_manager'])) {
            return array();
        }
        
        // Sincronizza i dati, non rimuovendo i record
        return $pnrr_plugin['clone_manager']->sync_clone_data(true);
    }
    
    /**
     * Gestisce la richiesta Ajax per sincronizzare i dati dei cloni
     */
    public function ajax_sync_clone_data() {
        // Verifica di sicurezza
        check_ajax_referer('pnrr_cloner_nonce', 'nonce');
        
        // Verifica dei permessi
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'));
            return;
        }
        
        global $pnrr_plugin;
        
        // Controlla se l'istanza del clone manager è disponibile
        if (!isset($pnrr_plugin['clone_manager']) || !is_object($pnrr_plugin['clone_manager'])) {
            wp_send_json_error(array('message' => 'Errore: Gestore dei cloni non trovato'));
            return;
        }
        
        $clone_manager = $pnrr_plugin['clone_manager'];
        
        // Determina modalità di sincronizzazione (marca o rimuovi)
        $mark_only = isset($_POST['mark_only']) ? (bool)$_POST['mark_only'] : true;
        
        // Sincronizza i dati
        $result = $clone_manager->sync_clone_data($mark_only);
        
        if ($result) {
            // Log dell'operazione
            if (method_exists($pnrr_plugin['core'], 'log_action')) {
                $pnrr_plugin['core']->log_action(
                    'sync_clone_data',
                    0,
                    'Sincronizzazione dati cloni'
                );
            }
            
            wp_send_json_success(array(
                'message' => 'Sincronizzazione completata con successo',
                'result' => $result
            ));
        } else {
            wp_send_json_error(array('message' => 'Errore durante la sincronizzazione dei dati'));
        }
    }
    
    /**
     * Gestisce la richiesta Ajax per ottenere i cloni filtrati
     */
    public function ajax_get_filtered_clones() {
        // Verifica di sicurezza
        check_ajax_referer('pnrr_cloner_nonce', 'nonce');
        
        // Verifica dei permessi
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'));
            return;
        }
        
        global $pnrr_plugin;
        
        if (!isset($pnrr_plugin['clone_manager']) || !is_object($pnrr_plugin['clone_manager'])) {
            wp_send_json_error(array('message' => 'Gestore dei cloni non disponibile'));
            return;
        }
        
        $clone_manager = $pnrr_plugin['clone_manager'];
        
        // Ottieni il parametro di visualizzazione
        $show_deleted = isset($_POST['show_deleted']) ? 
            filter_var($_POST['show_deleted'], FILTER_VALIDATE_BOOLEAN) : false;
        
        // Ottieni i dati filtrati
        $clones = $clone_manager->get_clone_data(false, $show_deleted);
        
        // Genera l'HTML della tabella
        ob_start();
        if (empty($clones)) {
            echo '<tr><td colspan="7" class="no-items">Nessun dato clone disponibile.</td></tr>';
        } else {
            foreach ($clones as $index => $clone) {
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
                <?php
            }
        }
        $html = ob_get_clean();
        
        wp_send_json_success(array(
            'html' => $html,
            'count' => count($clones)
        ));
    }
}
