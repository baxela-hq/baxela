<?php

namespace Modules\Core\Http\Requests\Admin\Extension;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Modules\Core\Schemas\Extension\ExtensionSchema;

class ToggleExtensionRequest extends FormRequest
{
    /**
     * The identity lives in the route parameters, so there is nothing to
     * validate here — the reconstructed name is checked in withValidator().
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * The extension identity is a Composer package name: vendor and
     * package arrive as separate route parameters and are reconstructed
     * here, then held to the same SLUG_PATTERN the CLI enforces. A
     * malformed name fails validation and never reaches the action.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $slug = $this->route('vendor').'/'.$this->route('package');

            if (preg_match('~'.ExtensionSchema::SLUG_PATTERN.'~', $slug) !== 1) {
                $validator->errors()->add(
                    'slug',
                    'The extension name must be a Composer package name like vendor/baxela-package.'
                );
            }
        });
    }
}
