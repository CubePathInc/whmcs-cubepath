# CubePath WHMCS Module

WHMCS provisioning and addon module for [CubePath](https://cubepath.com). It automates the VPS lifecycle, gives end clients a self-service panel and creates WHMCS products from CubePath plans.

[![CI](https://github.com/CubePathInc/whmcs-cubepath/actions/workflows/ci.yml/badge.svg)](https://github.com/CubePathInc/whmcs-cubepath/actions/workflows/ci.yml)

## Requirements

- WHMCS 8.x or 9.x
- PHP 7.4 or newer (whichever version your WHMCS release supports)
- A CubePath API token, created from [my.cubepath.com](https://my.cubepath.com)

The [CubePath PHP SDK](https://github.com/CubePathInc/cubepath-php-sdk) and its dependencies are bundled in the release archive.

## Installation

### 1. Upload the module files

Download `whmcs-cubepath-<version>.zip` from the [latest release](https://github.com/CubePathInc/whmcs-cubepath/releases/latest) and extract it over your WHMCS root directory:

```bash
cd /path/to/whmcs
unzip whmcs-cubepath-<version>.zip 'modules/*'
```

This creates `modules/addons/cubepath/` (including `vendor/`) and `modules/servers/cubepath/`.

### 2. Activate the addon module

1. Go to **WHMCS Admin > System Settings > Addon Modules**
2. Find **CubePath** and click **Activate**
3. Enter your **API Token**
4. Set the **Default Project ID** (find it in your CubePath dashboard)
5. Grant admin access and click **Save Changes**

### Installing from source

To run the module straight from a clone of this repository, install the Composer dependencies yourself:

```bash
cp -r addons/cubepath/  /path/to/whmcs/modules/addons/cubepath/
cp -r servers/cubepath/ /path/to/whmcs/modules/servers/cubepath/
composer install --no-dev --working-dir=/path/to/whmcs/modules/addons/cubepath
```

## Module Components

### Addon Module (Admin Panel)

Access via **WHMCS Admin > Addons > CubePath**.

| Page | Description |
|------|-------------|
| **Dashboard** | API connection status, configured project and catalog size |
| **Product Creator** | Create a WHMCS product for one CubePath plan, or for every available plan at once |
| **Products** | List CubePath products with their service count, and sync new locations and templates into them |
| **Locations** | Choose which locations clients can order |
| **Templates** | Choose which operating systems and applications clients can order |

Plans, locations and templates come from the CubePath API, so new ones show up without updating the module.

### Server Module (Provisioning)

Handles the full VPS lifecycle:

| Function | Action |
|----------|--------|
| **Create Account** | Provisions a new VPS with the selected plan, location, and OS template |
| **Suspend Account** | Stops the VPS |
| **Unsuspend Account** | Starts the VPS |
| **Terminate Account** | Destroys the VPS and releases resources |
| **Change Package** | Resizes the VPS to a different plan |

Admin quick actions: **Start**, **Reboot**, **Stop**

### Client Area

Self-service panel available to end clients:

| Tab | Features |
|-----|----------|
| **Overview** | VPS status, IPs, plan info, power controls, label editing, password change |
| **Backups** | Create, restore, delete backups; configure backup schedule and retention |
| **DNS** | Create zones, manage A/AAAA/CNAME/MX/TXT/SRV records |
| **SSH Keys** | Add and remove SSH public keys |
| **Firewall** | View and assign firewall groups to the VPS |
| **Reinstall OS** | Reinstall with a different OS template |
| **ISO** | Mount/unmount ISO images |
| **Console** | VNC console access (when available via API) |

## Creating Products

### Automatic (recommended)

1. Go to **Addons > CubePath > Product Creator**
2. Pick a plan, product group and price and click **Create product**, or use **Create all plans**
3. The module sets up:
   - Module settings: API token, plan name and project ID
   - Admin-only custom fields: VPS ID, IP Address, Project ID
   - Configurable options for the location and operating system, limited to the locations where the plan is in stock and the templates that fit in its memory

Prices of the configurable options start at zero; adjust them from **System Settings > Configurable Options** if you want to charge for a location or template.

### Manual

1. Create a product in **WHMCS Admin > System Settings > Products/Services**
2. Set **Module** to `cubepath`
3. Configure:
   - **Config Option 1**: API Token
   - **Config Option 2**: Plan name (e.g., `rz.nano`)
   - **Config Option 3**: Project ID
4. Create a configurable option group linked to the product with two dropdown options:
   - `location|Location`, with sub-options named `<location_name>|<label>`, e.g. `us-mia-1|Miami, USA`
   - `template|Operating System`, with sub-options named `<template_name>|<label>`, e.g. `ubuntu-24|Ubuntu 24.04`

## Directory Structure

```
whmcs-cubepath/
├── bin/build-release.sh         # Builds the release zip
├── addons/cubepath/
│   ├── cubepath.php            # Addon entry point (config, activate, output)
│   ├── hooks.php               # WHMCS hooks
│   ├── composer.json           # SDK dependency and autoload (vendor/ is built, not committed)
│   ├── lib/                    # Settings, Catalog, Products, Admin\Controller
│   ├── lang/                   # Language files
│   ├── views/admin/            # Admin page templates
│   └── whmcs.json, logo.png    # Metadata shown in Apps & Integrations
│
└── servers/cubepath/
    ├── cubepath.php             # Server module entry point
    ├── loader.php               # Autoloader
    ├── hooks.php                # Server hooks
    ├── class/                   # Provisioning & renderer classes
    ├── controller/              # Client area controllers (8)
    ├── helper/                  # CubepathHelper, LangHelper, SessionHelper
    ├── lang/                    # Language files
    ├── template/                # Smarty templates
    └── whmcs.json, logo.png     # Metadata shown in Apps & Integrations
```

## SDK Method Mapping

| WHMCS Action | SDK Call |
|---|---|
| Create VPS | `$client->vps()->create($projectId, [...])` |
| Stop VPS | `$client->vps()->power($vpsId, 'stop')` |
| Start VPS | `$client->vps()->power($vpsId, 'start')` |
| Reboot VPS | `$client->vps()->power($vpsId, 'reboot')` |
| Destroy VPS | `$client->vps()->destroy($vpsId)` |
| Resize VPS | `$client->vps()->resize($vpsId, $planName)` |
| Reinstall OS | `$client->vps()->reinstall($vpsId, $templateName)` |
| Manage Backups | `$client->vps()->backups()->list/create/restore/delete()` |
| Manage DNS | `$client->dns()->createZone/listRecords/createRecord()` |
| Manage SSH Keys | `$client->sshKeys()->list/create/delete()` |
| Manage Firewall | `$client->firewall()->list/assignToVPS()` |
| Mount ISO | `$client->vps()->isos()->mount/unmount()` |

## Troubleshooting

### API connection fails
- Verify your API token is correct and active
- Check that your server can reach `https://api.cubepath.com`
- Review WHMCS module log: **Utilities > Logs > Module Log**

### VPS not provisioned
- Ensure the product has valid config options (API token, plan name, project ID)
- Check that the selected location and template are available for the plan
- Enable WHMCS module debug logging for detailed error output

### Client area not loading
- Verify both `modules/addons/cubepath/` and `modules/servers/cubepath/` are installed
- Check file permissions (readable by web server)
- Ensure `modules/addons/cubepath/vendor/` exists (it ships in the release zip; run `composer install --no-dev` if installing from source)

## Development

```bash
composer install --working-dir=addons/cubepath
find addons servers -path '*/vendor' -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
```

Dependencies are locked against PHP 7.4 (`config.platform.php` in `addons/cubepath/composer.json`), so the bundled `vendor/` runs on every supported PHP version. Keep it that way when updating packages.

To build a release archive locally:

```bash
bin/build-release.sh v1.0.0   # writes dist/whmcs-cubepath-v1.0.0.zip
```

### Releasing

1. Bump `CUBEPATH_ADDON_VERSION` in `addons/cubepath/cubepath.php`
2. Merge to `main`
3. Tag the merge commit with the same version, prefixed with `v`, and push the tag:

   ```bash
   git tag v1.0.0 && git push origin v1.0.0
   ```

The release workflow verifies that the tag matches the module version, builds the archive and publishes it as a GitHub release.

## License

[MIT](LICENSE)
