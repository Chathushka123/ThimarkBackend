<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MRN - Material Request Note</title>
    <style>
        @page {
            margin: 10mm 10mm;
            size: A4 landscape;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            font-size: 11px;
            color: #333;
            line-height: 1.4;
            padding: 20px;
        }

        .container {
            width: 100%;
            margin: 0 auto;
        }

        /* Header Section */
        .header {
            border-bottom: 3px solid #2c3e50;
            padding-bottom: 15px;
            margin-bottom: 20px;
            position: relative;
        }

        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
            text-align: center;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .document-title {
            font-size: 16px;
            color: #7f8c8d;
            text-align: center;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .barcode-container {
            position: absolute;
            right: 0;
            top: 0;
            text-align: right;
        }

        .barcode-container img {
            height: 50px;
            margin-bottom: 3px;
        }

        .barcode-label {
            font-size: 10px;
            color: #7f8c8d;
            font-weight: 600;
        }

        /* Info Section */
        .info-section {
            display: table;
            width: 100%;
            margin-bottom: 20px;
            border: 1px solid #ddd;
            background-color: #f8f9fa;
        }

        .info-row {
            display: table-row;
        }

        .info-label {
            display: table-cell;
            padding: 8px 12px;
            font-weight: bold;
            width: 25%;
            background-color: #ecf0f1;
            border-right: 1px solid #ddd;
            border-bottom: 1px solid #ddd;
        }

        .info-value {
            display: table-cell;
            padding: 8px 12px;
            width: 25%;
            border-right: 1px solid #ddd;
            border-bottom: 1px solid #ddd;
        }

        /* Table Section */
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .details-table thead {
            background-color: #34495e;
            color: white;
        }

        .details-table th {
            padding: 10px;
            text-align: left;
            font-weight: 600;
            border: 1px solid #2c3e50;
        }

        .details-table tbody tr {
            border-bottom: 1px solid #ddd;
        }

        .details-table tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        .details-table tbody tr:hover {
            background-color: #e9ecef;
        }

        .details-table td {
            padding: 8px 10px;
            border: 1px solid #ddd;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        /* Total Section */
        .total-row {
            background-color: #2c3e50 !important;
            color: white !important;
            font-weight: bold;
            font-size: 13px;
        }

        .total-row td {
            border: 1px solid #2c3e50 !important;
        }

        /* Footer */
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 2px solid #ddd;
            font-size: 10px;
            color: #7f8c8d;
            text-align: center;
        }

        /* Page Break */
        .page-break {
            page-break-after: always;
        }

        /* Additional Info */
        .meta-info {
            font-size: 10px;
            color: #7f8c8d;
            margin-top: 20px;
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="barcode-container">
                <img src="data:image/png;base64,{{ $barcode }}" alt="Barcode">
                <div class="barcode-label">MRN #{{ $mrn->id }}</div>
            </div>
            <div class="company-name">Thimark Technocreations</div>
            <div class="document-title">Material Request Note (MRN)</div>
        </div>

        <!-- MRN Information -->
        <div class="info-section">
            <div class="info-row">
                <div class="info-label">MRN ID:</div>
                <div class="info-value">{{ $mrn->id }}</div>
                <div class="info-label">Batch:</div>
                <div class="info-value">{{ $mrn->batch->model->name ?? 'N/A' }} - {{ $mrn->batch->batch_no ?? 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Warehouse:</div>
                <div class="info-value">{{ $mrn->warehouse->name ?? 'N/A' }}</div>
                <div class="info-label">Status:</div>
                <div class="info-value">{{ strtoupper($mrn->status) }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Created Date:</div>
                <div class="info-value">{{ date('d-M-Y', strtotime($mrn->created_at)) }}</div>
                <div class="info-label">Created Time:</div>
                <div class="info-value">{{ date('h:i A', strtotime($mrn->created_at)) }}</div>
            </div>
        </div>

        <!-- Material Details Table -->
        <table class="details-table">
            <thead>
                <tr>
                    <th class="text-center" style="width: 8%;">No.</th>
                    <th style="width: 20%;">Item Code</th>
                    <th style="width: 52%;">Material Name</th>
                    <th class="text-center" style="width: 20%;">Quantity</th>
                </tr>
            </thead>
            <tbody>
                @php
                $totalQty = 0;
                $serialNo = 1;
                @endphp

                @foreach($mrn->details as $detail)
                @php
                $totalQty += $detail->qty;
                @endphp
                <tr>
                    <td class="text-center">{{ $serialNo++ }}</td>
                    <td>{{ $detail->stockItem->code ?? 'N/A' }}</td>
                    <td>{{ $detail->stockItem->name ?? 'N/A' }}</td>
                    <td class="text-right">{{ number_format($detail->qty, 2) }}</td>
                </tr>
                @endforeach

                <!-- Total Row -->
                <tr class="total-row">
                    <td colspan="3" class="text-right">TOTAL QUANTITY:</td>
                    <td class="text-right">{{ number_format($totalQty, 2) }}</td>
                </tr>
            </tbody>
        </table>

        <!-- Additional Information -->
        <div class="meta-info">
            <p><strong>Batch Details:</strong> Color: {{ $mrn->batch->model->color ?? 'N/A' }} | Sizes: {{ implode(', ', $mrn->batch->model->sizes ?? []) }}</p>
            <p><strong>Warehouse Code:</strong> {{ $mrn->warehouse->code ?? 'N/A' }}</p>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>Generated on {{ date('d-M-Y h:i A') }} | Thimark Technocreations - Material Request Note</p>
        </div>
    </div>
</body>

</html>