<?php

namespace Emmy\Ego\Gateway;

use Emmy\Ego\Gateway\Realm\Tollgate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Emmy\Ego\Exception\ApiException;
use Emmy\Ego\Interface\PaymentGatewayInterface;
use Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException;

class Paystack extends Tollgate implements PaymentGatewayInterface
{
	// use ChecksIfWebhookRequestIsLocal;
	protected $paystack;
	public $baseUrl = 'https://api.paystack.co';

	public function __construct(public string $key = "")
	{
		$this->secretKey ??= ($key ?? config('settings.paystack.secret_key'));
	}

	public function getBanks(): array
	{
		// $this->connect();
		return json_decode(json_encode($this->paystack->bank->getList()), true);
	}
	public function verifyAccountNumber(array $request): array
	{
		$this->connect();
		try {
			json_decode(json_encode($this->paystack->bank->resolve($request)), true);
		} catch (ApiException $exception) {
			throw new PreconditionFailedHttpException("Could not resolve account number");
		}
		return json_decode(json_encode($this->paystack->bank->resolve($request)), true);
	}

	public function getTransferRecipients()
	{
		$this->connect();
		return $this->paystack->transferrecipient->getList();
	}

	public function createRecipient(array $postdata): array
	{
		$this->connect();
		return [];

		// try {
		// 	$response = $this->paystack->transferrecipient->create($postdata);
		// 	return json_decode(json_encode($response), true);
		// } catch (ApiException $e) {
		// 	throw new PreconditionFailedHttpException($e->getMessage()->message);
		// }
	}

	public function pay(?array $data = []): array
	{
		$this->createConnection();
		return $this->payViaAuthorizationUrl($data);
		// $data = !empty($data) ? $data : $this->builder;

		// $data['amount'] = $data['amount'] * 100;

		// $this->connect();

		// //Now let's make life easier. Recurrent billing feature in which a Buyer doesn't need to fill in their email address is only available for those Buyers/Sellers that have an account with us. And the card detail is tied to the account used in registering or signing up. This means that if a Buyer is actually signed up with us but input a different email we'll ignore the email and check if any auth code exists using the email used in registration.
		// if (!empty($auth_code)) {
		// 	$data["authorization_code"] = $auth_code;

		// 	try {
		// 		$tranx = $this->payWithAuthCode($data);
		// 		$response["status"] = true;
		// 		$response["url"] = $data['callback_url'] . "?reference=" . $data['reference'];
		// 		$response["message"] = "Payment was successful";

		// 		return $response;
		// 	} catch (PreconditionFailedHttpException $e) {
		// 		//Do nothing for now
		// 	}
		// }

		// $tranx = $this->makePayment($data);

		// // redirect to page so User can pay
		// $response["status"] = true;
		// $response["url"] = $tranx->data->authorization_url;
		// $response["message"] = "Proceeding to payment";

		// return $response;
	}

	public function payViaAuthorizationUrl(array $data)
	{
		$response = $this->http->post($this->baseUrl . '/transaction/initialize', $data);
		$response = json_decode($response, true);
		// dd($response);
		if ($response['status'] != true) {
			switch ($response['type']) {
				case 'validation_error':
					throw new ApiException(json_encode($response));
				default:
			}
		}
		// dd("dkdk");
		// dd($response);
		$url = $response['data']['authorization_url'];
		unset($response['status'], $response['message'], $response['data']['authorization_url']);
		return [
			"status" => true,
			"message" => "Authorization URL created",
			'url' => $url,
			'api_message' => $response,
		];

		// try {
		// 	$tranx = $this->paystack->transaction->initialize($data);

		// 	return $tranx;
		// } catch (ApiException $e) {
		// 	switch ($e->getMessage()->message) {
		// 		case "Duplicate Transaction Reference":
		// 			$message = "Please refresh your browser and try again. If problem persists please contact support.";

		// 			break;

		// 		default:
		// 			$message = "nothing.";
		// 	}
		// 	throw new PreconditionFailedHttpException($message);
		// }
	}

	public function payViaAuthCode(array $data): array|object
	{
		return [];
		// try {
		// 	$tranx = $this->paystack->transaction->charge($data);
		// 	// dd($tranx);
		// 	return $tranx;
		// } catch (ApiException $e) {
		// 	$message = '';
		// 	switch ($e->getResponseObject()->message) {
		// 		case 'Invalid Authorization Code':
		// 			$message = $e->getResponseObject()->message;
		// 			break;

		// 		case 'Email does not match Authorization code. Authorization may be inactive or belong to a different email. Please confirm.':
		// 			$message = $e->getResponseObject()->message;
		// 			break;

		// 		default:
		// 			$message = $e->getResponseObject()->message;
		// 	}
		// 	throw new PreconditionFailedHttpException($message);
		// }
	}

	public function transfer(array $data): array
	{
		return [];
		//create recipient first
		// $recipient = $this->createRecipient($data);

		// //Then we make the transfer at this juncture
		// $postData = [
		// 	"source" => "balance",
		// 	"amount" => $data['amount'] * 100,
		// 	"recipient" => $recipient['data']['recipient_code'],
		// 	"reference" => $data['reference'],
		// 	"reason" => $data['reason'],
		// ];
		// try {
		// 	$response = $this->paystack->transfer->initiate($postData);
		// 	if($response->status != true) {
		// 		throw new PreconditionFailedHttpException("Transfer failed. Please try again.");
		// 	}
		// 	return json_decode(json_encode($response->data), true);
		// } catch (ApiException $e) {
		// 	throw new PreconditionFailedHttpException($e->getResponseObject()->message);
		// }
	}

	public function dummyWebhook()
	{
		return json_decode(Storage::disk('local')->get('payment.json'), true);
	}

	public function verifyWebhook(Request $request): void
	{
		if ($this->webhookPostedLocally())
			return;

		$signature = $request->header('x-paystack-signature');
		if (
			(strtoupper($_SERVER['REQUEST_METHOD']) != 'POST') ||
			!$signature
		)
			abort(401);

		// validate event do all at once to avoid timing attack
		if ($signature !== hash_hmac('sha512', $request->getContent(), $this->secretKey))
			abort(401, "Webhook not verified");

		// parse event (which is json string) as object
		// Do something - that will not take long - with $event
		// PaystackEvent::dispatch(json_decode($input));

		exit();
	}

	public function verifyPayment(array|string $paystackData): array
	{
		if (is_array($paystackData)) {
			if (isset($paystackData['data']['trxref'])) {
				return $this->verifyByReference($paystackData['data']['trxref']);
			}
			return $this->verifyByReference($paystackData['trxref']);
		}
		else{
			return $this->verifyByReference($paystackData);
		}
	}

	public function verifyByReference(string $reference): array
	{
		$this->connect();
		try {
			// verify using the library
			$tranx = $this->paystack->transaction->verify([
				'reference' => $reference, // unique to transactions
			]);
		} catch (ApiException $e) {
			throw new PreconditionFailedHttpException($e->getMessage());
		}

		if ('success' === $tranx->data->status) {
			// transaction was successful...
			return json_decode(json_encode($tranx->data), true);
		}
		throw new PreconditionFailedHttpException("Payment could not be verified");
	}

	public function convertDataFromTransferPayload(array $data): array
	{
		return [
			'reference' => $data['data']['reference'] ?? $data['reference'],
			'amount' => $data['data']['amount'] ?? $data['amount'],
			'currency' => $data['data']['currency'] ?? $data['currency'],
			'fee' => 0,
			'recipient_code' => $data['data']['recipient']['recipient_code'],
			'bank' => $data['data']['recipient']['details']['bank_code'] ?? null,
		];
	}
	public function convertDataFromDepositPayload(array $data): array
	{
		return [
			'reference' => $data['data']['reference'] ?? $data['reference'],
			'amount' => $data['data']['amount'] ?? $data['amount'],
			'currency' => $data['data']['currency'] ?? $data['currency'],
			'fee' => $data['data']['fees'] ?? $data['fees'],
			'payment_channel' => $data['data']['channel'] ?? $data['channel'],
			'authorization' => $data['data']['authorization'] ?? $data['authorization'],
			'authorization_code' => $data['data']['authorization']['authorization_code'] ?? $data['authorization']['authorization_code'],
		];
	}

	/**
	 * Takes the webhook payload, verifies that the webhook is legit then return a different payload structure 
	 * gotten via the payment verification endpoint.
	 */
	public function handleWebhook(array $payload): array
	{
		$gatewayData = [];
		if ($payload['event'] === 'charge.success') {
			$gatewayData = $this->verifyByReference($payload['data']['reference']);
			return $gatewayData;
		}
		if ($payload['event'] === 'transfer.success') {
			$gatewayData = $payload;
			return $gatewayData;
		}
		throw new \Exception('Paystack Webhook verification failed. ');
	}
}
