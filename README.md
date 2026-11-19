# Tony_CurrencyConverter

A Magento 2 module that adds a storefront **currency exchange converter** page powered by the
[Frankfurter API](https://frankfurter.dev). Shoppers can:

- pick any currency Frankfurter supports as **From** and **To**, including precious metals and cryptocurrencies
- **swap** the two currencies with one click
- enter an **amount** and see it converted, plus the rate in both directions
- see a **history chart** of the rate over 1M / 3M / 6M / 1Y / 5Y, with the % change over that period
- share a link that reopens the same view (`?from=USD&to=INR&range=1Y&amount=250`)

The page lives at **`/currency-converter`** and has a link in the storefront footer.

## Requirements

| | Version |
|---|---|
| Magento Open Source / Adobe Commerce | 2.4.x (built and tested on 2.4.6-p15) |
| PHP | 8.1+ |
| Theme | Luma or Blank (uses Knockout/RequireJS) |
| Outbound HTTPS | `api.frankfurter.dev` |

No database tables are created and no third-party Composer packages are needed. The chart uses
Chart.js 3.9.1, which already ships with Magento in `lib/web/chartjs`.

## Installation

### Option A: copy into `app/code`

```bash
mkdir -p app/code/Tony
git clone <this-repo-url> app/code/Tony/CurrencyConverter
bin/magento module:enable Tony_CurrencyConverter
bin/magento setup:upgrade
bin/magento cache:flush
```

In production mode, also run:

```bash
bin/magento setup:di:compile
bin/magento setup:static-content:deploy
```

### Option B: Composer (VCS repository)

```bash
composer config repositories.tony-currency-converter vcs <this-repo-url>
composer require tony/module-currency-converter:^1.0
bin/magento module:enable Tony_CurrencyConverter
bin/magento setup:upgrade
```

Then open `https://<your-store>/currency-converter`.

## Configuration

**Stores → Configuration → General → Currency Converter**

| Setting | Default | Scope |
|---|---|---|
| Enable Converter Page | Yes | store view |
| Default "From" Currency | USD | store view |
| Default "To" Currency | EUR | store view |
| Include Cryptocurrencies | Yes | store view |
| Show Link in Footer | Yes | store view |
| API Base URL | `https://api.frankfurter.dev/v2` | global |
| Request Timeout (seconds) | 10 | global |
| Currency list / current rate / history cache lifetime | 24 h / 1 h / 6 h | global |

When the page is disabled, the page and its JSON endpoints return 404 and the footer link is hidden.

Rates are cached in their own cache type, **Currency Converter Rates** (`currency_converter`), which can
be flushed or disabled on its own under **System → Cache Management**.

## How it works

```
Browser (Knockout UI component + Chart.js)
   │  GET /currency-converter/rate?from=USD&to=INR
   │  GET /currency-converter/history?from=USD&to=INR&range=3M
   ▼
Controller\Rate / Controller\History   (thin JSON actions, map errors → HTTP status)
   ▼
Api\ExchangeRateServiceInterface → Model\ExchangeRateService
   ├─ validates codes against the currency list and checks the range is allowed
   ├─ caches responses (Model\Cache\Type)
   └─ Model\Client\FrankfurterClient → https://api.frankfurter.dev/v2
```

- **Every API call goes through Magento.** The browser never calls Frankfurter directly. This keeps
  validation, caching and error handling in one place, and needs no CSP whitelist changes.
- **The currency list is rendered into the page** by `ViewModel\Converter`, so the dropdowns are
  filled without an extra request. Rates and history load with AJAX, so the page itself is safe to
  cache in the full-page cache.
- **Long ranges are down-sampled by the API** (`group=week` for 1Y, `group=month` for 5Y), which
  keeps every chart under about 180 points.
- **The service contract** (`Api\ExchangeRateServiceInterface` and its `Api\Data` DTOs) can be
  reused by other modules, e.g. to show prices in a second currency.

### JSON endpoints

| Endpoint | Success response | Errors |
|---|---|---|
| `GET /currency-converter/rate?from=USD&to=EUR` | `{"from":"USD","to":"EUR","rate":0.91,"date":"2026-11-19"}` | 400 bad currency, 502 provider down |
| `GET /currency-converter/history?from=USD&to=EUR&range=3M` | `{"from":"USD","to":"EUR","range":"3M","points":[{"date":"…","rate":0.91},…]}` | 400 bad currency or range, 502 provider down |

Allowed ranges: `1M`, `3M`, `6M`, `1Y`, `5Y`.

### Error handling

| Situation | What the shopper sees |
|---|---|
| Frankfurter unreachable when the page loads | "Exchange rates are temporarily unavailable" message instead of the form |
| Rate or history request fails | Inline error in that section; the rest of the page keeps working |
| Same currency on both sides | Rate 1, with a note; no API calls are made |
| Unknown code in a shared URL | Falls back to the configured default |
| Selection changed while a request is in flight | The stale response is ignored |

Failures are logged to `var/log/system.log` / `exception.log` with the URL and HTTP status.

## Module layout

```
Api/                         Service contract + data interfaces
Block/FooterLink.php         Footer link, hidden when the page is disabled
Controller/                  Page action + JSON actions (shared AbstractJsonAction)
Exception/                   ProviderUnavailableException
Model/Client/                Frankfurter HTTP client
Model/Cache/Type.php         Dedicated cache type
Model/Config.php             Typed config access
Model/ExchangeRateService.php
Model/HistoryRange.php       Range → start date + API grouping
ViewModel/Converter.php      Initial JS config for the page
etc/                         module, routes, DI, cache, ACL, admin config
view/frontend/               layout, template, Knockout component + template, LESS
Test/Unit/                   PHPUnit tests
```

## Tests

From the Magento root:

```bash
cd dev/tests/unit
../../../vendor/bin/phpunit -c phpunit.xml.dist ../../../app/code/Tony/CurrencyConverter/Test/Unit
```

There are 26 tests. They cover the HTTP client (URL building, HTTP and transport errors, malformed
JSON), the service (validation, crypto filter, caching on and off, history sorting) and the controllers'
HTTP status mapping.

Coding standard (Magento2, no errors or warnings, including at `--severity=1`):

```bash
cd app/code/Tony/CurrencyConverter
../../../../vendor/bin/phpcs
```

The module ships a `phpcs.xml` ruleset that points at the Magento2 standard. IDE phpcs
extensions pick it up automatically. Without it, phpcs falls back to its built-in PEAR standard
and reports non-Magento rules such as "Missing @category tag" or "Missing short description".

## Known limitations

- Frankfurter publishes **reference rates** once or a few times a day. They are not live dealing
  quotes, and the page says so.
- The currency dropdowns are native `<select>` elements grouped into Currencies, Precious metals and
  Cryptocurrencies. Keyboard type-ahead works on the code (e.g. typing "I-N-R"), but there is no
  free-text search box.
- Built for Luma/Blank. A Hyvä theme would need an Alpine.js version of the template.

## License

MIT
