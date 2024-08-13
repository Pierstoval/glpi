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

namespace Glpi\PhpUnit\functional\Glpi\Http;

use Glpi\PhpUnit\functional\Glpi\TestTools\SubProcessFunctionalTesterTrait;
use Glpi\PhpUnit\functional\Glpi\TestTools\SubProcessRequest;
use PHPUnit\Framework\TestCase;

class PluginRouteTest extends TestCase
{
    use SubProcessFunctionalTesterTrait;

    public function testPluginRoute(): void
    {
        self::assertTrue(\Plugin::isPluginActive('tester'), 'Failed asserting that plugin is inactive');

        $res = $this->request(new SubProcessRequest(
            method: 'GET',
//            url: '/front/login.php',
            url: '/plugins/tester/plugin-test',
            login_info: ['user' => 'glpi', 'password' => 'glpi'],
        ));
        dd($res);

        self::assertSame(200, $res->status_code, 'Failed to assert that route returns expected HTTP code');
        self::assertSame('', $res->error);
        self::assertSame('', $res->output);
    }
}
