# Feuille de route — laravel-json-api V2.0

Ce dossier suit la réécriture de **v1.9.0** à **v2.0.0**. Chaque EPIC a son document avec la liste de ses livrables ;
le tableau ci-dessous en est calculé automatiquement. Le dossier entier (documents et outils de `bin/`) est
**temporaire** : il sera supprimé avant le tag `v2.0.0` (E11.7).

**Cibles :** PHP `^8.3`, Laravel `^12.46|^13.0` (puis `^14.0`).
**Principe directeur :** la ressource **décrit**, la définition **se compile une fois**, le moteur de rendu
**décide**, le document **s'écrit**. On garde la facilité d'écriture des ressources ; tout le reste peut être
restructuré.
**Modèles :** `openapi-writer` et `laravel-openapi-generator` (architecture, patterns, outillage), sans aucune
dépendance vers eux.

## Progression

<!-- progress:start -->
**Avancement global : `██████░░░░` 67 % (106 / 158 livrables)**

| EPIC | Jalon | Statut | Avancement | Livrables |
| --- | --- | --- | --- | --- |
| [E0 — Socle, outillage et conventions](E00-socle.md) | M0 | Terminé | `██████████` 100 % | 18 / 18 |
| [E1 — Conformité à la spec JSON:API 1.1](E01-conformite-spec.md) | M2 | Terminé | `██████████` 100 % | 15 / 15 |
| [E2 — Corrections de bugs et nettoyage de la dette](E02-bugs-dette.md) | M2 | Terminé | `██████████` 100 % | 18 / 18 |
| [E3 — Modèle de définition et évolution du FakeModel](E03-definition.md) | M1 | En cours | `████████░░` 89 % | 17 / 19 |
| [E4 — Moteur de rendu](E04-rendu.md) | M2 | Terminé | `██████████` 100 % | 12 / 12 |
| [E5 — Relations et chargement](E05-relations.md) | M3 | Terminé | `██████████` 100 % | 15 / 15 |
| [E6 — API d'écriture des ressources (DX)](E06-dx.md) | M1, M3 | En cours | `█████░░░░░` 53 % | 8 / 15 |
| [E7 — Couche HTTP](E07-http.md) | M4 | À faire | `░░░░░░░░░░` 0 % | 0 / 10 |
| [E8 — Positionnement face au JSON:API natif de Laravel](E08-natif-laravel.md) | M5 | En cours | `█░░░░░░░░░` 14 % | 1 / 7 |
| [E9 — Contrat public pour `laravel-openapi-generator`](E09-openapi-generator.md) | M5 | À faire | `░░░░░░░░░░` 0 % | 0 / 7 |
| [E10 — Stratégie de tests et qualité](E10-tests.md) | M0 → M5 | En cours | `██░░░░░░░░` 20 % | 2 / 10 |
| [E11 — Documentation, migration et release](E11-release.md) | M5 | À faire | `░░░░░░░░░░` 0 % | 0 / 12 |
<!-- progress:end -->

## Mettre à jour la progression

1. Cocher le livrable dans le document de l'EPIC (`- [ ]` devient `- [x]`).
2. Lancer `php roadmap-v2/bin/progress.php` : le tableau ci-dessus est régénéré à partir des cases cochées.
3. Committer les deux fichiers ensemble. `php roadmap-v2/bin/progress.php --check` échoue si le tableau n'est pas à
   jour.

Le statut est déduit des cases : aucune cochée = **À faire**, une partie = **En cours**, toutes = **Terminé**.
Les cases dans les blocs de code ne comptent pas.

Légende des livrables : 🔴 rupture de compatibilité (à reporter dans `UPGRADE.md`), sans marque = non cassant.

## Jalons

| Jalon | Contenu |
| --- | --- |
| **M0 — Socle** | [E0](E00-socle.md), socle d'[E10](E10-tests.md), `docs/architecture.md` validé, CI verte sur PHP 8.3+ × Laravel 12 / 13 |
| **M1 — Définition** | [E3](E03-definition.md) (Probe, `ResourceDefinition`, cache, `jsonapi:check`), E6.1 (descripteurs immuables) |
| **M2 — Rendu** | [E4](E04-rendu.md), [E1](E01-conformite-spec.md), [E2](E02-bugs-dette.md) : là où se concentrent les ruptures visibles du JSON |
| **M3 — Relations et DX** | [E5](E05-relations.md), [E6](E06-dx.md) |
| **M4 — HTTP** | [E7](E07-http.md) |
| **M5 — Écosystème** | [E8](E08-natif-laravel.md), [E9](E09-openapi-generator.md), [E11](E11-release.md) → **v2.0.0** |

La release 1.10 de dépréciation (E11.1) se prépare dès que les ruptures sont figées.

## Dépendances

| EPIC | Dépend de |
| --- | --- |
| E0 — Socle | — |
| E1 — Conformité spec | E4, E10.1 |
| E2 — Bugs et dette | E0 |
| E3 — Définition | E0 |
| E4 — Rendu | E3 |
| E5 — Relations | E3, E4 |
| E6 — DX | E3 |
| E7 — HTTP | E3, E4 |
| E8 — Natif Laravel | E4, E11 |
| E9 — Contrat OpenAPI | E3 |
| E10 — Tests | E0 |
| E11 — Release | tous |

## Outils (`bin/`)

| Script | Rôle |
| --- | --- |
| `bin/progress.php` | Régénère le tableau de progression (`--check` pour vérifier qu'il est à jour) |

## Décisions

| Date | Décision |
| --- | --- |
| 2026-10-29 | Version majeure **v2.0.0** à partir de v1.9.0, avec une release 1.10 de dépréciation (Q8) |
| 2026-10-29 | `FakeModel` conservé et formalisé en `Probe` + `ResourceDefinition` compilée et cachée ; attributs PHP, définition statique imposée et analyse statique écartés (E3) |
| 2026-10-30 | Q1 — Indépendant du `JsonApiResource` natif de Laravel, interop aux frontières (E8) |
| 2026-10-30 | Q2 — PHP `^8.3` ; Laravel 12 conservé tant qu'il est peu contraignant (`^12.46\|^13.0`), retiré dans une mineure après février 2027 (E0.10) |
| 2026-10-30 | Q3 — Type par défaut inchangé (`kebab-singular`), convention configurable (E6.3) |
| 2026-10-30 | Q4 — Linkage des relations non incluses conservé (la spec l'autorise sans l'imposer), sans N+1 ; option `relationship.linkage` (E5.1) |
| 2026-10-30 | Q5 — `toMeta()` renommé `toDocumentMeta()` et lève une exception en V2 ; `toResourceMeta()` inchangé (E6.4b) |
| 2026-10-30 | Q6 — `sort`, `filter`, `page` hors périmètre : on ne s'écarte pas de Laravel (E7) |
| 2026-10-30 | Q7 — Code, `docs/` et `docs/architecture.md` en anglais (ce dossier de suivi reste en français) |
| 2026-10-30 | Sparse fieldsets : `fields[type]` filtre attributs et relations par défaut (conforme) ; `fields.relationships = 'keep'` rétablit le comportement 1.x, écart à la spec assumé et opt-in (E1.6) |
| 2026-10-30 | Compilation en une seule passe : le diagnostic « attributs dépendant de la requête » (deux passes identiques) est abandonné, la règle « pas de `if`, `when()` / `applyWhen()` » est documentée (E3.6, E3.8) |
| 2026-10-30 | Définitions paresseuses : le boot ne pose que la configuration et le chemin du fichier compilé, lu au premier `Definitions::for()` (E3) |
| 2026-10-30 | Les libs `openapi-writer` et `laravel-openapi-generator` sont des modèles, sans dépendance ; le générateur nous consomme indirectement (E9) |
| 2026-10-30 | Traits autorisés uniquement dans `Resources/` (façade utilisateur), interdits ailleurs (E0) |
| 2026-10-30 | Dette 1.x gelée dans `phpstan-baseline.neon` (PHPStan max, phpat, disallowed calls) : la baseline ne peut que diminuer et doit être vide avant 2.0.0 (E0.2b) |
| 2026-10-30 | Infection installé globalement (comme le générateur), `phpUnit.customPath` explicite ; MSI de départ 83,33 % : job bloquant sur `master`, informatif ailleurs (E0.6b) |
| 2026-10-30 | `docs/architecture.md` validé : il sert de contrat pour la suite (M1 et au-delà) |
| 2026-10-30 | Pas de JSON Schema 1.1 officiel : le schéma de jsonapi.org (2020-12) est copié dans les tests et complété des membres 1.1 ; les 12 documents Feature qu'il rejette sont les tests rouges d'E1 (E10.1) |
| 2026-10-30 | Meta de document : uniquement celles des ressources primaires (jamais des incluses) ; dans une collection, la première ressource qui pose une clé la garde, et le `toMeta()` de la collection l'emporte sur celles des ressources (E4) |
| 2026-10-30 | M0 clos : la baseline (1 256 erreurs) est vidée par chaque EPIC sur le code qu'il réécrit, vide avant le tag (E11.9) ; `fixtures:update` et `benchmark` livrés avec E10.2 et E4.7 ; CI GitHub confirmée au premier push (E10) |
