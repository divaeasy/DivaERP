# CodeOperation CRUD Pages & Admin Interfaces - Complete Inventory

## Overview
CodeOperation is a **reference entity** that represents accounting operation types (e.g., "Vente Standard", "Achat Standard", "Transfert Interne"). It is NOT managed through a standard CRUD interface, but is integrated throughout the application via:
1. **Migration Management Panel** (Admin Interface)
2. **Inline Selection** in Piece List & Edit Forms
3. **API Endpoints** for data operations
4. **Services** for business logic

---

## 1. ENTITY FILES

### CodeOperation Entity
**File:** [src/Entity/CodeOperation.php](src/Entity/CodeOperation.php)

**Properties:**
- `id` (int, Primary Key)
- `libelle` (string, 255) - Display label
- `sens` (SensEnum) - Direction: DEBIT/CREDIT (Entrée/Sortie)
- `isActive` (bool, default: true) - Active/Inactive flag
- `pieceTypeDevis` (bool) - Applies to Devis
- `pieceTypeCommande` (bool) - Applies to Commande
- `pieceTypeBL` (bool) - Applies to BL (Bon de Livraison)
- `pieceTypeFacture` (bool) - Applies to Facture
- `pieceTypeInterne` (bool) - Applies to Internal Pieces
- Timestamps (created_at, updated_at)

**Unique Constraint:** `UNIQ_CODE_OPERATION_LIBELLE` on `libelle` column

---

## 2. CONTROLLERS

### CodeOperationMigrationController
**File:** [src/Controller/CodeOperationMigrationController.php](src/Controller/CodeOperationMigrationController.php)

**Routes:**
- `GET /admin/code-operation-migration` → `admin.code_operation_migration`
  - **Purpose:** Display migration status dashboard
  - **Access:** ROLE_COMPTABLE required
  - **Action:** Shows:
    - Total pieces and pending code operations
    - Total lines and pending sens values
    - Migration completion percentages
    - Options to run auto or manual migration

- `POST /admin/code-operation-migration/run` → `admin.code_operation_migration_run`
  - **Purpose:** Execute code operation migration
  - **Access:** ROLE_COMPTABLE required
  - **Modes:** 
    - `auto` - Automatic migration based on tier type rules
    - `manual` - Batch manual migration with configurable limit (default: 200)
  - **CSRF Protected:** Yes, token key `run_code_operation_migration`

**Controller Services:**
- Uses `CodeOperationMigrationService` for migration logic

---

### EntetePController (Piece Management)
**File:** [src/Controller/EntetePController.php](src/Controller/EntetePController.php)

**CodeOperation Integration:**
- Routes: `/piece/*`
- **Key Methods:**
  1. `index()` - Redirects to client piece list
  2. `clientPieces()` - Lists client/prospect pieces
  3. `fournisseurPieces()` - Lists supplier pieces
  4. `internePieces()` - Lists internal pieces (ROLE_ADMIN/ROLE_COMPTABLE only)
  5. `editPiece()` - Edit/create piece with CodeOperation field
  6. `transitionPiece()` - Convert piece type while preserving CodeOperation

**CodeOperation-Specific Handling in `editPiece()` (Lines 200-250):**
- Auto-resolves CodeOperation for tier type if not selected
- Validates CodeOperation matches tier type and piece type
- Allows manual override for pieces requiring it
- Preserves CodeOperation on piece type transitions

**CodeOperation Population in `renderPieceList()` (Line 1170+):**
```php
$inlineCodeOperations = array_map(static fn ($operation): array => [
    'id' => (int) ($operation->getId() ?? 0),
    'label' => (string) ($operation->getLibelle() ?? ''),
    'sens' => (string) $operation->getSens()->label(),
], $this->codeOperationService->getActiveForPiece(
    is_string($defaultTierType) ? $defaultTierType : null, 
    'Facture'
));
```

---

### CrudTableController (Inline API Operations)
**File:** [src/Controller/CrudTableController.php](src/Controller/CrudTableController.php)

**API Routes:**
- `POST /api/crud/{resource}/create` → `api.crud.create`
  - Creates entities via JSON
  - Special handling for Entetepiece:
    - Auto-generates pieceno
    - Auto-resolves CodeOperation if missing or inactive
    - Sets default statut to 'Brouillon'
  - **CSRF Protected:** Token key `crud_create_{resource}`

- `POST /api/crud/{resource}/bulk-delete` → `api.crud.bulk_delete`
  - Bulk delete via JSON
  - **CSRF Protected:** Token key `crud_bulk_delete_{resource}`

- `POST /api/crud/{resource}/inline-edit` → `api.crud.inline_edit`
  - Inline field editing
  - Supports CodeOperation field mapping:
    ```
    'code_operation' => [
        'setter' => 'setCodeOperation',
        'getter' => 'getCodeOperation',
        'type' => 'entity',
        'entity' => CodeOperation::class,
        'lookup' => 'libelle',
        'required' => true
    ]
    ```

**Resource Definition (Line 570+):**
- Entetepiece is marked as `simpleCrud: true`
- Supports quick inline create/edit operations

---

### ExportController
**File:** [src/Controller/ExportController.php](src/Controller/ExportController.php)

**CodeOperation Integration:**
- Line 277: Exports `$entry['codeOperation']` in piece export data
- Line 290: Includes 'CodeOperation' column in CSV export headers

---

## 3. FORM TYPES

### EntetePieceFormType
**File:** [src/Form/EntetePieceFormType.php](src/Form/EntetePieceFormType.php)

**CodeOperation Field Configuration:**
- **Method:** `addCodeOperationField()` (Line 456+)
- **Field Type:** EntityType
- **Query Builder:** Filters by:
  - Active operations only
  - Tier type (Client, Prospect, Fournisseur, Interne)
  - Piece type (Devis, Commande, BL, Facture)
- **Form Options:**
  - CSS Class: `js-example-basic-single` (Select2 integration)
  - Placeholder: None
  - Required: true for most scenarios

**Dynamic Field Addition (Line 161+):**
- Called during form build based on tier type and piece type
- Called during form pre-set data
- Called during form post-submit validation
- Handles legacy data migration automatically

**No dedicated CodeOperationFormType exists** - CodeOperation is only a selector in Entetepiece form

---

## 4. TEMPLATES

### Admin: Code Operation Migration Dashboard
**File:** [templates/admin/code_operation_migration.html.twig](templates/admin/code_operation_migration.html.twig)

**URL:** `/admin/code-operation-migration`

**Features:**
- KPI Cards showing:
  - Total pieces & pending count
  - Total lines & pending sens count
  - Completion percentages
  - Progress bars
- Migration Status Indicator (Complete/Partial)
- Migration Control Buttons:
  - Auto-migration mode selector
  - Manual migration with configurable limit
  - Batch processing controls
- Access: ROLE_COMPTABLE required

---

### Piece List View
**File:** [templates/entetepiece/index.html.twig](templates/entetepiece/index.html.twig)

**URL:** 
- `/piece/client` (Client & Prospect pieces)
- `/piece/fournisseur` (Supplier pieces)
- `/piece/interne` (Internal pieces - Admin/Comptable only)

**CodeOperation Display (Line 242-250):**
```html
<td data-column-key="codeoperation">
    {% set opLabel = entetepiece.codeOperation ? entetepiece.codeOperation.libelle : '____' %}
    {% set opSens = entetepiece.codeOperation ? entetepiece.codeOperation.sens.label : '' %}
    {% if opSens == 'Entree' %}
        <span class="piece-sens-badge piece-sens-badge--debit">
            <i class="fas fa-arrow-down"></i>{{ opLabel }}
        </span>
    {% elseif opSens == 'Sortie' %}
        <span class="piece-sens-badge piece-sens-badge--credit">
            <i class="fas fa-arrow-up"></i>{{ opLabel }}
        </span>
    {% else %}
        <span class="piece-sens-badge">{{ opLabel }}</span>
    {% endif %}
</td>
```

**Circle/Icon Display:**
- **Entée (Debit):** Blue badge with `<i class="fas fa-arrow-down"></i>` (down arrow icon)
- **Sortie (Credit):** Green badge with `<i class="fas fa-arrow-up"></i>` (up arrow icon)
- **Unknown:** Gray badge with text only
- **Class Prefix:** `piece-sens-badge piece-sens-badge--debit|--credit`

**Inline Quick Creation (Line 447-450):**
```html
<label for="pieceQuickCodeOperation">Code operation</label>
<select id="pieceQuickCodeOperation" name="codeOperationId" class="form-control piece-quick-input">
    {% for item in inlineCodeOperations|default([]) %}
        {# Options rendered from controller #}
    {% endfor %}
</select>
```

**Column Toggle Feature (Line 116-118):**
```html
<label class="piece-column-settings__item" for="pieceColumnToggle_codeoperation">
    <input type="checkbox" id="pieceColumnToggle_codeoperation" data-column-toggle="codeoperation">
```
- User can toggle visibility of CodeOperation column
- Persists in optional columns list

**Data Attributes on Row (Line 203-205):**
```html
data-code-operation-id="{{ entetepiece.codeOperation ? entetepiece.codeOperation.id : '' }}"
data-code-operation-label="{{ entetepiece.codeOperation ? entetepiece.codeOperation.libelle : '' }}"
data-code-operation-sens="{{ entetepiece.codeOperation ? entetepiece.codeOperation.sens.label : '' }}"
```

---

### Piece Edit/Create Form
**File:** [templates/entetepiece/add-entetepiece.html.twig](templates/entetepiece/add-entetepiece.html.twig)

**CodeOperation Field (Line 75-79):**
```html
<div class="form-group">
    {{ form_label(entetepiece.codeOperation, 'Code operation', {'label_attr': {'class': 'required'}}) }}
    {{ form_widget(entetepiece.codeOperation, {'attr': {'class': 'form-control js-example-basic-single'}}) }}
    {% if not isReadOnly|default(false) %}
        <div class="invalid-feedback">Le code operation est obligatoire</div>
        {{ form_errors(entetepiece.codeOperation) }}
    {% endif %}
</div>
```

**Features:**
- Select2 integration via `js-example-basic-single` class
- Required field indicator
- Shows validation errors
- Disabled in read-only mode (perimee status)

---

## 5. SERVICES

### CodeOperationService
**File:** [src/Service/CodeOperationService.php](src/Service/CodeOperationService.php)

**Key Methods:**

1. **`canManageCodeOperations(): bool`**
   - Checks if user has ROLE_ADMIN or ROLE_COMPTABLE

2. **`getActiveForPiece(?string $tierType, ?string $pieceType): array`**
   - Returns active CodeOperation entities for given tier/piece type
   - Tier types: client, prospect, fournisseur, interne, vat
   - Auto-syncs default operations if none found

3. **`resolveCodeOperationForTierType(?string $tierType, ?string $pieceType): ?CodeOperation`**
   - Auto-selects CodeOperation if only one available for tier type
   - Returns null for internal pieces (requires manual selection)

4. **`isOperationAllowedForTierType(CodeOperation $operation, ?string $tierType, ?string $pieceType): bool`**
   - Validates if operation is compatible with tier/piece type

5. **`resolveLineSensFromPiece(Entetepiece $piece): ?SensEnum`**
   - Derives line sens from piece's CodeOperation

---

### CodeOperationMigrationService
**File:** [src/Service/CodeOperationMigrationService.php](src/Service/CodeOperationMigrationService.php)

**Key Methods:**

1. **`getStatus(): array`**
   - Returns migration dashboard data:
     - totalPieces, piecesWithoutCodeOperation
     - totalLines, linesWithoutSens
     - Completion percentages

2. **`runAutoMigration(int $limit): array`**
   - Auto-assigns CodeOperation based on tier type rules
   - Updates line sens from piece CodeOperation
   - Returns counts of updated pieces/lines

3. **`runManualMigration(int $limit): array`**
   - Manual batch migration with limit

4. **`synchronizeDefaultOperations(): void`**
   - Creates default CodeOperation records if missing

---

## 6. REPOSITORIES

### CodeOperationRepository
**File:** [src/Repository/CodeOperationRepository.php](src/Repository/CodeOperationRepository.php)

**Query Methods:**

1. **`findActiveForScope(string $scope, ?string $pieceType): array`**
   - Returns active operations for scope (client/fournisseur/interne)
   - Filters by piece type flags

2. **`find(mixed $id, ?LockMode $lockMode = null, ?int $lockVersion = null): ?CodeOperation`**
   - Standard Doctrine find by ID

---

## 7. ENTITIES USING CodeOperation

### Entetepiece (Piece Header)
**File:** [src/Entity/Entetepiece.php](src/Entity/Entetepiece.php)

**Relationship:**
- Type: Many-to-One (inverse)
- Property: `$codeOperation`
- Methods:
  - `getCodeOperation(): ?CodeOperation`
  - `setCodeOperation(?CodeOperation $codeOperation): static`
- Required for invoice generation and accounting

### Lignepiece (Line Item)
**File:** [src/Entity/Lignepiece.php](src/Entity/Lignepiece.php)

**Relationship:** Indirect via Piece
- Line 76-77: Auto-derives `$sens` from piece's CodeOperation if not set
- Line 186: Uses piece operation for accounting

---

## 8. COMMANDS

### BackfillCodeOperationCommand
**File:** [src/Command/BackfillCodeOperationCommand.php](src/Command/BackfillCodeOperationCommand.php)

**Purpose:** CLI command to backfill missing CodeOperations
**Execution:** `php bin/console app:backfill-code-operation`

---

## 9. SECURITY & VOTERS

### CodeOperationVoter
**File:** [src/Security/Voter/CodeOperationVoter.php](src/Security/Voter/CodeOperationVoter.php)

**Purpose:** Access control for CodeOperation resources

---

## 10. SUMMARY TABLE

| Component | Location | Type | Purpose |
|-----------|----------|------|---------|
| Entity | src/Entity/CodeOperation.php | ORM Entity | Data model |
| Repository | src/Repository/CodeOperationRepository.php | Repository | Database queries |
| Controller (Admin) | src/Controller/CodeOperationMigrationController.php | Controller | Migration management UI |
| Controller (Piece) | src/Controller/EntetePController.php | Controller | Piece CRUD with CodeOp selection |
| Controller (API) | src/Controller/CrudTableController.php | Controller | REST API for inline operations |
| Form | src/Form/EntetePieceFormType.php | FormType | CodeOp field in piece form |
| Service | src/Service/CodeOperationService.php | Service | Business logic |
| Service | src/Service/CodeOperationMigrationService.php | Service | Migration logic |
| Template (Admin) | templates/admin/code_operation_migration.html.twig | Twig | Migration dashboard |
| Template (List) | templates/entetepiece/index.html.twig | Twig | Piece list with CodeOp display |
| Template (Form) | templates/entetepiece/add-entetepiece.html.twig | Twig | Piece form with CodeOp field |
| Command | src/Command/BackfillCodeOperationCommand.php | Command | CLI backfill tool |
| Voter | src/Security/Voter/CodeOperationVoter.php | Voter | Access control |

---

## 11. KEY ROUTES & WORKFLOWS

### Workflow 1: Create/Edit Piece with CodeOperation
1. User navigates to `/piece/client`, `/piece/fournisseur`, or `/piece/interne`
2. Clicks "Add" button or edits existing piece
3. Form shows `EntetePieceFormType` with:
   - CodeOperation field populated via `EntetePieceFormType::addCodeOperationField()`
   - Choices filtered by tier type and piece type
   - Pre-selected based on `CodeOperationService::resolveCodeOperationForTierType()`
4. User submits form → CodeOperation stored with piece
5. On piece list, CodeOperation displays with:
   - Label (libelle)
   - Direction icon (arrow-down for Entée, arrow-up for Sortie)

### Workflow 2: Migrate Legacy Pieces
1. User goes to `/admin/code-operation-migration`
2. Sees dashboard with migration status
3. Clicks "Run Migration" button
4. Selects auto or manual mode
5. `CodeOperationMigrationController::run()` calls:
   - `CodeOperationMigrationService::runAutoMigration()` OR
   - `CodeOperationMigrationService::runManualMigration()`
6. Updates pieces and lines with CodeOperations

### Workflow 3: API Inline Operations
1. POST `/api/crud/entetepiece/create` with JSON:
   ```json
   {
     "tierId": 123,
     "tierType": "Client",
     "codeOperationId": 5,
     "_token": "csrf_token"
   }
   ```
2. `CrudTableController::create()` processes
3. Auto-resolves CodeOperation if missing
4. Returns success/error JSON

---

## 12. ICON/CIRCLE STYLING

**CSS Classes Used:**
- `piece-sens-badge` - Base badge styling
- `piece-sens-badge--debit` - Blue badge for Entée (down arrow)
- `piece-sens-badge--credit` - Green badge for Sortie (up arrow)

**Icons Used:**
- `fas fa-arrow-down` (FontAwesome) - For Entée direction
- `fas fa-arrow-up` (FontAwesome) - For Sortie direction

**Display Locations:**
1. Piece list table (templates/entetepiece/index.html.twig line 242)
2. Inline quick add dropdown (line 447)
3. Data attributes for JavaScript manipulation (line 203)

---

## 13. DATABASE SCHEMA

```sql
CREATE TABLE `code_operation` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `libelle` VARCHAR(255) NOT NULL UNIQUE,
  `sens` VARCHAR(20) NOT NULL,  -- DEBIT or CREDIT
  `is_active` TINYINT(1) DEFAULT 1,
  `piece_type_devis` TINYINT(1) DEFAULT 0,
  `piece_type_commande` TINYINT(1) DEFAULT 0,
  `piece_type_b_l` TINYINT(1) DEFAULT 0,
  `piece_type_facture` TINYINT(1) DEFAULT 0,
  `piece_type_interne` TINYINT(1) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
```

**Tier Type Mapping (from code, not explicit columns):**
- Client/Prospect operations: Default lookup
- Fournisseur operations: "Achat Standard"
- Interne operations: "Transfert Interne Sortie"
