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
      font-size:36px;
    }

    .common {
        font-size : 12px;
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
    <h3 style="text-align:center">Team Report</h3>
<?php $count = 0; ?>
<?php $first = true; ?>
    <table cellspacing="10" cellpadding="5" border="1" width="100%" style="border-color:#333; border-collapse: collapse; margin-top:20px;">
        

        @foreach($team as $rec)
            @if( $count == 0)
                <tr>
                
            @endif
            @if($first)
                <?php $first =false; ?>
            @endif

            <th>
                <table cellspacing="10" cellpadding="5" border="0" width="100%" style="border-color:#333; border-collapse: collapse;">
                <tr>
                    <th colspan="2" style="   font-weight:50; padding:2px"><p id="No">*{{$rec->id}}*</p></th>
                    
                </tr>
                <tr>
                    <th class="common">{{$rec->code}}</th>
                    <th class="common" style="text-align:right">{{$rec->id}}</th>
                </tr>
                </table>
            </th>

            {{$count++}}
            @if($count == 4)
                </tr>
                <?php $count = 0; ?>
            @endif
        @endforeach
        
        @if($count < 3)
            @for($i = $count; $i < 4; $i++)
                <th></th>
            @endfor
            </tr>
        @endif
        
    </table>
</body>
</html>