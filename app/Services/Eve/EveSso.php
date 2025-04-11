<?php

namespace App\Services\Eve;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EveSso
{
    /**
     * Generate an authorization URL for EVE SSO
     *
     * @return string
     */
    public function getAuthorizationUrl()
    {
        // Generate a random state value for security
        $state = Str::random(32);
        session(['eve_sso_state' => $state]);

        $params = http_build_query([
            'response_type' => 'code',
            'redirect_uri' => config('eve.callback_url'),
            'client_id' => config('eve.client_id'),
            'scope' => config('eve.scopes'),
            'state' => $state,
        ]);

        return 'https://login.eveonline.com/v2/oauth/authorize?' . $params;
    }

    /**
     * Handle the callback from EVE SSO
     *
     * @param string $code
     * @param string $state
     * @return array|null
     */
    public function handleCallback($code, $state)
    {
        // Verify the state
        if ($state !== session('eve_sso_state')) {
            Log::error('EVE SSO state mismatch');
            return null;
        }

        try {
            $client = new Client();
            $response = $client->post('https://login.eveonline.com/v2/oauth/token', [
                'auth' => [config('eve.client_id'), config('eve.client_secret')],
                'form_params' => [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                ],
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Host' => 'login.eveonline.com'
                ]
            ]);

            $tokenData = json_decode($response->getBody(), true);

            // Get character information
            $characterData = $this->verifyAccessToken($tokenData['access_token']);

            if (!$characterData) {
                return null;
            }

            // Store tokens with character data
            $userData = array_merge($characterData, $tokenData);

            // Cache the tokens for future use
            Cache::put('eve_sso_tokens_' . $userData['CharacterID'], [
                'access_token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'],
                'expires_at' => now()->addSeconds($tokenData['expires_in'] - 60),
            ], $tokenData['expires_in']);

            return $userData;
        } catch (\Exception $e) {
            Log::error('EVE SSO Callback Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Verify an access token and get character information
     *
     * @param string $accessToken
     * @return array|null
     */
    public function verifyAccessToken($accessToken)
    {
        try {
            $client = new Client();
            $response = $client->get('https://login.eveonline.com/oauth/verify', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ]
            ]);

            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            Log::error('EVE SSO Token Verification Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Refresh an expired token
     *
     * @param string $refreshToken
     * @return array|null
     */
    public function refreshToken($refreshToken)
    {
        try {
            $client = new Client();
            $response = $client->post('https://login.eveonline.com/v2/oauth/token', [
                'auth' => [config('eve.client_id'), config('eve.client_secret')],
                'form_params' => [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken,
                ],
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Host' => 'login.eveonline.com'
                ]
            ]);

            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            Log::error('EVE SSO Token Refresh Error: ' . $e->getMessage());
            return null;
        }
    }
}
