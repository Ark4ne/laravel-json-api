
# E10 — Stratégie de tests et qualité

**Objectif.** Des tests qui prouvent la conformité à la spec, protègent des régressions de performance et suivent les
patterns des libs modèles.

**Dépend de :** E0
**Jalon :** M0 → M5
**Note :** le socle au M0, puis en continu avec chaque EPIC.

## Livrables

### Conformité

- [x] **E10.1** Chaque document produit en test est validé contre le JSON Schema JSON:API 1.1
      (`opis/json-schema` en dépendance de dev), comme le writer valide contre le schéma OpenAPI. jsonapi.org ne
      publie qu'un schéma 1.0 : il est copié dans `tests/Support/jsonapi-1.1.schema.json` et complété des membres 1.1
      (`jsonapi.ext` / `profile`, membres des link objects). Toute réponse `application/vnd.api+json` d'un test Feature
      passe par `FeatureTestCase::createTestResponse()`
- [x] **E10.2** Documents attendus en fichiers `tests/Feature/<Scenario>/expected/*.json`, régénérés par
      `composer fixtures:update` et relus dans le diff : `FeatureTestCase::assertDocument()` ; le seed est
      déterministe (valeurs écrites, sans Faker, et horloge figée) ; scénarios `User` et `Comment` migrés, les fichiers vérifiés
      identiques aux attentes écrites à la main qu'ils remplacent

### Organisation

- ~~E10.3 Object Mother (`tests/Support/Models.php`, `Resources.php`) à la place des stubs ad hoc~~ : retiré, une
  définition est compilée et mise en cache par classe : chaque test de descripteur a besoin de sa propre classe
  anonyme ; `Stub::model()` et les factories de `tests/app` suffisent pour les modèles
- [x] **E10.3b** Assertions partagées dans un `TestCase` de base, pas en traits : `render()` (document validé et
      requêtes comptées), `includedTypes()` et `assertDocument()` dans `FeatureTestCase`, `ruleErrors()` dans
      `TestCase`
- [x] **E10.4** Tests d'architecture `phpat` : couches, `final`, traits limités à `Resources/`
- [x] **E10.7** Noms de tests en phrases (`test_null_to_one_relationship_renders_data_null`) : les 90 derniers
      renommés ; leurs paramètres typés, ce qui retire 51 entrées de la baseline

### Non-régression et performance

- [x] **E10.5** Un test de non-régression par bug d'E1 / E2, écrit rouge avant la correction : inventaire
      ci-dessous ; seul E2.5 n'en avait pas : ajouté après sa correction (E4.2), vu rouge en réinjectant la mutation
      de `$this->with` dans `toResponse()`
- [x] **E10.6** Tests à compteur de requêtes SQL : aucun N+1 sur les scénarios d'include et de linkage
      (`LinkageTest`, `MorphTest`, `NativeResourceTest`) ; preuve directe :
      `test_the_number_of_queries_does_not_depend_on_the_number_of_resources` (une ressource ou toutes, mêmes
      requêtes)

## Non-régression (E10.5)

| Bug | Test |
| --- | --- |
| E1.1 | `ResourceDocumentTest::test_the_type_and_the_id_are_read_the_id_as_a_string` |
| E1.7 | `AttributesTest::test_the_default_attributes_of_a_model_leave_out_its_key_and_the_foreign_keys_of_its_relationships` |
| E1.2, E1.3 | `RelationshipsStageTest::test_an_empty_to_one_renders_data_null_and_an_empty_to_many_an_empty_list` |
| E1.4 | `RelationshipsStageTest::test_a_relationship_without_data_renders_its_links_and_meta_or_is_left_out` |
| E1.4b | `ResourceObjectTest::test_empty_members_are_left_out` (+ schéma JSON:API sur chaque document) |
| E1.5 | `IncludedRegistryTest::test_a_primary_resource_is_never_included_but_its_own_includes_are`, `DeepIncludeDeduplicationTest` |
| E1.6 | `RelationshipsStageTest::test_the_fieldset_of_the_type_restricts_the_relationships` |
| E1.12 | `User\ResourceTest::test_the_fieldset_of_a_type_restricts_its_included_resources_only` |
| E2.1 | `ValueTest::test_a_float_is_rounded_to_its_precision_or_truncated_on_demand` |
| E2.2 | `ValueTest::test_a_null_date_that_is_not_nullable_throws_when_strict` |
| E2.3 | `ValueTest::test_auto_when_has_does_not_stack_conditions_on_each_rendering` |
| E2.4 | `RelationTest::test_when_loaded_reads_the_attribute_of_a_resource_that_is_not_eloquent` (et `can`, `whenPivotLoaded`, `whenAppended`) |
| E2.5 | `User\ResourceTest::test_rendering_a_resource_twice_gives_the_same_document_and_leaves_it_unchanged` (**ajouté**) |
| E2.9 | `ValueTest::test_an_enum_of_a_declared_type_casts_the_raw_values_through_it` |

## Critères de sortie

- [ ] Couverture ≥ 90 %, MSI ≥ 80 % — couverture 96,3 % (2026-11-02) ; MSI à mesurer (Infection non installé ici)
- [ ] Suite verte sur toute la matrice PHP × Laravel, en CI GitHub (premier push de `v2.0`)
