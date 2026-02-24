<style>
  .test{
    font-size : 14px;
  }
</style>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<head>

</head>
<body style="font-family: sans-serif;">

<table cellspacing="0" cellpadding="5px" border="0" width="100%" style="border-color:#333; border-collapse: collapse;table-layout: fixed ;
    width: 100% ;">
    <tr>
      <th style="width:100%; border:1px solid black; font-size:14px">INQUBE GLOBAL (PVT) LTD.</th>
    </tr> 
    <tr>
      <th style="width:100%;  font-size:11px">CUTTING LAY SHEET</th>
    </tr> 
</table>
{{$fppo_no=""}}
<?php foreach ($fppo as $rec){ 
    
    $fppo_no .= $rec->fppo_no ." / ";
 } ?>

<table cellspacing="0" cellpadding="5px" border="0" width="100%" style="border-color:#333; border-collapse: collapse;table-layout: fixed ;margin-top:20px;
    width: 100% ;">
    <tr>
      <th style="width:20%; border:1px solid black; font-size:11px; text-align:left;">Soc</th>
      {{ $soc =""}}
      {{ $fpos =""}}
      {{$customer_style_ref=""}}
      {{$style_code=""}}
      {{$buyer_name=""}}
      {{$line_no=""}}
      
      {{$cut_no=""}}
      {{$fabric=""}}
      {{$acc_width=""}}
      {{$garment_color=""}}
      {{$marker_name=""}}
      {{$max_plies=0}}
      {{$pcs_macker=0}}
      {{$yrds=0}}
      {{$inch=0}}
      {{$cutID=""}}
      <?php $value_json="" ?>
      <?php foreach ($cut as $rec){  
        $soc .= $rec->wfx_soc_no . " / ";
        $fpos .= $rec->wfx_fpo_no ." / ";
        $customer_style_ref = $rec->customer_style_ref ;
        $style_code = $rec->style_code ;
        $buyer_name = $rec->name ;
        $line_no .= $rec->line_no ." / ";
        $cut_no = $rec->cut_no ;
        $fabric = $rec->fabric;
        $acc_width = $rec->acc_width;
        $garment_color = $rec->garment_color;
        $value_json = $rec->value_json;
        $marker_name = $rec->marker_name;
        $max_plies = $rec->max_plies;
        $yrds = $rec->yrds;
        $inch = $rec->inch;
        $cutID = $rec->id;
         } ?>
        <th style="width:80%; border:1px solid black; font-size:11px; text-align:left; font-weight:50">{{$soc}}</th>
    </tr> 
    <tr>
        <th style="width:20%; border:1px solid black; font-size:11px; text-align:left;">Fpo</th>
        <th style="width:80%; border:1px solid black; font-size:11px; text-align:left; font-weight:50">{{$fpos}}</th>
    </tr>
    <tr>
        <th style="width:20%; border:1px solid black; font-size:11px; text-align:left;">Customer Style</th>
        <th style="width:80%; border:1px solid black; font-size:11px; text-align:left; font-weight:50">{{$customer_style_ref}}</th>
    </tr>
    <tr>
        <th style="width:20%; border:1px solid black; font-size:11px; text-align:left;">WFX Style Code</th>
        <th style="width:80%; border:1px solid black; font-size:11px; text-align:left; font-weight:50">{{$style_code}}</th>
    </tr>

</table>

<table cellspacing="0" cellpadding="5px" border="0" width="100%" style="border-color:#333; border-collapse: collapse;table-layout: fixed ; margin-top:20px;
    width: 100% ;">
    <tr>
        <th style="width:20%; border:1px solid black; font-size:11px; text-align:left">Print Date</th>
        <th colspan="3" style="width:80%; border:1px solid black; font-size:11px; font-weight:50;text-align:left"><?php echo date("Y-m-d h:i:sa"); ?></th>
    </tr> 
    <tr>
        <th style="width:20%; border:1px solid black; font-size:11px;text-align:left">Customer</th>
        <th style="width:50%; border:1px solid black; font-size:11px;font-weight:50;text-align:left">{{$buyer_name}}</th>
        <th style="width:10%; border:1px solid black; font-size:11px;text-align:left">Line</th>
        <th style="width:20%; border:1px solid black; font-size:11px; font-weight:50;text-align:left"></th>
    </tr> 
    <tr>
        <th style="width:20%; border:1px solid black; font-size:11px; text-align:left">FPPO</th>
        <th style="width:50%; border:1px solid black; font-size:11px;font-weight:50;text-align:left">{{$fppo_no}}</th>
        <th style="width:10%; border:1px solid black; font-size:11px;text-align:left">Cut No</th>
        <th style="width:20%; border:1px solid black; font-size:11px; font-weight:50;text-align:left">{{$cut_no}}</th>
    </tr> 
    <tr>
        <th style="width:20%; border:1px solid black; font-size:11px; text-align:left">Fabric</th>
        <th style="width:50%; border:1px solid black; font-size:11px;font-weight:50;text-align:left">{{$fabric}}</th>
        <th style="width:10%; border:1px solid black; font-size:11px;text-align:left">Width</th>
        <th style="width:20%; border:1px solid black; font-size:11px; font-weight:50;text-align:left"></th>
    </tr> 
    <tr>
        <th style="width:20%; border:1px solid black; font-size:11px; text-align:left">GMT Color</th>
        <th style="width:50%; border:1px solid black; font-size:11px;font-weight:50;text-align:left">{{$garment_color}}</th>
        <th style="width:10%; border:1px solid black; font-size:11px;text-align:left">FAB Color</th>
        <th style="width:20%; border:1px solid black; font-size:11px; font-weight:50;text-align:left">{{$garment_color}}</th>
    </tr> 
    <tr>
        <th style="width:20%; border:1px solid black; font-size:11px; text-align:left; background_color:yellow">Part</th>
        <th colspan="3" style="width:80%; border:1px solid black; font-size:11px; background_color:yellow">Body</th>
    </tr> 
</table>
<?php $pcs_macker=0; ?>
<?php $array = json_decode($value_json, true ); ?>
<table cellspacing="0" cellpadding="5px" border="0" width="100%" style="border-color:#333; border-collapse: collapse;table-layout: fixed ;
    width: 100% ; margin-top:20px">
    <tr>
        <?php foreach ($array  as $key=>$value){ 
           if(intval($value) > 0){ 
               $pcs_macker += intval($value);
               ?>

            <th style="width:10%; border:1px solid black; font-size:11px">{{$key}}</th>
        <?php }} ?>
      
    </tr> 
    <tr>
        <?php foreach ($array  as $key=>$value){ 
           if(intval($value) > 0){ ?>
            <th style="width:10%; border:1px solid black; font-size:11px; font-weight:50;">{{$value}}</th>
        <?php }} ?>
      
    </tr> 

</table>
<?php $fab_yardage =round(($max_plies*$yrds)+($inch/36)*$max_plies,4);  
    $yds = round($yrds+($inch/36),4);
    $str_yds="";
    if(intval($yds) < 10){
      $str_yds = "0".$yds."-".$marker_name."-".$cutID."-";
    }
    else{
      $str_yds = $yds."-".$marker_name."-".$cutID."-";
    }
    $meter = round(($yrds+($inch/36))/1.09361,4);

    $consump ="";
    foreach ($consumption as $rec){  
        $consump = $rec->avg_consumption;
    }

    $perimeter = round(($consump*$pcs_macker)/1.03,4);

?>


<table cellspacing="0" cellpadding="5px" border="0" width="100%" style="border-color:#333; border-collapse: collapse;table-layout: fixed ;
    width: 100% ; margin-top:20px">
    <tr>
      <th   style="width:20%; border:1px solid black; font-size:11px; text-align:left">Marker Name</th>
      <th colspan="6"  style="width:80%; border:1px solid black; font-size:11px;font-weight:50;">{{$marker_name}}</th>
    </tr> 
    <tr>
        <th  style="width:20%; border:1px solid black; font-size:11px; text-align:left">Fab Yardage</th>
        <th colspan="6" style="width:80%; border:1px solid black; font-size:11px; font-weight:50;">{{$fab_yardage}}</th>
    </tr>
    <tr>
      <th style="width:20%; border:1px solid black; font-size:11px; text-align:left">Ply Length</th>
      <th style="width:10%; border:1px solid black; font-size:11px;">(Yards)</th>
      <th style="width:10%; border:1px solid black; font-size:11px;">(Inch)</th>
      <th colspan="4" style="width:60%; border:1px solid black; font-size:11px;"></th>
    </tr> 

    <tr>
      <th style="width:20%; border:1px solid black; font-size:11px"></th>
      <th style="width:10%; border:1px solid black; font-size:11px; font-weight:50; background-color:yellow">{{$yrds}}</th>
      <th style="width:10%; border:1px solid black; font-size:11px; font-weight:50;background-color:yellow">{{$inch}}</th>
      <th  style="width:20%; border:1px solid black; font-size:11px; font-weight:50;">{{$str_yds}}</th>
      <th  style="width:10%; border:1px solid black; font-size:11px;">YDS</th>
      <th  style="width:20%; border:1px solid black; font-size:11px; font-weight:50;">{{$meter}}</th>
      <th  style="width:10%; border:1px solid black; font-size:11px;">M</th>
    </tr> 
    <tr>
      <th style="width:20%; border:1px solid black; font-size:11px; text-align:left">Perimeter Amt</th>
      <th colspan="2" style="width:20%; border:1px solid black; font-size:11px;font-weight:50;">{{$perimeter}}</th>
      <th style="width:20%; border:1px solid black; font-size:11px;background-color:yellow">Marker Width </th>
      <th colspan="3" style="width:40%; border:1px solid black; font-size:11px;font-weight:50;background-color:yellow">{{$acc_width}}</th>
    </tr> 
    <tr>
      <th style="width:20%; border:1px solid black; font-size:11px; padding:5px,15px,15px; text-align:left">Remarks</th>
      <th colspan="6"  style="width:80%; border:1px solid black; font-size:11px;font-weight:50;padding:15px"></th>
    </tr> 
</table>

<table cellspacing="0" cellpadding="5px" border="0" width="100%" style="border-color:#333; border-collapse: collapse;table-layout: fixed ;
    width: 100% ; margin-top:20px">
    <tr>
      <th rowspan="2" style="width:100%; border:1px solid black; font-size:11px; text-align:left">Nequired Fab Yardage</th>
      <th style="width:100%;border:1px solid black;  font-size:11px">Total Yards</th>
      <th style="width:100%;border:1px solid black;  font-size:11px">(Yards) </th>
      <th style="width:100%;border:1px solid black;  font-size:11px">(Inch)</th>
    </tr> 
    <tr>
      <th style="width:100%;border:1px solid black;  font-size:14px; "><?php echo $fab_yardage ?></th>
      <th style="width:100%;border:1px solid black;  font-size:11px"><?php echo intval($fab_yardage) ?></th>
      <th style="width:100%;border:1px solid black;  font-size:11px"><?php echo(round(($fab_yardage- intval($fab_yardage))*36,4)) ?></th>
    </tr> 
    <tr>
      <th style="width:100%;border:1px solid black;  font-size:11px; text-align:left">Pcs in Marker</th>
      <th style="width:100%;border:1px solid black;  font-size:11px;font-weight:50;">{{$pcs_macker}}</th>
      <th style="width:100%;border:1px solid black;  font-size:11px">Consumption</th>
      <th style="width:100%;border:1px solid black;  font-size:11px;font-weight:50;">{{$consump}}</th>
    </tr> 
    <tr>
      <th style="width:100%;border:1px solid black;  font-size:11px; text-align:left">No of Layers</th>
      <th style="width:100%;border:1px solid black;  font-size:11px;font-weight:50;">{{$max_plies}}</th>
      <th style="width:100%;border:1px solid black;  font-size:11px">Act. Consumption </th>
      <th style="width:100%;border:1px solid black;  font-size:11px;font-weight:50;"><?php echo round($fab_yardage/($pcs_macker*$max_plies),4) ?></th>
    </tr>
    <tr>
      <th style="width:100%;border:1px solid black;  font-size:11px; text-align:left">Total Cut Qty</th>
      <th colspan="3" style="width:100%;border:1px solid black;  font-size:11px;font-weight:50;"><?php echo $pcs_macker*$max_plies ?></th>
    </tr>
    <tr>
      <th style="width:100%;border:1px solid black;  font-size:11px; background-color:yellow; text-align:left">Components</th>
      <th colspan="3" style="width:100%;border:1px solid black;  font-size:11px;font-weight:50;background-color:yellow"></th>
    </tr>
    <tr>
      <th style="width:100%;border:1px solid black;  font-size:11px; background-color:yellow; text-align:left">Laying Mode</th>
      <th colspan="3" style="width:100%;border:1px solid black;  font-size:11px;font-weight:50; background-color:yellow"></th>
    </tr>
</table>

<table cellspacing="0" cellpadding="5px" border="0" width="100%" style="border-color:#333; border-collapse: collapse;table-layout: fixed ;
    width: 60% ; margin-top:20px">
    <tr>
      <th style="width:40%; font-size:11px; text-align:left">Binding Consumption </th>
      <th style="width:50%;border:1px solid black;  font-size:11px"></th>
      <th style="width:10%; font-size:11px; text-align:left">Y ds. </th>
    </tr> 
    <tr>
      <th style="width:40%; font-size:11px; text-align:left">Total Fabrics for Binding </th>
      <th style="width:40%;border:1px solid black;  font-size:11px"></th>
      <th style="width:20%; font-size:11px; text-align:left">Y ds. </th> 
    </tr> 
</table>

</body>
</html>