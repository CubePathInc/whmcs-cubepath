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

### 2. Add a CubePath server

1. Go to **WHMCS Admin > System Settings > Servers** and click **Add New Server**
2. Set **Module** to **CubePath Cloud VPS**
3. Set **Hostname** to `api.cubepath.com` and paste your API token in **Password**
4. Click **Test Connection**, then **Save Changes**

Products use the token of the CubePath server in their server group, so it is kept in one place.

### 3. Activate the addon module

1. Go to **WHMCS Admin > System Settings > Addon Modules**
2. Find **CubePath** and click **Activate**
3. Click **Configure**, grant admin access and click **Save Changes**
4. Go to **Addons > CubePath > Settings** and choose the **Default Project** used by the Product Creator. The API token can also be changed there

### Installing from source

To run the module straight from a clone of this repository, install the Composer dependencies yourself:

```bash
cp -r addons/cubepath/  /path/to/whmcs/modules/addons/cubepath/
cp -r servers/cubepath/ /path/to/whmcs/modules/servers/cubepath/
composer install --no-dev --working-dir=/path/to/whmcs/modules/addons/cubepath
(cd ui && npm ci && npm run build)   # builds servers/cubepath/assets/dist/app.js
cp -r servers/cubepath/assets/ /path/to/whmcs/modules/servers/cubepath/assets/
```

## Module Components

### Addon Module (Admin Panel)

Access via **WHMCS Admin > Addons > CubePath**. Every page is part of the same panel as the client area, built from `ui/`.

| Page | Description |
|------|-------------|
| **Dashboard** | API connection status, configured project and catalog size |
| **VPS** | Every CubePath service with its live status, resources and client; search, filter and run start, stop, reboot, suspend or unsuspend on several at once |
| **Product Creator** | Create a WHMCS product for one CubePath plan, or for every available plan at once |
| **Products** | List CubePath products with their service count, and sync new locations and templates into them |
| **Locations** | Choose which locations clients can order |
| **Templates** | Choose which operating systems and applications clients can order |
| **Settings** | Change the API token (checked against the API, saved to the CubePath server) and the default project, optionally applying it to existing products |

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

### Client Area and Admin Panel

The client area of a CubePath service shows a panel in the style of the CubePath dashboard. The same panel appears in the **CubePath** field of the service in the admin area, where administrators can act on the VPS for the client.

| Tab | Features |
|-----|----------|
| **Overview** | Resources, CPU and network of the last hour, bandwidth used this month, access details (IP, user, password, SSH command) and billing |
| **Graphs** | CPU, memory, network and disk I/O for 1 hour to 30 days, with live refresh |
| **Network** | Public IPs and their reverse DNS, private network |
| **Firewall** | Inbound and outbound rules, with presets for SSH, HTTP, HTTPS, RDP and ping |
| **Backups** | Automatic backup schedule; create, restore and delete backups |
| **ISO** | Mount and unmount ISOs from the CubePath library |
| **Reinstall** | Reinstall with any operating system the product sells, with a new root password |
| **Settings** | Server name and root password |
| **Activity** | Every action run through the module, by the client, an administrator or WHMCS |

The header has the status and the start, reboot and stop buttons. Clients can only act while the service is Active; a suspended service is read-only.

Firewall groups, SSH keys and DNS zones belong to the whole CubePath organization, so the panel never shows the reseller's other resources. Each service gets its own firewall group (`whmcs-service-<id>`), created the first time its rules are saved and deleted when the service is terminated. Groups attached to the VPS from CubePath are kept and only shown to administrators.

There is no console: CubePath only opens VNC sessions for dashboard logins, not for API tokens.

The panel is a React app built from `ui/` into `servers/cubepath/assets/dist/app.js`. It renders inside a shadow root, so it looks the same with any WHMCS theme and does not affect the theme's styles. The admin panel and the **Servers** page call the addon (`addonmodules.php?module=cubepath&cpapi=1`), so administrators need access to the CubePath addon.

## Creating Products

### From the product settings

1. Create a product in **WHMCS Admin > System Settings > Products/Services**
2. In **Module Settings**, set **Module** to **CubePath Cloud VPS** and **Server Group** to the group of your CubePath server
3. Choose the **Plan** and **Project** from the lists loaded from the API
4. Save the product. The module then creates:
   - Admin-only custom fields: VPS ID, IP Address, Project ID
   - Configurable options for the location and operating system, limited to the locations where the plan is in stock and the templates that fit in its memory
5. In **Pricing**, set your price, and in **Module Settings** choose **Automatically setup the product as soon as the first payment is received**

**API Token** (advanced mode) overrides the server's token for that product only.

### With the Product Creator

**Addons > CubePath > Products > Product Creator** does the same for one plan, or for every available plan at once with a markup over CubePath's prices. Products are assigned to the CubePath server group and set up automatically on payment.

Each product goes in a product group, the categories of the store. **Create all plans** always puts each plan in the group of its family (gp.* in General Purpose, rz.* in High Frecuency, dc.* in Dedicated CPU...). A single product can go there too, or in a group chosen in the form. Family groups are created the first time and keep receiving their family's products if renamed.

Every location where a plan exists gets an option. Locations where the plan is sold out are hidden from the order form, and the WHMCS cron checks CubePath's stock every 15 minutes to show them again when restocked.

Prices of the location and template options start at zero; adjust them from **System Settings > Configurable Options** if you want to charge for a location or template.

## Order Form

Besides the location and the image, **Addons > CubePath > Settings > Order form** chooses what clients can order. Saving applies it to every CubePath product:

| Option | What the client gets | Price |
|--------|----------------------|-------|
| **SSH key** | A public key added to the VPS. It is created in your CubePath account and deleted when the service is terminated (retried by the daily cron while the VPS is being destroyed) | Free |
| **Cloud-init** | A user data script run on the first boot | Free |
| **Automatic backups** | Daily backups. Clients without them cannot turn backups on from their panel, and can add them with **Upgrade options** | A share of the product price, 20% by default (what CubePath charges) |
| **IPv6 only** | The VPS is created without an IPv4 address | A monthly discount, $1.50 by default (what CubePath saves you) |

Backups and network are configurable options; the SSH key and cloud-init are custom fields shown on the order form. Windows images take neither.

The **CubePath-style configurator** replaces the cart's fields with cards like the CubePath dashboard: locations with flags, operating systems with their versions, applications, network, backups, hostname and password. It drives the cart's own fields, so prices, validation and checkout work as usual with any order form template; if it cannot load, the native fields are shown.

## Directory Structure

```
whmcs-cubepath/
├── bin/build-release.sh         # Builds the release zip
├── addons/cubepath/
│   ├── cubepath.php            # Addon entry point (config, activate, output)
│   ├── hooks.php               # WHMCS hooks
│   ├── composer.json           # SDK dependency and autoload (vendor/ is built, not committed)
│   ├── lib/                    # Settings, Catalog, Products, Admin\AddonApi
│   ├── lang/                   # Language files
│   └── whmcs.json, logo.png    # Metadata shown in Apps & Integrations
│
└── servers/cubepath/
    ├── cubepath.php             # Server module entry point
    ├── loader.php               # Autoloader
    ├── hooks.php                # Server hooks
    ├── class/                   # Provisioning (create, suspend, terminate...)
    ├── controller/              # PanelController: JSON actions of the panels
    ├── helper/                  # CubepathHelper, ActivityHelper
    ├── template/panel.tpl       # Client area template that mounts the panel
    ├── assets/dist/             # Built panel (from ui/, not committed)
    └── whmcs.json, logo.png     # Metadata shown in Apps & Integrations

ui/                              # Panel source: React, Tailwind, recharts
├── src/views/                   # Service panel, its tabs and the reseller list
├── src/components/              # UI components (ShadCN style, dashboard tokens)
└── src/i18n.tsx                 # English and Spanish texts
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
| Server details | `$client->vps()->listAll()` |
| Graphs, bandwidth | `POST /graphql` (`vps { metrics, bandwidthUsage }`) |
| Reinstall OS | `POST /vps/reinstall/{id}` with template and password |
| Change password | `$client->vps()->changePassword($vpsId, $password)` |
| Manage Backups | `$client->vps()->backups()->list/create/restore/delete/updateSettings()` |
| Reverse DNS | `POST /floating_ips/reverse_dns/configure` |
| Manage Firewall | `$client->firewall()->create/update/delete/assignToVPS()` |
| Mount ISO | `$client->vps()->isos()->list/mount/unmount()` |

## Troubleshooting

### API connection fails
- Use **Test Connection** on the CubePath server in **System Settings > Servers** to check the API token
- Check that your server can reach `https://api.cubepath.com`
- Review WHMCS module log: **Utilities > Logs > Module Log**

### VPS not provisioned
- Ensure the product has a CubePath server group, a plan and a project in **Module Settings**
- If the location and operating system options are missing, check **Utilities > Logs > Activity Log** for a `CubePath:` entry and save the product again
- Check that the selected location and template are available for the plan
- `The SSH public key of the order is not valid` means the key was changed after ordering; fix the `SSH public key` field of the service and run Create again
- Enable WHMCS module debug logging for detailed error output

### Client area not loading
- Verify both `modules/addons/cubepath/` and `modules/servers/cubepath/` are installed
- Check file permissions (readable by web server)
- Ensure `modules/addons/cubepath/vendor/` and `modules/servers/cubepath/assets/dist/app.js` exist (both ship in the release zip; build them as in **Installing from source** otherwise)
- If the admin panel says the session expired, check that your admin role has access to the CubePath addon

## Development

```bash
composer install --working-dir=addons/cubepath
find addons servers -path '*/vendor' -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
```

Dependencies are locked against PHP 7.4 (`config.platform.php` in `addons/cubepath/composer.json`), so the bundled `vendor/` runs on every supported PHP version. Keep it that way when updating packages.

The panel lives in `ui/` (Node 22):

```bash
cd ui && npm ci
npm run build   # type-check and build into servers/cubepath/assets/dist/app.js
npm run dev     # rebuild on every change
```

To build a release archive locally (builds the panel too when `npm` is available):

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
