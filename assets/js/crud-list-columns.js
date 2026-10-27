/**
 * CRUD List Columns - Shared column toggle and resize system
 * Works with .js-crud-list-table tables and .crud-column-settings-floating gear buttons.
 */
(function($) {
    'use strict';

    if (!$) {
        return;
    }

    var isResizing = false;
    var $resizeTh = null;
    var resizeStartX = 0;
    var resizeStartWidth = 0;
    var resizeTableId = '';
    var globalHandlersBound = false;
    var MAX_VISIBLE_COLUMNS = 10;
    var LIMIT_EXCLUDED_TABLE_IDS = { pieces: true };

    function init() {
        initColumnMenus();
        initColumnResizing();
        applyColumnPreferences();
        applyColumnWidths();

        if (!globalHandlersBound) {
            bindGlobalMenuHandlers();
            bindColumnToggleHandler();
            globalHandlersBound = true;
        }
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function getStorageKey(tableId, suffix) {
        return 'crud_columns_' + tableId + '_' + suffix;
    }

    function isLimitEnabled(tableId, $table) {
        var normalizedTableId = String(tableId || '').trim().toLowerCase();
        if (normalizedTableId !== '' && LIMIT_EXCLUDED_TABLE_IDS[normalizedTableId]) {
            return false;
        }
        if ($table && $table.length && String($table.attr('data-disable-column-limit') || '').trim() === '1') {
            return false;
        }
        return true;
    }

    function getColumnPrefs(tableId) {
        try {
            return JSON.parse(localStorage.getItem(getStorageKey(tableId, 'visible')) || '{}');
        } catch (e) {
            return {};
        }
    }

    function setColumnPref(tableId, key, visible) {
        var prefs = getColumnPrefs(tableId);
        prefs[key] = visible;
        localStorage.setItem(getStorageKey(tableId, 'visible'), JSON.stringify(prefs));
    }

    function showToast(message, type) {
        if (typeof window.showAppToast === 'function') {
            window.showAppToast({ message: message, type: type || 'info' });
            return;
        }
        window.alert(String(message || ''));
    }

    function getMandatoryColumns($table) {
        var mandatory = Object.create(null);
        mandatory.selector = true;
        mandatory.id = true;
        mandatory.actions = true;

        $table.find('thead th[data-column-key][data-required="1"]').each(function() {
            var key = String($(this).data('columnKey') || '').trim();
            if (key !== '') {
                mandatory[key] = true;
            }
        });

        return mandatory;
    }

    function getAllOrderedColumnKeys($table) {
        var keys = [];
        $table.find('thead tr th[data-column-key]').each(function() {
            var key = String($(this).data('columnKey') || '').trim();
            if (key === '' || keys.indexOf(key) >= 0) {
                return;
            }
            keys.push(key);
        });
        return keys;
    }

    function getOptionalColumnKeysOrdered($table) {
        var mandatory = getMandatoryColumns($table);
        return getAllOrderedColumnKeys($table).filter(function(key) {
            return !mandatory[key];
        });
    }

    function getMandatoryVisibleCount($table) {
        var mandatory = getMandatoryColumns($table);
        return getAllOrderedColumnKeys($table).filter(function(key) {
            return !!mandatory[key];
        }).length;
    }

    function getOptionalVisibleSlots($table) {
        var tableId = String($table.data('tableId') || '').trim();
        if (!isLimitEnabled(tableId, $table)) {
            return Number.MAX_SAFE_INTEGER;
        }
        return Math.max(0, MAX_VISIBLE_COLUMNS - getMandatoryVisibleCount($table));
    }

    function enforceColumnVisibilityLimit($table, prefs) {
        var nextPrefs = $.extend({}, prefs || {});
        var optionalKeys = getOptionalColumnKeysOrdered($table);
        var optionalSlots = getOptionalVisibleSlots($table);
        var visibleCount = 0;

        optionalKeys.forEach(function(key) {
            var isVisible = nextPrefs.hasOwnProperty(key) ? !!nextPrefs[key] : true;
            if (!isVisible) {
                nextPrefs[key] = false;
                return;
            }

            if (visibleCount >= optionalSlots) {
                nextPrefs[key] = false;
                return;
            }

            nextPrefs[key] = true;
            visibleCount += 1;
        });

        return nextPrefs;
    }

    function countVisibleColumns($table, prefs) {
        var mandatory = getMandatoryColumns($table);
        var keys = getAllOrderedColumnKeys($table);
        var total = 0;

        keys.forEach(function(key) {
            if (mandatory[key]) {
                total += 1;
                return;
            }
            var visible = prefs.hasOwnProperty(key) ? !!prefs[key] : true;
            if (visible) {
                total += 1;
            }
        });

        return total;
    }

    function initColumnMenus() {
        $('table.js-crud-list-table[data-table-id]').each(function() {
            var $table = $(this);
            var tableId = String($table.data('tableId') || '').trim();
            if (tableId === '') {
                return;
            }

            var $container = $table.closest('.table-container');
            var $floating = $container.find('.crud-column-settings-floating').first();
            if ($floating.length === 0) {
                return;
            }

            var $trigger = $floating.find('.crud-column-settings__trigger').first();
            if ($trigger.length === 0) {
                return;
            }

            var $menu = $floating.find('.crud-column-settings__menu').first();
            if ($menu.length === 0) {
                $menu = $('<div class="crud-column-settings__menu"></div>');
                $floating.append($menu);
            }

            $floating.attr('data-table-id', tableId);
            $menu.attr('data-table-id', tableId);

            if ($menu.find('[data-column-toggle]').length === 0) {
                generateMenuItems($table, tableId, $menu);
            } else {
                syncMenuState($table, tableId, $menu);
            }
        });
    }

    function generateMenuItems($table, tableId, $menu) {
        var storedPrefs = getColumnPrefs(tableId);
        var prefs = enforceColumnVisibilityLimit($table, storedPrefs);
        var staticItems = [];
        var toggleItems = [];
        var mandatory = getMandatoryColumns($table);
        var optionalSlots = getOptionalVisibleSlots($table);
        var visibleOptionalCount = 0;

        $table.find('thead tr th[data-column-key]').each(function() {
            var $th = $(this);
            var key = String($th.data('columnKey') || '').trim();
            if (!key) {
                return;
            }

            var label = String($th.text() || '').replace(/\s+/g, ' ').trim();
            if (label === '') {
                label = key;
            }

            if (mandatory[key]) {
                var staticToggleId = sanitizeDomId(tableId + '_column_static_' + key);
                staticItems.push(
                    '<div class="crud-column-settings__item is-static">' +
                        '<span>' + escapeHtml(label) + '</span>' +
                        '<small>Obligatoire</small>' +
                        '<input type="checkbox" id="' + escapeHtml(staticToggleId) + '" checked disabled>' +
                    '</div>'
                );
                return;
            }

            var hasStoredPref = Object.prototype.hasOwnProperty.call(storedPrefs, key);
            var isVisible = hasStoredPref ? !!prefs[key] : (visibleOptionalCount < optionalSlots);
            prefs[key] = isVisible;
            if (isVisible) {
                visibleOptionalCount += 1;
            }
            var inputId = sanitizeDomId(tableId + '_column_' + key);

            toggleItems.push(
                '<label class="crud-column-settings__item" for="' + escapeHtml(inputId) + '">' +
                    '<span>' + escapeHtml(label) + '</span>' +
                    '<input type="checkbox" id="' + escapeHtml(inputId) + '" data-column-toggle="' + escapeHtml(key) + '" data-table-id="' + escapeHtml(tableId) + '"' + (isVisible ? ' checked' : '') + '>' +
                '</label>'
            );
        });

        var html = '<div class="crud-column-settings__title">Colonnes</div>';

        if (staticItems.length > 0) {
            html += '<div class="crud-column-settings__group">' + staticItems.join('') + '</div>';
        }

        if (toggleItems.length > 0) {
            if (staticItems.length > 0) {
                html += '<div class="crud-column-settings__divider"></div>';
            }
            html += '<div class="crud-column-settings__group">' + toggleItems.join('') + '</div>' +
                '<div class="crud-column-settings__hint js-crud-columns-hint"></div>';
        } else {
            html += '<div class="crud-column-settings__empty">Aucune colonne configurable</div>';
        }

        $menu.html(html);
        syncMenuState($table, tableId, $menu);
        if (JSON.stringify(storedPrefs) !== JSON.stringify(prefs)) {
            localStorage.setItem(getStorageKey(tableId, 'visible'), JSON.stringify(prefs));
        }
    }

    function syncMenuState($table, tableId, $menu) {
        var prefs = enforceColumnVisibilityLimit($table, getColumnPrefs(tableId));
        var visibleCount = countVisibleColumns($table, prefs);
        var hasLimit = isLimitEnabled(tableId, $table);
        var atLimit = hasLimit && visibleCount >= MAX_VISIBLE_COLUMNS;

        $menu.find('[data-column-toggle]').each(function() {
            var $input = $(this);
            var key = String($input.data('columnToggle') || '').trim();
            if (key === '') {
                return;
            }

            var visible = prefs.hasOwnProperty(key)
                ? !!prefs[key]
                : $table.find('thead th[data-column-key="' + key + '"]').is(':visible');

            $input.prop('checked', visible);
            $input.prop('disabled', atLimit && !visible);
            $input.closest('.crud-column-settings__item').toggleClass('is-disabled', atLimit && !visible);
        });

        var $hint = $menu.find('.js-crud-columns-hint').first();
        if ($hint.length > 0) {
            if (!hasLimit) {
                $hint.removeClass('is-warning').text('');
            } else {
                $hint
                    .toggleClass('is-warning', atLimit)
                    .text('Colonnes visibles: ' + visibleCount + '/' + MAX_VISIBLE_COLUMNS + (atLimit ? ' (masquez une colonne optionnelle pour en afficher une autre)' : ''));
            }
        }
    }

    function bindGlobalMenuHandlers() {
        $(document).on('click.crudColumnMenu', '.crud-column-settings__trigger', function(event) {
            event.preventDefault();
            event.stopPropagation();

            var $floating = $(this).closest('.crud-column-settings-floating');
            if ($floating.length === 0) {
                return;
            }

            var isOpen = $floating.hasClass('is-open');
            closeAllMenus();

            if (!isOpen) {
                $floating.addClass('is-open');
                $(this).attr('aria-expanded', 'true');
            }
        });

        $(document).on('click.crudColumnMenu', function(event) {
            if ($(event.target).closest('.crud-column-settings-floating').length > 0) {
                return;
            }
            closeAllMenus();
        });

        $(document).on('keydown.crudColumnMenu', function(event) {
            if (event.key === 'Escape') {
                closeAllMenus();
            }
        });
    }

    function closeAllMenus() {
        $('.crud-column-settings-floating.is-open').removeClass('is-open');
        $('.crud-column-settings__trigger[aria-expanded="true"]').attr('aria-expanded', 'false');
    }

    function bindColumnToggleHandler() {
        $(document).on('change.crudColumnToggle', '.crud-column-settings__menu [data-column-toggle]', function(event) {
            event.stopPropagation();

            var $input = $(this);
            var key = String($input.data('columnToggle') || '').trim();
            var tableId = String($input.data('tableId') || '').trim();
            var visible = $input.is(':checked');

            if (key === '' || tableId === '') {
                return;
            }

            var $table = $('table[data-table-id="' + tableId + '"]').first();
            if ($table.length === 0) {
                return;
            }

            if (visible && isLimitEnabled(tableId, $table)) {
                var prefsBefore = enforceColumnVisibilityLimit($table, getColumnPrefs(tableId));
                var nextPrefs = $.extend({}, prefsBefore);
                nextPrefs[key] = true;
                nextPrefs = enforceColumnVisibilityLimit($table, nextPrefs);
                if (!nextPrefs[key]) {
                    $input.prop('checked', false);
                    syncMenuState($table, tableId, $input.closest('.crud-column-settings__menu'));
                    return;
                }
            }

            toggleColumn($table, key, visible);
            setColumnPref(tableId, key, visible);
            syncMenuState($table, tableId, $input.closest('.crud-column-settings__menu'));
        });
    }

    function applyColumnPreferences() {
        $('table.js-crud-list-table[data-table-id]').each(function() {
            var $table = $(this);
            var tableId = String($table.data('tableId') || '').trim();
            if (tableId === '') {
                return;
            }

            var prefs = enforceColumnVisibilityLimit($table, getColumnPrefs(tableId));
            Object.keys(prefs).forEach(function(key) {
                var mandatory = getMandatoryColumns($table);
                if (mandatory[key]) {
                    return;
                }
                toggleColumn($table, key, !!prefs[key]);
            });

            localStorage.setItem(getStorageKey(tableId, 'visible'), JSON.stringify(prefs));
            var $menu = $('.crud-column-settings__menu[data-table-id="' + tableId + '"]').first();
            if ($menu.length > 0) {
                syncMenuState($table, tableId, $menu);
            }
        });
    }

    function toggleColumn($table, key, visible) {
        if (!$table || $table.length === 0 || key === '' || key === 'selector' || key === 'actions') {
            return;
        }

        var mandatory = getMandatoryColumns($table);
        if (mandatory[key]) {
            visible = true;
        }

        var $ths = $table.find('th[data-column-key="' + key + '"]');
        var $tds = $table.find('td[data-column-key="' + key + '"]');

        if (visible) {
            $ths.show();
            $tds.show();
        } else {
            $ths.hide();
            $tds.hide();
        }
    }

    function initColumnResizing() {
        $('table.js-crud-list-table[data-table-id] thead tr th').each(function() {
            var $th = $(this);
            if ($th.find('.column-resize-handle').length > 0) {
                return;
            }

            var key = String($th.data('columnKey') || '').trim();
            if (!key || key === 'selector' || key === 'actions') {
                return;
            }

            $th.css('position', 'relative');
            $th.append('<div class="column-resize-handle"></div>');
        });
    }

    function getColumnWidths(tableId) {
        try {
            return JSON.parse(localStorage.getItem(getStorageKey(tableId, 'widths')) || '{}');
        } catch (e) {
            return {};
        }
    }

    function setColumnWidth(tableId, key, width) {
        var widths = getColumnWidths(tableId);
        widths[key] = width;
        localStorage.setItem(getStorageKey(tableId, 'widths'), JSON.stringify(widths));
    }

    function applyColumnWidths() {
        $('table.js-crud-list-table[data-table-id]').each(function() {
            var $table = $(this);
            var tableId = String($table.data('tableId') || '').trim();
            if (!tableId) {
                return;
            }

            var widths = getColumnWidths(tableId);
            Object.keys(widths).forEach(function(key) {
                var width = parseFloat(widths[key]);
                if (isNaN(width) || width <= 0) {
                    return;
                }
                $table.find('th[data-column-key="' + key + '"]').css({ width: width + 'px', minWidth: width + 'px' });
            });
        });
    }

    $(document).on('mousedown', '.column-resize-handle', function(event) {
        event.preventDefault();
        event.stopPropagation();

        isResizing = true;
        $resizeTh = $(this).closest('th');
        resizeStartX = event.pageX;
        resizeStartWidth = $resizeTh.outerWidth();
        resizeTableId = String($resizeTh.closest('table').data('tableId') || '').trim();

        $('body').css('cursor', 'col-resize').addClass('is-resizing-column');
    });

    $(document).on('mousemove', function(event) {
        if (!isResizing || !$resizeTh) {
            return;
        }

        var newWidth = resizeStartWidth + (event.pageX - resizeStartX);
        if (newWidth < 40) {
            newWidth = 40;
        }

        $resizeTh.css({ width: newWidth + 'px', minWidth: newWidth + 'px' });
    });

    $(document).on('mouseup', function() {
        if (!isResizing || !$resizeTh) {
            return;
        }

        var finalWidth = $resizeTh.outerWidth();
        var key = String($resizeTh.data('columnKey') || '').trim();
        if (key && resizeTableId) {
            setColumnWidth(resizeTableId, key, finalWidth);
        }

        isResizing = false;
        $resizeTh = null;
        resizeTableId = '';
        $('body').css('cursor', '').removeClass('is-resizing-column');
    });

    function sanitizeDomId(value) {
        return String(value || '')
            .replace(/[^a-zA-Z0-9\-_:.]/g, '_')
            .replace(/^_+/, '')
            .replace(/_+$/, '');
    }

    $(init);
    document.addEventListener('turbo:load', init);
    document.addEventListener('turbo:render', init);

})(window.jQuery);
