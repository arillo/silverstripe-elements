<?php

/**
 * Test bootstrap for silverstripe-elements fork.
 *
 * Two environment issues are worked around here:
 *
 * 1. Duplicate-class scan: When this module is installed via a composer path-repository
 *    (symlink:true), SilverStripe's class manifest sees the same physical files at two
 *    different paths:
 *      - silverstripe-elements/src/           (project-root module, depth 1)
 *      - vendor/arillo/silverstripe-elements/ (vendor symlink → same dir, depth 3)
 *    This triggers a "two files containing the same class" exception during manifest
 *    regeneration. Fix: temporarily replace the vendor symlink with an empty directory
 *    before the manifest boots, then restore it on shutdown. Classes are found
 *    exclusively via the project-root path during the test run.
 *
 * 2. Config cache miss for test stubs: The configcache is shared between test and
 *    non-test runs. When warm from a production run, test-only stub classes are not
 *    present in the static-transformer's class list, so their $db/$has_one specs are
 *    invisible to DataObjectSchema. Fix: inject `flush` into $_SERVER['argv'] so
 *    SapphireTest::start() boots the kernel with $flush=true, forcing a fresh
 *    configcache build that includes all test classes.
 */

// ── Fix 1: replace symlink with empty dir ──────────────────────────────────
$symlinkPath = dirname(__DIR__, 2) . '/vendor/arillo/silverstripe-elements';

if (is_link($symlinkPath)) {
    $linkTarget = readlink($symlinkPath);
    unlink($symlinkPath);
    mkdir($symlinkPath, 0755, true);
    // Restore symlink when the test process exits
    register_shutdown_function(function () use ($symlinkPath, $linkTarget) {
        if (is_dir($symlinkPath) && !is_link($symlinkPath)) {
            @rmdir($symlinkPath);
            symlink($linkTarget, $symlinkPath);
        }
    });
}

// ── Fix 2: force flush so test-stub $db specs enter the config cache ───────
// SapphireTest::start() uses HTTPApplication; it reads flush from $request->getVars()
// (i.e. $_GET), which CLIRequestBuilder merges into the environment variables.
// Setting $_GET['flush'] here ensures the kernel boots with $flush=true, rebuilding
// the configcache with the test manifest so that stub $db specs are visible.
$_GET['flush'] = 1;

// Boot the standard SilverStripe CMS test bootstrap
require_once dirname(__DIR__, 2) . '/vendor/silverstripe/cms/tests/bootstrap.php';
