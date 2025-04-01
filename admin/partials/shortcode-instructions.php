<div class="pnrr-shortcode-instructions">
    <h2>Istruzioni per l'utilizzo degli Shortcode</h2>
    
    <p>Per inserire dati personalizzati nelle pagine clone, puoi utilizzare i seguenti shortcode nella tua pagina master di Elementor:</p>
    
    <div class="notice notice-info inline">
        <p><strong>Suggerimento:</strong> Gli shortcode possono essere inseriti in qualsiasi widget di Elementor che supporta HTML o testo, inclusi i widget Testo, HTML, Intestazione e altri.</p>
    </div>
    
    <table class="widefat fixed" cellspacing="0">
        <thead>
            <tr>
                <th>Shortcode</th>
                <th>Descrizione</th>
                <th>Attributi opzionali</th>
                <th>Esempio</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>[pnrr_nome]</code></td>
                <td>Visualizza il nome/titolo dell'ente</td>
                <td>Nessuno</td>
                <td><code>[pnrr_nome]</code></td>
            </tr>
            <tr>
                <td><code>[pnrr_logo]</code></td>
                <td>Visualizza il logo dell'ente</td>
                <td>
                    <code>width</code> - Larghezza immagine<br>
                    <code>height</code> - Altezza immagine<br>
                    <code>class</code> - Classe CSS<br>
                    <code>alt</code> - Testo alternativo
                </td>
                <td><code>[pnrr_logo width="200px" height="auto" alt="Logo Comune"]</code></td>
            </tr>
            <tr>
                <td><code>[pnrr_url]</code></td>
                <td>Link al sito web dell'ente</td>
                <td>
                    <code>text</code> - Testo del link<br>
                    <code>class</code> - Classe CSS<br>
                    <code>target</code> - Target del link<br>
                    <code>raw</code> - Se impostato a "true", mostra solo l'URL senza formattarlo come link
                </td>
                <td>
                    <code>[pnrr_url text="Visita il sito ufficiale" class="custom-btn"]</code><br>
                    <code>[pnrr_url raw="true"]</code> - Mostra solo l'URL
                </td>
            </tr>
            <tr>
                <td><code>[pnrr_indirizzo]</code></td>
                <td>Mostra l'indirizzo dell'ente</td>
                <td><code>class</code> - Classe CSS</td>
                <td><code>[pnrr_indirizzo class="address-box"]</code></td>
            </tr>
            <tr>
                <td><code>[pnrr_contatti]</code></td>
                <td>Mostra i contatti dell'ente</td>
                <td><code>class</code> - Classe CSS</td>
                <td>
                    <code>[pnrr_contatti]</code><br>
                    <small>* Converte automaticamente email e numeri di telefono in link cliccabili</small>
                </td>
            </tr>
            <tr>
                <td><code>[pnrr_altre]</code></td>
                <td>Mostra altre informazioni</td>
                <td><code>class</code> - Classe CSS</td>
                <td><code>[pnrr_altre]</code></td>
            </tr>
        </tbody>
    </table>
    
    <h3>Come utilizzare gli shortcode</h3>
    
    <ol>
        <li>Apri la pagina master in Elementor</li>
        <li>Aggiungi o seleziona un widget di testo o HTML</li>
        <li>Inserisci lo shortcode nel contenuto del widget</li>
        <li>Durante la clonazione, gli shortcode verranno sostituiti con i dati specifici di ciascun clone</li>
    </ol>
    
    <h3>Esempi pratici</h3>
    
    <div class="shortcode-examples">
        <h4>Esempio 1: Intestazione con logo e titolo</h4>
        <pre>&lt;div class="site-header"&gt;
    [pnrr_logo width="150px" height="auto" alt="Logo"]
    &lt;h1&gt;[pnrr_nome]&lt;/h1&gt;
&lt;/div&gt;</pre>

        <h4>Esempio 2: Sezione contatti con link al sito</h4>
        <pre>&lt;div class="contact-section"&gt;
    &lt;h3&gt;Contattaci&lt;/h3&gt;
    &lt;div class="address"&gt;[pnrr_indirizzo]&lt;/div&gt;
    &lt;div class="contacts"&gt;[pnrr_contatti]&lt;/div&gt;
    &lt;div class="website"&gt;
        Visita il nostro sito web: [pnrr_url text="Clicca qui" class="button"]
    &lt;/div&gt;
&lt;/div&gt;</pre>

        <h4>Esempio 3: HTML personalizzato con URL raw</h4>
        <pre>&lt;a href="[pnrr_url raw=true]" class="custom-button" target="_blank"&gt;
    &lt;img src="[pnrr_logo raw=true]" width="20" height="20"&gt; Vai al sito [pnrr_nome]
&lt;/a&gt;</pre>
    </div>
    
    <div class="notice notice-warning inline">
        <p><strong>Nota:</strong> Per preservare la formattazione HTML nei contenuti (ad esempio indirizzo e contatti), assicurati che il campo "Permetti HTML" sia abilitato nelle impostazioni di importazione CSV.</p>
    </div>
    
    <div class="notice notice-info inline">
        <p><strong>Visualizzazione nell'editor:</strong> Nell'editor Elementor, gli shortcode verranno visualizzati come placeholder. I dati reali appariranno solo nelle pagine clone generate.</p>
    </div>
    
    <h3>Risoluzione problemi</h3>
    
    <ul>
        <li><strong>Gli shortcode non vengono sostituiti:</strong> Verifica che gli shortcode siano scritti correttamente, incluse le parentesi quadre.</li>
        <li><strong>HTML non visualizzato correttamente:</strong> Assicurati che lo shortcode sia inserito in un widget che supporta HTML (come il widget HTML o Testo).</li>
        <li><strong>Immagini non visualizzate:</strong> Verifica che l'URL dell'immagine sia corretto e accessibile pubblicamente.</li>
    </ul>
</div>

<div class="pnrr-docs-export">
    <button type="button" id="export-docs-pdf" class="button button-secondary">
        <span class="dashicons dashicons-media-document"></span> 
        Esporta documentazione in PDF
    </button>
</div>

<script>
jQuery(document).ready(function($) {
    $('#export-docs-pdf').on('click', function() {
        window.location.href = ajaxurl + '?action=pnrr_export_docs_pdf&nonce=' + pnrr_cloner.nonce;
    });
});
</script>

<style>
.shortcode-examples pre {
    background: #f5f5f5;
    padding: 15px;
    border-left: 4px solid #0073aa;
    margin-bottom: 20px;
    overflow: auto;
    white-space: pre-wrap;
}
.pnrr-shortcode-instructions table td {
    vertical-align: top;
}
</style>
