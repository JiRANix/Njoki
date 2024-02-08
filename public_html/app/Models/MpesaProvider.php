<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Safaricom\Mpesa\Mpesa;

class MpesaProvider extends Model
{
    use HasFactory;

    protected $app;
    protected $payload = [];
    protected $access_token;
    protected $response;
    protected $transaction_type;
    protected $expires_in = null;
    protected $authed_at = null;
    protected $config = [];

    protected $whitelist = [
        '196.201.214.206',
        '196.201.214.207',
        '196.201.214.208',
        '35.177.159.183',
        "196.201.213.44"
    ];


    public function __construct($app = null, $auth = false, array $opts = [])
    {
        $app  =  $app ? : config('mpesa.default');
        $this->app  = $app;
        $this->config =  config("mpesa.apps.{$app}");

        if ($auth)
            $this->auth();
    }

    public  function  getToken(){

        $mpesa = new Mpesa();

        return $mpesa->generateToken();

    }

    public function getConfig($key =  null, $default = null)
    {
        if(!$key)
            return $this->config;

        return Arr::get($this->config, $key, $default);
    }

    public static function stk_push($phone, $amount, $ref, $description,$userId): string
    {
        $mpesa = new Mpesa();
        $callback = "https://jiranimall.com/api/v2/stkCallBack/{$ref}";
        $shortcode = 7845258;
        $passkey  =  '666b900add95869f050e489037e5bc7fc60e0b160595d385503e19db5ca17e48';
        return $mpesa->STKPushSimulation(
            $shortcode, $passkey,
            'CustomerBuyGoodsOnline', $amount, $phone,
            "9566609", $phone, $callback,
            $ref, $description,
            $description
        );
    }

    public static function dashboard_stk_push($phone, $amount, $ref, $description): string
    {
        $mpesa = new Mpesa();
        $callback = "https://assistallbackend.signmediake.com.assistallapp.com/api/stkCallBack/{$ref}";
        $shortcode =  '7062030';
        $passkey  =  '62968663a8d68db4544d68aa7205a375225272647d1486931dbe74ddfc87ba33';
        return $mpesa->STKPushSimulation(
            $shortcode, $passkey,
            'CustomerPayBillOnline', $amount, $phone,
            $shortcode, $phone, $callback,
            $ref, $description,
            $description
        );
    }


    public function depositStkPush($phone, $amount, $ref, $description, $type): string
    {
        $phone = encode_phone_number($phone);
        $mpesa = new Mpesa();
        $callback = url("api/ipn/stk/{$type}/{$ref}");

    }
    public function deposit($phone, $amount, $ref, $description, $type): string
    {
        $phone = encode_phone_number($phone);
        $mpesa = new Mpesa();
        $callback = url("api/cashier/c2bConfirmation");

        $shortcode =  $this->getConfig('shortcode');
        $passkey  =  $this->getConfig('lipa_na_mpesa_passkey');
        $stkPushSimulation = $mpesa->STKPushSimulation(
            $shortcode, $passkey,
            'CustomerPayBillOnline', $amount, $phone,
            $shortcode, $phone, $callback,
            $ref, $description,

            $description

        );
        return $stkPushSimulation;
    }

    /**
     * Check if is authed and is still valid
     *
     * @return boolean
     */
    public function is_authed(): bool
    {
        // session has not been authed yet
        if (!$this->access_token)
            return false;
        return time() < ($this->authed_at + $this->expires_in);

    }

    /**
     * Get oauth access token
     *
     * todo: find a better way to manage the authentication i.e store in session
     *
     * @return $this
     * @throws \Exception
     */
    public function auth(): MpesaSdk
    {
        // skip authentication if access token is still valid
        if ($this->is_authed())
            return $this;

        $url = $this->get_url_for('auth');
        $consumer_key = $this->getConfig('consumer_key');
        $consumer_secret = $this->getConfig('consumer_secret');
        $credentials = base64_encode("{$consumer_key}:{$consumer_secret}");

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => [
                "Authorization: Basic {$credentials}",
                "cache-control: no-cache",
            ],
        ]);

        $curl_response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err) {
            throw new \Exception($err);
        }

        try {
            $result = json_decode($curl_response, true);
            $this->authed_at = time();
            $this->access_token = $result['access_token'];
            $this->expires_in = $result['expires_in'];

            return $this;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * @param $validation_url
     * @param $confirmation_url
     * @return $this
     */
    public function register_url($validation_url, $confirmation_url): MpesaSdk
    {
        $this->transaction_type = __FUNCTION__;
        $this->payload = [
            'ShortCode' => $this->getConfig('shortcode'),
            'ResponseType' => 'Cancelled',
            'ConfirmationURL' => $confirmation_url,
            'ValidationURL' => $validation_url,
        ];

        return $this;
    }

    public function c2b_simulate($amount, $bill_ref_number): MpesaSdk
    {
        $this->transaction_type = __FUNCTION__;
        $this->payload = [
            'ShortCode' => env('mpesa_shortcode'),
            'CommandID' => 'CustomerPayBillOnline',
            'Amount' => $amount,
            'Msisdn' => "254708374149",
            'BillRefNumber' => $bill_ref_number
        ];

        return $this;
    }

    public static function withdraw($amount, $receiver, $remarks,$ref,$userId,$occasion = null)
    {

        $initiator = env("MPESA_INITIATOR_NAME");
        $security_credential = env("MPESA_INITIATOR_PASSWORD");
        $partyA = env("MPESA_B2C_SHORTCODE");
        $partyB = $receiver;

        $cert = storage_path("ProductionCertificate.cer");


        $publicKey = file_get_contents($cert);

        openssl_public_encrypt($security_credential, $encrypted, $publicKey, OPENSSL_PKCS1_PADDING);

        $encrypted_credential =  base64_encode($encrypted);

        $mpesa = new Mpesa();
        $callback = "https://assistallbackend.signmediake.com.assistallapp.com/api/b2cCallBack/{$ref}/{$userId}";

        $b2cTransaction = self::business2Customer(
            $initiator,
            $encrypted_credential,
            'SalaryPayment',
            $amount,
            $partyA,
            $partyB,
            $remarks,
            "https://assistallbackend.signmediake.com.assistallapp.com",
            $callback,
            $occasion
        );

        return json_decode($b2cTransaction, true);
    }

    public static function business2Customer($InitiatorName, $SecurityCredential, $CommandID, $Amount, $PartyA, $PartyB, $Remarks, $QueueTimeOutURL, $ResultURL, $Occasion){
        $url = 'https://api.safaricom.co.ke/mpesa/b2c/v1/paymentrequest';
        $token=self::b2cGenerateLiveToken();

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type:application/json','Authorization:Bearer '.$token));


        $curl_post_data = array(
            'InitiatorName' => $InitiatorName,
            'SecurityCredential' => $SecurityCredential,
            'CommandID' => $CommandID ,
            'Amount' => $Amount,
            'PartyA' => $PartyA ,
            'PartyB' => $PartyB,
            'Remarks' => $Remarks,
            'QueueTimeOutURL' => $QueueTimeOutURL,
            'ResultURL' => $ResultURL,
            'Occasion' => $Occasion
        );

        $data_string = json_encode($curl_post_data);

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);

        $curl_response = curl_exec($curl);

        return json_encode($curl_response);

    }


    public static function b2cGenerateLiveToken(){

        $consumer_key = 'sslUGbZvWlUMkTb7wdVXAl2AOImvjeGK';
        $consumer_secret ='rYEMy9BjOy7fGqZT';

        if(!isset($consumer_key)||!isset($consumer_secret)){
            die("please declare the consumer key and consumer secret as defined in the documentation");
        }
        $url = 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        $credentials = base64_encode($consumer_key.':'.$consumer_secret);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Authorization: Basic '.$credentials)); //setting a custom header
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

        $curl_response = curl_exec($curl);

        return json_decode($curl_response)->access_token;


    }


    public function c2bDeposit($amount, $phone)
    {

        $shortCode = $this->getConfig('shortcode');
        $misdn = encode_phone_number($phone);


        $mpesa = new Mpesa($this->getConfig('consumer_key'),
            $this->getConfig('consumer_secret'));

        $c2bTransaction = $mpesa->c2b($shortCode,"1234",$amount,$misdn,"1234");

        return json_decode($c2bTransaction, true);
    }

    public function b2b($amount, $partyB, $ref, $remarks): string
    {
        $initiator = $this->getConfig('initiator_name');
        $partyA = $this->getConfig('shortcode');
        $security_credential = $this->getConfig('initiator_password');
        $encrypted_credential = $this->compute_security_credentials($security_credential);

        $mpesa = new Mpesa($this->getConfig('consumer_key'),
            $this->getConfig('consumer_secret'));
        $b2cTransaction = $mpesa->b2b(
            $initiator,
            $encrypted_credential,
            $amount,
            $partyA,
            $partyB,
            $remarks,
            url("api/gateways/mpesa/b2b/timeout"), //timeout url
            url("api/gateways/mpesa/b2b/process"), //result url
            $ref,
            'BusinessPayBill',
            4,
            4
        );

        return $b2cTransaction;
    }

    public function balance($partyA = null, $partyType = 4)
    {

        $initiator = $this->getConfig('initiator_name');
        $partyA = $partyA ? encode_phone_number($partyA) : $this->getConfig('shortcode');
        $security_credential = $this->getConfig('initiator_password');
        $encrypted_credential = $this->compute_security_credentials($security_credential);

        $mpesa = new Mpesa($this->getConfig('consumer_key'),
            $this->getConfig('consumer_secret'));

        $b2cTransaction = $mpesa->accountBalance(
            'AccountBalance',
            $initiator,
            $encrypted_credential,
            $partyA,
            $partyType,
            "AccountBalance Query",
            url("api/ipn/b2c-timeout"),
            url("api/ipn/b2c")
        );

        return json_decode($b2cTransaction, true);
    }

    /**
     * Send request
     *
     * @return mixed
     */
    public function send()
    {
        $url = $this->get_url_for($this->transaction_type);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type:application/json', "Authorization:Bearer {$this->access_token}")); //setting custom header


        $curl_post_data = $this->payload;

        $data_string = json_encode($curl_post_data);

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);

        $curl_response = curl_exec($curl);

        return json_decode($curl_response, true);
    }

    public function get_url_for($key, $default = null)
    {
        if ($this->getConfig('status') != 'live') {
            $urls = [
                'auth' => 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials',
                'register_url' => 'https://sandbox.safaricom.co.ke/mpesa/c2b/v1/registerurl',
                'c2b_simulate' => 'https://sandbox.safaricom.co.ke/mpesa/c2b/v1/simulate'
            ];
        } else {
            $urls = [
                'auth' => 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials',
                'register_url' => 'https://api.safaricom.co.ke/mpesa/c2b/v1/registerurl',
                'c2b_simulate' => 'https://api.safaricom.co.ke/mpesa/c2b/v1/simulate'
            ];
        }

        return Arr::get($urls, $key, $default);
    }

    /**
     * @return mixed
     */
    public function getTransactionType()
    {
        return $this->transaction_type;
    }

    public function getAccessToken()
    {
        return $this->access_token;
    }

    public function deposit_to_wallet($payload)
    {
        try {
            $data = $payload;

            $meta = $data['Body']['stkCallback']['CallbackMetadata']['Item'];

            $return = [
                'amount' => $meta[0]['Value'],
                'trx_no' => $meta[1]['Value'],
                'ref_no' => time(),
                'source' => $meta[4]['Value'],
                'date_paid' => carbon($meta[3]['Value'])
            ];

            return $return;

        } catch (\Exception $e) {

            \Log::info('This is not possible for me why?' . $e->getMessage());
            return false;
        }
    }


    public static function process_stk($payload)
    {
        try {
            $data = $payload;

            $meta = $data['Body']['stkCallback']['CallbackMetadata']['Item'];

            $return = [
                'amount' => $meta[0]['Value'],
                'trx_no' => $meta[1]['Value'],
                'ref_no' => time(),
                'source' => $meta[4]['Value'],
                'date_paid' => carbon($meta[3]['Value'])
            ];

            return $return;

        } catch (\Exception $e) {

            \Log::info('This is not possible for me why?' . $e->getMessage());
            return false;
        }
    }

    public static function process_c2b_callback($payload)
    {
        try {
            $bill_ref  =  $payload['BillRefNumber'];

            $name = Arr::get($payload, 'FirstName') . " " .
                Arr::get($payload, 'MiddleName') . " "
                . Arr::get($payload, 'LastName');

            return [
                'rct_no' => $payload['TransID'],
                'paid_at' => Carbon::createFromFormat("YmdHis",$payload['TransTime']),
                'amount' => $payload['TransAmount'],
                'source' => $payload['MSISDN'],
                'ref' => $bill_ref,
                'customer_name' => $name,
            ];
        } catch (\Exception $e) {
            report($e);
            return false;
        }
    }

    public static function process_b2c_callback($payload)
    {
        try {
            $conversation_id = $payload["Result"]["ConversationID"];
            $result_code = $payload["Result"]["ResultCode"];
            $transaction_id = $payload["Result"]["TransactionID"];

            $withdrawal = Settlement::getByConversationID($conversation_id);

            if (!$withdrawal)
                return false;

            if ($result_code === 0) {
                $withdrawal->process_successful_withdrawal($transaction_id);
            } else {
                $withdrawal->process_failed_withdrawal($transaction_id, $payload['Result']['ResultDesc']);
            }

            return $withdrawal;

        } catch (\Exception $e) {
            report($e);
            return false;
        }
    }


    public function compute_security_credentials($password): string
    {
        $env = env('MPESA_ENV');

        $cert = storage_path("mpesa_{$env}_cert.cer");

        $publicKey = file_get_contents($cert);

        openssl_public_encrypt($password, $encrypted, $publicKey, OPENSSL_PKCS1_PADDING);

        return base64_encode($encrypted);
    }

    /**
     * @param Request $request
     * @return bool
     */
    public function validate_request(Request $request): bool
    {
        $ips  = json_encode($request->ips());
        Log::info("Validating ips => {$ips}", []);

        if (env('APP_ENV') === 'local')
            return true;


        //todo: work on this
        return true;
        return in_array($request->ip(), $this->whitelist, false);
    }

    /**
     * @param array $payload
     * @return array
     */
    public static function getB2cResultParameters(array  $payload): array
    {
        $data['rct_no'] = $payload["Result"]["TransactionID"];
        $data['shortCode'] =  config('mpesa.apps.b2c.shortcode');

        foreach (Arr::get($payload, 'Result.ResultParameters.ResultParameter', []) as $parameter) {
            if ($parameter['Key'] == "TransactionAmount")
                $data['amount'] = $parameter["Value"];

            if ($parameter['Key'] == "B2CUtilityAccountAvailableFunds")
                $data['balance'] = $parameter["Value"];
        }

        return $data;
    }
}
