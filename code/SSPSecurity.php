<?php
/**
 * Replaces the default Silverstripe {@link Security} class for dealing with SimpleSAMLphp
 * authentication
 * 
 * @package silverstripe-ssp
 * @author Anton Smith <anton.smith@op.ac.nz>
 */
class SSPSecurity extends Controller {

    /**
     * A list of all the authenticators
     * @var array
     */
    private static $authenticators;
    
    /**
     * Use this authenticator as the default when an authentication source isn't specified.
     * Authenticators can be Silverstripe environment specific
     * @var mixed
     */
    private static $default_authenticator;
    
    /**
     * Redirect the user to the URL after login is complete. If the session contains
     * a BackURL this is used instead
     * @var string
     */
    private static $default_logged_in_url;

    /**
     * Replace the default Silverstripe Security class
     * @var boolean
     */
    private static $enable_ssp_auth = true;
    
    /**
     * Force HTTPS mode when executing authentication functions
     * @var boolean
     */
    private static $force_ssl = true;
    
    private static $allowed_actions = array( 
        'index',
        'login',
        'logout',
        'loggedout'
    );

    public function init() {
        parent::init();

        // Prevent clickjacking, see https://developer.mozilla.org/en-US/docs/HTTP/X-Frame-Options
        $this->response->addHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function index() {
        return $this->httpError(404);
    }
    
    /**
     * Log the current user into the identity provider, and then Silverstripe
     * @see SimpleSAML_Auth_Simple->login()
     */
    public function login() {
        $this->forceSSL();

        $auth = $this->getAuthenticator();
        
        $auth->requireAuth(array(
            'ReturnTo' => '/Security/login',
            'ReturnCallback' => $auth->loginComplete()
        ));
        
        //SSPAuthenticator->authenticate() must return a Member
        $member = $auth->authenticate();

        if(!$member instanceof Member) {
            user_error(get_class($auth) . ' does not return a valid Member');
        }

        $member->login();
        
        //Use the BackURL for redirection if avaiable, or use the default logged in URL
        $dest = !empty(Session::get('BackURL')) ? Session::get('BackURL') : 
                $this->config()->default_logged_in_url;
        
        Session::clear('BackURL');
        
        //Callback for after login
        $auth->onAfterLogin();
        
        return $this->redirect($dest);
    }
    
    /**
     * Log the currently logged in user out of the identity provider
     * @see SimpleSAML_Auth_Simple->logout()
     */
    public function logout() {
        $this->forceSSL();
        
        $auth = $this->getAuthenticator();
        
        $auth->logout(array(
            'ReturnTo' => '/Security/loggedout'
        ));
    }

    /**
     * Log the currently logged in user out of the local Silverstripe website.
     * This function should only be called after logging out of the identity provider.
     *
     * @see logout()
     */
    public function loggedout() {
        $this->forceSSL();
        
        //Log out Silverstripe members
        if($member = Member::currentUser()) {
            $member->logout();
        }
        
        $auth = $this->getAuthenticator();
        
        $auth->logoutComplete();
        
        //Callback for after logout
        $auth->onAfterLogout();;
    
        return $this->redirect(str_replace('https', 'http', Director::absoluteBaseURL()));
    }
    
    /**
     * Gets the current SSPAuthenticator class used for SimpleSAMLphp authentication
     * @return SSPAuthenticator Active session for authentication
     */
    public function getAuthenticator() {
        
        if(!is_null(Session::get('ssp_current_auth'))) {
            $ssp = unserialize(Session::get('ssp_current_auth'));   

            if($ssp->isAuthenticated()) {
                return $ssp;   
            }
            
            else {
                Session::clear('SimpleSAMLphp_SESSION');
                Session::clear('ssp_current_auth');
            }
        }
        
        return $this->initAuthenticator();   
    }
    
    /**
     * Initialises the selected SSPAuthenticator class for SimpleSAMLphp authentication
     * @return SSPAuthenticator Active session for authentication
     */
    private function initAuthenticator() {
        $authenticators = $this->config()->authenticators;
        
        if(!$authenticators || !is_array($authenticators)) {
            user_error("Expected array of authentication sources in SSPSecurity::authenticators", E_USER_ERROR);
        }
        
        $auth_source = '';

        //If set, override authentication sources in config
        if(isset($_GET['as'])) {
            $auth_source = $_GET['as'];
        }
        
        //Else look for the default authentication source
        else {
            $default_auth = $this->config()->default_authenticator;
            
            $env = Director::get_environment_type();
            
            if(is_string($default_auth)) {
                $auth_source = $default_auth;
            }
            
            //Use the current environment defined in the config
            else if(is_array($default_auth) && array_key_exists($env, $default_auth)) {
                $auth_source = $default_auth[$env];
            }
        }
        
        //If no auth_source is found, default to the first authentication source
        if(empty($auth_source)) {
            $auth_source = key($authenticators);
        }
        
        if(!array_key_exists($auth_source, $authenticators)) {
            user_error("'$auth_source' does not exist in SSPSecurity::authenticators", E_USER_ERROR);
        }

        $class = $authenticators[$auth_source];
        
        if(!class_exists($class)) {
            user_error("$class does not exist", E_USER_ERROR);
        }

        if(!is_subclass_of($class, 'SSPAuthenticator')) {
            user_error("$class does not extend from SSPAuthenticator", E_USER_ERROR);
        }

        $authenticator = new $class($auth_source);

        return $authenticator;   
    }
    
    /**
     * Forces HTTPS mode if set in the configuration
     */
    private function forceSSL() {
        $mode = $this->config()->force_ssl;
        
        if(!is_bool($mode)) {
            user_error("Expected boolean in SSPSecurity::force_ssl", E_USER_ERROR);
        }
        
        if($mode) {
            Director::forceSSL();
        }
    }
}