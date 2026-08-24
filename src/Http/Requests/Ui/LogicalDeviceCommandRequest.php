<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Http\Requests\Ui;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class LogicalDeviceCommandRequest extends FormRequest
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
            'command' => [
                'required',
                'string',
                Rule::in(['on', 'off']),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'command' => strtolower(
                trim(
                    (string) $this->input(
                        'command',
                        '',
                    ),
                ),
            ),
        ]);
    }
}
