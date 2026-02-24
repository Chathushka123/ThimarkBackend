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
      font-size:32px;
    }

    .common {
        font-size : 8px;
        text-align:"left";
        padding-left: 5px;
    }

    .page-break {
        page-break-after:'always';
    }
    </style>
</head>
<body style="font-family: sans-serif; margin:0; padding:0;">

<?php $count = 0; $rows =0;?>
    <table cellspacing="0" cellpadding="0" border="1" width="100%" style="border-color:#333;  margin-top:27px; margin-left:-30px; margin-right:-6px; margin-bottom:-80px">

    <?php $style = ""; $soc = ""; ?>
        @foreach($cut_plans as $rec)

                {{$soc .=$rec->wfx_soc_no }}
                {{$style =$rec->style_code }}

        @endforeach

        @foreach($bundle as $rec)

            <?php
            //print_r($rows);
                $remarks = $cut_plans[0]->special_remark;

                if($rec->special_remarks != null && $rec->special_remarks != ""){
                    $remarks = $rec->shade;
                }
                $remarks = $rec->shade;
            ?>

                    <?php if($rows == 15){ ?>
                        </tr></table>
                            <pagebreak style="page-break-before: always;" pagebreak="true"></pagebreak>
                            <table cellspacing="0" cellpadding="0" border="1" width="100%" style="border-color:#333;  margin-top:27px; margin-left:-30px; margin-right:-6px; margin-bottom:-80px">
                            {{$count=0}}
                            {{$rows = 0}}
                    <?php } ?>

                    @if($count == 0)
                        <tr >
                    @endif



                     <?php $user = $rec['email'];
                        $rows++;
                        $index = strpos($user, '@');
                        $id = "0".$rec['id'];
                        $color = $rec['ColorName'];
                        $soc = $rec['wfx_soc_no'];
                        $order_type = $rec->order_type;

                        if(strlen($order_type) > 4){
                            $order_type = substr($order_type,0,4);
                        }

                        if($index > 10){
                            $user = substr($user,0,10);
                        }else{
                            $user = substr($user,0,$index);
                        }

                        if(strlen($color) > 28){
                            $color = substr($color,0,29);
                        }

                    ?>

                        <th width="33.36%" style = "height:143px; min-height:143px; max-height:143px; ">
                            <table cellspacing="1" cellpadding="1" border="0" width="100%"  style="border-color:#333; ">

                                <tr>
                                    <th colspan="2" class="common" style="padding-top: 5px">Shedule</th>
                                    <th colspan="5" class="common" style="padding-top: 5px">{{$shedule}}</th>

                                </tr>
                                <tr>
                                    <th colspan="2" class="common" style="padding-top: 5px">Style</th>
                                    <th colspan="5" class="common" style="padding-top: 5px">{{$style}}</th>

                                </tr>

                                <tr>
                                    <th colspan="2" class="common">SOC</th>
                                    <th colspan="5" class="common" >{{$soc}}</th>

                                </tr>

                                 <tr>
                                    <th colspan="2" class="common">FPO</th>
                                    <th colspan="3" class="common" >{{$rec->wfx_fpo_no}}</th>
                                    <th colspan="2" class="common" >{{  date('Y-m-d') }}</th>

                                </tr>
                               <tr>
                                    <th colspan="2" class="common" >Color</th>
                                    <th colspan="5" class="common" style="font-size:8px">{{$color}}</th>
                                </tr>



                               <tr>
                                    <th colspan="2" class="common">Cut Details</th>
                                    <th colspan="3" class="common" ><?php echo ($rec->combine_order_no." ".$rec->cut_no." (".$rec->fppo_no.")") ?></th>
                                    <th colspan="2" class="common" ><?php echo $order_type ?></th>


                                </tr>

                                <tr>
                                    <th colspan="2" class="common" >Remarks</th>
                                    <th colspan="5" class="common" style="font-size:8px">{{$remarks}}</th>
                                </tr>


                                  <tr>
                                    <th colspan="2" class="common" style="font-size : 10px">Size</th>
                                    <th colspan="2" class="common" style="font-size : 10px">{{$rec->size}}</th>
                                    <th colspan="1" class="common" style="font-size : 10px">Qty</th>
                                    <th colspan="2" class="common" style="font-size : 10px">{{$rec->quantity}}</th>
                                </tr>
                                <tr>
                                    <th  colspan="2" class="common" ><img
                                            src="data:image/png;base64, {!! base64_encode(QrCode::format('svg')->size(60)->generate($id)) !!} "></th>
                                    <th colspan="5" class="common" style="font-weight:50 "><p>*{{$id}}* </p></th>

                                </tr>
                                 <tr>
                                    <th  colspan="4" class="common" style="   font-weight:50; text-align:right; padding-bottom: 5px; font-size : 10px" >BID - <b>{{$id}}</b></th>
                                    <th colspan="3" class="common"style="text-align:right; padding-right:7px;; padding-bottom: 5px; font-size : 10px" >{{$rec->number_sequence}}</th>
                                </tr>

                            </table>
                        </th>

                    {{$count++}}
                    @if($count == 3)
                        </tr>
                        {{$count=0}}
                    @endif




        @endforeach
        <?php if($count != 0){
            for($i = $count; $i < 3; $i++){?>
                <th width="33.36%"></th>
        <?php  }} ?>
            </tr>
    </table>
</body>
</html>
