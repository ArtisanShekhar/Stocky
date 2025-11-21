<!DOCTYPE html>
<html lang="en">
   <head>
      <meta charset="utf-8">
      <title>Transfer _{{$transfer['Ref']}}</title>
      <link rel="stylesheet" href="{{asset('/css/pdf_style.css')}}" media="all" />
   </head>

   <body>
      <header class="clearfix">
         <div id="logo">
         <img src="{{asset('/images/'.$setting['logo'])}}">
         </div>
         <div id="company">
            <div><strong> Date : </strong>{{$transfer['date']}}</div>
            <div><strong> Number : </strong> {{$transfer['Ref']}}</div>
            <div><strong> Status : </strong> {{$transfer['statut']}}</div>
            <div><strong> From Warehouse : </strong> {{$transfer['from_warehouse']}}</div>
            <div><strong> To Warehouse : </strong> {{$transfer['to_warehouse']}}</div>

         </div>
         <div id="Title-heading">
            Transfer  : {{$transfer['Ref']}}
         </div>
         </div>
      </header>
      <main>

         <div id="details_inv">
            <table  class="table-sm">
               <thead>
                  <tr>
                     <th>PRODUCT</th>
                     <th>QUANTITY</th>
                  </tr>
               </thead>
               <tbody>
                  @foreach ($details as $detail)
                  <tr>
                     <td>
                        <span>{{$detail['code']}} ({{$detail['name']}})</span>
                     </td>
                     <td>{{$detail['quantity']}}/{{$detail['unit_purchase']}}</td>
                  </tr>
                  @endforeach
               </tbody>
            </table>
         </div>
        
         <div id="signature">
            @if($setting['is_invoice_footer'] && $setting['invoice_footer'] !==null)
               <p>{{$setting['invoice_footer']}}</p>
            @endif
         </div>
      </main>
   </body>
</html>
