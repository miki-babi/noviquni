<?php

namespace App\Services;

use App\Enums\ResourceType;
use App\Enums\StudyEventType;
use App\Enums\UserEventName;
use App\Models\LearningResource;
use App\Models\User;
use App\Models\UserEvent;

class UserEventService
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function log(User $user, UserEventName $event, array $details = []): UserEvent
    {
        return UserEvent::query()->create([
            'user_id' => $user->id,
            'event' => $event,
            'details' => $details === [] ? null : $details,
            'created_at' => now(),
        ]);
    }

    public function logResourceOpen(User $user, LearningResource $resource): UserEvent
    {
        $resource->loadMissing('course');

        return $this->log($user, UserEventName::ResourceOpen, $this->resourceStudyDetails($resource));
    }

    /**
     * @return array<string, mixed>
     */
    public function resourceStudyDetails(LearningResource $resource, array $extra = []): array
    {
        $resource->loadMissing('course');

        $type = $resource->type instanceof ResourceType
            ? $resource->type
            : ResourceType::from((string) $resource->type);

        return array_merge([
            'resource_id' => $resource->id,
            'course' => $resource->course?->name,
            'chapter' => $resource->title,
            'type' => StudyEventType::fromResourceType($type)->value,
            'resource_type' => $type->value,
        ], $extra);
    }
}
