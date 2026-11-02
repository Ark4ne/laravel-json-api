# E11 — Documentation, migration et release

**Objectif.** Publier la V2 avec un chemin de migration simple : release 1.x de transition, guide d'upgrade, skills
d'agent et site de documentation à jour.

**Dépend de :** tous les autres EPICs
**Jalon :** M5
**Note :** E11.1 (release 1.10) dès que les ruptures sont figées.

## Livrables

### Transition

- [ ] **E11.1** Release **1.10** : `@deprecated` + `trigger_deprecation()` sur tout ce qui disparaît ou change de sens
      en V2
- [ ] **E11.1b** 1.10 : activation optionnelle par config des nouveaux comportements (`id` en string,
      `data: null`…), pour migrer progressivement

### Migration

- [x] **E11.2** `UPGRADE.md` 1.x → 2.0 exhaustif : une section par rupture (🔴 des autres EPICs), avec exemple
      avant / après
- [ ] **E11.4** Règles Rector optionnelles pour les renommages mécaniques (`withLinks` → `links`,
      `toMeta` → `toDocumentMeta`…)

### Documentation

- [x] **E11.3** Skill d'agent `skills/laravel-json-api/` (API publique), testé par un `SkillTest`
- [x] **E11.3b** Skill d'upgrade `skills/laravel-json-api-upgrade/`
- [ ] **E11.5** Site `laravel-json-api-docs` (Nuxt) : section V2, doc versionnée 1.x / 2.x
- [ ] **E11.6** README allégé (renvoi vers le site), `CHANGELOG.md` au format *Keep a Changelog*

### Release

- [x] **E11.9** `phpstan-baseline.neon` vide (vidée au fil des EPIC, E0.2b)
- [ ] **E11.7** Supprimer le dossier `roadmap-v2/` (suivi et outils temporaires) avant le tag
- [ ] **E11.8** Tag `v2.0.0` et release notes

## Critères de sortie

- [ ] Une application v1.10 sans avertissement de dépréciation passe en V2 en suivant `UPGRADE.md`
