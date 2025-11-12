<?php

namespace App\Http\Requests;

use App\Rules\ValidCpf;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'cpf' => ['required', 'string', 'min:11', 'max:14', new ValidCpf()],
            'phone' => ['required', 'string', 'min:10', 'max:15'],
            'birthdate' => ['required', 'date', 'before:-18 years'],
            'gender' => ['required', Rule::in(['M', 'F', 'Outro'])],
            'accept_terms' => ['required', 'accepted'],
            'street' => ['required', 'string', 'max:255'],
            'number' => ['required', 'string', 'max:10'],
            'complement' => ['nullable', 'string', 'max:100'],
            'neighborhood' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'size:2'],
            'zip_code' => ['required', 'string', 'min:8', 'max:10'],
        ];
    }

    /**
     * Mensagens de erro customizadas
     */
    public function messages(): array
    {
        return [
            'name.required' => 'O nome é obrigatório.',
            'name.min' => 'O nome deve ter pelo menos 3 caracteres.',
            'email.required' => 'O e-mail é obrigatório.',
            'email.email' => 'O e-mail deve ter um formato válido.',
            'password.required' => 'A senha é obrigatória.',
            'password.min' => 'A senha deve ter pelo menos 8 caracteres.',
            'password.confirmed' => 'A confirmação da senha não confere.',
            'cpf.required' => 'O CPF é obrigatório.',
            'cpf.valid_cpf' => 'O CPF informado não é válido.',
            'phone.required' => 'O telefone é obrigatório.',
            'birthdate.required' => 'A data de nascimento é obrigatória.',
            'birthdate.before' => 'Você deve ser maior de 18 anos.',
            'gender.required' => 'O gênero é obrigatório.',
            'gender.in' => 'O gênero deve ser M, F ou Outro.',
            'accept_terms.required' => 'Você deve aceitar os termos de uso.',
            'accept_terms.accepted' => 'Você deve aceitar os termos de uso.',
            'street.required' => 'A rua é obrigatória.',
            'number.required' => 'O número é obrigatório.',
            'neighborhood.required' => 'O bairro é obrigatório.',
            'city.required' => 'A cidade é obrigatória.',
            'state.required' => 'O estado é obrigatório.',
            'state.size' => 'O estado deve ter 2 caracteres (ex: SP).',
            'zip_code.required' => 'O CEP é obrigatório.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => $validator->errors()->first(),
            'errors' => $validator->errors(),
        ], 422));
    }
}
