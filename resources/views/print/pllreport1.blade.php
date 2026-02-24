<style>
  .test{
    font-size : 14px;
  }
  th span {
  transform-origin: 30% 30%;
  transform: rotate(-90deg); 
   /* white-space: nowrap;  */
  display: block;
  left: 50%;
  bottom: 0;
  max-height:20px;
  position: relative;
}


</style>


<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<head>
  <link href='https://fonts.googleapis.com/css?family=Libre Barcode 39' rel='stylesheet'>
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
<body style="">

<script type="text/php">
    if ( isset($pdf) ) {
        $font = "";
        $pdf->page_text(400, 560, "Page: {PAGE_NUM} of {PAGE_COUNT}", $font, 12, array(0,0,0));
    }
</script> 

<?php $description = "" ?>

@foreach ($soc as $rec)
 <?php $description = $rec->description; 
    $rv_no = $rec->revision_no;
  // if($revision_no == ''){
  //   $rv_no = $rec->revision_no;
  // }else{
  //   $rv_no = $revision_no;
  // }
   
 
 ?>
@endforeach
<table cellspacing="0" cellpadding="0" border="0" width="100%" style="border-color:#333; border-collapse: collapse;table-layout: fixed ;
    width: 100% ;">
    <tr>
      <th  style="width:70%; border:1px solid black; font-size:12px; padding:2px; text-align:left">Print Time :  {{now()}}</th>

      <th  style="width:25%;  border:1px solid black; font-weight:50; padding:2px">{{$status}}</th>
      <th  style="width:5%;  border:1px solid black; font-weight:50; padding:2px">{{$revision_no}}</th>
    </tr> 
 
  </table>
  <table cellspacing="0" cellpadding="0" border="0" width="100%" style="border-color:#333; border-collapse: collapse;table-layout: fixed ;
    width: 100% ;">

    <tr>
      <th style="width:62%; border:1px solid black; font-size:12px; padding:2px">{{$description." "."PACKING LIST "}}</th>
      <th style="width:8%; font-size:10px; border:1px solid black; padding:2px">Packing List Sequence</th>
      <th  style="width:10%; font-size:10px; border:1px solid black; font-weight:50;padding:2px">{{$ID}}</th>
      <th  style="width:10%;  border:1px solid black; font-weight:50; padding:2px"><p id="No">*{{$ID}}*</p></th>
      <th  style="width:5%; font-size:10px; border:1px solid black; font-weight:50;padding:2px"> Latest RV</th>
      <th  style="width:5%;  border:1px solid black; font-weight:50; padding:2px">{{$rv_no}}</th>
    </tr> 
  </table>


  <!-- ---------------------------- SOC Details -------------------------------------------->


  <table cellspacing="0" cellpadding="0" border="1" width="100%" style="border-color:#333; border-collapse: collapse;table-layout: fixed ;
    width: 100% ; margin-top:15px;">
    <tr >
      <th style="width:5%;  font-size:8px;  ">No</th>
      <th style="width:20%;  font-size:8px">Production VPO</th>
      <th style="width:20%;  font-size:8px">Current VPO</th>
      <th style="width:10%; font-size:8px;">Plan Delivery<th>
      <th style="width:20%; font-size:8px; ">Style Code<th>
      <th style="width:10%; font-size:8px; ">Summary OC No<th>
      <!-- <th style="width:10%; font-size:8px; ">FPO No<th> -->
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
    {{$carton_numbering_id=0}}

    @foreach ($soc as $rec)
    {{$no++}}
    {{$orderQty =0}}
    {{$ratioSum+=$rec->pack_ratio}}
    {{$carton_numbering_id = $rec->carton_number_format_id}}
    <?php
    $array = json_decode( $rec->quantity_json, true );
    foreach ($array  as $key=>$value){
    if(intval($value)>0){
      $orderQty+=$value;
      $orderTotal+=$value;
      
    }}?>
    
    <tr>
      <th style="width:5%;  font-size:8px; font-weight:50; padding_top:5px;padding-bottom:5px ">{{$no}}</th>
      <th style="width:20%;  font-size:8px; font-weight:50">{{$rec->vpo}}</th>
      <th style="width:20%;  font-size:8px; font-weight:50">{{$rec->current_vpo}}</th>
      <th style="width:10%; font-size:8px; font-weight:50">{{$rec->packing_list_delivery_date}}<th>
      <th style="width:20%; font-size:8px; font-weight:50">{{$rec->style_code}}<th>
      <th style="width:15%; font-size:8px; font-weight:50">{{$rec->wfx_soc_no}}<th>
      <!-- <th style="width:10%; font-size:8px; font-weight:50">-<th> -->
      <th style="width:10%; font-size:8px; font-weight:50">{{$rec->pack_color}}<th>
      <th style="width:10%; font-size:8px; font-weight:50">{{$rec->ColorName}}<th>
      <th style="width:10%; font-size:8px; font-weight:50">{{$rec->shipment_mode}}<th>
      <th style="width:10%; font-size:8px; font-weight:50">{{$rec->destination}}<th>
      <th style="width:10%; font-size:8px; font-weight:50">{{$orderQty}}<th>
      <th style="width:5%; font-size:8px; font-weight:50">{{$rec->pack_ratio}}<th>
    </tr>
    @endforeach
  </table>
  <table cellspacing="0" cellpadding="0" border="0" width="100%" style="border-color:#333; border-collapse: collapse;table-layout: fixed ;
    width: 100% ; padding-left:1px; padding-right:1px;">
    <tr style="">
      <th style="width:105%;  font-size:12px;border-bottom:1px solid black; border-left:1px solid black; padding:5px"></th>
      <th style="width:10%;  font-size:10px; border-bottom:1px solid black; padding:5px ">Total<th>
      <th style="width:10%;   font-size:10px; border-bottom:1px solid black">{{$orderTotal}}<th>
      <th style="width:5%;  font-size:10px; border-bottom:1px solid black; border-right:1px solid black">{{$ratioSum}}<th>
    </tr> 
  </table>


<!------------------------------- Summary Details ------------------------------------------->
  <?php

  /////////////////////////  Set Json Array According to Sorting Array  ///////////////
    $summary = [];
    $count = 0;
    foreach ($soc as $rec){
      if(!is_null($rec->sorting_json)){
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
    }

    $customer_size=[];
    foreach ($soc as $rec){
      $array = json_decode( $rec->quantity_json, true );
      foreach ($array  as $key=>$value){
        if (array_key_exists($key, $summary)) {
          $summary[$key] = $summary[$key] + $value;
        } else {
          $summary[$key] = $value;
        }
      }
    }
    //---------------- Make Customer Size Array according to summary array   ------------//
    foreach ($summary as $key=> $val){
      $customer_size[$key]="NotCustomerCode";
      foreach ($carton as $rec){  
        $array = json_decode( $rec->ratio_json, true );
        foreach ($array  as $key1=>$val1){
          if($key == $key1 && intval($val1)>0){
            // if($customer_size[$key] == ""){
              $customer_size[$key] = $rec->customer_size_code;
            //}
          }
        }
      }
    }
    
  ?>
  
<table cellspacing="0" cellpadding="0" border="1" width="100%" style="border-color:#333; border-collapse: collapse;table-layout: fixed ;
    padding-left:1px; padding-right:1px;  margin-top:15px;">
	
    <tr style= "padding:1px">
      <th style="width:40%;position: relative; font-size:8px; height:40px ;">SOC Summary</th>
      <?php foreach ($summary as $key=> $val){ 
        if($val >0){?>
		
        <th style="width:5%;position: relative; font-size:8px; vertical-align:bottom; "> <span>{{$key}}</span></th>
      <?php }} ?>
      <th style="width:40%;position: relative; font-size:8px;">Total</th>
    </tr>

    <!-- ---------------------- Print Soc ------------------------------------------>
    <?php 
          foreach ($soc as $rec){
            $array = json_decode( $rec->quantity_json, true ); 
            $soc_qty_total = 0; ?>

            <tr style= "padding:1px">
              <th style="width:20%; font-size:8px; padding:1px">{{ $rec->wfx_soc_no }}</th>

            <?php foreach ($summary as $key=> $val){  
                if($val > 0){ 
                  $soc_qty_total += $array[$key];
                  ?>
                  <th style="width:20%; font-size:8px; padding:1px">{{ $array[$key] }}</th>
            <?php }} ?>

                <th style="width:20%; font-size:8px; padding:1px">{{$soc_qty_total}}</th>
          </tr>

    <?php      } ?>

    

    <tr style= "padding:1px">
      <th style="width:20%; font-size:8px; padding:1px">Qty</th>
      {{$total =0}}
      <?php foreach ($summary as $key=> $val){  ?>
        <?php if($val > 0){
          $total += $val;
        
        ?>
        <th style="width:10%; font-size:8px; padding:1px; font-weight:50">{{$val}}</th>
      <?php }} ?>
      <th style="width:20%; font-size:8px; padding:1px">{{$total}}</th>
    </tr>
   <!-- <tr style= "padding:1px">
      <th style="width:20%; font-size:8px; padding:1px; height:100px ;">Customer Size Code</th>
      {{$total =0}}
      <?php foreach ($customer_size as $key=> $val){  
        if($val != "NotCustomerCode"){?>
        <th style="width:5%;position: relative; font-size:8px; max-height:100px;vertical-align:bottom; padding-bottom:5px"><span>{{$val}}</span></th>
      <?php }} ?>
        
        
      
      <th style="width:20%; font-size:8px; padding:1px; height:100px ;"></th>
    </tr> -->
  </table>

  <!-----------------------------------------Comment  -------------------------------------------->
  <!-- <table cellspacing="0" cellpadding="0" border="1" width="100%" style="border-color:#333; border-collapse: collapse;table-layout: fixed ;
    padding-left:1px; padding-right:1px; background-color:#fff; margin-top:20px;">
    <tr style= "padding:1px">
      <th style="width:15%; font-size:7px; padding:1px">Comment :</th>
      <th style="width:85%; font-size:7px; padding:1px"></th>
    </tr> -->
  <!----------------------------------- Carton Details----------------------------------------- -->
  <table cellspacing="0" cellpadding="0" border="1" width="100%" style="border-color:#333; border-collapse: collapse;table-layout: fixed ;
    padding-left:1px; padding-right:1px; margin-top:15px;">
    <tr style= "padding:1px">
        
      <th colspan = "2" style="width:20%; font-size:8px; padding:1px">CTN NO</th>
      <th  style="width:20%; font-size:8px; padding:1px">Carton Type</th>
      <th  style="width:30%; font-size:8px; padding:1px">Customer Size Code</th>
      <?php foreach ($summary as $key=> $val){ 
        if($val >0){ ?>
        
      <th style="width:10%; font-size:8px; ;position: relative; font-size:8px;height:40px; vertical-align:bottom;"><span>{{$key}}</span></th>
      <?php }} ?>
      <th style="width:10%; font-size:8px; padding:1px">PCS PER CTN</th>
      <th style="width:20%; font-size:8px; padding:1px">NO OF CTN</th>
      <th style="width:20%; font-size:8px; padding:1px">TOTAL PCS</th>
      
    </tr>
    {{$i=1}}
    <?php $total_ratio_json =[]; $total_ctn=0; $all_pcs =0; $row = 0?>
    <?php foreach ($carton as $rec){ 
      $row++ ;

      ?>
     
      <tr id=<?php echo $row ?>>
        <!-------------------------  Carton Number Format  ---------------------------->
        <?php if(!($carton_numbering_id == 3 || $carton_numbering_id == 4)){ ?>
          <th style="width:10%; font-size:8px; padding:1px; font-weight:50">{{$i}}</th>
          {{$i = $i+$rec->no_of_cartons}}
          <th style="width:10%; font-size:8px; padding:1px; font-weight:50">{{$i-1}}</th>
          <th style="width:20%; font-size:8px; padding:1px; font-weight:50">{{$rec->carton_type}}</th>
          <th style="width:30%; font-size:8px; padding:1px; font-weight:50">{{$rec->customer_size_code}}</th>
        <?php }else{ ?>
          <th style="width:10%; font-size:8px; padding:1px; font-weight:50">{{1}}</th>
          
          <th style="width:10%; font-size:8px; padding:1px; font-weight:50">{{$rec->no_of_cartons}}</th>
          <th style="width:20%; font-size:8px; padding:1px; font-weight:50">{{$rec->carton_type}}</th>
          <th style="width:30%; font-size:8px; padding:1px; font-weight:50">{{$rec->customer_size_code}}</th>
        <?php } ?>
        <!------------------------ for Different Json Format  --------------------------->
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
        $found = false;
        if(intval($val) > 0){
          $pcs_carton+=$val;
        }
        foreach ($summary as $key1=> $val1){ 
          if($val1 >0 && $key1 == $key){
            $found = true;
          }
        }
        if($found){
        ?>

        <th style="width:10%; font-size:8px; padding:1px; font-weight:50">{{$val}}</th>
        <?php }} ?>
        <?php
        $pcs_carton = $pcs_carton;
        $total_pcs = $pcs_carton*$rec->no_of_cartons;
        $total_ctn += intval($rec->no_of_cartons);
        $all_pcs += intval($total_pcs); ?>
        <th style="width:10%; font-size:8px; padding:1px; font-weight:50">{{$pcs_carton}}</th>
        <th style="width:10%; font-size:8px; padding:1px; font-weight:50">{{$rec->no_of_cartons}}</th>
        <th style="width:10%; font-size:8px; padding:1px; font-weight:50">{{$total_pcs}}</th>
      </tr>

    <?php } ?>
    <tr style= "padding:1px">
      <th colspan = "2" style="width:20%; font-size:8px; padding:1px">Total</th>
      <th  style="width:20%; font-size:8px; padding:1px"></th>
      <th  style="width:30%; font-size:8px; padding:1px"></th>

      <?php foreach($total_ratio_json as $key=>$val){
        if($val > 0){ ?>
        <th  style="width:10%; font-size:8px; padding:1px"></th>
      <?php }} ?>
      <th  style="width:10%; font-size:8px; padding:1px"></th>
      <th  style="width:10%; font-size:8px; padding:1px">{{$total_ctn}}</th>
      <th  style="width:10%; font-size:8px; padding:1px">{{$all_pcs}}</th>
    </tr>
  </table>

    <!----------------------------------------  BOX  ---------------------------------------------->

    <table cellspacing="0" cellpadding="0" border="0" width="10%" style="border-color:#333; border-collapse: collapse;table-layout: fixed ;
    padding-left:1px; padding-right:1px; margin-top:15px;">
      <tr style= "padding:1px">
       <th  style="width:10%; font-size:8px; padding:1px; align:left">No of Box</th>
      </tr>
      </table>

      
      <?php if(!($carton_numbering_id == 3 || $carton_numbering_id==4)){ ?>
          <?php for($i=0; $i<$total_ctn; $i++){ 
              if($i%10 == 0){ 
                if(($total_ctn-$i) >=10){ ?>
                  <table cellspacing="0" cellpadding="0" border="1" width="100%" style="border-color:#333; border-collapse: collapse;table-layout: fixed ;
                    padding-left:1px; padding-right:1px; ">
                    <tr style= "padding:1px">
                    <th style="width:10%; font-size:8px;  padding:1px; font-weight:50">{{$i+1}}</th>
                  <?php }else{ $width = (($total_ctn-$i)*10)."%" ?>  
                    <table cellspacing="0" cellpadding="0" border="1" width=<?php echo $width ?> style="border-color:#333; border-collapse: collapse;table-layout: fixed ;
                    padding-left:1px; padding-right:1px; ">
                    <tr style= "padding:1px">
                    <th style="width:10%; font-size:8px;  padding:1px; font-weight:50">{{$i+1}}</th>
                  <?php  }
              }else if($i%10 == 9){ ?>
                    <th style="width:10%; font-size:8px;  padding:1px; font-weight:50">{{$i+1}}</th>
                  </tr>
                </table>
            <?php }else{ ?>
                  <th style="width:10%; font-size:8px; padding:1px; font-weight:50">{{$i+1}}</th>
            <?php }} ?>

          <?php if($total_ctn%10 == 0){  ?>
            </table>
      <?php }}else{ 
          $print_ctn=0;      
          foreach ($carton as $rec){
            $array = json_decode( $rec->ratio_json, true );
            $size="";
            foreach($array as $key=>$value){
              if(intval($value)>0){
                $size = $key;
              }
            }
            
            for($i=1; $i<=$rec->no_of_cartons; $i++){ 
              if($print_ctn%10 == 0){ 
                  if(($total_ctn-$print_ctn) >=10){ ?>
                    <table cellspacing="0" cellpadding="0" border="1" width="100%" style="border-color:#333; border-collapse: collapse;table-layout: fixed ;
                      padding-left:1px; padding-right:1px; ">
                      <tr style= "padding:1px">
                      <th style="width:10%; font-size:8px;  padding:1px; font-weight:50">{{$i." of ".$rec->no_of_cartons."  (".$size.")"}}</th>
                    <?php }else{ $width = (($total_ctn-$print_ctn)*10)."%" ?>  
                      <table cellspacing="0" cellpadding="0" border="1" width=<?php echo $width ?> style="border-color:#333; border-collapse: collapse;table-layout: fixed ;
                      padding-left:1px; padding-right:1px; ">
                      <tr style= "padding:1px">
                      <th style="width:10%; font-size:8px;  padding:1px; font-weight:50">{{$i." of ".$rec->no_of_cartons."  (".$size.")"}}</th>
                    <?php  }
                }else if($print_ctn%10 == 9){ ?>
                      <th style="width:10%; font-size:8px;  padding:1px; font-weight:50">{{$i." of ".$rec->no_of_cartons."  (".$size.")"}}</th>
                    </tr>
                  </table>
              <?php }else{ ?>
                    <th style="width:10%; font-size:8px; padding:1px; font-weight:50">{{$i." of ".$rec->no_of_cartons."  (".$size.")"}}</th>
              <?php } 
              $print_ctn++;           
              }
            } ?>
            <?php if($total_ctn%10 == 0){  ?>
              </table>
       <?php }} ?>
      

      <!-------------------------------------------   Carton Type ------------------------------------>
      

      <table cellspacing="0" cellpadding="0" border="1" width="40%" style="border-color:#333; border-collapse: collapse;table-layout: fixed ;
                padding-left:1px; padding-right:1px; margin-top : 15px;">
                <tr style= "padding:1px">
                  <th style="width:60%; font-size:8px;  padding:1px;">Dimension</th>
                  <th style="width:40%; font-size:8px;  padding:1px;">No of Carton</th>
                  
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
            $length = $rec->length;
            $width = $rec->width;
            $height = $rec->height;
            $no_of_carton += intval($rec->no_of_cartons);
        
         }} ?>
    
            <tr style= "padding:1px">
              <th style="width:60%; font-size:8px;  padding:1px; font-weight:50">{{$length." * ".$width." * ".$height." (".$carton_type.")"}}</th>
              <th style="width:40%; font-size:8px;  padding:1px; font-weight:50">{{$no_of_carton}}</th>
              
            </tr>
      <?php } ?>
        </table>

</body>
</html>