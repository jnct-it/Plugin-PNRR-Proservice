<?php
/**
 * Classe per la gestione dell'interfaccia amministrativa (Wrapper)
 * 
 * Questo file serve da wrapper per mantenere la compatibilità con il codice esistente
 * delegando le responsabilità ai nuovi sotto-moduli
 * 
 * @since 1.0.0
 */

// Assicurarsi che il plugin non sia accessibile direttamente
if (!defined('ABSPATH')) {
    exit;
}

// Carica i file dei sotto-moduli
require_once PNRR_PLUGIN_DIR . 'admin/class-pnrr-admin-main.php';
require_once PNRR_PLUGIN_DIR . 'admin/class-pnrr-admin-ajax.php';
require_once PNRR_PLUGIN_DIR . 'admin/class-pnrr-admin-display.php';

class PNRR_Admin {
    
    /**
     * Istanza principale dell'amministrazione
     *
     * @var PNRR_Admin_Main
     */
    private $main;
    
    /**
     * Istanza del gestore AJAX
     *
     * @var PNRR_Admin_Ajax
     */
    private $ajax_handler;
    
    /**
     * Istanza del gestore della visualizzazione
     *
     * @var PNRR_Admin_Display
     */
    private $display_handler;
    
    /**
     * Costruttore
     */
    public function __construct() {
        // Inizializza le istanze dei sotto-moduli
        $this->main = new PNRR_Admin_Main();
        $this->ajax_handler = new PNRR_Admin_Ajax();
        $this->display_handler = new PNRR_Admin_Display();
        
        // Mantiene la compatibilità con il passato delegando metodi all'handler main
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Delega le chiamate AJAX all'handler specifico
        $this->delegate_ajax_actions();

        // Aggiungi hook per la pagina di diagnostica
        add_action('admin_menu', array($this, 'add_diagnostics_menu'));
    }
    
    /**
     * Delega le chiamate AJAX all'handler specifico
     */
    private function delegate_ajax_actions() {
        // Le azioni AJAX sono già gestite dal costruttore di PNRR_Admin_Ajax
        // Questo metodo è qui solo per chiarezza e futura estendibilità
    }
    
    /**
     * Delega la creazione del menu all'handler main
     */
    public function add_admin_menu() {
        $this->main->add_admin_menu();
    }
    
    /**
     * Delega il caricamento degli script all'handler main
     */
    public function enqueue_admin_scripts($hook) {
        $this->main->enqueue_admin_scripts($hook);
    }
    
    /**
     * Delega la visualizzazione della pagina admin
     */
    public function admin_page_display() {
        $this->main->display_admin_page();
    }
    
    /**
     * Restituisce l'istanza dell'handler display
     * 
     * @return PNRR_Admin_Display
     */
    public function get_display_handler() {
        return $this->display_handler;
    }
    
    /**
     * Delega i metodi AJAX all'handler specifico
     * 
     * Intercetta le chiamate ai metodi non definiti in questa classe
     * e le passa all'handler AJAX
     * 
     * @param string $name Nome del metodo chiamato
     * @param array $arguments Argomenti passati al metodo
     * @return mixed
     */
    public function __call($name, $arguments) {
        // Se il metodo inizia con 'ajax_' o contiene '_ajax_' lo deleghiamo all'handler AJAX
        if (strpos($name, 'ajax_') === 0 || strpos($name, '_ajax_') !== false) {
            if (method_exists($this->ajax_handler, $name)) {
                return call_user_func_array(array($this->ajax_handler, $name), $arguments);
            }
        }
        
        // Metodi non gestiti generano un errore
        trigger_error("Chiamata al metodo non definito $name in " . get_class($this), E_USER_ERROR);
    }

    /**
     * Aggiunge la voce di menu per la diagnostica
     */
    public function add_diagnostics_menu() {
        add_submenu_page(
            'pnrr-page-cloner',        // Slug della pagina padre
            'PNRR Diagnostica',        // Titolo della pagina
            'Diagnostica',             // Nome nel menu
            'manage_options',          // Capacità necessaria
            'pnrr-diagnostics',        // Slug della pagina
            array($this, 'display_diagnostics_page') // Funzione di callback
        );
    }
    
    /**
     * Visualizza la pagina di diagnostica
     */
    public function display_diagnostics_page() {
        // Esegui alcuni test di sistema
        global $pnrr_plugin;
        
        $debug_info = array(
            'Plugin Version' => PNRR_VERSION,
            'WP_DEBUG' => defined('WP_DEBUG') && WP_DEBUG ? 'Attivo' : 'Non attivo',
            'WP_DEBUG_LOG' => defined('WP_DEBUG_LOG') && WP_DEBUG_LOG ? 'Attivo' : 'Non attivo',
            'PHP Version' => phpversion(),
            'WordPress Version' => get_bloginfo('version'),
            'Memory Limit' => ini_get('memory_limit'),
            'Post Max Size' => ini_get('post_max_size'),
            'Upload Max Filesize' => ini_get('upload_max_filesize'),
            'Max Execution Time' => ini_get('max_execution_time') . ' secondi',
            'PNRR Debug Logs' => $this->get_debug_logs_info()
        );
        
        // Info sulle istanze del plugin
        $plugin_instances = array(
            'Core' => isset($pnrr_plugin['core']) && is_object($pnrr_plugin['core']),
            'Clone Manager' => isset($pnrr_plugin['clone_manager']) && is_object($pnrr_plugin['clone_manager']),
            'Admin' => isset($pnrr_plugin['admin']) && is_object($pnrr_plugin['admin'])
        );
        
        // Ottieni le opzioni del plugin
        $plugin_options = pnrr_get_option();
        
        // Verifica se i file di log esistono
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/pnrr-logs';
        $log_exists = file_exists($log_dir);
        $log_writable = $log_exists && is_writable($log_dir);
        
        // Crea un log di test
        $test_log = $this->write_test_log();
        ?>
        <div class="wrap">
            <h1>PNRR Page Cloner - Diagnostica</h1>
            
            <div class="notice notice-info inline">
                <p>Questa pagina mostra informazioni diagnostiche che possono essere utili per la risoluzione dei problemi.</p>
            </div>
            
            <h2>Test di Debug</h2>
            <p><strong>Risultato test scrittura log:</strong> <?php echo $test_log ? 'Successo' : 'Fallimento'; ?></p>
            
            <h2>Informazioni di Sistema</h2>
            <table class="widefat fixed" cellspacing="0">
                <thead>
                    <tr>
                        <th>Impostazione</th>
                        <th>Valore</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($debug_info as $key => $value) : ?>
                    <tr>
                        <td><?php echo esc_html($key); ?></td>
                        <td><?php echo is_array($value) ? '<pre>' . esc_html(print_r($value, true)) . '</pre>' : esc_html($value); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <h2>Istanze Plugin</h2>
            <table class="widefat fixed" cellspacing="0">
                <thead>
                    <tr>
                        <th>Componente</th>
                        <th>Stato</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($plugin_instances as $name => $loaded) : ?>
                    <tr>
                        <td><?php echo esc_html($name); ?></td>
                        <td><?php echo $loaded ? '<span style="color:green">Caricato</span>' : '<span style="color:red">Non caricato</span>'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <h2>Opzioni Plugin</h2>
            <table class="widefat fixed" cellspacing="0">
                <thead>
                    <tr>
                        <th>Chiave</th>
                        <th>Valore</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($plugin_options as $key => $value) : ?>
                    <tr>
                        <td><?php echo esc_html($key); ?></td>
                        <td><?php echo is_array($value) ? '<pre>' . esc_html(print_r($value, true)) . '</pre>' : esc_html($value); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <h2>Directory Log</h2>
            <table class="widefat fixed" cellspacing="0">
                <tbody>
                    <tr>
                        <td>Path</td>
                        <td><?php echo esc_html($log_dir); ?></td>
                    </tr>
                    <tr>
                        <td>Esiste</td>
                        <td><?php echo $log_exists ? '<span style="color:green">Sì</span>' : '<span style="color:red">No</span>'; ?></td>
                    </tr>
                    <tr>
                        <td>Scrivibile</td>
                        <td><?php echo $log_writable ? '<span style="color:green">Sì</span>' : '<span style="color:red">No</span>'; ?></td>
                    </tr>
                </tbody>
            </table>
            
            <?php if ($log_exists) : ?>
                <h2>File di Log</h2>
                <form method="post" action="">
                    <?php wp_nonce_field('pnrr_view_logs', 'pnrr_logs_nonce'); ?>
                    <button type="submit" name="pnrr_action" value="view_logs" class="button button-primary">Visualizza ultimi log</button>
                    <button type="submit" name="pnrr_action" value="clear_logs" class="button button-secondary" onclick="return confirm('Sei sicuro di voler cancellare tutti i file di log?')">Cancella log</button>
                </form>
                
                <?php if (isset($_POST['pnrr_action']) && $_POST['pnrr_action'] === 'view_logs' && 
                         isset($_POST['pnrr_logs_nonce']) && wp_verify_nonce($_POST['pnrr_logs_nonce'], 'pnrr_view_logs')) : ?>
                    <h3>Ultimi Log</h3>
                    <div class="log-entries" style="max-height: 400px; overflow-y: auto; background: #f0f0f0; padding: 10px; margin-top: 10px; font-family: monospace;">
                        <?php echo $this->get_latest_logs(100); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_POST['pnrr_action']) && $_POST['pnrr_action'] === 'clear_logs' && 
                         isset($_POST['pnrr_logs_nonce']) && wp_verify_nonce($_POST['pnrr_logs_nonce'], 'pnrr_view_logs')) : 
                    $cleared = $this->clear_all_logs();
                ?>
                    <div class="notice <?php echo $cleared ? 'notice-success' : 'notice-error'; ?> inline">
                        <p><?php echo $cleared ? 'File di log cancellati con successo.' : 'Errore durante la cancellazione dei log.'; ?></p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Ottiene informazioni sui file di log
     * 
     * @return string Informazioni sui log
     */
    private function get_debug_logs_info() {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/pnrr-logs';
        
        if (!file_exists($log_dir)) {
            return 'Directory log non trovata';
        }
        
        $log_files = glob($log_dir . '/pnrr-debug-*.log');
        
        if (empty($log_files)) {
            return 'Nessun file di log trovato';
        }
        
        $count = count($log_files);
        $latest_log = end($log_files);
        $latest_log_size = file_exists($latest_log) ? size_format(filesize($latest_log)) : 'N/A';
        $latest_log_time = file_exists($latest_log) ? date('Y-m-d H:i:s', filemtime($latest_log)) : 'N/A';
        
        return sprintf(
            '%d file di log trovati. Ultimo log: %s, dimensione: %s, ultimo aggiornamento: %s',
            $count,
            basename($latest_log),
            $latest_log_size,
            $latest_log_time
        );
    }
    
    /**
     * Scrive un log di test per verificare che il sistema di logging funzioni
     * 
     * @return bool Esito della scrittura
     */
    private function write_test_log() {
        if (!function_exists('pnrr_debug_log')) {
            return false;
        }
        
        try {
            pnrr_debug_log("Test log da pagina diagnostica", 'info');
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Ottiene gli ultimi log dal file più recente
     * 
     * @param int $lines Numero di linee da visualizzare
     * @return string Contenuto del log
     */
    private function get_latest_logs($lines = 50) {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/pnrr-logs';
        
        if (!file_exists($log_dir)) {
            return 'Directory log non trovata';
        }
        
        $log_files = glob($log_dir . '/pnrr-debug-*.log');
        
        if (empty($log_files)) {
            return 'Nessun file di log trovato';
        }
        
        // Ordina i file per data, più recenti prima
        usort($log_files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        $latest_log = $log_files[0];
        
        if (!file_exists($latest_log)) {
            return 'File di log non trovato';
        }
        
        // Leggi gli ultimi N righe del file
        $file = new SplFileObject($latest_log, 'r');
        $file->seek(PHP_INT_MAX); // Vai alla fine del file
        $total_lines = $file->key(); // Ottieni il numero totale di righe
        
        $start_line = max(0, $total_lines - $lines); // Calcola da dove iniziare
        
        $log_content = '';
        $file->seek($start_line);
        
        while (!$file->eof()) {
            $log_content .= htmlentities($file->fgets());
        }
        
        return $log_content;
    }
    
    /**
     * Cancella tutti i file di log
     * 
     * @return bool Esito dell'operazione
     */
    private function clear_all_logs() {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/pnrr-logs';
        
        if (!file_exists($log_dir)) {
            return false;
        }
        
        $log_files = glob($log_dir . '/pnrr-debug-*.log');
        
        if (empty($log_files)) {
            return true; // Nessun file da cancellare
        }
        
        $success = true;
        foreach ($log_files as $file) {
            if (!unlink($file)) {
                $success = false;
            }
        }
        
        return $success;
    }
}
