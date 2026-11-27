<?php

declare(strict_types=1);

/*
 * Default e-mail templates. Administrators can override subject and body in
 * Admin → E-mail templates; "Reset to default" restores these versions.
 *
 * Placeholders use {{ name }}. Values are HTML-escaped automatically in the
 * HTML body. Always available: company_name, support_email, app_url.
 */
return [
    'account_registration' => [
        'name' => 'Account registration',
        'variables' => ['name', 'email', 'login_url'],
        'subject' => 'Welcome to {{ company_name }}',
        'html' => '<p>Hello {{ name }},</p><p>thank you for creating an account with {{ company_name }}. You can sign in at any time:</p><p><a class="button" href="{{ login_url }}">Sign in</a></p><p>If you did not create this account, please contact {{ support_email }}.</p>',
    ],
    'email_verification' => [
        'name' => 'E-mail verification',
        'variables' => ['name', 'verification_url', 'expires_hours'],
        'subject' => 'Please confirm your e-mail address',
        'html' => '<p>Hello {{ name }},</p><p>please confirm your e-mail address to activate your account:</p><p><a class="button" href="{{ verification_url }}">Confirm e-mail address</a></p><p>The link is valid for {{ expires_hours }} hours. If you did not register, you can ignore this e-mail.</p>',
    ],
    'password_reset' => [
        'name' => 'Password reset',
        'variables' => ['name', 'reset_url', 'expires_minutes', 'ip'],
        'subject' => 'Reset your password',
        'html' => '<p>Hello {{ name }},</p><p>we received a request to reset your password (from IP address {{ ip }}). Use the button below to choose a new password:</p><p><a class="button" href="{{ reset_url }}">Reset password</a></p><p>The link is valid for {{ expires_minutes }} minutes and can be used once. If you did not request this, you can ignore this e-mail – your password stays unchanged.</p>',
    ],
    'api_token_created' => [
        'name' => 'API token created',
        'variables' => ['name', 'token_name', 'scopes', 'expires', 'ip', 'tokens_url'],
        'subject' => 'A new API token was created for your account',
        'html' => '<p>Hello {{ name }},</p><p>a new API token "{{ token_name }}" was created for your {{ company_name }} account (IP address {{ ip }}). Permissions: {{ scopes }}. The token expires on {{ expires }}.</p><p>If you did not create this token, revoke it immediately, change your password and contact {{ support_email }}.</p><p><a class="button" href="{{ tokens_url }}">Manage API tokens</a></p>',
    ],
    'password_changed' => [
        'name' => 'Password changed',
        'variables' => ['name', 'ip', 'time'],
        'subject' => 'Your password was changed',
        'html' => '<p>Hello {{ name }},</p><p>the password of your {{ company_name }} account was changed on {{ time }} (IP address {{ ip }}). All other sessions were signed out and all API tokens were revoked.</p><p>If this was not you, reset your password immediately and contact {{ support_email }}.</p>',
    ],
    'order_confirmation' => [
        'name' => 'Order confirmation',
        'variables' => ['name', 'order_number', 'order_total', 'invoice_url'],
        'subject' => 'Order {{ order_number }} received',
        'html' => '<p>Hello {{ name }},</p><p>thank you for your order <strong>{{ order_number }}</strong> ({{ order_total }}).</p><p>Your services will be set up automatically as soon as the payment has been confirmed.</p><p><a class="button" href="{{ invoice_url }}">View and pay invoice</a></p>',
    ],
    'invoice_created' => [
        'name' => 'Invoice created',
        'variables' => ['name', 'invoice_number', 'invoice_total', 'due_date', 'invoice_url'],
        'subject' => 'Invoice {{ invoice_number }}',
        'html' => '<p>Hello {{ name }},</p><p>invoice <strong>{{ invoice_number }}</strong> over {{ invoice_total }} is due on {{ due_date }}.</p><p><a class="button" href="{{ invoice_url }}">View and pay invoice</a></p>',
    ],
    'payment_confirmation' => [
        'name' => 'Payment confirmation',
        'variables' => ['name', 'invoice_number', 'amount', 'gateway'],
        'subject' => 'Payment received for invoice {{ invoice_number }}',
        'html' => '<p>Hello {{ name }},</p><p>we have received your payment of {{ amount }} via {{ gateway }} for invoice {{ invoice_number }}. Thank you!</p>',
    ],
    'service_provisioned' => [
        'name' => 'Service provisioned',
        'variables' => ['name', 'service_name', 'service_url'],
        'subject' => 'Your service "{{ service_name }}" is ready',
        'html' => '<p>Hello {{ name }},</p><p>your service <strong>{{ service_name }}</strong> has been set up. For security reasons access data is not sent by e-mail; you can view it in your customer area:</p><p><a class="button" href="{{ service_url }}">Open service</a></p>',
    ],
    'service_suspended' => [
        'name' => 'Service suspended',
        'variables' => ['name', 'service_name', 'reason', 'service_url'],
        'subject' => 'Your service "{{ service_name }}" was suspended',
        'html' => '<p>Hello {{ name }},</p><p>your service <strong>{{ service_name }}</strong> was suspended. Reason: {{ reason }}</p><p>Pay the open invoice to reactivate it automatically.</p><p><a class="button" href="{{ service_url }}">Open service</a></p>',
    ],
    'service_expiring' => [
        'name' => 'Service expiration',
        'variables' => ['name', 'service_name', 'due_date', 'invoice_url'],
        'subject' => 'Your service "{{ service_name }}" is due for renewal',
        'html' => '<p>Hello {{ name }},</p><p>your service <strong>{{ service_name }}</strong> is due for renewal on {{ due_date }}. Please pay the renewal invoice to avoid an interruption. If automatic payment from your credit is enabled and the balance suffices, the invoice is paid automatically.</p><p><a class="button" href="{{ invoice_url }}">Pay renewal invoice</a></p>',
    ],
    'invoice_reminder' => [
        'name' => 'Payment reminder',
        'variables' => ['name', 'invoice_number', 'invoice_total', 'due_date', 'invoice_url'],
        'subject' => 'Payment reminder: invoice {{ invoice_number }}',
        'html' => '<p>Hello {{ name }},</p><p>invoice {{ invoice_number }} over {{ invoice_total }} is due on {{ due_date }} and has not been paid yet. Please pay it in time – services with unpaid renewal invoices are suspended after the due date.</p><p><a class="button" href="{{ invoice_url }}">Pay invoice</a></p><p>If you have already paid, please ignore this e-mail.</p>',
    ],
    'cancellation_received' => [
        'name' => 'Cancellation / withdrawal received',
        'variables' => ['name', 'reference', 'kind', 'contract', 'received_at', 'effective', 'reason'],
        'subject' => 'Confirmation of receipt: {{ kind }} {{ reference }}',
        'html' => '<p>Hello {{ name }},</p><p>we have received your declaration and confirm its receipt:</p><p>Reference: <strong>{{ reference }}</strong><br>Type: {{ kind }}<br>Contract: {{ contract }}<br>Received: {{ received_at }}<br>Requested end: {{ effective }}<br>Reason: {{ reason }}</p><p>We will confirm the exact end date separately. If you did not send this declaration, please contact {{ support_email }} immediately.</p>',
    ],
    'cancellation_confirmed' => [
        'name' => 'Cancellation confirmed',
        'variables' => ['name', 'reference', 'service_name', 'effective_date', 'service_url'],
        'subject' => 'Your cancellation of "{{ service_name }}" takes effect on {{ effective_date }}',
        'html' => '<p>Hello {{ name }},</p><p>your service <strong>{{ service_name }}</strong> ends on <strong>{{ effective_date }}</strong> (reference {{ reference }}). No renewal invoices are issued for the time after this date; invoices for the time before it remain payable. Please save your data before that date – the service and its data are deleted when it ends.</p><p>If you want to keep the service, take back the cancellation in your customer area or contact our support before that date.</p><p><a class="button" href="{{ service_url }}">Open service</a></p>',
    ],
    'cancellation_rejected' => [
        'name' => 'Cancellation / withdrawal not accepted',
        'variables' => ['name', 'reference', 'note'],
        'subject' => 'Your declaration {{ reference }}',
        'html' => '<p>Hello {{ name }},</p><p>we could not process your declaration {{ reference }}:</p><p>{{ note }}</p><p>If you have questions, please contact {{ support_email }}.</p>',
    ],
    'invoice_correction' => [
        'name' => 'Cancellation / correction document',
        'variables' => ['name', 'document_number', 'invoice_number', 'amount', 'document_url'],
        'subject' => 'Document {{ document_number }} for invoice {{ invoice_number }}',
        'html' => '<p>Hello {{ name }},</p><p>we have issued the document <strong>{{ document_number }}</strong> over {{ amount }} for invoice {{ invoice_number }} (cancellation or refund). It is attached and available in your customer area.</p><p><a class="button" href="{{ document_url }}">View document</a></p>',
    ],
    'ticket_reply' => [
        'name' => 'Support ticket reply',
        'variables' => ['name', 'ticket_subject', 'ticket_url'],
        'subject' => 'New reply to your ticket "{{ ticket_subject }}"',
        'html' => '<p>Hello {{ name }},</p><p>our support team has replied to your ticket <strong>{{ ticket_subject }}</strong>.</p><p><a class="button" href="{{ ticket_url }}">View ticket</a></p>',
    ],
    'test' => [
        'name' => 'SMTP test message',
        'variables' => [],
        'subject' => 'Test e-mail from {{ company_name }}',
        'html' => '<p>This is a test message. Your SMTP settings work.</p>',
    ],
];
