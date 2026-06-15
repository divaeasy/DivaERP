(function(window) {
    'use strict';

    function getJQuery() {
        return window.jQuery || window.$;
    }

    var $ = getJQuery();
    if (!$) {
        return;
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

    class InlineLigneEditor {
        constructor(pieceId, tierType) {
            this.pieceId = pieceId;
            this.tierType = tierType || 'Client';
            this.articles = [];
            this.lignesData = [];
            this.$anchorRow = null;
            this.priceLoading = false;
            this.pricePromise = null;
            this.init();
        }

        init() {
            window.__activeInlineLigneEditor = this;
            this.$container = $('#inlineLigneEditorModal');
            this.$tableBody = $('#inlineLignesTableBody');
            this.$total = $('#inlineLigneTotal');
            this.$addBtn = $('.inline-add-ligne-btn');
            this.$closeBtn = $('#closeLigneEditor, #closeLigneEditorBtn');
            this.$meta = $('#inlineLigneEditorMeta');
            this.$articleSelect = $('#inlineEditorArticleSelect');
            this.$qtyInput = $('#inlineEditorQteInput');
            this.$pubInput = $('#inlineEditorPubInput');
            this.$remiseInput = $('#inlineEditorRemiseInput');
            this.$montantDisplay = $('#inlineEditorMontantDisplay');

            // Edit modal elements
            this.$editModal = $('#editLigneModal');
            this.$editForm = $('#editLigneForm');
            this.$editLigneId = $('#editLigneId');
            this.$editLigneIndex = $('#editLigneIndex');
            this.$editArticleSelect = $('#editArticleSelect');
            this.$editQteInput = $('#editQteInput');
            this.$editPubInput = $('#editPubInput');
            this.$editRemiseInput = $('#editRemiseInput');
            this.$editMontantDisplay = $('#editMontantDisplay');
            this.$editQteStDisplay = $('#editQteStDisplay');
            this.$editMouvementDisplay = $('#editMouvementDisplay');
            this.$editLigneSaveBtn = $('#editLigneSaveBtn');

            this.bindEvents();
            this.bindEditModalEvents();
            this.ensureAddButtonEnabled();
            this.loadArticles();
        }

        ensureAddButtonEnabled() {
            if (!this.$addBtn || !this.$addBtn.length) {
                return;
            }
            this.$addBtn
                .prop('disabled', false)
                .removeClass('disabled')
                .attr('aria-disabled', 'false')
                .html('<i class="fas fa-plus mr-1"></i>Ajouter');
        }

        bindEvents() {
            this.$addBtn.off('click.inlineLigne').on('click.inlineLigne', (event) => {
                event.preventDefault();
                event.stopPropagation();
                this.addLigne();
            });
            this.$closeBtn.off('click.inlineLigne').on('click.inlineLigne', () => this.hide());
            this.$articleSelect.off('change.inlineLigne').on('change.inlineLigne', () => {
                this.prefillArticlePrice();
                this.updateInlineMontantDisplay();
            });
            $('#inlineLigneQuickForm').off('submit.inlineLigne').on('submit.inlineLigne', (event) => {
                event.preventDefault();
                this.addLigne();
            });
            this.$qtyInput.add(this.$remiseInput)
                .off('keydown.inlineLigne input.inlineLigne')
                .on('keydown.inlineLigne input.inlineLigne', (event) => {
                    this.updateInlineMontantDisplay();
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        this.addLigne();
                    }
                });
        }

        bindEditModalEvents() {
            this.$editLigneSaveBtn.off('click.inlineLigneEdit').on('click.inlineLigneEdit', () => this.saveEditLigne());
            this.$editArticleSelect.off('change.inlineLigneEdit').on('change.inlineLigneEdit', () => {
                this.prefillEditArticlePrice();
                this.updateEditMontantPreview();
            });
            this.$editQteInput.add(this.$editPubInput).add(this.$editRemiseInput)
                .off('keydown.inlineLigneEdit input.inlineLigneEdit')
                .on('keydown.inlineLigneEdit input.inlineLigneEdit', (event) => {
                    this.updateEditMontantPreview();
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        this.saveEditLigne();
                    }
                });
        }

        updateInlineMontantDisplay() {
            const qte = numberValue(this.$qtyInput.val());
            const pub = numberValue(this.$pubInput.val());
            const remise = numberValue(this.$remiseInput.val());
            const montant = qte * pub * (1 - remise / 100);
            this.$montantDisplay.val(formatMoney(montant));
        }

        prefillEditArticlePrice() {
            const articleId = this.$editArticleSelect.val();
            if (!articleId) {
                return;
            }

            const existingArticle = this.articles.find(a => String(a.id) === String(articleId));
            if (!existingArticle) {
                return;
            }

            try {
                fetch('/lignepiece/price?articleId=' + encodeURIComponent(articleId) + '&pieceId=' + encodeURIComponent(this.pieceId), {
                    headers: {'X-Requested-With': 'XMLHttpRequest'}
                })
                .then(response => response.ok ? response.json() : Promise.reject())
                .then(payload => {
                    if (payload && payload.price !== null && typeof payload.price !== 'undefined') {
                        this.$editPubInput.val(formatMoney(payload.price));
                        this.updateEditMontantPreview();
                    }
                })
                .catch(() => {
                    // Ignore errors
                });
            } catch (error) {
                console.warn('Unable to prefill article price', error);
            }
        }

        updateEditMontantPreview() {
            const qte = numberValue(this.$editQteInput.val());
            const pub = numberValue(this.$editPubInput.val());
            const remise = numberValue(this.$editRemiseInput.val());
            const montant = qte * pub * (1 - remise / 100);
            this.$editMontantDisplay.val(formatMoney(montant));
        }

        openEditModal(index) {
            const ligne = this.lignesData[index];
            if (!ligne) {
                return;
            }

            this.$editLigneId.val(ligne.id);
            this.$editLigneIndex.val(index);
            this.$editArticleSelect.val(ligne.articleId);
            this.$editQteInput.val(formatMoney(ligne.qte || ligne.qty));
            this.$editPubInput.val(formatMoney(ligne.pub));
            this.$editRemiseInput.val(formatMoney(ligne.remise || 0));
            this.$editMontantDisplay.val(formatMoney(ligne.montant));
            this.$editQteStDisplay.val(ligne.qteSt !== null && ligne.qteSt !== undefined ? String(ligne.qteSt) : '-');
            this.$editMouvementDisplay.val(ligne.mouvementDeStock || '-');

            this.populateArticleSelect(this.$editArticleSelect, ligne.articleId);
            this.updateEditMontantPreview();

            this.$editLigneSaveBtn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i>Enregistrer');
            this.$editModal.modal('show');
        }

        async saveEditLigne() {
            const ligneId = this.$editLigneId.val();
            const index = numberValue(this.$editLigneIndex.val());

            if (!ligneId || index < 0 || !this.lignesData[index]) {
                notify('Donnees invalides.', 'error');
                this.$editModal.modal('hide');
                return;
            }

            const articleId = this.$editArticleSelect.val();
            const qte = numberValue(this.$editQteInput.val());
            const pub = numberValue(this.$editPubInput.val());
            const remise = numberValue(this.$editRemiseInput.val());

            if (!articleId) {
                notify('Selectionnez un article.', 'warning');
                this.$editArticleSelect.focus();
                return;
            }
            if (qte <= 0) {
                notify('La quantite doit etre superieure a 0.', 'warning');
                this.$editQteInput.focus();
                return;
            }
            if (pub <= 0) {
                notify('Le prix unitaire doit etre superieur a 0.', 'warning');
                this.$editPubInput.focus();
                return;
            }

            this.$editLigneSaveBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Enregistrement...');

            try {
                const payload = await this.postJson('/lignepiece/api/lignes/' + encodeURIComponent(ligneId), {
                    articleId,
                    qte,
                    pub,
                    remise
                });
                this.lignesData[index] = payload.item;
                this.renderTable();
                this.updatePieceSummary(payload.piece);
                notify('Ligne mise a jour.', 'success');
                this.$editModal.modal('hide');
            } catch (error) {
                notify(error.message || 'Sauvegarde impossible.', 'error');
                this.$editLigneSaveBtn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i>Enregistrer');
            }
        }

        async loadArticles() {
            try {
                const response = await fetch('/lignepiece/api/articles', {
                    headers: {'X-Requested-With': 'XMLHttpRequest'}
                });
                const payload = await readJsonResponse(response);
                if (!response.ok || !payload.success) {
                    throw new Error(payload.message || 'Articles indisponibles.');
                }
                this.articles = payload.items || [];
                this.populateArticleSelect(this.$articleSelect, '');
            } catch (error) {
                notify(error.message || 'Impossible de charger les articles.', 'error');
            }
        }

        populateArticleSelect($select, selectedId) {
            $select.empty().append('<option value="">Selectionner...</option>');
            this.articles.forEach((article) => {
                $select.append(
                    $('<option></option>')
                        .attr('value', article.id)
                        .prop('selected', String(article.id) === String(selectedId || ''))
                        .text(article.libelle)
                );
            });
            this.enableArticleSearch($select);
        }

        enableArticleSearch($select) {
            if (!$select || !$select.length || typeof $select.select2 !== 'function') {
                return;
            }
            if ($select.data('select2')) {
                $select.select2('destroy');
            }
            $select.select2({
                dropdownParent: $select.closest('.modal'),
                width: '100%',
                placeholder: 'Rechercher un article...',
                allowClear: true
            });
        }

        async prefillArticlePrice($select, $pubInput) {
            const articleId = ($select || this.$articleSelect).val();
            const targetInput = $pubInput || this.$pubInput;
            if (!articleId) {
                targetInput.val('0.00');
                this.updateInlineMontantDisplay();
                return;
            }

            const isQuickAddPrice = !$pubInput;
            if (isQuickAddPrice) {
                this.priceLoading = true;
            }
            targetInput.val('').attr('placeholder', 'Chargement...');

            const request = (async () => {
                const response = await fetch('/lignepiece/price?articleId=' + encodeURIComponent(articleId) + '&pieceId=' + encodeURIComponent(this.pieceId), {
                    headers: {'X-Requested-With': 'XMLHttpRequest'}
                });
                if (!response.ok) {
                    targetInput.val('0.00');
                    targetInput.attr('placeholder', '');
                    this.updateInlineMontantDisplay();
                    return;
                }
                const payload = await readJsonResponse(response);
                if (payload && payload.price !== null && typeof payload.price !== 'undefined') {
                    targetInput.val(formatMoney(payload.price));
                } else {
                    targetInput.val('0.00');
                }
                targetInput.attr('placeholder', '');
                this.updateInlineMontantDisplay();
            })();

            if (isQuickAddPrice) {
                this.pricePromise = request;
            }

            try {
                await request;
            } catch (error) {
                console.warn('Unable to prefill article price', error);
                targetInput.val('0.00');
                targetInput.attr('placeholder', '');
                this.updateInlineMontantDisplay();
            } finally {
                if (isQuickAddPrice) {
                    this.priceLoading = false;
                    this.pricePromise = null;
                }
            }
        }

        async loadExistingLignes() {
            this.$tableBody.html('<tr><td colspan="7" class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin mr-1"></i>Chargement...</td></tr>');

            try {
                const response = await fetch('/lignepiece/api/piece/' + encodeURIComponent(this.pieceId) + '/lignes', {
                    headers: {'X-Requested-With': 'XMLHttpRequest'}
                });
                const payload = await readJsonResponse(response);
                if (!response.ok || !payload.success) {
                    throw new Error(payload.message || 'Chargement impossible.');
                }
                this.lignesData = payload.items || [];
                this.renderTable();
                this.updatePieceSummary(payload.piece);
            } catch (error) {
                this.$tableBody.html('<tr><td colspan="7" class="text-center text-danger py-3">' + escapeHtml(error.message || 'Erreur de chargement.') + '</td></tr>');
            }
        }

        async addLigne() {
            const defaultAddLabel = '<i class="fas fa-plus mr-1"></i>Ajouter';

            try {
                if (this.priceLoading) {
                    notify('Le prix est encore en cours de chargement. Patientez une seconde.', 'warning');
                    return;
                }

                const articleId = String(this.$articleSelect.val() || '').trim();
                const qte = numberValue(this.$qtyInput.val());
                const pub = numberValue(this.$pubInput.val());
                const remise = numberValue(this.$remiseInput.val());

                if (!articleId) {
                    notify('Selectionnez un article.', 'warning');
                    return;
                }
                if (qte <= 0) {
                    notify('La quantite doit etre superieure a 0.', 'warning');
                    return;
                }
                if (pub <= 0) {
                    notify('Le prix unitaire doit etre superieur a 0. Verifiez le tarif de l article.', 'warning');
                    return;
                }
                if (pub < 0) {
                    notify('Le prix unitaire doit etre positif.', 'warning');
                    return;
                }
                if (!this.pieceId || Number(this.pieceId) <= 0) {
                    notify('Piece introuvable. Rechargez la page.', 'error');
                    return;
                }

                this.$addBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Ajout...');

                const payload = await this.postJson('/lignepiece/api/piece/' + encodeURIComponent(this.pieceId) + '/lignes', {
                    articleId: articleId,
                    qte: qte,
                    pub: pub,
                    remise: remise
                });
                this.lignesData.push(payload.item);
                this.renderTable();
                this.updatePieceSummary(payload.piece);
                this.resetQuickForm();
                notify('Ligne ajoutee.', 'success');
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
                this.$tableBody.html('<tr><td colspan="7" class="text-center text-muted py-3"><i class="fas fa-inbox mb-1"></i><br>Aucune ligne</td></tr>');
                this.$total.text('0.00');
                return;
            }

            this.lignesData.forEach((ligne, index) => {
                total += numberValue(ligne.montant);
                const articleDisplay = ligne.articleDisplay || ligne.article || '...';
                const $row = $(
                    '<tr class="inline-ligne-row" data-ligne-id="' + escapeHtml(ligne.id) + '">' +
                        '<td>' + escapeHtml(articleDisplay) + '</td>' +
                        '<td class="text-right">' + escapeHtml(formatMoney(ligne.qte || ligne.qty)) + '</td>' +
                        '<td class="text-right">' + escapeHtml(formatMoney(ligne.pub)) + '</td>' +
                        '<td class="text-right">' + escapeHtml(formatMoney(ligne.remise || 0)) + '</td>' +
                        '<td class="text-right"><strong>' + escapeHtml(formatMoney(ligne.montant)) + '</strong></td>' +
                        '<td class="text-right">' + (ligne.qteSt == null ? '-' : escapeHtml(String(ligne.qteSt))) + '</td>' +
                        '<td class="text-center"><div class="inline-ligne-actions">' +
                            '<button type="button" class="btn btn-sm btn-outline-primary edit-inline-ligne" title="Modifier"><i class="fas fa-edit"></i></button>' +
                            '<button type="button" class="btn btn-sm btn-outline-danger delete-inline-ligne" title="Supprimer"><i class="fas fa-trash-alt"></i></button>' +
                        '</div></td>' +
                    '</tr>'
                );

                this.bindRowEvents($row, index);
                this.$tableBody.append($row);
            });

            this.$total.text(formatMoney(total));
        }

        bindRowEvents($row, index) {
            $row.find('.edit-inline-ligne').on('click', () => this.openEditModal(index));
            $row.find('.delete-inline-ligne').on('click', () => this.deleteLigne(index));
        }

        async deleteLigne(index) {
            const ligne = this.lignesData[index];
            if (!ligne || !ligne.id) {
                return;
            }
            if (!window.confirm('Supprimer cette ligne ?')) {
                return;
            }

            try {
                const payload = await this.postJson('/lignepiece/api/lignes/' + encodeURIComponent(ligne.id) + '/delete', {});
                this.lignesData.splice(index, 1);
                this.renderTable();
                this.updatePieceSummary(payload.piece);
                notify('Ligne supprimee.', 'success');
            } catch (error) {
                notify(error.message || 'Suppression impossible.', 'error');
            }
        }

        async postJson(url, data) {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(data || {})
            });
            const payload = await readJsonResponse(response);
            if (!response.ok || !payload.success) {
                throw new Error(payload.message || 'Operation impossible.');
            }
            return payload;
        }

        resetQuickForm() {
            this.$articleSelect.val('');
            if (this.$articleSelect.data('select2')) {
                this.$articleSelect.trigger('change.select2');
            }
            this.$qtyInput.val('1');
            this.$pubInput.val('0.00');
            this.$remiseInput.val('0');
            this.$montantDisplay.val('0.00');
        }

        updatePieceSummary(summary) {
            if (!summary) {
                return;
            }
            if (typeof window.updatePieceLigneSummary === 'function') {
                window.updatePieceLigneSummary(summary);
            }
        }

        show($anchorRow) {
            this.$anchorRow = $anchorRow && $anchorRow.length ? $anchorRow : this.$anchorRow;
            if (this.$anchorRow && this.$anchorRow.length) {
                this.$meta.text('Piece #' + (this.pieceId || ''));
            }
            this.ensureAddButtonEnabled();
            this.$container.modal('show');
            this.loadExistingLignes();
        }

        hide() {
            this.$container.modal('hide');
        }

        static getInstance(pieceId, tierType) {
            if (!window.inlineLigneEditors) {
                window.inlineLigneEditors = {};
            }
            var key = String(pieceId);
            var existing = window.inlineLigneEditors[key];
            if (existing && (!existing.$container || !existing.$container.length)) {
                delete window.inlineLigneEditors[key];
                existing = null;
            }
            if (!existing) {
                window.inlineLigneEditors[key] = new InlineLigneEditor(pieceId, tierType);
            }
            return window.inlineLigneEditors[key];
        }
    }

    $(document)
        .off('click.inlineLigneGlobal', '.inline-add-ligne-btn')
        .on('click.inlineLigneGlobal', '.inline-add-ligne-btn', function(event) {
            event.preventDefault();
            event.stopPropagation();
            var editor = window.__activeInlineLigneEditor;
            if (editor && typeof editor.addLigne === 'function') {
                editor.addLigne();
                return;
            }
            notify('Le gestionnaire de lignes n est pas pret. Rechargez la page.', 'error');
        });

    window.InlineLigneEditor = InlineLigneEditor;
})(window);
