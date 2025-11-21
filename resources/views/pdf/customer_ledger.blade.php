<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Customer Ledger</title>
  <style>
    /* Page + Base */
    @page { margin: 22px; }
    body { margin: 0; font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
    .wrap { page-break-inside: avoid; }

    /* Header */
    .header {
      padding: 12px 14px;
      border: 1px solid #e5e7eb;
      border-radius: 8px;
      background: #f8fafc;
      margin-bottom: 14px;
    }
    .h-top { display: table; width: 100%; }
    .h-left, .h-right { display: table-cell; vertical-align: top; }
    .h-right { text-align: right; }
    .title { font-size: 20px; font-weight: 700; color: #111827; }
    .muted { color: #6b7280; }

    .client-box {
      margin-top: 8px; padding: 10px 12px;
      border: 1px dashed #d1d5db; border-radius: 8px; background: #ffffff;
    }
    .client-col { display: table; width: 100%; }
    .client-left, .client-right { display: table-cell; vertical-align: top; }
    .client-right { text-align: right; }

    /* KPI / Quick Stats */
    .stats {
      margin: 10px 0 14px;
      border: 1px solid #e5e7eb;
      border-radius: 10px;
      padding: 10px;
      background: #ffffff;
    }
    .kpi-grid { display: table; width: 100%; border-spacing: 8px 6px; }
    .kpi {
      display: table-cell; width: 33%;
      border: 1px solid #eef2f7; border-radius: 10px;
      background: #f9fafb; padding: 10px;
    }
    .kpi .label { font-size: 11px; color: #6b7280; }
    .kpi .value { font-size: 16px; font-weight: 800; color: #111827; }
    .danger { color: #dc2626; }
    .warning { color: #d97706; }
    .success { color: #16a34a; }

    /* Section heading */
    h3 {
      margin: 18px 0 8px; font-size: 14px; color: #0f172a;
      border-left: 4px solid #3b82f6; padding-left: 8px;
    }

    /* Tables */
    table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    th, td { border: 1px solid #e5e7eb; padding: 6px 6px; vertical-align: middle; word-wrap: break-word; }
    thead th { font-size: 12px; text-align: left; background: #f3f4f6; color: #111827; }
    tbody tr:nth-child(odd) { background: #fafafa; }
    .right { text-align: right; }
    tfoot td { font-weight: 700; background: #f8fafc; }

    /* Badges (statuses) */
    .pill {
      display: inline-block; padding: 2px 8px; border-radius: 999px;
      font-size: 10px; font-weight: 700; border: 1px solid #e5e7eb; color: #374151; background: #f9fafb;
    }
    .pill.success { border-color: #bae6fd; color: #065f46; background: #ecfdf5; }
    .pill.warn    { border-color: #fde68a; color: #92400e; background: #fffbeb; }
    .pill.info    { border-color: #bfdbfe; color: #1e3a8a; background: #eff6ff; }
    .pill.danger  { border-color: #fecaca; color: #7f1d1d; background: #fef2f2; }

    /* Break control */
    .avoid-break { page-break-inside: avoid; }
  </style>
</head>
<body>

  @php
    // ---------- Helper: status badges (order/workflow) ----------
    function pillSale($status) {
      $s = strtolower(trim((string)$status));
      if (preg_match('/\b(completed|approved|fulfilled|closed)\b/', $s)) return 'pill success';
      if (preg_match('/\b(pending|awaiting|on hold)\b/', $s))        return 'pill warn';
      if (preg_match('/\b(sent|shipped|in transit)\b/', $s))         return 'pill info';
      if (preg_match('/\b(canceled|cancelled|rejected|void)\b/', $s))return 'pill danger';
      return 'pill';
    }

    // ---------- Helper: payment badges (avoid "unpaid" -> "paid") ----------
    function pillPayment($status) {
      $s = strtolower(trim((string)$status));
      if (preg_match('/\b(unpaid|not\s*paid|overdue|failed|due)\b/', $s)) return 'pill danger';
      if (preg_match('/\b(partial|partially\s*paid|part-?paid)\b/', $s))  return 'pill warn';
      if (preg_match('/\b(paid|settled|paid\s*in\s*full)\b/', $s))        return 'pill success';
      return 'pill';
    }

    // ---------- Section totals ----------
    $salesGrandSum = $sales->sum('GrandTotal');
    $salesPaidSum  = $sales->sum('paid_amount');
    $salesDueSum   = $sales->sum(function($s){ return (float)($s->GrandTotal ?? 0) - (float)($s->paid_amount ?? 0); });

    $paymentsSum   = $payments->sum('montant');

    $quotesGrand   = $quotations->sum('GrandTotal');

    $retGrandSum   = $returns->sum('GrandTotal');
    $retPaidSum    = $returns->sum('paid_amount');
    $retDueSum     = $returns->sum(function($r){ return (float)($r->GrandTotal ?? 0) - (float)($r->paid_amount ?? 0); });

    // ---------- Safe fallbacks for quick stats ----------
    $qsSalesGrand   = (float)($client->salesGrand ?? $salesGrandSum);
    $qsSalesPaid    = (float)($client->salesPaid  ?? $salesPaidSum);
    $qsSaleDue      = (float)($client->sale_due   ?? ($qsSalesGrand - $qsSalesPaid));

    $qsReturnsDue   = (float)($client->return_due ?? $retDueSum);
    $qsPaymentsTot  = (float)($client->paymentsTotal ?? $paymentsSum);
    $qsQuotesGrand  = (float)($client->quotationsTotal ?? $quotesGrand);

    $qsNetBalance   = isset($client->netBalance)
                      ? (float)$client->netBalance
                      : ($qsSaleDue - $qsReturnsDue);
  @endphp

  <!-- HEADER -->
  <div class="header wrap">
    <div class="h-top">
      <div class="h-left">
        <div class="title">Customer Ledger</div>
        <div class="muted">Generated at: {{ now()->format('Y-m-d H:i') }}</div>
      </div>
      <div class="h-right">
        {{-- (Optional) Logo / Company Name --}}
      </div>
    </div>

    <div class="client-box">
      <div class="client-col">
        <div class="client-left">
          <strong>{{ $client->name }}</strong><br>
          Code: {{ $client->code ?? '-' }}<br>
          City: {{ $client->city ?? '-' }}, Country: {{ $client->country ?? '-' }}
        </div>
        <div class="client-right">
          Email: {{ $client->email ?? '-' }}<br>
          Phone: {{ $client->phone ?? '-' }}<br>
          Tax #: {{ $client->tax_number ?? '-' }}
        </div>
      </div>
    </div>
  </div>

  <!-- QUICK STATS -->
  <div class="stats wrap">
    <div class="kpi-grid">
      <div class="kpi">
        <div class="label">Sales (Grand)</div>
        <div class="value">{{ number_format($qsSalesGrand, 2) }}</div>
      </div>
      <div class="kpi">
        <div class="label">Sales (Paid)</div>
        <div class="value">{{ number_format($qsSalesPaid, 2) }}</div>
      </div>
      <div class="kpi">
        <div class="label">Sales (Due)</div>
        <div class="value danger">{{ number_format($qsSaleDue, 2) }}</div>
      </div>

      <div class="kpi">
        <div class="label">Returns (Due)</div>
        <div class="value warning">{{ number_format($qsReturnsDue, 2) }}</div>
      </div>
      <div class="kpi">
        <div class="label">Payments (Total)</div>
        <div class="value">{{ number_format($qsPaymentsTot, 2) }}</div>
      </div>
      <div class="kpi">
        <div class="label">Quotations (Grand)</div>
        <div class="value">{{ number_format($qsQuotesGrand, 2) }}</div>
      </div>

      <div class="kpi">
        <div class="label">Net Balance</div>
        <div class="value {{ $qsNetBalance >= 0 ? 'danger' : 'success' }}">
          {{ number_format($qsNetBalance, 2) }}
        </div>
      </div>
    </div>
  </div>

  <!-- SALES -->
  <h3>Sales</h3>
  <table class="avoid-break">
    <thead>
      <tr>
        <th style="width:12%;">Date</th>
        <th style="width:14%;">Ref</th>
        <th style="width:18%;">Warehouse</th>
        <th style="width:12%;">Status</th>
        <th class="right" style="width:14%;">Grand Total</th>
        <th class="right" style="width:14%;">Paid</th>
        <th class="right" style="width:14%;">Due</th>
        <th style="width:12%;">Payment Status</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($sales as $s)
      <tr>
        <td>{{ $s->date }}</td>
        <td>{{ $s->Ref }}</td>
        <td>{{ optional($s->warehouse)->name }}</td>
        <td><span class="{{ pillSale($s->statut) }}">{{ $s->statut }}</span></td>
        <td class="right">{{ number_format($s->GrandTotal, 2) }}</td>
        <td class="right">{{ number_format($s->paid_amount, 2) }}</td>
        <td class="right">{{ number_format(($s->GrandTotal - $s->paid_amount), 2) }}</td>
        <td><span class="{{ pillPayment($s->payment_statut) }}">{{ $s->payment_statut }}</span></td>
      </tr>
      @empty
      <tr><td colspan="8">No sales found.</td></tr>
      @endforelse
    </tbody>
    <tfoot>
      <tr>
        <td colspan="4" class="right">Totals</td>
        <td class="right">{{ number_format($salesGrandSum, 2) }}</td>
        <td class="right">{{ number_format($salesPaidSum, 2) }}</td>
        <td class="right">{{ number_format($salesDueSum, 2) }}</td>
        <td></td>
      </tr>
    </tfoot>
  </table>

  <!-- PAYMENTS -->
  <h3>Payments</h3>
  <table class="avoid-break">
    <thead>
      <tr>
        <th style="width:16%;">Date</th>
        <th style="width:18%;">Payment Ref</th>
        <th style="width:18%;">Sale Ref</th>
        <th style="width:20%;">Method</th>
        <th class="right" style="width:18%;">Amount</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($payments as $p)
      <tr>
        <td>{{ $p->date }}</td>
        <td>{{ $p->Ref }}</td>
        <td>{{ $p->Sale_Ref }}</td>
        <td>{{ $p->payment_method }}</td>
        <td class="right">{{ number_format($p->montant, 2) }}</td>
      </tr>
      @empty
      <tr><td colspan="5">No payments found.</td></tr>
      @endforelse
    </tbody>
    <tfoot>
      <tr>
        <td colspan="4" class="right">Total Payments</td>
        <td class="right">{{ number_format($paymentsSum, 2) }}</td>
      </tr>
    </tfoot>
  </table>

  <!-- QUOTATIONS -->
  <h3>Quotations</h3>
  <table class="avoid-break">
    <thead>
      <tr>
        <th style="width:16%;">Date</th>
        <th style="width:20%;">Ref</th>
        <th style="width:18%;">Status</th>
        <th style="width:28%;">Warehouse</th>
        <th class="right" style="width:18%;">Grand Total</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($quotations as $q)
      <tr>
        <td>{{ $q->date }}</td>
        <td>{{ $q->Ref }}</td>
        <td><span class="{{ pillSale($q->statut) }}">{{ $q->statut }}</span></td>
        <td>{{ optional($q->warehouse)->name }}</td>
        <td class="right">{{ number_format($q->GrandTotal, 2) }}</td>
      </tr>
      @empty
      <tr><td colspan="5">No quotations found.</td></tr>
      @endforelse
    </tbody>
    <tfoot>
      <tr>
        <td colspan="4" class="right">Total Quotations</td>
        <td class="right">{{ number_format($quotesGrand, 2) }}</td>
      </tr>
    </tfoot>
  </table>

  <!-- RETURNS -->
  <h3>Returns</h3>
  <table class="avoid-break">
    <thead>
      <tr>
        <th style="width:16%;">Ref</th>
        <th style="width:14%;">Status</th>
        <th style="width:18%;">Sale Ref</th>
        <th style="width:24%;">Warehouse</th>
        <th class="right" style="width:12%;">Grand Total</th>
        <th class="right" style="width:8%;">Paid</th>
        <th class="right" style="width:8%;">Due</th>
        <th style="width:12%;">Payment Status</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($returns as $r)
      <tr>
        <td>{{ $r->Ref }}</td>
        <td><span class="{{ pillSale($r->statut) }}">{{ $r->statut }}</span></td>
        <td>{{ optional($r->sale)->Ref ?? '---' }}</td>
        <td>{{ optional($r->warehouse)->name }}</td>
        <td class="right">{{ number_format($r->GrandTotal, 2) }}</td>
        <td class="right">{{ number_format($r->paid_amount, 2) }}</td>
        <td class="right">{{ number_format(($r->GrandTotal - $r->paid_amount), 2) }}</td>
        <td><span class="{{ pillPayment($r->payment_statut) }}">{{ $r->payment_statut }}</span></td>
      </tr>
      @empty
      <tr><td colspan="8">No returns found.</td></tr>
      @endforelse
    </tbody>
    <tfoot>
      <tr>
        <td colspan="4" class="right">Totals</td>
        <td class="right">{{ number_format($retGrandSum, 2) }}</td>
        <td class="right">{{ number_format($retPaidSum, 2) }}</td>
        <td class="right">{{ number_format($retDueSum, 2) }}</td>
        <td></td>
      </tr>
    </tfoot>
  </table>

</body>
</html>
