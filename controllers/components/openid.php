<?php
use Symfony\Component\Yaml\Yaml;
class OpenidComponent extends Component
{
    var $components = array('Auth', 'Session');
    var $domains, $client_id, $client_secret, $scopes;

    /**
     * {@inheritdoc}
     */
    function initialize(&$controller) {
        // sauvegarde la référence du contrôleur pour une utilisation ultérieure
        $this->controller =& $controller;
        $this->Auth->allowedActions = array('*');  
        $this->_getParameters();
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
        $this->client_secret = $values['secret_id'];;
        $this->domains = $values['domains'];
        $this->scopes = $values['scopes'];
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
                        $this->Session->setFlash('You only can loggin with you\'r Isart email account', 'flash_bad');
                    }
                } else {
                    $this->Session->setFlash('Authentification Google failed. CSRF Token invalid', 'flash_bad');
                }
            } else {
                $this->Session->setFlash('Authentification Google failed. ID Token not verified', 'flash_bad');
            }

            return '/';
        }
        $client->setState($this->_getState());

        return $client->createAuthUrl();
    }

    /**
     * Return google client object
     */
    function _getGoogleClient()
    {
        $redirect_uri = 'http://'.$_SERVER['HTTP_HOST'].'/openid/authentification';
        $client       = new Google_Client();
        $client->setClientId($this->client_id);
        $client->setClientSecret($this->secret_id);
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
     * Log User Only if domain of email address contain isartdigital.com or student.isartdigital.com
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
        $length        = count($this->domains);
        $i             = 0;
        for ($i; $i <= $length; $i++) {
            if ($this->domains[$i] == $emailExplosed[1]) {

                return $emailExplosed[0];
            }
        }

        return false;
    }
}