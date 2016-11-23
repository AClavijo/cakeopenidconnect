<?php
use Symfony\Component\Yaml\Yaml;
class OpenidComponent extends Component
{
    var $components = array('Auth', 'Session');
    var $client_id, $client_secret, $scopes, $cakeLog = true, $domains = null, $isWorkDomain = false, $logPath = null, $flashCtp = 'flash_bad';

    /**
     * {@inheritdoc}
     */
    function initialize(&$controller) {
        // sauvegarde la référence du contrôleur pour une utilisation ultérieure
        $this->controller =& $controller;
        $this->Auth->allowedActions = array('*');  
        $this->_getParameters();
    }

    function beforeRedirect()
    {
        
    }

    /**
     * Configure varaibles from config/parameters.yml
     * @Return void
     */
    function _getParameters()
    {
        //load config file parameters
        $values = Yaml::parse(file_get_contents(APP.'plugins/openid/config/parameters.yml'));
        $values = $values['OpenidConnect'];
        $this->client_id = $values['client_id'];
        $this->client_secret = $values['secret_id'];
        $this->scopes = $values['scopes'];
        if (isset($values['domains'])) {
            $this->domains = $values['domains'];
        }
        if (isset($values['is_work_domain'])) {
            $this->isWorkDomain = $values['is_work_domain'];
        }
        if (isset($values['log_path'])) {
            $this->logPath = $values['log_path'];
        }
        if (isset($values['cake_log'])) {
            $this->cakeLog = $values['cake_log'];
        }
        if (isset($values['flash_ctp'])) {
            $this->flashCtp = $values['flash_ctp'];
        }
    }

    /**
     * Authentification OpenId controller
     * This function is call two times.
     *      1) When you want to request an authorization
     *      2) When google OpenId connect response you with a $code parameters
     * @Return string           url to redirect (two options): 1- redirect to google 2- redirect to homepage
     */
    function authentification()
    {
        $client = $this->_getGoogleClient();

        if (isset($this->controller->params['url']['code'])) {
            $code   = $this->controller->params['url']['code'];
            $client = $this->_getGoogleClient();
            $token  = $client->fetchAccessTokenWithAuthCode($code);
            $client->setAccessToken($token);
            if ($playload = $client->verifyIdToken()) {
                //Check check state
                if ($this->Session->read('state') == $this->controller->params['url']['state']) {
                    //remove state from user session
                    $this->Session->delete('state');
                    if (!$this->_logUser($playload)) {
                        $this->Session->setFlash('You only can loggin with you\'r Isart email account', $this->flashCtp);
                        $this->_logAction('Unauthorize domain user try to loggin. payload: '.serialize($playload));
                    }
                } else {
                    $this->Session->setFlash('Authentification Google failed. CSRF Token invalid', $this->flashCtp);
                    $this->_logAction('Authentification Google failed. CSRF Token invalid. payload: '.serialize($playload));
                }
            } else {
                $this->Session->setFlash('Authentification Google failed. ID Token not verified', $this->flashCtp);
                $this->_logAction('Authentification Google failed. ID Token not verified. payload: '.serialize($playload));
            }

            $this->controller->redirect('/');
        }
        $client->setState($this->_getState());

        $this->controller->redirect($client->createAuthUrl());
    }

    /**
     * Return google client object
     */
    function _getGoogleClient()
    {

        $redirect_uri = $this->_get_http_protocol().$_SERVER['HTTP_HOST'].'/openid/oauth/authentification';
        $client       = new Google_Client();
        $client->setClientId($this->client_id);
        $client->setClientSecret($this->client_secret);
        $client->setRedirectUri($redirect_uri);
        $client->setScopes($this->scopes);

        return $client;
    }

    /**
     * Generate state parameter to preventing request forgery attacks
     * More informations at https://developers.google.com/identity/protocols/OpenIDConnect#createxsrftoken
     *
     * @Return string
     */
    function _getState()
    {
        $state = sha1(openssl_random_pseudo_bytes(1024));
        $this->Session->write('state', $state);

        return $state;
    }

    /**
     * Log User Only if pseudo == (first part of email address)
     * Need to be updated for your own implementation
     * 
     * @Param array $payload     Payload return in the JWT by Google OpenId connect
     * @Return boolean           True, if user is log, False, if user don't have an isart address
     */
    function _logUser($playload)
    {
        $emailOpenIdUser = $playload['email'];
        if (!$pseudo = $this->_checkDomain($emailOpenIdUser)) {

            return false;
        }
        $user = $this->controller->Utilisateur->getUserByPseudo($pseudo);
        $this->Session->write('Auth.Utilisateur', $user);
        $this->Auth->_loggedIn = true;

        return true;
    }

    /**
     * Check the domain of an email.
     * @Param string $email    Email address return by the JWT
     * @Return boolean|string  False, if no isart domain found, user pseudo if yes
     */
    function _checkDomain($email)
    {
        $emailExplosed = explode('@', $email);
        if ($isWorkDomain) {
            return $emailExplosed[0];
        }
        if(empty($domains)){
            return $emailExplosed[0];
        }
        $length = count($this->domains);
        $i      = 0;
        for ($i; $i <= $length; $i++) {
            if ($this->domains[$i] == $emailExplosed[1]) {

                return $emailExplosed[0];
            }
        }

        return false;
    }

    function _logAction($log)
    {
        if ($this->cakeLog) {
            //log the $log string in cake
            $this->log($log);
        }
        if ($this->logPath) {
            //log into file set in $logPath
        }
    }
    
    function _get_http_protocol()
    { 
        if ( !empty($_SERVER['HTTPS']) && $_SERVER['SERVER_PORT'] == 443) {
            return "https://"; 
        } else {
            return 'http://';
        }
            
    } 
}