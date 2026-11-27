# Administrator Guide

The administration area is available at `/admin` for staff accounts. What a staff member sees and may do depends on the permissions of their role; buttons for actions without permission are hidden and the server rejects them as well.

## First steps after installation

1. Sign in with the account created by `user:create-admin` and enable two-factor authentication (Account → Security).
2. **Settings → General**: company name and address (shown on invoices and e-mails), contact and support e-mail, legal URLs, language, currency, time zone.
3. **Settings → Branding**: logo, favicon, colours, login texts. Changes apply immediately for all visitors.
4. **Settings → E-mail**: SMTP server and sender, then *Send test e-mail*. Without SMTP, e-mails are only written to the log.
5. **Settings → Authentication**: registration, e-mail verification, lockout, **"Require two-factor authentication for staff"** (recommended).
6. **Settings → Billing**: number prefixes, payment term, renewal notice, grace period before suspension, automatic termination (off by default), automatic payment from credit, payment reminders.
7. **Settings → General → Taxes** and **Invoice details**: tax status (small business, reverse charge, OSS, third countries) and the details printed on invoices (address, VAT ID or tax number, bank account, contact person). Have the tax settings checked by your tax advisor – see [Taxes and invoices](#taxes-and-invoices).
8. **Payments**: Stripe/PayPal keys and webhooks – see [PAYMENTS.md](PAYMENTS.md).
9. **Integrations**: add remote systems and run *Test connection* – see [INTEGRATIONS.md](INTEGRATIONS.md).
10. **Products**: create the offers customers can order.
11. **Staff & roles**: create accounts for your team with the least privileges they need.

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
  * Send a password reset link, resend the verification e-mail or mark the e-mail as verified, reset two-factor authentication, revoke all sessions (both also revoke the customer's API tokens).
  * **Close and anonymize** (GDPR erasure): only possible when the customer has no running services; invoices and payments are retained for legal reasons.
* Staff never see or set customer passwords and cannot sign in as a customer.

### Customer groups
Groups provide a percentage discount on orders and renewals and can disable ordering or self-service actions (power actions, password resets) for their members. Products can be restricted to one group.

## Products

* Category: VPS/KVM, LXC container, Dedicated server, Webhosting, Domain, Minecraft/game server, TeamSpeak 3, Nextcloud storage.
* Name, URL slug, description, feature list, price, setup fee, currency, billing cycle (monthly, quarterly, every 6 months, annually, one-time), tax rate, stock (empty = unlimited), visibility and active flags, sort order, optional customer-group restriction.
* **Provisioning**: integration and category-specific settings (e.g. template, CPU, memory, disk, IP pool, reinstall templates, backup storage and number of backups for Proxmox; mode, model, location and images for Hetzner dedicated servers; service plan for Plesk; TLD for domains; nest/egg/limits for Pterodactyl; slots for TeamSpeak; quota for Nextcloud). All values are validated. For manual fulfilment leave the integration empty and switch off "Provision automatically" (the form does not accept automation without an integration).
* For Hetzner pool products set the **stock** to the number of free pool servers so customers cannot order more than you have.
* Products are not deleted; deactivate or hide them instead. Existing orders and services keep their price snapshot.

## Orders, invoices and transactions

* **Orders**: status `pending` (unpaid), `paid`, `cancelled`. Unpaid orders can be cancelled (stock is released); orders whose invoice is still unpaid 14 days after its due date are cancelled automatically.
* **Invoices**: order, renewal and credit top-up invoices with status `unpaid`, `paid`, `cancelled`, `refunded`, plus correction documents (type `credit_note`, status `issued`). Actions: cancel an unpaid invoice, record a manual payment (bank transfer), download PDF and XRechnung XML. The invoice page shows the payment event history and related correction documents.
* **Transactions**: all payments and refunds with provider references; refunds are issued here (see [PAYMENTS.md](PAYMENTS.md#refunds)). A full refund suspends the services the invoice paid for. Every refund creates a correction document (credit note) for the refunded amount.
* **Webhook events**: every received payment webhook with its processing status.

## Taxes and invoices

* **Tax decision per invoice** (stored with the invoice: category, country, note): domestic customers and customers without verified VAT ID pay VAT; EU business customers with a VAT ID confirmed by VIES are invoiced net with the reverse charge note; with OSS, EU private customers pay the standard rate of their country; customers outside the EU are invoiced without VAT only if "No VAT outside the EU" is on. With the **small business** switch every invoice is issued without VAT and with the § 19 UStG note.
* **Sole proprietorship ≠ small business**: an Einzelunternehmen charges VAT normally unless it uses the small business rule (§ 19 UStG: previous-year turnover up to 25,000 EUR and current year up to 100,000 EUR since 2025). Leave the switch off unless your tax advisor confirms it.
* Customers enter their VAT ID in their profile; it is checked with VIES immediately and re-checked regularly. Reverse charge needs a company name matching the holder VIES reports (legal forms, case and umlaut spelling are ignored) – the profile and the customer page show *verified*, *registered to a different name*, *company name missing*, *not valid* or *not checked yet* together with the registered name. Business customers must give their full address (required for e-invoices). When staff change a customer's VAT ID or country, it is checked again immediately.
* **Documents**: invoices are rendered as PDF and, for business customers, as XRechnung 3.0 XML (UN/CEFACT CII). Both are generated once, archived in `storage/invoices` with a SHA-256 checksum and served unchanged afterwards (the archive is the legal record – back it up and keep it for the statutory 8–10 years). Order and renewal e-mails carry the documents as attachments.
* **Corrections**: issued invoices are never changed or deleted. Cancelling an unpaid invoice creates a cancellation document (prefix `RK-`, type 381 "credit note" in XRechnung) that refers to the original; a refund creates a correction document for the refunded amount (all items for a full refund, one line for a partial refund).
* Validate a sample XRechnung with the official KoSIT validator before you send e-invoices to business customers (see [INTEGRATIONS.md](INTEGRATIONS.md#e-invoices-xrechnung)).

## Cancellations and withdrawals

* Customers cancel through the **"Cancel contracts here"** link (§ 312k BGB) and withdraw through **"Withdraw from contract here"** (EU withdrawal function, mandatory since 19 June 2026). Both links are on every page, also for visitors who are not signed in; the forms work without an account.
* Every declaration is stored with the time of receipt and confirmed to the customer by e-mail immediately (the confirmation is the legal proof of receipt).
* **Ordinary cancellations** of signed-in customers for one of their own services take effect automatically at the end of the paid term (or a later requested date): no further renewal invoices are created and the service is terminated on that date (`services.cancellations` task). The customer can take such a cancellation back until then. Unpaid invoices for the time before the end date stay open, but are neither paid automatically from credit nor reminded – settle them manually.
* **Declarations of guests, extraordinary cancellations and withdrawals** appear in **Admin → Cancellations** for review (the identity of a guest cannot be verified automatically). Staff match the declaration to the customer (by account, e-mail or customer number), then *schedule* the end of the service (today or later), *complete* it after handling it manually (e.g. a withdrawal with refund via Transactions) or *reject* it with a note. Rejecting a scheduled declaration removes the end date – the service continues and is renewed. Cancellations scheduled by staff can only be taken back through staff; scheduling a second declaration for the same service replaces the first. Each step is audit-logged.
* Withdrawals: when the customer asked for the service to start during the withdrawal period, a proportionate amount for the time used may be retained – decide this case by case with your legal advisor.

## Services

* Filter by status and category; search by service name, customer e-mail or service ID.
* Service page: configuration, remote identifiers, live status (where supported), access data (revealing it is audit-logged), invoices, related jobs and the last provisioning error.
* Actions: suspend (with reason; the customer is notified), unsuspend, terminate (deletes the remote resource – confirm carefully), **retry provisioning** for failed services, **activate manually** (for services without automation, optionally storing access data).
* **Remote actions**: the actions the integration offers (power, password reset, reinstall, rescue system, reverse DNS, name servers, Plesk login, …) – the same as for customers, executed and audit-logged in the staff member's name.
* **Console**: graphical console for Proxmox VMs and containers (requires the console proxy, see INSTALLATION.md).
* **Backups**: Proxmox products with a backup storage and Pterodactyl products with a backup limit offer *Create / Restore / Delete backup* (Pterodactyl also *Download*). Customers only see the backups of their own server; restores of Proxmox guests run through the job queue like a reinstall. Terminating a service deletes its customer backups. See [INTEGRATIONS.md](INTEGRATIONS.md#customer-backups).
* Services are suspended automatically when their renewal invoice is unpaid after the grace period and reactivated when it is paid. Termination of long-suspended services happens only if enabled in the billing settings.

## Support tickets

Customers open tickets (optionally about one of their services). Staff reply, change status/priority and assign tickets. Ticket replies notify the customer by e-mail. Messages are shown as plain text.

## Integrations

Add Proxmox clusters, Hetzner Robot accounts, Plesk servers, INWX accounts, Pterodactyl panels, Nextcloud instances and TeamSpeak servers; credentials are stored encrypted and never displayed again. The integration page shows health, the last check, the request log and available resources (Proxmox nodes, Pterodactyl nodes/eggs, TeamSpeak servers, Hetzner servers with their pool state). Disabled integrations are not used for provisioning.

**Hetzner pool workflow**: name spare servers in Robot with the pool prefix (e.g. `nebrix-free-1`). Assigned servers are renamed to `nebrix-service-<id>`. After a termination the server appears as *wipe* (`nebrix-wipe-<number>`): wipe its disks, then rename it back to the pool prefix.

### IP pools
Admin → IP pools: create IPv4 pools (network, gateway, resolvers, optional range) and assign them to VPS/LXC products. The pool page shows every address with its status and service; free addresses can be reserved (and released) with a note. Pools that are used by products or still have assigned addresses cannot be deleted.

## E-mail templates

Admin → E-mail templates lists all system e-mails (registration, verification, password reset/changed, order confirmation, invoice, payment confirmation and reminder, correction document, cancellation receipt and confirmation, service provisioned/suspended/expiring, ticket reply, test). Each template has a subject, an HTML and a text body with `{{ variable }}` placeholders (the available variables are listed next to the editor) in **English and German** – switch the language above the editor. Customers receive the version in their account language; *Reset* restores the default of the selected language. *Preview* renders sample data in a sandbox. Variable values are HTML-escaped.

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
* New cancellations or withdrawals waiting for review? (Cancellations)
* Backups of database, `APP_KEY` and the invoice archive (`storage/invoices`) tested?
