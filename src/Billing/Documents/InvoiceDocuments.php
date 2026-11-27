<?php

declare(strict_types=1);

namespace Nebrix\Billing\Documents;

use Dompdf\Dompdf;
use Dompdf\Options;
use Nebrix\Billing\InvoiceRepository;
use Nebrix\Billing\Money;
use Nebrix\Core\Clock;
use Nebrix\Core\Config;
use Nebrix\Database\Connection;
use Nebrix\Settings\SettingsRepository;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Invoice documents: PDF (dompdf) and electronic invoice (XRechnung, CII).
 *
 * Documents are generated once and archived unchanged under
 * storage/invoices/<year>/<number>.pdf|.xml (GoBD); their SHA-256 hashes are
 * stored on the invoice. Later downloads and e-mails use the archived files,
 * so a changed company address or logo never alters an issued invoice. A
 * file whose hash no longer matches is reported and not delivered.
 *
 * Rendering is isolated: own Twig environment with auto-escaping, dompdf
 * without remote resources or embedded PHP.
 */
class InvoiceDocuments
{
    private const LABELS = [
        'en' => [
            'invoice' => 'Invoice', 'cancellation' => 'Cancellation invoice', 'correction' => 'Invoice correction',
            'number' => 'Invoice no.', 'document_number' => 'Document no.', 'date' => 'Date', 'customer_number' => 'Customer no.', 'due' => 'Due date',
            'description' => 'Description', 'quantity' => 'Qty', 'unit_price' => 'Unit price', 'tax_rate' => 'VAT', 'net' => 'Net',
            'subtotal' => 'Net total', 'vat' => 'VAT %s %% on %s', 'total' => 'Total', 'total_credit' => 'Credit total',
            'period' => 'Service period', 'delivery_date' => 'The date of supply corresponds to the invoice date.',
            'refers_to' => 'This document corrects invoice', 'customer_vat_id' => 'Your VAT ID', 'vat_id' => 'VAT ID', 'tax_number' => 'Tax no.', 'bank' => 'Bank account',
            'payment_text' => 'Please pay the invoice by %date% in the customer area.', 'transfer_text' => 'For bank transfers please state the invoice number %number%.',
            'correction_text' => 'The amount is refunded or credited to your customer account.',
            'void_text' => 'The invoice has been cancelled before it was paid. Nothing is payable for it.',
        ],
        'de' => [
            'invoice' => 'Rechnung', 'cancellation' => 'Stornorechnung', 'correction' => 'Rechnungskorrektur',
            'number' => 'Rechnungsnr.', 'document_number' => 'Belegnr.', 'date' => 'Datum', 'customer_number' => 'Kundennr.', 'due' => 'Fällig am',
            'description' => 'Beschreibung', 'quantity' => 'Menge', 'unit_price' => 'Einzelpreis', 'tax_rate' => 'USt.', 'net' => 'Netto',
            'subtotal' => 'Summe netto', 'vat' => 'USt. %s %% auf %s', 'total' => 'Gesamtbetrag', 'total_credit' => 'Gutschriftsbetrag',
            'period' => 'Leistungszeitraum', 'delivery_date' => 'Das Leistungsdatum entspricht dem Rechnungsdatum.',
            'refers_to' => 'Dieser Beleg korrigiert die Rechnung', 'customer_vat_id' => 'Ihre USt-IdNr.', 'vat_id' => 'USt-IdNr.', 'tax_number' => 'Steuernr.', 'bank' => 'Bankverbindung',
            'payment_text' => 'Bitte begleichen Sie die Rechnung bis zum %date% im Kundenbereich.', 'transfer_text' => 'Bei Überweisung geben Sie bitte die Rechnungsnummer %number% an.',
            'correction_text' => 'Der Betrag wird Ihnen erstattet bzw. Ihrem Kundenkonto gutgeschrieben.',
            'void_text' => 'Die Rechnung wurde vor der Zahlung storniert. Es ist kein Betrag zu zahlen.',
        ],
    ];

    public function __construct(
        private readonly InvoiceRepository $invoices,
        private readonly SettingsRepository $settings,
        private readonly Connection $db,
        private readonly Clock $clock,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function seller(): Seller
    {
        return Seller::fromSettings($this->settings);
    }

    /** The archived PDF (generated and archived on first use). */
    public function pdf(int $invoiceId): string
    {
        return $this->archive($invoiceId)['pdf'];
    }

    /** The archived XRechnung XML (generated and archived on first use). */
    public function xml(int $invoiceId): string
    {
        return $this->archive($invoiceId)['xml'];
    }

    /**
     * Attachments for an e-mail about the invoice: the PDF, and for business
     * customers (company or VAT ID) the XRechnung file when enabled.
     *
     * @return list<array{filename: string, content: string, mime: string}>
     */
    public function attachments(int $invoiceId): array
    {
        try {
            $files = $this->archive($invoiceId);
        } catch (\Throwable $exception) {
            $this->logger->error('Invoice documents could not be created', ['invoice' => $invoiceId, 'message' => $exception->getMessage()]);

            return [];
        }
        $invoice = $this->invoices->find($invoiceId) ?? throw new RuntimeException('Invoice not found.');
        $billing = json_decode((string) ($invoice['billing_details'] ?? '{}'), true);
        $business = is_array($billing) && (trim((string) ($billing['company'] ?? '')) !== '' || trim((string) ($billing['vat_id'] ?? '')) !== '');

        $attachments = [['filename' => $this->filename($invoice) . '.pdf', 'content' => $files['pdf'], 'mime' => 'application/pdf']];
        if ($business && $this->settings->bool('invoice.e_invoice_b2b')) {
            $attachments[] = ['filename' => $this->filename($invoice) . '.xml', 'content' => $files['xml'], 'mime' => 'application/xml'];
        }

        return $attachments;
    }

    /**
     * @param array<string, mixed> $invoice
     */
    public function filename(array $invoice): string
    {
        return (string) preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $invoice['number']);
    }

    /**
     * @return array{pdf: string, xml: string}
     */
    private function archive(int $invoiceId): array
    {
        // One writer per invoice: the documents of an invoice are generated exactly once.
        return $this->db->withLock('nebrix:invoice-archive:' . $invoiceId, 30, function () use ($invoiceId): array {
            $invoice = $this->invoices->find($invoiceId) ?? throw new RuntimeException('Invoice not found.');
            $base = $this->path($invoice);

            if ($invoice['archived_at'] !== null) {
                $pdf = @file_get_contents($base . '.pdf');
                $xml = @file_get_contents($base . '.xml');
                if ($pdf === false || $xml === false || !hash_equals((string) $invoice['pdf_sha256'], hash('sha256', $pdf)) || !hash_equals((string) $invoice['xml_sha256'], hash('sha256', $xml))) {
                    $this->logger->critical('Archived invoice document is missing or was modified', ['invoice' => $invoiceId, 'number' => $invoice['number']]);

                    throw new RuntimeException('The archived invoice document is not available. Please contact support.');
                }

                return ['pdf' => $pdf, 'xml' => $xml];
            }

            $items = $this->invoices->items($invoiceId);
            $original = $invoice['reference_invoice_id'] !== null ? $this->invoices->find((int) $invoice['reference_invoice_id']) : null;
            $seller = $this->seller();
            $pdf = $this->renderPdf($invoice, $items, $seller, $original);
            $xml = (new XRechnungWriter())->write($invoice, $items, $seller, $original);

            $directory = dirname($base);
            if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) {
                throw new RuntimeException('The invoice archive directory is not writable.');
            }
            foreach (['pdf' => $pdf, 'xml' => $xml] as $extension => $content) {
                $temporary = $base . '.' . $extension . '.' . bin2hex(random_bytes(4)) . '.tmp';
                if (file_put_contents($temporary, $content, LOCK_EX) === false || !rename($temporary, $base . '.' . $extension)) {
                    @unlink($temporary);
                    throw new RuntimeException('The invoice document could not be archived.');
                }
                @chmod($base . '.' . $extension, 0440);
            }
            $this->db->run(
                'UPDATE `nx_invoices` SET `archived_at` = ?, `pdf_sha256` = ?, `xml_sha256` = ? WHERE `id` = ? AND `archived_at` IS NULL',
                [$this->clock->nowString(), hash('sha256', $pdf), hash('sha256', $xml), $invoiceId],
            );

            return ['pdf' => $pdf, 'xml' => $xml];
        });
    }

    /**
     * @param array<string, mixed> $invoice
     */
    private function path(array $invoice): string
    {
        $year = substr((string) $invoice['created_at'], 0, 4);

        return rtrim($this->config->string('paths.invoices'), '/') . '/' . $year . '/' . $this->filename($invoice) . '-' . $invoice['id'];
    }

    /**
     * @param array<string, mixed> $invoice
     * @param list<array<string, mixed>> $items
     * @param array<string, mixed>|null $original
     */
    private function renderPdf(array $invoice, array $items, Seller $seller, ?array $original): string
    {
        $billing = json_decode((string) ($invoice['billing_details'] ?? '{}'), true);
        $billing = is_array($billing) ? $billing : [];
        $locale = $this->locale((int) $invoice['user_id']);
        $labels = self::LABELS[$locale];
        $currency = (string) $invoice['currency'];
        $money = static fn (int $minor): string => Money::format($minor, $currency, $locale);
        $date = static fn (?string $value): string => $value === null ? '' : date($locale === 'de' ? 'd.m.Y' : 'Y-m-d', strtotime($value . ' UTC') ?: 0);
        $correction = $invoice['type'] === InvoiceRepository::TYPE_CREDIT_NOTE;

        $rows = [];
        $taxes = [];
        $hasPeriod = false;
        foreach ($items as $item) {
            $rate = (int) $item['tax_rate_bp'];
            $period = null;
            if ($item['period_start'] !== null && $item['period_end'] !== null) {
                $hasPeriod = true;
                $period = $date((string) $item['period_start']) . ' – ' . $date(date('Y-m-d H:i:s', (strtotime($item['period_end'] . ' UTC') ?: 0) - 86400));
            }
            $rows[] = [
                'description' => (string) $item['description'],
                'quantity' => (int) $item['quantity'],
                'unit' => $money((int) $item['unit_amount']),
                'rate' => number_format($rate / 100, $rate % 100 === 0 ? 0 : 2, $locale === 'de' ? ',' : '.', '') . ' %',
                'net' => $money((int) $item['total']),
                'period' => $period,
            ];
            $taxes[$rate] = ($taxes[$rate] ?? 0) + (int) $item['total'];
        }
        $taxRows = [];
        foreach ($taxes as $rate => $basis) {
            if ($rate > 0) {
                $taxRows[] = ['label' => sprintf($labels['vat'], number_format($rate / 100, $rate % 100 === 0 ? 0 : 2, $locale === 'de' ? ',' : '.', ''), $money($basis)), 'amount' => $money(\Nebrix\Billing\Money::tax($basis, $rate))];
            }
        }
        if ($taxRows === []) {
            $taxRows[] = ['label' => sprintf($labels['vat'], '0', $money((int) $invoice['subtotal'])), 'amount' => $money(0)];
        }

        $twig = new Environment(new FilesystemLoader($this->config->string('paths.views') . '/pdf'), ['autoescape' => 'html', 'strict_variables' => false, 'cache' => false]);
        $html = $twig->render('invoice.twig', [
            'locale' => $locale,
            'labels' => $labels,
            'title' => $correction ? ($original !== null && (int) $invoice['total'] === (int) $original['total'] ? $labels['cancellation'] : $labels['correction']) : $labels['invoice'],
            'correction' => $correction,
            // Cancellation of an unpaid invoice: nothing was paid, nothing is refunded.
            'void' => $correction && $original !== null && $original['status'] === InvoiceRepository::STATUS_CANCELLED,
            'invoice' => $invoice,
            'billing' => $billing,
            'seller' => $seller,
            'items' => $rows,
            'tax_rows' => $taxRows,
            'has_period' => $hasPeriod,
            'subtotal' => $money((int) $invoice['subtotal']),
            'total' => $money((int) $invoice['total']),
            'issue_date' => $date((string) $invoice['created_at']),
            'due_date' => $date($invoice['due_at'] !== null ? (string) $invoice['due_at'] : null),
            'original' => $original,
            'original_date' => $original !== null ? $date((string) $original['created_at']) : '',
        ]);

        $options = new Options();
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setIsJavascriptEnabled(false);
        $options->setDefaultFont('DejaVu Sans');
        $options->setChroot([$this->config->string('paths.views') . '/pdf']);
        $cache = $this->config->string('paths.cache') . '/dompdf';
        if (!is_dir($cache)) {
            @mkdir($cache, 0750, true);
        }
        $options->setFontCache($cache);
        $options->setTempDir(sys_get_temp_dir());

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->addInfo('Title', (string) $invoice['number']);
        $dompdf->addInfo('Creator', 'Nebrix');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    private function locale(int $userId): string
    {
        $language = $this->db->fetchValue('SELECT `language` FROM `nx_users` WHERE `id` = ?', [$userId]);
        $language = is_string($language) && $language !== '' ? $language : ($this->settings->string('general.default_language') ?: 'en');

        return isset(self::LABELS[$language]) ? $language : 'en';
    }
}
