<?php

namespace App\Support;

class InvoicePdf
{
    public static function render(array $payload): string
    {
        $pages = self::buildPages($payload);

        return self::compileDocument($pages, (string) ($payload['title'] ?? 'Invoice'));
    }

    private static function buildPages(array $payload): array
    {
        $siteLabel = self::safeText((string) ($payload['site_label'] ?? 'Invoice'));
        $siteAddress = self::safeMultiline((string) ($payload['site_address'] ?? ''));
        $customerName = self::safeText((string) ($payload['customer_name'] ?? 'Customer'));
        $customerAddress = self::safeMultiline((string) ($payload['customer_address'] ?? ''));
        $customerPhone = self::safeText((string) ($payload['customer_phone'] ?? ''));
        $invoiceNumber = self::safeText((string) ($payload['invoice_number'] ?? '-'));
        $invoiceDate = self::safeText((string) ($payload['invoice_date'] ?? '-'));
        $invoiceTotal = self::safeText((string) ($payload['invoice_total'] ?? '0.00'));
        $paymentSummary = self::safeText((string) ($payload['payment_summary'] ?? ''));
        $supportEmail = self::safeText((string) ($payload['support_email'] ?? ''));
        $items = $payload['items'] ?? [];

        $pages = [];
        $page = self::newPage();
        $siteAddressLines = $siteAddress !== '-' ? self::wrapLines($siteAddress, 42) : [];
        $customerAddressLines = $customerAddress !== '-' ? self::wrapLines($customerAddress, 32) : [];
        $siteAddressBottomY = 786 - ((max(count($siteAddressLines), 1) - 1) * 13);
        $invoiceTitleY = min(754, $siteAddressBottomY - 22);
        $invoiceMetaTopY = $invoiceTitleY - 36;
        $customerNameY = $invoiceMetaTopY - 18;
        $customerAddressTopY = $customerNameY - 18;
        $customerAddressBottomY = $customerAddressTopY - ((max(count($customerAddressLines), 1) - 1) * 13);
        $customerPhoneY = $customerAddressBottomY - 18;
        $invoiceBlockBottomY = $customerPhone !== '-' ? $customerPhoneY : $customerAddressBottomY;
        $tableRuleY = max(590, $invoiceBlockBottomY - 18);
        $tableHeaderY = $tableRuleY - 22;

        self::addText($page, 44, 804, $siteLabel, 'F2', 20);
        if ($siteAddress !== '-') {
            self::addMultilineText($page, 44, 786, $siteAddressLines, 'F1', 10, 13);
        }
        self::addText($page, 44, $invoiceTitleY, 'INVOICE', 'F2', 18);
        self::addRule($page, 44, $invoiceTitleY - 12, 551);

        self::addText($page, 44, $invoiceMetaTopY, 'BILLED TO:', 'F2', 10);
        self::addText($page, 44, $customerNameY, $customerName, 'F1', 11);
        if ($customerAddress !== '-') {
            self::addMultilineText($page, 44, $customerAddressTopY, $customerAddressLines, 'F1', 10, 13);
        }
        if ($customerPhone !== '-') {
            self::addText($page, 44, $customerPhoneY, $customerPhone, 'F1', 10);
        }

        self::addText($page, 372, $invoiceMetaTopY, 'INVOICE NO:', 'F2', 10);
        self::addText($page, 468, $invoiceMetaTopY, $invoiceNumber, 'F1', 11);
        self::addText($page, 372, $invoiceMetaTopY - 26, 'DATE:', 'F2', 10);
        self::addText($page, 468, $invoiceMetaTopY - 26, $invoiceDate, 'F1', 11);

        self::addRule($page, 44, $tableRuleY, 551);
        self::addTableHeader($page, $tableHeaderY);

        $y = $tableHeaderY - 22;
        $rowCount = 0;

        foreach ($items as $item) {
            if ($rowCount >= 24 || $y < 170) {
                $pages[] = $page;
                $page = self::newPage();
                self::addText($page, 44, 800, $siteLabel, 'F2', 16);
                self::addText($page, 44, 780, 'INVOICE', 'F2', 14);
                self::addText($page, 380, 780, 'Invoice No: '.$invoiceNumber, 'F1', 10);
                self::addRule($page, 44, 766, 551);
                self::addTableHeader($page, 744);
                $y = 722;
                $rowCount = 0;
            }

            self::addTableRow($page, $y, [
                self::truncate(self::safeText((string) ($item['description'] ?? '-')), 36),
                self::safeText((string) ($item['date'] ?? '-')),
                self::safeText((string) ($item['quantity'] ?? '1')),
                '$'.self::safeText((string) ($item['price'] ?? '0.00')),
                '$'.self::safeText((string) ($item['amount'] ?? '0.00')),
            ]);

            $y -= 28;
            $rowCount++;
        }

        $summaryStartY = max($y - 10, 170);
        self::addSummaryBlock($page, $summaryStartY, $invoiceTotal, $paymentSummary, $supportEmail);
        self::addPageNumber($page, count($pages) + 1);
        $pages[] = $page;

        return $pages;
    }

    private static function compileDocument(array $pages, string $title): string
    {
        $objects = [];
        $pageCount = count($pages);
        $font1Id = 3 + ($pageCount * 2);
        $font2Id = $font1Id + 1;

        $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";

        $kids = [];
        for ($i = 0; $i < $pageCount; $i++) {
            $kids[] = (3 + ($i * 2))." 0 R";
        }
        $objects[2] = "<< /Type /Pages /Count {$pageCount} /Kids [ ".implode(' ', $kids)." ] >>";

        foreach ($pages as $index => $page) {
            $pageId = 3 + ($index * 2);
            $contentId = $pageId + 1;
            $stream = implode("\n", $page)."\n";
            $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 {$font1Id} 0 R /F2 {$font2Id} 0 R >> >> /Contents {$contentId} 0 R >>";
            $objects[$contentId] = "<< /Length ".strlen($stream)." >>\nstream\n{$stream}endstream";
        }

        $objects[$font1Id] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
        $objects[$font2Id] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>";

        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $maxObject = max(array_keys($objects));
        $pdf .= "xref\n0 ".($maxObject + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= $maxObject; $i++) {
            $offset = $offsets[$i] ?? 0;
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $safeTitle = self::escape((string) $title);
        $pdf .= "trailer\n<< /Size ".($maxObject + 1)." /Root 1 0 R /Info << /Title ({$safeTitle}) /Producer (Digitizing Zone) >> >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    private static function newPage(): array
    {
        return [];
    }

    private static function addText(array &$page, int $x, int $y, string $text, string $font = 'F1', int $size = 11): void
    {
        $page[] = "BT /{$font} {$size} Tf 1 0 0 1 {$x} {$y} Tm (".self::escape($text).") Tj ET";
    }

    private static function addRule(array &$page, int $x1, int $y, int $x2): void
    {
        $page[] = "0.82 w {$x1} {$y} m {$x2} {$y} l S";
    }

    private static function addTableHeader(array &$page, int $y): void
    {
        self::addText($page, 44, $y, 'Description', 'F2', 10);
        self::addText($page, 286, $y, 'Date', 'F2', 10);
        self::addText($page, 386, $y, 'Qty', 'F2', 10);
        self::addText($page, 430, $y, 'Price', 'F2', 10);
        self::addText($page, 494, $y, 'Amount', 'F2', 10);
        self::addRule($page, 44, $y - 8, 551);
    }

    private static function addTableRow(array &$page, int $y, array $cells): void
    {
        self::addText($page, 44, $y, self::truncate($cells[0], 32), 'F1', 10);
        self::addText($page, 286, $y, $cells[1], 'F1', 10);
        self::addText($page, 386, $y, $cells[2], 'F1', 10);
        self::addText($page, 430, $y, $cells[3], 'F1', 10);
        self::addText($page, 494, $y, $cells[4], 'F1', 10);
        self::addRule($page, 44, $y - 8, 551);
    }

    private static function addSummaryBlock(array &$page, int $y, string $invoiceTotal, string $paymentSummary, string $supportEmail): void
    {
        self::addText($page, 44, $y - 24, 'TOTAL PAID: $'.$invoiceTotal.' USD', 'F2', 13);
        if ($paymentSummary !== '-') {
            self::addText($page, 44, $y - 48, $paymentSummary, 'F1', 10);
        }
        self::addText($page, 44, $y - 84, 'Thank you for your business!', 'F1', 10);
        if ($supportEmail !== '-') {
            self::addText($page, 44, $y - 110, 'For support, contact '.$supportEmail, 'F1', 10);
        }
    }

    private static function addMultilineText(array &$page, int $x, int $y, array $lines, string $font = 'F1', int $size = 11, int $lineHeight = 14): void
    {
        foreach (array_values($lines) as $index => $line) {
            self::addText($page, $x, $y - ($index * $lineHeight), $line, $font, $size);
        }
    }

    private static function addPageNumber(array &$page, int $pageNumber): void
    {
        self::addText($page, 500, 24, 'Page '.$pageNumber, 'F1', 9);
    }

    private static function truncate(string $value, int $length): string
    {
        return mb_strlen($value) > $length
            ? rtrim(mb_substr($value, 0, $length - 1)).'…'
            : $value;
    }

    private static function safeText(string $value): string
    {
        $clean = preg_replace('/\s+/', ' ', trim($value)) ?? '';

        return $clean === '' ? '-' : $clean;
    }

    private static function safeMultiline(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", trim($value));
        $lines = array_values(array_filter(array_map(
            static fn (string $line) => preg_replace('/\s+/', ' ', trim($line)) ?? '',
            explode("\n", $value)
        ), static fn (string $line) => $line !== ''));

        return $lines === [] ? '-' : implode("\n", $lines);
    }

    private static function wrapLines(string $value, int $maxCharacters): array
    {
        if ($value === '-' || $value === '') {
            return [$value === '' ? '-' : $value];
        }

        $wrapped = [];

        foreach (explode("\n", $value) as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $chunks = wordwrap($line, $maxCharacters, "\n", true);
            foreach (explode("\n", $chunks) as $chunk) {
                $wrapped[] = $chunk;
            }
        }

        return $wrapped === [] ? ['-'] : $wrapped;
    }

    private static function escape(string $value): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    }
}
