<?php

namespace MauticPlugin\MauticEhloWorldBundle\EventListener;

use Mautic\PluginBundle\Helper\IntegrationHelper;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * This only works with a single mailer_dsn for all users.
 *
 * @author Dominic Mayers
 */
class RequestListener implements EventSubscriberInterface
{
    protected $integration;

    public function __construct(
        protected IntegrationHelper $integrationHelper,
    ) {
        // This statement could be moved in a more basic file of the plugin that that takes care of this stuff.
        // This needs to be adapted for a local instance.
        $confFile = 'config/.env.tokens.local';
        if (file_exists($confFile)) {
            (new Dotenv())->loadEnv($confFile, overrideExistingVars: true);
        } else {
            date_default_timezone_set('America/montreal');
            $debugFile    = 'var/logs/hellworldebug_'.date('d_H:i:s').'.log';
            $debugMessage = "The configuration file $confFile does not exist.".PHP_EOL;
            file_put_contents($debugFile, $debugMessage, FILE_APPEND);
        }
        $this->integration  = $integrationHelper->getIntegrationObject('GmailSmtp');
    }

    public static function getSubscribedEvents(): array
    {
        return [
            RequestEvent::class => ['onKernelRequest', 300],
        ];
    }

    public function onKernelRequest(RequestEvent $event)
    {
        // LOGGING and DEBUGGING

        date_default_timezone_set('America/montreal');
        $debugFile    = 'var/logs/hellworldebug_'.date('d_H:i:s').'.log';
        $infoFile     = 'var/logs/helloworld_'.date('d').'.log';
        $logMessage   = $debugMessage = '';

        $request  = $event->getRequest(); // of class Symfony\Component\HttpFoundation\Request
        $url      = $request->getRequestUri(); // includes the queru string

        // Requests with no extension are more than enough for our purpose.
        $extension = \pathinfo($request->getPathInfo(), PATHINFO_EXTENSION);
        if ($extension) {
            // LOGGING
            $logMessage .= date('H:i:s').': '.$url." has a $extension extension.".PHP_EOL;
            file_put_contents($infoFile, $logMessage, FILE_APPEND);

            return;
        }

        if (!isset($_ENV['EXPIRES_AT'])) {
            // DEBUGGING-
            $debugMessage .= 'EXPIRES_AT environment variable not defined.'.PHP_EOL;
            file_put_contents($debugFile, $debugMessage, FILE_APPEND);

            return;
        }
        $max_time = max(ini_get('max_execution_time'), get_cfg_var('max_execution_time'));
        if (time() + $max_time < $_ENV['EXPIRES_AT'] + 10) {
            // LOGGING
            // $logMessage .= date('H:i:s').': Access token is still valid'.PHP_EOL;
            // file_put_contents($infoFile, $logMessage, FILE_APPEND);
            return;
        }
        // LOGGING
        $logMessage .= date('H:i:s').': Refreshing access token... ';

        // get api_key from plugin settings for client_id and client_secret.
        try {
            $keys = $this->integration->getDecryptedApiKeys();
        } catch (\Exception $e) {
            // DEBUGGING
            $debugMessage .= 'Could not connect with GmailSmtp integration.'.PHP_EOL;
            file_put_contents($debugFile, $debugMessage, FILE_APPEND);

            return;
        }
        if (!isset($keys['client_id'])) {
            // DEBUGGING
            $debugMessage .= 'The client_id key is missing.'.PHP_EOL;
            file_put_contents($debugFile, $debugMessage, FILE_APPEND);

            return;
        } else {
            $client_id = $keys['client_id'];
        }
        if (!isset($keys['client_secret'])) {
            // DEBUGGING
            $debugMessage .= 'The client_secret key is missing.'.PHP_EOL;
            file_put_contents($debugFile, $debugMessage, FILE_APPEND);

            return;
        } else {
            $client_secret = $keys['client_secret'];
        }

        if (!isset($_ENV['REFRESH_TOKEN'])) {
            // DEBUGGING
            $debugMessage .= 'The environment variable REFRESH_TOKEN is not defined.'.PHP_EOL;
            file_put_contents($debugFile, $debugMessage, FILE_APPEND);

            return;
        } else {
            $refresh_token = $_ENV['REFRESH_TOKEN'];
        }

        if (isset($_ENV['REFRESH_EXPIRES_AT']) && time() > $_ENV['REFRESH_EXPIRES_AT']) {
            // DEBUGGING
            $debugMessage .= 'Since '.date('Y_m_d_H:i:s', $_ENV['REFRESH_EXPIRES_AT']).' the refresh token is expired.'.PHP_EOL;
            file_put_contents($debugFile, $debugMessage, FILE_APPEND);

            return;
        }

        // Curl request to get a new access token
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
            curl_setopt(
                $ch,
                CURLOPT_POSTFIELDS,
                "access_type=offline&refresh_token=$refresh_token&client_id=$client_id&client_secret=$client_secret&grant_type=refresh_token"
            );
            $resp      = curl_exec($ch);
            $curlError = curl_error($ch);
            curl_close($ch);
        } catch (\Exception $e) {
            // DEBUGGING
            $debugMessage .= 'Could not curl to Google authorization server.'.PHP_EOL;
            $debugMessage .= $e->getMessage().PHP_EOL;
            file_put_contents($debugFile, $debugMessage, FILE_APPEND);

            return;
        }

        $payload = json_decode($resp, true);
        if (!isset($payload['access_token'])) {
            // DEBUGGING
            $debugMessage .= 'No access_token in authorization response.'.PHP_EOL;
            $debugMessage .= 'Authorization response: '.$resp.PHP_EOL;
            $debugMessage .= 'Curl error: '.$curlError.PHP_EOL;
            file_put_contents($debugFile, $debugMessage, FILE_APPEND);

            return;
        }
        $_ENV['ACCESS_TOKEN'] = $payload['access_token'];

        if (!isset($payload['expires_in'])) {
            // DEBUGGING
            $debugMessage .= 'No expires-in in authorization response'.PHP_EOL;
            file_put_contents($debugFile, $debugMessage, FILE_APPEND);

            return;
        }
        $_ENV['EXPIRES_AT']   = $payload['expires_in'] + time();

        if (!isset($_ENV['GMAIL_USER'])) {
            // DEBUGGING
            $debugMessage .= 'The environment variable GMAIL_USER is not defined.'.PHP_EOL;
            file_put_contents($debugFile, $debugMessage, FILE_APPEND);

            return;
        }

        // Store ACCESS_TOKEN, EXPIRES_AT, MAILER_DSN, etc. in .env.tokens.local
        if (isset($_SERVER['MAUTIC_NAME'])) {
            $confFile   = 'config/'.$_SERVER['MAUTIC_NAME'].'/.env.tokens.local';
        } else {
            $confFile   = 'config/.env.tokens.local';
        }
        if (!file_exists($confFile)) {
            $debugMessage .= "The configuration file $confFile does not exist.".PHP_EOL;
            file_put_contents($debugFile, $debugMessage, FILE_APPEND);

            return;
        }

        $newconfig  = "# This file is automatically rewritten each time the access token is renewed.".PHP_EOL;
        $newconfig .= 'ACCESS_TOKEN='.$_ENV['ACCESS_TOKEN'].PHP_EOL;
        $newconfig .= 'EXPIRES_AT='.$_ENV['EXPIRES_AT'].PHP_EOL.PHP_EOL;
        $newconfig .= "# If the following values are modified, they will not be rewritten to their original value when the access token is renewed.".PHP_EOL;
        $newconfig .= "# This could prevent the use of the Gmail transport until the GMAIL user re-authorizes its use.".PHP_EOL;
        $newconfig .= 'GMAIL_USER='.$_ENV['GMAIL_USER'].PHP_EOL;
        $newconfig .= 'CLIENT_ID='.$client_id.PHP_EOL;
        $newconfig .= 'CLIENT_SECRET='.$client_secret.PHP_EOL;
        $newconfig .= 'REFRESH_TOKEN='.$_ENV['REFRESH_TOKEN'].PHP_EOL;
        if (isset($_ENV['REFRESH_EXPIRES_AT'])) {
            $newconfig .= '# Expires '.date('l jS \o\f F Y H:i:s', $_ENV['REFRESH_EXPIRES_AT']).PHP_EOL;
            $newconfig .= 'REFRESH_EXPIRES_AT='.$_ENV['REFRESH_EXPIRES_AT'].PHP_EOL;
        }


        // This does not work as a way to define the parameter $mailer_dsn in local.php.
        //$mailer_dsn = 'smtp://'.urlencode($_ENV['GMAIL_USER']).':'.$_ENV['ACCESS_TOKEN'].'@smtp.gmail.com:465';
        //$newconfig .= 'MAILER_DSN='.$mailer_dsn.PHP_EOL;
        // So we use this workaround.
        try {
            file_put_contents($confFile, $newconfig);
        } catch (\Exception $e) {
            // DEBUGGING
            $debugMessage .= $e->getMessage().PHP_EOL;
            file_put_contents($debugFile, $debugMessage, FILE_APPEND);

            return;
        }
        // This is a workaround for not being able to use %env(ENV_VARIABLE)% in mailer_dsn, which is likely a bug.
        // Uppdate a mailer_dsn include file and make sure it is included in local.php.
        if (isset($_SERVER['MAUTIC_NAME'])) {
            $includeFile = 'config/'.$_SERVER['MAUTIC_NAME'].'/new_mailer_dsn.php';
        } else {
            $includeFile = 'config/new_mailer_dsn.php';
        }
        $mailer_dsn  = 'smtp://'.urlencode($_ENV['GMAIL_USER']).':'.$_ENV['ACCESS_TOKEN'].'@smtp.gmail.com:465';
        $content     = "<?php".PHP_EOL;
        $content .= '$parameters[\'mailer_dsn\'] = '."'$mailer_dsn';".PHP_EOL;
        $content .= '$parameters[\'mailer_from_email\'] = '."'{$_ENV['GMAIL_USER']}';".PHP_EOL;
        try {
            file_put_contents($includeFile, $content);
        } catch (\Exception $e) {
            // DEBUGGING
            $debugMessage .= $e->getMessage().PHP_EOL;
            file_put_contents($debugFile, $debugMessage, FILE_APPEND);
            return;
        }

        if (isset($_SERVER['MAUTIC_NAME'])) {
            $configFile       = 'config/'.$_SERVER['MAUTIC_NAME'].'/local.php';
        } else {
            $configFile       = 'config/local.php';
        }
        $lastLine         = shell_exec("tail -n 1 $configFile");
        $lastLine         = trim($lastLine);
        $expectedLastLine = "include('$includeFile');";
        if ($lastLine !== $expectedLastLine) {
            try {
                file_put_contents($configFile, "\n$expectedLastLine", FILE_APPEND);
            } catch (\Exception $e) {
                // DEBUGGING
                $debugMessage .= $e->getMessage().PHP_EOL;
                file_put_contents($debugFile, $debugMessage, FILE_APPEND);
                return;
            }
        }
        // LOGGING
        $logMessage .= 'Access token refreshed.'.PHP_EOL;

        // Create a RedirectResponse to the same URL
        $response = new RedirectResponse($url);

        // LOGGING
        file_put_contents($infoFile, $logMessage, FILE_APPEND);

        // Set the response (and stop the event propagation)
        $event->setResponse($response);
    }
}
