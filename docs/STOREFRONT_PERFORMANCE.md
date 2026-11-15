# Storefront performance evidence

Measured locally on 2026-11-15 with synthetic, disposable test data. These are
query-count and serialized-card-payload measurements, **not** production latency,
load-capacity, full HTTP response size or a new Lighthouse score.

| Fixture                                                                  |             Before |            After |
| ------------------------------------------------------------------------ | -----------------: | ---------------: |
| Cart summary, 20 distinct physical products with covers                  |         45 queries |        6 queries |
| 12 catalog cards, 1,000 repeated description sentences and 6 images each | 335,575 JSON bytes | 3,512 JSON bytes |

The baseline assertions were run before optimization and failed at these measured
values. After optimization, SQLite and isolated MySQL runs both recorded 6 queries
and 3,512 bytes. The cart fixture has no selected variants or membership plans;
mixed carts may need additional fixed eager-load/plan queries.

## Changes and safety boundaries

- Cart display eagerly loads products with variant counts, one ordered cover,
  selected variants and their images. Active reservation quantities are grouped
  in one query by the actual product/variant pairs; active membership plans are
  fetched in a batch.
- Homepage, catalog and related-product cards use an explicit compact projection:
  identity, category, prices, stock, variant count and at most one cover image.
  Full descriptions and galleries remain on product detail pages.
- Covers retain ascending position, then ascending ID tie-breaking.
- Reads are fresh on every request. No persistent inventory, price, membership,
  authorization or payment-state cache was introduced.
- The authoritative `selection()` and locking `held(..., true)` checkout paths
  are unchanged. Cart availability remains advisory and does not reserve stock.

## Reproducing

PowerShell, using the project's disposable test configuration:

```powershell
$env:STOREFRONT_METRICS='1'
php artisan test --filter='^Tests\\Feature\\StorefrontPerformanceTest'
```

For the real MySQL dialect, set `MYSQL_CONCURRENCY_TEST=1` and run
`php vendor/phpunit/phpunit/phpunit --filter=MySqlStorefrontPerformance --testdox`.
These tests create/drop only randomly named `lr_yazid_test_[a-f0-9]{16}` databases;
never point test operations at a production database account.

Regressions cover canonical/batched display parity for inactive/foreign/missing
variant selections, inactive/missing membership plans, quantity errors, sale
boundaries, fresh stock/price changes, active/expired/released reservations and
product/variant reservation isolation. Additional checks preserve cover ties,
category/variant-count fields, full detail galleries and compact related cards.
Card query count stays bounded as the fixture grows from one to twelve products.

Chrome acceptance exercised catalog filtering, detail/related cards and a separate
initially empty guest cart: add one sample item, update quantity to two, verify MYR
totals, then remove that same item. No order, payment, stock reservation or unrelated
customer/administrator cart was mutated. No console errors or warnings occurred.

## Remaining production acceptance

Measure the full personalized HTTP payload, server/query timings, indexes and
execution plans against realistic production-scale catalog/reservation volumes.
The batched reservation predicate grows with distinct cart selections; fixed query
count alone does not prove acceptable execution time or large-cart capacity.
Run cold/warm mobile Lighthouse and concurrent read/checkout tests on the actual
HTTPS deployment, including images, compression, static caching and queue load.
The earlier local Lighthouse score is not replaced by these microbenchmarks.
