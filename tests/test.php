<?php

use ZanPHP\SPI\AliasLoader;

require __DIR__ . "/../src/MetaInfoLoader.php";
require __DIR__ . "/../src/AliasLoader.php";



function call($obj, $method, array $args = []) {
    $method = new \ReflectionMethod($obj, $method);
    $method->setAccessible(true);
    $method->invokeArgs($obj, $args);
}

spl_autoload_register(function($c) {
    if ($c === "A") {
        return;
    }

    if ($c === "B") {
        return;
    }

    if ($c === "C") {
        eval("class C extends E {}");
        return;
    }

    if ($c === "D") {
        eval("class D { const N = 42; }");
        return;
    }
});

/**
 *
 * 扫描顺序
 * 1. class A               ---alias-->     class B
 * 2. class C extends E     ---alias-->     class A
 * 3. class D               ---alias-->     class E
 *
 */
$metaInfo = [
    "file1" => [
        "A" => "B",
    ],
    "file2" => [
        "C" => "A",
    ],
    "file3" => [
        "D" => "E",
    ],
];
$a = AliasLoader::getInstance();
call($a, "parserMetaMap", [$metaInfo]);
call($a, "registerAliasMap");


assert(\A::N === 42);
assert(\B::N === 42);
assert(\C::N === 42);
assert(\D::N === 42);
assert(\E::N === 42);

