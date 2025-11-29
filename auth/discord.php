<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../assets/config/config.php';
require __DIR__ . '/../assets/config/database.php';

use League\OAuth2\Client\Provider\GenericProvider;
use App\Helpers\ProtocolDetection;

// Validate Discord OAuth credentials are configured
$discordClientId = $_ENV['DISCORD_CLIENT_ID'] ?? '';
$discordClientSecret = $_ENV['DISCORD_CLIENT_SECRET'] ?? '';

if (empty($discordClientId) || empty($discordClientSecret)) {
    exit('Discord OAuth is not configured. Please set DISCORD_CLIENT_ID and DISCORD_CLIENT_SECRET in your .env file.');
}

$provider = new GenericProvider([
    'clientId'                => $discordClientId,
    'clientSecret'            => $discordClientSecret,
    'redirectUri'             => ProtocolDetection::buildRedirectUri('auth/callback.php'),
    'urlAuthorize'            => 'https://discord.com/api/oauth2/authorize',
    'urlAccessToken'          => 'https://discord.com/api/oauth2/token',
    'urlResourceOwnerDetails' => 'https://discord.com/api/users/@me',
]);

$authorizationUrl = $provider->getAuthorizationUrl([
    'scope' => ['identify']
]);

session_start();
$_SESSION['oauth2state'] = $provider->getState();
$_SESSION['oauth2state_time'] = time();

header('Location: ' . $authorizationUrl);
exit;
