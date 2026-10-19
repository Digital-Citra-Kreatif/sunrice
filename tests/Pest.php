<?php

declare(strict_types=1);

use Sunrice\Tests\Admin\AdminTestCase;
use Sunrice\Tests\Cms\CmsTestCase;

/*
 * Each package's tests run against its own Testbench app: admin tests
 * load the panel alone (proving it works without the CMS), CMS tests
 * load both. Architecture tests need no app.
 */
uses(AdminTestCase::class)->in('Admin');
uses(CmsTestCase::class)->in('Cms');
