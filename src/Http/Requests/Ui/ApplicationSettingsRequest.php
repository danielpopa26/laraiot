<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Http\Requests\Ui;

use Danpopa\LaraIoT\Models\ApplicationSetting;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ApplicationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'application_mode' => [
                'required',
                Rule::in([
                    ApplicationSetting::MODE_POLLING,
                    ApplicationSetting::MODE_WEBSOCKET,
                ]),
            ],
            'polling_interval' => [
                'required',
                'integer',
                'min:'.ApplicationSetting::MIN_POLLING_INTERVAL,
                'max:'.ApplicationSetting::MAX_POLLING_INTERVAL,
            ],
            'timezone' => [
                'required',
                'string',
                Rule::in(
                    DateTimeZone::listIdentifiers(),
                ),
            ],
            'date_format' => [
                'required',
                'string',
                Rule::in(
                    array_keys(
                        ApplicationSetting::DATE_FORMATS,
                    ),
                ),
            ],
            'time_format' => [
                'required',
                'string',
                Rule::in(
                    array_keys(
                        ApplicationSetting::TIME_FORMATS,
                    ),
                ),
            ],
        ];
    }
}
