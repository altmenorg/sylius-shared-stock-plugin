# Sylius Shared Stock Plugin

One stock shared by all the variants of a product, counted in a base unit.

Sell a spice in 100 g, 250 g and 1 kg bags from the same stock of grams, rope by the metre,
or garlic in single bulbs and packs of two: each variant **consumes** a fixed amount of the
product's stock per unit sold, and availability follows what is left.

| Product stock | Variant | Consumption | Available |
|---|---|---|---|
| 380 g on hand | 100 g bag | 100 | yes (3) |
| | 250 g bag | 250 | yes (1) |
| | 500 g bag | 500 | no |

Built for Sylius 2 (tested on Sylius 2.3, Symfony 7.4, PHP 8.3, MariaDB). MIT licence.

## How it works

- The product carries the stock (`shared_stock_on_hand`, `shared_stock_on_hold`, a unit);
  each variant its `stock_consumption`. Shared stock is on when the stock on hand is not null.
- Shared-stock variants are **untracked** for Sylius (`tracked = false`): the core inventory
  code leaves them alone, nothing in Sylius is patched.
- Two decorators do the work:
  - the **order inventory operator** (`hold`, `sell`, `cancel`) sums the quantities per product
    before moving the stock: an order with 2 × 100 g and 1 × 250 g of the same product is one
    hold of 450 g, all or nothing across products;
  - the **availability checker** answers "in stock?" with free stock (on hand − on hold)
    divided by the consumption. It is the service used by the shop, the cart and the checkout
    validators.
- **Every movement is one SQL `UPDATE … WHERE` that carries its own check** ("hold 250 if 250
  are free"). Never read-compute-write: two concurrent checkouts can never both take the last
  250 g. After each update the entity is refreshed, so a later Doctrine flush does not write
  the old figures back. A counter (`shared_stock_version`) is bumped by every movement, because
  MySQL and MariaDB report 0 affected rows for an update that changes nothing, which would read
  as a refusal.
- Cancelling an order gives the stock back: the reservation of an unpaid order, the sold
  quantity of a paid or refunded one.

### Admin

- **Product form**, new "Shared stock" tab (configurable products, or any product already
  using it): switch on or off, base unit, stock on hand; reserved and available figures, and
  each variant with its consumption and the units still available.
- **Variant form**, "Inventory" tab: the consumption, with a note on the shared stock it draws on.
- **Product list**: a "Shared stock" column. **Variant list**: units available and consumption
  instead of "Not tracked".
- **Editing the stock in the admin goes through the same checked `UPDATE`.** The value typed is
  saved as a correction from the value shown when the page was opened (+50, −120), applied to
  the stock as it is when you save: a sale made meanwhile is kept, never overwritten, and the
  admin is told. A correction that would take the stock below zero, or switching off a stock
  that orders still hold, is refused.
- Translations: English and French.

## Installation

1. Require the package. It is not on Packagist: declare the GitHub repository.

   ```json
   {
       "repositories": [
           { "type": "vcs", "url": "https://github.com/altmenorg/sylius-shared-stock-plugin" }
       ],
       "require": {
           "altmenorg/sylius-shared-stock-plugin": "^0.1"
       }
   }
   ```

2. Register the bundle in `config/bundles.php` (Symfony Flex may do it for you):

   ```php
   Altmenorg\SyliusSharedStockPlugin\AltmenorgSyliusSharedStockPlugin::class => ['all' => true],
   ```

3. Add the interfaces and traits to your product and variant entities:

   ```php
   use Altmenorg\SyliusSharedStockPlugin\Model\SharedStockProductInterface;
   use Altmenorg\SyliusSharedStockPlugin\Model\SharedStockProductTrait;

   #[ORM\Entity]
   #[ORM\Table(name: 'sylius_product')]
   class Product extends BaseProduct implements SharedStockProductInterface
   {
       use SharedStockProductTrait;
   }
   ```

   ```php
   use Altmenorg\SyliusSharedStockPlugin\Model\SharedStockVariantInterface;
   use Altmenorg\SyliusSharedStockPlugin\Model\SharedStockVariantTrait;

   #[ORM\Entity]
   #[ORM\Table(name: 'sylius_product_variant')]
   class ProductVariant extends BaseProductVariant implements SharedStockVariantInterface
   {
       use SharedStockVariantTrait;
   }
   ```

   The traits use Doctrine attributes. With XML mapping, map the same columns yourself.

4. Run the migrations:

   ```bash
   bin/console doctrine:migrations:migrate
   ```

   The plugin's migration only adds the columns that are missing, so a shop that already has
   them (same names) adopts the plugin without moving any data.

5. Clear the cache.

## Configuration

```yaml
# config/packages/altmenorg_sylius_shared_stock.yaml
altmenorg_sylius_shared_stock:
    # Hold and sell beyond the stock on hand (backorders). Default: false.
    allow_negative: false
```

## Usage

Set up a product in the admin (Shared stock tab, then a consumption on each variant), or in
code, for imports and fixtures:

```php
$product->setSharedStockUnit('g');
$product->setSharedStockOnHand(5000);      // new product only: see below
foreach ($product->getVariants() as $variant) {
    $variant->setTracked(false);
}
$variant100g->setStockConsumption(100);
$variant250g->setStockConsumption(250);
```

On a live product, move the stock with the store, never with the setters:

```php
use Altmenorg\SyliusSharedStockPlugin\Exception\InsufficientSharedStockException;
use Altmenorg\SyliusSharedStockPlugin\Inventory\SharedStockStoreInterface;

public function __construct(private SharedStockStoreInterface $store) {}

$this->store->adjust($product, 'increase', 2500);          // delivery
$this->store->adjust($product, 'replace', 4180);           // inventory count
try {
    $this->store->adjust($product, 'decrease', 300);       // refused below zero
} catch (InsufficientSharedStockException $e) {
}
$free = $this->store->available($product);                 // on hand − on hold, read from the database
```

`adjust(..., 'decrease', $n, allowNegative: true)` lets a back office take the stock below
zero, whatever the shop's setting.

Twig helpers, safe on any product or variant: `sylius_shared_stock_product(productOrVariant)`
(the product if it uses a shared stock, else null), `sylius_shared_stock_free(productOrVariant)`,
`sylius_shared_stock_variant_units(variant)`.

## Limits

- The cart checks each line on its own: two lines of the same product may together ask for more
  than the free stock. The hold at checkout completion, which sums them, refuses the order.
- The admin "Inventory" page of Sylius lists tracked variants only: shared-stock variants are
  not in it.
- A variant without consumption keeps its own Sylius stock, even on a shared-stock product.
- Saving a variant that has a consumption, on a shared-stock product, sets it as untracked.
- The stock is an integer in the base unit: choose a unit small enough (grams rather than kilos).
- Movements are written in SQL against the product table: Doctrine ORM with a SQL database is
  required.

## Tests

```bash
composer install
vendor/bin/phpunit
```

The unit tests cover the availability rule, the per-product summing and the all-or-nothing
hold, the admin helpers and the grid changes. The atomic updates themselves need a real
database: check them in your shop with concurrent holds (for example, 20 processes holding
100 g each from 1000 g must give 10 holds, 10 refusals and 1000 g reserved).

To run the tests from a Sylius application that has the plugin installed:

```bash
SHARED_STOCK_AUTOLOAD=/path/to/shop/vendor/autoload.php /path/to/shop/vendor/bin/phpunit \
    -c vendor/altmenorg/sylius-shared-stock-plugin/phpunit.xml.dist
```

## Licence

MIT, see [LICENSE](LICENSE).
