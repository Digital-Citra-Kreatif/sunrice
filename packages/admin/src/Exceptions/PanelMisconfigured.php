<?php

declare(strict_types=1);

namespace Sunrice\Admin\Exceptions;

use LogicException;

/**
 * The panel or a plugin is set up wrongly: a missing plugin, a duplicate
 * key, a change after boot. Thrown at boot so mistakes fail loudly in
 * development instead of misbehaving in production.
 *
 * @api
 */
final class PanelMisconfigured extends LogicException {}
