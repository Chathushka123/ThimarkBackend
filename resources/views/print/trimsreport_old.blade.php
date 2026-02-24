<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<head>
<!-- <link href='https://fonts.googleapis.com/css?family=Libre Barcode 39' rel='stylesheet'> -->
  <!-- <link href='../sass/IDAUTOMATIONHC39M.TTF' rel='stylesheet'> -->
  <style>
    p {
      font-family: 'Libre Barcode 39';
      margin:0;
      padding:0;
      font-size:30px;
    }
    </style>
</head>
<body style="font-family: sans-serif;">

<script type="text/php">
    if ( isset($pdf) ) {
        $font = "";
        $pdf->page_text(400, 560, "Page: {PAGE_NUM} of {PAGE_COUNT}", $font, 12, array(0,0,0));
    }
</script> 
  
  <table cellspacing="0" cellpadding="0" border="1" width="100%" style="border-color:#333; border-collapse: collapse;table-layout: fixed ;">
  <tr style = "font-size:12px;">
      <th style = "padding:5px; text-align:center; width: 60%; font-size:24px"> Job Card Report </th>
      <th style = "padding:5px; text-align:center; width: 10% "> Job Card No </th>
      <th style = "padding:5px; text-align:center; width: 10% "> {{$header[0]['job_card_no']}} </th>
      <th style = "padding:5px; text-align:center; width: 20%;font-weight:50 "> <p>*{{$header[0]['job_card_no']}}*</p> </th>
  </table>



  <table cellspacing="0" cellpadding="0" border="1" width="100%" style="border-color:#333; border-collapse: collapse;table-layout: fixed ;
  width: 100% ;">
    <tr style = "font-size:12px;">
      <th style = "padding:5px; text-align:left; width: 25% "> {{'SOC: '. $header[0]['soc_no']}} </th>
      <th style = "padding:5px; text-align:left; width: 25% "> {{'FPO: '. $header[0]['fpo_no']}} </th>
      <th style = "padding:5px; text-align:left; width: 25% "> {{'Cus Sty Ref: '. $header[0]['customer_style_ref']}} </th>
      <th style = "padding:5px; text-align:left; width: 25% "> {{'Team: '. $header[0]['team']}} </th>
    </tr>

    <tr style = "font-size:12px;">
      <th style = "padding:5px; text-align:left; width: 25% "> {{'Style: '. $header[0]['style']}} </th>
      <th style = "padding:5px; text-align:left; width: 25% "> {{'Color: '. $header[0]['color']}} </th>
      <th style = "padding:5px; text-align:left; width: 25% "> {{'Job Qty: '. $header[0]['total']}} </th>
      <th style = "padding:5px; text-align:left; width: 25% "> {{'Issued Date: '. $header[0]['issued_date']}} </th>
	 
    </tr >
    <tr style = "font-size:12px;">
      <th colspan="2" style = "padding:5px; text-align:left; width: 25% "> PO No :  </th>
      <th colspan="2" style = "padding:5px; text-align:left; width: 25% "> Country : </th>
    </tr>
  </table>
  <table cellspacing="0" cellpadding="0" border="1" width="100%" style="border-color:#333; border-collapse: collapse; margin-top:10px;">
    <tr style = "font-size:12px;">
      <th style = "padding:5px;"> Bundle ID </th> 
      <th style = "padding:5px;"> Size </th>      
      
      <th style = "padding:5px;"> Number Sequence</th>
      <th style = "padding:5px;"> Qty</th>
      <th style = "padding:5px;">Remark</th>
      <th style = "padding:5px;">Cut No</th>
      <th style = "padding:5px;"> 01</th>
      <th style = "padding:5px;"> 02</th>
      <th style = "padding:5px;"> 03</th>
      <th style = "padding:5px;"> 04</th>
      <th style = "padding:5px;"> 05</th>
      <th style = "padding:5px;"> 06</th>
      <th style = "padding:5px;"> 07</th>
      <th style = "padding:5px;"> 08</th>
      <th style = "padding:5px;"> 09</th>
      <th style = "padding:5px;"> 10</th>
      <th style = "padding:5px;"> 11</th>
      <th style = "padding:5px;"> 12</th>
      
      <th style = "padding:5px;"> Total</th>
      <th style = "padding:5px;">Reject</th>
      <th style = "padding:5px;"> Ctn No1</th>
      <th style = "padding:5px;">Qty</th>
      <th style = "padding:5px;"> Ctn No2</th>
      <th style = "padding:5px;">Qty</th>
      
    </tr>
    @foreach ($details_rec as $rec)
    <tr>
      <td style = "padding:5px; font-size:12px;">{{$rec->id}}</td>
      <td style = "padding:5px; font-size:12px;">{{$rec->size}}</td>
      
	    <td style = "padding:5px; font-size:12px;">{{$rec->number_sequence}}</td>
      <td style = "padding:5px; font-size:12px;">{{$rec->qty}}</td>
      <td style = "padding:5px; font-size:12px;">{{$rec->special_remark}}</td>
      <td style = "padding:5px; font-size:12px;">{{$rec->cudId}}</td>
      <td style = "padding:5px; font-size:12px;"></td>
      <td style = "padding:5px; font-size:12px;"></td>
      <td style = "padding:5px; font-size:12px;"></td>
      <td style = "padding:5px; font-size:12px;"></td>
      <td style = "padding:5px; font-size:12px;"></td>
      <td style = "padding:5px; font-size:12px;"></td>
      <td style = "padding:5px; font-size:12px;"></td>
      <td style = "padding:5px; font-size:12px;"></td>
      <td style = "padding:5px; font-size:12px;"></td>
      <td style = "padding:5px; font-size:12px;"></td>
      <td style = "padding:5px; font-size:12px;"></td>
      <td style = "padding:5px; font-size:12px;"></td>

      <td style = "padding:5px; font-size:12px;"></td>
      <td style = "padding:5px; font-size:12px;"></td>
      <td style = "padding:5px; font-size:12px;"></td>
      <td style = "padding:5px; font-size:12px;"></td>
      <td style = "padding:5px; font-size:12px;"></td>
      <td style = "padding:5px; font-size:12px;"></td>
    </tr>
    @endforeach
  </table>
  
  <table cellspacing="0" cellpadding="" border="1" width="100%" style="border-color:#333; border-collapse: collapse; margin-top:20px;">
    <tr>
      <th style="padding 20px; height: 60px; font-size:8px; text-align:left; vertical-align:top; width: 20%">Coment</th>

      <th style="width: 5%; font-size:8px; vertical-align:bottom;">
        <table cellspacing="0" cellpadding="" border="0" width="100%" style="border-color:#333; border-collapse: collapse; margin-top:10px;">
          <tr><th>---------------------------</th></tr>
          <tr><th>Recorder</th></tr>
        </table>
      </th>


      <th style="width: 5%; font-size:8px; vertical-align:bottom;">
        <table cellspacing="0" cellpadding="" border="0" width="100%" style="border-color:#333; border-collapse: collapse; margin-top:10px;">
          <tr><th>----------------------------</th></tr>
          <tr><th>Surpervisor</th></tr>
        </table>
      </th>


      <th style="width: 5%; font-size:8px; vertical-align:bottom;">
        <table cellspacing="0" cellpadding="" border="0" width="100%" style="border-color:#333; border-collapse: collapse; margin-top:10px;">
          <tr><th>-----------------------------</th></tr>
          <tr><th>Line Qc</th></tr>
        </table>
      </th>


      <th style="width: 10%; font-size:10px; vertical-align:bottom;">
        <table cellspacing="0" cellpadding="" border="0" width="100%" style="border-color:#333; border-collapse: collapse; margin-top:10px;">
          <tr><th>----------------------------------</th></tr>
          <tr><th>Production Surpervisor</th></tr>
        </table>
      </th>


      <th style="width: 10%; font-size:10px; vertical-align:bottom;">
        <table cellspacing="0" cellpadding="" border="0" width="100%" style="border-color:#333; border-collapse: collapse; margin-top:10px;">
          <tr><th>----------------------------------</th></tr>
          <tr><th>Section Incharge</th></tr>
        </table>
      </th>


      <th style="width: 10%; font-size:10px; vertical-align:bottom;">
        <table cellspacing="0" cellpadding="" border="0" width="100%" style="border-color:#333; border-collapse: collapse; margin-top:10px;">
          <tr><th>----------------------------------</th></tr>
          <tr><th>Packing Incharge</th></tr>
        </table>
      </th>
    </tr>
  <table>

  <table cellspacing="0" cellpadding="0" border="0" width="100%" style="border-color:#333; border-collapse: collapse; margin-top:10px;">
        </table>

</body>
</html>