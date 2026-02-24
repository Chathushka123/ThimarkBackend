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
        $pdf->page_text(300, 820, "Page: {PAGE_NUM} of {PAGE_COUNT}", $font, 12, array(0,0,0));
    }
</script> 
  
  <table cellspacing="0" cellpadding="0" border="1" width="100%" style="border-color:#333; border-collapse: collapse;table-layout: fixed ;">
    <tr style = "font-size:11px;">
        <th style = "padding:5px; text-align:center; width: 38%; font-size:18px" colspan ="2"> Bundle Details Report </th>
        <th style = "padding:5px; text-align:center; width: 10% "> CUT NO </th>
        <th style = "padding:5px; text-align:center; width: 12% ">{{$header['cut_no']}}</th>
        <th style = "padding:5px; text-align:center; width: 10% "> STYLE</th>
        <th style = "padding:5px; text-align:center; width: 30% "> {{$header['style']}} </th>
    </tr>   
    <tr style = "font-size:11px;">
        <th style = "padding:5px; text-align:center; width: 19%; "> COLOR </th>
        <th style = "padding:5px; text-align:center; width: 19%; ">{{$header['color']}}</th>
        <!-- <th style = "padding:5px; text-align:center; width: 10% "> QTY </th>
        <th style = "padding:5px; text-align:center; width: 12% ">{{$header['qty']}}</th> -->
        <th style = "padding:5px; text-align:center; width: 10% "> SHADE</th>
        <th style = "padding:5px; text-align:center; width: 52% " colspan="3"> {{$header['shade']}} </th>
    </tr>  
    <tr style = "font-size:11px;">
        <th style = "padding:5px; text-align:center; width: 10%; "> MOTHER FPO </th>
        <th style = "padding:5px; text-align:center; width: 38%; " colspan="2">{{$header['mother_fpo']}}</th>
        <th style = "padding:5px; text-align:center; width: 10% "> TEAM </th>
        <th style = "padding:5px; text-align:center; width: 42% " colspan="2">{{$header['team']}}</th>>
    </tr> 
    <tr style = "font-size:11px;">
        <th style = "padding:5px; text-align:center; width: 10%; "> SOC </th>
        <th style = "padding:5px; text-align:center; width: 38%; " colspan="2">{{$header['soc']}}</th>
        <th style = "padding:5px; text-align:center; width: 10% "> FPO </th>
        <th style = "padding:5px; text-align:center; width: 42% " colspan="2">{{$header['fpo']}}</th>>
    </tr>  
  </table>

  <table cellspacing="0" cellpadding="0" border="1" width="100%" style="border-color:#333; border-collapse: collapse; margin-top:10px;">
    <tr style = "font-size:12px;">
      @foreach ($header['value_json'] as $key => $val)
        <?php if($val > 0){ ?>
          <th style = "padding:5px; text-align:center; width: 10%; "> {{$key}}</th>
          <?php } ?>
      @endforeach
        <th style = "padding:5px; text-align:center; width: 10%; "> Total</th>
        </tr>
        <tr style = "font-size:11px;">
      @foreach ($header['value_json'] as $key => $val)
        <?php if($val > 0){ ?>
          <th style = "padding:5px; text-align:center; width: 10%; "> {{$val}}</th>
          <?php } ?>
      @endforeach
      <th style = "padding:5px; text-align:center; width: 10%; "> {{$header['qty']}}</th>
    </tr>

    </table>

  <table cellspacing="0" cellpadding="0" border="1" width="100%" style="border-color:#333; border-collapse: collapse; margin-top:10px;">
    <tr style = "font-size:12px;">

      <th style = "padding:5px;"> Bundle ID </th>   
      <th style = "padding:5px;"> Fpo </th> 
      <th style = "padding:5px;"> Destination</th>     
      <th style = "padding:5px;"> Size </th>            
      <th style = "padding:5px;"> Shade </th>
      <th style = "padding:5px;"> NumSeq </th>
      <th style = "padding:5px;">Qty </th>
           
    </tr>
    
    <?php $pre_id =""; $print_id = ""; $start =0; $end = 0; $qty =0;?>
    @foreach ($bundles as $rec)
    <?php  if($pre_id != $rec->id){
              $print_id = $rec->id;
              $pre_id = $rec->id;
              $fpo = $rec->wfx_fpo_no;
              $destination = $rec->destination;

              $index = strpos($rec->number_sequence,"-")-1;
              $start = substr($rec->number_sequence,0,$index);
              $end = intval($start) + intval($rec->shade_qty) -1;

            }else{
                  $print_id = "";
                  $fpo = "";
                  $destination = "";
                  $start = $end+1;
                  $end = $start + intval($rec->shade_qty) -1;
            }
            if($qty == $header['shade_qty']){ 
              $qty =0;
              ?>
              <tr style = "font-size:11px; text-align:center">
                <td colspan = "7" style = "padding:2px; background-color:#000"></td> 
              </tr>
           <?php }   
            $qty +=$rec->shade_qty ?>
          <tr style = "font-size:11px; text-align:center">
            <td style = "padding:2px; text-align:center">{{$print_id}}</td> 
            <td style = "padding:2px; text-align:center">{{$fpo}}</td> 
            <td style = "padding:2px; text-align:center">{{$destination}}</td>       
            <td style = "padding:2px; text-align:center"> {{$rec->size}} </td>            
            <td style = "padding:2px; text-align:center"> {{$rec->shade}} </td>
            <td style = "padding:2px; text-align:center"> {{$start."  - ".$end}} </td>
            <td style = "padding:2px; text-align:center">{{$rec->shade_qty}} </td>
          </tr>
    @endforeach

  </table>

  <table cellspacing="0" cellpadding="0" border="1" width="100%" style="border-color:#333; border-collapse: collapse; margin-top:10px;">
    <tr style = "font-size:12px;">

      <th style = "padding:5px;"> LINE </th>   
      <th style = "padding:5px;"> ITEM </th> 
      <th style = "padding:5px;"> RECORDER</th>     
      <th style = "padding:5px;"> LODING </th>            
      <th style = "padding:5px;"> UNLODING </th>
      <th style = "padding:5px;"> RECORDER </th>
           
    </tr>

    <tr style = "font-size:9px; text-align:center">
      <td style = "padding:1px; text-align:center"></td> 
      <td style = "padding:1px; text-align:center">BONDING</td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
    </tr>

    <tr style = "font-size:9px; text-align:center">
      <td style = "padding:1px; text-align:center"></td> 
      <td style = "padding:1px; text-align:center">PIPING</td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
    </tr>

    <tr style = "font-size:9px; text-align:center">
      <td style = "padding:1px; text-align:center"></td> 
      <td style = "padding:1px; text-align:center">LABEL</td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
    </tr>

    <tr style = "font-size:9px; text-align:center">
      <td style = "padding:1px; text-align:center"></td> 
      <td style = "padding:1px; text-align:center">BACKING</td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
    </tr>

    <tr style = "font-size:9px; text-align:center">
      <td style = "padding:1px; text-align:center"></td> 
      <td style = "padding:1px; text-align:center">THREAD</td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
    </tr>

    <tr style = "font-size:9px; text-align:center">
      <td style = "padding:1px; text-align:center"></td> 
      <td style = "padding:1px; text-align:center">ELASTIC</td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
    </tr>

    <tr style = "font-size:9px; text-align:center">
      <td style = "padding:1px; text-align:center"></td> 
      <td style = "padding:1px; text-align:center">FUSING BOX</td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
    </tr>

    <tr style = "font-size:11px; text-align:center">
      <th colspan="6" style = "padding:5px; text-align:center; font-size:11px">RECUT FABRICS</th> 
    </tr>
    <tr style = "font-size:9px; text-align:center">
      <td style = "padding:1px; text-align:center"></td> 
      <td style = "padding:1px; text-align:center">BODY</td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
    </tr>
    <tr style = "font-size:9px; text-align:center">
      <td style = "padding:1px; text-align:center"></td> 
      <td style = "padding:1px; text-align:center">CONTRAST 1</td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
    </tr>
    <tr style = "font-size:9px; text-align:center">
      <td style = "padding:1px; text-align:center"></td> 
      <td style = "padding:1px; text-align:center">CONTRAST 2</td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
    </tr>
    <tr style = "font-size:9px; text-align:center">
      <td style = "padding:1px; text-align:center"></td> 
      <td style = "padding:1px; text-align:center">MESH 1</td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
    </tr>
    <tr style = "font-size:9px; text-align:center">
      <td style = "padding:1px; text-align:center"></td> 
      <td style = "padding:1px; text-align:center">MESH 2</td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
    </tr>
    <tr style = "font-size:9px; text-align:center">
      <td style = "padding:1px; text-align:center"></td> 
      <td style = "padding:1px; text-align:center">RIB</td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
    </tr>
  </table>


    
  <table cellspacing="0" cellpadding="0" border="1" width="100%" style="border-color:#333; border-collapse: collapse; margin-top:10px;">
    <tr style = "font-size:12px;">

      <th colspan ="6" style = "padding:5px;"> PIPING CONSUMPTION CHART</th>
           
    </tr>
    <tr style = "font-size:12px;">

      <th style = "padding:5px;"> SHADE </th>   
      <th style = "padding:5px;"> PCS PER JOINT </th> 
      <th style = "padding:5px;"> JOINT PER ROLL</th>     
      <th style = "padding:5px;"> PCS PER ROLL </th>            
      <th style = "padding:5px;"> TOTAL PCS </th>
      <th style = "padding:5px;"> ROLL REQUIRED </th>
           
    </tr>

    <tr style = "font-size:9px; text-align:center">
      <td style = "padding:7px; text-align:center"></td> 
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
    </tr>
    <tr style = "font-size:9px; text-align:center">
      <td style = "padding:7px; text-align:center"></td> 
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
    </tr>
    <tr style = "font-size:9px; text-align:center">
      <td style = "padding:7px; text-align:center"></td> 
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
    </tr>
    <tr style = "font-size:9px; text-align:center">
      <td style = "padding:7px; text-align:center"></td> 
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
    </tr>
    <tr style = "font-size:9px; text-align:center">
      <td style = "padding:7px; text-align:center"></td> 
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
    </tr>
    <tr style = "font-size:9px; text-align:center">
      <td style = "padding:7px; text-align:center"></td> 
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
      <td style = "padding:1px; text-align:center"></td>
    </tr>
  </table>


  <table cellspacing="0" cellpadding="0" border="0" width="100%" style="border-color:#333; border-collapse: collapse; margin-top:10px;">
  <tr style = "font-size:12px;">

    <th colspan ="4" style = "padding:5px; text-align:left"> Prepared By   ..............................</th>
    <th colspan ="1" style = "padding:5px;"></th>
    <th colspan ="1" style = "padding:5px;"></th>
    <th colspan ="1" style = "padding:5px;"></th>
    <th colspan ="1" style = "padding:5px;"></th>

  </tr>
  
    <tr style = "font-size:12px;">
      <th colspan ="4" style = "padding:5px; text-align:left"> Numbering By   ..............................</th>
      <th colspan ="1" style = "padding:5px;">Strips</th>
      <th colspan ="1" style = "padding:5px; border:1px solid black"></th>
      <th colspan ="1" style = "padding:5px;">Squre</th>
      <th colspan ="1" style = "padding:5px; border:1px solid black"></th>
    </tr>
    <tr style = "font-size:12px;">

      <th colspan ="4" style = "padding:5px; text-align:left"> Re Cut By  ..............................</th>
      <th colspan ="1" style = "padding:5px;">Fabric</th>
      <th colspan ="1" style = "padding:5px; border:1px solid black"></th>
      <th colspan ="1" style = "padding:5px;">Fusing</th>
      <th colspan ="1" style = "padding:5px; border:1px solid black"></th>
    </tr>

    <tr style = "font-size:12px;">

      <th colspan ="4" style = "padding:5px; text-align:left"> Bundling By   ..............................</th>
      <th colspan ="1" style = "padding:5px;">Signature</th>
      <th colspan ="1" style = "padding:5px; border:1px ">...............</th>
      <th colspan ="1" style = "padding:5px;"></th>
      <th colspan ="1" style = "padding:5px; "></th>
    </tr>
  </table>



  <!-- <table cellspacing="0" cellpadding="0" border="0" width="100%" style="border-color:#333; border-collapse: collapse; margin-top:20px;">
    <tr style = "font-size:12px;">


      <th style="width: 5%; font-size:8px; vertical-align:bottom;">
        <table cellspacing="0" cellpadding="" border="0" width="100%" style="border-color:#333; border-collapse: collapse; margin-top:20px;">
          <tr><th>---------------------------</th></tr>
          <tr><th>Recorder</th></tr>
        </table>
      </th>

      <th style="width: 5%; font-size:8px; vertical-align:bottom;">
        <table cellspacing="0" cellpadding="" border="0" width="100%" style="border-color:#333; border-collapse: collapse; margin-top:20px;">
          <tr><th>---------------------------</th></tr>
          <tr><th>Recorder</th></tr>
        </table>
      </th>

      <th style="width: 5%; font-size:8px; vertical-align:bottom;">
        <table cellspacing="0" cellpadding="" border="0" width="100%" style="border-color:#333; border-collapse: collapse; margin-top:20px;">
          <tr><th>---------------------------</th></tr>
          <tr><th>Recorder</th></tr>
        </table>
      </th>

    </tr>
    </table> -->

 

  

</body>
</html>