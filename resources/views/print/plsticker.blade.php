<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<head>

  <!-- <link href='../sass/IDAUTOMATIONHC39M.TTF' rel='stylesheet'> -->
  <style>
    p {
      font-family: 'Libre Barcode 39';
      margin:0;
      padding:0;
      font-size:31px;
    }

    .common {
        font-size : 8px;
        text-align:"left";
        padding-left: 1px;
    }

    .page-break {
        page-break-after:'always';
    }
    </style>
</head>
<body style="font-family: sans-serif;">
<?php $count = 0; $rows =0; ?>
<table cellspacing="10" cellpadding="5" border="1" width="100%" style="border-color:#333; border-collapse: collapse;   margin-top:28px; margin-left:-33px; margin-right:-6px; margin-bottom:-80px">
    @foreach($cartons as $rec)

            <?php if($rows == 21){ ?>
            </tr></table>
                <pagebreak style="page-break-before: always;" pagebreak="true"></pagebreak>
                <table cellspacing="10" cellpadding="5" border="1" width="100%" style="border-color:#333; border-collapse: collapse;   margin-top:28px; margin-left:-33px; margin-right:-6px; margin-bottom:-80px">
                    {{$count=0}}
                {{$rows = 0}}
        <?php } ?>

        <?php $count++; $rows++;?>
        @if($count ==1)
        <tr style = "font-size:8px; ">
        @endif
        <th>
            <table cellspacing="" cellpadding="0" border="0" width="100%" style="border-color:#333; border-collapse: collapse;max-height:50px; min-height:50px; ">

                <tr >
                    <th colspan="3" style = "text-align:center; font-weight:50;"><p>*{{$rec->id}}*</p></th>
                    <th colspan="1" style = "text-align:center; font-weight:50;"><img
                            src="data:image/png;base64, {!! base64_encode(QrCode::format('svg')->size(40)->generate($rec->id)) !!} "></th>
                </tr>
                <tr style = "font-size:10px;">
                    <th style = " text-align:left; font-weight:50">VPO : </th>
                    <th colspan="2" style = " text-align:left; ">{{$packing_style->vpo}}</th>
                    <th style = " text-align:center; font-size:10px;">{{$rec->carton_no2}}</th>
                </tr>
                <tr style = "font-size:10px;">
                    <th colspan="1" style = " text-align:left; font-weight:50"><?php echo "PL ID: " ?></th>
					<th colspan="1" style = " text-align:left; "><?php echo $packing_style->pkId ?></th>
                    <th  style = " text-align:center; font-weight:50">Box No : </th>
                    <th style = " text-align:center; ">{{$rec->id}}</th>
                </tr>


                <!-- -----------------------------------  Get Required Data Field --------------- -->
                <?php $color=""; $qty = json_decode($rec->qty_json); $size=""; $str_soc=""; $str_fpo=""; $colorName = ""  ?>
                @foreach($soc as $soc_rec)
                    <?php $color=substr($soc_rec->pack_color,0,20); $colorName = $soc_rec->ColorName; $str_soc .= $soc_rec->wfx_soc_no." "; $str_fpo .= $soc_rec->wfx_fpo_no." "; ?>
                @endforeach

                @foreach($qty as $key=>$val)
                    @if(intval($val) > 0)
                        <?php $size .= $key."(".$val*$pack_ratio_sum.")"." "; ?>
                    @endif
                @endforeach

                <!-- ------------  Validate Soc Length ----------------------------->
                <tr style = "font-size:9.5px;">
                    <th  style = " text-align:left; border-top:1px solid black; font-weight:50">Style: </th>
                    <th colspan="3" style = " text-align:left; border-top:1px solid black">{{$packing_style->style_code}}</th>
                </tr>

                <?php  $str_soc = substr($str_soc,0,30)  ?>
                <?php  $colorName = substr($colorName,0,30)  ?>
                <?php  $str_fpo = substr($str_fpo,0,30)  ?>

                <tr style = "font-size:8px; ">
                    <th  style = " text-align:left; font-weight:50">Color: </th>
                    <th colspan="4"  style = " text-align:left;">{{$colorName}} ({{$color}})</th>
                </tr>

                <tr style = "font-size:10px; ">
                    <th  style = " text-align:left; font-weight:50">Size: </th>
                    <th colspan="4"  style = " text-align:left;">{{$size}}</th>
                </tr>

                <tr style = "font-size:10px; ">
                    <th  style = " text-align:left; font-weight:50">SOC: </th>
                    <th colspan="4" style = " text-align:left;">{{$str_soc}} / {{$str_fpo}}</th>
                </tr>
                <tr style = "font-size:8px; ">
                    <th  style = " text-align:left; font-weight:50">Des: </th>
                    <th colspan="4" style = " text-align:left;">{{$packing_style->destination}}</th>
                </tr>
                <tr style = "font-size:9.57px; ">
                    <th  style = " text-align:left; font-weight:50">Remark: </th>
                    <th colspan="4" style = " text-align:left;">{{$rec->customer_size_code}}</th>
                </tr>

            </table>
        </th>

        @if($count ==3)
            <?php $count =0 ?>
            </tr>
        @endif

  @endforeach

  @if($count > 0)
    @for($i =$count; $count < 3; $count++)
        <th style="width:33.3333%"></th>
    @endfor
    </tr>
  @endif
  </table>
</body>
</html>
