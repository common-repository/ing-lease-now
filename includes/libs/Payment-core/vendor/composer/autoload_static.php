<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit64a0a0cd3d30341b0b5bd1ff0f7fc6fa
{
    public static $prefixLengthsPsr4 = array (
        'L' => 
        array (
            'Leasenow\\Payment\\' => 17,
        ),
        'J' => 
        array (
            'JsonSchema\\' => 11,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Leasenow\\Payment\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
        'JsonSchema\\' => 
        array (
            0 => __DIR__ . '/..' . '/justinrainbow/json-schema/src/JsonSchema',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit64a0a0cd3d30341b0b5bd1ff0f7fc6fa::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit64a0a0cd3d30341b0b5bd1ff0f7fc6fa::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}
