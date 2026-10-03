<?php

namespace App\Http\Controllers\Telegram\MiniApp\Concerns;

use App\Enums\ResourceHub;
use App\Enums\ResourceType;

trait ResolvesMiniAppHub
{
    /**
     * @return array{hub: ResourceType|ResourceHub, types: list<string>}|null
     */
    protected function resolveMiniAppHub(string $hub): ?array
    {
        $type = ResourceType::tryFrom($hub);

        if ($type instanceof ResourceType) {
            return [
                'hub' => $type,
                'types' => [$type->value],
            ];
        }

        $resourceHub = ResourceHub::tryFrom($hub);

        if ($resourceHub instanceof ResourceHub) {
            return [
                'hub' => $resourceHub,
                'types' => $resourceHub->typeValues(),
            ];
        }

        return null;
    }
}
