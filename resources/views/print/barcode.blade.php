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
      font-size:48px;
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

    
   

        @foreach($bundle as $rec)

         

                    <?php if($rows == 18){ ?>
                        </tr></table>
                            <pagebreak style="page-break-before: always;" pagebreak="true"></pagebreak>
                            <table cellspacing="0" cellpadding="0" border="1" width="100%" style="border-color:#333;  margin-top:27px; margin-left:-30px; margin-right:-6px; margin-bottom:-80px">
                            {{$count=0}}
                            {{$rows = 0}}
                    <?php } ?>

                    @if($count == 0)
                        <tr >
                    @endif



                     <?php 
                        $rows++;
                        
                        $id = $rec->id;
                      

                   

                    ?>

                        <th width="33.36%" style = "height:143px; min-height:143px; max-height:143px; ">
                            <table cellspacing="1" cellpadding="1" border="0" width="100%"  style="border-color:#333; ">
               

                               

                            



                             

                                


                                
                                <tr>
                                    <th  colspan="2" class="common" ><img
                                            src="data:image/png;base64, {!! base64_encode(QrCode::format('svg')->size(60)->generate($id)) !!} "></th>
                                    <th colspan="5" class="common" style="font-weight:50 "><p>*{{$id}}* </p></th>

                                </tr>
                                 <!-- <tr>
                                    <th  colspan="7" class="common" style="   font-weight:50; text-align:center; padding-bottom: 5px; font-size : 10px" ><b>{{$id}}</b></th>
                                </tr> -->
                                <tr>
                                    <th  colspan="7" class="common" style="   font-weight:50; text-align:center; padding-bottom: 5px; font-size : 14px" ><b>{{$rec->location_name}}</b></th>
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
