<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

<head>
    <style>
        * {
            margin: 0;
            padding: 0;
        }

        body {
            font-family: sans-serif;
            margin: 6px;
        }

        .barcode-img {
            max-width: 100%;
            height: 50px;
        }

        .outer-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 5px 5px;
        }

        .sticker-td {
            width: 50%;
            max-width: 50%;
            vertical-align: top;
        }

        .sticker {
            border: 2px solid #000;
            width: 100%;
            border-collapse: collapse;
        }

        .top-info {
            vertical-align: middle;
            padding: 5px 8px;
            width: 75%;
            max-width: 75%;
            border-right: 1px solid #555;
            height: 55px;
        }

        .top-qr {
            vertical-align: middle;
            text-align: center;
            width: 25%;
            height: 55px;
            padding: 3px;
        }

        .bottom-barcode {
            text-align: center;
            border-top: 2px solid #000;
            padding: 3px 3px 2px;
            height: 50px;
        }

        .rack-text {
            font-size: 12px;
            color: #000427;
            margin-bottom: 3px;
            font-weight: bold;
        }

        .bin-text {
            font-size: 18px;
            font-weight: bold;
        }

        .bin-human-text {
            font-size: 12px;
            font-weight: bold;
            margin-top: 3px;
            text-align: center;
        }
    </style>
</head>

<body>
    @php $colCount = 0; $stickerCount = 0; $prevRack = null; @endphp
    <table class="outer-table" cellspacing="5" cellpadding="0">
        @foreach($locations as $location)
        @php
        $rackChanged = $prevRack !== null && ($location->rack ?? '') !== $prevRack;
        $prevRack = $location->rack ?? '';
        @endphp
        @if($rackChanged)
        @if($colCount == 1)
        <td class="sticker-td"></td>
        </tr>
        @endif
    </table>
    <div style="page-break-before: always;"></div>
    <table class="outer-table" cellspacing="5" cellpadding="0">
        @php $colCount = 0; $stickerCount = 0; @endphp
        @endif
        @php $colCount++; $stickerCount++; @endphp
        @if($colCount == 1)
        <tr>
            @endif

            <td class="sticker-td">
                <table class="sticker" cellspacing="0" cellpadding="0">
                    <tr>
                        <td class="top-info">
                            <div class="rack-text">{{ $location->rack ?? '' }}</div>
                            <div class="bin-text">{{ $location->bin ?? '' }}@if($location->stockMaterial) | {{ $location->stockMaterial->code }} | {{ \Illuminate\Support\Str::limit($location->stockMaterial->name, 15, '...') }}@endif</div>
                        </td>
                        <td class="top-qr">
                            {{-- {!! QrCode::format('svg')->size(65)->generate($location->bin ?? '') !!} --}}
                            <img src="data:image/png;base64, {!! base64_encode(QrCode::format('svg')->size(45)->generate($location->id ?? '')) !!} ">

                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" class="bottom-barcode">
                            @php
                            $generator = new \Picqer\Barcode\BarcodeGeneratorPNG();
                            $barcodePng = base64_encode($generator->getBarcode($location->id ?? '', $generator::TYPE_CODE_39, 2, 38));
                            @endphp
                            <img class="barcode-img" src="data:image/png;base64,{{ $barcodePng }}">
                            <div class="bin-human-text">{{ $location->id ?? '' }}</div>
                        </td>
                    </tr>
                </table>
            </td>

            @if($colCount == 2)
        </tr>
        @php $colCount = 0; @endphp
        @endif

        @if($stickerCount == 12 && !$loop->last)
        @if($colCount == 1)
        <td class="sticker-td"></td>
        </tr>
        @endif
    </table>
    <pagebreak style="page-break-before: always;" pagebreak="true"></pagebreak>
    <table class="outer-table" cellspacing="5" cellpadding="0">
        @php $colCount = 0; $stickerCount = 0; $prevRack = null; @endphp
        @endif
        @endforeach
        @if($colCount == 1)
        <td class="sticker-td"></td>
        </tr>
        @elseif($colCount == 0 && $stickerCount > 0)
        {{-- even count, last </tr> already closed --}}
        @endif
    </table>
</body>

</html>