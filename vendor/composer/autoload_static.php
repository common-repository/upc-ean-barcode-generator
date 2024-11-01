<?php


namespace Composer\Autoload;

class ComposerStaticInitfc3d1d301da413c8990a83159f7148b6
{
    public static $prefixLengthsPsr4 = array (
        'U' => 
        array (
            'UkrSolution\\UpcEanGenerator\\' => 28,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'UkrSolution\\UpcEanGenerator\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static $prefixesPsr0 = array (
        'P' => 
        array (
            'PHPExcel' => 
            array (
                0 => __DIR__ . '/../..' . '/extlibs/PHPExcel',
            ),
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitfc3d1d301da413c8990a83159f7148b6::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitfc3d1d301da413c8990a83159f7148b6::$prefixDirsPsr4;
            $loader->prefixesPsr0 = ComposerStaticInitfc3d1d301da413c8990a83159f7148b6::$prefixesPsr0;

        }, null, ClassLoader::class);
    }
}
