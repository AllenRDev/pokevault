<?php

namespace App\Http\Requests;

use App\Models\UserCard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate a card being added to, or updated in, a collection.
 *
 * Both writes accept the same fields, so the rules live here rather than
 * being duplicated per controller action.
 */
class StoreUserCardRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * `external_id` is only accepted when creating, because a stored entry
     * identifies its card through the `card_id` relation and must not be
     * repointed at a different card on update.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'quantity' => [
                'required',
                'integer',
                'min:1',
                'max:'.UserCard::MAX_QUANTITY,
            ],

            'condition' => [
                'required',
                'string',
                Rule::in(UserCard::CONDITIONS),
            ],

            'language' => [
                'required',
                'string',
                Rule::in(UserCard::LANGUAGES),
            ],

            'purchase_price' => [
                'nullable',
                'numeric',
                'min:0',
                'max:99999999.99',
            ],

            'purchase_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];

        if ($this->isCreating()) {
            $rules['external_id'] = ['required', 'string', 'max:100'];
        }

        return $rules;
    }

    /**
     * Whether the request creates a new collection entry.
     */
    public function isCreating(): bool
    {
        return $this->isMethod('POST');
    }
}
