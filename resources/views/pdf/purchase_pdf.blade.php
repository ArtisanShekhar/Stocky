<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="utf-8">
   <title>Purchase _{{$purchase['Ref']}}</title>
   <!-- <link rel="stylesheet" href="{{asset('/css/pdf_style.css')}}" media="all" /> -->
    <style>
@page { margin: 0; }
* { box-sizing: border-box; }

body {
   margin: 0;
   padding: 15px 25px;
   font-size: 13px;
   font-family: "DejaVu Sans", sans-serif;
   color: #000;
   background: #fff;
}

.clearfix::after {
   content: "";
   display: table;
   clear: both;
}

/* ===========================
      HEADER
=========================== */
#logo {
   float: left;
   width: 220px;
}
#logo img {
   height: 70px;
   width: auto;
}

#company {
   float: right;
   text-align: right;
   font-size: 13px;
}
.invoice-title {
   margin: 0;
   padding: 0;
   color: #4B1CC0;
   font-weight: 800;
   font-size: 26px;
   margin-bottom: 5px;
}

/* ===========================
      SECTIONS WITH BORDER TABLE
=========================== */
.box-section {
   margin-top: 12px;
   margin-bottom: 10px;
}
.two-col-table {
   width: 100%;
   border-collapse: collapse;
   border: 1px solid #c1c1c1;
   font-size: 13px;
}
.two-col-table td {
   width: 50%;
   padding: 8px 10px;
   border-right: 1px solid #c1c1c1;
   vertical-align: top;
}
.two-col-table tr td:last-child {
   border-right: none;
}

/* Section Titles */
.sec-title {
   background: #4B1CC0;
   color: #fff;
   padding: 4px 6px;
   margin: -8px -10px 6px -10px;
   font-size: 13px;
   font-weight: 700;
}

/* ===========================
      PRODUCT TABLE
=========================== */
#details_inv table {
   width: 100%;
   border-collapse: collapse;
   font-size: 13px;
   margin-top: 15px;
}
#details_inv th {
   background: #4B1CC0;
   color: #fff;
   padding: 6px;
   border: 1px solid #c1c1c1;
   font-weight: 700;
}
#details_inv td {
   border: 1px solid #c1c1c1;
   padding: 6px;
   background: #fdfdfd;
}
#details_inv .imei {
   font-size: 11px;
   color: #555;
   margin-top: 3px;
}

/* ===========================
       TOTAL BOX
=========================== */
#total {
   width: 260px;
   float: right;
   margin-top: 18px;
}
#total table {
   width: 100%;
   border-collapse: collapse;
}
#total td {
   border: 1px solid #c1c1c1;
   padding: 7px 10px;
   font-size: 13px;
   background: #f8f8f8;
}
#total tr.grand td {
   background: #d7cffb;
   font-weight: 900;
}
#total tr.due td {
   background: #ffdddd;
   font-weight: 900;
}

/* ===========================
      FOOTER
=========================== */
#signature {
   text-align: center;
   margin-top: 25px;
   font-size: 12px;
   color: #333;
}
</style>

</head>

<body>

<header class="clearfix">
   <div id="logo">
      <img src="{{asset('/images/'.$setting['logo'])}}">
   </div>

   <div id="company">
      <h1 class="invoice-title">Purchase Invoice</h1>
      <p><strong>Date:</strong> {{$purchase['date']}}</p>
      <p><strong>Invoice No:</strong> {{$purchase['Ref']}}</p>
      <p><strong>Status:</strong> {{$purchase['statut']}}</p>
      <p><strong>Payment Status:</strong> {{$purchase['payment_status']}}</p>
   </div>
</header>

<main>

<section class="box-section">
   <table class="two-col-table">
      <tbody>
         <tr>
            <td>
               <h4 class="sec-title">Invoice Details</h4>
               <p><strong>Invoice No:</strong> {{$purchase['Ref']}}</p>
               <p><strong>Dated:</strong> {{$purchase['date']}}</p>
               <p><strong>Place of Supply:</strong> {{$purchase['place_of_supply']}}</p>
               <p><strong>Reverse Charge:</strong> {{ $purchase['reverse_charge'] ? 'Y' : 'N' }}</p>
               <p><strong>GR/RR No:</strong> {{$purchase['gr_rr_no']}}</p>
               <p><strong>Transport:</strong> {{$purchase['transport']}}</p>
            </td>

            <td>
               <h4 class="sec-title">Transport / Order</h4>
               <p><strong>Vehicle No:</strong> {{$purchase['vehicle_no'] ?? ''}}</p>
               <p><strong>Station:</strong> {{$purchase['station']}}</p>
               <p><strong>E-Way Bill No:</strong> {{$purchase['e_way_bill_no']}}</p>
               <p><strong>Order No:</strong> {{$purchase['order_no']}}</p>
               <p><strong>Order Date:</strong> {{$purchase['order_date']}}</p>
            </td>
         </tr>
      </tbody>
   </table>
</section>

<section class="box-section">
   <table class="two-col-table">
      <tbody>
         <tr>
            <td>
               <h4 class="sec-title">Billed To</h4>
               {{$purchase['billing_name'] ?? $purchase['supplier_name']}}<br>
               {{$purchase['billing_address']}}<br>
               <strong>GSTIN / UIN:</strong> {{$purchase['billing_gstin']}}<br>
               <strong>State:</strong> {{$purchase['billing_state_name']}}<br>
               <strong>State Code:</strong> {{$purchase['billing_state_code']}}
            </td>

            <td>
               <h4 class="sec-title">Shipped To</h4>
               {{$purchase['shipping_name'] ?? $purchase['supplier_name']}}<br>
               {{$purchase['shipping_address']}}<br>
               <strong>GSTIN / UIN:</strong> {{$purchase['shipping_gstin']}}<br>
               <strong>State:</strong> {{$purchase['shipping_state_name']}}<br>
               <strong>State Code:</strong> {{$purchase['shipping_state_code']}}
            </td>
         </tr>
      </tbody>
   </table>
</section>

<section id="details_inv">
   <table>
      <thead>
         <tr>
            <th>PRODUCT</th>
            <th>HSN/SAC CODE</th>
            <th>UNIT COST</th>
            <th>QTY</th>
            <th>DISCOUNT</th>
            <th>TAX</th>
            <th>TOTAL</th>
         </tr>
      </thead>
      <tbody>
         @foreach ($details as $detail)
         <tr>
            <td>
               {{$detail['code']}} ({{$detail['name']}})
               @if($detail['is_imei'] && $detail['imei_number'] !==null)
               <div class="imei">IMEI/SN: {{$detail['imei_number']}}</div>
               @endif
            </td>
            <td>{{$detail['hsn_number']}}</td>
            <td>{{$detail['cost']}} </td>
            <td>{{$detail['quantity']}}/{{$detail['unit_purchase']}}</td>
            <td>{{$detail['DiscountNet']}}</td>
            <td>{{$detail['taxe']}}</td>
            <td>{{$detail['total']}}</td>
         </tr>
         @endforeach
      </tbody>
   </table>
</section>

<section id="total">
   <table>
      <tr><td>Order Tax</td><td>{{$purchase['TaxNet']}}</td></tr>
      <tr><td>Discount</td><td>{{$purchase['discount']}}</td></tr>
      <tr><td>Shipping</td><td>{{$purchase['shipping']}}</td></tr>
      <tr class="grand"><td><strong>Total</strong></td><td><strong>{{$symbol}} {{$purchase['GrandTotal']}}</strong></td></tr>
      <tr><td>Paid Amount</td><td>{{$symbol}} {{$purchase['paid_amount']}}</td></tr>
      <tr class="due"><td><strong>Due</strong></td><td><strong>{{$symbol}} {{$purchase['due']}}</strong></td></tr>
   </table>
</section>

<div id="signature">
   @if($setting['is_invoice_footer'] && $setting['invoice_footer'] !==null)
   <p>{{$setting['invoice_footer']}}</p>
   @endif
</div>

</main>
</body>
</html>
