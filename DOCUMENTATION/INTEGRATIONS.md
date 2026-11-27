# Integrations

Integrations connect Nebrix to the systems that run customers' services. They are managed in **Admin → Integrations** (permission `integrations.manage`); several connections per type are possible (e.g. one Proxmox entry per cluster). Each product is linked to one integration.

> **Test status.** All integrations (Proxmox incl. backups, Pterodactyl incl. backups, Nextcloud, TeamSpeak, Hetzner Robot, Plesk, INWX, EU VIES) are covered by automated tests against **stateful fakes of their APIs** (mocked HTTP, a fake ServerQuery socket server and a fake console WebSocket server). They have **not** been tested against live systems because no credentials or test systems were available. Before going live, run the connection test in the admin panel and walk through order → provisioning → actions → suspension → unsuspension → termination on a staging system for every product type. Use the Hetzner order **test mode** and the INWX **OTE** system first.

## Common behaviour

| Topic | Behaviour |
|---|---|
| Credentials | Secrets (tokens, passwords) are encrypted with `APP_KEY`, write-only in the UI, never logged. |
| Network safety | URLs/hosts are validated when saved and before every request (HTTPS only unless `INTEGRATIONS_ALLOW_HTTP=true`; no loopback unless `INTEGRATIONS_ALLOW_LOOPBACK=true`; link-local, cloud-metadata, multicast and unspecified addresses always blocked). The resolved IP is pinned for the request, redirects are not followed, timeout `INTEGRATIONS_TIMEOUT` (default 20 s). |
| TLS | Certificates are verified unless "Verify TLS certificate" is switched off for the entry (lab use only). |
| Connection test | "Test connection" in the admin panel and `php bin/nebrix integrations:check`; the scheduler re-checks enabled integrations every 30 minutes and shows the health status. |
| Logging | Every request is logged in `nx_integration_logs` (method, path, status, duration, error) without bodies or secrets; retention configurable. |
| Provisioning | Runs in the queue worker (`service.provision`). Remote IDs and progress are stored **before/after each remote step**, so a retried job continues instead of creating duplicates. Temporary states (remote task still running) are retried with back-off; permanent errors mark the service `failed` and show the error to staff (Admin → Services → *Retry provisioning*). |
| Customer actions | Actions (power, password reset, reinstall, rDNS, name servers, …) are only offered for active services and only if the customer group allows self-service. Only actions the provisioner offers for the service are accepted; input fields are validated server-side (select fields only accept the offered values). Every action is audit-logged. Staff run the same actions under Admin → Services → *Remote actions*. |
| Reinstall | Reinstalling (Proxmox, Hetzner) sets the service to `provisioning` and re-runs the regular, idempotent provisioning job, which first removes the old installation. Limited to 3 per service and hour for customers. |
| Credentials of services | Generated passwords/keys are stored encrypted in `nx_services.credentials`; customers reveal them explicitly (logged in the audit log). |

A product is provisioned automatically when it has an integration and "Provision automatically" is on. Without an integration (any category) the service is created in state `pending`, staff set it up and use Admin → Services → *Activate manually* (optionally storing access data, encrypted).

| Category | Integration |
|---|---|
| VPS/KVM, LXC | Proxmox VE |
| Dedicated server | Hetzner Robot |
| Webhosting | Plesk |
| Domain | INWX (registrar interface for further registrars) |
| Minecraft / game server | Pterodactyl |
| Nextcloud storage | Nextcloud |
| TeamSpeak 3 | TeamSpeak ServerQuery |

## Proxmox VE (VPS/KVM and LXC)

### Connection settings
| Field | Notes |
|---|---|
| API URL | `https://pve.example.com:8006` – one integration per cluster/stand-alone node. |
| API token ID | `user@realm!tokenname` |
| API token secret | UUID shown once by Proxmox when the token is created. |
| Verify TLS certificate | Keep enabled; use a trusted certificate (e.g. ACME) on Proxmox. |

Authentication uses the header `Authorization: PVEAPIToken=<id>=<secret>`. No passwords, tickets, SSH or shell commands are used.

### Creating a least-privilege token
Create a dedicated user and token (with privilege separation) and grant only what is needed. A starting point – verify it against your Proxmox version and adjust paths to your pools/storages:

```bash
pveum user add nebrix@pve --comment "Nebrix panel"
```

```bash
pveum role add NebrixProvisioner -privs "VM.Allocate VM.Clone VM.Audit VM.PowerMgmt VM.Config.CPU VM.Config.Memory VM.Config.Disk VM.Config.Network VM.Config.Options VM.Config.Cloudinit VM.Config.HWType Datastore.AllocateSpace Datastore.Audit Sys.Audit SDN.Use"
```

```bash
pveum user token add nebrix@pve panel --privsep 1
```

```bash
pveum acl modify / --tokens 'nebrix@pve!panel' --roles NebrixProvisioner
```

Granting the role on `/` is the simplest option; for tighter scoping grant it on a resource pool (`/pool/<name>`), the target storages (`/storage/<id>`), the nodes (`/nodes`) and the bridge (`/sdn/zones/localnetwork`) instead.

For customer backups additionally `VM.Backup` (create, restore, delete backups) and `Datastore.AllocateSpace` + `Datastore.Audit` on the backup storage. Additional privileges for the console and the password reset: `VM.Console` (`vncproxy`/`vncwebsocket`) and the privilege for QEMU guest agent calls (`VM.Monitor` on Proxmox VE 8; newer versions use `VM.GuestAgent.*` privileges). Check the API viewer of your Proxmox version for `agent/set-user-password` and `vncproxy`.

### Product configuration
**VPS / KVM server** – Node (empty = node with the most free memory), template VM ID, target storage, CPU cores, memory, disk size, bridge, rate limit, first VM ID, IP pool (empty = DHCP), additional templates for reinstall (`VM ID|Label` per line). Customers choose a hostname when ordering.

**LXC container** – Node, OS template (`local:vztmpl/debian-12-standard_….tar.zst`), root disk storage, cores, memory, swap, disk, bridge, rate limit, unprivileged flag, first container ID, IP pool, additional OS templates for reinstall (`template|Label` per line).

Both: **backup storage** (a Proxmox storage with content type `backup` – directory/NFS/CIFS or Proxmox Backup Server; empty = no customer backups) and **backups per server** (0–50).

### IP pools (static addressing)
Admin → IP pools manages IPv4 networks (CIDR /16–/32) with gateway, resolvers and an optional assignable range. Network, broadcast and gateway addresses are never assigned; single addresses can be reserved (e.g. for routers). A product with a pool gives each service one address: KVM via cloud-init (`ipconfig0 ip=…/prefix,gw=…`, `nameserver`), LXC via `net0 ip=…,gw=…` and `nameserver`. IPv6 stays on SLAAC (`ip6=auto`). The allocation is idempotent per service (row lock, unique key), survives reinstalls and is released after the guest has been deleted. When a pool is exhausted provisioning stops with a clear error.

### Lifecycle
| Step | KVM | LXC |
|---|---|---|
| Provision | Full clone of the template → set cores, memory, network (`virtio`, bridge, rate limit), cloud-init user `root` with a generated password, IP via DHCP (`ip=dhcp,ip6=auto`), start on boot → resize disk `scsi0` → start | Create the container from the OS template with a generated root password, `eth0` on the bridge via DHCP, start on boot, `nesting=1` for unprivileged containers → started by Proxmox |
| VMID | `vmid_start + service ID` (deterministic). An existing guest on that VMID is adopted only if its description contains `nebrix-service-<id>` (written at creation); otherwise provisioning stops with a conflict and termination refuses to delete it. Use non-overlapping `vmid_start` ranges per product. | same |
| Suspend / unsuspend | Stop / start the guest | same |
| Terminate | Stop (if running) → delete the guest (`purge`) after the task finished | same |
| Customer actions | Start, shut down, reboot, force stop, **reset root password** (QEMU guest agent; if the agent is not running and the VM has a cloud-init drive, via cloud-init at the next boot – otherwise the action fails without changes), **reinstall**, **console**; live status (state, uptime, CPU, memory, disk, network, IPv4) | Start, shut down, reboot, force stop, **reinstall**, **console** (the root password of a container can only be changed by reinstalling) |
| Reinstall | Stop → delete (ownership checked) → clone the chosen template again with the same VMID and address, new root password | same with the chosen OS template |
| Backups | See [Customer backups](#customer-backups) | same |

Requirements and limitations:
* KVM templates must be **cloud-init enabled** (cloud-init drive attached, `qemu-guest-agent` recommended) and use `scsi0` as the boot disk.
* Without an IP pool, addresses come from **DHCP** on the bridge (provide DHCP or SDN with DHCP).
* Guests imported from RED-Host (`legacy:import --only=services`) have no marker; the importer records their VMID and host name, and the provisioner accepts exactly that VMID only while the guest still has the recorded host name and carries no marker of another service (a reused VMID is never deleted).
* Keep "Unprivileged container" enabled for LXC products: Proxmox allows only `root@pam` to set feature flags on privileged containers, and many privileged-container options are not available to API tokens.
* Multi-cluster: create one integration per cluster and one product per cluster/plan.

### Customer backups
With a backup storage and a number of backups > 0 on the product, customers get the actions *Create backup*, *Restore backup* and *Delete backup*.

* **Create**: `vzdump` in snapshot mode (`zstd`) to the backup storage with the note `nebrix-service-<id>` (requires Proxmox VE 7.2 or newer for notes). The server keeps running. Only one backup per service runs at a time; the product limit is enforced under a database lock. A finished backup appears in the list.
* **Ownership**: customers only see backups on the product's backup storage that belong to the guest's VMID, carry the note `nebrix-service-<id>` and were made after the service was created. Backups from your own backup jobs (other notes) and backups of an earlier guest with the same VMID are never shown, restored or deleted.
* **Restore**: runs through the job queue like a reinstall (service status `provisioning`, max. 3 per hour): waits for a running backup, checks the backup and the guest's ownership again, stops the guest, restores in place (`force`, same VMID, address and stored access data – the root password is the one valid at backup time) and starts it. If the backup disappeared meanwhile, nothing is changed and the job fails; *Retry provisioning* re-activates the service unchanged.
* **Delete**: removes the backup volume (`DELETE …/storage/<storage>/content/<volume>`).
* **Termination** deletes the service's backups after the guest has been deleted; failures are logged (delete the remaining backups manually) and do not block the termination.
* Customer backups are not a replacement for your own disaster-recovery backups of the cluster.

### Console (noVNC)
Customers (and staff) open a graphical console from the service page. The browser never receives Proxmox credentials:

1. The panel requests a VNC ticket (`POST …/vncproxy websocket=1`) and stores the upstream target (address, path with ticket, API token header) **encrypted** in `nx_console_sessions` under the SHA-256 hash of a random 256-bit token, valid for **60 seconds** and **once**.
2. The page (noVNC 1.7.0, vendored) connects to `CONSOLE_WS_URL?token=…` and authenticates the VNC session with the ticket.
3. `bin/nebrix console:proxy` redeems the token, checks that service and user are still active, connects to Proxmox (address pinned, TLS verified per integration, `Sec-WebSocket-Accept` checked) and relays the WebSocket frames unchanged. It checks the `Origin` (must be `APP_URL`), limits header size, handshake time (10 s), concurrent connections (`CONSOLE_MAX_CONNECTIONS`, default 100), concurrent handshakes (32), connections per client IP (`CONSOLE_MAX_PER_IP`, default 10; the `X-Forwarded-For` address when the peer is listed in `TRUSTED_PROXIES`), open consoles per user (`CONSOLE_MAX_PER_USER`, default 3), session duration (`CONSOLE_MAX_LIFETIME`, default 4 h) and buffered bytes (a direction is paused while the other side has more than 512 KiB queued). The process sets its own memory limit (`CONSOLE_MEMORY_LIMIT`, default 512M).

Run the proxy as a service (docker-compose service `console`, or systemd) and route `CONSOLE_WS_URL` (default `wss://<panel>/console/ws`) from the TLS reverse proxy to it, e.g. nginx:

```nginx
location /console/ws { proxy_pass http://127.0.0.1:8081; proxy_http_version 1.1; proxy_set_header Upgrade $http_upgrade; proxy_set_header Connection "upgrade"; proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for; proxy_read_timeout 4h; access_log off; }
```

`TRUSTED_PROXIES` must contain the reverse proxy's address (as seen by the console proxy – with docker-compose the Docker gateway), otherwise all clients share one address for the per-IP limit. The token is part of the URL; `access_log off` keeps it out of the reverse proxy's logs (it is single-use and expires after 60 seconds either way).

Opening a console is audit-logged and rate-limited (10 per 5 minutes per user).

## Hetzner Robot (dedicated servers)

### Connection settings
| Field | Notes |
|---|---|
| Robot API URL | `https://robot-ws.your-server.de` (default) |
| Webservice user / password | Create a separate webservice user in Robot (Settings → Webservice and app settings). Basic authentication over HTTPS. |
| Pool prefix | Only servers whose Robot **server name** starts with this prefix (default `nebrix-free`) are handed out. Prefixes that overlap the reserved names `nebrix-service-` / `nebrix-wipe-` (e.g. `nebrix-`) are rejected. |
| Allow ordering new servers | Off by default. Products in *order* mode buy servers at Hetzner – this causes costs. |
| Send orders in test mode | On by default: Hetzner validates the order without processing it; provisioning then stops with a clear message. |
| Cancel ordered servers when terminated | Off by default. |
| SSH key fingerprint for orders | Optional key stored in Robot, used for newly ordered servers before the customer installation. |

### Product configuration
Mode (*pool* or *order*), server model (pool: part of the Robot product name such as `AX41`; order: Robot order product ID such as `AX41-NVMe`), location (`FSN1`, `NBG1`, `HEL1`, empty = any), operating system image (exact name from the Robot Linux installation, e.g. `Debian 12 base`), additional images for reinstall (one per line). For manual fulfilment leave the integration empty and switch off "Provision automatically".

### Lifecycle
| Step | Behaviour |
|---|---|
| Pool assignment | Under a database lock the first ready, not cancelled server with the pool prefix, matching model and location that is not reserved by another service is reserved and renamed to `nebrix-service-<id>`. An empty pool stops provisioning with a clear error (add servers, retry). |
| Order | Only with *Allow ordering*. The order carries the comment `nebrix-service-<id>`; a retry finds an existing order by that comment instead of ordering twice. Waits (10-minute retries) until Hetzner delivers the server. |
| Installation | Deactivates an active rescue system (only one boot configuration can be active), activates the Linux installation (Hetzner generates the root password, stored encrypted), hardware reset, waits until the installer has booted and at least 10 minutes have passed. |
| Suspend / unsuspend | Enables the Robot firewall without rules (incoming traffic dropped) after saving the current firewall (input and output rules); unsuspend restores it. The server keeps running. |
| Terminate | Boots the rescue system (the customer's OS stops), optionally cancels ordered servers at the earliest date, and renames the server to `nebrix-wipe-<number>`. **Disks are not wiped automatically**: wipe the server, then rename it to the pool prefix to return it to the pool. |
| Customer actions | Reboot (CTRL+ALT+DEL), hardware reset, power button, rescue system (password under access data), reverse DNS of the main IPv4, reinstall with an offered image. |
| Status | Model, location, IPv4/IPv6, traffic; cached for 5 minutes because of the Robot API request limits. |

Every action, the installation and its reset, and a reinstall request first check live at the Robot API that the server is still named `nebrix-service-<id>`. If test mode is switched off after a test order, the next retry places the real order. Admin → Integrations → *Remote resources* lists all Robot servers with their pool state (free, assigned, wipe, unmanaged).

## Plesk (webhosting)

### Connection settings
Plesk URL (`https://host:8443`), shared IPv4 address for new subscriptions, **API secret key** created on the Plesk server:

```bash
plesk bin secret_key --create -ip-address <panel IP> -description Nebrix
```

The XML API (`/enterprise/control/agent.php`) is used; requests are built with escaped values and responses are parsed without DTDs.

### Product configuration
Name of the Plesk **service plan** (required for automatic provisioning). Customers enter the domain when ordering.

### Lifecycle
* One Plesk customer per Nebrix customer (external ID `nebrix-user-<id>`, login `nx<id>`, generated password), shared by all subscriptions of the customer; one subscription per service with FTP user `nx<service id>` and a generated password.
* A subscription for the domain that belongs to another Plesk customer is never adopted, and a domain can have only one active webhosting service per Plesk server (checked before the order and before provisioning).
* Suspend/unsuspend set the subscription status (16 = suspended by admin / 0 = active); terminate deletes the subscription (the customer account stays).
* Customer actions: **Open Plesk** (one-time login session; the panel shows a link page because the strict CSP does not allow form posts to redirect to other hosts) and **reset the Plesk password** (applies to all subscriptions of the customer).

## INWX (domains)

### Connection settings
Environment (**OTE** test system by default, or live), INWX user name and password, default name servers (one per line), optional two-factor secret (Base32) – the panel then unlocks the session with a generated TOTP code.

### Product configuration
Top-level domain (e.g. `de`); product billing cycle *annually*. Customers enter the domain and, for transfers, the auth code.

### Before the order
* The domain must be a direct second-level domain of the product's TLD.
* The customer profile must contain name, address, postcode, city, country and a phone number (converted to the EPP format `+CC.NUMBER`).
* Availability is checked at INWX; registered domains can only be ordered with an auth code (transfer), free domains only without one.
* A domain that is already managed by another (not terminated) service in the panel cannot be ordered again.

### Lifecycle
* A registrant contact is created from the profile and reused as long as the data does not change; the same contact is used as admin, tech and billing contact.
* Registration (`domain.create`, 1 year, auto-renew) or transfer (`domain.transfer`). The auth code is stored **encrypted** with the order and removed from the service after the registry accepted the transfer request. A rejected request (e.g. wrong auth code) is not counted as submitted; the job fails and the service stays inactive.
* The service becomes active only when INWX reports the domain with status `OK`. Pending registrations are re-checked every 5 minutes, pending transfers every hour; after 2 days (registration) or 10 days (transfer) the job fails with a message to check the domain at the registrar.
* A domain in the INWX account belongs to a service only if the service submitted it and INWX reports the service's registrant contact; other domains are never adopted, changed or deleted.
* Suspend: renewal mode *AUTOEXPIRE*; unsuspend: *AUTORENEW*; terminate: *AUTODELETE* (deleted at the end of the term, never immediately).
* Customer actions: change name servers, show the auth code (stored under access data).

## Pterodactyl (Minecraft / game servers)

### Connection settings
| Field | Notes |
|---|---|
| Panel URL | `https://games.example.com` |
| Application API key (`ptla_…`) | Admin → Application API in Pterodactyl. Permissions: Users – read & write, Servers – read & write, Nests and Eggs – read; everything else – none. |
| Client API key (`ptlc_…`, optional) | Client API key of a root administrator; enables live status, power actions and backups in the customer panel. Treat it like a root credential. |

### Product configuration
Nest ID, egg ID, location ID, memory, swap, disk, CPU limit, block IO weight, databases, additional ports, backups, Docker image (empty = egg default), egg variable overrides (JSON). Customers choose a server name.

### Lifecycle
* One Pterodactyl user per customer (`external_id` = `nebrix-user-<id>`, user name `nx<id>`), looked up by external ID; created on the first order with a generated password and re-used for further servers (the access data of the first service is shown again). If an account with the customer's e-mail already exists in Pterodactyl but was not created by Nebrix, user creation fails with a validation error and the service is marked `failed` – set the account's external ID to `nebrix-user-<id>` in Pterodactyl (or change its e-mail) and retry provisioning.
* One server per service (`external_id` = `nebrix-service-<id>`), deployed automatically to a node of the configured location (Pterodactyl deploy API picks a free allocation). A retried job finds the existing server by external ID.
* Suspend/unsuspend use the Application API suspend endpoints; termination deletes the server (the panel user is kept).
* Customer actions (with client API key): start, restart, stop, kill; live resource usage.
* **Backups** (with client API key and a backup limit > 0 on the product): create, restore (all current files are deleted first – `truncate`), download (short-lived signed URL of the node) and delete finished backups of the customer's own server. The limit is the product's "Backups" value, which Pterodactyl also enforces (`feature_limits.backups`). Deleting a server in Pterodactyl deletes its backups.

## Nextcloud (cloud storage)

### Connection settings
Nextcloud URL, admin user name, admin **app password** (Settings → Security in Nextcloud), user ID prefix (default `nx`).

### Product configuration
Storage quota (GB), optional Nextcloud group.

### Lifecycle
* One account per service: user ID `<prefix><service-id>`, generated password, display name and e-mail of the customer, quota and optional group, created via the Provisioning API (`/ocs/v2.php/cloud/users`). The password is stored before the remote call, so a retry can adopt the account.
* Suspend disables the account (data kept), unsuspend enables it, terminate deletes the account **and its data**.
* Customer action: generate a new password.

## TeamSpeak 3

### Connection settings
ServerQuery host and port (default 10011), query login (default `serveradmin`), query password, public host name shown to customers, voice port range for new servers.

> Raw ServerQuery (TCP) is **not encrypted**. Allow the query port only from the panel's IP address and connect over a private network or VPN. Add the panel IP to `query_ip_allowlist.txt` on the TeamSpeak server so it is not flood-banned.

### Product configuration
Slots. Customers choose a server name.

### Lifecycle
* Provision: reserves the first voice port in the configured range that is neither used on the server nor reserved by another service of the integration (under a database lock), stores it **before** `servercreate`, creates the virtual server (name, slots, port, autostart on) and stores the returned server admin privilege key encrypted. A retried job adopts a virtual server on the reserved port only if it has the requested name; any other server on that port is reported as a conflict.
* Suspend: disable autostart and stop the virtual server; unsuspend: enable autostart and start it.
* Terminate: stop and delete the virtual server.
* Customer actions: start, stop, restart, create a new admin privilege key.
* All parameter values are escaped according to the ServerQuery specification; command and parameter names are fixed in code.

## Imported RED-Host services

`legacy:import --only=services` links legacy LXC/KVM servers, TeamSpeak servers, game servers and webspaces to the integrations you map (see UPGRADE_GUIDE.md). These services are marked `legacy`: the provisioners accept the remote IDs recorded by the legacy database (Proxmox VMID, Pterodactyl server ID, Plesk subscription ID) although the objects carry no Nebrix marker – with additional checks before anything is deleted: a Proxmox guest must still have the recorded host name and no marker of another service, a Pterodactyl server the recorded identifier.

## EU VIES (VAT ID check)

VAT IDs are verified with the public REST interface of the European Commission (`https://ec.europa.eu/taxation_customs/vies/rest-api/ms/<country>/vat/<number>`, no credentials). Only country code and number are sent. Results: valid, invalid, or unknown when VIES or the member state's service is unavailable (a newly entered VAT ID stays unchecked and VAT is charged; an earlier result for an unchanged VAT ID is kept; the scheduler retries). Reverse charge also needs the customer's company name to match the holder name VIES reports; several member states (e.g. Germany, Spain) do not disclose names – then the VAT ID alone counts. The service is subject to the EU's availability and fair-use rules.

## E-invoices (XRechnung)

Invoice XML is generated as XRechnung 3.0 in the UN/CEFACT CII syntax (`urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0`) with VAT categories S, AE, E and O and type codes 380 (invoice) and 381 (correction). Every generated file is checked against the CII XSD in the automated tests. The official Schematron rules (EN 16931 + XRechnung) were **not** executed in this environment; validate sample invoices with the KoSIT validator before sending e-invoices:

```bash
java -jar validationtool-<version>-standalone.jar -s <configuration>/scenarios.xml -r <configuration> invoice.xml
```

(Validator: `github.com/itplr-kosit/validator`, configuration for XRechnung 3.0: `github.com/itplr-kosit/validator-configuration-xrechnung` – check their READMEs for the exact options of the current release.) Download an XML with Admin → Invoices → *E-invoice (XRechnung)*. Required seller data (address, VAT ID or tax number, contact person, e-mail and phone) is configured in Settings → General → Invoice details; the admin dashboard warns while it is incomplete.

## Adding a new integration type

1. Add the type with its connection fields to `src/Integration/IntegrationType.php`.
2. Implement a client (extend the pattern in `src/Integration/Http/ApiClient.php`, which applies the URL guard, timeouts and logging) and register its health check in `IntegrationManager`.
3. Add product category fields in `src/Catalog/ProductCategory.php` (all values validated with rules).
4. Implement `Nebrix\Provisioning\Provisioner` (provision, suspend, unsuspend, terminate, status, actions, perform) with persisted progress for idempotency, and register it in `ProvisionerRegistry`.
5. Add tests with mocked responses (see `tests/Integration/Integrations/`).
