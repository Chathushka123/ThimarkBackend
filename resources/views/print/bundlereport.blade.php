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
      padding:5px;
      font-size:30px;
    }
    </style>
</head>
<body style="font-family: sans-serif;">
  
<?php $bundle_id=0; $pre_bundle_id =0;  $fpo="";$soc=""; $count=0;?>

  <table cellspacing="0" cellpadding="0" border="1" width="100%" style="border-color:#333; border-collapse: collapse;table-layout: fixed ;
  width: 100% ;">

    @foreach ($bundle as $rec)
    <?php $bundle_id = $rec->id; ?>

    @if($bundle_id==0 || $bundle_id != $pre_bundle_id)  
        @foreach ($fpo_soc as $rec1)  
            <?php $fpo=$rec1->wfx_fpo_no; $soc= $rec1->wfx_soc_no; $color_name = $rec1->ColorName; ?>
        @if($bundle_id !=0 )                        
            </tr> <!-- Close Previoue Row -->
            {{$count =0}}
        @endif

        @if(strlen($color_name) > 30)
        <?php $color_name =substr($color_name,0,30) ?>
        @endif
        <!-- --------------------------------  Header Detyails ----------------------------------- -->
        <tr>
            <th style="padding:5px; border: 1px solid black;pheight:60px;" >
                <table cellspacing="0" cellpadding="0" border="0" width="100%" style="border-color:#333; border-collapse: collapse;">
                    <tr style = "font-size:8px; ">
                        <th style = "text-align:left; width:40%" > Color Code  :   </th>
                        <th style="font-weight:50;text-align:left;width:60%">{{$rec1->garment_color}}<th>
                    </tr>
                    <tr style = "font-size:8px;">
                        <th style = "text-align:left"> Color Name : </th>
                        <th style="font-weight:50;text-align:left;width:60%">{{$color_name}} <th>
                    </tr>
                    <tr style = "font-size:8px;">
                        <th style = "text-align:left"> Size Fit : </th>
                        <th style="font-weight:50;text-align:left;width:60%">{{$rec->size}}<th>
                    </tr>
                    <tr style = "font-size:8px;">
                        <th style = "text-align:left"> Job card : </th>
                        <th style="font-weight:50;text-align:left;width:60%">{{$job_card}}<th>
                    </tr>
                </table>
            </th>
            <th style="padding:5px; border: 1px solid black;height:60px;">
                <table cellspacing="0" cellpadding="0" border="0" width="100%" style="border-color:#333; border-collapse: collapse;">
                    <tr style = "font-size:8px;">
                        <th style = " text-align:left; width:40%"> O/No :  </th>
                        <th style="font-weight:50;text-align:left;width:60%">{{$rec1->wfx_soc_no}}<th>
                    </tr>
                    <tr style = "font-size:8px;">
                        <th style = "text-align:left"> PO :  </th>                    
                    </tr>
                    <tr style = "font-size:8px;">
                        <th style = "text-align:left"> PO SRL :</th>
                    </tr>
                </table>
            </th >
            <th style="padding:5px ; border: 1px solid black;height:60px;">
                <table cellspacing="0" cellpadding="0" border="0" width="100%" style="border-color:#333; border-collapse: collapse;">
                    <tr style = "font-size:10px; ">
                        <th style = "text-align:center; width:100%" colspan="2"> Bundle Details     </th>
                        
                    </tr>
                    <tr style = "font-size:8px;">
                        <th style = "text-align:left"> No : </th>
                    </tr>
                    <tr style = "font-size:8px;">
                        <th style = "text-align:left; width:40%"> Qty : </th>
                        <th style="font-weight:50;text-align:left;width:60%">{{$rec->quantity}}<th>
                    </tr>
                    <tr style = "font-size:8px;">
                        <th style = "text-align:left; width:40%"> ID : </th>
                        <th style="font-weight:50;text-align:left;width:60%">{{$bundle_id}}<th>
                    </tr>
                </table>
            </th>
            <th style="padding:5px; border: 1px solid black;height:60px;">
                <table cellspacing="0" cellpadding="0" border="0" width="100%" style="border-color:#333; border-collapse: collapse;">
                    <tr style = "font-size:10px; ">
                        <th style = "text-align:center; width:100%" colspan="2"> Special Instructions</th>
                        
                    </tr>
                    <tr style = "font-size:8px;">
                        <th style = "text-align:center; width:100%" colspan="2">.</th>
                    </tr>
                    <tr style = "font-size:8px;">
                        <th style = "text-align:center; width:100%" colspan="2">.</th>
                    </tr>
                    <tr style = "font-size:8px;">
                        <th style = "text-align:left; width:60%"> Number Sequence : </th>
                        <th style="font-weight:50;text-align:left;width:40%">{{$rec->number_sequence}}<th>
                    </tr>
            
                </table>
            </th>

        </tr>
        <!-- ------------------------------------------------------------------------------------- -->
                
        @endforeach
        <tr>
            
    @endif

    <?php $op = substr($rec->operation_code,0,2) ?>
    <!------------------------ Reduce Unwanted Sticker  ------------------------------------------>
    @if(substr($op,0,2) =="SP" || (substr($op,0,2) =="SW" && $rec->direction == "IN")|| (substr($op,0,1)  =="P" && $rec->direction == "IN"))
        {{$count++}}
        @if($count <=4)
            <th style="padding:5px; border: 1px solid black; height:60px; ">
                <table cellspacing="0" cellpadding="0" border="0" width="100%" style="border-color:#333; border-collapse: collapse;max-height:500px; min-height:500px">
                    <tr style = "font-size:8px;">
                        <th style = " text-align:left; width:20%">{{$op}}</th>
                        <th style="font-weight:50;text-align:left; width:30%">{{$rec->size}}<th>
                        <th style = " text-align:left; width:30%">{{$rec->quantity}}</th>
                        <th style="font-weight:50;text-align:center; width:20%">{{$rec->direction}}<th>                        
                    </tr>
                    <tr style = "font-size:8px;">
                        <th colspan="4" style = "text-align:center; font-weight:50;"><p>*{{$rec->ticket_id}}*</p></th>  
							
                    </tr>
					<tr style = "font-size:8px;">
                        <th colspan="4" style = "text-align:center;font-weight:50">{{$rec->ticket_id}}</th>
                    </tr>
                    <tr style = "font-size:8px;">
                        <th colspan="4" style = "text-align:center;font-weight:50">{{$soc}} / {{$fpo}}</th>
                    </tr>
                </table>
            </th >
        @endif

        <!-- If Operation are > 4 then add new row -->
        @if($count >4)
        {{$count=1}}
        <tr>
            <th style="padding:5px; border: 1px solid black; height:60px; ">
                    <table cellspacing="0" cellpadding="0" border="0" width="100%" style="border-color:#333; border-collapse: collapse;max-height:500px; min-height:500px">
                        <tr style = "font-size:8px;">
                            <th style = " text-align:left; width:20%">{{$op}}</th>
                            <th style="font-weight:50;text-align:left; width:30%">{{$rec->size}}<th>
                            <th style = " text-align:left; width:30%">{{$rec->quantity}}</th>
                            <th style="font-weight:50;text-align:center; width:20%">{{$rec->direction}}<th>                        
                        </tr>
                        <tr style = "font-size:8px;">
                            <th colspan="4" style = "text-align:center; font-weight:50;"><p>*{{$rec->ticket_id}}*</p></th>                    
                        </tr>
						<tr style = "font-size:8px;">
							<th colspan="4" style = "text-align:center;font-weight:50">{{$rec->ticket_id}}</th>
						</tr>
                        <tr style = "font-size:8px;">
                            <th colspan="4" style = "text-align:center;font-weight:50">{{$soc}} / {{$fpo}}</th>
                        </tr>
                    </table>
                </th >
        @endif

        @if($op =="PK" && $rec->direction == "IN")
            @for($i = $count; $i < 4; $i++)
                <th></th>
            @endfor
        @endif
    @endif
    <?php $pre_bundle_id = $bundle_id; ?>
    @endforeach
    
    </tr>
</table>  

</body>
</html>