(function($) {
    'use strict';

    if (!$) {
        return;
    }

    var crudBulkDeleteState = null;
    var crudGlobalHandlersBound = false;
    var crudUnsavedConfirmAction = null;
    var crudDetailState = {
        $table: null,
        $row: null,
        config: null,
        editableFields: [],
        mode: 'view',
        charts: {
            completion: null,
            profile: null
        }
    };

    function bootCrudListEnhancements() {
        var $tables = $('table.js-crud-list-table[data-crud-resource]');
        if ($tables.length === 0) {
            return;
        }

        ensureCrudEnhancerModals();
        ensureUnsavedChangesModal();

        if (!crudGlobalHandlersBound) {
            bindCrudBulkDeleteConfirmHandler();
            bindUnsavedChangesConfirmHandler();
            bindCrudDetailModalHandlers();
            crudGlobalHandlersBound = true;
        }

        $tables.each(function() {
            var $table = $(this);
            if ($table.attr('data-crud-enhanced') === '1') {
                return;
            }

            enhanceCrudTable($table);
            $table.attr('data-crud-enhanced', '1');
        });
    }

    $(bootCrudListEnhancements);
    document.addEventListener('turbo:load', bootCrudListEnhancements);
    document.addEventListener('turbo:render', bootCrudListEnhancements);

    function enhanceCrudTable($table) {
            var resource = String($table.data('crudResource') || '').trim();
            if (resource === '') {
                return;
            }

            var config = {
                resource: resource,
                bulkDeleteToken: String($table.data('bulkDeleteToken') || '').trim(),
                updateToken: String($table.data('updateToken') || '').trim(),
                createToken: String($table.data('createToken') || '').trim(),
                importToken: String($table.data('importToken') || '').trim(),
                enableQuickCreate: String($table.data('enableQuickCreate') || '') === '1',
                enableImport: String($table.data('enableImport') || '') === '1',
                enableInlineEdit: isInlineEditEnabled($table),
                placeholder: String($table.data('placeholder') || '____'),
                rowSelector: 'tr[data-row-id]'
            };

            // Prevent loader lock only where edit is intercepted inline.
            if (config.enableInlineEdit && config.updateToken !== '') {
                $table.find('.btn-edit').addClass('no-loading');
            } else {
                $table.find('.btn-edit').removeClass('no-loading');
            }

            if (config.bulkDeleteToken !== '') {
                setupRowSelection($table, config);
                setupBulkDeletion($table, config);
            }

            if (config.enableInlineEdit && config.updateToken !== '') {
                setupInlineEditing($table, config);
            }

            if (hasViewDetailAction($table)) {
                setupDetailModal($table, config);
            }

            if (config.enableQuickCreate && config.createToken !== '') {
                setupQuickCreateTrigger($table, config);
            }

            if (config.enableImport && config.importToken !== '') {
                setupImportTrigger($table, config);
            }
        }

        function hasViewDetailAction($table) {
            if (!$table || !$table.length) {
                return false;
            }
            return $table.find('tbody a.btn-view').length > 0;
        }

        function setupRowSelection($table, config) {
            var $theadRow = $table.find('thead tr').first();
            if ($theadRow.length === 0) {
                return;
            }

            var tableId = resolveTableId($table, config.resource);
            var rowCheckboxSelector = '.js-row-selector, [data-row-checkbox]';
            var selectAllSelector = '.js-select-all-rows, [data-select-all-checkbox]';

            if ($theadRow.find(selectAllSelector).length === 0 && $theadRow.find('th.js-row-selector-head, th.row-selector-head').length === 0) {
                $theadRow.prepend(
                    '<th class="row-selector-head js-row-selector-head text-center" data-column-key="selector" style="width:42px;">' +
                        '<div class="form-check d-flex justify-content-center mb-0">' +
                            '<input type="checkbox" class="form-check-input position-static js-select-all-rows" data-select-all-checkbox aria-label="Tout selectionner">' +
                        '</div>' +
                    '</th>'
                );
            }

            $table.find('tbody tr[data-row-id]').each(function() {
                var $row = $(this);
                if ($row.find(rowCheckboxSelector).length > 0 || $row.find('td.js-row-selector-cell, td.row-selector-cell').length > 0) {
                    if (!$row.attr('data-item-id')) {
                        $row.attr('data-item-id', String($row.attr('data-row-id') || ''));
                    }
                    return;
                }

                var rowId = String($row.attr('data-row-id') || '');
                $row.prepend(
                    '<td class="row-selector-cell js-row-selector-cell text-center" data-column-key="selector">' +
                        '<div class="form-check d-flex justify-content-center mb-0">' +
                            '<input type="checkbox" class="form-check-input position-static js-row-selector" data-row-checkbox value="' + escapeHtml(rowId) + '" aria-label="Selectionner la ligne">' +
                        '</div>' +
                    '</td>'
                );

                if (!$row.attr('data-item-id')) {
                    $row.attr('data-item-id', rowId);
                }
            });

            var $bulkBar = buildOrGetBulkBar($table, tableId);
            bindBulkClearHandler($table, $bulkBar, rowCheckboxSelector, selectAllSelector);
            refreshBulkState($table, $bulkBar);

            $table.on('change', selectAllSelector, function() {
                var isChecked = $(this).is(':checked');
                $table.find('tbody tr[data-row-id]').each(function() {
                    var $row = $(this);
                    if ($row.hasClass('js-inline-editing')) {
                        return;
                    }

                    var $checkbox = $row.find(rowCheckboxSelector).first();
                    if ($checkbox.length === 0 || $checkbox.prop('disabled')) {
                        return;
                    }

                    $checkbox.prop('checked', isChecked).trigger('change');
                });
            });

            $table.on('change', rowCheckboxSelector, function() {
                var $row = $(this).closest('tr');
                if ($row.hasClass('js-inline-editing')) {
                    $(this).prop('checked', false);
                    showToast('Annulez la modification en cours avant de selectionner cette ligne.', 'error');
                    return;
                }

                var isChecked = $(this).is(':checked');
                $row.toggleClass('table-active', isChecked);
                $row.toggleClass('is-selected', isChecked);

                var totalRows = $table.find('tbody ' + rowCheckboxSelector).length;
                var checkedRows = $table.find('tbody ' + rowCheckboxSelector + ':checked').length;
                var $selectAll = $table.find(selectAllSelector);

                $selectAll.prop('checked', totalRows > 0 && totalRows === checkedRows);
                refreshBulkState($table, $bulkBar);
            });

            $table.on('click', 'tbody tr[data-row-id]', function(event) {
                if ($(event.target).closest('a, button, input, select, textarea, label, [contenteditable="true"], .inline-dropdown-shell, .inline-dropdown-menu, .inline-dropdown-option, .inline-dropdown-trigger').length > 0) {
                    return;
                }

                if ($(this).hasClass('js-inline-editing')) {
                    return;
                }

                var $checkbox = $(this).find(rowCheckboxSelector).first();
                if ($checkbox.length === 0 || $checkbox.prop('disabled')) {
                    return;
                }

                $checkbox.prop('checked', !$checkbox.is(':checked')).trigger('change');
            });
        }

        function bindBulkClearHandler($table, $bulkBar, rowCheckboxSelector, selectAllSelector) {
            if (!$bulkBar || $bulkBar.length === 0) {
                return;
            }

            $bulkBar.off('click.crudBulkClear', '[data-bulk-clear], .js-bulk-clear-btn');
            $bulkBar.on('click.crudBulkClear', '[data-bulk-clear], .js-bulk-clear-btn', function(event) {
                event.preventDefault();
                $table.find(selectAllSelector).prop('checked', false).prop('indeterminate', false);
                $table.find('tbody ' + rowCheckboxSelector).prop('checked', false).trigger('change');
            });
        }

        function buildOrGetBulkBar($table, tableId) {
            var normalizedTableId = String(tableId || '').trim();

            if (normalizedTableId !== '') {
                var $linked = $('[data-bulk-bar-for="' + normalizedTableId + '"]').first();
                if ($linked.length > 0) {
                    return $linked;
                }
            }

            var $existing = $table.prev('.js-crud-bulk-bar').first();
            if ($existing.length > 0) {
                return $existing;
            }

            var tableTitle = String($('.page-header h1').first().text() || 'elements').trim().toLowerCase();
            var $bar = $(
                '<div class="table-bulk-actions js-crud-bulk-bar" data-bulk-bar-for="' + escapeHtml(normalizedTableId) + '">' +
                    '<div class="table-bulk-actions__content">' +
                        '<div class="table-bulk-actions__left">' +
                            '<span class="table-bulk-actions__badge">' +
                                '<i class="fas fa-check-circle"></i>' +
                                '<strong data-selection-count>0</strong>' +
                            '</span>' +
                            '<span class="table-bulk-actions__label" data-selection-label>0 ligne selectionnee</span>' +
                        '</div>' +
                        '<div class="table-bulk-actions__buttons">' +
                            '<button type="button" class="btn btn-sm table-bulk-actions__delete-btn no-loading js-bulk-delete-btn" data-bulk-action="delete">' +
                                '<i class="fas fa-trash-alt mr-1"></i> Supprimer' +
                            '</button>' +
                            '<button type="button" class="btn btn-sm table-bulk-actions__clear-btn no-loading js-bulk-clear-btn" data-bulk-clear>' +
                                'Annuler' +
                            '</button>' +
                        '</div>' +
                    '</div>' +
                '</div>'
            );

            $bar.attr('data-list-title', tableTitle);
            var $actionBar = $table.closest('.table-container').prevAll('.action-bar').first();
            if ($actionBar.length > 0) {
                var $actionTools = $actionBar.find('.action-tools').first();
                if ($actionTools.length > 0) {
                    $bar.insertBefore($actionTools);
                } else {
                    $actionBar.append($bar);
                }
            } else {
                $table.before($bar);
            }

            return $bar;
        }

        function refreshBulkState($table, $bulkBar) {
            var selectedCount = $table.find('.js-row-selector:checked, [data-row-checkbox]:checked').length;
            var totalRows = $table.find('tbody .js-row-selector:not(:disabled), tbody [data-row-checkbox]:not(:disabled)').length;
            var label = selectedCount > 1
                ? selectedCount + ' lignes selectionnees'
                : (selectedCount === 1 ? '1 ligne selectionnee' : '0 ligne selectionnee');

            var $selectAll = $table.find('.js-select-all-rows, [data-select-all-checkbox]').first();
            if ($selectAll.length > 0) {
                $selectAll.prop('checked', totalRows > 0 && selectedCount === totalRows);
                $selectAll.prop('indeterminate', selectedCount > 0 && selectedCount < totalRows);
            }

            if ($bulkBar.length === 0) {
                return;
            }

            if ($bulkBar.find('[data-selection-count]').length > 0) {
                $bulkBar.find('[data-selection-count]').text(selectedCount);
            }
            if ($bulkBar.find('[data-selection-label]').length > 0) {
                $bulkBar.find('[data-selection-label]').text(label);
            }
            if ($bulkBar.find('.js-bulk-label').length > 0) {
                $bulkBar.find('.js-bulk-label').text(label);
            }

            if ($bulkBar.hasClass('table-bulk-actions')) {
                $bulkBar.toggleClass('is-visible', selectedCount > 0);
            } else if (selectedCount > 0) {
                $bulkBar.show();
            } else {
                $bulkBar.hide();
            }
        }

        function setupBulkDeletion($table, config) {
            var $bulkBar = buildOrGetBulkBar($table, resolveTableId($table, config.resource));

            $bulkBar.on('click', '[data-bulk-action="delete"], .js-bulk-delete-btn', function() {
                var selectedIds = collectSelectedIds($table);
                if (selectedIds.length === 0) {
                    return;
                }

                openCrudBulkDeleteDialog($table, config, selectedIds);
            });
        }

        function collectSelectedIds($table) {
            var ids = [];
            $table.find('tbody .js-row-selector:checked, tbody [data-row-checkbox]:checked').each(function() {
                var $row = $(this).closest('tr');
                var rowIdValue = String($row.attr('data-row-id') || $(this).val() || '').trim();
                var id = parseInt(rowIdValue, 10);
                if (!isNaN(id) && id > 0) {
                    ids.push(id);
                }
            });
            return ids;
        }

        function resolveTableId($table, fallbackResource) {
            var tableId = String($table.attr('data-table-id') || '').trim();
            if (tableId !== '') {
                return tableId;
            }

            tableId = String(fallbackResource || '').trim();
            if (tableId !== '') {
                $table.attr('data-table-id', tableId);
            }
            return tableId;
        }

        function bindCrudBulkDeleteConfirmHandler() {
            $(document).off('click.crudBulkDelete', '#confirmDeleteBtn').on('click.crudBulkDelete', '#confirmDeleteBtn', function(event) {
                if (String($(this).attr('data-crud-bulk-delete') || '') !== '1') {
                    return;
                }

                event.preventDefault();

                if (!crudBulkDeleteState || !crudBulkDeleteState.table || crudBulkDeleteState.ids.length === 0) {
                    resetCrudBulkDeleteState();
                    return;
                }

                submitCrudBulkDelete();
            });

            $('#deleteConfirmModal').off('hidden.bs.modal.crudBulkDelete').on('hidden.bs.modal.crudBulkDelete', function() {
                if (!crudBulkDeleteState || !crudBulkDeleteState.submitting) {
                    resetCrudBulkDeleteState();
                }
            });
        }

        function openCrudBulkDeleteDialog($table, config, selectedIds) {
            var ids = Array.isArray(selectedIds) ? selectedIds.slice() : [];
            if (ids.length === 0) {
                return;
            }

            var $modal = $('#deleteConfirmModal');
            if ($modal.length === 0 || $('#confirmDeleteBtn').length === 0) {
                fallbackBulkDeleteWithConfirm($table, config, ids);
                return;
            }

            crudBulkDeleteState = {
                table: $table,
                config: config,
                ids: ids,
                submitting: false
            };

            $('#deleteModalItemName').text(ids.length > 1 ? (ids.length + ' elements selectionnes') : 'cet element selectionne');
            $('#confirmDeleteBtn')
                .attr('href', '#')
                .attr('data-crud-bulk-delete', '1')
                .removeClass('bulk-delete-loading')
                .css('pointer-events', '');

            $modal.modal('show');
        }

        function submitCrudBulkDelete() {
            if (!crudBulkDeleteState || crudBulkDeleteState.submitting) {
                return;
            }

            var state = crudBulkDeleteState;
            var config = state.config || {};
            var $confirmDeleteBtn = $('#confirmDeleteBtn');
            var $deleteModal = $('#deleteConfirmModal');

            state.submitting = true;
            $confirmDeleteBtn.addClass('bulk-delete-loading').css('pointer-events', 'none');

            $.ajax({
                url: '/api/crud/' + encodeURIComponent(config.resource) + '/bulk-delete',
                method: 'POST',
                dataType: 'json',
                data: JSON.stringify({
                    _token: config.bulkDeleteToken,
                    ids: state.ids
                }),
                contentType: 'application/json; charset=UTF-8',
                processData: false,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).done(function(response) {
                if (!response || response.success !== true) {
                    showToast((response && response.message) ? response.message : 'Suppression impossible.', 'error');
                    return;
                }

                var deletedIds = Array.isArray(response.deletedIds) && response.deletedIds.length
                    ? response.deletedIds
                    : state.ids;

                removeRowsByIds(state.table, deletedIds);

                var $selectAll = state.table.find('.js-select-all-rows, [data-select-all-checkbox]').first();
                if ($selectAll.length > 0) {
                    $selectAll.prop('checked', false).prop('indeterminate', false);
                }

                refreshBulkState(state.table, buildOrGetBulkBar(state.table, resolveTableId(state.table, config.resource)));
                $deleteModal.modal('hide');
                showToast(response.message || 'Elements supprimes avec succes.', 'success');
            }).fail(function(xhr) {
                var message = 'Suppression impossible.';
                if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                showToast(message, 'error');
            }).always(function() {
                if (crudBulkDeleteState) {
                    crudBulkDeleteState.submitting = false;
                }
                $confirmDeleteBtn.removeClass('bulk-delete-loading').css('pointer-events', '');
                if (!$deleteModal.hasClass('show')) {
                    resetCrudBulkDeleteState();
                }
            });
        }

        function fallbackBulkDeleteWithConfirm($table, config, ids) {
            if (!window.confirm('Supprimer ' + ids.length + ' element(s) selectionne(s) ?')) {
                return;
            }

            $.ajax({
                url: '/api/crud/' + encodeURIComponent(config.resource) + '/bulk-delete',
                method: 'POST',
                dataType: 'json',
                data: {
                    _token: config.bulkDeleteToken,
                    ids: ids
                }
            }).done(function(response) {
                if (!response || response.success !== true) {
                    showToast((response && response.message) ? response.message : 'Suppression impossible.', 'error');
                    return;
                }
                removeRowsByIds($table, ids);
                refreshBulkState($table, buildOrGetBulkBar($table, resolveTableId($table, config.resource)));
                showToast(response.message || 'Elements supprimes avec succes.', 'success');
            }).fail(function(xhr) {
                var message = 'Suppression impossible.';
                if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                showToast(message, 'error');
            });
        }

        function removeRowsByIds($table, ids) {
            var idMap = {};
            ids.forEach(function(id) {
                idMap[String(id)] = true;
            });

            $table.find('tbody tr').each(function() {
                var $row = $(this);
                var rowId = String($row.attr('data-row-id') || $row.attr('data-item-id') || '').trim();
                if (rowId !== '' && idMap[rowId]) {
                    $row.remove();
                }
            });
        }

        function resetCrudBulkDeleteState() {
            crudBulkDeleteState = null;
            $('#confirmDeleteBtn')
                .removeAttr('data-crud-bulk-delete')
                .removeClass('bulk-delete-loading')
                .css('pointer-events', '');
        }

        function setupInlineEditing($table, config) {
            var editableFields = getEditableFields($table);
            if (editableFields.length === 0) {
                return;
            }

            var tableId = resolveTableId($table, config.resource);
            var guardNamespace = sanitizeEventNamespace('crudInlineUnsaved_' + tableId);

            function requestActionAfterUnsavedCheck(action) {
                var $activeRow = getActiveEditingRow($table);
                if (!$activeRow.length) {
                    action();
                    return;
                }

                if (!hasUnsavedChanges($activeRow, editableFields, config)) {
                    cancelInlineEdit($activeRow);
                    action();
                    return;
                }

                openUnsavedChangesDialog(function() {
                    cancelInlineEdit($activeRow);
                    action();
                });
            }

            $table.on('click', '.btn-edit', function(event) {
                var $row = $(this).closest('tr[data-row-id]');
                if ($row.length === 0) {
                    return;
                }

                event.preventDefault();

                if ($row.hasClass('js-inline-editing')) {
                    return;
                }

                requestActionAfterUnsavedCheck(function() {
                    beginInlineEdit($row, editableFields, config);
                });
            });

            $table.on('dblclick', 'tbody tr[data-row-id]', function(event) {
                if ($(event.target).closest('a, button, input, select, textarea, label, [data-row-checkbox], [data-select-all-checkbox]').length > 0) {
                    return;
                }

                var $row = $(this);
                if ($row.length === 0 || $row.hasClass('js-inline-editing')) {
                    return;
                }

                requestActionAfterUnsavedCheck(function() {
                    beginInlineEdit($row, editableFields, config);
                });
            });

            $table.on('click', '.js-inline-cancel-btn', function() {
                var $row = $(this).closest('tr[data-row-id]');
                cancelInlineEdit($row);
            });

            $table.on('click', '.js-inline-save-btn', function() {
                var $row = $(this).closest('tr[data-row-id]');
                saveInlineEdit($row, editableFields, config);
            });

            $table.on('click', '.inline-dropdown-trigger, .inline-dropdown-label', function(event) {
                event.preventDefault();
                event.stopPropagation();

                var $shell = $(this).closest('.inline-dropdown-shell');
                if ($shell.length === 0) {
                    return;
                }

                if ($shell.hasClass('is-open')) {
                    $shell.removeClass('is-open');
                    return;
                }

                openInlineDropdownShell($table, $shell);
            });

            $table.on('click', '.inline-dropdown-option', function(event) {
                event.preventDefault();
                event.stopPropagation();

                var $option = $(this);
                var $shell = $option.closest('.inline-dropdown-shell');
                if ($shell.length === 0) {
                    return;
                }

                var value = String($option.attr('data-value') || '');
                var label = String($option.attr('data-label') || $option.text() || '');
                setInlineDropdownValue($shell, value, label);
                $shell.removeClass('is-open');
            });

            $table.on('keydown', '.js-inline-editor', function(event) {
                var $editor = $(this);
                var editorType = String($editor.attr('data-type') || 'string').toLowerCase();

                if (event.key === 'Escape') {
                    event.preventDefault();
                    cancelInlineEdit($editor.closest('tr[data-row-id]'));
                    return;
                }

                if (event.key === 'Enter' && !event.shiftKey) {
                    event.preventDefault();
                    saveInlineEdit($editor.closest('tr[data-row-id]'), editableFields, config);
                    return;
                }

                if (editorType === 'digits' && !isAllowedDigitKey(event)) {
                    event.preventDefault();
                }
            });

            $table.on('paste', '.js-inline-editor[data-type="digits"]', function(event) {
                var clipboard = event.originalEvent && event.originalEvent.clipboardData
                    ? event.originalEvent.clipboardData.getData('text')
                    : '';
                var digits = String(clipboard || '').replace(/\D+/g, '');
                if (digits === '') {
                    event.preventDefault();
                    return;
                }

                event.preventDefault();
                insertTextAtCursor(digits);
            });

            $(document).off('click.' + guardNamespace);
            $(document).on('click.' + guardNamespace, function(event) {
                var $activeRow = getActiveEditingRow($table);
                if (!$activeRow.length) {
                    return;
                }

                var $target = $(event.target);
                if ($target.closest($activeRow).length > 0) {
                    if ($target.closest('.inline-dropdown-shell').length === 0 && $target.closest('.inline-dropdown-menu').length === 0) {
                        $table.find('.inline-dropdown-shell.is-open').removeClass('is-open');
                    }
                    return;
                }

                var $interactive = $target.closest('a, button, input, select, textarea, label');
                if ($interactive.length === 0) {
                    return;
                }

                if ($interactive.closest('.modal').length > 0 || $interactive.closest('#crudUnsavedChangesModal').length > 0) {
                    return;
                }

                if ($interactive.is('.js-inline-save-btn, .js-inline-cancel-btn')) {
                    return;
                }

                if ($interactive.data('skipUnsavedGuard') === 1) {
                    $interactive.removeData('skipUnsavedGuard');
                    return;
                }

                if (!hasUnsavedChanges($activeRow, editableFields, config)) {
                    cancelInlineEdit($activeRow);
                    return;
                }

                event.preventDefault();
                event.stopImmediatePropagation();

                var $element = $interactive.first();
                openUnsavedChangesDialog(function() {
                    cancelInlineEdit($activeRow);
                    triggerDeferredInteraction($element);
                });
            });

            $(document).off('submit.' + guardNamespace);
            $(document).on('submit.' + guardNamespace, 'form', function(event) {
                var $activeRow = getActiveEditingRow($table);
                if (!$activeRow.length) {
                    return;
                }

                var $form = $(this);
                if ($form.closest($activeRow).length > 0 || $form.closest('.modal').length > 0) {
                    return;
                }

                if ($form.data('skipUnsavedGuard') === 1) {
                    $form.removeData('skipUnsavedGuard');
                    return;
                }

                if (!hasUnsavedChanges($activeRow, editableFields, config)) {
                    cancelInlineEdit($activeRow);
                    return;
                }

                event.preventDefault();
                var formElement = this;
                openUnsavedChangesDialog(function() {
                    cancelInlineEdit($activeRow);
                    $form.data('skipUnsavedGuard', 1);
                    if (typeof formElement.submit === 'function') {
                        formElement.submit();
                    }
                });
            });
        }

        function setupDetailModal($table, config) {
            if (!$table || !$table.length) {
                return;
            }

            var editableFields = getEditableFields($table);
            var canEditInModal = config.enableInlineEdit && config.updateToken !== '' && editableFields.length > 0;

            $table.find('a.btn-view').addClass('no-loading');

            $table.off('click.crudDetailView', 'a.btn-view').on('click.crudDetailView', 'a.btn-view', function(event) {
                if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                    return;
                }

                var $row = $(this).closest('tr[data-row-id]');
                if ($row.length === 0) {
                    return;
                }

                event.preventDefault();

                var openModalAction = function() {
                    openCrudDetailModal($table, config, editableFields, $row, canEditInModal);
                };

                var $activeRow = getActiveEditingRow($table);
                if (!$activeRow.length) {
                    openModalAction();
                    return;
                }

                if (!hasUnsavedChanges($activeRow, editableFields, config)) {
                    cancelInlineEdit($activeRow);
                    openModalAction();
                    return;
                }

                openUnsavedChangesDialog(function() {
                    cancelInlineEdit($activeRow);
                    openModalAction();
                }, 'Des modifications non enregistrees seront perdues. Continuer ?');
            });
        }

        function openCrudDetailModal($table, config, editableFields, $row, canEditInModal) {
            var $modal = $('#crudDetailModal');
            if ($modal.length === 0) {
                var href = String($row.find('a.btn-view').first().attr('href') || '').trim();
                if (href !== '') {
                    window.location.href = href;
                }
                return;
            }

            var rowId = String($row.attr('data-row-id') || $row.attr('data-item-id') || '').trim();
            var rowData = buildCrudDetailRowData($table, $row, editableFields, String(config.placeholder || '____'), String(config.resource || ''));
            var title = rowData.primaryValue !== '' ? rowData.primaryValue : (config.resource + ' #' + rowId);

            crudDetailState.$table = $table;
            crudDetailState.$row = $row;
            crudDetailState.config = config;
            crudDetailState.editableFields = editableFields.slice();
            crudDetailState.mode = 'view';

            $modal.find('.js-crud-detail-title').text('Details - ' + title);
            $modal.find('.js-crud-detail-subtitle').text('Element #' + rowId);

            renderCrudDetailView(rowData);
            renderCrudDetailEdit(rowData, canEditInModal);
            setCrudDetailMode('view', canEditInModal);
            destroyCrudDetailCharts();
            renderCrudDetailCharts(rowData);

            $modal.modal('show');
        }

        function buildCrudDetailRowData($table, $row, editableFields, placeholder, resourceName) {
            var safePlaceholder = String(placeholder || '____');
            var fields = [];
            var fieldMap = Object.create(null);
            var editableMap = Object.create(null);
            var primaryValue = '';
            var firstRequired = null;

            editableFields.forEach(function(meta) {
                var key = String((meta && meta.field) || '').trim();
                if (key !== '') {
                    editableMap[key] = meta;
                }
            });

            function getRawCellValue($cell) {
                var raw = String($cell.attr('data-field-value') || '').trim();
                if (raw === '') {
                    raw = normalizeDisplayValue($cell.text(), safePlaceholder);
                }
                return raw;
            }

            function inferFieldTypeFromValue(fieldKey, value) {
                var key = String(fieldKey || '').toLowerCase().trim();
                var normalizedValue = String(value || '').trim();
                if (key.indexOf('date') >= 0 || key.indexOf('annee') >= 0) {
                    return 'date';
                }
                if (key.indexOf('email') >= 0) {
                    return 'string';
                }
                if (key.indexOf('tel') >= 0 || key.indexOf('phone') >= 0) {
                    return 'digits';
                }
                if (/^-?\d+$/.test(normalizedValue)) {
                    return 'int';
                }
                if (/^-?\d+(?:[.,]\d+)?$/.test(normalizedValue)) {
                    return 'float';
                }
                return 'string';
            }

            function pushField(fieldKey, fieldMeta, rawValue) {
                var normalizedKey = String(fieldKey || '').trim();
                if (normalizedKey === '' || fieldMap[normalizedKey]) {
                    return;
                }

                var meta = fieldMeta || {};
                var label = String(meta.label || normalizedKey).trim();
                var type = String(meta.type || inferFieldTypeFromValue(normalizedKey, rawValue)).trim();
                var required = !!meta.required;
                var editable = !!meta.editable;
                var displayValue = String(rawValue || '').trim() === '' ? safePlaceholder : String(rawValue);

                fields.push({
                    meta: {
                        field: normalizedKey,
                        label: label,
                        required: required,
                        type: type,
                        editable: editable,
                        options: Array.isArray(meta.options) ? meta.options : []
                    },
                    value: String(rawValue || ''),
                    display: displayValue
                });
                fieldMap[normalizedKey] = true;

                if (firstRequired === null && required) {
                    firstRequired = rawValue;
                }
            }

            $row.find('td[data-field], td[data-column-key]').each(function() {
                var $cell = $(this);
                var fieldName = String($cell.attr('data-field') || '').trim();
                var columnKey = String($cell.attr('data-column-key') || '').trim();
                var resolvedKey = fieldName !== '' ? fieldName : columnKey;

                if (resolvedKey === '' || resolvedKey === 'selector' || resolvedKey === 'actions' || resolvedKey === 'settings') {
                    return;
                }

                var resolvedMeta = editableMap[resolvedKey] || null;
                var $header = $();
                if (columnKey !== '' && $table && $table.length) {
                    $header = $table.find('thead th[data-column-key="' + columnKey + '"]').first();
                }
                if ($header.length === 0 && fieldName !== '' && $table && $table.length) {
                    $header = $table.find('thead th[data-field="' + fieldName + '"]').first();
                }

                if (!resolvedMeta) {
                    resolvedMeta = {
                        field: resolvedKey,
                        label: $header.length > 0 ? String($header.text() || resolvedKey).trim() : resolvedKey,
                        required: $header.length > 0 ? String($header.attr('data-required') || '') === '1' : false,
                        type: $header.length > 0 ? String($header.attr('data-type') || 'string').trim() : 'string',
                        editable: false
                    };
                }

                pushField(resolvedKey, resolvedMeta, getRawCellValue($cell));
            });

            editableFields.forEach(function(meta) {
                var fieldName = String((meta && meta.field) || '').trim();
                if (fieldName === '' || fieldMap[fieldName]) {
                    return;
                }

                var $cell = $row.find('td[data-field="' + fieldName + '"]').first();
                var rawValue = $cell.length > 0 ? getRawCellValue($cell) : '';
                var mergedMeta = $.extend({}, meta, { editable: true });
                pushField(fieldName, mergedMeta, rawValue);
            });

            if (firstRequired !== null && String(firstRequired).trim() !== '') {
                primaryValue = String(firstRequired).trim();
            } else if (fields.length > 0 && String(fields[0].value || '').trim() !== '') {
                primaryValue = String(fields[0].value || '').trim();
            }

            return {
                resource: String(resourceName || '').trim(),
                rowId: String($row.attr('data-row-id') || $row.attr('data-item-id') || '').trim(),
                primaryValue: primaryValue,
                fields: fields,
                placeholder: safePlaceholder
            };
        }

        function getCrudDetailCompletionStats(rowData) {
            var filledCount = 0;
            var totalCount = rowData.fields.length;
            var requiredCount = 0;
            var requiredFilled = 0;

            rowData.fields.forEach(function(fieldItem) {
                var isRequired = !!(fieldItem.meta && fieldItem.meta.required);
                var hasValue = String(fieldItem.value || '').trim() !== '';

                if (hasValue) {
                    filledCount += 1;
                }
                if (isRequired) {
                    requiredCount += 1;
                    if (hasValue) {
                        requiredFilled += 1;
                    }
                }
            });

            var completionPct = totalCount > 0 ? Math.round((filledCount / totalCount) * 100) : 0;
            var optionalTotal = Math.max(0, totalCount - requiredCount);
            var optionalFilled = Math.max(0, filledCount - requiredFilled);

            return {
                filledCount: filledCount,
                totalCount: totalCount,
                requiredCount: requiredCount,
                requiredFilled: requiredFilled,
                completionPct: completionPct,
                optionalTotal: optionalTotal,
                optionalFilled: optionalFilled
            };
        }

        function renderCrudDetailView(rowData) {
            var $list = $('#crudDetailViewList');
            if ($list.length === 0) {
                return;
            }

            var html = '';
            rowData.fields.forEach(function(fieldItem) {
                var label = String((fieldItem.meta && fieldItem.meta.label) || (fieldItem.meta && fieldItem.meta.field) || 'Champ').trim();
                var value = String(fieldItem.display || rowData.placeholder);
                html += '<dt>' + escapeHtml(label) + '</dt><dd>' + escapeHtml(value) + '</dd>';
            });
            $list.html(html);

            var stats = getCrudDetailCompletionStats(rowData);
            $('#crudDetailStatValue1').text(stats.completionPct + '%');
            $('#crudDetailStatValue2').text(stats.optionalFilled + '/' + stats.optionalTotal);
            $('#crudDetailStatValue3').text(stats.optionalTotal > 0 ? (stats.optionalFilled + '/' + stats.optionalTotal) : 'N/A');
        }

        function renderCrudDetailEdit(rowData, canEditInModal) {
            var $form = $('#crudDetailEditFields');
            var $metrics = $('#crudDetailEditMetrics');
            var $switchBtn = $('#crudDetailSwitchToEditBtn');
            var $saveBtn = $('#crudDetailSaveBtn');

            if (!$form.length) {
                return;
            }

            if (!canEditInModal) {
                $form.html(
                    '<div class="crud-detail-edit-empty">' +
                        '<i class="fas fa-lock"></i>' +
                        '<h6>Edition non disponible</h6>' +
                        '<p>Cette liste ne prend pas en charge l edition depuis ce panneau.</p>' +
                    '</div>'
                );
                if ($metrics.length) {
                    $metrics.empty();
                }
                if ($switchBtn.length) {
                    $switchBtn.hide();
                }
                if ($saveBtn.length) {
                    $saveBtn.hide();
                }
                return;
            }

            var editableItems = rowData.fields.filter(function(item) {
                return !!(item && item.meta && item.meta.editable);
            });
            if (editableItems.length === 0) {
                $form.html(
                    '<div class="crud-detail-edit-empty">' +
                        '<i class="fas fa-lock"></i>' +
                        '<h6>Aucun champ modifiable</h6>' +
                        '<p>Cette fiche ne contient pas de champ editable depuis cette vue.</p>' +
                    '</div>'
                );
                if ($metrics.length) {
                    $metrics.empty();
                }
                if ($saveBtn.length) {
                    $saveBtn.hide();
                }
                return;
            }

            var html = '';
            var totalFields = editableItems.length;
            var requiredCount = 0;
            editableItems.forEach(function(fieldItem, index) {
                var meta = fieldItem.meta || {};
                var fieldName = String(meta.field || '').trim();
                var fieldLabel = String(meta.label || fieldName || 'Champ').trim();
                var fieldType = String(meta.type || 'string').toLowerCase().trim();
                var requiredAttr = meta.required ? ' required' : '';
                var requiredLabel = meta.required ? '<span class="crud-detail-edit-required">*</span>' : '';
                var value = String(fieldItem.value || '');
                var safeFieldId = 'crudDetailField_' + String(fieldName || ('field_' + index)).replace(/[^a-zA-Z0-9_-]/g, '_');
                var requirementLabel = meta.required ? 'Obligatoire' : 'Optionnel';
                var requirementClass = meta.required ? ' is-required' : '';
                var typeLabel = getCrudDetailFieldTypeLabel(fieldType);
                var hintText = getCrudDetailFieldHint(fieldType);

                if (meta.required) {
                    requiredCount += 1;
                }

                html += '<div class="crud-detail-edit-field' + requirementClass + '" data-field-card="' + escapeHtml(fieldName) + '">';
                html += '<div class="crud-detail-edit-head">';
                html += '<label class="crud-detail-edit-label" for="' + escapeHtml(safeFieldId) + '">' + escapeHtml(fieldLabel) + requiredLabel + '</label>';
                html += '<div class="crud-detail-edit-badges">';
                html += '<span class="crud-detail-edit-badge' + requirementClass + '">' + escapeHtml(requirementLabel) + '</span>';
                html += '<span class="crud-detail-edit-badge">' + escapeHtml(typeLabel) + '</span>';
                html += '</div>';
                html += '</div>';

                if (isSelectEditorType(fieldType)) {
                    html += '<select id="' + escapeHtml(safeFieldId) + '" class="form-control js-crud-detail-input" data-field="' + escapeHtml(fieldName) + '" data-type="' + escapeHtml(fieldType) + '"' + requiredAttr + '>';
                    html += '<option value="">' + escapeHtml(rowData.placeholder) + '</option>';

                    var options = Array.isArray(meta.options) ? meta.options : [];
                    options.forEach(function(option) {
                        var optionValue = String((option && option.value) || '').trim();
                        if (optionValue === '') {
                            return;
                        }
                        var optionLabel = String((option && option.label) || optionValue).trim();
                        var selected = optionValue === value ? ' selected' : '';
                        html += '<option value="' + escapeHtml(optionValue) + '"' + selected + '>' + escapeHtml(optionLabel) + '</option>';
                    });

                    html += '</select>';
                } else {
                    var inputType = (fieldType === 'int' || fieldType === 'number' || fieldType === 'digits') ? 'text' : 'text';
                    html += '<input id="' + escapeHtml(safeFieldId) + '" type="' + inputType + '" class="form-control js-crud-detail-input" data-field="' + escapeHtml(fieldName) + '" data-type="' + escapeHtml(fieldType) + '" value="' + escapeHtml(value) + '" placeholder="' + escapeHtml(rowData.placeholder) + '"' + requiredAttr + '>';
                }
                html += '<p class="crud-detail-edit-hint">' + escapeHtml(hintText) + '</p>';
                html += '<div class="crud-detail-edit-error">Valeur invalide pour ce champ.</div>';
                html += '</div>';
            });

            if ($metrics.length) {
                $metrics.html(
                    '<span class="crud-detail-edit-chip"><strong>' + totalFields + '</strong> champs</span>' +
                    '<span class="crud-detail-edit-chip"><strong>' + requiredCount + '</strong> obligatoires</span>' +
                    '<span class="crud-detail-edit-chip"><strong>' + Math.max(0, totalFields - requiredCount) + '</strong> optionnels</span>'
                );
            }

            $form.html(html);
            if ($switchBtn.length) {
                $switchBtn.show();
            }
            if ($saveBtn.length) {
                $saveBtn.show();
            }
        }

        function setCrudDetailMode(mode, canEditInModal) {
            var normalized = mode === 'edit' ? 'edit' : 'view';
            crudDetailState.mode = normalized;

            var isEdit = normalized === 'edit' && canEditInModal;
            $('#crudDetailViewPane').toggleClass('is-active', !isEdit);
            $('#crudDetailEditPane').toggleClass('is-active', isEdit);
            $('#crudDetailModeViewBtn').toggleClass('is-active', !isEdit);
            $('#crudDetailModeEditBtn').toggleClass('is-active', isEdit);
            $('#crudDetailSaveBtn').toggle(isEdit && canEditInModal);
            $('#crudDetailSwitchToEditBtn').toggle(!isEdit && canEditInModal);
        }

        function destroyCrudDetailCharts() {
            if (crudDetailState.charts.completion) {
                crudDetailState.charts.completion.destroy();
                crudDetailState.charts.completion = null;
            }
            if (crudDetailState.charts.profile) {
                crudDetailState.charts.profile.destroy();
                crudDetailState.charts.profile = null;
            }
        }

        function renderCrudDetailCharts(rowData) {
            if (typeof Chart === 'undefined') {
                return;
            }

            var insight = buildCrudDetailInsight(rowData);
            applyCrudDetailInsightCards(insight);
            applyCrudDetailInsightTitles(insight);

            var completionCanvas = document.getElementById('crudDetailCompletionChart');
            if (completionCanvas && insight.chartA) {
                crudDetailState.charts.completion = new Chart(completionCanvas.getContext('2d'), insight.chartA);
            }

            var profileCanvas = document.getElementById('crudDetailProfileChart');
            if (profileCanvas && insight.chartB) {
                crudDetailState.charts.profile = new Chart(profileCanvas.getContext('2d'), insight.chartB);
            }
        }

        function buildCrudDetailInsight(rowData) {
            var stats = getCrudDetailCompletionStats(rowData);
            var resource = String(rowData.resource || '').toLowerCase().trim();

            if (resource === 'article') {
                return buildArticleDetailInsight(rowData, stats);
            }
            if (resource === 'entetepiece' || resource === 'pieces' || resource === 'piece') {
                return buildPieceDetailInsight(rowData, stats);
            }
            if (resource === 'tarifvente') {
                return buildTarifVenteDetailInsight(rowData, stats);
            }
            if (resource === 'client' || resource === 'fournisseur' || resource === 'prospect' || resource === 'tiers_interne' || resource === 'depot') {
                return buildContactDetailInsight(rowData, stats);
            }
            return buildDefaultDetailInsight(rowData, stats);
        }

        function buildDefaultDetailInsight(rowData, stats) {
            var completionData = [stats.filledCount, Math.max(0, stats.totalCount - stats.filledCount)];
            var labels = [];
            var values = [];
            rowData.fields.slice(0, 8).forEach(function(fieldItem) {
                labels.push(shortCrudDetailLabel((fieldItem.meta && fieldItem.meta.label) || (fieldItem.meta && fieldItem.meta.field) || 'Champ', 14));
                values.push(String(fieldItem.value || '').trim() !== '' ? 100 : 0);
            });

            return {
                cards: [
                    { label: 'Completion', value: stats.completionPct + '%' },
                    { label: 'Optionnels', value: stats.optionalFilled + '/' + stats.optionalTotal },
                    { label: 'Profil', value: stats.optionalTotal > 0 ? (stats.optionalFilled + '/' + stats.optionalTotal) : 'N/A' }
                ],
                chartATitle: 'Completion',
                chartBTitle: 'Couverture des champs',
                chartA: createCrudDoughnutChart(['Renseigne', 'Manquant'], completionData, ['#2563eb', '#e2e8f0']),
                chartB: createCrudPercentBarChart(labels, values, 'Couverture')
            };
        }

        function buildArticleDetailInsight(rowData, stats) {
            var salesField = findCrudDetailField(rowData, ['ventes', 'vente', 'qtevendue', 'quantitevendue', 'ca', 'chiffreaffaire']);
            var salesValue = salesField ? parseCrudNumber(salesField.value || salesField.display) : null;
            var salesBenchmark = salesField ? getCrudTableNumericBenchmark(salesField.meta.field) : null;
            var averageSales = salesBenchmark && salesBenchmark.count > 0 ? salesBenchmark.avg : null;

            var priceField = findCrudDetailField(rowData, ['tarif', 'prix', 'prixvente', 'price']);
            var priceValue = priceField ? parseCrudNumber(priceField.value || priceField.display) : null;
            var priceBenchmark = priceField ? getCrudTableNumericBenchmark(priceField.meta.field) : null;
            var averagePrice = priceBenchmark && priceBenchmark.count > 0 ? priceBenchmark.avg : null;

            var stockField = findCrudDetailField(rowData, ['sortistock', 'stock', 'suivistock']);
            var stockValue = stockField ? String(stockField.display || stockField.value || '').toLowerCase() : '';
            var hasStockTracking = stockValue.indexOf('oui') >= 0 || stockValue.indexOf('yes') >= 0 || stockValue.indexOf('true') >= 0 || stockValue.indexOf('1') >= 0;

            var identityScore = computeCrudGroupFillScore(rowData, ['libelle', 'unite', 'code']);
            var managementScore = computeCrudGroupFillScore(rowData, ['modegestion', 'modesuivi', 'natureproduction']);
            var supplyScore = computeCrudGroupFillScore(rowData, ['fournisseurhabituel', 'sortistock']);

            var cards = [
                { label: 'Tarif', value: priceValue === null ? rowData.placeholder : formatCrudNumber(priceValue, 2) },
                {
                    label: salesValue !== null ? 'Ventes' : 'Position',
                    value: salesValue !== null
                        ? formatCrudNumber(salesValue, 0)
                        : ((priceValue !== null && averagePrice !== null && averagePrice > 0) ? formatCrudDelta(((priceValue - averagePrice) / averagePrice) * 100) : 'N/A')
                },
                { label: 'Suivi stock', value: stockField ? (hasStockTracking ? 'Actif' : 'Inactif') : 'N/A' }
            ];

            var chartA = null;
            var chartATitle = 'Positionnement tarifaire';
            if (salesValue !== null || averageSales !== null) {
                chartATitle = 'Ventes article vs moyenne';
                chartA = createCrudMetricBarChart(['Article', 'Moyenne'], [salesValue !== null ? salesValue : 0, averageSales !== null ? averageSales : 0], 'Ventes');
            } else if (priceValue !== null || averagePrice !== null) {
                chartA = createCrudMetricBarChart(['Article', 'Moyenne'], [priceValue !== null ? priceValue : 0, averagePrice !== null ? averagePrice : 0], 'Tarif');
            } else {
                chartA = createCrudDoughnutChart(['Renseigne', 'Manquant'], [stats.filledCount, Math.max(0, stats.totalCount - stats.filledCount)], ['#2563eb', '#e2e8f0']);
            }

            return {
                cards: cards,
                chartATitle: chartATitle,
                chartBTitle: 'Maturite de la fiche article',
                chartA: chartA,
                chartB: createCrudRadarChart(
                    ['Identite', 'Gestion', 'Approvisionnement'],
                    [identityScore, managementScore, supplyScore],
                    'Score'
                )
            };
        }

        function buildPieceDetailInsight(rowData, stats) {
            var amountField = findCrudDetailField(rowData, ['montant', 'total', 'totalttc']);
            var linesField = findCrudDetailField(rowData, ['lignes', 'nbreligne', 'nombrelignes']);
            var discountField = findCrudDetailField(rowData, ['remise']);

            var amount = amountField ? parseCrudNumber(amountField.value || amountField.display) : null;
            var lines = linesField ? parseCrudNumber(linesField.value || linesField.display) : null;
            var discount = discountField ? parseCrudNumber(discountField.value || discountField.display) : null;

            var amountBenchmark = amountField ? getCrudTableNumericBenchmark(amountField.meta.field) : null;
            var avgAmount = amountBenchmark && amountBenchmark.count > 0 ? amountBenchmark.avg : null;
            var maxAmount = amountBenchmark && amountBenchmark.max > 0 ? amountBenchmark.max : null;
            var maxLines = linesField ? (getCrudTableNumericBenchmark(linesField.meta.field).max || null) : null;

            var discountRate = null;
            if (discount !== null) {
                if (discount <= 100) {
                    discountRate = clampCrudPercent(discount);
                } else if (amount !== null && amount > 0) {
                    discountRate = clampCrudPercent((discount / amount) * 100);
                }
            }

            var cards = [
                { label: 'Montant', value: amount === null ? rowData.placeholder : formatCrudNumber(amount, 2) },
                { label: 'Remise', value: discountRate === null ? (discount === null ? 'N/A' : formatCrudNumber(discount, 2)) : formatCrudNumber(discountRate, 1) + '%' },
                { label: 'Lignes', value: lines === null ? '0' : String(Math.round(lines)) }
            ];

            var amountScore = (amount !== null && maxAmount && maxAmount > 0) ? clampCrudPercent((amount / maxAmount) * 100) : stats.completionPct;
            var complexityScore = (lines !== null && maxLines && maxLines > 0) ? clampCrudPercent((lines / maxLines) * 100) : stats.completionPct;
            var controlScore = discountRate === null ? stats.completionPct : clampCrudPercent(100 - discountRate);

            return {
                cards: cards,
                chartATitle: 'Montant vs moyenne',
                chartBTitle: 'Profil de la piece',
                chartA: createCrudMetricBarChart(['Piece', 'Moyenne'], [amount !== null ? amount : 0, avgAmount !== null ? avgAmount : 0], 'Montant'),
                chartB: createCrudRadarChart(['Valeur', 'Complexite', 'Remise maitrisee'], [amountScore, complexityScore, controlScore], 'Indice')
            };
        }

        function buildTarifVenteDetailInsight(rowData, stats) {
            var priceField = findCrudDetailField(rowData, ['prix']);
            var effectDateField = findCrudDetailField(rowData, ['dateeffet', 'date']);
            var price = priceField ? parseCrudNumber(priceField.value || priceField.display) : null;
            var benchmark = priceField ? getCrudTableNumericBenchmark(priceField.meta.field) : null;
            var avgPrice = benchmark && benchmark.count > 0 ? benchmark.avg : null;

            var ageDays = null;
            if (effectDateField) {
                var dt = parseCrudDate(effectDateField.value || effectDateField.display);
                if (dt) {
                    var ms = (new Date()).getTime() - dt.getTime();
                    ageDays = Math.max(0, Math.floor(ms / 86400000));
                }
            }
            var freshness = ageDays === null ? null : clampCrudPercent(100 - (ageDays / 365) * 100);

            return {
                cards: [
                    { label: 'Prix', value: price === null ? rowData.placeholder : formatCrudNumber(price, 2) },
                    { label: 'Ecart', value: (price !== null && avgPrice !== null && avgPrice > 0) ? formatCrudDelta(((price - avgPrice) / avgPrice) * 100) : 'N/A' },
                    { label: 'Age tarif', value: ageDays === null ? 'N/A' : (ageDays + ' j') }
                ],
                chartATitle: 'Prix vs moyenne',
                chartBTitle: 'Fraicheur du tarif',
                chartA: createCrudMetricBarChart(['Tarif', 'Moyenne'], [price !== null ? price : 0, avgPrice !== null ? avgPrice : 0], 'Prix'),
                chartB: createCrudDoughnutChart(
                    ['Actuel', 'A rafraichir'],
                    [freshness === null ? stats.completionPct : freshness, freshness === null ? (100 - stats.completionPct) : (100 - freshness)],
                    ['#0f766e', '#e2e8f0']
                )
            };
        }

        function buildContactDetailInsight(rowData, stats) {
            var hasPhone = hasCrudFieldValue(rowData, ['tel', 'telephone', 'phone']);
            var hasEmail = hasCrudFieldValue(rowData, ['email']);
            var hasAddress = hasCrudFieldValue(rowData, ['adr1', 'adresse']);
            var hasCity = hasCrudFieldValue(rowData, ['ville']);
            var hasCountry = hasCrudFieldValue(rowData, ['pays']);
            var hasTarif = hasCrudFieldValue(rowData, ['tarif']);
            var contactReady = (hasPhone ? 1 : 0) + (hasEmail ? 1 : 0) + (hasAddress ? 1 : 0);

            return {
                cards: [
                    { label: 'Completion', value: stats.completionPct + '%' },
                    { label: 'Canaux contact', value: contactReady + '/3' },
                    { label: 'Localisation', value: ((hasCity ? 1 : 0) + (hasCountry ? 1 : 0)) + '/2' }
                ],
                chartATitle: 'Disponibilite des contacts',
                chartBTitle: 'Qualite du profil',
                chartA: createCrudDoughnutChart(
                    ['Renseigne', 'Manquant'],
                    [contactReady, Math.max(0, 3 - contactReady)],
                    ['#16a34a', '#e2e8f0']
                ),
                chartB: createCrudPercentBarChart(
                    ['Telephone', 'Email', 'Adresse', 'Ville', 'Pays', 'Tarif'],
                    [hasPhone ? 100 : 0, hasEmail ? 100 : 0, hasAddress ? 100 : 0, hasCity ? 100 : 0, hasCountry ? 100 : 0, hasTarif ? 100 : 0],
                    'Disponibilite'
                )
            };
        }

        function applyCrudDetailInsightCards(insight) {
            var cards = Array.isArray(insight && insight.cards) ? insight.cards : [];
            for (var i = 0; i < 3; i += 1) {
                var card = cards[i] || {};
                $('#crudDetailStatLabel' + (i + 1)).text(String(card.label || 'Indicateur'));
                $('#crudDetailStatValue' + (i + 1)).text(String(card.value || 'N/A'));
            }
        }

        function applyCrudDetailInsightTitles(insight) {
            $('#crudDetailChartATitle').text(String((insight && insight.chartATitle) || 'Indicateur'));
            $('#crudDetailChartBTitle').text(String((insight && insight.chartBTitle) || 'Analyse'));
        }

        function findCrudDetailField(rowData, keys) {
            var wanted = Array.isArray(keys) ? keys.map(function(key) { return String(key || '').toLowerCase().trim(); }) : [];
            for (var i = 0; i < rowData.fields.length; i += 1) {
                var item = rowData.fields[i];
                var fieldKey = String((item.meta && item.meta.field) || '').toLowerCase().trim();
                if (wanted.indexOf(fieldKey) >= 0) {
                    return item;
                }
            }
            return null;
        }

        function hasCrudFieldValue(rowData, keys) {
            var item = findCrudDetailField(rowData, keys);
            return !!(item && String(item.value || '').trim() !== '');
        }

        function computeCrudGroupFillScore(rowData, keys) {
            var used = 0;
            var filled = 0;
            (keys || []).forEach(function(key) {
                var item = findCrudDetailField(rowData, [key]);
                if (!item) {
                    return;
                }
                used += 1;
                if (String(item.value || '').trim() !== '') {
                    filled += 1;
                }
            });
            if (used === 0) {
                return 0;
            }
            return Math.round((filled / used) * 100);
        }

        function parseCrudNumber(value) {
            var input = String(value || '').replace(/\u00A0/g, ' ').trim();
            if (input === '') {
                return null;
            }
            var cleaned = input.replace(/[^\d,.\-]/g, '');
            if (cleaned === '' || cleaned === '-' || cleaned === ',' || cleaned === '.') {
                return null;
            }

            var lastComma = cleaned.lastIndexOf(',');
            var lastDot = cleaned.lastIndexOf('.');
            if (lastComma > lastDot) {
                cleaned = cleaned.replace(/\./g, '').replace(',', '.');
            } else {
                cleaned = cleaned.replace(/,/g, '');
            }

            var parsed = parseFloat(cleaned);
            if (!isFinite(parsed)) {
                return null;
            }
            return parsed;
        }

        function parseCrudDate(value) {
            var raw = String(value || '').trim();
            if (raw === '') {
                return null;
            }
            var date = null;
            if (/^\d{4}-\d{2}-\d{2}/.test(raw)) {
                date = new Date(raw + 'T00:00:00');
            } else if (/^\d{2}\/\d{2}\/\d{4}$/.test(raw)) {
                var parts = raw.split('/');
                date = new Date(parts[2] + '-' + parts[1] + '-' + parts[0] + 'T00:00:00');
            } else {
                date = new Date(raw);
            }
            if (isNaN(date.getTime())) {
                return null;
            }
            return date;
        }

        function getCrudTableNumericBenchmark(fieldKey) {
            if (!crudDetailState.$table || !crudDetailState.$table.length) {
                return { count: 0, avg: null, min: null, max: null };
            }
            var selector = 'td[data-field="' + fieldKey + '"],td[data-column-key="' + fieldKey + '"]';
            var values = [];
            crudDetailState.$table.find('tbody tr[data-row-id]').each(function() {
                var $cell = $(this).find(selector).first();
                if (!$cell.length) {
                    return;
                }
                var raw = String($cell.attr('data-field-value') || '').trim();
                if (raw === '') {
                    raw = normalizeDisplayValue($cell.text(), String(crudDetailState.config && crudDetailState.config.placeholder ? crudDetailState.config.placeholder : '____'));
                }
                var num = parseCrudNumber(raw);
                if (num !== null) {
                    values.push(num);
                }
            });
            if (values.length === 0) {
                return { count: 0, avg: null, min: null, max: null };
            }
            var sum = values.reduce(function(acc, current) { return acc + current; }, 0);
            return {
                count: values.length,
                avg: sum / values.length,
                min: Math.min.apply(null, values),
                max: Math.max.apply(null, values)
            };
        }

        function shortCrudDetailLabel(label, maxLen) {
            var text = String(label || '').replace(/\s+/g, ' ').trim();
            if (text.length <= maxLen) {
                return text;
            }
            return text.slice(0, maxLen) + '...';
        }

        function formatCrudNumber(value, digits) {
            if (value === null || !isFinite(value)) {
                return 'N/A';
            }
            return Number(value).toLocaleString('fr-FR', {
                minimumFractionDigits: digits || 0,
                maximumFractionDigits: digits || 0
            });
        }

        function formatCrudDelta(value) {
            if (!isFinite(value)) {
                return 'N/A';
            }
            var rounded = Math.round(value * 10) / 10;
            return (rounded > 0 ? '+' : '') + formatCrudNumber(rounded, 1) + '%';
        }

        function clampCrudPercent(value) {
            var numeric = Number(value);
            if (!isFinite(numeric)) {
                return 0;
            }
            if (numeric < 0) {
                return 0;
            }
            if (numeric > 100) {
                return 100;
            }
            return Math.round(numeric);
        }

        function createCrudDoughnutChart(labels, values, colors) {
            return {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: colors,
                        borderWidth: 2,
                        borderColor: ['#ffffff', '#ffffff', '#ffffff', '#ffffff']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutoutPercentage: 68,
                    legend: { position: 'bottom' }
                }
            };
        }

        function createCrudMetricBarChart(labels, values, seriesName) {
            return {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: seriesName || 'Valeur',
                        data: values,
                        backgroundColor: ['#1d4ed8', '#93c5fd']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    legend: { display: false },
                    scales: {
                        yAxes: [{
                            ticks: { beginAtZero: true },
                            gridLines: { color: 'rgba(148, 163, 184, 0.25)' }
                        }],
                        xAxes: [{ gridLines: { display: false } }]
                    }
                }
            };
        }

        function createCrudPercentBarChart(labels, values, seriesName) {
            return {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: seriesName || 'Taux',
                        data: values,
                        backgroundColor: '#1d4ed8'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    legend: { display: false },
                    scales: {
                        yAxes: [{
                            ticks: {
                                beginAtZero: true,
                                max: 100,
                                callback: function(value) { return value + '%'; }
                            },
                            gridLines: { color: 'rgba(148, 163, 184, 0.25)' }
                        }],
                        xAxes: [{ gridLines: { display: false } }]
                    }
                }
            };
        }

        function createCrudRadarChart(labels, values, seriesName) {
            return {
                type: 'radar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: seriesName || 'Indice',
                        data: values,
                        backgroundColor: 'rgba(37, 99, 235, 0.20)',
                        borderColor: '#1d4ed8',
                        pointBackgroundColor: '#1d4ed8',
                        pointBorderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    legend: { display: false },
                    scale: {
                        ticks: {
                            beginAtZero: true,
                            max: 100
                        }
                    }
                }
            };
        }

        function submitCrudDetailSave() {
            if (!crudDetailState.$row || !crudDetailState.$row.length || !crudDetailState.config) {
                return;
            }

            var config = crudDetailState.config;
            var rowId = parseInt(String(crudDetailState.$row.attr('data-row-id') || ''), 10);
            if (!rowId || !config.updateToken) {
                return;
            }

            var payload = { _token: config.updateToken };
            var hasError = false;

            $('#crudDetailEditFields').find('.js-crud-detail-input').each(function() {
                var $input = $(this);
                var $fieldCard = $input.closest('.crud-detail-edit-field');
                var field = String($input.data('field') || '').trim();
                var type = String($input.data('type') || 'string').toLowerCase();
                var required = $input.prop('required');
                var value = String($input.val() || '').trim();

                $input.removeClass('is-invalid');
                $fieldCard.removeClass('is-invalid');

                if (required && value === '') {
                    hasError = true;
                    $input.addClass('is-invalid');
                    $fieldCard.addClass('is-invalid');
                    return;
                }
                if (type === 'digits' && value !== '' && !/^\d+$/.test(value)) {
                    hasError = true;
                    $input.addClass('is-invalid');
                    $fieldCard.addClass('is-invalid');
                    return;
                }
                if ((type === 'int' || type === 'number') && value !== '' && !/^-?\d+$/.test(value)) {
                    hasError = true;
                    $input.addClass('is-invalid');
                    $fieldCard.addClass('is-invalid');
                    return;
                }
                if (type === 'float' && value !== '' && !/^-?\d+(?:[.,]\d+)?$/.test(value)) {
                    hasError = true;
                    $input.addClass('is-invalid');
                    $fieldCard.addClass('is-invalid');
                    return;
                }
                payload[field] = value;
            });

            if (hasError) {
                showToast('Merci de verifier les champs obligatoires et numeriques.', 'error');
                $('#crudDetailEditFields').find('.js-crud-detail-input.is-invalid').first().trigger('focus');
                return;
            }

            $('#crudDetailSaveBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Enregistrement...');

            $.ajax({
                url: '/api/crud/' + encodeURIComponent(config.resource) + '/' + rowId + '/update',
                method: 'POST',
                dataType: 'json',
                data: payload
            }).done(function(response) {
                if (!response || response.success !== true) {
                    showToast((response && response.message) ? response.message : 'Mise a jour impossible.', 'error');
                    return;
                }

                applyInlineSavedValues(crudDetailState.$row, crudDetailState.editableFields, payload, config);
                var updatedData = buildCrudDetailRowData(crudDetailState.$table, crudDetailState.$row, crudDetailState.editableFields, String(config.placeholder || '____'), String(config.resource || ''));
                renderCrudDetailView(updatedData);
                renderCrudDetailEdit(updatedData, true);
                destroyCrudDetailCharts();
                renderCrudDetailCharts(updatedData);
                setCrudDetailMode('view', true);
                showToast(response.message || 'Element mis a jour.', 'success');
            }).fail(function(xhr) {
                var message = 'Mise a jour impossible.';
                if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                showToast(message, 'error');
            }).always(function() {
                $('#crudDetailSaveBtn').prop('disabled', false).html('<i class="fas fa-save mr-1"></i>Enregistrer');
            });
        }

        function beginInlineEdit($row, editableFields, config) {
            var snapshot = {};
            var values = {};

            editableFields.forEach(function(fieldMeta) {
                var $cell = $row.find('td[data-field="' + fieldMeta.field + '"]').first();
                if ($cell.length === 0) {
                    return;
                }

                var currentValue = String($cell.data('fieldValue') || '').trim();
                if (currentValue === '') {
                    currentValue = normalizeDisplayValue($cell.text(), config.placeholder);
                }

                snapshot[fieldMeta.field] = {
                    html: $cell.html(),
                    value: currentValue
                };
                values[fieldMeta.field] = currentValue;

                $cell.html(buildInlineEditorHtml(fieldMeta, currentValue, config.placeholder || '____'));
            });

            var $actionsCell = findActionsCell($row);
            if ($actionsCell.length > 0) {
                $actionsCell.children().addClass('d-none js-inline-hidden-action');
                $actionsCell.append(
                    '<span class="js-inline-actions">' +
                        '<button type="button" class="btn btn-action btn-view js-inline-save-btn" title="Enregistrer"><i class="fas fa-check"></i></button>' +
                        '<button type="button" class="btn btn-action btn-delete js-inline-cancel-btn" title="Annuler"><i class="fas fa-times"></i></button>' +
                    '</span>'
                );
            }

            setRowSelectionAvailability($row, false);
            $row
                .addClass('js-inline-editing')
                .data('inlineSnapshot', snapshot)
                .data('inlineValues', values);
            focusInlineEditor($row.find('.js-inline-editor').first());
        }

        function saveInlineEdit($row, editableFields, config) {
            if ($row.length === 0 || !$row.hasClass('js-inline-editing')) {
                return;
            }

            var id = parseInt($row.data('rowId'), 10);
            if (isNaN(id) || id <= 0) {
                showToast('Ligne invalide.', 'error');
                return;
            }

            var payload = { _token: config.updateToken };
            var hasError = false;

            editableFields.forEach(function(fieldMeta) {
                var $input = $row.find('.js-inline-editor[data-field="' + fieldMeta.field + '"]');
                if ($input.length === 0) {
                    return;
                }

                var value = normalizeInlineEditorValue($input);
                if (fieldMeta.required && value === '') {
                    hasError = true;
                    $input.addClass('is-invalid');
                    return;
                }
                if (String(fieldMeta.type || '').toLowerCase() === 'digits' && value !== '' && !/^\d+$/.test(value)) {
                    hasError = true;
                    $input.addClass('is-invalid');
                    return;
                }

                $input.removeClass('is-invalid');
                payload[fieldMeta.field] = value;
            });

            if (hasError) {
                showToast('Merci de verifier les champs obligatoires et numeriques.', 'error');
                return;
            }

            $row.addClass('js-inline-saving');

            $.ajax({
                url: '/api/crud/' + encodeURIComponent(config.resource) + '/' + id + '/update',
                method: 'POST',
                dataType: 'json',
                data: payload
            }).done(function(response) {
                if (!response || response.success !== true) {
                    showToast((response && response.message) ? response.message : 'Mise a jour impossible.', 'error');
                    $row.removeClass('js-inline-saving');
                    return;
                }

                applyInlineSavedValues($row, editableFields, payload, config);
                showToast(response.message || 'Element mis a jour.', 'success');
            }).fail(function(xhr) {
                var message = 'Mise a jour impossible.';
                if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                showToast(message, 'error');
                $row.removeClass('js-inline-saving');
            });
        }

        function cancelInlineEdit($row) {
            if ($row.length === 0 || !$row.hasClass('js-inline-editing')) {
                return;
            }

            var snapshot = $row.data('inlineSnapshot') || {};
            Object.keys(snapshot).forEach(function(fieldName) {
                var fieldSnapshot = snapshot[fieldName] || null;
                var $cell = $row.find('td[data-field="' + fieldName + '"]').first();
                if ($cell.length === 0 || !fieldSnapshot) {
                    return;
                }
                $cell.html(fieldSnapshot.html || '');
            });

            var $actionsCell = findActionsCell($row);
            $actionsCell.find('.js-inline-actions').remove();
            $actionsCell.find('.js-inline-hidden-action').removeClass('d-none js-inline-hidden-action');

            setRowSelectionAvailability($row, true);
            $row
                .removeClass('js-inline-editing js-inline-saving')
                .removeData('inlineSnapshot')
                .removeData('inlineValues');
        }

        function applyInlineSavedValues($row, editableFields, payload, config) {
            editableFields.forEach(function(fieldMeta) {
                var fieldName = String(fieldMeta.field || '').trim();
                if (fieldName === '') {
                    return;
                }

                var $cell = $row.find('td[data-field="' + fieldName + '"]').first();
                if ($cell.length === 0) {
                    return;
                }

                var value = String(payload[fieldName] || '').trim();
                $cell.attr('data-field-value', value);
                if (isSelectEditorType(fieldMeta.type)) {
                    var label = value;
                    var options = Array.isArray(fieldMeta.options) ? fieldMeta.options : [];
                    for (var i = 0; i < options.length; i += 1) {
                        var option = options[i] || {};
                        if (String(option.value || '').trim() === value) {
                            label = String(option.label || value).trim();
                            break;
                        }
                    }
                    $cell.text(label === '' ? String(config.placeholder || '____') : label);
                } else {
                    $cell.text(value === '' ? String(config.placeholder || '____') : value);
                }
            });

            var $actionsCell = findActionsCell($row);
            $actionsCell.find('.js-inline-actions').remove();
            $actionsCell.find('.js-inline-hidden-action').removeClass('d-none js-inline-hidden-action');

            setRowSelectionAvailability($row, true);
            $row
                .removeClass('js-inline-editing js-inline-saving')
                .removeData('inlineSnapshot')
                .removeData('inlineValues');
        }

        function normalizeInlineEditorValue($editor) {
            if ($editor.hasClass('inline-dropdown-shell')) {
                return String($editor.attr('data-value') || '').trim();
            }

            if ($editor.is('select')) {
                return String($editor.val() || '').trim();
            }

            if ($editor.is('input, textarea')) {
                return String($editor.val() || '').trim();
            }

            var placeholder = String($editor.attr('data-placeholder') || '____').trim();
            var value = String($editor.text() || '')
                .replace(/\u00a0/g, ' ')
                .replace(/\s+/g, ' ')
                .trim();

            if (value === placeholder) {
                return '';
            }

            return value;
        }

        function focusInlineEditor($editor) {
            if (!$editor || $editor.length === 0) {
                return;
            }

            if ($editor.hasClass('inline-dropdown-shell')) {
                openInlineDropdownShell($editor.closest('table'), $editor);
                $editor.find('.inline-dropdown-trigger').trigger('focus');
                return;
            }

            if ($editor.is('select, input, textarea')) {
                $editor.trigger('focus');
                return;
            }

            var node = $editor.get(0);
            if (!node) {
                return;
            }

            $editor.trigger('focus');

            var selection = window.getSelection ? window.getSelection() : null;
            if (!selection || !document.createRange) {
                return;
            }

            var range = document.createRange();
            range.selectNodeContents(node);
            range.collapse(false);
            selection.removeAllRanges();
            selection.addRange(range);
        }

        function cancelAllEditingRows($table) {
            $table.find('tbody tr.js-inline-editing').each(function() {
                cancelInlineEdit($(this));
            });
        }

        function findActionsCell($row) {
            var $cell = $row.find('td[data-role="actions"]').first();
            if ($cell.length > 0) {
                return $cell;
            }

            var $editButton = $row.find('.btn-edit').first();
            return $editButton.length > 0 ? $editButton.closest('td') : $row.find('td').last();
        }

        function getEditableFields($table) {
            var fields = [];
            var fieldMap = {};

            $table.find('thead th[data-field]').each(function() {
                var $th = $(this);
                var field = String($th.data('field') || '').trim();
                if (field === '') {
                    return;
                }

                var meta = {
                    field: field,
                    label: String($th.text() || field).trim(),
                    required: String($th.data('required') || '') === '1',
                    type: String($th.data('type') || 'string').trim()
                };

                if (isSelectEditorType(meta.type)) {
                    meta.options = collectFieldOptions($table, field, String($table.data('placeholder') || '____'));
                }

                fields.push(meta);
                fieldMap[field] = meta;
            });

            $table.find('tbody tr').first().find('td[data-field]').each(function() {
                var $cell = $(this);
                var field = String($cell.data('field') || '').trim();
                if (field === '' || fieldMap[field]) {
                    return;
                }

                var columnKey = String($cell.data('columnKey') || '').trim();
                var $header = columnKey !== '' ? $table.find('thead th[data-column-key="' + columnKey + '"]').first() : $();
                var inferredType = $header.length > 0 ? String($header.data('type') || 'string').trim() : 'string';
                var inferredRequired = $header.length > 0 ? String($header.data('required') || '') === '1' : false;
                var inferredLabel = $header.length > 0 ? String($header.text() || field).trim() : field;

                var inferredMeta = {
                    field: field,
                    label: inferredLabel,
                    required: inferredRequired,
                    type: inferredType
                };

                if (isSelectEditorType(inferredMeta.type)) {
                    inferredMeta.options = collectFieldOptions($table, field, String($table.data('placeholder') || '____'));
                }

                fields.push(inferredMeta);
                fieldMap[field] = inferredMeta;
            });

            return fields;
        }

        function buildInlineEditorHtml(fieldMeta, currentValue, placeholder) {
            var normalizedType = String(fieldMeta.type || 'string').toLowerCase();
            var escapedField = escapeHtml(String(fieldMeta.field || '').trim());
            var escapedType = escapeHtml(normalizedType);
            var safePlaceholder = String(placeholder || '____');

            if (isSelectEditorType(normalizedType)) {
                var options = Array.isArray(fieldMeta.options) ? fieldMeta.options.slice() : [];
                var optionMap = {};
                options.forEach(function(option) {
                    var key = String((option && option.value) || '').trim().toLowerCase();
                    if (key !== '') {
                        optionMap[key] = true;
                    }
                });

                var currentKey = String(currentValue || '').trim().toLowerCase();
                if (currentKey !== '' && !optionMap[currentKey]) {
                    options.push({ value: currentValue, label: currentValue });
                }

                var selectedValue = String(currentValue || '').trim();
                var selectedLabel = selectedValue;
                for (var i = 0; i < options.length; i += 1) {
                    var currentOption = options[i] || {};
                    if (String(currentOption.value || '').trim() === selectedValue) {
                        selectedLabel = String(currentOption.label || selectedValue).trim();
                        break;
                    }
                }

                var html =
                    '<div class="inline-dropdown-shell js-inline-editor" data-field="' + escapedField + '" data-type="' + escapedType + '" data-value="' + escapeHtml(selectedValue) + '">' +
                        '<span class="inline-dropdown-label">' + escapeHtml(selectedLabel === '' ? safePlaceholder : selectedLabel) + '</span>' +
                        '<button type="button" class="inline-dropdown-trigger" aria-label="Ouvrir la liste"><i class="fas fa-chevron-down"></i></button>' +
                        '<div class="inline-dropdown-menu">';

                var emptyActive = selectedValue === '' ? ' is-active' : '';
                html += '<button type="button" class="inline-dropdown-option' + emptyActive + '" data-value="" data-label="' + escapeHtml(safePlaceholder) + '">' + escapeHtml(safePlaceholder) + '</button>';
                options.forEach(function(option) {
                    var value = String((option && option.value) || '').trim();
                    var label = String((option && option.label) || value).trim();
                    if (value === '') {
                        return;
                    }
                    var isActiveClass = value === selectedValue ? ' is-active' : '';
                    html += '<button type="button" class="inline-dropdown-option' + isActiveClass + '" data-value="' + escapeHtml(value) + '" data-label="' + escapeHtml(label) + '">' + escapeHtml(label) + '</button>';
                });

                html += '</div></div>';
                return html;
            }

            return '<span class="js-inline-editor" contenteditable="true" spellcheck="false" data-field="' + escapedField + '" data-type="' + escapedType + '" data-placeholder="' + escapeHtml(safePlaceholder) + '">' +
                escapeHtml(currentValue) +
            '</span>';
        }

        function isSelectEditorType(type) {
            var normalized = String(type || '').toLowerCase().trim();
            return normalized === 'entity' || normalized === 'enum' || normalized === 'select';
        }

        function getCrudDetailFieldTypeLabel(type) {
            var normalized = String(type || '').toLowerCase().trim();
            if (normalized === 'digits') {
                return 'Numerique';
            }
            if (normalized === 'int' || normalized === 'number') {
                return 'Entier';
            }
            if (normalized === 'float') {
                return 'Decimal';
            }
            if (normalized === 'date' || normalized === 'datetime') {
                return 'Date';
            }
            if (isSelectEditorType(normalized)) {
                return 'Liste';
            }
            return 'Texte';
        }

        function getCrudDetailFieldHint(type) {
            var normalized = String(type || '').toLowerCase().trim();
            if (normalized === 'digits') {
                return 'Utilisez uniquement les chiffres 0-9.';
            }
            if (normalized === 'int' || normalized === 'number') {
                return 'Valeurs entieres autorisees.';
            }
            if (normalized === 'float') {
                return 'Valeurs decimales autorisees.';
            }
            if (isSelectEditorType(normalized)) {
                return 'Choisissez une valeur dans la liste.';
            }
            return 'Saisissez une valeur conforme au champ.';
        }

        function collectFieldOptions($table, fieldName, placeholder) {
            var options = [];
            var valueMap = {};
            var ph = String(placeholder || '____').trim().toLowerCase();

            $table.find('tbody td[data-field="' + fieldName + '"]').each(function() {
                var $cell = $(this);
                var rawValue = String($cell.attr('data-field-value') || '').trim();
                var label = String($cell.text() || '').replace(/\s+/g, ' ').trim();

                if (rawValue === '') {
                    rawValue = normalizeDisplayValue(label, placeholder);
                }

                if (label === '' || label.toLowerCase() === ph) {
                    label = rawValue;
                }

                var normalized = String(rawValue || '').trim();
                if (normalized === '' || normalized.toLowerCase() === ph) {
                    return;
                }

                var key = normalized.toLowerCase();
                if (valueMap[key]) {
                    return;
                }
                valueMap[key] = true;
                options.push({ value: normalized, label: label || normalized });
            });

            options.sort(function(a, b) {
                return String(a.label || '').localeCompare(String(b.label || ''), 'fr');
            });

            return options;
        }

        function isInlineEditEnabled($table) {
            var rawAttr = String($table.attr('data-enable-inline-edit') || '').trim();
            if (rawAttr === '') {
                return true;
            }

            var normalized = rawAttr.toLowerCase();
            if (normalized === '0' || normalized === 'false' || normalized === 'off' || normalized === 'no') {
                return false;
            }
            return true;
        }

        function openInlineDropdownShell($table, $shell) {
            if (!$shell || $shell.length === 0) {
                return;
            }

            var $ctxTable = $table && $table.length ? $table : $shell.closest('table');
            if ($ctxTable && $ctxTable.length) {
                $ctxTable.find('.inline-dropdown-shell.is-open').not($shell).removeClass('is-open');
            }
            $shell.addClass('is-open');
        }

        function setInlineDropdownValue($shell, value, label) {
            if (!$shell || $shell.length === 0) {
                return;
            }

            var normalizedValue = String(value || '');
            var normalizedLabel = String(label || '');
            $shell.attr('data-value', normalizedValue);
            $shell.find('.inline-dropdown-label').text(normalizedLabel);
            $shell.find('.inline-dropdown-option').removeClass('is-active');
            $shell.find('.inline-dropdown-option').filter(function() {
                return String($(this).attr('data-value') || '') === normalizedValue;
            }).first().addClass('is-active');
        }

        function setupQuickCreateTrigger($table, config) {
            var editableFields = getEditableFields($table);
            if (editableFields.length === 0) {
                return;
            }

            var $actionBar = resolveActionBar($table);
            var $actionBarButtons = $actionBar.find('.action-buttons').first();
            if ($actionBarButtons.length === 0) {
                return;
            }

            var $existingQuickButton = findQuickCreateButton($actionBarButtons);
            if ($existingQuickButton.length > 0) {
                bindQuickCreateButton($existingQuickButton, config, editableFields);
                return;
            }

            var $button = $(
                '<button type="button" class="btn btn-sm btn-outline-primary ml-2 no-loading js-open-quick-create" data-resource="' + config.resource + '">' +
                    '<i class="fas fa-bolt mr-1"></i>Creation rapide' +
                '</button>'
            );
            $actionBarButtons.append($button);
            bindQuickCreateButton($button, config, editableFields);
        }

        function openQuickCreateModal(config, editableFields) {
            var $modal = $('#crudQuickCreateModal');
            var $title = $modal.find('.modal-title');
            var $fieldsWrap = $modal.find('.js-quick-create-fields');
            var $submit = $modal.find('.js-quick-create-submit');

            $title.text('Creation rapide');
            $fieldsWrap.empty();

            editableFields.forEach(function(fieldMeta) {
                var normalizedType = String(fieldMeta.type || 'string').toLowerCase();
                var inputType = (normalizedType === 'int' || normalizedType === 'number' || normalizedType === 'float') ? 'number' : 'text';
                var requiredHtml = fieldMeta.required ? ' <span class="text-danger">*</span>' : '';
                var requiredAttr = fieldMeta.required ? ' required' : '';
                var extraAttrs = '';
                if (normalizedType === 'digits') {
                    extraAttrs = ' inputmode="numeric" pattern="[0-9]*"';
                } else if (normalizedType === 'float') {
                    extraAttrs = ' step="any"';
                }

                var controlHtml = '';
                if (isSelectEditorType(normalizedType)) {
                    controlHtml = '<select class="form-control js-quick-create-input" data-field="' + escapeHtml(fieldMeta.field) + '" data-type="' + escapeHtml(normalizedType) + '"' + requiredAttr + '>' +
                        '<option value="">' + escapeHtml(config.placeholder || '____') + '</option>';

                    var selectOptions = Array.isArray(fieldMeta.options) ? fieldMeta.options : [];
                    selectOptions.forEach(function(option) {
                        var optionValue = String((option && option.value) || '').trim();
                        if (optionValue === '') {
                            return;
                        }
                        var optionLabel = String((option && option.label) || optionValue).trim();
                        controlHtml += '<option value="' + escapeHtml(optionValue) + '">' + escapeHtml(optionLabel) + '</option>';
                    });
                    controlHtml += '</select>';
                } else {
                    controlHtml = '<input type="' + inputType + '" class="form-control js-quick-create-input" data-field="' + escapeHtml(fieldMeta.field) + '" data-type="' + escapeHtml(normalizedType) + '"' + requiredAttr + extraAttrs + '>';
                }

                var fieldHtml =
                    '<div class="form-group">' +
                        '<label>' + escapeHtml(fieldMeta.label) + requiredHtml + '</label>' +
                        controlHtml +
                    '</div>';

                $fieldsWrap.append(fieldHtml);
            });

            $submit.off('click').on('click', function() {
                var payload = { _token: config.createToken };
                var hasError = false;

                $fieldsWrap.find('.js-quick-create-input').each(function() {
                    var $input = $(this);
                    var field = String($input.data('field') || '').trim();
                    var value = String($input.val() || '').trim();
                    var required = $input.prop('required');
                    var inputType = String($input.data('type') || 'string').toLowerCase();

                    if (required && value === '') {
                        $input.addClass('is-invalid');
                        hasError = true;
                        return;
                    }

                    if (inputType === 'digits' && value !== '' && !/^\d+$/.test(value)) {
                        $input.addClass('is-invalid');
                        hasError = true;
                        return;
                    }

                    if ((inputType === 'int' || inputType === 'number') && value !== '' && !/^-?\d+$/.test(value)) {
                        $input.addClass('is-invalid');
                        hasError = true;
                        return;
                    }

                    if (inputType === 'float' && value !== '' && !/^-?\d+(?:[.,]\d+)?$/.test(value)) {
                        $input.addClass('is-invalid');
                        hasError = true;
                        return;
                    }

                    $input.removeClass('is-invalid');
                    payload[field] = value;
                });

                if (hasError) {
                    showToast('Merci de renseigner les champs obligatoires.', 'error');
                    return;
                }

                $submit.prop('disabled', true);

                $.ajax({
                    url: '/api/crud/' + encodeURIComponent(config.resource) + '/create',
                    method: 'POST',
                    dataType: 'json',
                    data: payload
                }).done(function(response) {
                    if (!response || response.success !== true) {
                        showToast((response && response.message) ? response.message : 'Creation impossible.', 'error');
                        $submit.prop('disabled', false);
                        return;
                    }

                    queueToastForReload('success', response.message || 'Element cree avec succes.');
                    $modal.modal('hide');
                    window.location.reload();
                }).fail(function(xhr) {
                    var message = 'Creation impossible.';
                    if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }
                    showToast(message, 'error');
                    $submit.prop('disabled', false);
                });
            });

            $modal.off('hidden.bs.modal.crudQuickCreate').on('hidden.bs.modal.crudQuickCreate', function() {
                $submit.prop('disabled', false);
            });

            $modal.modal('show');
            window.setTimeout(function() {
                $fieldsWrap.find('input').first().trigger('focus');
            }, 120);
        }

        function setupImportTrigger($table, config) {
            var $actionBar = resolveActionBar($table);
            var $actionBarButtons = $actionBar.find('.action-buttons').first();
            if ($actionBarButtons.length === 0) {
                return;
            }

            var $existingImportButton = findImportButton($actionBarButtons);
            if ($existingImportButton.length > 0) {
                bindImportButton($existingImportButton, config);
                return;
            }

            var $button = $(
                '<button type="button" class="btn btn-sm btn-outline-primary ml-2 no-loading js-open-import" data-resource="' + config.resource + '">' +
                    '<i class="fas fa-file-import mr-1"></i>Importer' +
                '</button>'
            );
            $actionBarButtons.append($button);
            bindImportButton($button, config);
        }

        function resolveActionBar($table) {
            return $table.closest('.table-container').prevAll('.action-bar').first();
        }

        function findQuickCreateButton($actionBarButtons) {
            var $explicitQuickButton = $actionBarButtons.find('[data-crud-action="quick-create"]').first();
            if ($explicitQuickButton.length > 0) {
                return $explicitQuickButton;
            }

            return $actionBarButtons.find('.btn-add').first();
        }

        function findImportButton($actionBarButtons) {
            var $explicitImportButton = $actionBarButtons.find('[data-crud-action="import"]').first();
            if ($explicitImportButton.length > 0) {
                return $explicitImportButton;
            }

            return $actionBarButtons.find('a, button').filter(function() {
                return $(this).find('.fa-file-import').length > 0;
            }).first();
        }

        function bindQuickCreateButton($button, config, editableFields) {
            if (!$button || $button.length === 0) {
                return;
            }

            if ($button.attr('data-crud-quick-bound') === '1') {
                return;
            }

            $button
                .attr('data-crud-quick-bound', '1')
                .attr('data-crud-action', 'quick-create')
                .removeAttr('data-toggle')
                .removeAttr('data-target')
                .off('click.crudQuickCreate')
                .on('click.crudQuickCreate', function(event) {
                    event.preventDefault();
                    openQuickCreateModal(config, editableFields);
                });
        }

        function bindImportButton($button, config) {
            if (!$button || $button.length === 0) {
                return;
            }

            if ($button.attr('data-crud-import-bound') === '1') {
                return;
            }

            $button
                .attr('data-crud-import-bound', '1')
                .attr('data-crud-action', 'import')
                .removeAttr('data-toggle')
                .removeAttr('data-target')
                .off('click.crudImport')
                .on('click.crudImport', function(event) {
                    event.preventDefault();
                    openImportModal(config);
                });
        }

        function openImportModal(config) {
            var $modal = $('#crudImportModal');
            var $fileInput = $modal.find('input[name="file"]');
            var $submit = $modal.find('.js-import-submit');

            $fileInput.val('');
            $submit.prop('disabled', false);

            $submit.off('click').on('click', function() {
                var file = $fileInput[0] && $fileInput[0].files ? $fileInput[0].files[0] : null;
                if (!file) {
                    showToast('Selectionnez un fichier a importer.', 'error');
                    return;
                }

                var formData = new FormData();
                formData.append('_token', config.importToken);
                formData.append('file', file);

                $submit.prop('disabled', true);

                $.ajax({
                    url: '/api/crud/' + encodeURIComponent(config.resource) + '/import',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false
                }).done(function(response) {
                    if (!response || response.success !== true) {
                        showToast((response && response.message) ? response.message : 'Import impossible.', 'error');
                        $submit.prop('disabled', false);
                        return;
                    }

                    queueToastForReload('success', response.message || 'Import termine.');
                    $modal.modal('hide');
                    window.location.reload();
                }).fail(function(xhr) {
                    var message = 'Import impossible.';
                    if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }
                    showToast(message, 'error');
                    $submit.prop('disabled', false);
                });
            });

            $modal.modal('show');
        }

        function normalizeDisplayValue(value, placeholder) {
            var normalized = String(value || '').trim();
            if (normalized === '' || normalized === placeholder) {
                return '';
            }
            return normalized;
        }

    function getActiveEditingRow($table) {
        if (!$table || $table.length === 0) {
            return $();
        }

        return $table.find('tbody tr.js-inline-editing').first();
    }

    function hasUnsavedChanges($row, editableFields, config) {
        if (!$row || $row.length === 0 || !$row.hasClass('js-inline-editing')) {
            return false;
        }

        var snapshot = $row.data('inlineValues') || {};
        var fields = Array.isArray(editableFields) ? editableFields : [];
        if (fields.length === 0) {
            fields = Object.keys(snapshot).map(function(fieldName) {
                return { field: fieldName };
            });
        }

        for (var i = 0; i < fields.length; i += 1) {
            var fieldMeta = fields[i] || {};
            var fieldName = String(fieldMeta.field || '').trim();
            if (fieldName === '') {
                continue;
            }

            var $editor = $row.find('.js-inline-editor[data-field="' + fieldName + '"]').first();
            if ($editor.length === 0) {
                continue;
            }

            var currentValue = normalizeInlineEditorValue($editor);
            var originalValue = String(snapshot[fieldName] || '');
            if (String(currentValue) !== String(originalValue)) {
                return true;
            }
        }

        return false;
    }

    function setRowSelectionAvailability($row, enabled) {
        if (!$row || $row.length === 0) {
            return;
        }

        var $table = $row.closest('table');
        var $checkbox = $row.find('.js-row-selector, [data-row-checkbox]').first();
        if ($checkbox.length === 0) {
            return;
        }

        if (!enabled) {
            $checkbox.prop('checked', false);
            $checkbox.prop('disabled', true);
            $row.removeClass('table-active is-selected');
        } else {
            $checkbox.prop('disabled', false);
        }

        var $bulkBar = buildOrGetBulkBar($table, resolveTableId($table, String($table.data('crudResource') || '')));
        refreshBulkState($table, $bulkBar);
    }

    function isAllowedDigitKey(event) {
        if (!event) {
            return true;
        }

        if (event.ctrlKey || event.metaKey || event.altKey) {
            return true;
        }

        var key = String(event.key || '');
        if (key === '') {
            return true;
        }

        var allowedKeys = [
            'Backspace',
            'Delete',
            'ArrowLeft',
            'ArrowRight',
            'ArrowUp',
            'ArrowDown',
            'Home',
            'End',
            'Tab',
            'Escape',
            'Enter'
        ];
        if (allowedKeys.indexOf(key) >= 0) {
            return true;
        }

        return /^[0-9]$/.test(key);
    }

    function insertTextAtCursor(text) {
        var safeText = String(text || '');
        if (safeText === '') {
            return;
        }

        if (document.queryCommandSupported && document.queryCommandSupported('insertText')) {
            document.execCommand('insertText', false, safeText);
            return;
        }

        var selection = window.getSelection ? window.getSelection() : null;
        if (!selection || selection.rangeCount === 0) {
            return;
        }

        selection.deleteFromDocument();
        selection.getRangeAt(0).insertNode(document.createTextNode(safeText));
        selection.collapseToEnd();
    }

    function sanitizeEventNamespace(value) {
        return String(value || 'crudInline')
            .replace(/[^a-zA-Z0-9_]/g, '_')
            .replace(/^_+/, '')
            .replace(/_+$/, '') || 'crudInline';
    }

    function triggerDeferredInteraction($element) {
        if (!$element || $element.length === 0) {
            return;
        }

        var $target = $element.first();
        var node = $target.get(0);

        $target.data('skipUnsavedGuard', 1);

        if (node && typeof node.click === 'function') {
            node.click();
            return;
        }

        $target.trigger('click');
    }

    function ensureUnsavedChangesModal() {
        if ($('#crudUnsavedChangesModal').length > 0) {
            return;
        }

        $('body').append(
            '<div class="modal fade unsaved-changes-modal" id="crudUnsavedChangesModal" tabindex="-1" role="dialog" aria-hidden="true">' +
                '<div class="modal-dialog modal-dialog-centered" role="document">' +
                    '<div class="modal-content">' +
                        '<div class="modal-header">' +
                            '<h5 class="modal-title">Confirmer l action</h5>' +
                            '<button type="button" class="close" data-dismiss="modal" aria-label="Fermer">' +
                                '<span aria-hidden="true">&times;</span>' +
                            '</button>' +
                        '</div>' +
                        '<div class="modal-body">' +
                            '<p class="mb-0" id="crudUnsavedChangesMessage">Des modifications non enregistrees seront perdues. Continuer ?</p>' +
                        '</div>' +
                        '<div class="modal-footer">' +
                            '<button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>' +
                            '<button type="button" class="btn btn-primary" id="crudUnsavedChangesConfirmBtn">Continuer</button>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>'
        );
    }

    function bindUnsavedChangesConfirmHandler() {
        $(document).off('click.crudUnsavedConfirm', '#crudUnsavedChangesConfirmBtn');
        $(document).on('click.crudUnsavedConfirm', '#crudUnsavedChangesConfirmBtn', function(event) {
            event.preventDefault();

            var action = crudUnsavedConfirmAction;
            crudUnsavedConfirmAction = null;

            var $modal = $('#crudUnsavedChangesModal');
            if ($modal.length > 0) {
                $modal.modal('hide');
            }

            if (typeof action === 'function') {
                action();
            }
        });

        $('#crudUnsavedChangesModal').off('hidden.bs.modal.crudUnsavedConfirm');
        $('#crudUnsavedChangesModal').on('hidden.bs.modal.crudUnsavedConfirm', function() {
            crudUnsavedConfirmAction = null;
        });
    }

    function openUnsavedChangesDialog(onConfirm, message) {
        var $modal = $('#crudUnsavedChangesModal');
        var $message = $('#crudUnsavedChangesMessage');
        if ($modal.length === 0) {
            if (window.confirm(String(message || 'Des modifications non enregistrees seront perdues. Continuer ?'))) {
                if (typeof onConfirm === 'function') {
                    onConfirm();
                }
            }
            return;
        }

        crudUnsavedConfirmAction = typeof onConfirm === 'function' ? onConfirm : null;
        if ($message.length > 0) {
            $message.text(String(message || 'Des modifications non enregistrees seront perdues. Continuer ?'));
        }
        $modal.modal('show');
    }

    function bindCrudDetailModalHandlers() {
        $(document).off('click.crudDetailModeView', '#crudDetailModeViewBtn');
        $(document).on('click.crudDetailModeView', '#crudDetailModeViewBtn', function(event) {
            event.preventDefault();
            setCrudDetailMode('view', true);
        });

        $(document).off('click.crudDetailModeEdit', '#crudDetailModeEditBtn, #crudDetailSwitchToEditBtn');
        $(document).on('click.crudDetailModeEdit', '#crudDetailModeEditBtn, #crudDetailSwitchToEditBtn', function(event) {
            event.preventDefault();
            setCrudDetailMode('edit', true);
            window.setTimeout(function() {
                $('#crudDetailEditFields').find('.js-crud-detail-input').first().trigger('focus');
            }, 80);
        });

        $(document).off('click.crudDetailSave', '#crudDetailSaveBtn');
        $(document).on('click.crudDetailSave', '#crudDetailSaveBtn', function(event) {
            event.preventDefault();
            submitCrudDetailSave();
        });

        $('#crudDetailModal').off('hidden.bs.modal.crudDetail').on('hidden.bs.modal.crudDetail', function() {
            destroyCrudDetailCharts();
            crudDetailState.$table = null;
            crudDetailState.$row = null;
            crudDetailState.config = null;
            crudDetailState.editableFields = [];
            crudDetailState.mode = 'view';
            $('#crudDetailSaveBtn').prop('disabled', false).html('<i class="fas fa-save mr-1"></i>Enregistrer');
        });
    }

    function ensureCrudEnhancerModals() {
        if ($('#crudDetailModalStyles').length === 0) {
            $('head').append(
                '<style id="crudDetailModalStyles">' +
                '.crud-detail-modal .modal-dialog{max-width:1080px;}' +
                '.crud-detail-modal .modal-content{border:none;border-radius:14px;box-shadow:0 28px 56px rgba(15,23,42,.2);overflow:hidden;}' +
                '.crud-detail-modal .modal-header{border-bottom:1px solid #e2e8f0;padding:.9rem 1.2rem;background:linear-gradient(180deg,#fff 0%,#f8fbff 100%);}' +
                '.crud-detail-mode-switch{display:inline-flex;align-items:center;gap:.35rem;margin-left:auto;margin-right:.9rem;padding:.2rem;border-radius:999px;border:1px solid #dbe3ec;background:#f8fafc;}' +
                '.crud-detail-mode-btn{border:none;background:transparent;color:#475569;border-radius:999px;padding:.38rem .78rem;font-size:.78rem;font-weight:700;transition:all .2s ease;}' +
                '.crud-detail-mode-btn.is-active{color:#fff;background:linear-gradient(135deg,var(--theme-primary) 0%,var(--theme-secondary) 100%);box-shadow:0 10px 18px rgba(30,64,175,.2);}' +
                '.crud-detail-pane{display:none;}' +
                '.crud-detail-pane.is-active{display:block;}' +
                '.crud-detail-report-hero{padding:.95rem;border-radius:12px;border:1px solid #dbe3ec;background:linear-gradient(135deg,rgba(241,245,249,.92) 0%,rgba(255,255,255,.95) 70%);margin-bottom:.85rem;}' +
                '.crud-detail-report-title{margin:0;font-size:1.05rem;font-weight:800;color:#0f172a;}' +
                '.crud-detail-report-subtitle{margin:.25rem 0 0;color:#64748b;font-size:.85rem;line-height:1.45;}' +
                '.crud-detail-report-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.7rem;margin-bottom:.9rem;}' +
                '.crud-detail-stat-card{border:1px solid #dbe3ec;border-radius:10px;background:#fff;padding:.66rem .75rem;}' +
                '.crud-detail-stat-label{margin:0;color:#64748b;font-size:.73rem;font-weight:700;text-transform:uppercase;}' +
                '.crud-detail-stat-value{margin:.24rem 0 0;color:#0f172a;font-size:1.12rem;font-weight:800;}' +
                '.crud-detail-report-grid{display:grid;grid-template-columns:1fr 1fr;gap:.85rem;}' +
                '.crud-detail-report-card{border:1px solid #dbe3ec;border-radius:11px;background:#fff;padding:.75rem .8rem;min-width:0;}' +
                '.crud-detail-report-card h6{margin:0 0 .62rem;font-size:.82rem;font-weight:800;color:#1e293b;text-transform:uppercase;}' +
                '.crud-detail-report-list{margin:0;display:grid;grid-template-columns:minmax(120px,170px) minmax(0,1fr);gap:.42rem .7rem;}' +
                '.crud-detail-report-list dt{margin:0;font-size:.77rem;font-weight:700;color:#64748b;}' +
                '.crud-detail-report-list dd{margin:0;font-size:.82rem;color:#0f172a;word-break:break-word;}' +
                '.crud-detail-chart-wrap{height:220px;position:relative;}' +
                '.crud-detail-chart-wrap canvas{width:100%!important;height:100%!important;}' +
                '.crud-detail-edit-shell{background:linear-gradient(180deg,#f8fbff 0%,#ffffff 72%);border:1px solid #dbe3ec;border-radius:14px;padding:1rem .95rem;}' +
                '.crud-detail-edit-hero{display:flex;align-items:center;justify-content:space-between;gap:.8rem;flex-wrap:wrap;margin-bottom:.9rem;padding:.2rem .15rem;}' +
                '.crud-detail-edit-title{margin:0;color:#0f172a;font-size:.98rem;font-weight:800;}' +
                '.crud-detail-edit-subtitle{margin:.2rem 0 0;color:#64748b;font-size:.82rem;}' +
                '.crud-detail-edit-metrics{display:flex;gap:.45rem;flex-wrap:wrap;}' +
                '.crud-detail-edit-chip{display:inline-flex;align-items:center;gap:.2rem;background:#ffffff;border:1px solid #dbe3ec;border-radius:999px;padding:.28rem .55rem;color:#475569;font-size:.73rem;font-weight:600;}' +
                '.crud-detail-edit-chip strong{color:#0f172a;font-weight:800;}' +
                '.crud-detail-edit-grid{display:grid;grid-template-columns:1fr 1fr;gap:.78rem .9rem;max-height:50vh;overflow:auto;padding-right:.22rem;}' +
                '.crud-detail-edit-field{border:1px solid #dbe3ec;background:#ffffff;border-radius:12px;padding:.75rem .75rem .7rem;box-shadow:0 4px 12px rgba(15,23,42,.05);transition:border-color .2s ease,box-shadow .2s ease,transform .2s ease;}' +
                '.crud-detail-edit-field:focus-within{border-color:#60a5fa;box-shadow:0 10px 26px rgba(37,99,235,.15);transform:translateY(-1px);}' +
                '.crud-detail-edit-field.is-invalid{border-color:#fca5a5;box-shadow:0 0 0 1px rgba(220,38,38,.08);}' +
                '.crud-detail-edit-head{display:flex;align-items:flex-start;justify-content:space-between;gap:.45rem;margin-bottom:.46rem;}' +
                '.crud-detail-edit-label{display:block;margin:0;color:#0f172a;font-size:.82rem;font-weight:700;line-height:1.35;}' +
                '.crud-detail-edit-required{color:#dc2626;margin-left:.22rem;font-weight:800;}' +
                '.crud-detail-edit-badges{display:flex;gap:.3rem;flex-wrap:wrap;justify-content:flex-end;}' +
                '.crud-detail-edit-badge{display:inline-flex;align-items:center;background:#f8fafc;border:1px solid #dbe3ec;border-radius:999px;padding:.16rem .46rem;color:#64748b;font-size:.67rem;font-weight:700;text-transform:uppercase;letter-spacing:.02em;}' +
                '.crud-detail-edit-badge.is-required{background:#eff6ff;border-color:#bfdbfe;color:#1d4ed8;}' +
                '.crud-detail-edit-field .form-control{height:40px;border-radius:10px;border-color:#cbd5e1;font-size:.84rem;}' +
                '.crud-detail-edit-field .form-control:focus{border-color:#60a5fa;box-shadow:0 0 0 .2rem rgba(37,99,235,.12);}' +
                '.crud-detail-edit-hint{margin:.44rem 0 0;color:#64748b;font-size:.72rem;line-height:1.35;}' +
                '.crud-detail-edit-error{display:none;margin-top:.32rem;color:#b91c1c;font-size:.72rem;font-weight:600;}' +
                '.crud-detail-edit-field.is-invalid .crud-detail-edit-error{display:block;}' +
                '.crud-detail-edit-empty{padding:1.1rem 1rem;border:1px dashed #cbd5e1;border-radius:12px;background:#f8fafc;text-align:center;color:#475569;}' +
                '.crud-detail-edit-empty i{font-size:1.1rem;color:#64748b;display:block;margin-bottom:.4rem;}' +
                '.crud-detail-edit-empty h6{margin:0;color:#0f172a;font-size:.9rem;font-weight:800;}' +
                '.crud-detail-edit-empty p{margin:.3rem 0 0;font-size:.8rem;line-height:1.4;}' +
                '@media (max-width:768px){.crud-detail-report-stats{grid-template-columns:1fr;}.crud-detail-report-grid{grid-template-columns:1fr;}.crud-detail-report-list{grid-template-columns:1fr;}.crud-detail-edit-grid{grid-template-columns:1fr;max-height:none;padding-right:0;}.crud-detail-edit-shell{padding:.88rem .75rem;}}' +
                '</style>'
            );
        }

        if ($('#crudDetailModal').length === 0) {
            $('body').append(
                '<div class="modal fade crud-detail-modal" id="crudDetailModal" tabindex="-1" role="dialog" aria-hidden="true">' +
                    '<div class="modal-dialog modal-dialog-centered modal-lg" role="document">' +
                        '<div class="modal-content">' +
                            '<div class="modal-header">' +
                                '<h5 class="modal-title js-crud-detail-title">Details</h5>' +
                                '<div class="crud-detail-mode-switch">' +
                                    '<button type="button" class="crud-detail-mode-btn is-active" id="crudDetailModeViewBtn">Vue rapport</button>' +
                                    '<button type="button" class="crud-detail-mode-btn" id="crudDetailModeEditBtn">Edition</button>' +
                                '</div>' +
                                '<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>' +
                            '</div>' +
                            '<div class="modal-body">' +
                                '<div class="crud-detail-pane is-active" id="crudDetailViewPane">' +
                                    '<div class="crud-detail-report-hero"><h4 class="crud-detail-report-title js-crud-detail-subtitle">Element</h4><p class="crud-detail-report-subtitle">Rapport de synthese: informations principales et taux de completion.</p></div>' +
                                    '<div class="crud-detail-report-stats">' +
                                        '<div class="crud-detail-stat-card"><p class="crud-detail-stat-label" id="crudDetailStatLabel1">Completion</p><p class="crud-detail-stat-value" id="crudDetailStatValue1">0%</p></div>' +
                                        '<div class="crud-detail-stat-card"><p class="crud-detail-stat-label" id="crudDetailStatLabel2">Optionnels</p><p class="crud-detail-stat-value" id="crudDetailStatValue2">0/0</p></div>' +
                                        '<div class="crud-detail-stat-card"><p class="crud-detail-stat-label" id="crudDetailStatLabel3">Profil</p><p class="crud-detail-stat-value" id="crudDetailStatValue3">N/A</p></div>' +
                                    '</div>' +
                                    '<div class="crud-detail-report-grid">' +
                                        '<section class="crud-detail-report-card"><h6>Donnees</h6><dl class="crud-detail-report-list" id="crudDetailViewList"></dl></section>' +
                                        '<section class="crud-detail-report-card"><h6 id="crudDetailChartATitle">Completion</h6><div class="crud-detail-chart-wrap"><canvas id="crudDetailCompletionChart"></canvas></div></section>' +
                                        '<section class="crud-detail-report-card"><h6 id="crudDetailChartBTitle">Couverture des champs</h6><div class="crud-detail-chart-wrap"><canvas id="crudDetailProfileChart"></canvas></div></section>' +
                                    '</div>' +
                                '</div>' +
                                '<div class="crud-detail-pane" id="crudDetailEditPane">' +
                                    '<div class="crud-detail-edit-shell">' +
                                        '<div class="crud-detail-edit-hero">' +
                                            '<div>' +
                                                '<h6 class="crud-detail-edit-title">Edition directe</h6>' +
                                                '<p class="crud-detail-edit-subtitle">Mettez a jour les champs puis enregistrez sans quitter la fiche.</p>' +
                                            '</div>' +
                                            '<div class="crud-detail-edit-metrics" id="crudDetailEditMetrics"></div>' +
                                        '</div>' +
                                        '<div class="crud-detail-edit-grid" id="crudDetailEditFields"></div>' +
                                    '</div>' +
                                '</div>' +
                            '</div>' +
                            '<div class="modal-footer">' +
                                '<button type="button" class="btn btn-secondary" data-dismiss="modal">Fermer</button>' +
                                '<button type="button" class="btn btn-outline-primary" id="crudDetailSwitchToEditBtn"><i class="fas fa-pen mr-1"></i>Passer en edition</button>' +
                                '<button type="button" class="btn btn-primary" id="crudDetailSaveBtn"><i class="fas fa-save mr-1"></i>Enregistrer</button>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>'
            );
        }

        if ($('#crudQuickCreateModal').length === 0) {
            $('body').append(
                '<div class="modal fade crud-quick-modal" id="crudQuickCreateModal" tabindex="-1" role="dialog" aria-hidden="true">' +
                    '<div class="modal-dialog modal-dialog-centered" role="document">' +
                        '<div class="modal-content border-0 shadow-lg">' +
                            '<button type="button" class="close crud-quick-modal-close" data-dismiss="modal" aria-label="Close">' +
                                '<span aria-hidden="true">&times;</span>' +
                            '</button>' +
                            '<div class="crud-quick-modal-body">' +
                                '<div class="crud-quick-modal-badge">' +
                                    '<i class="fas fa-plus"></i>' +
                                '</div>' +
                                '<h3 class="crud-quick-modal-title modal-title">Creation rapide</h3>' +
                                '<p class="crud-quick-modal-subtitle">Renseignez uniquement les champs requis.</p>' +
                                '<div class="js-quick-create-fields"></div>' +
                                '<div class="crud-quick-modal-actions">' +
                                    '<button type="button" class="btn crud-quick-cancel" data-dismiss="modal">Annuler</button>' +
                                    '<button type="button" class="btn crud-quick-submit js-quick-create-submit">' +
                                        '<i class="fas fa-check mr-1"></i>Enregistrer' +
                                    '</button>' +
                                '</div>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>'
            );
        }

        if ($('#crudImportModal').length === 0) {
            $('body').append(
                '<div class="modal fade crud-quick-modal" id="crudImportModal" tabindex="-1" role="dialog" aria-hidden="true">' +
                    '<div class="modal-dialog modal-dialog-centered" role="document">' +
                        '<div class="modal-content border-0 shadow-lg">' +
                            '<button type="button" class="close crud-quick-modal-close" data-dismiss="modal" aria-label="Close">' +
                                '<span aria-hidden="true">&times;</span>' +
                            '</button>' +
                            '<div class="crud-quick-modal-body">' +
                                '<div class="crud-quick-modal-badge">' +
                                    '<i class="fas fa-file-import"></i>' +
                                '</div>' +
                                '<h3 class="crud-quick-modal-title modal-title">Importer des donnees</h3>' +
                                '<p class="crud-quick-modal-subtitle">Fichiers supportes: CSV, XLS, XLSX.</p>' +
                                '<div class="form-group mb-0">' +
                                    '<label>Fichier</label>' +
                                    '<input type="file" name="file" class="form-control-file" accept=".csv,.txt,.xls,.xlsx">' +
                                '</div>' +
                                '<div class="crud-quick-modal-actions">' +
                                    '<button type="button" class="btn crud-quick-cancel" data-dismiss="modal">Annuler</button>' +
                                    '<button type="button" class="btn crud-quick-submit js-import-submit">' +
                                        '<i class="fas fa-upload mr-1"></i>Importer' +
                                    '</button>' +
                                '</div>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>'
            );
        }
        }

        function showToast(message, type) {
            if (typeof window.showAppToast === 'function') {
                window.showAppToast({ message: message, type: type || 'info' });
                return;
            }
            window.alert(message);
        }

        function queueToastForReload(type, message) {
            if (typeof window.queueAppToastForReload === 'function') {
                window.queueAppToastForReload({ type: type || 'info', message: message });
                return;
            }
            showToast(message, type || 'info');
        }

        function escapeHtml(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }
})(window.jQuery);
