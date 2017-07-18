<?php

namespace ZanPHP\SPI;


use ZanPHP\SPI\Exception\ClassNotFoundException;
use ZanPHP\SPI\Exception\ServiceConfigurationException;

class ServiceLoader extends MetaInfoLoader
{
    const SUFFIX = "service.php";

    private static $providers;

    public static function load($interface = null)
    {
        if ($interface === null) {
            return static::$providers;
        }

        if (isset(static::$providers[$interface])) {
            return static::$providers[$interface];
        } else {
            return [];
        }
    }

    public function scan($vendor)
    {
        static::$providers = [];
        parent::scan($vendor);
    }

    protected function accept(\SplFileInfo $fileInfo, $path, \FilesystemIterator $iter)
    {
        $suffix = static::PREFIX . static::SUFFIX;
        $len = strlen($suffix);
        if ($fileInfo->isFile()) {
            $realPath = $fileInfo->getRealPath();
            return substr($realPath, -$len) === $suffix;
        }
        return false;
    }

    protected function parse($realPath)
    {
        /** @noinspection PhpIncludeInspection */
        $providers = require $realPath;
        if (is_array($providers)) {
            $this->registerServiceProvider($realPath, $providers);
        }
    }

    private function registerServiceProvider($realPath, array $providers)
    {
        foreach ($providers as $impl => $interfaceInfo) {
            if (!isset($interfaceInfo["interface"])) {
                throw new ServiceConfigurationException("missing interface item in $realPath.$impl");
            }

            $interface = $interfaceInfo["interface"];

            if (!class_exists($impl)) {
                throw new ClassNotFoundException("class $impl not found in $realPath");
            }
            if (!interface_exists($interface)) {
                throw new ClassNotFoundException("interface $interface not found in $realPath");
            }

            if (isset(static::$providers[$interface])) {
                static::$providers[$interface] = [];
            }

            static::$providers[$interface][] = [ "class" => $impl ] + $interfaceInfo;
        }
    }
}