<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<head>
<style>
    * { margin: 0; padding: 0; }
    body { font-family: sans-serif; margin: 6px; }

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
        vertical-align: top;
    }

    .sticker {
        border: 2px solid #000;
        width: 100%;
        border-collapse: collapse;
    }

    .top-info {
        vertical-align: middle;
        padding: 8px 10px;
        width: 58%;
        border-right: 1px solid #555;
        height: 75px;
    }

    .top-qr {
        vertical-align: middle;
        text-align: center;
        width: 42%;
        height: 75px;
        padding: 4px;
    }

    .bottom-barcode {
        text-align: center;
        border-top: 2px solid #000;
        padding: 4px 3px 2px;
        height: 68px;
    }

    .rack-text {
        font-size: 9px;
        color: #555;
        margin-bottom: 5px;
    }

    .bin-text {
        font-size: 24px;
        font-weight: bold;
    }

    .bin-human-text {
        font-size: 9px;
        margin-top: 3px;
        text-align: center;
    }
</style>
</head>
<body>
@php $colCount = 0; $stickerCount = 0; @endphp
<table class="outer-table" cellspacing="5" cellpadding="0">
@foreach($locations as $location)
    @php $colCount++; $stickerCount++; @endphp
    @if($colCount == 1)
    <tr>
    @endif

    <td class="sticker-td">
        <table class="sticker" cellspacing="0" cellpadding="0">
            <tr>
                <td class="top-info">
                    <div class="rack-text">{{ $location->rack ?? '' }}</div>
                    <div class="bin-text">{{ $location->bin ?? '' }}</div>
                </td>
                <td class="top-qr">
                    {{-- {!! QrCode::format('svg')->size(65)->generate($location->bin ?? '') !!} --}}
                   <img src="data:image/png;base64, {!! base64_encode(QrCode::format('svg')->size(60)->generate($location->bin ?? '')) !!} ">

                </td>
            </tr>
            <tr>
                <td colspan="2" class="bottom-barcode">
                    @php
                        $generator = new \Picqer\Barcode\BarcodeGeneratorPNG();
                        $barcodePng = base64_encode($generator->getBarcode($location->bin ?? '', $generator::TYPE_CODE_39, 2, 50));
                    @endphp
                    <img class="barcode-img" src="data:image/png;base64,{{ $barcodePng }}">
                    <div class="bin-human-text">{{ $location->bin ?? '' }}</div>
                </td>
            </tr>
        </table>
    </td>

    @if($colCount == 2)
        </tr>
        @php $colCount = 0; @endphp
    @endif

    @if($stickerCount == 8 && !$loop->last)
        @if($colCount == 1)
        <td class="sticker-td"></td></tr>
        @endif
        </table>
        <pagebreak style="page-break-before: always;" pagebreak="true"></pagebreak>
        <table class="outer-table" cellspacing="5" cellpadding="0">
        @php $colCount = 0; $stickerCount = 0; @endphp
    @endif
@endforeach
@if($colCount == 1)
    <td class="sticker-td"></td></tr>
@elseif($colCount == 0 && $stickerCount > 0)
    {{-- even count, last </tr> already closed --}}
@endif
</table>
</body>
</html>
