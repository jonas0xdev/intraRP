/**
 * Custom Dialog System
 * Replaces browser's confirm(), alert(), and prompt() with Bootstrap modals
 */

(function(window) {
    'use strict';

    // Create modal container if it doesn't exist
    function ensureModalContainer() {
        let container = document.getElementById('intra-dialogs-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'intra-dialogs-container';
            document.body.appendChild(container);
        }
        return container;
    }

    // Generate unique ID for modals
    function generateId() {
        return 'intra-dialog-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
    }

    /**
     * Show a confirmation dialog (replaces confirm())
     * @param {string} message - The message to display
     * @param {Object} options - Additional options
     * @returns {Promise<boolean>} - Resolves to true if confirmed, false if cancelled
     */
    window.showConfirm = function(message, options = {}) {
        return new Promise((resolve) => {
            const container = ensureModalContainer();
            const modalId = generateId();
            
            const title = options.title || 'Bestätigung';
            const confirmText = options.confirmText || 'OK';
            const cancelText = options.cancelText || 'Abbrechen';
            const confirmClass = options.confirmClass || 'btn-primary';
            const danger = options.danger || false;
            
            const modalHTML = `
                <div class="modal fade" id="${modalId}" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    ${danger ? '<i class="fa-solid fa-triangle-exclamation text-danger me-2"></i>' : ''}
                                    ${escapeHtml(title)}
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                            </div>
                            <div class="modal-body">
                                <p class="mb-0">${escapeHtml(message)}</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">${escapeHtml(cancelText)}</button>
                                <button type="button" class="btn ${danger ? 'btn-danger' : confirmClass}" id="${modalId}-confirm">${escapeHtml(confirmText)}</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', modalHTML);
            const modalElement = document.getElementById(modalId);
            const modal = new bootstrap.Modal(modalElement);
            
            let resolved = false;
            
            // Handle confirm button
            document.getElementById(`${modalId}-confirm`).addEventListener('click', function() {
                if (!resolved) {
                    resolved = true;
                    resolve(true);
                }
                modal.hide();
            });
            
            // Handle cancel/close
            modalElement.addEventListener('hidden.bs.modal', function() {
                modalElement.remove();
                if (!resolved) {
                    resolved = true;
                    resolve(false);
                }
            }, { once: true });
            
            modal.show();
        });
    };

    /**
     * Show an alert dialog (replaces alert())
     * @param {string} message - The message to display
     * @param {Object} options - Additional options
     * @returns {Promise<void>}
     */
    window.showAlert = function(message, options = {}) {
        return new Promise((resolve) => {
            const container = ensureModalContainer();
            const modalId = generateId();
            
            const title = options.title || 'Hinweis';
            const buttonText = options.buttonText || 'OK';
            const type = options.type || 'info'; // 'info', 'success', 'warning', 'error'
            
            let icon = '';
            if (type === 'success') {
                icon = '<i class="fa-solid fa-circle-check text-success me-2"></i>';
            } else if (type === 'error') {
                icon = '<i class="fa-solid fa-circle-xmark text-danger me-2"></i>';
            } else if (type === 'warning') {
                icon = '<i class="fa-solid fa-triangle-exclamation text-warning me-2"></i>';
            } else {
                icon = '<i class="fa-solid fa-circle-info text-info me-2"></i>';
            }
            
            const modalHTML = `
                <div class="modal fade" id="${modalId}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">${icon}${escapeHtml(title)}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                            </div>
                            <div class="modal-body">
                                <p class="mb-0">${escapeHtml(message)}</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">${escapeHtml(buttonText)}</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', modalHTML);
            const modalElement = document.getElementById(modalId);
            const modal = new bootstrap.Modal(modalElement);
            
            modalElement.addEventListener('hidden.bs.modal', function() {
                modalElement.remove();
                resolve();
            }, { once: true });
            
            modal.show();
        });
    };

    /**
     * Show a prompt dialog (replaces prompt())
     * @param {string} message - The message to display
     * @param {string} defaultValue - Default input value
     * @param {Object} options - Additional options
     * @returns {Promise<string|null>} - Resolves to input value or null if cancelled
     */
    window.showPrompt = function(message, defaultValue = '', options = {}) {
        return new Promise((resolve) => {
            const container = ensureModalContainer();
            const modalId = generateId();
            
            const title = options.title || 'Eingabe';
            const confirmText = options.confirmText || 'OK';
            const cancelText = options.cancelText || 'Abbrechen';
            const inputType = options.inputType || 'text';
            
            const modalHTML = `
                <div class="modal fade" id="${modalId}" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">${escapeHtml(title)}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                            </div>
                            <div class="modal-body">
                                <p>${escapeHtml(message)}</p>
                                <input type="${escapeHtml(inputType)}" class="form-control" id="${modalId}-input" value="${escapeHtml(defaultValue)}">
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">${escapeHtml(cancelText)}</button>
                                <button type="button" class="btn btn-primary" id="${modalId}-confirm">${escapeHtml(confirmText)}</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', modalHTML);
            const modalElement = document.getElementById(modalId);
            const modal = new bootstrap.Modal(modalElement);
            const inputElement = document.getElementById(`${modalId}-input`);
            
            let resolved = false;
            
            // Handle confirm button
            document.getElementById(`${modalId}-confirm`).addEventListener('click', function() {
                if (!resolved) {
                    resolved = true;
                    resolve(inputElement.value);
                }
                modal.hide();
            });
            
            // Handle cancel/close
            modalElement.addEventListener('hidden.bs.modal', function() {
                modalElement.remove();
                if (!resolved) {
                    resolved = true;
                    resolve(null);
                }
            }, { once: true });
            
            // Handle Enter key
            inputElement.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    if (!resolved) {
                        resolved = true;
                        resolve(inputElement.value);
                    }
                    modal.hide();
                }
            });
            
            modal.show();
            
            // Focus input after modal is shown
            modalElement.addEventListener('shown.bs.modal', function() {
                inputElement.focus();
                inputElement.select();
            }, { once: true });
        });
    };

    // Helper function to escape HTML
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // Legacy compatibility - these can be used as drop-in replacements
    // but we'll use the async versions for new code
    window.intraConfirm = window.showConfirm;
    window.intraAlert = window.showAlert;
    window.intraPrompt = window.showPrompt;

})(window);
