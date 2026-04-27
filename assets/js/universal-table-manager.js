/**
 * Universal Table Manager - Generic table enhancement utility
 * Provides inline editing, bulk actions, column preferences, and column resizing
 * 
 * Usage:
 * new UniversalTableManager({
 *   tableId: 'my-table',
 *   editableFields: ['name', 'email'],
 *   bulkDeleteUrl: '/api/bulk-delete',
 *   inlineUpdateUrl: '/api/inline-update',
 *   editableFieldsMap: { 'email': 'emailField' },
 *   dropdownFields: {} // optional
 * });
 */

class UniversalTableManager {
  constructor(options) {
    this.options = {
      tableId: '',
      editableFields: [],
      bulkDeleteUrl: '',
      inlineUpdateUrl: '',
      editableFieldsMap: {},
      dropdownFields: {},
      entityName: 'Item',
      bulkDeleteToken: '',
      deleteItemLabel: '',
      ...options
    };

    this.init();
  }

  init() {
    if (!this.options.tableId) return;
    
    this.$table = $(`#${this.options.tableId}`);
    if (!this.$table.length) return;

    this.setupTableStructure();
    this.setupSelectionManager();
    this.setupEventHandlers();
  }

  setupTableStructure() {
    const $thead = this.$table.find('thead tr:first');
    const $tbody = this.$table.find('tbody');

    // Add checkbox column if not exists
    if (!$thead.find('[data-row-checkbox]').length) {
      $thead.prepend('<th style="width: 40px; text-align: center;"><input type="checkbox" data-select-all-checkbox></th>');
      $tbody.find('tr').each(function() {
        const id = $(this).attr('data-item-id') || $(this).find('td:first strong').text().replace('#', '');
        $(this).prepend(`<td style="text-align: center;"><input type="checkbox" data-row-checkbox value="${id}"></td>`);
      });
    }
  }

  setupSelectionManager() {
    this.selectionManager = new UniversalTableSelectionManager(this.$table, {
      bulkBarSelector: `[data-bulk-bar-for="${this.options.tableId}"]`,
      onSelectionChange: () => this.updateBulkLabel(),
      onBulkAction: (action, ids) => this.handleBulkAction(action, ids)
    });
  }

  setupEventHandlers() {
    this.$table.on('dblclick', 'tbody tr', (e) => this.beginEdit($(e.currentTarget)));
    this.$table.on('click', '.inline-save-btn', () => this.saveEdit());
    this.$table.on('click', '.inline-cancel-btn', () => this.cancelEdit());
    
    // Delete button handler
    this.$table.on('click', '.delete-btn', (e) => {
      const $row = $(e.target).closest('tr');
      const itemName = $row.attr('data-item-name') || $row.find('td:nth-child(2)').text();
      this.showDeleteConfirm(itemName, $row.attr('data-item-id'), () => {
        this.deleteItem($row.attr('data-item-id'));
      });
    });
  }

  beginEdit($row) {
    if ($row.hasClass('is-editing')) return;
    if (this.activeEditRow) this.cancelEdit();

    this.activeEditRow = $row;
    $row.addClass('is-editing');

    // Replace cells with editable fields
    this.options.editableFields.forEach(field => {
      const $cell = $row.find(`[data-field="${field}"]`);
      if (!$cell.length) return;

      const currentValue = $cell.text().trim();
      $cell.html(`<input type="text" class="inline-edit-input form-control form-control-sm" data-field="${field}" value="${this.escapeHtml(currentValue)}">`);
    });

    // Update actions
    const $actionsCell = $row.find('[data-field="actions"]');
    if ($actionsCell.length) {
      $actionsCell.html(`
        <button class="btn btn-sm btn-success inline-save-btn"><i class="fas fa-check"></i></button>
        <button class="btn btn-sm btn-secondary inline-cancel-btn"><i class="fas fa-times"></i></button>
      `);
    }

    $row.find('.inline-edit-input:first').focus();
  }

  saveEdit() {
    if (!this.activeEditRow) return;

    const $row = this.activeEditRow;
    const id = $row.attr('data-item-id');
    const data = { id };

    // Collect field values
    this.options.editableFields.forEach(field => {
      const $input = $row.find(`[data-field="${field}"] input`);
      if ($input.length) {
        data[field] = $input.val();
      }
    });

    $row.addClass('is-saving');

    $.ajax({
      url: this.options.inlineUpdateUrl,
      method: 'POST',
      data: JSON.stringify(data),
      contentType: 'application/json',
      headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
      },
      success: (response) => {
        // Update row data attributes
        Object.keys(data).forEach(key => {
          $row.attr(`data-${key}`, data[key]);
        });
        this.cancelEdit();
        this.showToast('success', response.message || 'Item updated successfully');
      },
      error: (xhr) => {
        this.cancelEdit();
        this.showToast('error', xhr.responseJSON?.message || 'Error updating item');
      }
    });
  }

  cancelEdit() {
    if (!this.activeEditRow) return;

    const $row = this.activeEditRow;
    $row.removeClass('is-editing is-saving');

    // Restore original content
    this.options.editableFields.forEach(field => {
      const $cell = $row.find(`[data-field="${field}"]`);
      const originalValue = $row.attr(`data-${field}`) || '';
      $cell.text(originalValue);
    });

    // Restore actions
    const $actionsCell = $row.find('[data-field="actions"]');
    if ($actionsCell.length && $row.attr('data-original-actions')) {
      $actionsCell.html($row.attr('data-original-actions'));
    }

    this.activeEditRow = null;
  }

  handleBulkAction(action, ids) {
    if (action === 'delete') {
      this.showBulkDeleteConfirm(ids.length, () => this.bulkDelete(ids));
    }
  }

  bulkDelete(ids) {
    $.ajax({
      url: this.options.bulkDeleteUrl,
      method: 'POST',
      data: JSON.stringify({ ids }),
      contentType: 'application/json',
      headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
      },
      success: (response) => {
        ids.forEach(id => {
          $(`tr[data-item-id="${id}"]`).fadeOut(() => $(`tr[data-item-id="${id}"]`).remove());
        });
        this.selectionManager.clearSelection();
        this.showToast('success', response.message || 'Items deleted successfully');
      },
      error: (xhr) => {
        this.showToast('error', xhr.responseJSON?.message || 'Error deleting items');
      }
    });
  }

  deleteItem(id) {
    const $row = $(`tr[data-item-id="${id}"]`);
    if (!$row.length) return;

    $.ajax({
      url: $row.find('.delete-btn').data('delete-url'),
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
      },
      success: () => {
        $row.fadeOut(() => $row.remove());
        this.showToast('success', 'Item deleted successfully');
      },
      error: (xhr) => {
        this.showToast('error', xhr.responseJSON?.message || 'Error deleting item');
      }
    });
  }

  updateBulkLabel() {
    const count = this.selectionManager.selectedIds.length;
    const label = count > 1 ? `${count} items selected` : (count === 1 ? '1 item selected' : '');
    $(`[data-bulk-bar-for="${this.options.tableId}"] [data-selection-label]`).text(label);
  }

  showDeleteConfirm(itemName, id, onConfirm) {
    if (confirm(`Delete "${itemName}"?`)) {
      onConfirm();
    }
  }

  showBulkDeleteConfirm(count, onConfirm) {
    const msg = count > 1 ? `Delete ${count} items?` : 'Delete this item?';
    if (confirm(msg)) {
      onConfirm();
    }
  }

  showToast(type, message) {
    if (typeof window.showAppToast === 'function') {
      window.showAppToast(type, message);
    } else {
      alert(message);
    }
  }

  escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }
}

// Universal Table Selection Manager (reuse from article page)
class UniversalTableSelectionManager {
  constructor($table, options) {
    this.$table = $table;
    this.options = $.extend({
      rowSelector: 'tbody tr[data-item-id]',
      rowCheckboxSelector: '[data-row-checkbox]',
      selectAllSelector: '[data-select-all-checkbox]',
      bulkBarSelector: '',
      enableRowClickSelection: true,
      onSelectionChange: null,
      onBulkAction: null
    }, options || {});

    this.selectedIds = [];
    this.$headerCheckbox = this.$table.find(this.options.selectAllSelector).first();
    this.$bulkBar = this.options.$bulkBar && this.options.$bulkBar.length
      ? this.options.$bulkBar
      : this.resolveBulkBar();

    this.bindEvents();
    this.syncFromDom();
  }

  resolveBulkBar() {
    if (this.options.bulkBarSelector) {
      return $(this.options.bulkBarSelector).first();
    }
    return $();
  }

  getRows() {
    return this.$table.find(this.options.rowSelector);
  }

  getRowCheckbox($row) {
    return $row.find(this.options.rowCheckboxSelector).first();
  }

  getRowId($row) {
    return String($row.attr('data-item-id') || this.getRowCheckbox($row).val() || '').trim();
  }

  setRowSelection($row, isSelected, config = {}) {
    const $checkbox = this.getRowCheckbox($row);
    const rowId = this.getRowId($row);

    $checkbox.prop('checked', isSelected);
    $row.toggleClass('is-selected', isSelected);

    if (isSelected) {
      if (!this.selectedIds.includes(rowId)) this.selectedIds.push(rowId);
    } else {
      this.selectedIds = this.selectedIds.filter(id => id !== rowId);
    }

    if (!config.skipUiUpdate) this.updateUi();
  }

  selectAll(shouldSelect) {
    this.getRows().each((i, row) => {
      this.setRowSelection($(row), shouldSelect, { skipUiUpdate: true });
    });
    this.updateUi();
  }

  clearSelection() {
    this.selectAll(false);
  }

  syncFromDom() {
    const nextSelectedIds = [];
    this.getRows().each((i, row) => {
      const $row = $(row);
      const rowId = this.getRowId($row);
      const isChecked = this.getRowCheckbox($row).is(':checked');
      $row.toggleClass('is-selected', isChecked);
      if (isChecked && rowId && !nextSelectedIds.includes(rowId)) {
        nextSelectedIds.push(rowId);
      }
    });
    this.selectedIds = nextSelectedIds;
    this.updateUi();
  }

  updateUi() {
    const totalRows = this.getRows().length;
    const selectedCount = this.selectedIds.length;
    const allSelected = totalRows > 0 && selectedCount === totalRows;

    if (this.$headerCheckbox.length) {
      this.$headerCheckbox.prop('checked', allSelected);
      this.$headerCheckbox.prop('indeterminate', selectedCount > 0 && !allSelected);
    }

    if (this.$bulkBar.length) {
      this.$bulkBar.toggleClass('is-visible', selectedCount > 0);
      this.$bulkBar.find('[data-selection-count]').text(selectedCount);
    }

    if (typeof this.options.onSelectionChange === 'function') {
      this.options.onSelectionChange(this.selectedIds);
    }
  }

  bindEvents() {
    this.$table.on('change', this.options.rowCheckboxSelector, (e) => {
      const $row = $(e.target).closest(this.options.rowSelector);
      this.setRowSelection($row, $(e.target).is(':checked'));
    });

    this.$headerCheckbox.on('change', (e) => {
      this.selectAll($(e.target).is(':checked'));
    });

    this.$bulkBar.on('click', '[data-bulk-clear]', (e) => {
      e.preventDefault();
      this.clearSelection();
    });

    this.$bulkBar.on('click', '[data-bulk-action]', (e) => {
      e.preventDefault();
      const action = $(e.target).data('bulk-action');
      if (typeof this.options.onBulkAction === 'function') {
        this.options.onBulkAction(action, this.selectedIds);
      }
    });
  }
}
