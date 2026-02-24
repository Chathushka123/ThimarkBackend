<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<head>
 <!-- <link href='https://support.idautomation.com/php-barcode/idautomation-code39.php?D=123456&CC=F&BH=30' rel='stylesheet'>  -->
  <!-- <link href='../sass/IDAUTOMATIONHC39M.TTF' rel='stylesheet'> -->
  <style>
       /* include the idautomation.com Code39 WOFF Font -- */
@font-face { 
font-family: IDAutomationHC39M;	 
src: url(https://www.yourdomain.com/woff-web-fonts/IDAutomationHC39M.woff);
}

    p {
      font-family: "Myriad Pro","Helvetica Neue",Helvetica,Arial,Sans-Serif;
      margin:0;
      padding:0;
      font-size:42px;
    }
    @page { margin: 8px,8px,8px,8px; }
    
    </style>
</head>
<body style="font-family: sans-serif;">
<?php $count = 0 ?>
<table cellspacing="10" cellpadding="5" border="1" width="100%" style="border-color:#333; border-collapse: collapse; margin:0; padding:0">
    @foreach($cartons as $rec)
       
        <tr style = "font-size:11px; ">
            <th >
                <table cellspacing="" cellpadding="0" border="0" width="100%" style="border-color:#333; border-collapse: collapse;max-height:500px; min-height:500px">

                    <tr >
                        <th colspan="4" style = "text-align:center; font-weight:50;"><p>*{{$rec->id}}*</p></th>                    
                    </tr>
                    <tr style = "font-size:11px;">
                        <th style = " text-align:center; font-weight:50">vpo : </th>  
                        <th colspan="2" style = " text-align:left; ">{{$packing_style->vpo}}</th>  
                        <th style = " text-align:center; ">{{$rec->id}}</th>                                          
                    </tr>
                    <tr style = "font-size:11px;">
                        <th colspan="2" style = " text-align:center; "></th>  
                        <th  style = " text-align:center; font-weight:50">Box No : </th>  
                        <th style = " text-align:center; ">{{$rec->carton_no2}}</th>                                          
                    </tr>


                    <!-- -----------------------------------  Get Required Data Field --------------- -->
                    <?php $color=""; $qty = json_decode($rec->qty_json); $size=""; $str_soc="" ?>
                    @foreach($soc as $soc_rec)
                        <?php $color=$soc_rec->pack_color; $str_soc .= $soc_rec->wfx_soc_no." "; ?>
                    @endforeach

                    @foreach($qty as $key=>$val)
                        @if(intval($val) > 0)
                            <?php $size .= $key."(".$val*$pack_ratio_sum.")"." "; ?>
                        @endif
                    @endforeach

                    <!-- ------------  Validate Soc Length ----------------------------->
                    <tr style = "font-size:11px; "> 
                        <th  style = " text-align:center; border-top:1px solid black; padding-top:5px;font-weight:50">Style : </th>  
                        <th colspan="3" style = " text-align:left; border-top:1px solid black">{{$packing_style->style_code}}</th>                                          
                    </tr>

                    <?php  $str_soc = substr($str_soc,0,40)  ?>

                    <tr style = "font-size:11px; "> 
                        <th  style = " text-align:center; font-weight:50">Color : </th>  
                        <th colspan="3" style = " text-align:left;">{{$color}}</th>                                          
                    </tr>
                    <tr style = "font-size:11px; "> 
                        <th  style = " text-align:center; font-weight:50">Size : </th>  
                        <th colspan="3" style = " text-align:left;">{{$size}}</th>                                          
                    </tr>
                    <tr style = "font-size:11px; "> 
                        <th  style = " text-align:center; font-weight:50">SOC : </th>  
                        <th colspan="3" style = " text-align:left;">{{$str_soc}}</th>                                          
                    </tr>
                
                </table>
            </th>
        </tr>

         
  @endforeach

  </table> 
</body>
</html>