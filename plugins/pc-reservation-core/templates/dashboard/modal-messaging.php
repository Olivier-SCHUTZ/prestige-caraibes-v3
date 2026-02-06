<?php
if (! defined('ABSPATH')) {
    exit;
}
?>
<!-- Nouvelle Modale Channel Manager - Phase 3 -->
<div id="pc-messaging-modal" class="pc-messaging-modal" style="display:none;">
    <div class="pc-messaging-modal-backdrop" id="pc-messaging-modal-close"></div>
    <div class="pc-messaging-modal-dialog" role="dialog" aria-modal="true">
        <!-- Header avec Navigation par Onglets -->
        <header class="pc-chat-header">
            <div class="pc-chat-header__client-info">
                <div class="pc-chat-header__avatar">
                    <div class="pc-avatar pc-avatar--client">
                        <span class="pc-avatar__initial" id="pc-chat-client-initial">C</span>
                    </div>
                </div>

                <div class="pc-chat-header__info">
                    <h2 class="pc-chat-header__title" id="pc-chat-client-name">Client</h2>
                    <div class="pc-chat-header__meta">
                        <span class="pc-chat-status-badge" id="pc-chat-reservation-status">Réservation confirmée</span>
                        <span class="pc-chat-reservation-id" id="pc-chat-reservation-ref">#1234</span>
                    </div>
                </div>
            </div>

            <!-- 🆕 Navigation par Onglets -->
            <div class="pc-tabs-nav" id="pc-tabs-nav">
                <button type="button" class="pc-tab-btn pc-tab-btn--active" data-tab="chat" id="pc-tab-chat">
                    <span class="pc-tab-icon">💬</span>
                    <span class="pc-tab-label">WhatsApp / Chat</span>
                </button>
                <button type="button" class="pc-tab-btn" data-tab="email" id="pc-tab-email">
                    <span class="pc-tab-icon">📧</span>
                    <span class="pc-tab-label">Emails Officiels</span>
                </button>
                <button type="button" class="pc-tab-btn" data-tab="notes" id="pc-tab-notes">
                    <span class="pc-tab-icon">📝</span>
                    <span class="pc-tab-label">Notes Internes</span>
                </button>
            </div>

            <div class="pc-chat-header__actions">
                <button type="button" class="pc-chat-close-btn" id="pc-messaging-modal-close-btn" aria-label="Fermer">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
        </header>

        <!-- Body - Zone de scroll des messages -->
        <div class="pc-chat-body">
            <div class="pc-chat-container" id="pc-chat-container">
                <!-- Les messages seront injectés ici par JavaScript -->
                <div class="pc-chat-loading">
                    <div class="pc-chat-loading__spinner"></div>
                    <p>Chargement de la conversation...</p>
                </div>
            </div>
        </div>

        <!-- Footer - Zone de saisie -->
        <footer class="pc-chat-footer">
            <!-- Templates de messages rapides -->
            <div class="pc-chat-templates" id="pc-chat-templates" style="display:none;">
                <div class="pc-chat-templates__header">
                    <span>Messages rapides</span>
                    <button type="button" class="pc-chat-templates__close">×</button>
                </div>
                <div class="pc-chat-templates__list" id="pc-chat-templates-list">
                    <!-- Templates chargés par AJAX -->
                </div>
            </div>

            <!-- Zone de composition -->
            <div class="pc-chat-compose">
                <div class="pc-chat-compose__actions">
                    <button type="button" class="pc-chat-action-btn pc-chat-templates-toggle" id="pc-chat-templates-btn" title="Messages rapides">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m3 21 1.9-5.7a8.5 8.5 0 1 1 3.8 3.8z"></path>
                        </svg>
                    </button>

                    <button type="button" class="pc-chat-action-btn pc-chat-attachments-toggle" id="pc-chat-attachments-btn" title="Joindre un document">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66L9.64 16.2a2 2 0 0 1-2.83-2.83l8.49-8.49"></path>
                        </svg>
                    </button>

                    <!-- Liste déroulante des pièces jointes -->
                    <div class="pc-chat-attachments-popover" id="pc-chat-attachments-popover" style="display:none;">
                        <div class="pc-chat-attachments__header">
                            <span>Documents disponibles</span>
                            <button type="button" class="pc-chat-attachments__close">×</button>
                        </div>

                        <!-- Section Upload Local -->
                        <div class="pc-chat-attachments__upload-section">
                            <button type="button" class="pc-attachment-upload-btn" id="pc-attachment-upload-btn">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                                    <polyline points="7,10 12,15 17,10" />
                                    <line x1="12" y1="15" x2="12" y2="3" />
                                </svg>
                                📂 Importer depuis l'ordinateur
                            </button>
                            <input type="file" id="pc-msg-file-upload" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" style="display:none;">
                        </div>

                        <div class="pc-chat-attachments__divider"></div>

                        <div class="pc-chat-attachments__list" id="pc-chat-attachments-list">
                            <!-- Documents chargés par AJAX -->
                        </div>
                    </div>
                </div>

                <div class="pc-chat-compose__input">
                    <!-- Zone d'affichage des pièces jointes sélectionnées -->
                    <div class="pc-chat-attachments-chips" id="pc-chat-attachments-chips" style="display:none;">
                        <!-- Les chips des fichiers joints apparaîtront ici -->
                    </div>

                    <div class="pc-chat-input-group">
                        <input type="text"
                            class="pc-chat-subject-input"
                            id="pc-chat-subject-input"
                            placeholder="Sujet du message"
                            style="display:none;">

                        <textarea class="pc-chat-message-input"
                            id="pc-chat-message-input"
                            placeholder="Tapez votre message..."
                            rows="1"></textarea>
                    </div>
                </div>

                <div class="pc-chat-compose__send">
                    <button type="button" class="pc-chat-send-btn" id="pc-chat-send-btn" disabled>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="22" y1="2" x2="11" y2="13"></line>
                            <polygon points="22,2 15,22 11,13 2,9"></polygon>
                        </svg>
                        <span class="pc-chat-send-text">Envoyer</span>
                    </button>

                    <button type="button" class="pc-chat-whatsapp-btn" id="pc-chat-whatsapp-btn" title="Ouvrir dans l'application WhatsApp">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893A11.821 11.821 0 0020.465 3.516" />
                        </svg>
                        <span class="pc-whatsapp-text">WhatsApp</span>
                    </button>
                </div>
            </div>

            <!-- Indicateur de frappe (futur) -->
            <div class="pc-chat-typing-indicator" id="pc-chat-typing" style="display:none;">
                <div class="pc-chat-typing__dots">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
                <span class="pc-chat-typing__text">Le client écrit...</span>
            </div>
        </footer>
    </div>
</div>

<!-- Hidden data container -->
<input type="hidden" id="pc-chat-current-reservation-id">
<input type="hidden" id="pc-chat-current-client-name">