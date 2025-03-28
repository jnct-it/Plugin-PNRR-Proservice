<?php
/**
 * Funzioni di utilità per il plugin PNRR Page Cloner
 *
 * @since 1.0.0
 */

// Assicurarsi che il plugin non sia accessibile direttamente
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Funzione di autoload per le classi del plugin
 */
spl_autoload_register('pnrr_autoload');

function pnrr_autoload($class_name) {
    // Verifica se la classe appartiene al plugin
    if (strpos($class_name, 'PNRR_') !== 0) {
        return;
    }
    
    // Converti il nome della classe in nome del file
    $class_file = 'class-' . strtolower(str_replace('_', '-', $class_name)) . '.php';
    
    // Cerca il file nelle directory del plugin
    $directories = array('includes', 'admin');
    foreach ($directories as $directory) {
        $file_path = PNRR_PLUGIN_DIR . $directory . '/' . $class_file;
        if (file_exists($file_path)) {
            require_once $file_path;
            return;
        }
    }
}

/**
 * Carica le classi principali del plugin
 */
require_once PNRR_PLUGIN_DIR . 'includes/class-pnrr-core.php';
require_once PNRR_PLUGIN_DIR . 'includes/class-pnrr-clone.php';
require_once PNRR_PLUGIN_DIR . 'admin/class-pnrr-admin.php';

/**
 * Ottiene le opzioni del plugin
 *
 * @param string $key Chiave dell'opzione da recuperare
 * @param mixed $default Valore di default se l'opzione non esiste
 * @return mixed Valore dell'opzione o array completo se $key è null
 */
function pnrr_get_option($key = null, $default = null) {
    $options = get_option(PNRR_OPTION_NAME, array());
    
    if (is_null($key)) {
        return $options;
    }
    
    return isset($options[$key]) ? $options[$key] : $default;
}

/**
 * Aggiorna un'opzione del plugin
 *
 * @param string $key Chiave dell'opzione da aggiornare
 * @param mixed $value Nuovo valore
 * @return bool Esito dell'operazione
 */
function pnrr_update_option($key, $value) {
    $options = get_option(PNRR_OPTION_NAME, array());
    $options[$key] = $value;
    return update_option(PNRR_OPTION_NAME, $options);
}

/**
 * Crea le directory necessarie al plugin
 */
function pnrr_create_required_directories() {
    // Crea la directory admin/partials se non esiste
    $partials_dir = PNRR_PLUGIN_DIR . 'admin/partials';
    if (!file_exists($partials_dir)) {
        wp_mkdir_p($partials_dir);
    }
}

/**
 * Analizza un file CSV e restituisce un array di dati
 *
 * @param string $file_path Percorso completo del file CSV
 * @return array|WP_Error Array di dati o WP_Error in caso di problemi
 */
function pnrr_parse_csv_file($file_path) {
    // Verifica che il file esista
    if (!file_exists($file_path)) {
        return new WP_Error('file_not_found', 'Il file CSV non è stato trovato');
    }
    
    // Verifica che il file sia leggibile
    if (!is_readable($file_path)) {
        return new WP_Error('file_not_readable', 'Il file CSV non può essere letto');
    }
    
    // Apri il file in modalità lettura
    $handle = fopen($file_path, 'r');
    if (!$handle) {
        return new WP_Error('file_open_error', 'Impossibile aprire il file CSV');
    }
    
    // Leggi la prima riga (intestazioni)
    $headers = fgetcsv($handle, 0, ',');
    
    // Converti le intestazioni in minuscolo per facilitare il confronto
    $headers = array_map('strtolower', $headers);
    
    // Verifica la presenza delle colonne obbligatorie
    $required_columns = array('slug', 'title');
    $optional_columns = array('logo_url', 'footer_text', 'home_url');
    
    foreach ($required_columns as $column) {
        if (!in_array($column, $headers)) {
            fclose($handle);
            return new WP_Error(
                'missing_column',
                sprintf('Colonna obbligatoria mancante: %s', $column)
            );
        }
    }
    
    // Prepara l'array dei risultati
    $data = array();
    
    // Leggi tutte le righe e costruisci l'array dei dati
    $row_number = 1; // La prima riga (intestazione) è già stata letta
    while (($row = fgetcsv($handle, 0, ',')) !== false) {
        $row_number++;
        
        // Verifica che la riga abbia lo stesso numero di colonne delle intestazioni
        if (count($row) !== count($headers)) {
            fclose($handle);
            return new WP_Error(
                'invalid_row',
                sprintf('La riga %d ha un numero errato di colonne', $row_number)
            );
        }
        
        // Combina intestazioni e valori
        $row_data = array_combine($headers, $row);
        
        // Verifica che slug e title non siano vuoti
        if (empty($row_data['slug']) || empty($row_data['title'])) {
            fclose($handle);
            return new WP_Error(
                'missing_required_data',
                sprintf('Dati obbligatori mancanti alla riga %d', $row_number)
            );
        }
        
        // Assicurati che tutte le colonne opzionali esistano
        foreach ($optional_columns as $column) {
            if (!isset($row_data[$column])) {
                $row_data[$column] = '';
            }
        }
        
        // Aggiungi valori predefiniti
        $row_data['enabled'] = true;
        $row_data['last_updated'] = current_time('mysql');
        
        // Aggiungi la riga all'array dei dati
        $data[] = $row_data;
    }
    
    fclose($handle);
    
    // Verifica che ci siano dati
    if (empty($data)) {
        return new WP_Error('empty_data', 'Il file CSV non contiene dati validi');
    }
    
    return $data;
}

/**
 * Converti un percorso file caricato in un percorso assoluto e verifica il tipo di file
 * 
 * @param array $file Array $_FILES dell'upload
 * @return string|WP_Error Percorso assoluto al file o errore
 */
function pnrr_validate_uploaded_csv($file) {
    // Verifica errori di caricamento
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error_messages = array(
            UPLOAD_ERR_INI_SIZE => 'Il file caricato supera la direttiva upload_max_filesize in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'Il file caricato supera la direttiva MAX_FILE_SIZE specificata nel form HTML',
            UPLOAD_ERR_PARTIAL => 'Il file è stato caricato solo parzialmente',
            UPLOAD_ERR_NO_FILE => 'Nessun file è stato caricato',
            UPLOAD_ERR_NO_TMP_DIR => 'Manca una cartella temporanea',
            UPLOAD_ERR_CANT_WRITE => 'Impossibile scrivere il file su disco',
            UPLOAD_ERR_EXTENSION => 'Un\'estensione PHP ha interrotto il caricamento del file'
        );
        
        $message = isset($error_messages[$file['error']]) 
            ? $error_messages[$file['error']] 
            : 'Errore sconosciuto durante il caricamento del file';
        
        return new WP_Error('upload_error', $message);
    }
    
    // Verifica dimensione
    if ($file['size'] > 5 * 1024 * 1024) { // 5MB max
        return new WP_Error('file_too_large', 'Il file è troppo grande (massimo 5MB)');
    }
    
    // Verifica estensione e tipo MIME
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->file($file['tmp_name']);
    
    $allowed_types = array(
        'text/csv',
        'text/plain',
        'application/csv',
        'application/excel',
        'application/vnd.ms-excel',
        'application/vnd.msexcel'
    );
    
    // Verifica MIME type
    if (!in_array($mime_type, $allowed_types)) {
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        // Accetta comunque file con estensione .csv anche se il MIME type non corrisponde
        if (strtolower($ext) !== 'csv') {
            return new WP_Error(
                'invalid_file_type', 
                sprintf('Tipo di file non consentito. Tipo rilevato: %s', $mime_type)
            );
        }
    }
    
    return $file['tmp_name'];
}
