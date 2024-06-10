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

namespace Glpi\Config;

use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class LegacyConfigProviderListener implements EventSubscriberInterface
{
    /**
     * @var LegacyConfigProviderInterface[]
     */
    private array $configProviders = [];

    public function __construct(
        #[TaggedIterator(LegacyConfigProviderInterface::TAG_NAME)]
        iterable $configProviders = [],
    ) {
        foreach ($configProviders as $provider) {
            $this->addProvider($provider);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Has to be executed before anything else!
            KernelEvents::REQUEST => ['onKernelRequest', 10000],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        foreach ($this->configProviders as $provider) {
            $provider->execute($event->getRequest());
        }
    }

    private function addProvider(LegacyConfigProviderInterface $provider)
    {
        $this->configProviders[] = $provider;
    }
}
