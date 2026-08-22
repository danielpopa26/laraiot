<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Http\Controllers\Ui;

use Danpopa\LaraIoT\Models\ActivityLog;
use Danpopa\LaraIoT\Models\ApplicationSetting;
use Danpopa\LaraIoT\Models\LogicalDevice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

final class ActivityLogController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'type' => ['nullable', 'string', 'max:50'],
            'logical_device_id' => [
                'nullable',
                'integer',
                'exists:laraiot_logical_devices,id',
            ],
            'from' => ['nullable', 'date'],
            'to' => [
                'nullable',
                'date',
                'after_or_equal:from',
            ],
        ]);

        $settings = ApplicationSetting::current();

        $logs = ActivityLog::query()
            ->with([
                'logicalDevice:id,name',
                'mqttTopic:id,topic',
            ])
            ->when(
                filled($filters['search'] ?? null),
                function (
                    Builder $query,
                ) use ($filters): void {
                    $search =
                        (string) $filters['search'];

                    $query->where(
                        function (
                            Builder $nested,
                        ) use ($search): void {
                            $nested
                                ->where(
                                    'title',
                                    'like',
                                    '%'.$search.'%',
                                )
                                ->orWhere(
                                    'description',
                                    'like',
                                    '%'.$search.'%',
                                );
                        },
                    );
                },
            )
            ->when(
                filled($filters['type'] ?? null),
                fn (Builder $query) => $query
                    ->where(
                        'type',
                        $filters['type'],
                    ),
            )
            ->when(
                filled(
                    $filters['logical_device_id']
                        ?? null,
                ),
                fn (Builder $query) => $query
                    ->where(
                        'logical_device_id',
                        $filters['logical_device_id'],
                    ),
            )
            ->when(
                filled($filters['from'] ?? null),
                fn (Builder $query) => $query
                    ->whereDate(
                        'happened_at',
                        '>=',
                        $filters['from'],
                    ),
            )
            ->when(
                filled($filters['to'] ?? null),
                fn (Builder $query) => $query
                    ->whereDate(
                        'happened_at',
                        '<=',
                        $filters['to'],
                    ),
            )
            ->latest('happened_at')
            ->paginate(25)
            ->withQueryString()
            ->through(
                fn (ActivityLog $log): array => [
                    'id' => $log->getKey(),
                    'type' => $log->type,
                    'title' => $log->title,
                    'description' => $log->description,
                    'logical_device' =>
                        $log->logicalDevice
                            ? [
                                'id' => $log
                                    ->logicalDevice
                                    ->getKey(),
                                'name' =>
                                    $log
                                        ->logicalDevice
                                        ->name,
                            ]
                            : null,
                    'topic' => $log->mqttTopic?->topic,
                    'data' => $log->data,
                    'happened_at' =>
                        $log->happened_at
                            ?->toIso8601String(),
                    'happened_at_formatted' =>
                        $log->happened_at
                            ?->copy()
                            ->setTimezone(
                                $settings->timezone,
                            )
                            ->format(
                                $settings->date_format
                                    .' '
                                    .$settings->time_format,
                            ),
                ],
            );

        return Inertia::render(
            'laraiot/logs/Index',
            [
                'logs' => $logs,
                'filters' => [
                    'search' =>
                        $filters['search'] ?? '',
                    'type' =>
                        $filters['type'] ?? '',
                    'logical_device_id' =>
                        $filters['logical_device_id']
                            ?? '',
                    'from' =>
                        $filters['from'] ?? '',
                    'to' =>
                        $filters['to'] ?? '',
                ],
                'types' => ActivityLog::query()
                    ->select('type')
                    ->distinct()
                    ->orderBy('type')
                    ->pluck('type')
                    ->values(),
                'logicalDevices' =>
                    LogicalDevice::query()
                        ->orderBy('name')
                        ->get([
                            'id',
                            'name',
                        ]),
            ],
        );
    }
}
