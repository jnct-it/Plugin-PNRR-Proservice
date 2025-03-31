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
 * @param bool $create_pages Se true, crea automaticamente le pagine clone
 * @return array|WP_Error Risultato dell'importazione o errore
 */
function pnrr_process_csv_import($file_path, $create_pages = false) {
    global $pnrr_plugin;
    
    // Verifica che l'istanza del clone manager sia disponibile
    if (!isset($pnrr_plugin['clone_manager']) || !is_object($pnrr_plugin['clone_manager'])) {
        return new WP_Error('clone_manager_not_found', 'Errore: Gestore dei cloni non trovato');
    }
    
    $clone_manager = $pnrr_plugin['clone_manager'];
    
    // Apri il file CSV
    $handle = fopen($file_path, 'r');
    if (!$handle) {
        return new WP_Error('file_open_error', 'Impossibile aprire il file CSV');
    }
    
    // Prova a rilevare automaticamente il delimitatore
    $first_line = fgets($handle);
    rewind($handle);
    
    $delimiter = ',';  // Default a virgola
    
    // Controlla se il primo rigo contiene il punto e virgola
    if (strpos($first_line, ';') !== false) {
        $delimiter = ';';
    }
    
    // Log per debug
    if (function_exists('pnrr_debug_log')) {
        pnrr_debug_log("Importazione CSV - Delimitatore rilevato: " . $delimiter);
    }
    
    // Leggi intestazioni con il delimitatore corretto
    $header = fgetcsv($handle, 0, $delimiter);
    if (!$header) {
        fclose($handle);
        return new WP_Error('invalid_format', 'Formato CSV non valido o intestazioni mancanti');
    }
    
    // Normalizza le intestazioni (tutto minuscolo e trim)
    $header = array_map('trim', $header);
    $header = array_map('strtolower', $header);
    
    // Log delle intestazioni trovate
    if (function_exists('pnrr_debug_log')) {
        pnrr_debug_log("Intestazioni trovate: " . print_r($header, true));
    }
    
    // Mapping aggiornato per le nuove colonne del CSV
    $field_mapping = [
        'nome'             => 'title',
        'logo'             => 'logo_url',
        'logo (url)'       => 'logo_url',
        'url sito'         => 'home_url',
        'url'              => 'home_url',
        'indirizzo'        => 'address',
        'indirizzo (testo)'=> 'address',
        'contatti'         => 'contacts',
        'contatti (testo)' => 'contacts',
        'altro'            => 'other_info',
        'altro (testo)'    => 'other_info'
    ];
    
    // Verifica che l'intestazione obbligatoria "nome" sia presente
    $required_found = false;
    foreach ($header as $column) {
        if ($column === 'nome') {
            $required_found = true;
            break;
        }
    }
    
    if (!$required_found) {
        fclose($handle);
        return new WP_Error(
            'missing_required', 
            'Intestazione obbligatoria "Nome" non trovata. Intestazioni trovate: ' . implode(', ', $header)
        );
    }
    
    // Avvia l'importazione
    $clones_data = [];
    $row_number = 1; // La prima riga è l'intestazione
    
    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
        $row_number++;
        
        if (count($row) < count($header)) {
            // Riga incompleta, aggiungi celle vuote
            $row = array_pad($row, count($header), '');
        }
        
        // Costruisci l'array dei dati del clone
        $clone_data = [];
        
        // Processa ogni colonna
        foreach ($header as $index => $column) {
            if (isset($field_mapping[$column])) {
                $mapped_field = $field_mapping[$column];
                $value = isset($row[$index]) ? trim($row[$index]) : '';
                $clone_data[$mapped_field] = $value;
            }
        }
        
        // Assicura che ci sia almeno un titolo
        if (empty($clone_data['title'])) {
            continue; // Salta righe senza titolo
        }
        
        // Genera automaticamente lo slug se non presente o vuoto
        if (empty($clone_data['slug'])) {
            $clone_data['slug'] = sanitize_title($clone_data['title']);
        }
        
        // Aggiungi campi obbligatori per il salvataggio
        $clone_data['enabled'] = true;
        $clone_data['clone_uuid'] = 'pnrr_' . uniqid();
        $clone_data['last_updated'] = current_time('mysql');
        
        $clones_data[] = $clone_data;
    }
    
    fclose($handle);
    
    // Se non ci sono dati, restituisci un errore
    if (empty($clones_data)) {
        return new WP_Error('no_data', 'Nessun dato valido trovato nel CSV');
    }
    
    // Prepara i dati nel formato corretto per il plugin
    $option_name = $clone_manager->get_option_name();
    $result = update_option($option_name, $clones_data);
    
    if (!$result) {
        return new WP_Error('update_failed', 'Impossibile aggiornare i dati dei cloni');
    }
    
    // Aggiorna anche la proprietà del clone manager (ricarica i dati)
    $clone_manager->reload_data();
    
    $response = [
        'imported' => count($clones_data),
        'success' => true
    ];
    
    // Se richiesto, crea le pagine clone
    if ($create_pages) {
        $source_page = $clone_manager->get_master_page();
        if (is_wp_error($source_page)) {
            $response['pages_created'] = 0;
            $response['page_creation_error'] = $source_page->get_error_message();
        } else {
            $pages_created = 0;
            $errors = [];
            
            foreach ($clones_data as $index => $clone_data) {
                $result = $clone_manager->clone_single_page($source_page, $clone_data);
                if (is_wp_error($result)) {
                    $errors[] = $result->get_error_message();
                } else {
                    $pages_created++;
                }
            }
            
            $response['pages_created'] = $pages_created;
            if (!empty($errors)) {
                $response['page_creation_errors'] = $errors;
            }
        }
    }
    
    return $response;
}

/**
 * Esporta i dati dei cloni in formato CSV
 *
 * @return string URL del file CSV generato
 */
function pnrr_export_clones_csv() {
    global $pnrr_plugin;

    // Verifica che l'istanza del clone manager sia disponibile
    if (!isset($pnrr_plugin['clone_manager']) || !is_object($pnrr_plugin['clone_manager'])) {
        return new WP_Error('clone_manager_not_found', 'Errore: Gestore dei cloni non trovato');
    }

    $clone_manager = $pnrr_plugin['clone_manager'];
    $clones = $clone_manager->get_clone_data();

    if (empty($clones)) {
        return new WP_Error('no_clones', 'Nessun clone disponibile per l\'esportazione');
    }

    // Crea directory di esportazione
    $upload_dir = wp_upload_dir();
    $export_dir = $upload_dir['basedir'] . '/pnrr_exports';

    if (!is_dir($export_dir)) {
        wp_mkdir_p($export_dir);
    }

    // Crea file CSV
    $filename = 'pnrr-clones-export-' . date('Y-m-d-His') . '.csv';
    $filepath = $export_dir . '/' . $filename;

    $csv_handle = fopen($filepath, 'w');

    // Usa lo stesso delimitatore dell'import per coerenza
    $delimiter = ';';
    
    // Scrivi intestazioni con le nuove colonne
    fputcsv($csv_handle, ['Nome', 'Logo (url)', 'Url sito', 'Indirizzo (testo)', 'Contatti (testo)', 'Altro (testo)'], $delimiter);

    // Scrivi dati
    foreach ($clones as $clone) {
        $row = [
            isset($clone['title']) ? $clone['title'] : '',
            isset($clone['logo_url']) ? $clone['logo_url'] : '',
            isset($clone['home_url']) ? $clone['home_url'] : '',
            isset($clone['address']) ? $clone['address'] : '',
            isset($clone['contacts']) ? $clone['contacts'] : '',
            isset($clone['other_info']) ? $clone['other_info'] : ''
        ];

        fputcsv($csv_handle, $row, $delimiter);
    }

    fclose($csv_handle);

    return $upload_dir['baseurl'] . '/pnrr_exports/' . $filename;
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
    fputcsv($output, ['Nome', 'Logo (url)', 'Url sito', 'Indirizzo (testo)', 'Contatti (testo)', 'Altro (testo)']);
    
    // Aggiungi righe
    foreach ($clone_data as $clone) {
        $row = [
            isset($clone['title']) ? $clone['title'] : '',
            isset($clone['logo_url']) ? $clone['logo_url'] : '',
            isset($clone['home_url']) ? $clone['home_url'] : '',
            isset($clone['address']) ? $clone['address'] : '',
            isset($clone['contacts']) ? $clone['contacts'] : '',
            isset($clone['other_info']) ? $clone['other_info'] : ''
        ];
        fputcsv($output, $row);
    }
    
    // Ottieni il contenuto
    rewind($output);
    $csv_content = stream_get_contents($output);
    fclose($output);
    
    return $csv_content;
}
