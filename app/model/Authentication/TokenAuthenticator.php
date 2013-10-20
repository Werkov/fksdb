<?php

namespace Authentication;

use ModelAuthToken;
use ModelLogin;
use Nette\Http\Session;
use Nette\Security\AuthenticationException;
use ServiceAuthToken;

/**
 * Users authenticator.
 */
class TokenAuthenticator {

    const SESSION_NS = 'auth';

    /**
     * @var ServiceAuthToken
     */
    private $authTokenService;

    /**
     * @var Session
     */
    private $session;

    function __construct(ServiceAuthToken $authTokenService, Session $session) {
        $this->authTokenService = $authTokenService;
        $this->session = $session;
    }

    /**
     * @param string $tokenData
     * @return ModelLogin
     * @throws AuthenticationException
     */
    public function authenticate($tokenData) {
        $token = $this->authTokenService->verifyToken($tokenData);
        if (!$token) {
            throw new AuthenticationException('Autentizační token je neplatný.');
        }
        // login by the identity
        $login = $token->getLogin();
        if (!$login->active) {
            throw new AuthenticationException('Neaktivní účet.');
        }

        $this->storeAuthToken($token);

        return $login;
    }

    /**
     * Get rid off token and user is no more authenticated by the token(?).
     * 
     * @return void
     */
    public function disposeAuthToken() {
        $section = $this->session->getSection(self::SESSION_NS);
        if (isset($section->token)) {
            $this->authTokenService->disposeToken($section->token);
            unset($section->token);
        }
    }

    /**
     * @return bool true iff user has been authenticated by the authentication token
     */
    public function isAuthenticatedByToken() {
        $section = $this->session->getSection(self::SESSION_NS);
        return isset($section->token);
    }

    private function storeAuthToken(ModelAuthToken $token) {
        $section = $this->session->getSection(self::SESSION_NS);
        $section->token = $token->token;
    }
}