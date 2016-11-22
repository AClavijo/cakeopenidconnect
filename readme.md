# Plugin Google OpenId Connect for Cake 1.3 applications

This plugin provide Google OpenId Connect to a cake 1.3 Framework.

> I try to make the installation easy. So please read the installation part :)

## Dependancies

This project include one dependancie:
- "google/apiclient": "^2.0" ['Documentation'](https://github.com/google/google-api-php-client)
- "symfony/yaml" :    "3.1.*" ['Documentation'](http://symfony.com/doc/current/components/yaml.html)
- "aclavijo/cakephp-installer-plugin": "0.1.*" Custom composer installer

## Installation

### via composer

```
composer require aclavijo/cakeopenidconnect
```

When you've done the installation, composer will place the plugin in 'app/plugins/openid'

### Parameter.yml

you need to create a parameter.yml in app/plugins/openid/config from the parameter.yml.dist

## Configuration

```
#parameter.yml
OpenidConnect:
    client_id: #client_id provide by google
    secret_id: #client_id provide by google
    domains: [] #list here the domains you want to accept
    scopes: ['openid', 'email', 'profile'] #google openid scope minimum ['openid', 'email']
    #optionnal
    log_path: "" #put here the log file 
```

## Deploy it on your project

when you succefully install you'r page, put a connect link in login.ctp page like this:
```php
<a href="<?php echo $html->link('authentification', array(
                    'plugin' => 'openid', 
                    'controller' => 'openid',
                    'action' => 'authentification' 
)); ?>">Google connect</a>
```

## To Do

- Implement the log path
- put domains optionnal parameter to accept all domains, because by default it accept none. But this plugin is dev for accept just selective email address domains