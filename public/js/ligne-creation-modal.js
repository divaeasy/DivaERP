(function(window) {
    'use strict';

    function getJQuery() {
        return window.jQuery || window.$;
    }

    function notify(message, type) {
        var level = type || 'info';
        if (window.showAppToast && typeof window.showAppToast === 'function') {
            window.showAppToast({ message: message, type: level });
            return;
        }
        if (window.toastr && typeof window.toastr[level] === 'function') {
            window.toastr[level](message);
            return;
        }
        if (window.showToast && typeof window.showToast === 'function') {
            window.showToast(message, level);
            return;
        }
        if (level === 'error' || level === 'warning') {
            window.alert(message);
            return;
        }
        console.log(message);
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function numberValue(value) {
        var parsed = parseFloat(String(value || '0').replace(',', '.'));
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function formatMoney(value) {
        return numberValue(value).toFixed(2);
    }

    async function readJsonResponse(response) {
        const text = await response.text();
        if (!text) {
            return {};
        }

        try {
            return JSON.parse(text);
        } catch (error) {
            if (text.trim().charAt(0) === '<') {
                throw new Error('Le serveur a retourne une page d erreur au lieu du JSON. Verifiez les logs Symfony.');
            }
            throw error;
        }
    }

    function bindGlobalHandlers($) {
        if (window.__ligneCreationModalHandlersBound) {
            return;
        }
        window.__ligneCreationModalHandlersBound = true;

        $(document)
            .off('click.ligneCreationGlobal', '#addLineQuickBtn')
            .on('click.ligneCreationGlobal', '#addLineQuickBtn', function(event) {
                event.preventDefault();
                event.stopPropagation();
                var instance = window.__activeLigneCreationModal;
                if (instance && typeof instance.addLineToDraft === 'function') {
                    instance.addLineToDraft();
                    return;
                }
                notify('Le formulaire de ligne n est pas pret. Rechargez la page.', 'error');
            });

        $(document)
            .off('submit.ligneCreationGlobal', '#quickLineForm')
            .on('submit.ligneCreationGlobal', '#quickLineForm', function(event) {
                event.preventDefault();
                var instance = window.__activeLigneCreationModal;
                if (instance && typeof instance.addLineToDraft === 'function') {
                    instance.addLineToDraft();
                }
            });
    }

    class LigneCreationModal {
        constructor(piece, options) {
            this.piece = piece || {};
            this.pieceId = parseInt(this.piece.id, 10) || 0;
            this.options = options || {};
            this.lignesData = [];
            this.articlesLoaded = false;
            this.priceLoading = false;
            this.pricePromise = null;
            this.priceRequestId = 0;
            this.init();
        }

        init() {
            var $ = getJQuery();
            if (!$) {
                return;
            }

            window.__activeLigneCreationModal = this;

            this.$modal = $('#ligneCreationModal');
            this.$form = $('#quickLineForm');
            this.$tableBody = $('#lignesTableBody');
            this.$addBtn = $('#addLineQuickBtn');
            this.$finishBtn = $('#ligneModalFinish');
            this.$continueBtn = $('#ligneModalContinue');
            this.$closeBtn = $('#ligneModalClose');
            this.$ligneCount = $('#ligneCount');
            this.$totalMontant = $('#totalMontant');
            this.$articleSelect = $('#quickArticleSelect');
            this.$qtyInput = $('#quickQtyInput');
            this.$pubInput = $('#quickPubInput');
            this.$remiseInput = $('#quickRemiseInput');
            this.$montantDisplay = $('#quickMontantDisplay');

            if (!this.$modal.length || !this.$addBtn.length) {
                console.error('Ligne creation modal markup is missing from the page.');
                return;
            }

            this.resetState();
            this.bindEvents();
            this.ensureAddButtonEnabled();
            this.loadArticles();
        }

        bindEvents() {
            var $ = getJQuery();
            if (!$) {
                return;
            }

            this.$finishBtn.off('click.ligneCreation').on('click.ligneCreation', () => this.finishAndSave());
            this.$continueBtn.off('click.ligneCreation').on('click.ligneCreation', () => this.skipToInlineEdit());
            this.$closeBtn.off('click.ligneCreation').on('click.ligneCreation', () => this.skipToInlineEdit());
            this.$articleSelect.off('change.ligneCreation').on('change.ligneCreation', () => {
                this.prefillArticlePrice();
                this.updateMontantDisplay();
            });
            this.$qtyInput.add(this.$remiseInput)
                .off('keydown.ligneCreation input.ligneCreation')
                .on('keydown.ligneCreation input.ligneCreation', (event) => {
                    this.updateMontantDisplay();
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        this.addLineToDraft();
                    }
                });
        }

        ensureAddButtonEnabled() {
            if (!this.$addBtn || !this.$addBtn.length) {
                return;
            }
            this.$addBtn
                .prop('disabled', false)
                .removeClass('disabled')
                .attr('aria-disabled', 'false')
                .html('<i class="fas fa-plus mr-1"></i> Ajouter');
        }

        resetState() {
            this.lignesData = [];
            this.$tableBody.empty();
            this.renderTable();
            this.resetForm();
        }

        async loadArticles() {
            this.$articleSelect.find('option:not(:first)').remove();

            try {
                const response = await fetch('/lignepiece/api/articles', {
                    headers: {'X-Requested-With': 'XMLHttpRequest'}
                });
                const payload = await readJsonResponse(response);
                if (!response.ok || !payload.success) {
                    throw new Error(payload.message || 'Articles indisponibles.');
                }

                (payload.items || []).forEach((article) => {
                    this.$articleSelect.append(
                        $('<option></option>').attr('value', article.id).text(article.libelle)
                    );
                });
                this.articlesLoaded = true;
            } catch (error) {
                notify(error.message || 'Impossible de charger les articles.', 'error');
            }
        }

        async prefillArticlePrice() {
            const articleId = this.$articleSelect.val();
            if (!articleId) {
                this.$pubInput.val('0.00');
                this.updateMontantDisplay();
                return;
            }

            const requestId = ++this.priceRequestId;
            this.priceLoading = true;
            this.$pubInput.val('').attr('placeholder', 'Chargement...');

            try {
                const response = await fetch('/lignepiece/price?articleId=' + encodeURIComponent(articleId) + '&pieceId=' + encodeURIComponent(this.pieceId), {
                    headers: {'X-Requested-With': 'XMLHttpRequest'}
                });
                if (requestId !== this.priceRequestId) {
                    return;
                }
                if (!response.ok) {
                    this.$pubInput.val('0.00');
                    return;
                }
                const payload = await readJsonResponse(response);
                if (requestId !== this.priceRequestId) {
                    return;
                }
                if (payload && payload.price !== null && typeof payload.price !== 'undefined') {
                    this.$pubInput.val(formatMoney(payload.price));
                } else {
                    this.$pubInput.val('0.00');
                }
            } catch (error) {
                if (requestId === this.priceRequestId) {
                    console.warn('Unable to prefill article price', error);
                    this.$pubInput.val('0.00');
                }
            } finally {
                if (requestId === this.priceRequestId) {
                    this.priceLoading = false;
                    this.pricePromise = null;
                    this.$pubInput.attr('placeholder', '');
                    this.updateMontantDisplay();
                }
            }
        }

        updateMontantDisplay() {
            const qte = numberValue(this.$qtyInput.val());
            const pub = numberValue(this.$pubInput.val());
            const remise = numberValue(this.$remiseInput.val());
            const montant = qte * pub * (1 - remise / 100);
            this.$montantDisplay.val(formatMoney(montant));
        }

        async addLineToDraft() {
            const defaultAddLabel = '<i class="fas fa-plus mr-1"></i> Ajouter';

            try {
                if (this.priceLoading) {
                    notify('Le prix est encore en cours de chargement. Patientez une seconde.', 'warning');
                    return;
                }

                const articleId = String(this.$articleSelect.val() || '').trim();
                const articleText = this.$articleSelect.find('option:selected').text();
                const qte = numberValue(this.$qtyInput.val());
                const pub = numberValue(this.$pubInput.val());
                const remise = numberValue(this.$remiseInput.val());

                if (!articleId) {
                    notify('Selectionnez un article.', 'warning');
                    this.$articleSelect.trigger('focus');
                    return;
                }
                if (qte <= 0) {
                    notify('La quantite doit etre superieure a 0.', 'warning');
                    this.$qtyInput.trigger('focus');
                    return;
                }
                if (pub < 0) {
                    notify('Le prix unitaire doit etre positif.', 'warning');
                    return;
                }
                if (remise < 0 || remise > 100) {
                    notify('La remise doit etre comprise entre 0 et 100.', 'warning');
                    this.$remiseInput.trigger('focus');
                    return;
                }

                this.lignesData.push({
                    articleId: articleId,
                    articleText: articleText,
                    qte: qte,
                    pub: pub,
                    remise: remise,
                    montant: qte * pub * (1 - remise / 100)
                });
                this.renderTable();
                this.resetForm();
                notify('Ligne ajoutee a la liste.', 'success');
            } catch (error) {
                notify(error.message || 'Ajout impossible.', 'error');
            } finally {
                this.ensureAddButtonEnabled();
                this.$addBtn.html(defaultAddLabel);
            }
        }

        renderTable() {
            this.$tableBody.empty();
            let total = 0;

            if (!this.lignesData.length) {
                this.$tableBody.html(
                    '<tr id="emptyLigneRow"><td colspan="6" class="text-center text-muted py-4">' +
                    '<i class="fas fa-inbox mb-1"></i><br>Aucune ligne ajoutee pour le moment</td></tr>'
                );
            } else {
                this.lignesData.forEach((ligne, index) => {
                    total += ligne.montant;
                    const $row = $(
                        '<tr>' +
                            '<td>' + escapeHtml(ligne.articleText) + '</td>' +
                            '<td class="text-right">' + formatMoney(ligne.qte) + '</td>' +
                            '<td class="text-right">' + formatMoney(ligne.pub) + '</td>' +
                            '<td class="text-right">' + formatMoney(ligne.remise) + '%</td>' +
                            '<td class="text-right"><strong>' + formatMoney(ligne.montant) + '</strong></td>' +
                            '<td class="text-center"><button type="button" class="ligne-delete-btn" title="Supprimer"><i class="fas fa-trash-alt"></i></button></td>' +
                        '</tr>'
                    );
                    $row.find('.ligne-delete-btn').on('click', () => {
                        this.lignesData.splice(index, 1);
                        this.renderTable();
                    });
                    this.$tableBody.append($row);
                });
            }

            this.$ligneCount.text(this.lignesData.length);
            this.$totalMontant.text(formatMoney(total));
            this.$finishBtn.prop('disabled', this.lignesData.length === 0);
        }

        resetForm() {
            this.$articleSelect.val('');
            this.$qtyInput.val('1');
            this.$pubInput.val('0.00').attr('placeholder', '');
            this.$remiseInput.val('0');
            this.updateMontantDisplay();
            this.$articleSelect.trigger('focus');
        }

        async finishAndSave() {
            if (!this.lignesData.length) {
                notify('Ajoutez au moins une ligne.', 'warning');
                return;
            }
            if (this.pieceId <= 0) {
                notify('Piece introuvable. Rechargez la page.', 'error');
                return;
            }

            this.$finishBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Enregistrement...');

            try {
                let lastPieceSummary = null;
                for (const ligne of this.lignesData) {
                    const response = await fetch('/lignepiece/api/piece/' + encodeURIComponent(this.pieceId) + '/lignes', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify(ligne)
                    });
                    const payload = await readJsonResponse(response);
                    if (!response.ok || !payload.success) {
                        throw new Error(payload.message || 'Enregistrement impossible.');
                    }
                    lastPieceSummary = payload.piece || lastPieceSummary;
                }

                if (lastPieceSummary && typeof this.options.onPieceSummary === 'function') {
                    this.options.onPieceSummary(lastPieceSummary);
                } else if (lastPieceSummary && typeof window.updatePieceLigneSummary === 'function') {
                    window.updatePieceLigneSummary(lastPieceSummary);
                }

                notify(this.lignesData.length + ' ligne(s) enregistree(s).', 'success');
                this.$modal.modal('hide');
                if (typeof this.options.onFinish === 'function') {
                    this.options.onFinish(lastPieceSummary);
                } else {
                    window.location.href = window.location.pathname + '?scroll=piece-lines#piece-lines';
                }
            } catch (error) {
                notify(error.message || 'Erreur lors de l enregistrement.', 'error');
                this.$finishBtn.prop('disabled', false).html('<i class="fas fa-check mr-2"></i>Terminer et editer');
            } finally {
                this.$finishBtn.prop('disabled', this.lignesData.length === 0).html('<i class="fas fa-check mr-2"></i>Terminer et editer');
            }
        }

        skipToInlineEdit() {
            this.$modal.modal('hide');
            if (typeof this.options.onSkip === 'function') {
                this.options.onSkip();
            }
        }

        show() {
            this.ensureAddButtonEnabled();
            this.$modal.modal('show');
        }

        static showAfterPieceCreation(piece, options) {
            var $ = getJQuery();
            if (!$) {
                notify('jQuery est requis pour ouvrir le modal de lignes.', 'error');
                return null;
            }
            bindGlobalHandlers($);
            var modal = new LigneCreationModal(piece, options);
            modal.show();
            return modal;
        }
    }

    function registerLigneCreationModal() {
        var $ = getJQuery();
        if (!$) {
            return false;
        }
        bindGlobalHandlers($);
        window.LigneCreationModal = LigneCreationModal;
        return true;
    }

    if (!registerLigneCreationModal()) {
        document.addEventListener('DOMContentLoaded', registerLigneCreationModal);
        document.addEventListener('turbo:load', registerLigneCreationModal);
        document.addEventListener('turbo:render', registerLigneCreationModal);
    }
})(window);
