# Administrator Guide

The administration area is available at `/admin` for staff accounts. What a staff member sees and may do depends on the permissions of their role; buttons for actions without permission are hidden and the server rejects them as well.

## First steps after installation

1. Sign in with the account created by `user:create-admin` and enable two-factor authentication (Account → Security).
2. **Settings → General**: company name and address (shown on invoices and e-mails), contact and support e-mail, legal URLs, language, currency, time zone.
3. **Settings → Branding**: logo, favicon, colours, login texts. Changes apply immediately for all visitors.
4. **Settings → E-mail**: SMTP server and sender, then *Send test e-mail*. Without SMTP, e-mails are only written to the log.
5. **Settings → Authentication**: registration, e-mail verification, lockout, **"Require two-factor authentication for staff"** (recommended).
6. **Settings → Billing**: number prefixes, payment term, renewal notice, grace period before suspension, automatic termination (off by default).
7. **Payments**: Stripe/PayPal keys and webhooks – see [PAYMENTS.md](PAYMENTS.md).
8. **Integrations**: add remote systems and run *Test connection* – see [INTEGRATIONS.md](INTEGRATIONS.md).
9. **Products**: create the offers customers can order.
10. **Staff & roles**: create accounts for your team with the least privileges they need.

## Roles and permissions

| Role | Intended for | Default permissions (summary) |
|---|---|---|
| Super Administrator | Owners | Everything, including roles, permissions and other super administrators. Cannot be locked out: the last active super administrator cannot be demoted or suspended. |
| Administrator | Operations | Everything except managing roles and super administrators. |
| Support | Support team | Dashboard, customers (view), services (view/manage), tickets (view/reply/manage), orders, invoices, products and integrations (view). No credit, refunds, settings or staff. |
| Finance | Accounting | Dashboard, orders and invoices (view/manage), transactions, refunds, credit adjustments, customers, products and services (view). |
| Customer | Customers | No admin access. |

* Super administrators can create custom staff roles in **Roles** and tick individual permissions (e.g. a "Support lead" with `staff.manage`).
* Staff managers can only assign roles whose permissions they hold themselves, and cannot edit staff members who have more permissions than they do.
* Nobody can change their own role or status. Changing a member's role signs that member out everywhere.
* Every staff action that changes data is recorded in the **Audit log**.

## Dashboard

Shows customer counts by status, pending orders, active and failed services, unpaid invoices, revenue (current month and total), open tickets, recent transactions and customers, integration health, queue counts, the last scheduler runs and notifications (e.g. failed jobs, unhealthy integrations).

## Customers

* **List and search** by name, e-mail, company, status and group.
* **Customer page**: profile, billing address, last sign-in, number of active sessions, recent sign-ins, services, orders, invoices, transactions and credit history.
* Actions (each requires its own permission and is audit-logged):
  * Edit profile and group, suspend/reactivate the account (suspended customers are signed out).
  * Adjust credit (positive or negative, reason required).
  * Send a password reset link, resend the verification e-mail or mark the e-mail as verified, reset two-factor authentication, revoke all sessions.
  * **Close and anonymize** (GDPR erasure): only possible when the customer has no running services; invoices and payments are retained for legal reasons.
* Staff never see or set customer passwords and cannot sign in as a customer.

### Customer groups
Groups provide a percentage discount on orders and renewals and can disable ordering or self-service actions (power actions, password resets) for their members. Products can be restricted to one group.

## Products

* Category: VPS/KVM, LXC container, Dedicated server, Webhosting, Domain, Minecraft/game server, TeamSpeak 3, Nextcloud storage.
* Name, URL slug, description, feature list, price, setup fee, currency, billing cycle (monthly, quarterly, every 6 months, annually, one-time), tax rate, stock (empty = unlimited), visibility and active flags, sort order, optional customer-group restriction.
* **Provisioning**: integration and category-specific settings (e.g. template, CPU, memory, disk, IP pool and reinstall templates for Proxmox; mode, model, location and images for Hetzner dedicated servers; service plan for Plesk; TLD for domains; nest/egg/limits for Pterodactyl; slots for TeamSpeak; quota for Nextcloud). All values are validated. For manual fulfilment leave the integration empty and switch off "Provision automatically" (the form does not accept automation without an integration).
* For Hetzner pool products set the **stock** to the number of free pool servers so customers cannot order more than you have.
* Products are not deleted; deactivate or hide them instead. Existing orders and services keep their price snapshot.

## Orders, invoices and transactions

* **Orders**: status `pending` (unpaid), `paid`, `cancelled`. Unpaid orders can be cancelled (stock is released); orders whose invoice is still unpaid 14 days after its due date are cancelled automatically.
* **Invoices**: order, renewal and credit top-up invoices with status `unpaid`, `paid`, `cancelled`, `refunded`. Actions: cancel an unpaid invoice, record a manual payment (bank transfer). The invoice page shows the payment event history.
* **Transactions**: all payments and refunds with provider references; refunds are issued here (see [PAYMENTS.md](PAYMENTS.md#refunds)). A full refund suspends the services the invoice paid for.
* **Webhook events**: every received payment webhook with its processing status.

## Services

* Filter by status and category; search by service name, customer e-mail or service ID.
* Service page: configuration, remote identifiers, live status (where supported), access data (revealing it is audit-logged), invoices, related jobs and the last provisioning error.
* Actions: suspend (with reason; the customer is notified), unsuspend, terminate (deletes the remote resource – confirm carefully), **retry provisioning** for failed services, **activate manually** (for services without automation, optionally storing access data).
* **Remote actions**: the actions the integration offers (power, password reset, reinstall, rescue system, reverse DNS, name servers, Plesk login, …) – the same as for customers, executed and audit-logged in the staff member's name.
* **Console**: graphical console for Proxmox VMs and containers (requires the console proxy, see INSTALLATION.md).
* Services are suspended automatically when their renewal invoice is unpaid after the grace period and reactivated when it is paid. Termination of long-suspended services happens only if enabled in the billing settings.

## Support tickets

Customers open tickets (optionally about one of their services). Staff reply, change status/priority and assign tickets. Ticket replies notify the customer by e-mail. Messages are shown as plain text.

## Integrations

Add Proxmox clusters, Hetzner Robot accounts, Plesk servers, INWX accounts, Pterodactyl panels, Nextcloud instances and TeamSpeak servers; credentials are stored encrypted and never displayed again. The integration page shows health, the last check, the request log and available resources (Proxmox nodes, Pterodactyl nodes/eggs, TeamSpeak servers, Hetzner servers with their pool state). Disabled integrations are not used for provisioning.

**Hetzner pool workflow**: name spare servers in Robot with the pool prefix (e.g. `nebrix-free-1`). Assigned servers are renamed to `nebrix-service-<id>`. After a termination the server appears as *wipe* (`nebrix-wipe-<number>`): wipe its disks, then rename it back to the pool prefix.

### IP pools
Admin → IP pools: create IPv4 pools (network, gateway, resolvers, optional range) and assign them to VPS/LXC products. The pool page shows every address with its status and service; free addresses can be reserved (and released) with a note. Pools that are used by products or still have assigned addresses cannot be deleted.

## E-mail templates

Admin → E-mail templates lists all system e-mails (registration, verification, password reset/changed, order confirmation, invoice, payment confirmation, service provisioned/suspended/expiring, ticket reply, test). Each template has a subject, an HTML and a text body with `{{ variable }}` placeholders (the available variables are listed next to the editor). *Preview* renders sample data in a sandbox; *Reset* restores the default. Variable values are HTML-escaped.

## Staff, roles and logs

* **Staff**: create staff accounts (an e-mail with a link to set the password is sent), edit name/role/status, reset 2FA or send a password reset.
* **Roles**: super administrators manage custom roles and permissions; the system roles cannot be deleted and the super administrator role cannot be restricted.
* **Login history**: successful and failed sign-ins with IP and user agent.
* **Audit log**: who changed what and when (settings keys, never secret values).
* **API tokens** (permission `api_tokens.manage`): all personal access tokens with owner, scopes, last use and expiry; revoke single tokens or all tokens of a user. Token secrets are never shown – only their prefix (`nbx_1a2b3c4d…`).

## System

* **Settings → System**: maintenance mode (customers see a message; staff can sign in), debug logging, log retention, ticket system on/off, **REST API** (off by default; customer tokens on/off; rate limit per token – see [API.md](API.md#rest-api-v1)). The page also shows PHP version and extensions, database status, pending migrations, the queue and scheduler state.
* **Jobs**: background jobs with status, attempts and last error; failed jobs can be retried or cancelled.
* Updates, backups and server maintenance are done on the server, not in the panel – see [INSTALLATION.md](INSTALLATION.md) and [UPGRADE_GUIDE.md](UPGRADE_GUIDE.md).

## Operational checklist

* Workers and scheduler running? (Dashboard → scheduler runs, Jobs page)
* Failed jobs or provisioning errors? (Jobs, Services → status *failed*)
* Integrations healthy? (Integrations)
* Webhooks arriving? (Payments → Webhook events)
* Backups of database and `APP_KEY` tested?
