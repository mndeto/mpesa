<?php

namespace Mobidev\Mpesa\controllers;

//use App\Http\Controllers\Controller;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Mobidev\Mpesa\models\MpesaPaymentLog;
use Mobidev\Mpesa\models\Payment;


class C2BController extends BaseController
{

    protected $dispatcher;


    /**
     * C2BController constructor.
     * @param Dispatcher $dispatcher
     */
    public function __construct(Dispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    public function receiver(Request $request)
    {

        $input = $request->getContent(); //getting the file input


        $data = ['content' => $input, 'type' => 'c2b'];
        MpesaPaymentLog::create($data);


        $xml = new \DOMDocument();
        $xml->loadXML($input);// for c2b


        $data['phone_no'] = "+254" . substr(trim($xml->getElementsByTagName('MSISDN')->item(0)->nodeValue), -9);
        if ($xml->getElementsByTagName('KYCInfo')->length == 2) {
            $data['client_name'] = $xml->getElementsByTagName('KYCValue')->item(0)->nodeValue . ' ' . $xml->getElementsByTagName('KYCValue')->item(1)->nodeValue;
        } elseif ($xml->getElementsByTagName('KYCInfo')->length == 3) {
            $data['client_name'] = $xml->getElementsByTagName('KYCValue')->item(0)->nodeValue . ' ' . $xml->getElementsByTagName('KYCValue')->item(1)->nodeValue . ' ' . $xml->getElementsByTagName('KYCValue')->item(2)->nodeValue;
        }
        $data['transaction_id'] = $xml->getElementsByTagName('TransID')->item(0)->nodeValue;
        $data['amount'] = $xml->getElementsByTagName('TransAmount')->item(0)->nodeValue;
        $data['acc_no'] = preg_replace('/\s+/', '', $xml->getElementsByTagName('BillRefNumber')->item(0)->nodeValue);
        $data['transaction_time'] = $xml->getElementsByTagName('TransTime')->item(0)->nodeValue;
        $data['transaction_type'] = 1;

        /**
         * save this in the payments table, but we first check if it exists (Safaricom sometimes send the notification twice)
         */
        $transaction = Payment::whereTransactionId($data['transaction_id'])->first();
        if ($transaction === null) {
            $result = Payment::create($data);

            $payload = [
                'payment' => $result
            ];

            // Fire the 'payment received' event
            $this->dispatcher->fire('c2b.received.payment', $payload);

        }

    }
}