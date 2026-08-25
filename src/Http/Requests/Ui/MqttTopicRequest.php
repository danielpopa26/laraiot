<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Http\Requests\Ui;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class MqttTopicRequest extends FormRequest
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
            'purpose' => [
                'required',
                Rule::in([
                    'state',
                    'command',
                ]),
            ],
            'topic' => [
                'required',
                'string',
                'max:65535',
            ],
            'payload_format' => [
                Rule::requiredIf(
                    fn (): bool => $this->input('purpose')
                            === 'state',
                ),
                'nullable',
                Rule::in([
                    'raw',
                    'json',
                ]),
            ],
            'value_path' => [
                Rule::requiredIf(
                    fn (): bool => $this->input('purpose')
                            === 'state'
                        && $this->input(
                            'payload_format',
                        ) === 'json',
                ),
                'nullable',
                'string',
                'max:1000',
            ],
            'value_map' => [
                'nullable',
                'array',
            ],
            'value_map.*.source' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'value_map.*.target' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'command_on' => [
                Rule::requiredIf(
                    fn (): bool => $this->input('purpose')
                            === 'command',
                ),
                'nullable',
                'string',
            ],
            'command_off' => [
                Rule::requiredIf(
                    fn (): bool => $this->input('purpose')
                            === 'command',
                ),
                'nullable',
                'string',
            ],
            'qos' => [
                'required',
                'integer',
                Rule::in([
                    0,
                    1,
                    2,
                ]),
            ],
            'retain' => [
                'required',
                'boolean',
            ],
            'is_enabled' => [
                'required',
                'boolean',
            ],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $topic = trim(
                    (string) $this->input(
                        'topic',
                        '',
                    ),
                );

                if (
                    str_contains($topic, '+')
                    || str_contains($topic, '#')
                ) {
                    $validator->errors()->add(
                        'topic',
                        'LaraIoT MQTT topics must be exact topics without wildcard characters.',
                    );
                }
            },
        ];
    }
}
