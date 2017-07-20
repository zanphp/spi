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

    private $aliasOriginMap ;

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

    public function normalizedClassName($class)
    {
        return ltrim($class, "\\");
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

    public function scan($vendor)
    {
        $this->aliasOriginMap = [];

        $metaMap = $this->getMetaInfo($vendor);

        $this->parserMetaMap($metaMap);

        $this->registerAliasMap();
    }

    private function parserMetaMap(array $metaMap)
    {
        foreach ($metaMap as $realPath => $pkgAliasMap) {
            if (!is_array($pkgAliasMap)) {
                continue;
            }

            foreach ($pkgAliasMap as $origin => $aliases) {
                $aliases = (array)$aliases;
                $origin = $this->normalizedClassName($origin);
                $aliases = array_map([$this, "normalizedClassName"], $aliases);

                // 这里不检查重复定义, 类加载器会检查
                foreach ($aliases as $alias) {
                    $this->aliasOriginMap[$alias] = [$origin, $realPath, false];
                }
            }
        }

    }

    /**
     * 利用 autoload 递归注册依赖别名
     * 解决class_alias优先级问题,
     *
     * @throws ClassNotFoundException
     * @throws RedeclareException
     *
     * 扫描顺序
     * 1. class A               ---alias-->     class B
     * 2. class C extends E     ---alias-->     class A
     * 3. class D               ---alias-->     class E
     *
     * 检测A是否声明, 触发A自动加载, composer autoload 没有发现，由 [$this, autoload] 处理
     * [$this, autoload] 发现 A 是 C 的别名
     * 检查是C是否声明, 触发C自动加载, 发现C继承E, 触发E自动加载, composer autoload 没有发现，由 [$this, autoload] 处理
     * [$this, autoload] 发现 E 是 D 别名, 检测D是否声明, 未声明, 自动加载D
     * 将D 别名成 E, 完成 E自动加载, 从而完成 C 自动加载
     * 将 C 别名成 A, 从而完成 A 自动加载,
     * 将 A 别名成 B
     */
    private function registerAliasMap()
    {
        spl_autoload_register([$this, "autoload"]);

        foreach ($this->aliasOriginMap as $alias => list($origin, $realPath, $hasAliased)) {
            if (false === $this->declareIsExists($origin) /* 触发 origin 自动加载 */) {
                throw new ClassNotFoundException("class or interface or trait $origin not found in $realPath");
            }
            if ($hasAliased) {
                continue;
            }

            if ($this->declareIsExists($alias) /* 触发 alias 自动加载 */ ) {
                $hasAliased = $this->aliasOriginMap[$alias][2];
                if ($hasAliased) {
                    continue;
                }

                throw new RedeclareException("Cannot declare alias $alias in $realPath, because the name is already in use");
            }
        }

        spl_autoload_unregister([$this, "autoload"]);
    }

    public function autoload($class)
    {
        if (isset($this->aliasOriginMap[$class])) {
            // echo "prepare load class $class\n";

            list($origin, $realPath) = $this->aliasOriginMap[$class];
            if (false === class_alias($origin, $class) /* 触发 origin 自动加载 */) {
                throw new \BadMethodCallException("class_alias $origin to $class fail in $realPath");
            }

            // echo "alias $origin to $class\n";
            $this->aliasOriginMap[$class][2] = true;

            // echo "finish load class $class\n";
        }
    }

    private function declareIsExists($declare, $autoload = true)
    {
        return class_exists($declare, $autoload) || interface_exists($declare, $autoload) || trait_exists($declare, $autoload);
    }
}