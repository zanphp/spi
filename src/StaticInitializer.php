<?php

namespace ZanPHP\SPI;


class StaticInitializer
{
    public function hook()
    {
        $funcs = spl_autoload_functions();

        $staticInitFuncs = [];
        foreach ($funcs as $func) {
            spl_autoload_unregister($func);

            $staticInitFuncs[] = function($class) use($func) {
                echo "[autoload] $class\n"; // debug
                $func($class);

                // public static function __static();
                // 类或接口完成加载后, 自动加载队列停止执行autoload函数, 保证__static 执行一次
                if (is_callable("$class::__static")) {
                    call_user_func("$class::__static");
                }
            };
        }

        foreach ($staticInitFuncs as $func) {
            spl_autoload_register($func);
        }
    }
}