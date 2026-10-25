# Changelog

## 0.1.0 — 2026-10-25

First release.

### Added

- Shared stock on the product (`shared_stock_on_hand`, `shared_stock_on_hold`,
  `shared_stock_unit`, `shared_stock_version`) and stock consumption on the variant
  (`stock_consumption`), as Doctrine traits and interfaces.
- `SharedStockStore`: hold, release, sell, give back, adjust (`replace`, `increase`,
  `decrease`), enable, disable; each one a single checked SQL `UPDATE`, the entity refreshed
  afterwards.
- Decorators of the order inventory operator (quantities summed per product, all-or-nothing
  hold) and of the availability checker (free stock divided by consumption).
- `allow_negative` setting (default `false`).
- Admin: "Shared stock" tab on the product form, consumption on the variant form, "Shared
  stock" column in the product list, units available and consumption in the variant list.
  Stock edited in the admin is saved as a correction through the same checked `UPDATE`: a sale
  made while the page was open is kept.
- Migration that adds only the missing columns.
- Translations: English, French.
- Unit tests.
