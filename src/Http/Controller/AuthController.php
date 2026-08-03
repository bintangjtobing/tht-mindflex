<?php

declare(strict_types=1);

namespace Mindflex\Http\Controller;

use Mindflex\Container;
use Mindflex\Http\Flash;
use Mindflex\Http\RedirectResponse;
use Mindflex\Http\Request;

final class AuthController
{
    public function __construct(private readonly Container $container)
    {
    }

    public function login(Request $request): RedirectResponse
    {
        $username = $request->input('username');
        $password = $request->input('password');

        if ($username === '' || $password === '') {
            Flash::error('Enter your username and password.');

            return RedirectResponse::toLogin();
        }

        if (! $this->container->auth()->attempt($username, $password)) {
            // Pesan sengaja tidak menyebut bagian mana yang salah.
            Flash::error('Those credentials do not match our records.');

            return RedirectResponse::toLogin();
        }

        Flash::success('You are signed in.');

        return RedirectResponse::toDashboard();
    }

    public function logout(): RedirectResponse
    {
        $this->container->auth()->logout();

        return RedirectResponse::toLogin();
    }
}
