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

namespace Glpi\Controller;

use CommonGLPI;
use Html;
use Glpi\Event;
use Glpi\Exception\Http\AccessDeniedHttpException;
use Glpi\Exception\Http\BadRequestHttpException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

final class GenericFormController extends AbstractController
{
    public const ACTIONS_AND_CHECKS = [
        'add' => ['permission' => CREATE, 'post_action' => 'back'],
        'delete' => ['permission' => DELETE, 'post_action' => 'list'],
        'restore' => ['permission' => DELETE, 'post_action' => 'list'],
        'purge' => ['permission' => PURGE, 'post_action' => 'list'],
        'update' => ['permission' => UPDATE, 'post_action' => 'back'],
    ];

    #[Route("/{class}/Form", name: "glpi_generic_form")]
    public function __invoke(Request $request): Response
    {
        $class = $request->attributes->getString('class');

        $this->checkIsValidClass($class);

        /** @var class-string<CommonGLPI> $class */

        if (!$class::canView()) {
            throw new AccessDeniedHttpException();
        }

        if ($response = $this->handlePostRequest($request, $class)) {
            return $response;
        }

        return $this->render('pages/generic_form.html.twig', [
            'id' => $request->query->get('id', -1),
            'object_class' => $class,
        ]);
    }

    private function checkIsValidClass(string $class): void
    {
        if (!$class) {
            throw new BadRequestHttpException('The "class" attribute is mandatory for dropdown routes.');
        }

        if (!\class_exists($class)) {
            throw new BadRequestHttpException(\sprintf("Class \"%s\" does not exist.", $class));
        }

        if (!\is_subclass_of($class, CommonGLPI::class)) {
            throw new BadRequestHttpException(\sprintf("Class \"%s\" is not a DB object.", $class));
        }
    }

    /**
     * @param class-string<CommonGLPI> $class
     */
    private function handlePostRequest(Request $request, string $class): ?Response
    {
        foreach (self::ACTIONS_AND_CHECKS as $action => ['permission' => $permission, 'post_action' => $post_action]) {
            if (
                $request->request->has($action)
                && method_exists($class, $action)
            ) {
                return $this->callAction($request, $class, $action, (int) $permission, $post_action);
            }
        }

        return null;
    }

    /**
     * @param class-string<CommonGLPI> $class
     */
    private function callAction(Request $request, string $class, string $action, int $permission, string $post_action): Response
    {
        $id = $request->query->get('id', -1);
        $object = new $class();
        $post_data = $request->request->all();

        // Permissions
        $object->check($id, $permission, $post_data);

        // Action execution
        $result = match ($action) {
            'add' => $object->add($post_data),
            'delete' => $object->delete($post_data),
            'restore' => $object->restore($post_data),
            'purge' => $object->delete($post_data, 1),
            'update' => $object->update($post_data),
            default => throw new \RuntimeException(\sprintf("Unsupported object action \"%s\".", $post_action)),
        };

        if ($result) {
            Event::log(
                $result,
                \strtolower(\basename($class)),
                $class::getFormLogLevel(),
                $class::getFormServiceName(),
                sprintf(__('%1$s executes the "%2$s" action on the item %3$s'), $_SESSION["glpiname"], $action, $post_data["name"])
            );

            // Specific case for "add"
            if ($action === 'add' && $_SESSION['glpibackcreated']) {
                return new RedirectResponse($object->getLinkURL());
            }
        }

        return match ($post_action) {
            'back' => new RedirectResponse(Html::getBackUrl()),
            'list' => new StreamedResponse(fn() => $object->redirectToList(), 302),
            default => throw new \RuntimeException(\sprintf("Unsupported post-action \"%s\".", $post_action)),
        };
    }
}
