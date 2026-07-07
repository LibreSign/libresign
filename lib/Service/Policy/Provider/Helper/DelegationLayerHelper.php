<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\Helper;

use OCA\Libresign\Service\Policy\Model\PolicyLayer;

final class DelegationLayerHelper {
	public static function hasExplicitGlobalDelegation(?PolicyLayer $systemPolicy): bool {
		return $systemPolicy instanceof PolicyLayer
			&& $systemPolicy->getScope() === 'global'
			&& $systemPolicy->isVisibleToChild()
			&& $systemPolicy->isAllowChildOverride()
			&& $systemPolicy->getValue() !== null;
	}

	/** @param array<array-key, PolicyLayer> $groupLayers */
	public static function hasSystemCreatedGroupDelegation(array $groupLayers): bool {
		foreach ($groupLayers as $groupLayer) {
			if (!$groupLayer instanceof PolicyLayer) {
				continue;
			}

			if (!$groupLayer->isVisibleToChild() || $groupLayer->getValue() === null) {
				continue;
			}

			if ($groupLayer->isDelegatedFromSystemCreatedSeed()) {
				return true;
			}

			if ($groupLayer->isAllowChildOverride() && $groupLayer->isCreatedBySystemAdmin()) {
				return true;
			}
		}

		return false;
	}
}
