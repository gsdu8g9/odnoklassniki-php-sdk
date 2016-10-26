<?php

namespace Holystix\Odnoklassniki;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * @author    Janis Osenieks
 */
class Api
{

    const API_AUTH_URL  = 'http://api.odnoklassniki.ru/oauth/token.do';
    const API_LOGIN_URL = 'http://www.odnoklassniki.ru/oauth/authorize';
    const API_BASE_URL  = 'http://api.odnoklassniki.ru/fb.do';

    /**
     * @var string
     */
    private $clientId;

    /**
     * @var string
     */
    private $applicationKey;

    /**
     * @var string
     */
    private $clientSecret;

    /**
     * @var string
     */
    private $accessToken;

    /**
     * @var string
     */
    private $refreshToken;

    /**
     * @var Client
     */
    private $client;

    /**
     * @var array
     */
    private $scope;

    /**
     * @var string
     */
    private $redirectUri;

    /**
     * @param string $clientId
     * @param string $applicationKey
     * @param string $clientSecret
     * @param array  $scope
     * @param string $accessToken
     * @param string $refreshToken
     * @param array  $httpParams
     */
    public function __construct( $clientId, $applicationKey, $clientSecret, $scope, $accessToken = null, $refreshToken = null, $httpParams = [] )
    {
        $this->clientId       = $clientId;
        $this->applicationKey = $applicationKey;
        $this->clientSecret   = $clientSecret;
        $this->scope          = $scope;
        $this->accessToken    = $accessToken;
        $this->refreshToken   = $refreshToken;

        $this->client = new Client( array_merge( $httpParams, [
            'headers' => [
                'Accept' => 'application/json',
            ]
        ] ) );
    }

    /**
     * @return string The URL for the login flow
     */
    public function getLoginUrl()
    {
        $params = [
            'client_id'     => $this->clientId,
            'response_type' => 'code',
            'redirect_uri'  => $this->redirectUri,
        ];

        if( $this->scope )
        {
            $params[ 'scope' ] = implode( ',', $this->scope );
        }

        return self::API_LOGIN_URL . '?' . http_build_query( $params );
    }


    /**
     * Set the URL to which the user will be redirected
     *
     * @param string $redirectUri
     *
     * @return $this
     */
    public function setRedirectUri( $redirectUri )
    {
        $this->redirectUri = $redirectUri;
        return $this;
    }

    public function authenticate( $code = null )
    {
        if( null === $code )
        {
            if( isset( $_GET[ 'code' ] ) )
            {
                $code = $_GET[ 'code' ];
            }
        }

        $params = [
            'code'          => $code,
            'redirect_uri'  => $this->redirectUri,
            'grant_type'    => 'authorization_code',
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret
        ];

        $response          = $this->call( self::API_AUTH_URL, $params );
        $this->accessToken = $response[ 'access_token' ];
    }

    private function call( $url, array $params )
    {
        try
        {
            $response = $this->client->post( $url, [
                'query' => $params,
                'form'  => $params,
            ] )->getBody();
            if( !$response = json_decode( $response, true ) )
            {
                throw new ApiException( 'ResponseParseError' );
            }
            if( !empty( $response[ 'error_code' ] ) && !empty( $response[ 'error_msg' ] ) )
            {
                throw new ApiException( $response[ 'error_msg' ],
                    $response[ 'error_code' ] );
            }
            return $response;
        } catch( RequestException $e )
        {
            throw new ApiException( $e->getMessage() );
        }
    }

    public function getUser()
    {
        $params = [
            'access_token'    => $this->accessToken,
            'application_key' => $this->applicationKey,
            'method'          => 'users.getCurrentUser',
            'sig'             => md5( 'application_key=' . $this->applicationKey . 'method=users.getCurrentUser'
                . md5( $this->accessToken . $this->clientSecret ) )
        ];

        return $this->call( self::API_BASE_URL, $params );
    }

    public function getAccessToken()
    {
        return $this->accessToken;
    }
}