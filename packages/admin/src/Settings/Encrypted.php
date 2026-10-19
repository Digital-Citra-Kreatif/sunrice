<?php

declare(strict_types=1);

namespace Sunrice\Admin\Settings;

use Attribute;

/**
 * Stores a settings property encrypted with the app key: API keys and
 * other secrets. Pair it with a Secret field so it isn't shown again.
 *
 * @api
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Encrypted {}
