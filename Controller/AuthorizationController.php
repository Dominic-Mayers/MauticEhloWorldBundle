<?php

namespace MauticPlugin\MauticHelloWorldBundle\Controller;

use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\PluginBundle\Helper\IntegrationHelper;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
//use Symfony\Component\Dotenv\Dotenv;

class AuthorizationController extends AbstractController
{

    public function __construct(
        protected IntegrationHelper $integrationHelper,
        private CoreParametersHelper $coreParametersHelper,
    ) {
        // This statement could be moved in a more basic file of the plugin that that takes care of this stuff.
        //(new Dotenv())->loadEnv('config/sherbrooke/.env.tokens.local', overrideExistingVars: true);
        $this->integration  = $integrationHelper->getIntegrationObject('Helloworld');
    }

    public function authorize_client(ClientRegistry $clientRegistry)
    {
        file_put_contents('/home/dominic/app_devel/mautic/var/temp.log', 'Here in authorize_client'.PHP_EOL, FILE_APPEND); 
        return $clientRegistry->getClient('google')->redirect(
            ['email', 'profile', 'openid', 'https://mail.google.com/'],
            ['prompt' => 'consent']
        );
    }

    public function connectCheckAction(Request $request, ClientRegistry $clientRegistry)
    {
        file_put_contents('/home/dominic/app_devel/mautic/var/temp.log', 'Here in connectCheckAction'.PHP_EOL, FILE_APPEND);
        // We need to store in .env.tokens.local: 
        // CLIENT_ID
        // CLIENT_SECRET
        // SITE_DIR_URL
        // ACCESS_TOKEN
        // GMAIL_USER
        // EXPIRES_AT
        // REFRESH_TOKEN
        // REFRESH_EXPIRES_AT
        
        // LOGGING and DEBUGGING
        date_default_timezone_set('America/montreal');
        $debugFile    = 'var/logs/hellworldebug_'.date('d_H:i:s').'.log';
        $infoFile     = 'var/logs/helloworld_'.date('d').'.log';
        $logMessage   = $debugMessage = '';
    
        // LOGGING
        $client       = $clientRegistry->getClient('google');
        $access_token = $client->getAccessToken();
        $expires_at   = $access_token->getExpires(); 

        // It is not possible to directly fetch the user
        // once we have fetched the access_token.
        $gmail_user = $client->fetchUserFromToken($access_token)->getEmail(); 
        $refresh_token = $access_token->getRefreshToken();
        if (!$refresh_token) {
            // DEBUGGING
            $debugMessage .= 'Failed to obtain a new refresh token.'."\n";
            file_put_contents($debugFile, $debugMessage, FILE_APPEND);

            return;
        }
        $refresh_expires_in = $access_token->getValues()['refresh_token_expires_in']; 
        $refresh_token_expires_at = isset($refresh_expires_in) ? $refresh_expires_in + time() : false;         

        // get api_key from plugin settings for client_id and client_secret.
        $keys = $this->integration->getDecryptedApiKeys();
        if (!isset($keys['client_id'])) {
            // DEBUGGING
            $debugMessage .= 'The client_id key is missing.'."\n";
            file_put_contents($debugFile, $debugMessage, FILE_APPEND);

            return;
        } else {
            $client_id = $keys['client_id'];
        }
        if (!isset($keys['client_secret'])) {
            // DEBUGGING
            $debugMessage .= 'The client_secret key is missing.'."\n";
            file_put_contents($debugFile, $debugMessage, FILE_APPEND);

            return;
        } else {
            $client_secret = $keys['client_secret'];
        }
        
        // Store ACCESS_TOKEN, EXPIRES_AT, MAILER_DSN, etc. in .env.tokens.local
        $site_dir_url      = dirname($this->coreParametersHelper->get('site_url'));
        $instance_name_dir = isset($_SERVER['MAUTIC_NAME']) ? $_SERVER['MAUTIC_NAME'] . '/' : ''; 
        $confFile          = 'config/'.$instance_name_dir.'.env.tokens.local';


        $newconfig  = "# This file is automatically rewritten each time the access token is renewed.\n";
        $newconfig .= 'ACCESS_TOKEN='.$access_token.PHP_EOL;
        $newconfig .= 'EXPIRES_AT='.$expires_at.PHP_EOL.PHP_EOL;
        $newconfig .= "# If the following values are modified, they will not be rewritten to their original value when the access token is renewed.".PHP_EOL; 
        $newconfig .= "# This could prevent the use of the Gmail transport until the GMAIL user re-authorizes its use.".PHP_EOL; 
        $newconfig .= 'GMAIL_USER='.$gmail_user.PHP_EOL;
        $newconfig .= 'CLIENT_ID='.$client_id.PHP_EOL;
        $newconfig .= 'CLIENT_SECRET='.$client_secret.PHP_EOL;
        $newconfig .= 'SITE_DIR_URL='.$site_dir_url.PHP_EOL;
        $newconfig .= 'REFRESH_TOKEN='.$refresh_token.PHP_EOL;
        if ($refresh_token_expires_at ) { 
            $newconfig .= '# Expires '.date('l jS \o\f F Y H:i:s', $refresh_token_expires_at).PHP_EOL;  
            $newconfig .= 'REFRESH_EXPIRES_AT='.$refresh_token_expires_at.PHP_EOL;
        }

        // This does not work as a way to define the parameter $mailer_dsn in local.php. See workaround below. 
        //$mailer_dsn = 'smtp://'.urlencode($_ENV['GMAIL_USER']).':'.$_ENV['ACCESS_TOKEN'].'@smtp.gmail.com:465';
        //$newconfig .= 'MAILER_DSN='.$mailer_dsn.PHP_EOL;
        try {
            file_put_contents($confFile, $newconfig);
        } catch (\Exception $e) {
            // DEBUGGING
            $debugMessage .= $e->getMessage()."\n";
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
        $mailer_dsn  = 'smtp://'.urlencode($gmail_user).':'.$access_token.'@smtp.gmail.com:465';
        $content     = "<?php\n";
        $content .= '$parameters[\'mailer_dsn\'] = '."'$mailer_dsn';\n";
        $content .= '$parameters[\'mailer_from_email\'] = '."'$gmail_user';\n";
        try {
            file_put_contents($includeFile, $content);
        } catch (\Exception $e ) {
            // DEBUGGING
            $debugMessage .= $e->getMessage()."\n";
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
                // DEBUGGINGdate('Y_m_d_H:i:s')
                $debugMessage .= $e->getMessage()."\n";
                file_put_contents($debugFile, $debugMessage, FILE_APPEND);        
                return; 
            }
        }
        // LOGGING
        $logMessage .= date('H:i:s').': New access and refresh tokens for '.$gmail_user.' installed.'."\n";
        file_put_contents($infoFile, $logMessage, FILE_APPEND);
        
        $url = "https://loc.tmorg.ca/mautic/sherbrooke/s/dashboard"; 
        return new RedirectResponse($url);
    }
}
