<?php

namespace ZanPHP\SPI;


use ZanPHP\SPI\Exception\ClassNotFoundException;
use ZanPHP\SPI\Exception\RedeclareException;

class AliasLoader extends MetaInfoLoader
{
    const SUFFIX = "alias.php";

    /**
     * @var static
     */
    private static $instance = null;

    /**
     * @return static
     */
    public static function getInstance()
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    protected function parse($realPath)
    {
        /** @noinspection PhpIncludeInspection */
        $aliasMap = require $realPath;

        if (is_array($aliasMap)) {
            $this->registerAlias($realPath, $aliasMap);
        }
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

    private function registerAlias($realPath, array $aliasMap)
    {
        foreach ($aliasMap as $origin => $aliases) {
            if (empty($aliases)) {
                continue;
            }

            if (!class_exists($origin) && !interface_exists($origin) && !trait_exists($origin)) {
                throw new ClassNotFoundException("class or interface or trait $origin not found in $realPath");
            }

            foreach ((array)$aliases as $alias) {
                if (class_exists($alias) || interface_exists($alias) || trait_exists($alias)) {
                    throw new RedeclareException("Cannot declare alias $alias in $realPath, because the name is already in use");
                }


                if (class_alias($origin, $alias) === false) {
                    throw new \BadMethodCallException("class_alias $origin to $alias fail in $realPath");
                }
            }
        }
    }
}