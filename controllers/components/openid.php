<?php

class OpenidComponent extends Component
{
    var $components = array('Auth', 'Session');

    /**
     * {@inheritdoc}
     */
    function initialize(&$controller) {
        // sauvegarde la référence du contrôleur pour une utilisation ultérieure
        $this->controller =& $controller;
        $this->Auth->allowedActions = array('*');     
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
        $client->setClientId('692158080346-a4g4jns2k966dls4klcti7i8739jp4bt.apps.googleusercontent.com');
        $client->setClientSecret('IO0TPAp13MtqNpW0bEIndjKW');
        $client->setRedirectUri($redirect_uri);
        $client->setScopes(array('openid', 'email', 'profile'));

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
        if (!$pseudo = $this->_checkIsartDomain($emailOpenIdUser)) {

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
    function _checkIsartDomain($email)
    {
        $emailExplosed = explode('@', $email);
        $isartDomains  = array('isartdigital.com', 'student.isartdigital.com');
        $length        = count($isartDomains);
        $i             = 0;
        for ($i; $i <= $length; $i++) {
            if ($isartDomains[$i] == $emailExplosed[1]) {

                return $emailExplosed[0];
            }
        }

        return false;
    }
}