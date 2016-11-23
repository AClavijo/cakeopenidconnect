<?php

class OauthController extends OpenidAppController
{
    var $helpers = array("Html", "Form");
    var $components = array('Auth', 'Session', 'Openid');
    var $uses = array('Utilisateur');

    function beforeFilter() {
        //parent::beforeFilter();
        $this->Auth->allowedActions = array('*');         
    }

    function authentification()
    {
        $this->Openid->authentification();
    }
}