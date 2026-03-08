<?php

namespace Emmy\Ego\Gateway\Nomba;

use Emmy\Ego\Exception\ApiException;
use Emmy\Ego\Gateway\Realm\Tollgate;
use Emmy\Ego\Interface\PaymentGatewayInterface;
use Emmy\Ego\Requests\NombaWebhookRequest;
use Emmy\Ego\Trait\NombaAuth;
use Emmy\Ego\Trait\Webhooker;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class Nomba extends Tollgate implements PaymentGatewayInterface
{
    use NombaAuth, Webhooker;
    /**
     * Issue Secret Key from your Nomba Dashboard
     */
    protected string $secretKey;

    /**
     * Issue Account Id from your Nomba Dashboard
     */
    protected string $accountId;

    /**
     * Issue Client Id from your Nomba Dashboard
     */
    protected string $clientId;

    /**
     * Nomba API base Url
     */
    protected string $baseUrl;

    public function __construct()
    {
        $this->secretKey ??= config('ego.credentials.nomba.secret_key');
        $this->accountId ??= config('ego.credentials.nomba.account_id');
        $this->clientId ??= config('ego.credentials.nomba.client_id');
        $this->baseUrl ??= config('ego.credentials.nomba.base_url');
    }

    public function setKey(string|array $key):void
	{
        if (is_array($key)) {
            $this->secretKey = $key['secret_key'] ?? $this->secretKey;
            $this->accountId = $key['account_id'] ?? $this->accountId;
            $this->clientId = $key['client_id'] ?? $this->clientId;
        } else {
            $this->secretKey =  $key;
        }
	}

    public function prepareForPayment(array $data): array
	{
		$email = searchArray('email', $data);
		$customerId = searchArray(['customerId', 'customer_id', 'email'], $data);
		$amount = searchArray('amount', $data);
		$currency = searchArray('currency', $data);
		$reference = searchArray('reference', $data);
		$callbackUrl = searchArray(['callback_url','callbackUrl'], $data);
        $token = searchArray(['token'], $data);
        $tokenize = searchArray(['tokenize'], $data);
		
		$this->setOrder([
            'orderReference' => $reference,
            'customerId' => $customerId,
            'callbackUrl' => $callbackUrl,
            'customerEmail' => $email,
            'amount' => $amount,
            'currency' => $currency,
        ]);
        $this->builder['tokenizeCard'] = $tokenize ?? true;
        if($token){
            $this->builder['tokenKey'] = $token;
        }

		return $this->builder;
	}

    public function pay(?array $data = []): array
    {
        $payload = $this->buildPayload($data);
        if(isset($payload['order']) && $this->accountId){
            $payload['order']['accountId'] = $this->accountId;
        }
        if(isset($payload['tokenKey'])){
            return $this->payViaAuthorizationCode($payload);
        }
        return $this->payViaAuthorizationUrl($payload);
    }

    protected function payViaAuthorizationUrl(array $payload): array
	{
		$response = $this->post(
            path:'checkout/order', 
            data:$payload, 
            headers:$this->getDefaultHeaders()
        );

        if (! isset($response['data']['checkoutLink'])) {
            throw new Exception('Failed to create checkout link: '.($response['description'] ?? 'Unknown error'));
        }

        return [
            "status" => true,
			"message" => "Authorization URL created",
			'url' => $response['data']['checkoutLink'],
			'api_message' => $response,
        ];
	}

    protected function payViaAuthorizationCode(array $data): array|object
	{
		$response = $this->post(
            path:'checkout/tokenized-card-payment', 
            data:$data, 
            headers:$this->getDefaultHeaders()
        );
		return [
			"status" => true,
			"message" => "Charge attempted",
			'api_message' => $response,
		];
	}

    public function verifyPayment(array|string $paymentReference, ?string $paymentType=null): array
    {
        $route = 'transactions/accounts/single';
        if($paymentType){
			$route = match($paymentType){
				'transaction', 'deposit' => 'transactions/accounts/single',
				'transfer', 'bank_transfer' => 'transactions/bank',
				default => throw new Exception("Payment type not supported. \n Supported types are: transaction => For confirming payment by customers; transfer (or 'bank_transfer') => For confirming payment by transfer")
			};
		}
        
        $response = $this->makeRequest(
            method: 'GET',
            path: $route,
            data: ['orderReference' => $paymentReference],
            headers: $this->getDefaultHeaders()
        );

        if (! isset($response['data']) || empty($response['data'])) {
            throw new Exception('Transaction not found');
        }

        // Get the first matching transaction
        $transaction = $response['data'];

        // Map Nomba status to our internal status
        $status = $this->mapNombaStatus(searchArray('status', $transaction));
        $description = searchArray(['description', "narration"], $transaction);
        
        return [
            'status' => $status,
            'message' => $description ?? 'No description',
            'data' => $transaction,
            'reference' => $transaction['onlineCheckoutOrderReference'] ?? null,
        ];
    }

    public function prepareForTransfer(array $data): array
	{
		$accountNumber = searchArray(['accountNumber', 'account_number'], $data);
		$amount = searchArray('amount', $data);
		$accountName = searchArray(['accountName','account_name'], $data);
		$bankCode = searchArray(['bankCode', 'bank_code'], $data);
		$narration = searchArray(['narration', 'description'], $data);
		$senderName = searchArray(['senderName', 'sender_name'], $data);
		$merchantTxRef = searchArray(['merchantTxRef','reference'], $data);

        $this->builder['accountNumber'] = $accountNumber;
        $this->builder['accountName'] = $accountName;
        $this->builder['bankCode'] = $bankCode;
        $this->builder['senderName'] = $senderName;
        $this->builder['merchantTxRef'] = $merchantTxRef;
		
        $this->setAmount($amount);
        $this->setNarration($narration);


		return $this->builder;
	}

    public function transfer(?array $data=[]): array 
    {
        $payload = $this->buildPayload($data);

        try {
            $response = $this->post(
                path:'transfers/bank', 
                data:$payload, 
                headers:$this->getDefaultHeaders());

            if (! isset($response['data'])) {
                throw new Exception('Invalid transfer response structure');
            }

            $transferResult = $response['data'];

            return [
                'status' => true,
                'success' => true,
                'data' => $transferResult,
            ];
        } catch (Exception $e) {
            throw new ApiException('Bank transfer failed: '.$e->getMessage());
        }
    }

    public function verifyWebhook(Request $request): void
	{
		$this->checkIfValidationIsNecessary();
        
		$signatureKey = config('ego.credentials.nomba.signature_key');

        if (! $signatureKey) {
            throw new ApiException("Nomba signature key not configured. Please set 'ego.credentials.nomba.signature_key' in your configuration.");
        }

        // Validate required webhook fields using NombaWebhookRequest rules
        $nombaRequest = new NombaWebhookRequest;
        $validator = Validator::make($request->all(), $nombaRequest->rules(), $nombaRequest->messages());

        if ($validator->fails()) {
            throw new ApiException('Invalid webhook payload: '.$validator->errors()->first());
        }

        $webhookData = $request->all();
        $headers = $request->headers->all();

        $timestamp = $headers['nomba-timestamp'] ?? [0 => ''];
        // Build the signature payload according to Nomba's specification
        $hashingPayload = $webhookData['event_type'].':'.
            $webhookData['requestId'].':'.
            $webhookData['data']['merchant']['userId'].':'.
            $webhookData['data']['merchant']['walletId'].':'.
            $webhookData['data']['transaction']['transactionId'].':'.
            $webhookData['data']['transaction']['type'].':'.
            $webhookData['data']['transaction']['time'].':'.
            $webhookData['data']['transaction']['responseCode'] ?? null;

        $message = $hashingPayload.':'.$timestamp[0];

        // Generate HMAC hash using SHA256
        $calculatedSignature = hash_hmac('sha256', $message, $signatureKey, true);
        $message = $hashingPayload.':'.$timestamp[0];

        // Generate HMAC hash using SHA256
        $calculatedSignature = hash_hmac('sha256', $message, $signatureKey, true);

        $encodedData = base64_encode($calculatedSignature);

        if ($encodedData !== ($headers['nomba-signature'][0] ?? '')) {
            throw new ApiException('Nomba webhook signature verification failed');
        }
	}

    /**
     * Map Nomba status to TransactionStatus enum
     */
    private function mapNombaStatus(string $status): string
    {
        return match (strtoupper($status)) {
            'PAYMENT SUCCESSFUL', 'SUCCESS' => 'success',
            'PENDING', 'PROCESSING' => 'pending',
            default => 'failed',
        };
    }

    public function getBanks(string $countryCode=""): array
    {
        $response = $this->get(
            path:'/transfers/banks',
            headers: $this->getDefaultHeaders()
        );
        return $response;
    }

    public function verifyAccountNumber(array $request=[]): array 
    {
        $accountNumber = searchArray(['accountNumber', 'account_number'], $request);
        $bankCode = searchArray(['bankCode', 'bank_code'], $request);

        if (! $accountNumber || ! $bankCode) {
            throw new Exception('Account number and bank code are required for verification');
        }

        $data = [
            'accountNumber' => $request['accountNumber'] ?? null,
            'bankCode' => $request['bankCode'] ?? null,
        ];
        try {
            $response = $this->post(
                path:'/transfers/bank/lookup', 
                data:$data,
                headers:$this->getDefaultHeaders()
                );
            if (! isset($response['data'])) {
                throw new Exception('Invalid transfer response structure');
            }

            $result = $response['data'];

            if (! isset($result['accountName'])) {
                throw new Exception('Account name not found in response');
            }

            return [
                'success' => true,
                'accountNumber' => $accountNumber,
                'bankCode' => $bankCode,
                'accountName' => $result['accountName'],
            ];
        } catch (Exception $e) {
            throw new ApiException('Bank transfer failed: '.$e->getMessage());
        }
    }
}
