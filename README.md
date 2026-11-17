# Bunting

Feature flags for Craft on Laravel Pennant — flip them in the CP, scope them to users, groups, sites or a percentage.

> **Status:** scaffold — nothing user-facing yet. See [PLAN.md](PLAN.md).

## Requirements

- Craft CMS 6.0.0-beta.1 or later (native Craft 6 plugin, no Yii 2 adapter)
- PHP 8.5
- Laravel integration: laravel/pennant. Pennant v1.28, Laravel 13 compatible, database driver.

## Editions and pricing

single edition — Free (provisional).

## Development

```bash
composer install
composer test        # Pest
composer phpstan     # Larastan level 6
composer check-cs    # Pint
```

Tested in the Craft 6 harness at `~/Sites/plugin-testing-craft6` (`bin/plugin-wire craft-bunting --install`).
