# E10 — Stratégie de tests et qualité

**Objectif.** Des tests qui prouvent la conformité à la spec, protègent des régressions de performance et suivent les
patterns des libs modèles.

**Dépend de :** E0
**Jalon :** M0 → M5
**Note :** le socle au M0, puis en continu avec chaque EPIC.

## Livrables

### Conformité

- [ ] **E10.1** Chaque document produit en test est validé contre le JSON Schema officiel JSON:API 1.1
      (`opis/json-schema` en dépendance de dev), comme le writer valide contre le schéma OpenAPI
- [ ] **E10.2** Documents attendus en fichiers `tests/Feature/<Scenario>/expected/*.json`, régénérés par
      `composer fixtures:update` et relus dans le diff

### Organisation

- [ ] **E10.3** Object Mother (`tests/Support/Models.php`, `Resources.php`) à la place des stubs ad hoc
- [ ] **E10.3b** Assertions partagées dans un `TestCase` de base, pas en traits
- [x] **E10.4** Tests d'architecture `phpat` : couches, `final`, traits limités à `Resources/`
- [ ] **E10.7** Noms de tests en phrases (`test_null_to_one_relationship_renders_data_null`)

### Non-régression et performance

- [ ] **E10.5** Un test de non-régression par bug d'E1 / E2, écrit rouge avant la correction
- [ ] **E10.6** Tests à compteur de requêtes SQL : aucun N+1 sur les scénarios d'include et de linkage

## Critères de sortie

- [ ] Couverture ≥ 90 %, MSI ≥ 80 %
- [ ] Suite verte sur toute la matrice PHP × Laravel, en CI GitHub (premier push de `v2.0`)
