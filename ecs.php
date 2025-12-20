<?php

declare(strict_types=1);

use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
	->withPaths([
		__DIR__ . '/Header',
		__DIR__ . '/RemoteEvent',
		__DIR__ . '/Transport',
		__DIR__ . '/Webhook',
		__DIR__ . '/tests',
	])
	->withRootFiles()
	->withFileExtensions(['php'])
	->withPreparedSets(psr12: true)
	->withEditorConfig()
	->withParallel();
