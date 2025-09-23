<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\User;

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
            'cpf' => ['required', 'digits:11'],
            'phone' => ['required', 'regex:/^\d{10,11}$/'],
            'birthdate' => ['required', 'date', 'before:-18 years'],
            'gender' => ['required', Rule::in(['M','F','Outro'])],
            'accept_terms' => ['required', 'accepted'],
            'street' => ['required', 'string', 'max:255'],
            'number' => ['required', 'string', 'max:10'],
            'complement' => ['required', 'string', 'max:100'],
            'neighborhood' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'size:2'],
            'zip_code' => ['required', 'regex:/^\d{8}$/'],
        ];
    }
    // protected function prepareForValidation(): void
    // {
    //     $this->merge([
    //         'email' => strtolower(trim($this->email ?? '')),
    //         'cpf' => preg_replace('/\D/', '', $this->cpf ?? ''),
    //     ]);
    // }
    // public function withValidator($validator)
    // {
    //     $validator->after(function ($validator) {
    //         $this->validateUniqueUser($validator);
    //     });
    // }
    // protected function validateUniqueUser($validator)
    // {
    //     $email = $this->input('email');
    //     $cpf = $this->input('cpf');
        
    //     $existingUser = User::where('email', $email)
    //                     ->orWhere('cpf', $cpf)
    //                     ->first(['email', 'cpf']);
        
    //     if ($existingUser) {
    //         if ($existingUser->email === $email) {
    //             $validator->errors()->add('email', 'Já existe um usuário cadastrado com este e-mail.');
    //         }
            
    //         if ($existingUser->cpf === $cpf) {
    //             $validator->errors()->add('cpf', 'Já existe um usuário cadastrado com este CPF.');
    //         }
    //     }
    // }
    
}
