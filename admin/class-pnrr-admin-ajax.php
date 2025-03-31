<?php
/**
 * Classe per gestire le chiamate AJAX dell'interfaccia amministrativa
 * 
 * Gestisce tutte le operazioni asincrone richieste dall'interfaccia admin
 * 
 * @since 1.0.0
 */

// Assicurarsi che il plugin non sia accessibile direttamente
if (!defined('ABSPATH')) {
    exit;
}

class PNRR_Admin_Ajax {
    
    /**
     * Costruttore
     */
    public function __construct() {
        // Registrazione degli handler AJAX
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
        $result = pnrr_process_csv_import($file_path);
        
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
        
        // Delega la generazione HTML al display handler
        global $pnrr_plugin;
        if (isset($pnrr_plugin['admin']) && 
            method_exists($pnrr_plugin['admin'], 'get_display_handler') && 
            method_exists($pnrr_plugin['admin']->get_display_handler(), 'render_clones_table')) {
            
            $pnrr_plugin['admin']->get_display_handler()->render_clones_table($clones, $show_deleted);
        } else {
            // Fallback: mostra solo un messaggio base
            if (empty($clones)) {
                echo '<tr><td colspan="7" class="no-items">Nessun dato clone disponibile.</td></tr>';
            } else {
                foreach ($clones as $index => $clone) {
                    echo '<tr><td>' . esc_html($clone['slug']) . '</td><td>' . esc_html($clone['title']) . '</td><td colspan="5">...</td></tr>';
                }
            }
        }
        
        $html = ob_get_clean();
        
        wp_send_json_success(array(
            'html' => $html,
            'count' => count($clones)
        ));
    }
}
