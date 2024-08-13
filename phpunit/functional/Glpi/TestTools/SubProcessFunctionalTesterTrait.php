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

namespace Glpi\PhpUnit\functional\Glpi\TestTools;

use Glpi\Kernel\Kernel;
use Symfony\Component\Process\PhpProcess;

trait SubProcessFunctionalTesterTrait
{
    public function request(SubProcessRequest $request): RequestResult
    {
        $public_path = \dirname(__DIR__, 4) . '/public/';
        $script_path = \dirname(__DIR__, 3) . '/tools/http_entrypoint.php';
        $status_code_file = $public_path . '/status_code';

        $url_parts = \parse_url($request->url, PHP_URL_QUERY);
        $url_parts['scheme'] = $url_parts['scheme'] ?? 'http';
        $url_parts['host'] = $url_parts['host'] ?? '127.0.0.1';
         $url_parts['query'] = $url_parts['query'] ?? '';
        $url_parts['path'] = $url_parts['path'] ?? '/';
        $uri = \sprintf('%s?%s', $url_parts['path'], $url_parts['query']);

        \parse_str($url_parts['query'], $get_options);
        $GET = \array_merge($request->GET, $get_options);

        $user = null;
        if ($request->login_info['user']) {
            $kernel = new Kernel('test', false);
            $kernel->boot();

            /** @var \DBmysql $DB */
            global $DB;

            $users = \iterator_to_array($DB->request([
                'SELECT' => ['*'],
                'FROM' => \User::getTable(),
                'WHERE' => [
                    'name' => $request->login_info['user'],
                ]
            ]));
            $user = \array_pop($users);

            $kernel->shutdown();
            unset($kernel);
        }

        $process = new PhpProcess(
            script: \file_get_contents($script_path),
            cwd: $public_path,
            env: [
                'GLPI_REQUEST_SCHEME' => $url_parts['scheme'],
                'GLPI_REQUEST_HOST' => $url_parts['host'],
                'GLPI_REQUEST_METHOD' => $request->method,
                'GLPI_REQUEST_URI' => $uri,
                'GLPI_REQUEST_GET' => \json_encode($GET),
                'GLPI_REQUEST_POST' => \json_encode($request->POST),
                'GLPI_REQUEST_HEADERS' => \json_encode($request->HEADERS),
                'GLPI_REQUEST_USER' => $user ? \json_encode($user) : null,
            ],
        );
        $process->run();

        $err = $process->getErrorOutput();
        $output = $process->getOutput();

        $code = \is_file($status_code_file) ? ((int) \trim(\file_get_contents($status_code_file))) : 1;

        if (\is_file($status_code_file)) {
            \unlink($status_code_file);
        }

        return new RequestResult($code, $output, $err);
    }
}
