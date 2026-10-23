<?php

use CodeIgniter\CodingStandard\CodeIgniter4;
use Nexus\CsConfig\Factory;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->files()
    ->in([__DIR__ . '/app', __DIR__ . '/tests'])
    ->exclude(['Views'])
    ->append([__FILE__]);

return Factory::create(new CodeIgniter4(), [], [
    'finder'    => $finder,
    'cacheFile' => 'writable/cache/.php-cs-fixer.cache',
])->forProjects();
