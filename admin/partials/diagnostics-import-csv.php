<div class="pnrr-csv-diagnostics-tool">
    <h2>Strumento di Diagnostica CSV</h2>
    
    <p>Utilizza questo strumento per verificare se un file CSV è formattato correttamente per l'importazione.</p>
    
    <form id="pnrr-csv-diagnostics-form" method="post" enctype="multipart/form-data">
        <?php wp_nonce_field('pnrr_csv_diagnostics', 'pnrr_csv_diagnostics_nonce'); ?>
        <div class="form-group">
            <label for="csv-diagnostics-file">Seleziona file CSV:</label>
            <input type="file" name="csv-diagnostics-file" id="csv-diagnostics-file" accept=".csv">
        </div>
        
        <div class="form-actions">
            <button type="submit" name="pnrr_csv_diagnostics" value="analyze" class="button button-primary">Analizza File CSV</button>
        </div>
    </form>
    
    <?php
    // Gestione del form inviato
    if (isset($_POST['pnrr_csv_diagnostics']) && $_POST['pnrr_csv_diagnostics'] === 'analyze') {
        // Verifica nonce
        if (!isset($_POST['pnrr_csv_diagnostics_nonce']) || !wp_verify_nonce($_POST['pnrr_csv_diagnostics_nonce'], 'pnrr_csv_diagnostics')) {
            echo '<div class="notice notice-error"><p>Verifica di sicurezza fallita.</p></div>';
            return;
        }
        
        // Verifica file caricato
        if (!isset($_FILES['csv-diagnostics-file']) || $_FILES['csv-diagnostics-file']['error'] !== UPLOAD_ERR_OK) {
            echo '<div class="notice notice-error"><p>Errore nel caricamento del file.</p></div>';
            return;
        }
        
        // Verifica estensione
        $file_info = pathinfo($_FILES['csv-diagnostics-file']['name']);
        if (!isset($file_info['extension']) || strtolower($file_info['extension']) !== 'csv') {
            echo '<div class="notice notice-error"><p>Il file deve essere in formato CSV.</p></div>';
            return;
        }
        
        // Elabora il file
        $tmp_file = $_FILES['csv-diagnostics-file']['tmp_name'];
        $csv_analysis = analyze_csv_file($tmp_file);
        
        // Mostra risultati
        display_csv_analysis_results($csv_analysis);
    }
    
    /**
     * Analizza un file CSV per diagnostica
     * 
     * @param string $file_path Percorso del file
     * @return array Risultati dell'analisi
     */
    function analyze_csv_file($file_path) {
        $result = [
            'file_name' => basename($_FILES['csv-diagnostics-file']['name']),
            'file_size' => size_format($_FILES['csv-diagnostics-file']['size']),
            'encoding' => 'Sconosciuta',
            'bom_detected' => false,
            'delimiter' => 'Sconosciuto',
            'headers' => [],
            'required_headers' => ['nome'],
            'sample_rows' => [],
            'errors' => [],
            'warnings' => [],
        ];
        
        // Leggi primi byte per rilevare BOM
        $handle = fopen($file_path, 'r');
        if (!$handle) {
            $result['errors'][] = "Impossibile aprire il file";
            return $result;
        }
        
        $first_bytes = fread($handle, 3);
        rewind($handle);
        $bom = "\xEF\xBB\xBF"; // UTF-8 BOM
        $result['bom_detected'] = (substr($first_bytes, 0, 3) === $bom);
        
        // Se c'è BOM, salta i primi 3 byte
        if ($result['bom_detected']) {
            fseek($handle, 3);
            $result['encoding'] = 'UTF-8 con BOM';
        } else {
            // Prova a rilevare l'encoding
            $sample_content = fread($handle, 1000);
            rewind($handle);
            
            if (mb_check_encoding($sample_content, 'UTF-8')) {
                $result['encoding'] = 'UTF-8 senza BOM';
            } else if (mb_check_encoding($sample_content, 'ISO-8859-1')) {
                $result['encoding'] = 'ISO-8859-1';
            } else if (mb_check_encoding($sample_content, 'Windows-1252')) {
                $result['encoding'] = 'Windows-1252';
            }
        }
        
        // Rileva delimitatore
        $first_line = fgets($handle);
        rewind($handle);
        if ($result['bom_detected']) {
            fseek($handle, 3);
        }
        
        $comma_count = substr_count($first_line, ',');
        $semicolon_count = substr_count($first_line, ';');
        $tab_count = substr_count($first_line, "\t");
        
        $delimiter = ','; // Default
        $delimiter_name = 'Virgola (,)';
        
        if ($semicolon_count > $comma_count && $semicolon_count > $tab_count) {
            $delimiter = ';';
            $delimiter_name = 'Punto e virgola (;)';
        } else if ($tab_count > $comma_count && $tab_count > $semicolon_count) {
            $delimiter = "\t";
            $delimiter_name = 'Tab';
        }
        
        $result['delimiter'] = $delimiter_name;
        
        // Leggi intestazioni
        $headers = fgetcsv($handle, 0, $delimiter, '"', '"');
        if (!$headers) {
            $result['errors'][] = "Impossibile leggere le intestazioni del file";
            fclose($handle);
            return $result;
        }
        
        // Normalizza intestazioni
        $headers = array_map('trim', $headers);
        $headers = array_map('strtolower', $headers);
        $result['headers'] = $headers;
        
        // Verifica intestazioni richieste
        $missing_headers = array_diff($result['required_headers'], $headers);
        if (!empty($missing_headers)) {
            $result['errors'][] = "Mancano le seguenti intestazioni obbligatorie: " . implode(', ', $missing_headers);
        }
        
        // Mapping applicato durante l'importazione
        $field_mapping = [
            'nome' => 'title',
            'logo' => 'logo_url',
            'logo (url)' => 'logo_url',
            'url sito' => 'home_url',
            'url' => 'home_url',
            'indirizzo' => 'address',
            'indirizzo (testo)' => 'address',
            'contatti' => 'contacts',
            'contatti (testo)' => 'contacts',
            'altro' => 'other_info',
            'altre' => 'other_info',
            'altre informazioni' => 'other_info',
            'altro (testo)' => 'other_info'
        ];
        
        // Mappa le intestazioni
        $mapped_headers = [];
        foreach ($headers as $header) {
            if (isset($field_mapping[$header])) {
                $mapped_headers[$header] = $field_mapping[$header];
            } else {
                $mapped_headers[$header] = 'Non mappato';
                $result['warnings'][] = "Intestazione '{$header}' non mappata ad alcun campo";
            }
        }
        $result['mapped_headers'] = $mapped_headers;
        
        // Leggi alcune righe di esempio (massimo 3)
        $sample_count = 0;
        while (($row = fgetcsv($handle, 0, $delimiter, '"', '"')) !== false && $sample_count < 3) {
            if (count($row) !== count($headers)) {
                $result['warnings'][] = "La riga " . ($sample_count + 1) . " ha un numero di colonne diverso dalle intestazioni";
            }
            
            // Formatta i dati con gli header
            $formatted_row = [];
            foreach ($headers as $i => $header) {
                $value = isset($row[$i]) ? $row[$i] : '';
                $formatted_row[$header] = $value;
            }
            
            $result['sample_rows'][] = $formatted_row;
            $sample_count++;
        }
        
        fclose($handle);
        return $result;
    }
    
    /**
     * Visualizza i risultati dell'analisi CSV
     * 
     * @param array $analysis Risultati dell'analisi
     */
    function display_csv_analysis_results($analysis) {
        ?>
        <div class="csv-analysis-results">
            <h3>Risultati Analisi CSV</h3>
            
            <?php if (!empty($analysis['errors'])): ?>
                <div class="notice notice-error">
                    <p><strong>Errori rilevati:</strong></p>
                    <ul>
                        <?php foreach ($analysis['errors'] as $error): ?>
                            <li><?php echo esc_html($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($analysis['warnings'])): ?>
                <div class="notice notice-warning">
                    <p><strong>Avvertimenti:</strong></p>
                    <ul>
                        <?php foreach ($analysis['warnings'] as $warning): ?>
                            <li><?php echo esc_html($warning); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if (empty($analysis['errors'])): ?>
                <div class="notice notice-success">
                    <p>Il file sembra essere formattato correttamente per l'importazione.</p>
                    <?php if ($analysis['delimiter'] !== 'Punto e virgola (;)'): ?>
                        <p><strong>Nota:</strong> Il delimitatore rilevato è "<?php echo esc_html($analysis['delimiter']); ?>", ma l'importatore si aspetta "Punto e virgola (;)".</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <table class="widefat">
                <tr>
                    <th>Nome file:</th>
                    <td><?php echo esc_html($analysis['file_name']); ?></td>
                </tr>
                <tr>
                    <th>Dimensione:</th>
                    <td><?php echo esc_html($analysis['file_size']); ?></td>
                </tr>
                <tr>
                    <th>Codifica:</th>
                    <td><?php echo esc_html($analysis['encoding']); ?></td>
                </tr>
                <tr>
                    <th>Delimitatore rilevato:</th>
                    <td><?php echo esc_html($analysis['delimiter']); ?></td>
                </tr>
                <tr>
                    <th>Intestazioni trovate:</th>
                    <td><?php echo esc_html(implode(', ', $analysis['headers'])); ?></td>
                </tr>
            </table>
            
            <h4>Mappatura colonne:</h4>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Intestazione CSV</th>
                        <th>Mappato a</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($analysis['mapped_headers'] as $header => $mapped): ?>
                        <tr>
                            <td><?php echo esc_html($header); ?></td>
                            <td><?php echo esc_html($mapped); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if (!empty($analysis['sample_rows'])): ?>
                <h4>Righe di esempio:</h4>
                <table class="widefat">
                    <thead>
                        <tr>
                            <?php foreach ($analysis['headers'] as $header): ?>
                                <th><?php echo esc_html($header); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($analysis['sample_rows'] as $row): ?>
                            <tr>
                                <?php foreach ($analysis['headers'] as $header): ?>
                                    <td><?php echo esc_html($row[$header] ?? ''); ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
    ?>
</div>

<style>
.csv-analysis-results {
    margin-top: 20px;
}
.csv-analysis-results table {
    margin-bottom: 20px;
}
.csv-analysis-results th {
    text-align: left;
    width: 200px;
}
</style>
