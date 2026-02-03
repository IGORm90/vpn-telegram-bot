<?php

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

// Health check endpoint
$router->get('/health', function () {
    return response()->json(['status' => 'ok']);
});

// Telegram webhook
$router->post('/webhook', 'MainController@handler');

// API routes (protected by Bearer token)
$router->group(['middleware' => 'bearer.auth'], function () use ($router) {
    $router->get('/api/users', 'UserController@index');
    $router->patch('/api/users/{id}', 'UserController@update');
    
    // VPN Server routes
    $router->get('/api/vpn-servers', 'VpnServerController@index');
    $router->post('/api/vpn-servers', 'VpnServerController@store');
    $router->delete('/api/vpn-servers/{id}', 'VpnServerController@destroy');
});
