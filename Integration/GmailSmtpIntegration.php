<?php

namespace MauticPlugin\MauticEhloWorldBundle\Integration;

use Mautic\PluginBundle\Integration\AbstractIntegration;

class GmailSmtpIntegration extends AbstractIntegration
{
    public function getName(): string
    {
        return 'GmailSmtp';
    }

    public function getDisplayName(): string
    {
        return 'Gmail Smtp';
    }

    public function getAuthenticationType(): string
    {
        return 'oauth2';
    }

    /**
     * Get the URL required to obtain an oauth2 access token.
     */
    public function getAccessTokenUrl(): string
    {
        return 'https://oauth2.googleapis.com/token';
    }

    /**
     * Generate the auth login URL.  Note that if oauth2, response_type=code is assumed.  If this is not the case,
     * override this function.
     *
     * @return string
     */
    public function getAuthLoginUrl()
    {
        // file_put_contents(TMP, date('Y_M_D_H:i:s').": The auth url is ".parent::getAuthLoginUrl() . "&access_type=offline&prompt=consent".PHP_EOL);
        return parent::getAuthLoginUrl() . "&access_type=offline&prompt=consent";
    }

    /**
     * Get the authentication/login URL for oauth2 access.
     */
    public function getAuthenticationUrl(): string
    {
        return 'https://accounts.google.com/o/oauth2/auth';
    }

    /**
     * Get the scope for auth flows.
     *
     * @return string
     */
    public function getAuthScope()
    {
        return 'https://mail.google.com/ email';
    }

    /**
     * Retrieves and stores tokens returned from oAuthLogin.
     *
     * @param array $settings
     * @param array $parameters
     *
     * @return bool|string false if no error; otherwise the error string
     */
    public function authCallback($settings = [], $parameters = [])
    {
        $error = parent::authCallback($settings, $parameters);
        if ($error) {
            return $error;
        }
        $keys = $this->getDecryptedApiKeys();
        $idKeys = json_decode(base64_decode(str_replace('_', '/', str_replace('-', '+', explode('.', $keys['id_token'])[1]))), true);

        // LOGGING and DEBUGGING
        date_default_timezone_set('America/montreal');
        $debugFile    = 'var/logs/gmail_smtp_debug_'.date('d_H:i:s').'.log';
        $infoFile     = 'var/logs/gmail_smtp_'.date('d').'.log';
        $logMessage   = $debugMessage = '';

        // DEBUGGING (TO BE REMOVED)
        $keysText = "";
        foreach ($keys as $key => $value) {
            $keysText .= "$key => $value". PHP_EOL;
        }
        foreach ($idKeys as $key => $value) {
            $keysText .= "id_$key => $value". PHP_EOL;
        }
        file_put_contents('/home/dominic/tmp/temp.log', $keysText);

        // Do my stuff here.
        if (!isset($keys['client_id'])) {
            // DEBUGGING
            $debugMessage .= 'The client_id key is missing.'."\n";
            file_put_contents($debugFile, $debugMessage, FILE_APPEND);

            return $debugMessage;
        }
        $client_id = $keys['client_id'];

        if (!isset($keys['client_secret'])) {
            // DEBUGGING
            $debugMessage .= 'The client_secret key is missing.'."\n";
            file_put_contents($debugFile, $debugMessage, FILE_APPEND);

            return $debugMessage;
        }
        $client_secret = $keys['client_secret'];

        if (!isset($idKeys['email'])) {
            // DEBUGGING
            $debugMessage .= 'The email key is missing in id_token.'."\n";
            file_put_contents($debugFile, $debugMessage, FILE_APPEND);

            return $debugMessage;
        }
        $gmail_user = $idKeys['email'];

        if (!isset($keys['access_token'])) {
            // DEBUGGING
            $debugMessage .= 'The access_token key is missing.'."\n";
            file_put_contents($debugFile, $debugMessage, FILE_APPEND);

            return $debugMessage;
        }
        $access_token = $keys['access_token'];

        if (!isset($keys['expires_in'])) {
            // DEBUGGING
            $debugMessage .= 'The expires_in key is missing.'."\n";
            file_put_contents($debugFile, $debugMessage, FILE_APPEND);

            return $debugMessage;
        }
        $expires_at   = $keys['expires_in'] + time();

        if (!isset($keys['refresh_token'])) {
            // DEBUGGING
            $debugMessage .= 'The refresh_token key is missing.'."\n";
            file_put_contents($debugFile, $debugMessage, FILE_APPEND);

            return $debugMessage;
        }
        $refresh_token = $keys['refresh_token'];

        $refresh_token_expires_at = isset($keys['refresh_token_expires_in']) ? $keys['refresh_token_expires_in'] + time() : false;

        // Store ACCESS_TOKEN, EXPIRES_AT, MAILER_DSN, etc. in .env.tokens.local
        $instance_name_dir = isset($_SERVER['MAUTIC_NAME']) ? $_SERVER['MAUTIC_NAME'] . '/' : '';
        $confFile          = 'config/'.$instance_name_dir.'.env.tokens.local';

        if (!file_exists($confFile) && !touch($confFile)) {
            $debugMessage .= "The configuration file $confFile did not exist and could not be created.".PHP_EOL;
            file_put_contents($debugFile, $debugMessage, FILE_APPEND);
            return $debugMessage;
        }

        $newconfig  = "# This file is automatically rewritten each time the access token is renewed.\n";
        $newconfig .= 'ACCESS_TOKEN='.$access_token.PHP_EOL;
        $newconfig .= 'EXPIRES_AT='.$expires_at.PHP_EOL.PHP_EOL;
        $newconfig .= "# If the following values are modified, they will not be rewritten to their original value when the access token is renewed.".PHP_EOL;
        $newconfig .= "# This could prevent the use of the Gmail transport until the GMAIL user re-authorizes its use.".PHP_EOL;
        $newconfig .= 'GMAIL_USER='.$gmail_user.PHP_EOL;
        $newconfig .= 'CLIENT_ID='.$client_id.PHP_EOL;
        $newconfig .= 'CLIENT_SECRET='.$client_secret.PHP_EOL;
        $newconfig .= 'REFRESH_TOKEN='.$refresh_token.PHP_EOL;
        if ($refresh_token_expires_at) {
            $newconfig .= '# Expires '.date('l jS \o\f F Y H:i:s', $refresh_token_expires_at).PHP_EOL;
            $newconfig .= 'REFRESH_EXPIRES_AT='.$refresh_token_expires_at.PHP_EOL;
        }

        try {
            file_put_contents($confFile, $newconfig);
        } catch (\Exception $e) {
            // DEBUGGING
            $debugMessage .= $e->getMessage()."\n";
            file_put_contents($debugFile, $debugMessage, FILE_APPEND);

            return $debugMessage;
        }

        // This does not work as a way to define the parameter $mailer_dsn in local.php.
        // It is not possible to use %env(ENV_VARIABLE)% in mailer_dsn, which is likely a bug.
        //$mailer_dsn = 'smtp://'.urlencode($_ENV['GMAIL_USER']).':'.$_ENV['ACCESS_TOKEN'].'@smtp.gmail.com:465';
        //$newconfig .= 'MAILER_DSN='.$mailer_dsn.PHP_EOL;

        // This is a workaround:
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
        } catch (\Exception $e) {
            // DEBUGGING
            $debugMessage .= $e->getMessage()."\n";
            file_put_contents($debugFile, $debugMessage, FILE_APPEND);
            return $debugMessage;
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
                return $debugMessage;
            }
        }
        
        // We unset the newly obtained keys, because it can create issues, keeping only:
        $newKeys['client_id']     = $keys['client_id'];
        $newKeys['client_secret'] = $keys['client_secret'];
        $apiKeys                  = $this->encryptApiKeys($newKeys);
    
        // Save (again) the data
        $entity = $this->getIntegrationSettings();
        $entity->setApiKeys($apiKeys);
        $this->em->persist($entity);
        $this->em->flush();
        $this->setIntegrationSettings($entity);

        // LOGGING
        $logMessage .= date('H:i:s').': New access and refresh tokens for '.$gmail_user.' installed.'."\n";
        file_put_contents($infoFile, $logMessage, FILE_APPEND);

        return false;
    }
}
