<?php

declare(strict_types=1);

/*
 * German default e-mail templates (keys as in mail_templates.php). Used for
 * recipients whose account language is German; administrators can override
 * them per language in Admin → E-mail templates.
 */
return [
    'account_registration' => [
        'subject' => 'Willkommen bei {{ company_name }}',
        'html' => '<p>Hallo {{ name }},</p><p>vielen Dank für Ihre Registrierung bei {{ company_name }}. Sie können sich jederzeit anmelden:</p><p><a class="button" href="{{ login_url }}">Anmelden</a></p><p>Falls Sie dieses Konto nicht erstellt haben, wenden Sie sich bitte an {{ support_email }}.</p>',
    ],
    'email_verification' => [
        'subject' => 'Bitte bestätigen Sie Ihre E-Mail-Adresse',
        'html' => '<p>Hallo {{ name }},</p><p>bitte bestätigen Sie Ihre E-Mail-Adresse, um Ihr Konto zu aktivieren:</p><p><a class="button" href="{{ verification_url }}">E-Mail-Adresse bestätigen</a></p><p>Der Link ist {{ expires_hours }} Stunden gültig. Falls Sie sich nicht registriert haben, können Sie diese E-Mail ignorieren.</p>',
    ],
    'password_reset' => [
        'subject' => 'Passwort zurücksetzen',
        'html' => '<p>Hallo {{ name }},</p><p>wir haben eine Anfrage zum Zurücksetzen Ihres Passworts erhalten (von der IP-Adresse {{ ip }}). Über die Schaltfläche wählen Sie ein neues Passwort:</p><p><a class="button" href="{{ reset_url }}">Passwort zurücksetzen</a></p><p>Der Link ist {{ expires_minutes }} Minuten gültig und kann einmal verwendet werden. Falls Sie das nicht angefordert haben, können Sie diese E-Mail ignorieren – Ihr Passwort bleibt unverändert.</p>',
    ],
    'api_token_created' => [
        'subject' => 'Für Ihr Konto wurde ein neues API-Token erstellt',
        'html' => '<p>Hallo {{ name }},</p><p>für Ihr Konto bei {{ company_name }} wurde das API-Token „{{ token_name }}“ erstellt (IP-Adresse {{ ip }}). Berechtigungen: {{ scopes }}. Das Token läuft am {{ expires }} ab.</p><p>Falls Sie dieses Token nicht erstellt haben, widerrufen Sie es sofort, ändern Sie Ihr Passwort und wenden Sie sich an {{ support_email }}.</p><p><a class="button" href="{{ tokens_url }}">API-Tokens verwalten</a></p>',
    ],
    'password_changed' => [
        'subject' => 'Ihr Passwort wurde geändert',
        'html' => '<p>Hallo {{ name }},</p><p>das Passwort Ihres Kontos bei {{ company_name }} wurde am {{ time }} geändert (IP-Adresse {{ ip }}). Alle anderen Sitzungen wurden abgemeldet und alle API-Tokens widerrufen.</p><p>Falls Sie das nicht waren, setzen Sie Ihr Passwort sofort zurück und wenden Sie sich an {{ support_email }}.</p>',
    ],
    'order_confirmation' => [
        'subject' => 'Bestellung {{ order_number }} eingegangen',
        'html' => '<p>Hallo {{ name }},</p><p>vielen Dank für Ihre Bestellung <strong>{{ order_number }}</strong> ({{ order_total }}).</p><p>Ihre Dienste werden automatisch eingerichtet, sobald die Zahlung bestätigt ist.</p><p><a class="button" href="{{ invoice_url }}">Rechnung ansehen und bezahlen</a></p>',
    ],
    'invoice_created' => [
        'subject' => 'Rechnung {{ invoice_number }}',
        'html' => '<p>Hallo {{ name }},</p><p>die Rechnung <strong>{{ invoice_number }}</strong> über {{ invoice_total }} ist am {{ due_date }} fällig.</p><p><a class="button" href="{{ invoice_url }}">Rechnung ansehen und bezahlen</a></p>',
    ],
    'payment_confirmation' => [
        'subject' => 'Zahlung für Rechnung {{ invoice_number }} eingegangen',
        'html' => '<p>Hallo {{ name }},</p><p>wir haben Ihre Zahlung über {{ amount }} per {{ gateway }} für die Rechnung {{ invoice_number }} erhalten. Vielen Dank!</p>',
    ],
    'service_provisioned' => [
        'subject' => 'Ihr Dienst „{{ service_name }}“ ist bereit',
        'html' => '<p>Hallo {{ name }},</p><p>Ihr Dienst <strong>{{ service_name }}</strong> wurde eingerichtet. Aus Sicherheitsgründen versenden wir Zugangsdaten nicht per E-Mail; Sie finden sie in Ihrem Kundenbereich:</p><p><a class="button" href="{{ service_url }}">Dienst öffnen</a></p>',
    ],
    'service_suspended' => [
        'subject' => 'Ihr Dienst „{{ service_name }}“ wurde gesperrt',
        'html' => '<p>Hallo {{ name }},</p><p>Ihr Dienst <strong>{{ service_name }}</strong> wurde gesperrt. Grund: {{ reason }}</p><p>Begleichen Sie die offene Rechnung, dann wird der Dienst automatisch wieder freigeschaltet.</p><p><a class="button" href="{{ service_url }}">Dienst öffnen</a></p>',
    ],
    'service_expiring' => [
        'subject' => 'Verlängerung Ihres Dienstes „{{ service_name }}“',
        'html' => '<p>Hallo {{ name }},</p><p>Ihr Dienst <strong>{{ service_name }}</strong> verlängert sich am {{ due_date }}. Bitte bezahlen Sie die Verlängerungsrechnung, um eine Unterbrechung zu vermeiden. Ist die automatische Zahlung aus dem Guthaben aktiv und reicht Ihr Guthaben aus, wird die Rechnung automatisch beglichen.</p><p><a class="button" href="{{ invoice_url }}">Verlängerungsrechnung bezahlen</a></p>',
    ],
    'invoice_reminder' => [
        'subject' => 'Zahlungserinnerung: Rechnung {{ invoice_number }}',
        'html' => '<p>Hallo {{ name }},</p><p>die Rechnung {{ invoice_number }} über {{ invoice_total }} ist am {{ due_date }} fällig und noch nicht bezahlt. Bitte begleichen Sie sie rechtzeitig – Dienste mit unbezahlter Verlängerungsrechnung werden nach dem Fälligkeitsdatum gesperrt.</p><p><a class="button" href="{{ invoice_url }}">Rechnung bezahlen</a></p><p>Falls Sie bereits bezahlt haben, betrachten Sie diese E-Mail bitte als gegenstandslos.</p>',
    ],
    'cancellation_received' => [
        'subject' => 'Eingangsbestätigung: {{ kind }} {{ reference }}',
        'html' => '<p>Hallo {{ name }},</p><p>wir haben Ihre Erklärung erhalten und bestätigen den Eingang:</p><p>Referenz: <strong>{{ reference }}</strong><br>Art: {{ kind }}<br>Vertrag: {{ contract }}<br>Eingegangen: {{ received_at }}<br>Gewünschtes Vertragsende: {{ effective }}<br>Grund: {{ reason }}</p><p>Das genaue Vertragsende bestätigen wir Ihnen gesondert. Falls Sie diese Erklärung nicht abgegeben haben, wenden Sie sich bitte umgehend an {{ support_email }}.</p>',
    ],
    'cancellation_confirmed' => [
        'subject' => 'Ihre Kündigung von „{{ service_name }}“ wird zum {{ effective_date }} wirksam',
        'html' => '<p>Hallo {{ name }},</p><p>Ihr Dienst <strong>{{ service_name }}</strong> endet am <strong>{{ effective_date }}</strong> (Referenz {{ reference }}). Für die Zeit nach diesem Datum werden keine Verlängerungsrechnungen mehr gestellt; Rechnungen für die Zeit davor bleiben zahlbar. Bitte sichern Sie Ihre Daten vorher – mit dem Vertragsende werden der Dienst und seine Daten gelöscht.</p><p>Wenn Sie den Dienst behalten möchten, nehmen Sie die Kündigung vor diesem Datum in Ihrem Kundenbereich zurück oder wenden Sie sich an unseren Support.</p><p><a class="button" href="{{ service_url }}">Dienst öffnen</a></p>',
    ],
    'cancellation_rejected' => [
        'subject' => 'Ihre Erklärung {{ reference }}',
        'html' => '<p>Hallo {{ name }},</p><p>wir konnten Ihre Erklärung {{ reference }} nicht bearbeiten:</p><p>{{ note }}</p><p>Bei Fragen wenden Sie sich bitte an {{ support_email }}.</p>',
    ],
    'invoice_correction' => [
        'subject' => 'Beleg {{ document_number }} zu Rechnung {{ invoice_number }}',
        'html' => '<p>Hallo {{ name }},</p><p>wir haben den Beleg <strong>{{ document_number }}</strong> über {{ amount }} zur Rechnung {{ invoice_number }} ausgestellt (Storno oder Erstattung). Sie finden ihn im Anhang und in Ihrem Kundenbereich.</p><p><a class="button" href="{{ document_url }}">Beleg ansehen</a></p>',
    ],
    'ticket_reply' => [
        'subject' => 'Neue Antwort auf Ihr Ticket „{{ ticket_subject }}“',
        'html' => '<p>Hallo {{ name }},</p><p>unser Support-Team hat auf Ihr Ticket <strong>{{ ticket_subject }}</strong> geantwortet.</p><p><a class="button" href="{{ ticket_url }}">Ticket ansehen</a></p>',
    ],
    'test' => [
        'subject' => 'Test-E-Mail von {{ company_name }}',
        'html' => '<p>Dies ist eine Testnachricht. Ihre SMTP-Einstellungen funktionieren.</p>',
    ],
];
