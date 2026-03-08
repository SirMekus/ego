<?php

namespace Emmy\Ego\Trait;


use Emmy\Ego\Exception\ApiException;
use Emmy\Ego\Trait\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http as IlluminateHttp;

trait NombaAuth
{
    use Http;
    protected $cacheKey = 'ego_nomba_access_token';

    /**
     * Get or refresh the access token for Nomba API
     */
    protected function getAccessToken(): string
    {
        if ($token = Cache::get($this->cacheKey)) {
            return $token;
        }
        
        $response = $this->post(
            path :'auth/token/issue', 
            data: [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->secretKey,
            ],
            headers: [
                "Content-Type" => "application/json",
                'accountId' => $this->accountId,
            ]
        );

        if (! isset($response['data']['access_token'])) {
            throw new ApiException('Failed to get access token from Nomba');
        }

        $token = $response['data']['access_token'];
        $expiresIn = $response['data']['expires_in'] ?? 3600;

        // Cache the token for slightly less than its expiry time
        Cache::put($this->cacheKey, $token, now()->addSeconds($expiresIn - 60));

        return $token;
    }

    protected function getDefaultHeaders(bool $useAuth = true): array
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'accountId' => $this->accountId,
        ];

        if ($useAuth) {
            $headers['Authorization'] = 'Bearer '.$this->getAccessToken();
        }

        return $headers;
    }

    protected function createConnection(): void
    {
        $this->http = IlluminateHttp::timeout(30)->withoutVerifying();
    }

    public function checkForError(array $response):void
    {
        if (isset($response['code']) && $response['code'] != "00" && isset($response['description'])) {
			throw new ApiException($response['description']);
		}
    }
}
