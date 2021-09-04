<?php

declare (strict_types = 1);

namespace isszz\third\facade;

use think\Facade;

class Third extends Facade
{
    /**
     * 获取当前Facade对应类名（或者已经绑定的容器对象标识）
     * @access protected
     * @return string
     */
    protected static function getFacadeClass()
    {
        return \isszz\third\Third::class;
    }
}