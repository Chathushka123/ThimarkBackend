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

  <table cellspacing="0" cellpadding="0" border="0" width="100%" style="border-color:#333; border-collapse: collapse;table-layout: fixed ;
    width: 100% ;">
    <tr>
      <th style="width:80%; border:1px solid black; font-size:14px">T32-44502 1503 (MS 246) PACKING LIST</th>
      <th style="width:10%; font-size:8px; ">Packing List Sequence<th>
      <th style="width:10%; font-size:8px; border:1px solid black">{{$ID}}<th>
    </tr> 
  </table>


  <!-- ---------------------------- SOC Details -------------------------------------------->


  <table cellspacing="0" cellpadding="0" border="1" width="100%" style="border-color:#333; border-collapse: collapse;table-layout: fixed ;
    width: 100% ; margin-top:30px;">
    <tr >
      <th style="width:5%;  font-size:8px;  ">No</th>
      <th style="width:20%;  font-size:8px">Delivery Buyer Order Ref (VPO)</th>
      <th style="width:10%; font-size:8px;">Plan Delivery<th>
      <th style="width:20%; font-size:8px; ">Style Code<th>
      <th style="width:10%; font-size:8px; ">Summary OC No<th>
      <th style="width:10%; font-size:8px; ">FPO No<th>
      <th style="width:10%; font-size:8px; ">Color Code<th>
      <th style="width:10%; font-size:8px; ">Color Name<th>
      <th style="width:10%; font-size:8px; ">Ship Mode<th>
      <th style="width:10%; font-size:8px; ">Final Destination<th>
      <th style="width:10%; font-size:8px; ">Order Qty<th>
      <th style="width:5%; font-size:8px; ">Pcs Pack<th>
    </tr> 
    {{ $no=0}}
    {{ $orderTotal=0}}
    {{ $ratioSum=0}}
    @foreach ($soc as $rec)
    {{$no++}}
    {{$orderQty =0}}
    {{$ratioSum+=$rec->pack_ratio}}
    <?php
    $array = json_decode( $rec->quantity_json, true );
    foreach ($array  as $key=>$value){
    if(intval($value)>0){
      $orderQty+=$value;
      $orderTotal+=$value;
      
    }}?>
    
    <tr>
      <th style="width:5%;  font-size:7px; font-weight:50; padding_top:5px;padding-bottom:5px ">{{$no}}</th>
      <th style="width:20%;  font-size:7px; font-weight:50">{{$rec->vpo}}</th>
      <th style="width:10%; font-size:7px; font-weight:50">{{$rec->packing_list_delivery_date}}<th>
      <th style="width:20%; font-size:7px; font-weight:50">{{$rec->style_code}}<th>
      <th style="width:15%; font-size:7px; font-weight:50">{{$rec->wfx_soc_no}}<th>
      <th style="width:10%; font-size:7px; font-weight:50">-<th>
      <th style="width:10%; font-size:7px; font-weight:50">{{$rec->pack_color}}<th>
      <th style="width:10%; font-size:7px; font-weight:50">{{$rec->ColorName}}<th>
      <th style="width:10%; font-size:7px; font-weight:50">{{$rec->shipment_mode}}<th>
      <th style="width:10%; font-size:7px; font-weight:50">-<th>
      <th style="width:10%; font-size:7px; font-weight:50">{{$orderQty}}<th>
      <th style="width:5%; font-size:7px; font-weight:50">{{$rec->pack_ratio}}<th>
    </tr>
    @endforeach
  </table>
  <table cellspacing="0" cellpadding="0" border="0" width="100%" style="border-color:#333; border-collapse: collapse;table-layout: fixed ;
    width: 100% ; padding-left:1px; padding-right:1px;">
    <tr style="">
      <th style="width:105%;  font-size:14px;border-bottom:1px solid black; border-left:1px solid black; padding:5px"></th>
      <th style="width:10%; font-size:8px; font-size:8px; border-bottom:1px solid black; padding:5px ">Total<th>
      <th style="width:10%; font-size:8px;  font-size:8px; border-bottom:1px solid black">{{$orderTotal}}<th>
      <th style="width:5%; font-size:8px; font-size:8px; border-bottom:1px solid black; border-right:1px solid black">{{$ratioSum}}<th>
    </tr> 
  </table>


<!------------------------------- Summary Details ------------------------------------------->
  <?php
    $summary = [];
    $count = 0;
    foreach ($soc as $rec){
      $sorting = json_decode( $rec->sorting_json, true );
      asort($sorting);
      
      if($count == 0){
        $count++;
        foreach ($sorting  as $key=>$value){
          if (array_key_exists($key, $summary)) {
            $summary[$key] = 0;
          } else {
            $summary[$key] = 0;
          }
        }
      }
    }

    foreach ($soc as $rec){
      $array = json_decode( $rec->quantity_json, true );
      foreach ($array  as $key=>$value){
        if (array_key_exists($key, $summary)) {
          $summary[$key] = $summary[$key] + $value;
        } else {
         // $summary[$key] = $value;
        }
      }
    } 
  ?>


  <table cellspacing="0" cellpadding="0" border="1" width="100%" style="border-color:#333; border-collapse: collapse;table-layout: fixed ;
    padding-left:1px; padding-right:1px;  margin-top:20px;">
    <tr style= "padding:5px">
    <?php foreach ($summary as $key=> $val){ ?>
      <th style="width:20%; font-size:7px; padding:5px">{{$key}}</th>
    <?php } ?>
    </tr>
    </table>

  
<table cellspacing="0" cellpadding="0" border="1" width="100%" style="border-color:#333; border-collapse: collapse;table-layout: fixed ;
    padding-left:1px; padding-right:1px;  margin-top:20px;">
    <tr style= "padding:5px">
      <th style="width:20%; font-size:7px; padding:5px">Summary</th>
      <?php foreach ($summary as $key=> $val){ ?>
   
        <th style="width:10%; font-size:7px; padding:10px">{{$key}}</th>
      <?php } ?>
      <th style="width:20%; font-size:7px; padding:5px">Total</th>
    </tr>

    <tr style= "padding:5px">
      <th style="width:20%; font-size:7px; padding:5px"></th>
      {{$total =0}}
      <?php foreach ($summary as $key=> $val){  ?>
        <?php if($val > 0){
          $total += $val;
        }
        ?>
        <th style="width:10%; font-size:7px; padding:5px; font-weight:50">{{$val}}</th>
      <?php } ?>
      <th style="width:20%; font-size:7px; padding:5px">{{$total}}</th>
    </tr>
    <tr style= "padding:5px">
      <th style="width:20%; font-size:7px; padding:5px">Variance</th>
      {{$total =0}}
      <?php foreach ($summary as $key=> $val){  ?>
        <?php if($val > 0){
          $total += $val;
        }
        ?>
        <th style="width:10%; font-size:7px; padding:5px"></th>
      <?php } ?>
      <th style="width:20%; font-size:7px; padding:5px">0</th>
    </tr>
  </table>

  <!-----------------------------------------Comment  -------------------------------------------->
  <!-- <table cellspacing="0" cellpadding="0" border="1" width="100%" style="border-color:#333; border-collapse: collapse;table-layout: fixed ;
    padding-left:1px; padding-right:1px; background-color:#fff; margin-top:20px;">
    <tr style= "padding:5px">
      <th style="width:15%; font-size:7px; padding:5px">Comment :</th>
      <th style="width:85%; font-size:7px; padding:5px"></th>
    </tr> -->
  <!----------------------------------- Carton Details----------------------------------------- -->
  <table cellspacing="0" cellpadding="0" border="1" width="100%" style="border-color:#333; border-collapse: collapse;table-layout: fixed ;
    padding-left:1px; padding-right:1px; margin-top:20px;">
    <tr style= "padding:5px">
      <th colspan = "2" style="width:20%; font-size:7px; padding:5px">CTN NO</th>
      <?php foreach ($summary as $key=> $val){ ?>
        
      <th style="width:10%; font-size:7px; padding:5px">{{$key}}</th>
      <?php } ?>
      <th style="width:10%; font-size:7px; padding:5px">PCS PER CTN</th>
      <th style="width:20%; font-size:7px; padding:5px">NO OF CTN</th>
      <th style="width:20%; font-size:7px; padding:5px">TOTAL PCS</th>
    </tr>
    {{$i=1}}
    <?php $total_ratio_json =[]; $total_ctn=0; $all_pcs =0;?>
    <?php foreach ($carton as $rec){ ?>
      <tr>
        <th style="width:10%; font-size:7px; padding:5px">{{$i}}</th>
        {{$i = $i+$rec->no_of_cartons}}
        <th style="width:10%; font-size:7px; padding:5px">{{$i-1}}</th>

        <!------------------------ for Different Json Format --------------------------->
        <?php $ratio_json =[];  ?>
      <?php foreach ($summary as $key=> $val){ 
        $array = json_decode( $rec->ratio_json, true );
        foreach($array as $key1=>$value1){
          if($key1 == $key){
            $ratio_json[$key]=$value1;

            if (array_key_exists($key1, $total_ratio_json)) {
              $total_ratio_json[$key] = $total_ratio_json[$key] + intval($value1);
            } else {
              $total_ratio_json[$key] = intval($value1);
            }
          }

      }}?>
      {{$pcs_carton = 0}}
      <?php foreach($ratio_json as $key=>$val){ 
        if(intval($val) > 0){
          $pcs_carton+=$val;
        }
        ?>

        <th style="width:10%; font-size:7px; padding:5px">{{$val}}</th>
        <?php } ?>
        <?php
        $pcs_carton = $pcs_carton;
        $total_pcs = $pcs_carton*$rec->no_of_cartons;
        $total_ctn += intval($rec->no_of_cartons);
        $all_pcs += intval($total_pcs); ?>
        <th style="width:10%; font-size:7px; padding:5px">{{$pcs_carton}}</th>
        <th style="width:10%; font-size:7px; padding:5px">{{$rec->no_of_cartons}}</th>
        <th style="width:10%; font-size:7px; padding:5px">{{$total_pcs}}</th>
      </tr>

    <?php } ?>
    <tr style= "padding:5px">
      <th colspan = "2" style="width:20%; font-size:7px; padding:5px">Total</th>

      <?php foreach($total_ratio_json as $key=>$val){ ?>
        <th  style="width:10%; font-size:7px; padding:5px"></th>
      <?php } ?>
      <th  style="width:10%; font-size:7px; padding:5px"></th>
      <th  style="width:10%; font-size:7px; padding:5px">{{$total_ctn}}</th>
      <th  style="width:10%; font-size:7px; padding:5px">{{$all_pcs}}</th>
    </tr>
      </table>

    <!----------------------------------------  BOX  ---------------------------------------------->

    <table cellspacing="0" cellpadding="0" border="0" width="10%" style="border-color:#333; border-collapse: collapse;table-layout: fixed ;
    padding-left:1px; padding-right:1px; margin-top:20px;">
      <tr style= "padding:5px">
       <th  style="width:10%; font-size:7px; padding:5px; align:left">No of Box</th>
      </tr>
      </table>

      
      
      <?php for($i=0; $i<$total_ctn; $i++){ 
          if($i%10 == 0){ 
            if(($total_ctn-$i) >=10){ ?>
              <table cellspacing="0" cellpadding="0" border="1" width="100%" style="border-color:#333; border-collapse: collapse;table-layout: fixed ;
                padding-left:1px; padding-right:1px; ">
                <tr style= "padding:5px">
                <th style="width:10%; font-size:8px;  padding:5px;">{{$i+1}}</th>
              <?php }else{ $width = (($total_ctn-$i)*10)."%" ?>  
                <table cellspacing="0" cellpadding="0" border="1" width=<?php echo $width ?> style="border-color:#333; border-collapse: collapse;table-layout: fixed ;
                padding-left:1px; padding-right:1px; ">
                <tr style= "padding:5px">
                <th style="width:10%; font-size:8px;  padding:5px;">{{$i+1}}</th>
              <?php  }
          }else if($i%10 == 9){ ?>
          <th style="width:10%; font-size:8px;  padding:5px;">{{$i+1}}</th>
        </tr>
        </table>
        <?php }else{ ?>
          <th style="width:10%; font-size:8px; padding:5px;">{{$i+1}}</th>
      <?php }} ?>

      <?php if($total_ctn%10 == 0){  ?>
        </table>
      <?php } ?>

      <!-------------------------------------------   Carton Type ------------------------------------>
      

      <table cellspacing="0" cellpadding="0" border="1" width="40%" style="border-color:#333; border-collapse: collapse;table-layout: fixed ;
                padding-left:1px; padding-right:1px; margin-top : 20px;">
                <tr style= "padding:5px">
                  <th style="width:60%; font-size:8px;  padding:5px;">Diamention</th>
                  <th style="width:40%; font-size:8px;  padding:5px;">No of Carton</th>
                  
                </tr>
      <?php $box = array(); ?>
      <?php foreach ($carton as $rec){ 
        $flag = false; ?>
        

        <?php if(sizeof($box) == 0){ 
          array_push($box,$rec->carton_id);
        }else {   
          for($i=0; $i<sizeof($box); $i++){
            if(intval($box[$i]) == intval($rec->carton_id)){
              $flag = true;

            }
          }
          if(!$flag){
            array_push($box,$rec->carton_id);
          }
        }?>


      <?php } ?> <!-- End of Foreach -->

      <?php for($i=0; $i<sizeof($box); $i++){
        $carton_type ="";
        $no_of_carton = 0;
       
        foreach ($carton as $rec){ 
          if($rec->carton_id == $box[$i]){
            $carton_type = $rec->carton_type;
            $no_of_carton += intval($rec->no_of_cartons);
        
         }} ?>
    
            <tr style= "padding:5px">
              <th style="width:60%; font-size:8px;  padding:5px;">{{$carton_type}}</th>
              <th style="width:40%; font-size:8px;  padding:5px;">{{$no_of_carton}}</th>
              
            </tr>
      <?php } ?>
        </table>

</body>
</html>