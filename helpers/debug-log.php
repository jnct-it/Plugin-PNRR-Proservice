<?php
/**
 * Funzioni di debug e logging
 *
 * @since 1.1.0
 */

// Assicurarsi che il plugin non sia accessibile direttamente
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Scrive un messaggio di log nel file debug.log di WordPress
 *
 * @param mixed $message Il messaggio da loggare
 * @param string $level Il livello di log (info, warning, error)
 */
function pnrr_log($message, $level = 'info') {
    if (!WP_DEBUG || !WP_DEBUG_LOG) {
        return;
    }
    
    // Formatta il messaggio
    if (is_array($message) || is_object($message)) {
        $message = print_r($message, true);
    }
    
    // Prefisso con data e livello
    $prefix = '[' . date('Y-m-d H:i:s') . '] [PNRR ' . strtoupper($level) . '] ';
    
    // Scrivi nel log
    error_log($prefix . $message);
}

/**
 * Log di debug che funziona anche senza WP_DEBUG
 * 
 * @param string $message Messaggio da loggare
 * @param string $level Livello di log (info, warning, error)
 * @return bool Esito dell'operazione di log
 */
function pnrr_debug_log($message, $level = 'info') {
    // Crea directory dei log se non esiste
    $upload_dir = wp_upload_dir();
    $log_dir = $upload_dir['basedir'] . '/pnrr-logs';
    if (!file_exists($log_dir)) {
        wp_mkdir_p($log_dir);
    }
    
    // Nome file di log basato sulla data
    $log_file = $log_dir . '/pnrr-debug-' . date('Y-m-d') . '.log';
    
    // Prefisso con data, ora e livello
    $prefix = '[' . date('Y-m-d H:i:s') . '] [' . strtoupper($level) . '] ';
    
    // Formatta il messaggio
    if (is_array($message) || is_object($message)) {
        $message = print_r($message, true);
    }
    
    // Includi il riferimento al chiamante
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
    $caller = isset($backtrace[1]) ? basename($backtrace[1]['file']) . ':' . $backtrace[1]['line'] : 'unknown';
    $prefix .= '[' . $caller . '] ';
    
    // Scrivi nel log
    return error_log($prefix . $message . PHP_EOL, 3, $log_file);
}

/**
 * Ottiene il percorso della directory dei log
 *
 * @return string Percorso della directory dei log
 */
function pnrr_get_log_directory() {
    $upload_dir = wp_upload_dir();
    return $upload_dir['basedir'] . '/pnrr-logs';
}

/**
 * Ottiene l'URL della directory dei log per l'accesso tramite browser
 *
 * @return string URL della directory dei log
 */
function pnrr_get_log_directory_url() {
    $upload_dir = wp_upload_dir();
    return $upload_dir['baseurl'] . '/pnrr-logs';
}

/**
 * Registra informazioni sull'importazione CSV
 *
 * @param string $csv_path Percorso del file CSV importato
 * @param array $result Risultati dell'importazione
 * @return void
 */
function pnrr_log_csv_import($csv_path, $result) {
    // Determina il risultato dell'importazione
    $status = isset($result['success']) && $result['success'] ? 'SUCCESS' : 'FAILURE';
    
    // Costruisci messaggio di log
    $message = "CSV Import {$status} - File: " . basename($csv_path) . "\n";
    $message .= "Records imported: " . (isset($result['imported']) ? $result['imported'] : 'unknown') . "\n";
    
    if (isset($result['pages_created'])) {
        $message .= "Pages created: " . $result['pages_created'] . "\n";
    }
    
    if (isset($result['errors']) && is_array($result['errors'])) {
        $message .= "Errors: " . count($result['errors']) . "\n";
        foreach ($result['errors'] as $i => $error) {
            $message .= " - Error " . ($i + 1) . ": {$error}\n";
        }
    }
    
    // Log dei dati analizzati
    pnrr_debug_log($message, $status === 'SUCCESS' ? 'info' : 'error');
    
    // Salva anche i risultati come opzione per la dashboard
    update_option('pnrr_last_csv_import', [
        'time' => current_time('mysql'),
        'file' => basename($csv_path),
        'status' => $status,
        'result' => $result
    ]);
}

/**
 * Leggi l'ultimo errore di importazione CSV
 *
 * @return array|bool Array con le informazioni sull'ultimo errore o false se non ci sono errori
 */
function pnrr_get_last_import_error() {
    $last_import = get_option('pnrr_last_csv_import', []);
    if (isset($last_import['status']) && $last_import['status'] === 'FAILURE') {
        return $last_import;
    }
    return false;
}
