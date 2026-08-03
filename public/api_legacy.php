<?php

declare(strict_types=1);

/**
 * Endpoint JSON Mindflex.
 *
 * Nama file dan nama aksi dipertahankan supaya klien yang sudah memanggil
 * api_legacy.php?action=get_tutors tetap jalan. Isinya kini hanya perutean tipis.
 * Aturan sebenarnya ada di ApiController dan lapisan service.
 */

use Mindflex\Container;
use Mindflex\Http\Controller\ApiController;
use Mindflex\Http\Request;

require dirname(__DIR__) . '/vendor/autoload.php';

$container = Container::boot(dirname(__DIR__));

$response = (new ApiController($container))->handle(Request::fromGlobals());
$response->send();
