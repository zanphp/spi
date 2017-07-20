<?php

namespace ZanPHP\SPI;


abstract class MetaInfoLoader
{
    const PREFIX = "META-INF/";

    abstract public function scan($vendor);

    abstract protected function accept(\SplFileInfo $fileInfo, $path, \FilesystemIterator $iter);

    protected function getMetaInfo($vendor)
    {
        $metaMap = [];
        $iter = $this->recursiveScan($vendor);
        foreach ($iter as $realPath) {
            $metaMap[$realPath] = require $realPath;
        }
        return $metaMap;
    }

    private function recursiveScan($dir)
    {
        $iter = new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS);
        $iter = new \RecursiveCallbackFilterIterator($iter, function($current, $key, \RecursiveDirectoryIterator $iter) {
            if ($iter->hasChildren()) {
                return true;
            }
            return $this->accept($current, $key, $iter);
        });
        $iter = new \RecursiveIteratorIterator($iter, \RecursiveIteratorIterator::LEAVES_ONLY);
        /** @var \SplFileInfo $fileInfo */
        foreach ($iter as $fileInfo) {
            yield $fileInfo->getRealPath();
        }
    }
}