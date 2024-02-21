<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\Libresign\Controller;

if (isset($parameters)) {
	/**
	 * Nextcloud's default route handling does not support SSE and injects
	 * headers into requests that cause an SSE route to not work correctly.
	 * In the OC\Route::match method there is a way to circumvent this header
	 * injection using a route that points directly to a file. In the
	 * routesAdminController.php file the SSE route is pointing directly to a
	 * file.
	 */
	$controller = \OC::$server->get(AdminController::class);
	$controller->downloadStatusSse();
} else {
	/**
	 * As of Nextcloud 29, the OC\Route::getAttributeRoutes method was implemented,
	 * which loads the routes by reflection and no longer just through the
	 * routes.php file, which is why this conditional was necessary to create a
	 * real class and not break the getAttributeRoutes method.
	 */
	class AdminSseController extends AdminController {
	}
}
