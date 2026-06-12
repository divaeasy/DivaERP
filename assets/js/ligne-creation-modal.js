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
            console.log('[LigneCreationModal] entering constructor');
            this.piece = piece || {};
            this.options = options || {};


            // Supporte différents formats de payload selon les appels back
            // (ex: {id}, {pieceId}, etc.)
            var rawId =
                this.piece.id ??
                this.piece.pieceId ??
                this.piece.entetepieceId ??
                this.piece.uuid ??
                this.options.pieceId ??
                this.options.id;

            this.pieceId = parseInt(rawId, 10) || 0;

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

            console.log('[LigneCreationModal] entering init');

            this.$modal = $('#ligneCreationModal');
            console.log('[LigneCreationModal] #ligneCreationModal length', this.$modal && this.$modal.length ? this.$modal.length : 0);

            console.log('[LigneCreationModal] this.$modal', this.$modal);
            console.log('[LigneCreationModal] entering init (after cache)');
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
                console.log('[LigneCreationModal] leaving init: missing DOM (modal/addBtn)', {
                    modalLen: this.$modal && this.$modal.length ? this.$modal.length : 0,
                    addBtnLen: this.$addBtn && this.$addBtn.length ? this.$addBtn.length : 0
                });
                console.error('[LigneCreationModal] Ligne creation modal markup is missing from the page.');
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
                this.enableArticleSearch();
            } catch (error) {
                notify(error.message || 'Impossible de charger les articles.', 'error');
            }
        }

        enableArticleSearch() {
            var $ = getJQuery();
            if (!$ || !this.$articleSelect.length || typeof this.$articleSelect.select2 !== 'function') {
                return;
            }

            if (this.$articleSelect.data('select2')) {
                this.$articleSelect.select2('destroy');
            }

            this.$articleSelect.select2({
                dropdownParent: this.$modal,
                width: '100%',
                placeholder: 'Rechercher un article...',
                allowClear: true
            });
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
            if (this.$articleSelect.data('select2')) {
                this.$articleSelect.trigger('change.select2');
            }
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
                }

                // Aller directement sur la page édition de la piece créée
                // pour afficher immédiatement "Gestion des lignes".
                var targetId = String(this.pieceId || 0);
                var currentUrl = new URL(window.location.href);
                currentUrl.pathname = '/piece/edit/' + encodeURIComponent(targetId);
                currentUrl.searchParams.set('scroll', 'piece-lines');
                currentUrl.searchParams.set('showLigneModal', '1');
                currentUrl.hash = '#piece-lines';

                window.location.href = currentUrl.toString();
            } catch (error) {
                notify(error.message || 'Erreur lors de l enregistrement.', 'error');
                this.$finishBtn.prop('disabled', false).html('<i class="fas fa-check mr-2"></i>Terminer et editer');
            } finally {
                this.$finishBtn.prop('disabled', this.lignesData.length === 0).html('<i class="fas fa-check mr-2"></i>Terminer et editer');
            }
        }

        skipToInlineEdit() {
            this.$modal.modal('hide');

            var targetId = String(this.pieceId || 0);
            if (!targetId || targetId === '0') {
                notify('Piece introuvable (ID manquant). Rechargez la page.', 'error');
                return;
            }

            // Conserve les query params existants (ex: origin=client/fournisseur/interne)
            var currentUrl = new URL(window.location.href);
            currentUrl.pathname = '/piece/edit/' + encodeURIComponent(targetId);
            currentUrl.searchParams.set('scroll', 'piece-lines');
            currentUrl.searchParams.set('showLigneModal', '1');
            currentUrl.hash = '#piece-lines';

            if (typeof this.options.onSkip === 'function') {
                try {
                    this.options.onSkip();
                } catch (e) {
                    // ignore
                }
            }

            window.location.href = currentUrl.toString();
        }

        show() {
            console.log('[LigneCreationModal] entering show');
            var $q = getJQuery();
            console.log('[LigneCreationModal] #ligneCreationModal length at show', ($q ? $q('#ligneCreationModal').length : 0));

            this.ensureAddButtonEnabled();

            if (!this.$modal || !this.$modal.length) {
                console.error('[LigneCreationModal] cannot show: #ligneCreationModal missing at show');
                return;
            }

            var $ = getJQuery();
            if (!$ || !$.fn) {
                console.warn('[LigneCreationModal] jQuery/.fn missing at show');
            }

            console.log('[LigneCreationModal] before bootstrap modal');

            var $q = getJQuery();
            console.log('[LigneCreationModal] $.fn.modal exists?', !!($q && $q.fn && $q.fn.modal));

            console.log('[LigneCreationModal] hasClass show BEFORE', this.$modal.hasClass('show'));
            console.log('[LigneCreationModal] style BEFORE', this.$modal.attr('style'));
            this.$modal.on('shown.bs.modal', function () {
                console.log('[LigneCreationModal] shown.bs.modal fired');
                var $q2 = getJQuery();
                var hasShow = $q2 ? $q2(this).hasClass('show') : false;
                console.log('[LigneCreationModal] hasClass show AFTER (shown)', hasShow);
            });

            this.$modal.on('hidden.bs.modal', function () {
                console.log('[LigneCreationModal] hidden.bs.modal fired');
            });
            this.$modal.on('hide.bs.modal', function () {
                console.log('[LigneCreationModal] hide.bs.modal fired');
            });
            this.$modal.on('show.bs.modal', function () {
                console.log('[LigneCreationModal] show.bs.modal fired');
            });

            var $global = getJQuery();
            var backdropBefore = ($global && $global('.modal-backdrop') && $global('.modal-backdrop').length) ? $global('.modal-backdrop').length : 0;
            console.log('[LigneCreationModal] modal-backdrop count BEFORE', backdropBefore);
            try {
                this.$modal.modal('show');
            } catch (e) {
                console.error('[LigneCreationModal] bootstrap modal show threw', e);
            }
            console.log('[LigneCreationModal] after bootstrap modal');
            console.log('[LigneCreationModal] hasClass show AFTER (post show)', this.$modal.hasClass('show'));
            console.log('[LigneCreationModal] style AFTER (post show)', this.$modal.attr('style'));
            var backdropAfter = ($global && $global('.modal-backdrop') && $global('.modal-backdrop').length) ? $global('.modal-backdrop').length : 0;
            console.log('[LigneCreationModal] modal-backdrop count AFTER', backdropAfter);


        }


        static showAfterPieceCreation(piece, options) {
            console.log('[LigneCreationModal] showAfterPieceCreation called', { piece: piece, options: options });
            var $ = getJQuery();
            console.log('[LigneCreationModal] jQuery present?', !!$);
            if (!$) {
                notify('jQuery est requis pour ouvrir le modal de lignes.', 'error');
                return null;
            }
            bindGlobalHandlers($);
            console.log('[LigneCreationModal] before new LigneCreationModal');
            var modal = new LigneCreationModal(piece, options);
            console.log('[LigneCreationModal] after new LigneCreationModal', { modal: modal });
            if (modal && typeof modal.show === 'function') {
                console.log('[LigneCreationModal] calling modal.show()');
                modal.show();
            } else {
                console.warn('[LigneCreationModal] modal.show not found');
            }
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
