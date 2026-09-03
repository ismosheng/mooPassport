<?php

declare(strict_types=1);

namespace app\common\repository\contract;

/** 为跨应用运行时配置提供只读访问，敏感凭据仍由环境变量管理。 */
interface SystemSettingReaderInterface
{
    /** @return array<string, array{value:string,version:int}> */
    public function allByKey(): array;
}
