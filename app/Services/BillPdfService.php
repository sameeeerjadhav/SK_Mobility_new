<?php

namespace App\Services;

/**
 * SAI KUBER MOBILITY style tax invoice (printable HTML / Dompdf).
 */
class BillPdfService
{
    public static function renderHtml(array $bill, array $items): string
    {
        $cgstRate = (float)($bill['cgst_rate'] ?? 14);
        $sgstRate = (float)($bill['sgst_rate'] ?? 14);
        $saleDate = $bill['vehicle_sale_date'] ?? $bill['created_at'] ?? null;
        $invoiceNo = self::displayInvoiceNo($bill['bill_number'] ?? '');
        $bookingNo = $bill['booking_no'] ?? '';
        $total = (float)($bill['total_amount'] ?? 0);
        $loan = (float)($bill['loan_amount'] ?? 0);
        $payment = strtolower((string)($bill['payment_mode'] ?? ''));
        $paidCash = str_contains($payment, 'cash');
        $paidCheque = str_contains($payment, 'cheque') || str_contains($payment, 'check');

        $companyState = $bill['company_state'] ?? setting('company_state', 'Maharashtra');
        $branch = $bill['company_branch_address'] ?? setting('company_branch_address', '');

        $battery = $bill['battery_type_no'] ?? '';
        if ($battery === '' && !empty($bill['battery_capacity'])) {
            $battery = (string)$bill['battery_capacity'];
        }

        $rows = '';
        $rowCount = max(5, count($items));
        for ($i = 0; $i < $rowCount; $i++) {
            $item = $items[$i] ?? null;
            if ($item) {
                [$taxable, $cgst, $sgst, $lineTotal, $disc] = self::lineAmounts($item, $bill, $i === 0);
                $name = htmlspecialchars($item['description'] ?? '');
                $code = htmlspecialchars($item['model_code'] ?? '');
                $modelCell = $name . ($code !== '' ? '<br><span style="font-size:10px;">' . $code . '</span>' : '');
                $rows .= '<tr>
                    <td class="c">' . ($i + 1) . '</td>
                    <td>' . $modelCell . '</td>
                    <td class="r">' . self::num($item['unit_price']) . '</td>
                    <td class="c">' . (int)$item['quantity'] . '</td>
                    <td class="r">' . self::num($disc) . '</td>
                    <td class="r">' . self::num($taxable) . '</td>
                    <td class="r">' . self::num($cgst) . '</td>
                    <td class="r">' . self::num($sgst) . '</td>
                    <td class="r">' . self::num($lineTotal) . '</td>
                </tr>';
            } else {
                $rows .= '<tr class="empty-row">
                    <td class="c">' . ($i + 1) . '</td>
                    <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
                </tr>';
            }
        }

        $v = static fn (?string $k) => htmlspecialchars((string)($bill[$k] ?? ''));
        $blank = static fn (?string $val) => htmlspecialchars($val !== null && $val !== '' ? $val : '');

        return '<!DOCTYPE html><html><head><meta charset="utf-8">
<title>Tax Invoice ' . htmlspecialchars($bill['bill_number'] ?? '') . '</title>
<style>
  @page { size: A4; margin: 10mm; }
  * { box-sizing: border-box; }
  body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 11px; color: #111; margin: 0; padding: 12px; }
  .sheet { border: 2px solid #111; max-width: 800px; margin: 0 auto; }
  .hdr { display: table; width: 100%; border-bottom: 1.5px solid #111; }
  .hdr-logo { display: table-cell; width: 110px; vertical-align: middle; padding: 10px 8px; text-align: center; }
  .logo-mark { color: #c62828; font-weight: 900; font-size: 28px; line-height: 1; letter-spacing: -1px; }
  .logo-sub { font-weight: 800; font-size: 11px; letter-spacing: 1px; color: #111; }
  .hdr-mid { display: table-cell; vertical-align: middle; padding: 8px 6px; text-align: center; }
  .co-name { color: #c62828; font-weight: 900; font-size: 22px; letter-spacing: 1px; margin: 0 0 4px; }
  .co-line { font-size: 10px; line-height: 1.35; margin: 0; }
  .gst-bar { border-bottom: 1.5px solid #111; padding: 5px 10px; font-size: 11px; font-weight: 700; text-align: center; }
  .gst-bar span { margin: 0 14px; }
  .top-grid { display: table; width: 100%; border-bottom: 1.5px solid #111; }
  .cust { display: table-cell; width: 62%; vertical-align: top; padding: 8px 10px; border-right: 1.5px solid #111; }
  .invbox { display: table-cell; width: 38%; vertical-align: top; padding: 8px 10px; }
  .field { margin: 0 0 5px; line-height: 1.4; }
  .lab { font-weight: 700; }
  .tax-title { background: #111; color: #fff; text-align: center; font-weight: 800; padding: 5px 8px; margin: 0 0 8px; letter-spacing: 1px; font-size: 13px; }
  .inv-row { margin: 0 0 6px; }
  .inv-no { color: #c62828; font-weight: 800; font-size: 14px; }
  table.grid { width: 100%; border-collapse: collapse; }
  table.grid td, table.grid th { border: 1px solid #111; padding: 5px 6px; vertical-align: top; }
  table.grid th { background: #f3f3f3; font-size: 10px; text-align: center; }
  .spec td.label { width: 28%; font-weight: 700; background: #fafafa; }
  .spec td.val { width: 32%; }
  .spec td.wlab { width: 12%; font-weight: 700; background: #fafafa; text-align: center; }
  .spec td.wval { width: 28%; }
  table.items th { font-size: 9.5px; }
  table.items td { height: 22px; }
  .c { text-align: center; }
  .r { text-align: right; }
  .empty-row td { height: 24px; }
  .foot-amt { font-weight: 800; }
  .footer { display: table; width: 100%; }
  .pay { display: table-cell; width: 50%; padding: 14px 12px; vertical-align: bottom; border-right: 1.5px solid #111; }
  .sign { display: table-cell; width: 50%; padding: 14px 12px; vertical-align: bottom; text-align: right; height: 90px; }
  .chk { display: inline-block; width: 12px; height: 12px; border: 1.5px solid #111; margin-right: 6px; vertical-align: -2px; text-align: center; line-height: 10px; font-size: 10px; }
  .chk.on::before { content: "✓"; }
  .no-print { margin: 0 auto 12px; max-width: 800px; }
  @media print { .no-print { display: none !important; } body { padding: 0; } }
</style></head><body>
<div class="no-print">
  <button onclick="window.print()" style="background:#c62828;color:#fff;border:0;padding:10px 16px;border-radius:6px;cursor:pointer;font-weight:700;">Print / Save PDF</button>
</div>
<div class="sheet">
  <div class="hdr">
    <div class="hdr-logo">
      <div class="logo-mark">SK</div>
      <div class="logo-sub">MOBILITY</div>
    </div>
    <div class="hdr-mid">
      <h1 class="co-name">' . htmlspecialchars($bill['company_name'] ?: 'SAI KUBER MOBILITY') . '</h1>
      <p class="co-line">' . htmlspecialchars($bill['company_address'] ?? '') . '</p>
      <p class="co-line">' . htmlspecialchars($branch) . '</p>
      <p class="co-line"><strong>Mob. :</strong> ' . htmlspecialchars($bill['company_phone'] ?? '') . '</p>
    </div>
  </div>
  <div class="gst-bar">
    <span>GST Reg. No. : ' . htmlspecialchars($bill['company_gstin'] ?? '') . '</span>
    <span>State: ' . htmlspecialchars((string)$companyState) . '</span>
    <span>State code : ' . htmlspecialchars($bill['company_state_code'] ?? '27') . '</span>
  </div>

  <div class="top-grid">
    <div class="cust">
      <div class="field"><span class="lab">Cust. Name :</span> ' . $v('customer_name') . '</div>
      <div class="field"><span class="lab">Add. :</span> ' . nl2br($v('customer_address')) . '</div>
      <div class="field"><span class="lab">Mob. :</span> ' . htmlspecialchars(format_phone($bill['customer_phone'] ?? '')) . ' &nbsp;&nbsp; <span class="lab">Email :</span> ' . $v('customer_email') . '</div>
      <div class="field"><span class="lab">Aadhar No. :</span> ' . htmlspecialchars(format_aadhar($bill['customer_aadhaar'] ?? '')) . '</div>
      <div class="field"><span class="lab">PAN No. :</span> ' . $v('customer_pan') . '</div>
    </div>
    <div class="invbox">
      <div class="tax-title">TAX INVOICE</div>
      <div class="inv-row"><span class="lab">No :</span> <span class="inv-no">' . htmlspecialchars($invoiceNo) . '</span></div>
      <div class="inv-row"><span class="lab">Date :</span> ' . india_date($saleDate) . '</div>
      <div class="inv-row"><span class="lab">Booking No. :</span> ' . htmlspecialchars($bookingNo) . '</div>
    </div>
  </div>

  <table class="grid spec">
    <tr>
      <td class="label">EV Model Name</td><td class="val">' . $v('vehicle_model') . '</td>
      <td class="label">EV Model Type</td><td class="val" colspan="2">' . $v('vehicle_model_type') . '</td>
    </tr>
    <tr>
      <td class="label">Model Color</td><td class="val">' . $v('color') . '</td>
      <td class="label">Date of Sale</td><td class="val" colspan="2">' . india_date($saleDate) . '</td>
    </tr>
    <tr>
      <td class="label">Chassis No.</td><td class="val" colspan="4">' . $v('chassis_no') . '</td>
    </tr>
    <tr>
      <td class="label">Motor No.</td><td class="val">' . $v('motor_no') . '</td>
      <td class="wlab">Warrenty</td><td class="wval" colspan="2">' . $blank($bill['motor_warranty'] ?? null) . '</td>
    </tr>
    <tr>
      <td class="label">Battery Type &amp; No.</td><td class="val">' . htmlspecialchars($battery) . '</td>
      <td class="wlab">Warrenty</td><td class="wval" colspan="2">' . $blank($bill['battery_warranty'] ?? null) . '</td>
    </tr>
    <tr>
      <td class="label">Controller No.</td><td class="val">' . $v('controller_no') . '</td>
      <td class="wlab">Warrenty</td><td class="wval" colspan="2">' . $blank($bill['controller_warranty'] ?? null) . '</td>
    </tr>
    <tr>
      <td class="label">Charger No.</td><td class="val">' . $v('charger_no') . '</td>
      <td class="wlab">Warrenty</td><td class="wval" colspan="2">' . $blank($bill['charger_warranty'] ?? null) . '</td>
    </tr>
    <tr>
      <td class="label">H.P. Name</td><td class="val" colspan="4">' . $v('hp_name') . '</td>
    </tr>
  </table>

  <table class="grid items">
    <thead>
      <tr>
        <th style="width:5%">S.No.</th>
        <th style="width:22%">Model Name and Code</th>
        <th style="width:11%">Unit Price</th>
        <th style="width:6%">Qty.</th>
        <th style="width:9%">Disc.</th>
        <th style="width:12%">Taxable Amount</th>
        <th style="width:10%">CGST</th>
        <th style="width:10%">SGST</th>
        <th style="width:15%">Total Amount</th>
      </tr>
    </thead>
    <tbody>' . $rows . '
      <tr>
        <td colspan="5" class="r foot-amt">Loan Amount</td>
        <td colspan="4" class="r foot-amt">' . self::num($loan) . '</td>
      </tr>
      <tr>
        <td colspan="5" class="r foot-amt">Total Amount</td>
        <td colspan="4" class="r foot-amt">' . self::num($total) . '</td>
      </tr>
      <tr>
        <td colspan="9"><strong>Total Amount in Words :</strong> ' . htmlspecialchars(amount_in_words($total)) . '</td>
      </tr>
    </tbody>
  </table>

  <div class="footer">
    <div class="pay">
      <div style="margin-bottom:8px;"><span class="chk ' . ($paidCash ? 'on' : '') . '"></span> Paid in Cash</div>
      <div><span class="chk ' . ($paidCheque ? 'on' : '') . '"></span> Paid in Cheque</div>
    </div>
    <div class="sign">
      <div style="margin-top:48px;font-weight:700;">Authorised Signatory</div>
    </div>
  </div>
</div>
<p style="max-width:800px;margin:10px auto 0;font-size:9px;color:#666;text-align:center;">
  CGST ' . $cgstRate . '% + SGST ' . $sgstRate . '% · HSN 87116020 · Computer generated tax invoice
</p>
</body></html>';
    }

    /** @return array{0:float,1:float,2:float,3:float,4:float} taxable, cgst, sgst, total, disc */
    private static function lineAmounts(array $item, array $bill, bool $firstLine): array
    {
        $unit = (float)($item['unit_price'] ?? 0);
        $qty = max(1, (int)($item['quantity'] ?? 1));
        $disc = (float)($item['discount'] ?? 0);
        if ($disc <= 0 && $firstLine) {
            $disc = (float)($bill['discount_amount'] ?? 0)
                + (float)($bill['pm_drive_incentive'] ?? 0)
                + (float)($bill['state_subsidy'] ?? 0);
        }

        if (!empty($item['taxable_amount']) || !empty($item['cgst_amount'])) {
            $taxable = (float)($item['taxable_amount'] ?? max(0, $unit * $qty - $disc));
            $cgst = (float)($item['cgst_amount'] ?? 0);
            $sgst = (float)($item['sgst_amount'] ?? 0);
            $total = (float)($item['total_price'] ?? ($taxable + $cgst + $sgst));
            return [$taxable, $cgst, $sgst, $total, $disc];
        }

        $cgstRate = (float)($bill['cgst_rate'] ?? 14);
        $sgstRate = (float)($bill['sgst_rate'] ?? 14);
        $taxable = max(0, $unit * $qty - $disc);
        $cgst = round($taxable * ($cgstRate / 100), 2);
        $sgst = round($taxable * ($sgstRate / 100), 2);
        $total = round($taxable + $cgst + $sgst, 2);
        return [$taxable, $cgst, $sgst, $total, $disc];
    }

    private static function displayInvoiceNo(string $billNumber): string
    {
        if (preg_match('/(\d+)$/', $billNumber, $m)) {
            return ltrim($m[1], '0') ?: $m[1];
        }
        return $billNumber;
    }

    private static function num(float|string|null $n): string
    {
        if ($n === null || $n === '' || (float)$n == 0.0) {
            return '';
        }
        return number_format((float)$n, 2, '.', ',');
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

        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: inline; filename="' . $bill['bill_number'] . '.html"');
        echo $html;
        exit;
    }
}
