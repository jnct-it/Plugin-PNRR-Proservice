<div id="edit-clone-modal" class="pnrr-modal">
    <div class="pnrr-modal-content">
        <span class="pnrr-modal-close">&times;</span>
        <h3>Modifica Clone</h3>
        
        <!-- Tab navigation -->
        <div class="pnrr-modal-tabs">
            <button type="button" class="tab-button active" data-tab="basic-info">Info Principali</button>
            <button type="button" class="tab-button" data-tab="visual-elements">Elementi Visivi</button>
            <button type="button" class="tab-button" data-tab="additional-info">Informazioni Aggiuntive</button>
        </div>
        
        <form id="edit-clone-form">
            <input type="hidden" id="edit-clone-id" name="clone_id" value="">
            
            <!-- Basic Info Tab -->
            <div class="tab-content active" id="basic-info">
                <div class="form-group">
                    <label for="edit-clone-slug">Slug: <span class="required-field">*</span></label>
                    <input type="text" id="edit-clone-slug" name="slug" class="widefat" required>
                    <div class="field-validation" id="slug-validation"></div>
                    <p class="description">Utilizzato nell'URL della pagina. Solo lettere minuscole, numeri e trattini.</p>
                </div>
                
                <div class="form-group">
                    <label for="edit-clone-title">Titolo: <span class="required-field">*</span></label>
                    <input type="text" id="edit-clone-title" name="title" class="widefat" required>
                    <div class="field-validation" id="title-validation"></div>
                    <p class="description">Nome visualizzato dell'ente.</p>
                </div>
                
                <div class="form-group">
                    <label for="edit-clone-home-url">URL del sito web:</label>
                    <input type="url" id="edit-clone-home-url" name="home_url" class="widefat" placeholder="https://www.esempio.it">
                    <div class="field-validation" id="url-validation"></div>
                    <p class="description">URL del sito web dell'ente.</p>
                </div>
                
                <div class="form-group">
                    <label for="edit-clone-enabled">Stato:</label>
                    <div class="toggle-switch">
                        <input type="checkbox" id="edit-clone-enabled-toggle" name="enabled_toggle" checked>
                        <label for="edit-clone-enabled-toggle"></label>
                        <span class="toggle-label" id="toggle-status">Attivo</span>
                    </div>
                    <input type="hidden" id="edit-clone-enabled" name="enabled" value="1">
                </div>
            </div>
            
            <!-- Visual Elements Tab -->
            <div class="tab-content" id="visual-elements">
                <div class="form-group logo-section">
                    <label for="edit-clone-logo-url">Logo dell'ente:</label>
                    
                    <div class="logo-preview-container">
                        <div class="logo-placeholder" id="logo-placeholder">
                            <span class="dashicons dashicons-format-image"></span>
                            <span>Seleziona un logo</span>
                        </div>
                        <img id="logo-preview" src="" alt="Preview Logo">
                        <button type="button" id="remove-logo-button" class="button-link remove-logo" title="Rimuovi Logo">
                            <span class="dashicons dashicons-no"></span>
                        </button>
                    </div>
                    
                    <div class="logo-url-controls">
                        <input type="url" id="edit-clone-logo-url" name="logo_url" class="widefat" placeholder="URL del logo">
                        <button type="button" id="select-logo-button" class="button">
                            <span class="dashicons dashicons-upload"></span> Sfoglia Media
                        </button>
                    </div>
                    <p class="description">Carica o seleziona un'immagine dalla libreria media. Dimensioni consigliate: 300x200px.</p>
                </div>
            </div>
            
            <!-- Additional Info Tab -->
            <div class="tab-content" id="additional-info">
                <div class="form-group">
                    <label for="edit-clone-address">Indirizzo:</label>
                    <textarea id="edit-clone-address" name="address" rows="3" class="widefat" placeholder="Inserisci l'indirizzo completo dell'ente"></textarea>
                    <p class="description">Indirizzo fisico dell'ente. Supporta il formato HTML per formattazione aggiuntiva.</p>
                </div>
                
                <div class="form-group">
                    <label for="edit-clone-contacts">Contatti:</label>
                    <textarea id="edit-clone-contacts" name="contacts" rows="3" class="widefat" placeholder="Inserisci i contatti dell'ente (email, telefono, ecc.)"></textarea>
                    <p class="description">Email e telefoni vengono automaticamente trasformati in link cliccabili.</p>
                </div>
                
                <div class="form-group">
                    <label for="edit-clone-other-info">Altre informazioni:</label>
                    <textarea id="edit-clone-other-info" name="other_info" rows="3" class="widefat" placeholder="Inserisci altre informazioni utili (orari apertura, servizi, ecc.)"></textarea>
                    <p class="description">Informazioni aggiuntive sull'ente.</p>
                </div>
            </div>
            
            <div class="form-actions">
                <div class="form-messages" id="edit-form-messages"></div>
                <div class="button-group">
                    <button type="button" class="pnrr-modal-close-button button">Annulla</button>
                    <button type="button" id="save-clone-button" class="button button-primary">
                        <span class="spinner-inline" id="save-spinner"></span>
                        <span class="button-text">Salva modifiche</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
