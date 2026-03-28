@php
    $settings = [
        'company_logo' => env('INVOICE_COMPANY_LOGO'),
        'company_name' => env('INVOICE_BUSINESS_NAME', 'Sri Vinayaka Murugan Sweets & Bakery'),
        'gst_number' => env('INVOICE_GSTIN', '34FKWPS4355H1ZE'),
        'fssai_number' => env('INVOICE_FSSAI', '13521001000391'),
        'billing_address' => env('INVOICE_ADDRESS', 'No.158/3, Villanur Main Road, Friends Nagar, Reddiarpalaiyam, Puducherry - 605111'),
        'company_phone_number' => env('INVOICE_PHONE', '97511 82277'),
        'customer_care' => env('INVOICE_CUSTOMER_CARE', '96557 52277'),
        'website' => env('INVOICE_WEBSITE', 'www.svmsweets.com'),
        'branch_qrcode' => env('INVOICE_BRANCH_QR'),
        'invoice_footer' => env('INVOICE_FOOTER', 'Thank You Visit Again'),
    ];

    $grand = (float) $payment->amount;
    $taxable = round($grand / 1.05, 2);
    $tax5 = round($grand - $taxable, 2);

    $order = new \stdClass;
    $order->customer = (object) ['name' => $payment->name, 'phone' => $payment->phone];
    $order->created_at = $payment->created_at;
    $order->order_type = $payment->status === 'COMPLETED' ? 'invoice' : 'quotation';
    $order->order_number = 'QUO-S3-'.$payment->created_at->format('Y').'-'.str_pad((string) $payment->id, 4, '0', STR_PAD_LEFT);
    $order->order_date = $payment->created_at->format('Y-m-d');
    $order->delivery_date = $payment->created_at->copy()->addDays(4)->format('Y-m-d');
    $order->items = collect([
        (object) [
            'product' => (object) ['name' => 'Online payment — '.$payment->merchant_order_id.($payment->transaction_id ? ' (Txn: '.$payment->transaction_id.')' : '')],
            'quantity' => 1,
            'unit_price' => $grand,
            'total_price' => $grand,
        ],
    ]);
    $order->payments = $payment->status === 'COMPLETED'
        ? collect([(object) ['amount' => $grand]])
        : collect([]);
    $order->notes = null;
    $order->carton_box_count = null;
    $order->packing_charges = 0;
    $order->add_on_charges = 0;
    $order->subtotal = $taxable;
    $order->tax_amount = $tax5;
    $order->discount_amount = 0;
    $order->utensils_charge = 0;
    $order->quotation_tax_amount = $tax5;
    $order->quotation_tax_percentage = 5;
    $order->total_amount = $grand;
    $order->salesman = null;

    $paymentMethod = 'upi';

    $qrPath = null;
    if (! empty($settings['branch_qrcode'])) {
        $rawQr = (string) $settings['branch_qrcode'];
        $pathPart = str_starts_with($rawQr, 'http')
            ? (parse_url($rawQr, PHP_URL_PATH) ?? '')
            : $rawQr;
        $relativePath = str_replace('/storage/', '', $pathPart);
        $relativePath = ltrim($relativePath, '/');
        $fullPath = storage_path('app/public/'.$relativePath);
        if ($relativePath !== '' && file_exists($fullPath)) {
            $qrPath = $fullPath;
        }
    }
@endphp
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
  <title>SVM Invoice — {{ $payment->merchant_order_id }}</title>

  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      background: #f5f5f5;
      font-family: DejaVu Sans, Arial, sans-serif;
      margin: 0;
      padding: 0;
    }

    @page {
      size: A4 portrait;
      margin: 15mm;
    }

    .invoice-container {
      width: 100%;
      max-width: 750px;
      margin: 0 auto;
      padding: 15px;
      box-sizing: border-box;
      background: white;
    }

    .header-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 14px;
    }

    .logo-cell {
      width: 130px;
      vertical-align: top;
      padding-right: 15px;
    }

    .logo-cell img {
      width: 110px;
    }

    .logo-placeholder {
      width: 110px;
      height: 80px;
      background: #C2185B;
      border-radius: 50%;
      color: white;
      font-weight: bold;
      font-size: 13px;
      text-align: center;
      line-height: 80px;
    }

    .company-cell {
      vertical-align: top;
    }

    .company-name {
      font-size: 22px;
      font-weight: 900;
      color: #1a1a1a;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin: 3px 0 5px 0;
    }

    .address-line {
      font-size: 11px;
      color: #444;
      margin: 2px 0;
    }

    .contact-line {
      font-size: 11px;
      color: #444;
      margin-top: 3px;
    }

    .highlight-red {
      color: #cc0000;
      font-weight: 700;
    }

    .info-card-title {
      font-size: 10px;
      font-weight: 700;
      color: #b9935a;
      letter-spacing: 0.8px;
      text-transform: uppercase;
      margin-bottom: 10px;
    }

    .items-table {
      width: 100%;
      border-collapse: separate;
      border-spacing: 0;
      margin-bottom: 16px;
      border: 1px solid #e0d5cc;
      border-radius: 10px;
      table-layout: fixed;
    }

    .items-table thead th {
      background-color: #b9935a;
      color: #ffffff;
      font-size: 11px;
      font-weight: 700;
      padding: 12px 10px;
      text-align: left;
      letter-spacing: 0.3px;
      border: none;
    }

    .items-table thead th:first-child {
      border-top-left-radius: 9px;
    }
    .items-table thead th:last-child {
      border-top-right-radius: 9px;
    }

    .items-table .center {
      text-align: center;
    }

    .items-table .right {
      text-align: right;
    }

    .items-table tbody td {
      font-size: 11px;
      padding: 8px 10px;
      color: #222;
      vertical-align: top;
      border-bottom: 1px solid #f0e8e2;
    }

    .items-table tbody tr:last-child td {
      border-bottom: none;
    }

    .payment-title {
      font-size: 12px;
      font-weight: 700;
      color: #222;
      margin-bottom: 8px;
    }

    .terms-title {
      font-size: 11px;
      font-weight: 700;
      margin-bottom: 5px;
    }

    .terms-list {
      font-size: 10px;
      padding-left: 14px;
      line-height: 1.7;
      color: #444;
      margin-bottom: 12px;
    }

    .thankyou-section {
      background: #a91c2c;
      color: white;
      text-align: center;
      padding: 12px;
      border-radius: 25px;
      font-weight: 700;
      font-size: 13px;
      letter-spacing: 0.5px;
      margin-bottom: 12px;
    }

    .totals-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 11.5px;
      background: #f7f7f7;
      border-radius: 6px;
      overflow: hidden;
      margin-bottom: 12px;
    }

    .totals-table tr {
      height: 24px;
    }

    .totals-table td {
      padding: 3px 10px;
    }

    .totals-table tr:not(:last-child) td {
      border-bottom: 1px dashed #e5e5e5;
    }

    .totals-table .t-label {
      text-align: right;
      font-weight: 600;
      color: #444;
      width: 55%;
    }

    .totals-table .t-value {
      text-align: right;
      font-weight: 700;
      color: #111;
      width: 45%;
    }

    .grand-total-row .t-label, .grand-total-row .t-value {
      font-weight: 800 !important;
      font-size: 13px !important;
      color: #cc0000 !important;
      padding: 6px 10px;
    }

    .balance-row .t-label, .balance-row .t-value {
      background: #fef6f6;
      font-weight: 800 !important;
      color: #cc0000 !important;
      padding: 6px 10px;
    }

    .person-incharge-row td {
      color: #cc0000 !important;
      font-weight: 700 !important;
      padding: 6px 10px;
    }

    .advance-row td {
      padding: 6px 10px;
      font-weight: 700 !important;
    }
  </style>
</head>

<body>

  <div class="invoice-container">

    <table class="header-table">
      <tr>
        <td class="logo-cell">
          @php
            $logoPath = '';
            if (isset($settings['company_logo']) && ! empty($settings['company_logo'])) {
                $urlPath = parse_url($settings['company_logo'], PHP_URL_PATH);
                $relativePath = str_replace('/storage/', '', (string) $urlPath);
                $dbLogoPath = storage_path('app/public/'.$relativePath);
                if ($relativePath !== '' && file_exists($dbLogoPath)) {
                    $logoPath = $dbLogoPath;
                }
            }
            if (empty($logoPath)) {
                $repoLogo = base_path('resources/logo/SVM - Updated Logo.svg');
                if (file_exists($repoLogo)) {
                    $logoPath = $repoLogo;
                }
            }
          @endphp
          @if(! empty($logoPath) && file_exists($logoPath))
            <img src="{{ $logoPath }}" alt="Logo">
          @else
            <div class="logo-placeholder">LOGO</div>
          @endif
        </td>

        <td class="company-cell">
          <table style="border-collapse: collapse; margin-bottom: 6px; width: 100%;">
            <tr>
              <td colspan="2" style="padding: 0;">
                <h1 class="company-name" style="margin-bottom: 2px;">{{ strtoupper($settings['company_name'] ?? 'Sri Vinayaka Murugan Sweets & Bakery') }}</h1>
              </td>
            </tr>
            <tr>
              <td style="text-align: left; padding: 0; font-size: 11px; color: #555;">GSTIN : {{ $settings['gst_number'] ?? '34FKWPS4355H1ZE' }}</td>
              <td style="text-align: right; padding: 0; font-size: 11px; color: #555;">FSSAI | {{ $settings['fssai_number'] ?? '13521001000391' }}</td>
            </tr>
          </table>
          <div class="address-line">
            {{ $settings['billing_address'] ?? 'No.158/3, Villanur Main Road, Friends Nagar, Reddiarpalaiyam, Puducherry - 605111' }}
          </div>
          <div class="contact-line">
            Contact: <span class="highlight-red">{{ $settings['company_phone_number'] ?? '97511 82277' }}</span>@if(! empty($settings['customer_care'])), {{ $settings['customer_care'] }}@else, 96557 52277 @endif
            &nbsp;|&nbsp; Website: {{ $settings['website'] ?? 'www.svmsweets.com' }}
          </div>
          <div style="font-size: 10px; color: #666; margin-top: 6px;">
            PhonePe: {{ $payment->merchant_order_id }} — {{ $payment->status }}
          </div>
        </td>
      </tr>
    </table>
    <div style="border-top: 1.5px solid #ebd5b3; margin-top: 4px; margin-bottom: 20px;"></div>

    <table style="width:100%; border-collapse:separate; border-spacing:12px 0; margin:12px 0 14px 0;">
      <tr>

        <td style="width:50%; border:1px solid #e0d5cc; border-radius:8px; padding:10px 14px 12px 14px; background:#fdf8f5; vertical-align:top;">
          <div class="info-card-title">CUSTOMER DETAILS</div>
          <table style="width:100%; border-collapse:collapse;">
            <tr>
              <td style="font-size:11px; font-weight:700; color:#555; width:115px; padding:3px 0;">CUSTOMER NAME</td>
              <td style="font-size:11px; font-weight:700; color:#555; width:8px; padding:3px 0;">:</td>
              <td style="font-size:11px; font-weight:600; color:#222; padding:3px 0;">{{ $order->customer->name ?? 'N/A' }}</td>
            </tr>
            <tr>
              <td style="font-size:11px; font-weight:700; color:#555; padding:3px 0;">PHONE NO</td>
              <td style="font-size:11px; font-weight:700; color:#555; padding:3px 0;">:</td>
              <td style="font-size:11px; font-weight:600; color:#222; padding:3px 0;">{{ $order->customer->phone ?? 'N/A' }}</td>
            </tr>
            <tr>
              <td style="font-size:11px; font-weight:700; color:#555; padding:3px 0;">TIME</td>
              <td style="font-size:11px; font-weight:700; color:#555; padding:3px 0;">:</td>
              <td style="font-size:11px; font-weight:600; color:#222; padding:3px 0;">{{ $order->created_at ? $order->created_at->format('h:i A') : '' }}</td>
            </tr>
          </table>
        </td>

        <td style="width:50%; border:1px solid #e0d5cc; border-radius:8px; padding:10px 14px 12px 14px; background:#fdf8f5; vertical-align:top;">
          <div class="info-card-title">ORDER DETAILS</div>
          <table style="width:100%; border-collapse:collapse;">
            <tr>
              <td style="font-size:11px; font-weight:700; color:#555; width:115px; padding:3px 0;">{{ $order->order_type === 'quotation' ? 'QUOTATION NO' : 'INVOICE NO' }}</td>
              <td style="font-size:11px; font-weight:700; color:#555; width:8px; padding:3px 0;">:</td>
              <td style="font-size:13px; font-weight:800; color:#cc0000; padding:3px 0;">{{ $order->order_number ?? 'S1' }}</td>
            </tr>
            <tr>
              <td style="font-size:11px; font-weight:700; color:#555; padding:3px 0;">ORDER DATE</td>
              <td style="font-size:11px; font-weight:700; color:#555; padding:3px 0;">:</td>
              <td style="font-size:11px; font-weight:600; color:#222; padding:3px 0;">{{ $order->order_date ? \Carbon\Carbon::parse($order->order_date)->format('d/m/Y') : date('d/m/Y') }}</td>
            </tr>
            <tr>
              <td style="font-size:11px; font-weight:700; color:#555; padding:3px 0;">DELIVERY DATE</td>
              <td style="font-size:11px; font-weight:700; color:#555; padding:3px 0;">:</td>
              <td style="font-size:11px; font-weight:600; color:#222; padding:3px 0;">{{ $order->delivery_date ? \Carbon\Carbon::parse($order->delivery_date)->format('d/m/Y') : '' }}</td>
            </tr>
          </table>
        </td>

      </tr>
    </table>

    <div style="padding: 0 12px;">
    <table class="items-table">
      <colgroup>
        <col style="width: 55%;">
        <col style="width: 10%;">
        <col style="width: 17.5%;">
        <col style="width: 17.5%;">
      </colgroup>
      <thead>
        <tr>
          <th style="width:55%;">ITEMS</th>
          <th class="center" style="width:10%;">QTY</th>
          <th class="center" style="width:17.5%;">UNIT PRICE</th>
          <th class="center" style="width:17.5%;">TOTAL PRICE</th>
        </tr>
      </thead>
      <tbody>
        <tr style="height:0;">
          <td colspan="4" style="padding:0; border:0; height:0; position:relative;">
            @php
              $watermarkPath = base_path('resources/views/pdf/SVM_Tagline.png');
            @endphp
            @if(file_exists($watermarkPath))
              <img src="{{ $watermarkPath }}" alt=""
                style="
                  position:absolute;
                  top:160px;
                  left:50%;
                  width:55%;
                  height:auto;
                  opacity:0.08;
                ">
            @endif
          </td>
        </tr>

        @foreach($order->items as $item)
        <tr>
          <td style="padding: 7px 10px; line-height: 1.4; vertical-align: top;">{{ $item->product ? $item->product->name : 'N/A' }}</td>
          <td class="center" style="padding: 7px 10px; vertical-align: top;">{{ round($item->quantity ?? 0) }}</td>
          <td class="center" style="padding: 7px 10px; vertical-align: top;">{{ number_format($item->unit_price ?? 0, 2) }}</td>
          <td class="center" style="padding: 7px 10px; vertical-align: top;">{{ number_format($item->total_price ?? 0, 2) }}</td>
        </tr>
        @endforeach

        @php
            $itemCount = count($order->items);
            $fillerCount = max(0, 8 - $itemCount);
        @endphp
        @for($i = 0; $i < $fillerCount; $i++)
        <tr>
          <td style="height: 22px; border: none;">&nbsp;</td>
          <td style="border: none;">&nbsp;</td>
          <td style="border: none;">&nbsp;</td>
          <td style="border: none;">&nbsp;</td>
        </tr>
        @endfor
      </tbody>
    </table>

    <div style="page-break-inside: avoid; padding-top: 20px;">
    @php
      $receivedAmount  = $order->payments ? $order->payments->sum('amount') : 0;
      $addonCharges    = $order->add_on_charges ?? 0;
      $subtotalWithGst = ($order->subtotal ?? 0) + ($order->tax_amount ?? 0);
      $balanceAmount   = ($order->total_amount ?? 0) - $receivedAmount;
    @endphp

    <table style="width:100%; border-collapse:collapse; margin-top:4px;">
      <tr>

        <td style="width:48%; vertical-align:top; padding-right:16px;">

          @if(isset($order->notes) && !empty($order->notes))
            <table style="width:100%; border-collapse:collapse; margin-bottom:8px;">
              <tr>
                <td style="border:1px solid #bbb; padding:6px 10px;">
                  <span style="font-size:11px; font-weight:700;">NOTES:</span><br>
                  <span style="font-size:11px; color:#333; line-height:1.6;">{{ $order->notes }}</span>
                </td>
              </tr>
            </table>
          @endif

          @if($order->carton_box_count)
            <table style="width:100%; border-collapse:collapse; margin-bottom:8px;">
              <tr>
                <td style="font-size:11px; padding:2px 0;">
                  <span style="font-weight:700;">CARTON BOX COUNT:</span>
                  <span style="margin-left:5px;">{{ $order->carton_box_count }}</span>
                </td>
              </tr>
            </table>
          @endif

          <div class="payment-title">PAYMENT MODE</div>

          <table style="width:100%; border-collapse:collapse; margin-bottom:14px; table-layout:fixed;">
            <tr>
              @php
                $methods = ['cash' => 'CASH', 'card' => 'CARD', 'upi' => 'UPI'];
              @endphp
              @foreach($methods as $key => $label)
              <td style="vertical-align:middle; width:33.3%;">
                @php
                  $isSelected = (strtolower($paymentMethod ?? '') == $key);
                @endphp
                <table style="border-collapse:collapse; {{ $isSelected ? 'background:#fff5f5; border:1px solid #cc0000; border-radius:4px;' : '' }} padding:4px 6px; width:95%;">
                  <tr>
                    <td style="padding-right:8px; vertical-align:middle; width:22px; padding-left:4px;">
                      @if($isSelected)
                        <span style="display:inline-block; width:16px; height:16px; background:#cc0000; border:1.5px solid #cc0000; border-radius:3px; position:relative;">
                          <span style="position:absolute; left:4px; top:1px; width:5px; height:9px; border-right:2.5px solid white; border-bottom:2.5px solid white;"></span>
                        </span>
                      @else
                        <span style="display:inline-block; width:16px; height:16px; border:1.5px solid #999; border-radius:3px; background:white;"></span>
                      @endif
                    </td>
                    <td style="font-size:12px; font-weight:{{ $isSelected ? '800' : '600' }}; color:{{ $isSelected ? '#cc0000' : '#444' }}; vertical-align:middle;">
                      {{ $label }}
                    </td>
                  </tr>
                </table>
              </td>
              @endforeach
            </tr>
          </table>

          <table style="width:100%; border-collapse:collapse; margin-bottom:12px;">
            <tr>
              <td style="text-align:center;">
                <div style="font-size:11px; font-weight:700; margin-bottom:6px;">Scan to Pay</div>
                <div style="width:90px; height:90px; overflow:hidden; margin:0 auto;">
                  @if(! empty($qrDataUri))
                    <img src="{{ $qrDataUri }}" alt="QR Code" style="width:100%; height:100%;">
                  @elseif(! empty($qrPath) && file_exists($qrPath))
                    <img src="{{ $qrPath }}" alt="QR Code" style="width:100%; height:100%;">
                  @else
                    <div style="width:90px; height:90px; border:1px dashed #bbb; font-size:9px; color:#aaa; text-align:center; padding-top:36px;">QR CODE</div>
                  @endif
                </div>
              </td>
            </tr>
          </table>

          <div class="terms-title">Terms &amp; Conditions</div>
          <ul class="terms-list">
            <li>50% of the bill amount should be paid to confirm the order, balance 50% payment should be done before picking your order</li>
            <li>Food products once sold cannot be exchanged or returned</li>
            <li>We accept cancellation before 24 hrs of delivery time</li>
          </ul>

          <table style="width:100%; border-collapse:collapse; margin-bottom:12px;">
            <tr>
              <td class="thankyou-section" style="border-radius:25px;">
                {{ $settings['invoice_footer'] ?? 'Thank You Visit Again' }}
              </td>
            </tr>
          </table>

        </td>

        <td style="width:52%; vertical-align:top;">

          <table class="totals-table">
            <tr>
              <td class="t-label">TOTAL :</td>
              <td class="t-value">{{ number_format($subtotalWithGst, 2) }}</td>
            </tr>
            <tr>
              <td class="t-label">DELIVERY CHARGE :</td>
              <td class="t-value">{{ number_format($order->packing_charges ?? 0, 2) }}</td>
            </tr>
            @if($addonCharges > 0)
            <tr>
              <td class="t-label">ADD ON CHARGES :</td>
              <td class="t-value">{{ number_format($addonCharges, 2) }}</td>
            </tr>
            @endif
            <tr>
              <td class="t-label">DISCOUNT :</td>
              <td class="t-value">
                {{ ($order->discount_amount ?? 0) > 0 ? number_format($order->discount_amount, 2) : '-' }}
              </td>
            </tr>
            <tr>
              <td class="t-label">UTENSILS CHARGE :</td>
              <td class="t-value">{{ number_format($order->utensils_charge ?? 0, 2) }}</td>
            </tr>
            @if(($order->quotation_tax_amount ?? 0) > 0)
            <tr>
              <td class="t-label">TAX ({{ round($order->quotation_tax_percentage ?? 0, 1) }}%) :</td>
              <td class="t-value">{{ number_format($order->quotation_tax_amount, 2) }}</td>
            </tr>
            @endif

            <tr class="grand-total-row">
              <td class="t-label">GRAND TOTAL :</td>
              <td class="t-value">{{ number_format($order->total_amount ?? 0, 2) }}</td>
            </tr>

            <tr class="advance-row">
              <td class="t-label">ADVANCE :</td>
              <td class="t-value">{{ number_format($receivedAmount, 2) }}</td>
            </tr>

            <tr class="balance-row">
              <td class="t-label">BALANCE AMOUNT :</td>
              <td class="t-value">{{ number_format($balanceAmount, 2) }}</td>
            </tr>

            @if($order->salesman)
            <tr class="person-incharge-row">
              <td class="t-label">PERSON INCHARGE :</td>
              <td class="t-value" style="font-weight:700;">{{ $order->salesman->name }}</td>
            </tr>
            @endif
          </table>

          <table style="width:100%; border-collapse:collapse; margin-top:25px;">
            <tr>
              <td style="text-align:right;">
                <span style="display:inline-block; border:1px solid #999; padding:6px 12px; padding-top:25px; font-size:10px; font-weight:600; color:#333;">
                  CASHIER SIGNATURE &amp; DATE
                </span>
              </td>
            </tr>
          </table>

        </td>
      </tr>
    </table>
    </div>
    </div>

  </div>

</body>
</html>
