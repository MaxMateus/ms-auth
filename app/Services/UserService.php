<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserService
{
    protected UserRepository $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * Verifica se usuário já existe (sem lançar exception)
     *
     * @param string $email
     * @param string $cpf
     * @return array
     */
    public function checkUserExists(string $email, string $cpf): array
    {
        // Normalizar dados
        $normalizedEmail = strtolower(trim($email));
        $normalizedCpf = preg_replace('/\D/', '', $cpf);

        // Verificar se usuário já existe
        $existingUser = $this->userRepository->findByEmailOrCpf($normalizedEmail, $normalizedCpf);

        if (!$existingUser) {
            return [
                'exists' => false,
                'conflicts' => []
            ];
        }

        $conflicts = [];
        
        if ($existingUser->email === $normalizedEmail) {
            $conflicts['email'] = 'Já existe um usuário cadastrado com este e-mail.';
        }
        
        if ($existingUser->cpf === $normalizedCpf) {
            $conflicts['cpf'] = 'Já existe um usuário cadastrado com este CPF.';
        }

        return [
            'exists' => true,
            'conflicts' => $conflicts
        ];
    }

    /**
     * Cria um novo usuário (sem validação de duplicata)
     *
     * @param array $userData
     * @return User
     */
    public function createUser(array $userData): User
    {
        // Normalizar dados
        $userData = $this->normalizeUserData($userData);

        // Criar usuário
        return $this->createUserInDatabase($userData);
    }


    /**
     * Cria o usuário no banco de dados
     *
     * @param array $userData
     * @return User
     */
    protected function createUserInDatabase(array $userData): User
    {
        try {
            // Hash da senha antes da transação para evitar timeout
            $userData['password'] = Hash::make($userData['password']);

            // Usar transação mais simples e direta
            return DB::transaction(function () use ($userData) {
                return $this->userRepository->create($userData);
            }, 3); // Máximo 3 tentativas
        } catch (\Exception $e) {
            // Log do erro específico para debug
            Log::error('Erro ao criar usuário: ' . $e->getMessage(), [
                'email' => $userData['email'] ?? 'N/A',
                'cpf' => $userData['cpf'] ?? 'N/A'
            ]);
            throw $e;
        }
    }

    /**
     * Normaliza os dados do usuário
     *
     * @param array $userData
     * @return array
     */
    protected function normalizeUserData(array $userData): array
    {
        // Preparar dados com validação de campos obrigatórios
        $normalized = [
            'name' => trim($userData['name'] ?? ''),
            'email' => strtolower(trim($userData['email'] ?? '')),
            'password' => $userData['password'] ?? '',
            'cpf' => preg_replace('/\D/', '', $userData['cpf'] ?? ''),
            'phone' => preg_replace('/\D/', '', $userData['phone'] ?? ''),
            'birthdate' => $userData['birthdate'] ?? null,
            'gender' => $userData['gender'] ?? '',
            'accept_terms' => (bool)($userData['accept_terms'] ?? false),
            'street' => trim($userData['street'] ?? ''),
            'number' => trim($userData['number'] ?? ''),
            'neighborhood' => trim($userData['neighborhood'] ?? ''),
            'city' => trim($userData['city'] ?? ''),
            'state' => strtoupper(trim($userData['state'] ?? '')),
            'zip_code' => preg_replace('/\D/', '', $userData['zip_code'] ?? ''),
        ];

        // Campo complement é opcional - só adiciona se existir e não for vazio
        if (isset($userData['complement']) && !empty(trim($userData['complement']))) {
            $normalized['complement'] = trim($userData['complement']);
        }

        return $normalized;
    }

    /**
     * Verifica se usuário existe por email
     *
     * @param string $email
     * @return bool
     */
    public function userExistsByEmail(string $email): bool
    {
        return $this->userRepository->existsByEmail($email);
    }

    /**
     * Verifica se usuário existe por CPF
     *
     * @param string $cpf
     * @return bool
     */
    public function userExistsByCpf(string $cpf): bool
    {
        $normalizedCpf = preg_replace('/\D/', '', $cpf);
        return $this->userRepository->existsByCpf($normalizedCpf);
    }

    /**
     * Busca usuário por ID
     *
     * @param int $id
     * @return User|null
     */
    public function findUserById(int $id): ?User
    {
        return $this->userRepository->findById($id);
    }

    /**
     * Busca usuário por email
     *
     * @param string $email
     * @return User|null
     */
    public function findUserByEmail(string $email): ?User
    {
        return $this->userRepository->findByEmail(strtolower(trim($email)));
    }

    public function validateCpfFormat(string $cpf): bool
    {
        // Remove caracteres não numéricos
        $cpf = preg_replace('/\D/', '', $cpf);

        // CPF precisa ter 11 dígitos
        if (strlen($cpf) !== 11) {
            return false;
        }

        // Elimina CPFs inválidos conhecidos
        if (preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }

        // Validação dos dígitos verificadores
        for ($t = 9; $t < 11; $t++) {
            $d = 0;
            for ($c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) {
                return false;
            }
        }

        return true;
    }
}

