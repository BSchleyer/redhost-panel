# Integrations

Integrations connect Nebrix to the systems that run customers' services. They are managed in **Admin → Integrations** (permission `integrations.manage`); several connections per type are possible (e.g. one Proxmox entry per cluster). Each product is linked to one integration.

> **Test status.** All four integrations are covered by automated tests against **mocked API responses** (Guzzle mock handler) and, for TeamSpeak, a **local fake ServerQuery socket server**. They have **not** been tested against live Proxmox, Pterodactyl, Nextcloud or TeamSpeak installations during this modernization because no credentials or test systems were available. Before going live, run the connection test in the admin panel and walk through order → provisioning → suspension → unsuspension → termination on a staging system for every product type.

## Common behaviour

| Topic | Behaviour |
|---|---|
| Credentials | Secrets (tokens, passwords) are encrypted with `APP_KEY`, write-only in the UI, never logged. |
| Network safety | URLs/hosts are validated when saved and before every request (HTTPS only unless `INTEGRATIONS_ALLOW_HTTP=true`; no loopback unless `INTEGRATIONS_ALLOW_LOOPBACK=true`; link-local, cloud-metadata, multicast and unspecified addresses always blocked). The resolved IP is pinned for the request, redirects are not followed, timeout `INTEGRATIONS_TIMEOUT` (default 20 s). |
| TLS | Certificates are verified unless "Verify TLS certificate" is switched off for the entry (lab use only). |
| Connection test | "Test connection" in the admin panel and `php bin/nebrix integrations:check`; the scheduler re-checks enabled integrations every 30 minutes and shows the health status. |
| Logging | Every request is logged in `nx_integration_logs` (method, path, status, duration, error) without bodies or secrets; retention configurable. |
| Provisioning | Runs in the queue worker (`service.provision`). Remote IDs and progress are stored **before/after each remote step**, so a retried job continues instead of creating duplicates. Temporary states (remote task still running) are retried with back-off; permanent errors mark the service `failed` and show the error to staff (Admin → Services → *Retry provisioning*). |
| Customer actions | Power/maintenance actions are only offered for active services and only if the customer group allows self-service. Every action is checked server-side and audit-logged. |
| Credentials of services | Generated passwords/keys are stored encrypted in `nx_services.credentials`; customers reveal them explicitly (logged in the audit log). |

Product categories without an automated integration – **Dedicated server**, **Webhosting**, **Domain** – are fulfilled manually: the service is created in state `pending`, staff set it up and use Admin → Services → *Activate manually* (optionally storing access data, encrypted).

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

### Product configuration
**VPS / KVM server** – Node (empty = node with the most free memory), template VM ID, target storage, CPU cores, memory, disk size, bridge, rate limit, first VM ID. Customers choose a hostname when ordering.

**LXC container** – Node, OS template (`local:vztmpl/debian-12-standard_….tar.zst`), root disk storage, cores, memory, swap, disk, bridge, rate limit, unprivileged flag, first container ID.

### Lifecycle
| Step | KVM | LXC |
|---|---|---|
| Provision | Full clone of the template → set cores, memory, network (`virtio`, bridge, rate limit), cloud-init user `root` with a generated password, IP via DHCP (`ip=dhcp,ip6=auto`), start on boot → resize disk `scsi0` → start | Create the container from the OS template with a generated root password, `eth0` on the bridge via DHCP, start on boot, `nesting=1` for unprivileged containers → started by Proxmox |
| VMID | `vmid_start + service ID` (deterministic). An existing guest on that VMID is adopted only if its description contains `nebrix-service-<id>` (written at creation); otherwise provisioning stops with a conflict and termination refuses to delete it. Use non-overlapping `vmid_start` ranges per product. | same |
| Suspend / unsuspend | Stop / start the guest | same |
| Terminate | Stop (if running) → delete the guest (`purge`) after the task finished | same |
| Customer actions | Start, shut down, reboot, force stop; live status (state, uptime, CPU, memory, disk, network) | same |

Requirements and limitations:
* KVM templates must be **cloud-init enabled** (cloud-init drive attached, `qemu-guest-agent` recommended) and use `scsi0` as the boot disk.
* IP addresses are assigned by **DHCP** on the bridge; IPAM/static IP pools are not implemented. Provide DHCP (or SDN with DHCP) on the chosen bridge.
* Customer self-service reinstall, password reset and noVNC console are **not implemented**.
* Keep "Unprivileged container" enabled for LXC products: Proxmox allows only `root@pam` to set feature flags on privileged containers, and many privileged-container options are not available to API tokens.
* Multi-cluster: create one integration per cluster and one product per cluster/plan.

## Pterodactyl (Minecraft / game servers)

### Connection settings
| Field | Notes |
|---|---|
| Panel URL | `https://games.example.com` |
| Application API key (`ptla_…`) | Admin → Application API in Pterodactyl. Permissions: Users – read & write, Servers – read & write, Nests and Eggs – read; everything else – none. |
| Client API key (`ptlc_…`, optional) | Client API key of a root administrator; enables live status and power actions in the customer panel. Treat it like a root credential. |

### Product configuration
Nest ID, egg ID, location ID, memory, swap, disk, CPU limit, block IO weight, databases, additional ports, backups, Docker image (empty = egg default), egg variable overrides (JSON). Customers choose a server name.

### Lifecycle
* One Pterodactyl user per customer (`external_id` = `nebrix-user-<id>`, user name `nx<id>`), looked up by external ID; created on the first order with a generated password and re-used for further servers (the access data of the first service is shown again). If an account with the customer's e-mail already exists in Pterodactyl but was not created by Nebrix, user creation fails with a validation error and the service is marked `failed` – set the account's external ID to `nebrix-user-<id>` in Pterodactyl (or change its e-mail) and retry provisioning.
* One server per service (`external_id` = `nebrix-service-<id>`), deployed automatically to a node of the configured location (Pterodactyl deploy API picks a free allocation). A retried job finds the existing server by external ID.
* Suspend/unsuspend use the Application API suspend endpoints; termination deletes the server (the panel user is kept).
* Customer actions (with client API key): start, restart, stop, kill; live resource usage.

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

## Adding a new integration type

1. Add the type with its connection fields to `src/Integration/IntegrationType.php`.
2. Implement a client (extend the pattern in `src/Integration/Http/ApiClient.php`, which applies the URL guard, timeouts and logging) and register its health check in `IntegrationManager`.
3. Add product category fields in `src/Catalog/ProductCategory.php` (all values validated with rules).
4. Implement `Nebrix\Provisioning\Provisioner` (provision, suspend, unsuspend, terminate, status, actions, perform) with persisted progress for idempotency, and register it in `ProvisionerRegistry`.
5. Add tests with mocked responses (see `tests/Integration/Integrations/`).
