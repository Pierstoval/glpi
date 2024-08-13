<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2024 Teclib' and contributors.
 * @licence   https://www.gnu.org/licenses/gpl-3.0.html
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * ---------------------------------------------------------------------
 */

require_once dirname(__DIR__).'/vendor/autoload.php';

function get_param(string $name, bool $required = false): mixed
{
    if ($required && !isset($_SERVER[$name])) {
        \fwrite(STDERR, \sprintf('Environment variable "%s" is mandatory for the HTTP entrypoint to work.', $name));
        exit(1);
    }

    return $_SERVER[$name] ?? null;
}

$front_controller = \dirname(__DIR__).'/public/index.php';
$_SERVER['SCRIPT_NAME'] = $front_controller;

$_SERVER['REQUEST_URI'] = \get_param('GLPI_REQUEST_URI', true);
$_SERVER['REQUEST_SCHEME'] = \get_param('GLPI_REQUEST_SCHEME', true);
$_SERVER['HOST'] = \get_param('GLPI_REQUEST_HOST') ?: 'http';
$_SERVER['HTTPS'] = \get_param('GLPI_REQUEST_SCHEME') === 'https';

$_SERVER['REQUEST_METHOD'] = \get_param('GLPI_REQUEST_METHOD') ?: 'GET';
$_GET = \json_decode(get_param('GLPI_REQUEST_GET') ?? '{}', true);
$_POST = \json_decode(get_param('GLPI_REQUEST_POST') ?? '{}', true);
$headers = \json_decode(get_param('GLPI_REQUEST_HEADERS') ?? '{}', true) ?: [];
foreach ($headers as $key => $value) {
    $_SERVER['HTTP_' . \strtoupper(str_replace('-', '_', $key))] = $value;
}

$user = get_param('GLPI_REQUEST_USER');
if ($user) {
    $user = \json_decode($user, true);
    session_start();
    $_SESSION['valid_id'] = session_id();
    $_SESSION['glpiID'] = $user['id'];
    $_SESSION['glpiname'] = $user['name'];
    $_SESSION['glpifriendlyname'] = $user['name'];
    $_SESSION['glpirealname'] = $user['realname'];
    $_SESSION['glpifirstname'] = $user['firstname'];
    $_SESSION['glpidefault_entity'] = $user['entities_id'];
    $_SESSION['glpiextauth'] = 0;
    $_SESSION['glpiauthtype'] = \Auth::DB_GLPI;
    $_SESSION['glpiactiveprofile']['create_ticket_on_login'] = null;
    $_SESSION['glpiactiveprofile']['interface'] = 'helpdesk';
}

\register_shutdown_function(static function () {
    global $response;

    $status_code_file = __DIR__.'/status_code';

    if ($response instanceof \Symfony\Component\HttpFoundation\Response) {
        \file_put_contents($status_code_file, $response->getStatusCode());
    } else {
        \file_put_contents($status_code_file, '');
    }

    if (ob_get_level()) {
        echo ob_get_flush();
    }
    @flush();
});

require $front_controller;
