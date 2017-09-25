<?php
use Symfony\Component\Yaml\Yaml;

class OpenidComponent extends Component
{
    var $components = array('Auth', 'Session');
    var $client_id, $client_secret, $scopes, $cakeLog = true, $domains = null, $logPath = null, $flashCtp = 'flash_bad';
    var $client, $googleplus;

    /**
     * {@inheritdoc}
     */
    function initialize(&$controller)
    {
        // sauvegarde la référence du contrôleur pour une utilisation ultérieure
        $this->controller =& $controller;
        $this->Auth->allowedActions = array('*');
        $this->getParameters();
    }

    function startup(&$controller)
    {
        // sauvegarde la référence du contrôleur pour une utilisation ultérieure
        $this->controller =& $controller;
    }

    function beforeRedirect()
    {

    }

    /**
     * Configure varaibles from config/parameters.yml
     * @Return void
     */
    function getParameters()
    {
        //load config file parameters
        $values = Yaml::parse(file_get_contents(APP.'plugins/openid/config/parameters.yml'));

        $values = $values['OpenidConnect'];
        $this->client_id = $values['client_id'];
        $this->client_secret = $values['secret_id'];
        $this->scopes = $values['scopes'];

        if (isset($values['domains']))
            $this->domains = $values['domains'];
        if (isset($values['is_work_domain']))
            $this->isWorkDomain = $values['is_work_domain'];
        if (isset($values['log_path']))
            $this->logPath = $values['log_path'];
        if (isset($values['cake_log']))
            $this->cakeLog = $values['cake_log'];
        if (isset($values['flash_ctp']))
            $this->flashCtp = $values['flash_ctp'];
    }

    /**
     * Authentification OpenId controller
     * This function is call two times.
     *      1) When you want to request an authorization
     *      2) When google OpenId connect response you with a $code parameters
     * @Return string url to redirect (two options): 1- redirect to google 2- redirect to homepage
     */
    function authentification()
    {
        $client = $this->getGoogleClient();
        if (!$client)
        {
            $this->Session->setFlash('Authentification Google failed. Invalid client.', $this->flashCtp);
            $this->logAction('Authentification Google failed. Invalid client.');
        }

        if (isset($this->controller->params['url']['code']))
        {
            $code   = $this->controller->params['url']['code'];
            try
            {
                $client->authenticate($code);
            }
            catch(Exception $e)
            {
                $this->Session->setFlash('Authentification Google failed', $this->flashCtp);
                $this->logAction('Authentification Google failed : '.serialize($e));
            }

            //Check check state
            if ($this->Session->read('state') === $this->controller->params['url']['state'])
            {
                //remove state from user session
                $this->Session->delete('state');

                if (!$this->logUser())
                {
                    $this->controller->Session->setFlash('You only can loggin with you\'r Isart email account', $this->flashCtp);
                    $this->logAction('Unauthorize domain user try to loggin.');
                }
            }
            else
            {
                $this->Session->setFlash('Authentification Google failed. State invalid', $this->flashCtp);
                $this->logAction('Authentification Google failed. State invalid.');
            }


            $this->controller->redirect('/');
        }

        $client->setState($this->getState());
        $this->controller->redirect($client->createAuthUrl());
    }

    /**
     * Return google client object
     */
    function getGoogleClient()
    {
        $redirect_uri = $this->getProtocol().$_SERVER['HTTP_HOST'].'/openid/oauth/authentification';

        $this->client = new Google_Client();
        $this->client->setClientId($this->client_id);
        $this->client->setClientSecret($this->client_secret);
        $this->client->setRedirectUri($redirect_uri);
        $this->client->setScopes($this->scopes);
        $this->googleplus = new Google_Service_Oauth2($this->client);

        return $this->client;
    }

    /**
     * Generate state parameter to preventing request forgery attacks
     * More informations at https://developers.google.com/identity/protocols/OpenIDConnect#createxsrftoken
     *
     * @Return string
     */
    function getState()
    {
        $state = sha1(openssl_random_pseudo_bytes(1024));
        $this->Session->write('state', $state);
        return $state;
    }

    /**
     * Log User Only if pseudo == (first part of email address)
     * Need to be updated for your own implementation
     *
     * @Param
     * @Return boolean           True, if user is log, False, if user don't have an isart address
     */
    function logUser()
    {
        if (!$this->googleplus) return false;

        $emailOpenIdUser = $this->googleplus->userinfo->get()->email;
        if (!$pseudo = $this->checkDomain($emailOpenIdUser))
            return false;

        $user = $this->controller->Utilisateur->getUserByPseudo($pseudo);
        if($user)
        {
            $this->Session->write('Auth.Utilisateur', $user);
            $this->Session->write('Auth.Utilisateur.is_alive', 1);
            $this->Session->write('Auth.Utilisateur.session_id', $_COOKIE["CAKEPHP"]);
            $this->Session->write('Auth.Utilisateur.access_token', $accessToken);
            $this->Auth->_loggedIn = true;
            $this->controller->set('is_logged', true);

            return true;
        }
        else
        {
            $this->controller->Session->setFlash('Le compte pour l\'utilisateur '.$pseudo.'  introuvable', $this->flashCtp);
            $this->logAction('Le compte pour l\'utilisateur '.$pseudo.'  introuvable. payload: '.serialize($playload));

            return false;
        }
    }

    /**
     * Check the domain of an email.
     * @Param string $email    Email address return by the JWT
     * @Return boolean|string  False, if no isart domain found, user pseudo if yes
     */
    function checkDomain($email)
    {
        $emailExplosed = explode('@', $email);
        if(empty($this->domains)) return $emailExplosed[0];

        $length = count($this->domains);
        for ($i = 0; $i <= $length; $i++)
            if ($this->domains[$i] == $emailExplosed[1])
                return $emailExplosed[0];

        return false;
    }

    function logAction($log)
    {
        if ($this->cakeLog)
        {
            $this->log($log);
        }
        if ($this->logPath)
        {
            //log into file set in $logPath
        }
    }

    function getProtocol()
    {
        return ( !empty($_SERVER['HTTPS']) && $_SERVER['SERVER_PORT'] == 443 ) ? "https://" : 'http://';
    }
}