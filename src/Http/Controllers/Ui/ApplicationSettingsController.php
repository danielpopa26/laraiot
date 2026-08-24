<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Http\Controllers\Ui;

use Danpopa\LaraIoT\Http\Requests\Ui\ApplicationSettingsRequest;
use Danpopa\LaraIoT\Models\ApplicationSetting;
use Danpopa\LaraIoT\Support\Reverb\ReverbHealthMonitor;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

final class ApplicationSettingsController extends Controller
{
    public function edit(
        ReverbHealthMonitor $reverbHealthMonitor,
    ): Response {
        $settings = ApplicationSetting::current();

        return Inertia::render(
            'laraiot/settings/Application',
            [
                'settings' => $settings,
                'websocket' => $reverbHealthMonitor->snapshot(
                    force: true,
                ),
                'timezones' => DateTimeZone::listIdentifiers(),
                'dateFormats' => $this->formatOptions(
                    ApplicationSetting::DATE_FORMATS,
                ),
                'timeFormats' => $this->formatOptions(
                    ApplicationSetting::TIME_FORMATS,
                ),
                'pollingIntervalLimits' => [
                    'min' => ApplicationSetting::MIN_POLLING_INTERVAL,
                    'max' => ApplicationSetting::MAX_POLLING_INTERVAL,
                ],
            ],
        );
    }

    public function update(
        ApplicationSettingsRequest $request,
        ReverbHealthMonitor $reverbHealthMonitor,
    ): RedirectResponse {
        $validated = $request->validated();
        $settings = ApplicationSetting::current();
        $enablingWebsocket = $validated['application_mode']
            === ApplicationSetting::MODE_WEBSOCKET
            && $settings->application_mode
                !== ApplicationSetting::MODE_WEBSOCKET;

        if ($enablingWebsocket) {
            $websocket = $reverbHealthMonitor->snapshot(
                force: true,
            );

            if (($websocket['selectable'] ?? false) !== true) {
                return back()
                    ->withErrors([
                        'application_mode' => 'WebSocket mode cannot be enabled because the Laravel Reverb server is not live.',
                    ])
                    ->withInput();
            }
        }

        $settings->update($validated);

        return back()->with(
            'laraiot_message',
            'Application settings updated.',
        );
    }

    /**
     * @param  array<string, string>  $formats
     * @return list<array{value: string, label: string}>
     */
    private function formatOptions(
        array $formats,
    ): array {
        $options = [];

        foreach ($formats as $value => $example) {
            $options[] = [
                'value' => $value,
                'label' => $value.' — '.$example,
            ];
        }

        return $options;
    }
}
