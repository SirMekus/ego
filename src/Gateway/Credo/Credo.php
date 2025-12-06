<?php

namespace Emmy\Ego\Gateway\Credo;

use Emmy\Ego\Exception\UnsupportedGatewayMethodException;
use Emmy\Ego\Trait\Http;
use Emmy\Ego\Gateway\Realm\Tollgate;
use Emmy\Ego\Interface\PaymentGatewayInterface;

class Credo extends Tollgate implements PaymentGatewayInterface
{

    use Http;
    protected $secretKey;
    protected $baseUrl = 'https://api.credocentral.com/';

    public function __construct()
    {
        $this->secretKey ??= config('ego.credentials.credo.secret_key');
    }

    /**
     * @inheritDoc
     */
    public function getBanks(string $bankcode = ''): array
    {
        throw new UnsupportedGatewayMethodException("Method not Supported. Credo does not support this feature.");
    }

    /**
     * @inheritDoc
     */
    public function prepareForPayment(array $data): array
    {
        $email = searchArray('email', $data);
        $amount = searchArray('amount', $data);
        $customerFirstName = searchArray('customerFirstName', $data);
        $customerLastName = searchArray("customerLastName", $data);
        $customerPhoneNumber = searchArray("customerPhoneNumber", $data);
        $currency = searchArray('currency', $data);
        $bearer = searchArray('bearer', $data);
        $reference = searchArray('reference', $data);
        $metadata = searchArray('metadata', $data) ?? searchArray('metaData', $data);
        $callbackUrl = searchArray('callback_url', $data) ?? searchArray('callbackUrl', $data);
        $channels = searchArray('channels', $data);

        $this->setEmail($email);
        $this->setAmount($amount);
        if ($customerFirstName) {
            $this->setCustomerFirstName($customerFirstName);
        }
        if ($customerLastName) {
            $this->setCustomerLastName($customerLastName);
        }
        if ($customerPhoneNumber) {
            $this->setCustomerPhoneNumber($customerPhoneNumber);
        }
        if ($metadata) {
            $this->setMetadata($metadata);
        }
        if ($callbackUrl) {
            $this->setCallbackUrl($callbackUrl);
        }
        if ($channels) {
            $this->setChannels($channels);
        }
        if ($reference) {
            $this->setReference($reference);
        }
        if ($currency) {
            $this->setCurrency($currency);
        }
        if ($bearer) {
            $this->setBearer($bearer);
        }

        return $this->builder;
    }

    /**
     * @inheritDoc
     */
    public function pay(array $array): array
    {
        $payload = $this->buildPayload($array);
        $this->createConnection();
        return isset($payload['authorizationUrl']) ?
            $this->payViaAuthorizationUrl($payload) :
            [
                "status" => false,
                "message" => "Authorization url not found"
            ]; // Credo only accepts payment via an authorization url.
    }
    protected function payViaAuthorizationUrl(array $data)
    {
        $response = $this->post('transaction/initialize', $data);
        $url = $response['data']['authorizationUrl'];
        unset($response['status'], $response['message'], $response['data']['authorizationUrl']);
        return [
            "status" => true,
            "message" => "Authorization URL created",
            'url' => $url,
            'api_message' => $response,
        ];
    }

    /**
     * @inheritDoc
     */
    public function setKey(string|array $key): void
    {
        $this->secretKey =  $key;
    }

    /**
     * @inheritDoc
     */
    public function transfer(array $data): array
    {
        throw new UnsupportedGatewayMethodException("Method not Supported. Credo does not support this feature.");
    }

    /**
     * @inheritDoc
     */
    public function verifyAccountNumber(array $request): array
    {
        throw new UnsupportedGatewayMethodException("Method not Supported. Credo does not support this feature.");
    }

    /**
     * @inheritDoc
     */
    public function verifyPayment(array|string $credoData): array
    {
        if (is_array($credoData)) {
            if (isset($credoData['data']['transRef']) || isset($credoData['transRef'])) {
                $transRef = $credoData['data']['transRef'] ?? isset($credoData['transRef']);
                $status = $this->get("transaction/{$transRef}/verify/");
            }
        } else {
            $status = $this->get("transaction/{$credoData}/verify");
        }
        return $status;
    }

    /**
     * @inheritDoc
     */
    public function verifyWebhook(\Illuminate\Http\Request $request): void
    {
        $signature = $request->header('x-credo-signature');
        if (
            (strtoupper($_SERVER['REQUEST_METHOD']) != 'POST') ||
            !$signature
        )
            abort(401);

        // validate event, do all at once to avoid timing attack
        if ($signature !== hash_hmac('sha512', $request->getContent(), $this->secretKey))
            abort(401, "Webhook not verified");

        // User can do whatever at this juncture.

    }
}
