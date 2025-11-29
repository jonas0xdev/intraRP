<?php

namespace App\Helpers;

use League\OAuth2\Client\Provider\GenericProvider;

class DiscordOAuth
{
    /**
     * Validate that Discord OAuth credentials are configured
     * 
     * @return array{clientId: string, clientSecret: string}|null Returns credentials if valid, null otherwise
     */
    public static function getCredentials(): ?array
    {
        $clientId = $_ENV['DISCORD_CLIENT_ID'] ?? '';
        $clientSecret = $_ENV['DISCORD_CLIENT_SECRET'] ?? '';

        if (empty($clientId) || empty($clientSecret)) {
            return null;
        }

        return [
            'clientId' => $clientId,
            'clientSecret' => $clientSecret,
        ];
    }

    /**
     * Validate Discord OAuth credentials and exit with error message if not configured
     * 
     * @return array{clientId: string, clientSecret: string} The validated credentials
     */
    public static function validateCredentials(): array
    {
        $credentials = self::getCredentials();

        if ($credentials === null) {
            exit('Discord OAuth is not configured. Please set DISCORD_CLIENT_ID and DISCORD_CLIENT_SECRET in your .env file.');
        }

        return $credentials;
    }

    /**
     * Create a Discord OAuth provider instance
     * 
     * @param string $redirectPath The path for the OAuth redirect (e.g., 'auth/callback.php')
     * @return GenericProvider The configured OAuth provider
     */
    public static function createProvider(string $redirectPath): GenericProvider
    {
        $credentials = self::validateCredentials();

        return new GenericProvider([
            'clientId'                => $credentials['clientId'],
            'clientSecret'            => $credentials['clientSecret'],
            'redirectUri'             => ProtocolDetection::buildRedirectUri($redirectPath),
            'urlAuthorize'            => 'https://discord.com/api/oauth2/authorize',
            'urlAccessToken'          => 'https://discord.com/api/oauth2/token',
            'urlResourceOwnerDetails' => 'https://discord.com/api/users/@me',
        ]);
    }
}
