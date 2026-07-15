<?php

namespace App\Services;

/**
 * Lightweight PDF generation via HTML print instructions + browser.
 * For true binary PDF without Composer on shared hosting, we use an HTML-to-PDF
 * approach: try Dompdf if present, otherwise return printable HTML headers
 * and a note. Also ships a minimal TCPDF-free HTML invoice download as .html
 * with Content-Disposition, plus print view.
 *
 * Optional: place Dompdf at vendor/autoload.php for real PDF output.
 */
class BillPdfService
{
    public static function renderHtml(array $bill, array $items): string
    {
        $cgst = round(((float)$bill['subtotal'] - (float)$bill['pm_drive_incentive'] - (float)$bill['state_subsidy']) * ((float)$bill['cgst_rate'] / 100), 2);
        $sgst = round(((float)$bill['subtotal'] - (float)$bill['pm_drive_incentive'] - (float)$bill['state_subsidy']) * ((float)$bill['sgst_rate'] / 100), 2);
        $taxable = max(0, (float)$bill['subtotal'] - (float)$bill['pm_drive_incentive'] - (float)$bill['state_subsidy']);

        $rows = '';
        foreach ($items as $i => $item) {
            $rows .= '<tr>
                <td style="padding:8px;border:1px solid #ddd;">' . ($i + 1) . '</td>
                <td style="padding:8px;border:1px solid #ddd;">' . htmlspecialchars($item['description']) . '</td>
                <td style="padding:8px;border:1px solid #ddd;">' . htmlspecialchars($item['hsn_code']) . '</td>
                <td style="padding:8px;border:1px solid #ddd;text-align:right;">' . (int)$item['quantity'] . '</td>
                <td style="padding:8px;border:1px solid #ddd;text-align:right;">' . money($item['unit_price']) . '</td>
                <td style="padding:8px;border:1px solid #ddd;text-align:right;">' . money($item['total_price']) . '</td>
            </tr>';
        }

        $title = ($bill['bill_type'] ?? 'vehicle') === 'warranty' ? 'Warranty Certificate' : 'Tax Invoice';

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . htmlspecialchars($bill['bill_number']) . '</title>
        <style>
            body{font-family:DejaVu Sans,Arial,sans-serif;font-size:12px;color:#0f172a;margin:24px;}
            .header{display:flex;justify-content:space-between;border-bottom:2px solid #0d9488;padding-bottom:12px;margin-bottom:16px;}
            .brand{font-size:22px;font-weight:800;color:#0d9488;}
            .muted{color:#64748b;}
            table{width:100%;border-collapse:collapse;margin-top:12px;}
            th{background:#f0faf8;padding:8px;border:1px solid #ddd;text-align:left;}
            .totals{margin-top:16px;width:320px;margin-left:auto;}
            .totals td{padding:4px 8px;}
            .badge{display:inline-block;background:#0d9488;color:#fff;padding:4px 10px;border-radius:6px;font-size:11px;}
            @media print{.no-print{display:none;}}
        </style></head><body>
        <div class="no-print" style="margin-bottom:16px;">
            <button onclick="window.print()" style="background:#0d9488;color:#fff;border:0;padding:10px 16px;border-radius:8px;cursor:pointer;">Print / Save PDF</button>
        </div>
        <div class="header">
            <div>
                <div class="brand">' . htmlspecialchars($bill['brand_name'] ?: $bill['company_name'] ?: 'SK Mobility') . '</div>
                <div class="muted">' . nl2br(htmlspecialchars($bill['company_address'] ?? '')) . '</div>
                <div class="muted">GSTIN: ' . htmlspecialchars($bill['company_gstin'] ?? '') . ' | Phone: ' . htmlspecialchars($bill['company_phone'] ?? '') . '</div>
            </div>
            <div style="text-align:right;">
                <div class="badge">' . htmlspecialchars($title) . '</div>
                <div style="margin-top:8px;font-weight:700;">' . htmlspecialchars($bill['bill_number']) . '</div>
                <div class="muted">Date: ' . india_date($bill['created_at'] ?? date('Y-m-d')) . '</div>
            </div>
        </div>
        <div style="display:flex;gap:24px;margin-bottom:16px;">
            <div style="flex:1;">
                <strong>Bill To</strong><br>
                ' . htmlspecialchars($bill['customer_name'] ?? '') . '<br>
                ' . nl2br(htmlspecialchars($bill['customer_address'] ?? '')) . '<br>
                Phone: ' . htmlspecialchars($bill['customer_phone'] ?? '') . '
            </div>
            <div style="flex:1;">
                <strong>Vehicle Details</strong><br>
                Model: ' . htmlspecialchars($bill['vehicle_model'] ?? '—') . '<br>
                Chassis: ' . htmlspecialchars($bill['chassis_no'] ?? '—') . '<br>
                Motor: ' . htmlspecialchars($bill['motor_no'] ?? '—') . '
            </div>
        </div>
        <table>
            <thead><tr>
                <th>#</th><th>Description</th><th>HSN</th><th>Qty</th><th>Unit Price</th><th>Total</th>
            </tr></thead>
            <tbody>' . $rows . '</tbody>
        </table>
        <table class="totals">
            <tr><td>Subtotal</td><td style="text-align:right;">' . money($bill['subtotal']) . '</td></tr>
            <tr><td>PM E-DRIVE Incentive</td><td style="text-align:right;">- ' . money($bill['pm_drive_incentive']) . '</td></tr>
            <tr><td>State Subsidy</td><td style="text-align:right;">- ' . money($bill['state_subsidy']) . '</td></tr>
            <tr><td>Taxable Amount</td><td style="text-align:right;">' . money($taxable) . '</td></tr>
            <tr><td>CGST (' . (float)$bill['cgst_rate'] . '%)</td><td style="text-align:right;">' . money($cgst) . '</td></tr>
            <tr><td>SGST (' . (float)$bill['sgst_rate'] . '%)</td><td style="text-align:right;">' . money($sgst) . '</td></tr>
            <tr style="font-weight:800;font-size:14px;"><td>Grand Total</td><td style="text-align:right;">' . money($bill['total_amount']) . '</td></tr>
        </table>
        <p class="muted" style="margin-top:32px;">This is a computer-generated invoice under GST. HSN 87116020 applies to electric vehicles.</p>
        </body></html>';
    }

    public static function outputPdf(array $bill, array $items): void
    {
        $html = self::renderHtml($bill, $items);
        $autoload = BASE_PATH . '/vendor/autoload.php';

        if (is_file($autoload)) {
            require $autoload;
            if (class_exists('\\Dompdf\\Dompdf')) {
                $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false]);
                $dompdf->loadHtml($html);
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->render();
                $dompdf->stream($bill['bill_number'] . '.pdf', ['Attachment' => true]);
                return;
            }
        }

        // Fallback: printable HTML as downloadable invoice (shared hosting safe)
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: inline; filename="' . $bill['bill_number'] . '.html"');
        echo $html;
        exit;
    }
}
