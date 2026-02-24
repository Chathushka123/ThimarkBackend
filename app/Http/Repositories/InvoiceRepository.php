<?php

namespace App\Http\Repositories;

use Illuminate\Http\Request;
use App\Exceptions\ConcurrencyCheckFailedException;
use PDF;
// use App\HashStore;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Exception;
use App\Exceptions\GeneralException;
use Illuminate\Support\Facades\Log;
use App\Invoice;
use App\InvoiceDetail;
use App\InvoiceStatus;
use App\Transaction;
use App\PaymentMethod;

use DateTime;

class InvoiceRepository

{

    // private $qr;

    public function __construct()
    {
        //$this->qr = new QrCodeController();
    }

    public function createAndUpdateInvoice($request)
    {
        try {
            DB::beginTransaction();

            $invoiceId = $request->input('invoiceId');
            $invoiceName = $request->input('invoiceName');
            $address = $request->input('address');
            $invoiceDate = $request->input('invoiceDate');
            $dueDate = $request->input('dueDate');
            $mobile = $request->input('mobile');
            $status_id = $request->input('status_id');
            $invoiceDetails = $request->input('invoiceDetails');
            $paymentType = $request->input('paymentType');
            $paymentDateInput = $request->input('PaymentDate');
            $paymentDate = $paymentDateInput ? (new DateTime($paymentDateInput))->format('Y-m-d') : null;

            $date = new DateTime($dueDate);
            $formattedDate = $date->format('Y-m-d');

            if ($invoiceId > 0) {
                $invoice = Invoice::findOrFail($invoiceId);
                $invoice->name = $invoiceName;
                $invoice->address = $address;
                $invoice->mobile = $mobile;
                $invoice->status_id = $status_id;
                $invoice->invoice_date = $invoiceDate;
                $invoice->due_date = $formattedDate;
                $invoice->save();

                foreach ($invoiceDetails as $detail) {
                    if ($detail['id'] > 0 && $detail['_rowstate'] != "DELETED") {
                        $invoiceDetails = InvoiceDetail::findOrFail($detail['id']);

                        $invoiceDetails->description = $detail['description'];
                        $invoiceDetails->quantity = $detail['qty'];
                        $invoiceDetails->total_price = $detail['amount'];
                        $invoiceDetails->invoice_id = $invoiceId;

                        $invoiceDetails->save();
                    } else if ($detail['id'] > 0 && $detail['_rowstate'] == "DELETED") {
                        $invoiceDetails = InvoiceDetail::findOrFail($detail['id']);
                        $invoiceDetails->active = 0;
                        $invoiceDetails->save();
                    } elseif ($detail['_rowstate'] != "DELETED") {
                        $invoiceDetails = new InvoiceDetail();
                        $invoiceDetails->description = $detail['description'];
                        $invoiceDetails->quantity = $detail['qty'];
                        $invoiceDetails->total_price = $detail['amount'];
                        $invoiceDetails->invoice_id = $invoiceId;
                        $invoiceDetails->save();
                    }
                }

                if ($request->input('paymentAmount') != 0) {
                    Transaction::create([
                        'invoice_id' => $invoiceId,
                        'amount' => $request->input('paymentAmount'),
                        'transaction_type' => null,
                        'payment_method' => $paymentType,
                        'payment_date' => $paymentDate
                    ]);
                }
            } else {
                $invoice = new Invoice();
                $invoice->name = $invoiceName;
                $invoice->address = $address;
                $invoice->mobile = $mobile;
                $invoice->status_id = $status_id;
                $invoice->invoice_date = $invoiceDate;
                $invoice->due_date = $formattedDate;
                $invoice->save();

                foreach ($invoiceDetails as $detail) {
                    if ($detail['_rowstate'] != "DELETED") {
                        $invoiceDetails = new InvoiceDetail();
                        $invoiceDetails->description = $detail['description'];
                        $invoiceDetails->quantity = $detail['qty'];
                        $invoiceDetails->total_price = $detail['amount'];
                        $invoiceDetails->invoice_id = $invoice->id;
                        $invoiceDetails->save();
                    }
                }

                if ($request->input('paymentAmount') != 0) {
                    Transaction::create([
                        'invoice_id' => $invoice->id,
                        'amount' => $request->input('paymentAmount'),
                        'transaction_type' => null
                    ]);
                }
            }


            DB::commit();
            return response()->json(['message' => 'success', 'data' => $invoice->id], 200);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error creating/updating invoice: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getSearchByInvoice($id, $name, $mobile)
    {
        $results = Invoice::select(
            'id',
            'name',
            'mobile'

        )
            ->where('id', 'LIKE', (is_null($id) ? '%' :  '%' . $id . '%'))
            ->where('name', 'LIKE', (is_null($name) ? '%' :  '%' . $name . '%'))
            ->where('mobile', 'LIKE', (is_null($mobile) ? '%' :  '%' . $mobile . '%'))
            ->get();

        return $results;
    }

    public function getInvoicePrint($id)
    {
        $invoice = Invoice::with(['invoice_details', 'transactions'])
            ->where('id', $id)
            ->first();

        $advance = 0;
        if ($invoice->transactions) {
            foreach ($invoice->transactions as $transaction) {
                if ($transaction->active == 1) {
                    $advance += floatval($transaction->amount);
                }
            }
        }
        $invoiceDetails = [];
        $totalAmount = 0;
        if ($invoice->invoice_details) {
            foreach ($invoice->invoice_details as $detail) {
                if ($detail->active == 1) {
                    $invoiceDetails[] = [
                        'description' => $detail->description,
                        'quantity' => $detail->quantity,
                        'unit_price' => $detail->unit_price,
                        'total_price' => $detail->total_price
                    ];
                    $totalAmount += floatval($detail->total_price);
                }
            }
        }

        if (!$invoice) {
            return response()->json(['error' => 'Invoice not found'], 404);
        }

        $data = ['invoice' => $invoice, 'advance' => $advance, 'invoiceDetails' => $invoiceDetails, 'totalAmount' => $totalAmount];

        // return $data;
        $pdf = PDF::loadView('print.invoice', $data);
        // print_r($pdf);
        // Set custom paper size - Half A4 height (210mm x 148.5mm)
        // $pdf->setPaper([0, 0, 595.276, 420.945], 'portrait');
        $pdf->setPaper([0, 0, 595.276, 420.945], 'landscape');
        return $pdf->stream('Invoice' . $id . '.pdf');
    }

    public function getStatus()
    {
        $invoiceStatus = InvoiceStatus::get();
        return $invoiceStatus;
    }



    public function getInvoice()
    {
        $data = DB::select("
            SELECT
                i.id AS bill_no,
                i.name,
                i.mobile AS contact,
                id.total_amount,
                i.invoice_date,
                i.due_date,
                s.status as status,
                t.paid_amount as paid,
                (id.total_amount - IFNULL(t.paid_amount, 0)) AS balance
            FROM
                invoices i
            JOIN invoice_status s on s.id = i.status_id
            LEFT JOIN (
                SELECT
                    SUM(id.total_price) AS total_amount,
                    id.invoice_id
                FROM
                    invoice_details id
                WHERE
                    id.active = 1
                GROUP BY
                    id.invoice_id
            ) AS id ON id.invoice_id = i.id

            LEFT JOIN (
                select sum(t.amount) as paid_amount, t.invoice_id
                from transactions t
                where t.active = 1
                group by t.invoice_id
            ) AS t ON t.invoice_id = i.id
            WHERE
                i.active = 1
            ORDER BY i.id DESC
        ");

        return $data;
    }

    public function setupInvoiceID($request)
    {
        try {
            $newInvoiceId = $request->input('invoice_id');

            // Validate the input
            if (!$newInvoiceId || !is_numeric($newInvoiceId)) {
                return response()->json(['error' => 'Invalid invoice ID provided'], 400);
            }

            // Sanitize the input to prevent SQL injection (ensure it's a positive integer)
            $newInvoiceId = (int) $newInvoiceId;
            if ($newInvoiceId <= 0) {
                return response()->json(['error' => 'Invoice ID must be a positive number'], 400);
            }

            // Get the current maximum ID from the invoices table
            $currentMaxId = DB::table('invoices')->max('id') ?? 0;

            // Validate that the new invoice ID is greater than the current maximum ID
            if ($newInvoiceId <= $currentMaxId) {
                throw new \App\Exceptions\GeneralException('New invoice ID must be greater than current maximum ID ' . $currentMaxId);
            }

            // Update the auto-increment value for the invoices table
            // Note: ALTER TABLE doesn't support parameter binding, so we use direct interpolation
            $update = DB::statement("ALTER TABLE invoices AUTO_INCREMENT = {$newInvoiceId}");

            if ($update) {
                return response()->json([
                    'message' => 'Auto-increment ID updated successfully',
                    'previous_max_id' => $currentMaxId,
                    'new_auto_increment' => $newInvoiceId
                ], 200);
            }
        } catch (Exception $e) {
            Log::error('Error updating invoice auto-increment ID: ' . $e->getMessage());
            throw new \App\Exceptions\GeneralException($e->getMessage());
        }
    }

    public function getTransactionSummary($dateFrom, $dateTo)
    {
        try {

            $dateFrom = $dateFrom ? (new DateTime($dateFrom))->format('Y-m-d') : null;
            $dateTo = $dateTo ? (new DateTime($dateTo))->format('Y-m-d') : null;

            $data = DB::select("
                SELECT
                    t.payment_date,
                    t.invoice_id AS bill_no,
                    t.amount,
                        COALESCE(t.transaction_type, 'Invoice Payment') AS type,
                    t.payment_method AS payment_type,
                    t.description,
                    t.payee,
                    i.name AS payer
                FROM transactions t
                LEFT JOIN invoices i ON i.id = t.invoice_id
                WHERE t.payment_date BETWEEN ? AND ?
            ", [$dateFrom, $dateTo]);

            return $data;
        } catch (Exception $e) {
            Log::error('Error updating invoice auto-increment ID: ' . $e->getMessage());
            throw new \App\Exceptions\GeneralException($e->getMessage());
        }
    }

    public function getPaymentMethodsByType($type)
    {
        try {
            // Fetch payment methods filtered by payment_type LIKE :type
            $results = DB::table('payment_methods')
                ->select('payment_type as name', 'payment_type as id')
                ->where('payment_type', 'LIKE', '%' . $type . '%')
                ->get();

            return $results;
        } catch (Exception $e) {
            Log::error('Error fetching payment methods: ' . $e->getMessage());
            throw new \App\Exceptions\GeneralException($e->getMessage());
        }
    }

    //////////// Ady Book ////////////
    public function getPaymentMethod()
    {
        $paymentTypes = PaymentMethod::get();
        return $paymentTypes;
    }

    public function createDayBookTransaction($request)
    {
        try {
            DB::beginTransaction();

            $amount = $request->input('amount');
            $payee = $request->input('payee');
            $description = $request->input('description');
            $transactionType = $request->input('transaction_type');
            $paymentMethod = $request->input('payment_method');
            $paymentDateInput = $request->input('payment_date');
            $paymentDate = $paymentDateInput ? (new DateTime($paymentDateInput))->format('Y-m-d') : null;

            Transaction::create([
                'invoice_id' => null,
                'amount' => $amount,
                'payee' => $payee,
                'description' => $description,
                'transaction_type' => $transactionType,
                'payment_method' => $paymentMethod,
                'payment_date' => $paymentDate,
                'active' => 1
            ]);

            DB::commit();
            return response()->json(['message' => 'Transaction created successfully'], 200);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error creating day book transaction: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function createPaymentType($request)
    {
        try {
            $paymentTypeName = $request->input('payment_type');

            // Validate input
            if (empty($paymentTypeName)) {
                return response()->json(['error' => 'Payment type name is required'], 400);
            }

            // Create new PaymentMethod
            $paymentMethod = new PaymentMethod();
            $paymentMethod->payment_type = $paymentTypeName;
            $paymentMethod->save();

            return response()->json(['message' => 'Payment type created successfully', 'data' => $paymentMethod], 200);
        } catch (Exception $e) {
            Log::error('Error creating payment type: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
