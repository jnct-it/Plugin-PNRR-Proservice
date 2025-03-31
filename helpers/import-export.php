<?php
/**
 * Funzioni per l'importazione ed esportazione dei dati
 *
 * @since 1.0.0
 */

// Assicurarsi che il plugin non sia accessibile direttamente
if (!defined('ABSPATH')) {
    exit;
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

/**
 * Processa l'importazione di un file CSV
 * 
 * @param string $file_path Percorso al file CSV temporaneo
 * @return array|WP_Error Risultato dell'importazione o errore
 */
function pnrr_process_csv_import($file_path) {
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
 * Prepara i dati per l'esportazione CSV
 * 
 * @param array $clone_data Array con i dati dei cloni
 * @return string Contenuto CSV pronto per il download
 */
function pnrr_prepare_csv_export($clone_data) {
    if (empty($clone_data)) {
        return '';
    }
    
    // Prepara l'output CSV
    $output = fopen('php://temp', 'r+');
    
    // Aggiungi intestazioni
    fputcsv($output, ['slug', 'title', 'logo_url', 'footer_text', 'home_url', 'enabled']);
    
    // Aggiungi righe
    foreach ($clone_data as $clone) {
        $row = [
            $clone['slug'],
            $clone['title'],
            isset($clone['logo_url']) ? $clone['logo_url'] : '',
            isset($clone['footer_text']) ? $clone['footer_text'] : '',
            isset($clone['home_url']) ? $clone['home_url'] : '',
            isset($clone['enabled']) ? ($clone['enabled'] ? '1' : '0') : '1'
        ];
        fputcsv($output, $row);
    }
    
    // Ottieni il contenuto
    rewind($output);
    $csv_content = stream_get_contents($output);
    fclose($output);
    
    return $csv_content;
}
