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
use Glpi\Exception\Http\NotFoundHttpException;
use Glpi\Form\FormAction;
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
        FormAction::ADD->name => ['permission' => CREATE, 'post_action' => 'back'],
        FormAction::DELETE->name => ['permission' => DELETE, 'post_action' => 'list'],
        FormAction::RESTORE->name => ['permission' => DELETE, 'post_action' => 'list'],
        FormAction::PURGE->name => ['permission' => PURGE, 'post_action' => 'list'],
        FormAction::UPDATE->name => ['permission' => UPDATE, 'post_action' => 'back'],
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

        $form_action = $this->getCurrentAllowedAction($request, $class);

        if (!$form_action) {
            throw new AccessDeniedHttpException();
        }

        if ($response = $this->handleFormAction($request, $form_action, $class)) {
            return $response;
        }

        throw new NotFoundHttpException();
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
    private function handleFormAction(Request $request, FormAction $form_action, string $class): ?Response
    {
        $id = $request->query->get('id', -1);
        $object = new $class();
        $post_data = $request->request->all();
        $permission = self::ACTIONS_AND_CHECKS[$form_action->name]['permission'];
        $post_action = self::ACTIONS_AND_CHECKS[$form_action->name]['post_action'];

        if ($object instanceof \CommonDBTM) {
            // Permissions
            $object->check($id, $permission, $post_data);
        }

        // Special case for GET
        if ($form_action->value === 'get' && $request->getMethod() === 'GET') {
            return $this->render('pages/generic_form.html.twig', [
                'id' => $request->query->get('id', -1),
                'object_class' => $class,
            ]);
        }

        // POST action execution
        $result = match ($form_action) {
            FormAction::ADD => $object->add($post_data),
            FormAction::DELETE => $object->delete($post_data),
            FormAction::RESTORE => $object->restore($post_data),
            FormAction::PURGE => $object->delete($post_data, 1),
            FormAction::UPDATE => $object->update($post_data),
            default => throw new \RuntimeException(\sprintf("Unsupported object action \"%s\".", $post_action)),
        };

        if ($result) {
            Event::log(
                $result,
                \strtolower(\basename($class)),
                $class::getLogLevel(),
                $class::getLogServiceName(),
                sprintf(__('%1$s executes the "%2$s" action on the item %3$s'), $_SESSION["glpiname"], $form_action, $post_data["name"])
            );

            // Specific case for "add"
            if ($form_action === FormAction::ADD && $_SESSION['glpibackcreated']) {
                return new RedirectResponse($object->getLinkURL());
            }
        }

        return match ($post_action) {
            'back' => new RedirectResponse(Html::getBackUrl()),
            'list' => new StreamedResponse(fn() => $object->redirectToList(), 302),
            default => throw new \RuntimeException(\sprintf("Unsupported post-action \"%s\".", $post_action)),
        };
    }

    /**
     * @param class-string<CommonGLPI> $class
     */
    private function callAction(Request $request, string $class, string $action, int $permission, string $post_action): Response
    {
    }

    /**
     * @param class-string<CommonGLPI> $class
     */
    private function getCurrentAllowedAction(Request $request, string $class): ?FormAction
    {
        if ($request->getMethod() === 'POST') {
            foreach ($class::getAllowedFormActions() as $action) {
                if (!isset(self::ACTIONS_AND_CHECKS[$action])) {
                    throw new \RuntimeException(\sprintf('Undefined action name "%s".', $action));
                }

                if (
                    $request->request->has($action)
                    && \method_exists($class, $action)
                    && $class::isFormActionAllowed($action)
                ) {
                    return FormAction::from($action);
                }
            }
        }

        // Specific get-case
        if ($class::isFormActionAllowed(FormAction::GET)) {
            return FormAction::GET;
        }

        return null;
    }
}
