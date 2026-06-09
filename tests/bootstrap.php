<?php
/**
 * Test bootstrap for silverstripe-elements fork.
 *
 * Wraps the standard silverstripe/cms test bootstrap with a flush=1 hint so
 * SapphireTest::start() boots the kernel with $flush=true. This rebuilds the
 * class manifest with test-only stubs included; without it, newly added stubs
 * are invisible to DataObjectSchema and cause "getItemPath returned null"
 * errors.
 */
$_GET['flush'] = 1;
require_once dirname(__DIR__, 3) . '/vendor/silverstripe/cms/tests/bootstrap.php';
