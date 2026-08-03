<?php

declare(strict_types=1);

use Mindflex\Container;
use Mindflex\Http\Controller\AdminActionController;
use Mindflex\Http\Controller\AuthController;
use Mindflex\Http\Controller\DashboardController;
use Mindflex\Http\Flash;
use Mindflex\Http\RedirectResponse;
use Mindflex\Http\Request;
use Mindflex\Support\Csrf;
use Mindflex\Support\Session;

require dirname(__DIR__) . '/vendor/autoload.php';

$container = Container::boot(dirname(__DIR__));

Session::start();
sendSecurityHeaders();

$request = Request::fromGlobals();
$auth = $container->auth();

// Mutasi hanya lewat POST. Versi lama menerima ?action=delete lewat GET.
if ($request->isPost() && ! Csrf::isValid($request->csrfToken())) {
    Flash::error('Your session expired. Try that action again.');
    (new RedirectResponse($auth->check() ? 'index.php' : 'index.php?page=login'))->send();

    return;
}

$response = route($container, $request, $auth->check());

if ($response instanceof RedirectResponse) {
    $response->send();

    return;
}

header('Content-Type: text/html; charset=utf-8');
echo $response;

/**
 * Peta rute halaman admin.
 */
function route(Container $container, Request $request, bool $isSignedIn): string|RedirectResponse
{
    $authController = new AuthController($container);
    $dashboardController = new DashboardController($container);

    if (! $isSignedIn) {
        if ($request->isPost() && $request->action() === 'login') {
            return $authController->login($request);
        }

        return $dashboardController->login($request);
    }

    if ($request->isPost()) {
        if ($request->action() === 'logout') {
            return $authController->logout();
        }

        return (new AdminActionController($container))->handle($request);
    }

    return $dashboardController->index($request);
}

function sendSecurityHeaders(): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:");
}
