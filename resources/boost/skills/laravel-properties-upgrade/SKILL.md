---
name: laravel-properties-upgrade
description: Upgrade an app to a new major version of tobymaxham/laravel-properties (e.g. 1.x to 2.0). Use when the user asks to upgrade, update or migrate laravel-properties, or when composer.json requires an older major version of tobymaxham/laravel-properties than the latest one.
---

# Upgrade tobymaxham/laravel-properties

Step by step instructions for AI agents to upgrade a Laravel app that uses `tobymaxham/laravel-properties`.
The human readable guide is [UPGRADING.md](https://github.com/TobyMaxham/laravel-properties/blob/main/UPGRADING.md).

## 1. Find the right guide

1. Read the installed version: `composer show tobymaxham/laravel-properties` (or `composer.lock`).
2. Pick the guide for the upgrade. Run them one after another, if the app skips a major version.

| From | To  | Guide                                                |
|------|-----|------------------------------------------------------|
| 1.x  | 2.0 | [references/1.x-to-2.0.md](references/1.x-to-2.0.md) |

If you read this file from GitHub, the guides are in
`https://raw.githubusercontent.com/TobyMaxham/laravel-properties/main/resources/boost/skills/laravel-properties-upgrade/references/`.

## 2. Rules for every upgrade

- **Ask the user before decisions that change behavior in production**, e.g. whether code relies on the old behavior of `getProperty()`. Never guess them. Each guide marks these steps with **ASK**.
- Do not change the stored data (no data migrations) unless the user agrees.
- Never print secrets like `APP_KEY`. Properties can contain personal data: do not print stored values of production databases.
- Only change code that uses this package. Do not refactor unrelated code.
- Do not commit. Leave the changes for the user to review.
- Run the test suite (and static analysis, if the app uses it) after the upgrade and fix failures caused by the upgrade.

## 3. Report

Finish with a short summary for the user:

1. Version before and after.
2. Decisions the user made (or still has to make).
3. Files you changed.
4. Migrations you created and whether they still have to run.
5. Things you could not check automatically, e.g. code that reads the JSON column directly or other apps using the same database.
6. Test results.
