# PolicyWorkbench Catalog Component

Refactored component structure for the policy/settings catalog UI.

## Structure

- **Catalog.vue** - Root orchestrator component (was RealPolicyWorkbench.vue)
- **components/** - UI subcomponents
  - `PolicyRuleCard.vue` - Setting tile display (cards layout)
  - `PolicyRuleEditorPanel.vue` - Setting editor modal
  - *(planned: Toolbar.vue, Navigation.vue, View.vue)*
- **composables/** - Logic extraction
  - `useRealPolicyWorkbench.ts` - Main logic (to be split)
  - *(planned: useNavigation.ts, useCatalogState.ts)*

## Refactoring Plan

### Phase 1: Base Structure (Current)
- ✅ Copy files to new location
- ✅ Create directory structure

### Phase 2: Extract Composables
- Extract `useNavigation.ts` - scroll tracking, active category
- Extract `useCatalogState.ts` - search, layout, collapse state

### Phase 3: Extract Components
- Extract `Toolbar.vue` - search + layout/collapse controls
- Extract `Navigation.vue` - category chips navigation
- Extract `View.vue` - settings display (cards/list)

### Phase 4: Cleanup & Tests
- Update imports in Catalog.vue
- Remove original files (or deprecate)
- Add tests for each component/composable
- Commit final refactoring

## Notes

- **settings/** directory is separate (policy definitions, types, etc.)
- **RealPolicyWorkbench.vue** remains as compatibility wrapper during transition
- All tests go to `src/tests/views/Settings/PolicyWorkbench/`
- CSS remains scoped within each component
