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

    .common {
        font-size : 8px;
        text-align:"left";
    }
    </style>
</head>
<body style="font-family: sans-serif;">

<script type="text/php">
    if ( isset($pdf) ) {
        $font = "";
        $pdf->page_text(260, 820, "Page: {PAGE_NUM} of {PAGE_COUNT}", $font, 10, array(0,0,0));
    }
</script> 

<?php $count = 0; ?>
    <table cellspacing="10" cellpadding="5" border="0" width="100%" style="border-color:#333; border-collapse: collapse;">

    <?php $style = ""; $soc = ""; ?>
        @foreach($cut_plans->fpo_cut_plans as $rec)
            
                {{$soc .=$rec->fpo->soc->wfx_soc_no }}
                {{$style =$rec->fpo->soc->style->style_code }}
            
        @endforeach
    
        @foreach($bundle as $rec)
           
            <?php 
                $remarks = $cut_plans->special_remark;

                if($rec->special_remarks != null && $rec->special_remarks != ""){
                    $remarks = $rec->special_remarks;
                }
            ?> 
                
                    @if($count == 0)
                        <tr>
                    @endif

                        <th>
                            <table cellspacing="10" cellpadding="5" border="1" width="100%" style="border-color:#333; border-collapse: collapse;">
                                <tr>
                                    <th colspan="1" class="common">Style</th>
                                    <th colspan="3" class="common" >{{$style}}</th>
                                </tr>
                                <tr>
                                    <th colspan="1" class="common">SOC</th>
                                    <th colspan="3" class="common" >{{$rec->wfx_soc_no}}</th>
                                    
                                </tr>
                                <tr>
                                    <th colspan="1" class="common">FPO</th>
                                    <th colspan="3" class="common" >{{$rec->wfx_fpo_no}}</th>
                                    
                                </tr>

                                <tr>
                                    <th colspan="1" class="common">FPPO</th>
                                    <th colspan="3" class="common" >{{$rec->fppo_no}}</th>
                                    
                                </tr>
                                <tr>
                                    <th class="common">Remarks</th>
                                    <th colspan="3" class="common" >{{$remarks}}</th>                                    
                                </tr>
                                <tr>
                                    <th class="common" >Color</th>
                                    <th colspan="3" class="common" >{{$rec->garment_color}}</th>                                    
                                </tr>
                                <tr>
                                    <th class="common">BID</th>
                                    <th class="common">{{$rec->id}}</th>
                                    <th colspan="2" class="common">{{ date('Y-m-d')}}</th>
                                </tr>
                                <tr>
                                    <th class="common">Cut No</th>
                                    <th class="common">{{$cut_plans->cut_no}}</th>
                                    <th class="common">Qty</th>
                                    <th class="common">{{$rec->quantity}}</th>
                                </tr>

                                <tr>
                                    <th class="common" style="width:20%">Size</th>
                                    <th class="common" style="width:20%">{{$rec->size}}</th>
                                    <th class="common" style="width:20%">Numbering </th>
                                    <th class="common" style="width:40%">{{$rec->number_sequence}}</th>
                                </tr>

                            </table>
                        </th>

                    {{$count++}}
                    @if($count == 3)
                        </tr>
                        {{$count=0}}
                    @endif
                

               
            
        @endforeach
    </table>
</body>
</html>