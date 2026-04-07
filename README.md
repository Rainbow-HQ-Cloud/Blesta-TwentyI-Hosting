# 20i Hosting Module for Blesta

An open source Blesta server provisioning module that integrates with the [20i Reseller API](https://docs.20i.com/api). Automates the full lifecycle of shared Linux, WordPress, and Windows hosting accounts.

---

## Features

- **Provisioning lifecycle** — create, suspend, unsuspend, terminate, and change package
- **Domain handling** — attach an existing domain or register a new one via 20i at order time
- **WHMCS migration** — manually link an existing 20i package ID to a Blesta service (no re-provisioning required)
- **Client area tabs**
  - Account overview (status, disk/bandwidth usage, SSL certificate status)
  - One-click SSO login to the 20i control panel
  - DNS management (view, add, delete records)
  - Email account management (create, delete, change password)
  - FTP lock toggle and password reset
  - Addon domain management
  - Nameserver management (for domains registered via 20i)
- **Admin area tabs** — all client features, plus:
  - Raw API info (JSON dump of the 20i package object)
  - Cache purge
  - Manual sync from 20i
  - Admin SSO button (opens the 20i package management page directly)

---

## Requirements

| Requirement | Version |
| ----------- | ------- |
| Blesta | 5.x (v6 compatible by design) |
| PHP | 8.1 or higher |
| Composer | 2.x |
| 20i Reseller account | Active, with API key |

---

## Installation

### 1. Download / clone

```bash
cd /path/to/blesta/components/modules/
git clone https://github.com/Rainbow-HQ-Cloud/Blesta-TwentyI-Hosting twentyi_hosting
```

Or download the zip and extract it as `components/modules/twentyi_hosting/`.

### 2. Install dependencies

```bash
cd components/modules/twentyi_hosting/
composer install --no-dev --prefer-dist
```

### 3. Install in Blesta

1. Log in to your Blesta admin panel
2. Go to **Settings → Modules**
3. Find **20i Hosting** and click **Install**
4. Click **Add Server** and enter:
   - **Account Label** — a friendly name (e.g. "My 20i Account")
   - **API Key** — your General API key from [my.20i.com/reseller/api](https://my.20i.com/reseller/api)
5. Click **Add Account** — a connection test runs automatically

### 4. Create a package

1. Go to **Packages → Create Package**
2. Select **20i Hosting** as the module
3. Choose a **Package Type** from the dropdown (populated from the 20i API)
4. Save the package

### 5. Assign to a product

Assign the package to a product/service as you would any other Blesta module.

---

## Migrating from WHMCS

If you are moving clients from WHMCS and their hosting accounts already exist in 20i:

1. Create the service in Blesta with **"Do not use module"** checked at the bottom of the service creation form. This saves the service without triggering provisioning.
2. Note the **Package ID** for the client's hosting account from your 20i dashboard.
3. In Blesta, edit the service and paste the **20i Package ID** into the provided field.
4. Save — all management actions (suspend, SSO, DNS, email, etc.) will now work against the linked account.

---

## API Key

Generate your API key at **my.20i.com → Reseller Preferences → API**. The key is stored encrypted in the Blesta database.

---

## Development

### Running tests

```bash
composer install
vendor/bin/phpunit
```

### Code style

```bash
vendor/bin/phpcs --standard=PSR12 --extensions=php --ignore=vendor/ .
```

### Static analysis

```bash
vendor/bin/phpstan analyse
```

---

## Blesta v6 Upgrade

This module is designed for Blesta 5.x but adheres strictly to the public `Module` class API — no core hacks, no direct database access. When Blesta 6 is released:

1. Run the test suite and check for deprecation notices
2. Update `"version"` in `config.json` if needed
3. Run `composer update` if the 20i SDK has been updated

---

## Contributing

Pull requests are welcome. Please ensure:

- All tests pass (`vendor/bin/phpunit`)
- Code meets PSR-12 (`vendor/bin/phpcs`)
- PHPStan level 6 passes (`vendor/bin/phpstan analyse`)

---

## License

MIT — see [LICENSE](LICENSE).
