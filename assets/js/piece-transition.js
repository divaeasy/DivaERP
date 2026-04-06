/**
 * Piece Transition Handler
 * Manages piece type transitions with dropdown menus and confirmations
 */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        initializePieceTransitions();
    });

    function initializePieceTransitions() {
        const transitionForms = document.querySelectorAll('.js-piece-transition-form');

        transitionForms.forEach(form => {
            const button = form.querySelector('button');
            if (button) {
                button.addEventListener('click', handleTransitionClick);
            }
        });
    }

    function handleTransitionClick(e) {
        e.preventDefault();

        const form = this.closest('.js-piece-transition-form');
        if (!form) return;

        const targetType = form.dataset.targetType;
        const pieceRef = getPieceRef(form);

        if (!targetType) {
            console.error('Target type not found');
            return;
        }

        // Get current piece type from the table row
        const row = form.closest('tr');
        const currentTypeCell = row ? row.querySelector('td:nth-child(2)') : null;
        const currentType = currentTypeCell ? currentTypeCell.innerText.trim() : 'Unknown';

        // Show confirmation dialog
        const confirmed = confirm(
            `Êtes-vous sûr de vouloir convertir cette pièce en ${targetType}?\n\n` +
            `Pièce actuelle: ${currentType}\n` +
            `${pieceRef ? 'Référence: ' + pieceRef : ''}\n\n` +
            `La pièce source sera archivée (Perimée),\n` +
            `et une nouvelle pièce ${targetType} sera créée\n` +
            `avec les mêmes lignes en statut Brouillon.`
        );

        if (confirmed) {
            // Submit the form
            form.submit();
        }
    }

    function getPieceRef(element) {
        const row = element.closest('tr');
        if (!row) return '';

        // Try to get piece number from the row
        const pieceNoCell = row.querySelector('td:nth-child(4)');
        return pieceNoCell ? pieceNoCell.innerText.trim() : '';
    }

    /**
     * Initialize dropdown toggle for transitions
     */
    function initializeDropdowns() {
        const dropdownToggles = document.querySelectorAll('.btn-convert.dropdown-toggle');

        dropdownToggles.forEach(toggle => {
            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                const menu = this.nextElementSibling;
                if (menu && menu.classList.contains('dropdown-menu')) {
                    menu.classList.toggle('show');
                }
            });
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            const dropdowns = document.querySelectorAll('.dropdown-menu.show');
            dropdowns.forEach(menu => {
                if (!menu.closest('.dropdown').contains(e.target)) {
                    menu.classList.remove('show');
                }
            });
        });
    }

    // Initialize dropdowns when page loads
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeDropdowns);
    } else {
        initializeDropdowns();
    }

})();
