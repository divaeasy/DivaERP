import glob, re

targets = [
    'templates/client/index.html.twig',
    'templates/fournisseur/index.html.twig',
    'templates/prospect/index.html.twig',
    'templates/tarifs/index.html.twig',
    'templates/tarifvente/index.html.twig',
    'templates/tiers_interne/index.html.twig',
    'templates/depot/index.html.twig',
    'templates/nature_production/index.html.twig'
]

for filepath in targets:
    try:
        with open(filepath, 'r', encoding='utf-8') as f:
            content = f.read()
            
        # Remove the column-settings div block inside action-tools
        # We find <div class="column-settings ml-2">...</div></div>
        content = re.sub(r'<div class="column-settings ml-2">.*?</div>\s*</div>', '</div>', content, flags=re.DOTALL)
        
        # Remove the <style> block containing column-settings
        content = re.sub(r'<style>\s*\.column-settings.*?column-resize-handle:hover.*?</style>\s*', '', content, flags=re.DOTALL)
        
        # Remove the <script> block containing column-settings logic
        # It starts with <script>\n$(function() {
        content = re.sub(r'<script>\s*\$\(function\(\)\s*\{\s*var tableId =.*?\n\}\);\s*</script>\s*', '', content, flags=re.DOTALL)
        
        # Inject the new crud-column-settings-floating into the table-container
        # We find <div class="table-container">...
        new_gear = '''<div class="table-container">
    <div class="crud-column-settings-floating">
        <button type="button" class="no-loading crud-column-settings__trigger" aria-expanded="false">
            <i class="fas fa-cog"></i>
        </button>
        <div class="crud-column-settings__menu"></div>
    </div>'''
        content = re.sub(r'<div class="table-container">', new_gear, content)

        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(content)
        print('Processed ' + filepath)
    except Exception as e:
        print('Error processing ' + filepath + ': ' + str(e))
