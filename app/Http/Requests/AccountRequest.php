<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // hoy no hay auth, agregar lógica acá cuando se sume
    }

    public function rules(): array
    {
        return [
            // 'game_id'           => ['required', 'exists:games,id'],
            'game_id'           => ['nullable','exists:games,id'],
            // 'parent_account_id' => [
            //     'nullable',
            //     'integer',
            //     Rule::exists('accounts', 'id'),
            //     // evita que una cuenta sea madre de sí misma (en update)
            //     Rule::notIn(array_filter([$this->route('account')?->id])),
            // ],
            'platform'          => ['required', 'string', 'max:24'],
            'is_dual'           => ['boolean'],
            'account_type'      => ['required', Rule::in(['INDEPENDIENTE', 'MADRE', 'HIJA'])],
            'region'            => ['required', 'string', 'max:32'],

            'email'             => ['required', 'email', 'max:255'],
            'password'          => ['required', 'string', 'max:255'],
            'mail_email'        => ['nullable', 'email', 'max:255'],
            'mail_password'     => ['nullable', 'string', 'max:255'],

            'created_date'      => ['nullable', 'date'],
            'purchased_date'    => ['nullable', 'date'],
            'reset_date'        => ['nullable', 'date'],

            'gamer_tag'         => ['nullable', 'string', 'max:255'],
            'full_name'         => 'nullable|string|max:255',
            'birth_date'        => ['nullable', 'date'],

            'status'            => ['required', Rule::in(['active', 'blocked', 'reset', 'archived'])],
            'notes'             => ['nullable', 'string'],

            // Llaves: array de objetos {id?, position, value}
            'keys'              => ['nullable', 'array', 'max:20'],
            'keys.*.id'         => ['nullable', 'integer', 'exists:account_keys,id'],
            'keys.*.position'   => ['required_with:keys.*.value', 'integer', 'min:1', 'max:20'],
            'keys.*.value'      => ['required_with:keys.*.position', 'string', 'max:64'],

            'is_membership'              => 'boolean',
            'membership_duration_months' => 'nullable|required_if:is_membership,1|in:3,6,12',

            'parent_account_id' => ['nullable', 'exists:accounts,id', Rule::requiredIf(fn () => $this->account_type === 'HIJA')],
            'children_ids'      => ['nullable', 'array'],
            'children_ids.*'    => ['exists:accounts,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'game_id.required' => 'Tenés que seleccionar un juego.',
            'game_id.exists'   => 'El juego seleccionado no existe.',
            'email.email'      => 'El email de la cuenta no tiene formato válido.',
            'mail_email.email' => 'El email del correo no tiene formato válido.',
        ];
    }

    /**
     * Limpia las llaves del input antes de validarlas:
     * descarta filas vacías que aparecen cuando el usuario agrega rows
     * y no las completa.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('keys')) {
            $keys = collect($this->input('keys', []))
                ->filter(fn ($k) => ! empty($k['value'] ?? null))
                ->values()
                ->all();
            $this->merge(['keys' => $keys]);
        }
        if (! $this->boolean('is_membership')) {
            $this->merge(['membership_duration_months' => null]);
        }
    }
}
