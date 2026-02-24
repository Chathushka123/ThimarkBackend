<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

<head>
    <!-- <link href='https://fonts.googleapis.com/css?family=Libre Barcode 39' rel='stylesheet'> -->
    <!-- <link href='../sass/IDAUTOMATIONHC39M.TTF' rel='stylesheet'> -->
    <style>
        /* p {
            font-family: 'Libre Barcode 39';
            margin: 0;
            padding: 0;
            font-size: 36px;
        } */

        .common {
            font-size: 12px;
            text-align: "left";
        }

        .arrow-text {
            font-size: 16px;
            font-weight: 600;
            width: 100%;
            /* color: #2196f3; */
            position: relative;
            display: inline-block;
            text-align: center;
            margin-top: 0;
            padding: 15px 0;
            color: #fff;
            background-color: #2196f3;
            border-radius: 20px;
        }

        .arrow-text::before {
            content: '◀';
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 20px;
            color: rgba(255, 255, 255, 0.8);
        }

        .arrow-text::after {
            content: '▶';
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 20px;
            color: rgba(255, 255, 255, 0.8);
        }

        .header-container {
            width: 100%;
            margin-top: 10px;
            margin-bottom: 10px;
        }

        .header-table {
            width: 100%;
            border: none;
        }

        .logo {
            max-height: 60px;
            max-width: 100px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(33, 150, 243, 0.2);
        }

        .company-info {
            text-align: center;
        }

        .address-info {
            width: 100%;
            margin-top: 10px;
            margin-bottom: 20px;
            clear: both;
            height: auto;
            display: block;
        }

        .address-left {
            display: inline-block;
            width: 45%;
            text-align: left;
            color: #4682B4;
            font-size: 12px;
            font-weight: 600;
            vertical-align: top;
            margin: 0;
            padding: 5px;
        }

        .address-right {
            display: inline-block;
            width: 45%;
            text-align: right;
            color: #4682B4;
            font-size: 12px;
            font-weight: 600;
            vertical-align: top;
            margin: 0;
            padding: 5px;
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

    <div class="header-container">
        <table class="header-table">
            <tr>
                <td style="width: 20%; text-align: left; vertical-align: middle; border: none;">
                    @php
                    $logoPath = base_path('resources/views/print/logo.png');
                    $logoExists = file_exists($logoPath);
                    $logoBase64 = $logoExists ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : '';
                    @endphp
                    @if($logoExists)
                    <img src="{{ $logoBase64 }}" alt="SQK Printers Logo" class="logo">
                    @else
                    <div style="width: 100px; height: 60px; background: #e3f2fd; border: 2px solid #2196f3; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #2196f3; font-size: 10px; text-align: center;">
                        LOGO<br>PLACEHOLDER
                    </div>
                    @endif
                </td>
                <td style="width: 80%; text-align: center; vertical-align: middle; border: none;">
                    <div class="company-info">
                        <h1 style="text-align:center; margin: 0; color:#2196f3; font-size: 28px;">SQK Printers</h1>
                        <p style="text-align:center; margin: 5px 0; color: #4682B4; font-size: 12px;">Offset & Screen Printing, Die Cutting, Emboss / Foiling</p>
                    </div>
                </td>
            </tr>
        </table>
    </div>
    <p class="arrow-text"> Specialist in Wedding Cards, Cake Boxes, Laser Cut Wedding Cards</p>

    <table width="100%" style="margin-top: 0; margin-bottom: 5px; padding-bottom: 10px; border: none; border-bottom: 2px solid #4682B4;">
        <tr>
            <td style="text-align: left; color: #4682B4; font-size: 14px; font-weight: 600; width: 50%; padding: 1px; border: none;">
                No. 04, Maliban Street, Colombo - 11.
            </td>
            <td style="text-align: right; color: #4682B4; font-size: 14px; font-weight: 600; width: 50%; padding: 1px; border: none;">
                Tel: 0727534144
            </td>
        </tr>
        <tr>
            <td style="text-align: left; color: #4682B4; font-size: 14px; font-weight: 600; width: 50%; padding: 1px; border: none;">
                Email: sqkprinters@gmail.com
            </td>
            <!-- <td style="text-align: right; color: #4682B4; font-size: 12px; font-weight: 600; width: 50%; padding: 1px; border: none;">
                Tel: 0773043877
            </td> -->
        </tr>
        <tr>
            <td style="text-align: left; color: #ff0000; font-size: 16px; font-weight: 600; width: 50%; padding: 1px; border: none;">
                BILL NO : {{$invoice->id}}
            </td>
            <td style="text-align: right; color: #4682B4; font-size: 12px; font-weight: 600; width: 50%; padding: 1px; border: none;">

            </td>
        </tr>
    </table>
    <table width="100%" style="margin-top: 0; margin-bottom: 5px; border: none; ">
        <tr>
            <td style="text-align: left; color: #000; font-size: 12px; font-weight: 600; width: 30%; padding: 1px; border: none; font-size : 14px">
                Name : {{$invoice->name}}
            </td>
            <td style="text-align: right; color: #000; font-size: 12px; font-weight: 600; width: 30%; padding: 1px; border: none; font-size : 14px">
                Tel : {{$invoice->mobile}}
            </td>
            <td style="text-align: right; color: #000; font-size: 12px; font-weight: 600; width: 40%; padding: 1px; border: none; font-size : 14px">
                Date : {{$invoice->invoice_date}}
            </td>
        </tr>
    </table>
    <?php $count = 0; ?>
    <?php $first = true; ?>
    <table cellspacing="0" cellpadding="5" width="100%" style="border-collapse: collapse; margin-top:20px;">
        <tr style="background-color: #2196f3; color: #fff;">
            <th class="common" style="text-align:center; border: 1px solid #333; padding: 8px;">Qty</th>
            <th class="common" style="text-align:center; border: 1px solid #333; padding: 8px;">Description</th>
            <th class="common" style="text-align:center; border: 1px solid #333; padding: 8px;">Amount</th>
        </tr>
        @foreach($invoiceDetails as $rec)
        <tr style="border-left: 2px solid #333; border-right: 2px solid #333;">
            <td class="common" style="text-align:left; border-left: 2px solid #333; border-right: 1px solid #333; border-top: 0; border-bottom: 0; padding: 8px; background-color: #f9f9f9;"><?php echo intval($rec['quantity']); ?></td>
            <td class="common" style="text-align:left; border-left: 0; border-right: 1px solid #333; border-top: 0; border-bottom: 0; padding: 8px; background-color: #f9f9f9;"><?php echo $rec['description']; ?></td>
            <td class="common" style="text-align:right; border-left: 0; border-right: 2px solid #333; border-top: 0; border-bottom: 0; padding: 8px; background-color: #f9f9f9;"><?php echo $rec['total_price']; ?></td>
        </tr>
        @endforeach
        <tr>
            <td class="common" colspan="2" style="text-align:right; font-weight:700;color:#4682B4; border: 1px solid #333; padding: 8px;">Total</td>
            <td class="common" style="text-align:right; font-weight:700;color:#4682B4; border: 1px solid #333; padding: 8px;">{{$totalAmount}}</td>
        </tr>
        <tr>
            <td class="common" colspan="2" style="text-align:right; font-weight:700;color:#4682B4; border: 1px solid #333; padding: 8px;">Paid</td>
            <td class="common" style="text-align:right; font-weight:700;color:#4682B4; border: 1px solid #333; padding: 8px;">{{$advance}}</td>
        </tr>
        <tr>
            <td class="common" colspan="2" style="text-align:right; font-weight:700;color:#4682B4; border: 1px solid #333; padding: 8px;">Balance</td>
            <td class="common" style="text-align:right; font-weight:700;color:#4682B4; border: 1px solid #333; padding: 8px;">{{$totalAmount - $advance}}</td>
        </tr>
        <!-- <tr>
            <td class="common" colspan="2" style="text-align:right; font-weight:700;color:#4682B4">Due</td>
            <td class="common" style="text-align:right; font-weight:700;color:#4682B4">{{$invoice->due_date}}</td>
        </tr> -->
    </table>
</body>

</html>